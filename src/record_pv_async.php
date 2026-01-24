<?php
/**
 * 异步PV记录脚本
 * 用于在后台记录API请求统计，不阻塞主请求
 */

if (!defined('METING_API')) {
    define('METING_API', true);
}

// 获取传入的参数
$ip = isset($argv[1]) ? $argv[1] : '';
$ref = isset($argv[2]) ? $argv[2] : '';
$server = isset($argv[3]) ? $argv[3] : '';
$type = isset($argv[4]) ? $argv[4] : '';
$id = isset($argv[5]) ? $argv[5] : '';

if ($ip && $ref && $server && $type && $id) {
    // 加载PV类
    include __DIR__ . '/PV.php';
    
    try {
        $pv = new \Metowolf\PV();
        $pv->record($ip, $ref, $server, $type, $id);
    } catch (Exception $e) {
        // 异步记录失败，静默处理，不影响主请求
        error_log('PV record error: ' . $e->getMessage());
    }
}
