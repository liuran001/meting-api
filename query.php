<?php
/**
 * 优化的查询接口
 * 
 * 特性:
 * 1. 使用STATS表进行快速查询（性能优化）
 * 2. 支持详情查询（兼容原有逻辑）
 * 3. 安全的SQL执行（防注入）
 * 4. 支持获取总解析次数
 */

require_once __DIR__ . '/src/PV.php';

// 加载配置文件
$config = require __DIR__ . '/config/loader.php';

use Metowolf\PV;

// 安全配置
$secret = $config['query_secret'];
$forbidden_arr = $config['query_forbidden'];

// 获取参数
$input = $_GET['query'] ?? null;
$key = $_GET['key'] ?? null;
$type = $_GET['type'] ?? null;

// 初始化PV对象
$pv = new PV(
    $config['db_path'],
    $config['db_enable_detail'],
    $config['db_enable_stats']
);

// 默认查询：获取总解析次数
if (!$input && !$type) {
    $total = $pv->getTotalCount();
    echo $total;
    exit;
}

// 快速查询类型
if ($type === 'total') {
    // 获取总解析次数
    $total = $pv->getTotalCount();
    echo $total;
    exit;
} elseif ($type === 'stats') {
    // 获取统计信息
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    $stats = $pv->getStats($days);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($stats, JSON_UNESCAPED_UNICODE);
    exit;
} elseif ($type === 'count') {
    // 获取指定资源的解析次数
    $server = $_GET['server'] ?? null;
    $req_type = $_GET['req_type'] ?? null;
    $id = $_GET['id'] ?? null;
    
    if ($server && $req_type && $id) {
        $count = $pv->getCount($server, $req_type, $id);
        echo $count;
    } else {
        echo "0";
    }
    exit;
} elseif ($type === 'clean_cache') {
    // 清空缓存（需要权限验证）
    if ($key !== $secret) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
    
    $msg = [];
    
    // 1. 清空 APCu
    if (function_exists('apcu_clear_cache')) {
        if (apcu_clear_cache()) {
            $msg[] = "APCu cache cleared.";
        } else {
            $msg[] = "Failed to clear APCu cache.";
        }
    } else {
        $msg[] = "APCu extension not loaded.";
    }
    
    echo implode("\n", $msg);
    exit;
}

// 自定义SQL查询（需要权限验证）
if ($input) {
    $lower_input = strtolower($input);
    
    // 检查是否包含禁止的关键字
    $has_forbidden = false;
    foreach ($forbidden_arr as $keyword) {
        if (strpos($lower_input, $keyword) !== false) {
            $has_forbidden = true;
            break;
        }
    }
    
    // 如果包含禁止关键字且没有正确的key，拒绝执行
    if ($has_forbidden && $key !== $secret) {
        http_response_code(403);
        echo "Forbidden: SQL contains restricted keywords";
        exit;
    }
    
    // 安全检查：只允许SELECT查询（如果没有key）
    if ($key !== $secret && strpos($lower_input, 'select') === false) {
        http_response_code(403);
        echo "Forbidden: Only SELECT queries allowed";
        exit;
    }
    
    try {
        $data = $pv->query($input);
        
        // 如果是统计查询，返回JSON格式
        if (strpos($lower_input, 'count') !== false || strpos($lower_input, 'sum') !== false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 返回HTML表格格式（兼容原有query.php）
        if (empty($data)) {
            echo "No results";
            exit;
        }
        
        // 获取列名
        $columns = array_keys($data[0]);
        $htmlTableColumn = "";
        $has_time = false;
        
        foreach ($columns as $col) {
            $htmlTableColumn .= "<th>" . htmlspecialchars($col) . "</th>";
            if ($col === 'TIME') {
                $has_time = true;
            }
        }
        
        // 如果有时间列，添加格式化时间列
        if ($has_time) {
            $htmlTableColumn .= "<th>FORMATTED_TIME</th>";
        }
        
        // 生成表格内容
        $htmlBody = "";
        foreach ($data as $row) {
            $htmlBody .= "<tr>";
            foreach ($columns as $col) {
                $value = $row[$col];
                if ($col === 'TIME' && is_numeric($value)) {
                    $htmlBody .= "<td>" . htmlspecialchars($value) . "</td>";
                } else {
                    $htmlBody .= "<td>" . htmlspecialchars($value) . "</td>";
                }
            }
            
            // 添加格式化时间
            if ($has_time && isset($row['TIME']) && is_numeric($row['TIME'])) {
                $formatted = date('Y-m-d H:i:s', $row['TIME']);
                $htmlBody .= "<td>" . htmlspecialchars($formatted) . "</td>";
            }
            $htmlBody .= "</tr>";
        }
        
        // 输出完整表格
        echo '<div><table style="text-align:center;margin:auto;">';
        echo '<thead><tr>' . $htmlTableColumn . '</tr></thead>';
        echo '<tbody>' . $htmlBody . '</tbody>';
        echo '</table></div>';
        echo '<div style="text-align:center;margin:auto;">Operation done successfully, count: ' . count($data) . '</div>';
        
    } catch (Exception $e) {
        http_response_code(500);
        echo 'SQL Execute Error, Message: ' . htmlspecialchars($e->getMessage());
        exit;
    }
}

// 如果没有查询参数，显示帮助信息
echo "<div style='text-align:center;margin:auto;'>";
echo "<h2>Meting-API PV查询接口</h2>";
echo "<h3>使用方法:</h3>";
echo "<p><strong>获取总解析次数:</strong> /query.php?type=total</p>";
echo "<p><strong>获取统计信息:</strong> /query.php?type=stats&days=30</p>";
echo "<p><strong>获取指定资源次数:</strong> /query.php?type=count&server=netease&type=song&id=12345</p>";
echo "<p><strong>自定义查询:</strong> /query.php?query=SELECT * FROM DETAIL LIMIT 10</p>";
echo "<p><strong>安全删除/更新:</strong> /query.php?query=DELETE FROM DETAIL WHERE id=1&key=qqcn10086</p>";
echo "</div>";
