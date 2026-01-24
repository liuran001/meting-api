<?php
namespace Metowolf;

/**
 * PV统计类 - 用于记录和查询API请求统计
 * 
 * 特性:
 * 1. 兼容原有pv.php的表结构(DETAIL表)
 * 2. 使用STATS表优化查询性能
 * 3. 支持实时统计和批量统计
 * 4. 避免popen调用，直接在内存中处理
 */
class PV
{
    private $db;
    private $db_path;
    private $enable_detail;
    private $enable_stats;

    /**
     * 构造函数
     * 
     * @param string $db_path 数据库路径
     * @param bool $enable_detail 是否启用详情记录
     * @param bool $enable_stats 是否启用统计表
     */
    public function __construct($db_path = null, $enable_detail = true, $enable_stats = true)
    {
        if ($db_path === null) {
            $db_path = __DIR__ . '/../db/pv.db';
        }
        $this->db_path = $db_path;
        $this->enable_detail = $enable_detail;
        $this->enable_stats = $enable_stats;
        $this->initDatabase();
    }

    /**
     * 初始化数据库
     */
    private function initDatabase()
    {
        $db_dir = dirname($this->db_path);
        if (!is_dir($db_dir)) {
            mkdir($db_dir, 0755, true);
        }

        $this->db = new \SQLite3($this->db_path);
        
        // 创建表（如果不存在）
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS DETAIL (
                ID INTEGER PRIMARY KEY AUTOINCREMENT,
                IP TEXT NOT NULL,
                TIME INTEGER NOT NULL,
                REFERER TEXT,
                SERVER TEXT,
                TYPE TEXT,
                SINGID TEXT
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS STATS (
                ID INTEGER PRIMARY KEY AUTOINCREMENT,
                SERVER TEXT NOT NULL,
                TYPE TEXT NOT NULL,
                SINGID TEXT NOT NULL,
                COUNT INTEGER DEFAULT 1,
                LAST_TIME INTEGER NOT NULL,
                UNIQUE(SERVER, TYPE, SINGID)
            )
        ");

        // 创建索引
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_detail_time ON DETAIL(TIME)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_detail_server ON DETAIL(SERVER)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_detail_type ON DETAIL(TYPE)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_detail_singid ON DETAIL(SINGID)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_stats_singid ON STATS(SINGID)");

        // 自动升级：检测旧数据并填充STATS表
        $this->autoUpgrade();
    }

    /**
     * 自动升级：从旧的DETAIL表数据生成STATS表
     */
    private function autoUpgrade()
    {
        // 检查STATS表是否为空
        $stats_count = $this->db->querySingle("SELECT COUNT(*) FROM STATS");
        
        // 如果STATS表为空，但DETAIL表有数据，则进行升级
        if ($stats_count == 0) {
            $detail_count = $this->db->querySingle("SELECT COUNT(*) FROM DETAIL");
            
            if ($detail_count > 0) {
                // 从DETAIL表数据填充STATS表
                $sql = "
                    INSERT OR IGNORE INTO STATS (SERVER, TYPE, SINGID, COUNT, LAST_TIME)
                    SELECT SERVER, TYPE, SINGID, COUNT(*) as COUNT, MAX(TIME) as LAST_TIME
                    FROM DETAIL
                    WHERE SERVER IS NOT NULL AND TYPE IS NOT NULL AND SINGID IS NOT NULL
                    GROUP BY SERVER, TYPE, SINGID
                ";
                
                $this->db->exec($sql);
                
                // 记录升级日志（可选）
                error_log("[PV] Auto-upgrade completed: " . $detail_count . " records migrated to STATS table");
            }
        }
    }

    /**
     * 记录请求
     * 
     * @param string $ip IP地址
     * @param string $ref Referer
     * @param string $server 服务器类型
     * @param string $type 请求类型
     * @param string $id 资源ID
     * @return bool 成功返回true
     */
    public function record($ip, $ref, $server, $type, $id)
    {
        $now = time();
        $success = true;

        // 记录到详情表（兼容原有逻辑）
        if ($this->enable_detail) {
            $sql = "INSERT INTO DETAIL (IP, TIME, REFERER, SERVER, TYPE, SINGID) 
                    VALUES (:ip, :time, :ref, :server, :type, :id)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':ip', $ip, \SQLITE3_TEXT);
            $stmt->bindValue(':time', $now, \SQLITE3_INTEGER);
            $stmt->bindValue(':ref', $ref, \SQLITE3_TEXT);
            $stmt->bindValue(':server', $server, \SQLITE3_TEXT);
            $stmt->bindValue(':type', $type, \SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, \SQLITE3_TEXT);
            
            if (!$stmt->execute()) {
                $success = false;
            }
        }

        // 更新统计表（性能优化）
        if ($this->enable_stats) {
            $sql = "INSERT INTO STATS (SERVER, TYPE, SINGID, COUNT, LAST_TIME) 
                    VALUES (:server, :type, :id, 1, :time)
                    ON CONFLICT(SERVER, TYPE, SINGID) 
                    DO UPDATE SET COUNT = COUNT + 1, LAST_TIME = :time";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':server', $server, \SQLITE3_TEXT);
            $stmt->bindValue(':type', $type, \SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, \SQLITE3_TEXT);
            $stmt->bindValue(':time', $now, \SQLITE3_INTEGER);
            
            if (!$stmt->execute()) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 手动执行升级（用于已有大量数据的情况）
     * 
     * @return int 迁移的记录数
     */
    public function upgrade()
    {
        $sql = "
            INSERT OR IGNORE INTO STATS (SERVER, TYPE, SINGID, COUNT, LAST_TIME)
            SELECT SERVER, TYPE, SINGID, COUNT(*) as COUNT, MAX(TIME) as LAST_TIME
            FROM DETAIL
            WHERE SERVER IS NOT NULL AND TYPE IS NOT NULL AND SINGID IS NOT NULL
            GROUP BY SERVER, TYPE, SINGID
        ";
        
        $this->db->exec($sql);
        return $this->db->changes();
    }

    /**
     * 获取总解析次数（从STATS表，性能最优）
     * 
     * @return int 总次数
     */
    public function getTotalCount()
    {
        $sql = "SELECT SUM(COUNT) as total FROM STATS";
        $result = $this->db->querySingle($sql, true);
        $total = $result['total'] ?? 0;
        
        // 如果STATS表为空，从DETAIL表计算（兼容旧数据）
        if ($total == 0) {
            $detail_count = $this->db->querySingle("SELECT COUNT(*) FROM DETAIL");
            if ($detail_count > 0) {
                // 自动升级并返回结果
                $this->upgrade();
                $result = $this->db->querySingle($sql, true);
                $total = $result['total'] ?? 0;
            }
        }
        
        return $total;
    }

    /**
     * 获取指定资源的解析次数
     * 
     * @param string $server 服务器类型
     * @param string $type 请求类型
     * @param string $id 资源ID
     * @return int 次数
     */
    public function getCount($server, $type, $id)
    {
        $sql = "SELECT COUNT FROM STATS WHERE SERVER = :server AND TYPE = :type AND SINGID = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':server', $server, \SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, \SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, \SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(\SQLITE3_ASSOC);
        
        if ($row) {
            return $row['COUNT'];
        }
        
        // 如果STATS表没有数据，从DETAIL表查询（兼容旧数据）
        $sql = "SELECT COUNT(*) as count FROM DETAIL WHERE SERVER = :server AND TYPE = :type AND SINGID = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':server', $server, \SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, \SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, \SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(\SQLITE3_ASSOC);
        
        return $row ? $row['count'] : 0;
    }

    /**
     * 查询详情（兼容原有query.php）
     * 
     * @param string $sql SQL查询语句
     * @return array 结果集
     */
    public function query($sql)
    {
        $result = $this->db->query($sql);
        $data = [];
        while ($row = $result->fetchArray(\SQLITE3_ASSOC)) {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 获取统计信息
     * 
     * @param int $days 天数
     * @return array 统计信息
     */
    public function getStats($days = 30)
    {
        $since = time() - ($days * 86400);
        
        $stats = [
            'total' => $this->getTotalCount(),
            'recent' => 0,
            'by_server' => [],
            'by_type' => [],
            'upgraded' => false
        ];

        // 检查是否需要升级
        $stats_count = $this->db->querySingle("SELECT COUNT(*) FROM STATS");
        if ($stats_count == 0) {
            $detail_count = $this->db->querySingle("SELECT COUNT(*) FROM DETAIL");
            if ($detail_count > 0) {
                $this->upgrade();
                $stats['upgraded'] = true;
            }
        }

        // 近期请求
        $sql = "SELECT COUNT(*) as count FROM DETAIL WHERE TIME >= :since";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':since', $since, \SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(\SQLITE3_ASSOC);
        $stats['recent'] = $row['count'] ?? 0;

        // 按服务器统计
        $sql = "SELECT SERVER, COUNT(*) as count FROM DETAIL WHERE TIME >= :since GROUP BY SERVER";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':since', $since, \SQLITE3_INTEGER);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(\SQLITE3_ASSOC)) {
            $stats['by_server'][$row['SERVER']] = $row['count'];
        }

        // 按类型统计
        $sql = "SELECT TYPE, COUNT(*) as count FROM DETAIL WHERE TIME >= :since GROUP BY TYPE";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':since', $since, \SQLITE3_INTEGER);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(\SQLITE3_ASSOC)) {
            $stats['by_type'][$row['TYPE']] = $row['count'];
        }

        return $stats;
    }

    /**
     * 关闭数据库连接
     */
    public function close()
    {
        if ($this->db) {
            $this->db->close();
        }
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->close();
    }
}
