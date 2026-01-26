<?php
/**
 * 配置加载器
 * 
 * 自动加载配置文件，支持本地配置覆盖
 */

// 加载默认配置
$config = require __DIR__ . '/default.php';

// 如果存在本地配置文件，则合并覆盖
$local_config_file = __DIR__ . '/local.php';
if (file_exists($local_config_file)) {
    $local_config = require $local_config_file;
    $config = array_merge($config, $local_config);
}

// 定义常量（保持向后兼容）
if (!defined('API_URI')) {
    // 在 CLI 模式下提供默认值
    if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
        define('API_URI', $config['api_uri'] ?: 'http://localhost:8000');
    } else {
        define('API_URI', !empty($config['api_uri']) ? $config['api_uri'] : api_uri());
    }
}
if (!defined('TLYRIC')) {
    define('TLYRIC', $config['tlyric']);
}
if (!defined('CACHE_TIME')) {
    define('CACHE_TIME', $config['cache_time']);
}
if (!defined('URL_CACHE_TIME')) {
    define('URL_CACHE_TIME', $config['url_cache_time']);
}
if (!defined('PLAYLIST_CACHE_TIME')) {
    define('PLAYLIST_CACHE_TIME', $config['playlist_cache_time']);
}
if (!defined('APCU_CACHE')) {
    define('APCU_CACHE', $config['apcu_cache']);
}
if (!defined('AUTH')) {
    define('AUTH', $config['auth']);
}
if (!defined('AUTH_SECRET')) {
    define('AUTH_SECRET', $config['auth_secret']);
}
if (!defined('NETEASE_COOKIE')) {
    define('NETEASE_COOKIE', $config['netease_cookie']);
}
if (!defined('RATE_LIMIT_ENABLE')) {
    define('RATE_LIMIT_ENABLE', $config['rate_limit']);
}
if (!defined('RATE_LIMIT_HEADER')) {
    define('RATE_LIMIT_HEADER', $config['rate_limit_header']);
}
if (!defined('RATE_LIMIT_WINDOW')) {
    define('RATE_LIMIT_WINDOW', $config['rate_limit_window']);
}
if (!defined('RATE_LIMIT_COUNT')) {
    define('RATE_LIMIT_COUNT', $config['rate_limit_count']);
}
if (!defined('FORCE_IMAGE_REDIRECT')) {
    define('FORCE_IMAGE_REDIRECT', $config['force_image_redirect']);
}
if (!defined('METING_API')) {
    define('METING_API', true);
}

// 返回配置数组供其他地方使用
return $config;

/**
 * 自动检测 API URI（仅在函数未定义时创建）
 */
if (!function_exists('api_uri')) {
    function api_uri()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['SCRIPT_NAME'];
        $path = dirname($script);
        
        return $protocol . $host . ($path == '/' ? '' : $path);
    }
}
