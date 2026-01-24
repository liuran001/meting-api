<?php
/**
 * PV详细记录清理工具
 * 用于清除DETAIL表中的历史记录数据
 */

$db_path = __DIR__ . '/../db/pv.db';

if (!file_exists($db_path)) {
    echo "数据库不存在: $db_path\n";
    exit(1);
}

try {
    $db = new SQLite3($db_path);
    
    // 获取DETAIL表记录数
    $detail_count = $db->querySingle("SELECT COUNT(*) FROM DETAIL");
    
    if ($detail_count == 0) {
        echo "DETAIL表已为空，无需清理\n";
        exit(0);
    }
    
    echo "开始清理PV详细记录...\n";
    echo "待删除记录数: $detail_count\n\n";
    
    // 清空DETAIL表
    $result = $db->exec("DELETE FROM DETAIL");
    
    if ($result) {
        // 清理数据库空间
        $db->exec("VACUUM");
        echo "✓ 详细记录清理成功\n";
        echo "✓ 已清空DETAIL表的所有数据\n";
        echo "✓ 已优化数据库存储空间\n\n";
        
        // 显示剩余数据
        $stats_count = $db->querySingle("SELECT COUNT(*) FROM STATS");
        echo "保留的统计数据(STATS表): $stats_count 条\n";
    } else {
        echo "✗ 清理失败\n";
        exit(1);
    }
    
    $db->close();
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
