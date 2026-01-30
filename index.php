<?php
// 路由处理 - 如果请求的是 query.php，直接处理
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/query.php') !== false) {
    include __DIR__ . '/query.php';
    exit;
}

// 路由处理 - 如果请求的是 handsome.php，开启兼容模式
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/handsome.php') !== false) {
    $_GET['handsome'] = 'true';
    if (!defined('HANDSOME_MODE')) {
        define('HANDSOME_MODE', true);
    }
}

// 加载配置文件
$config = require __DIR__ . '/config/loader.php';

$raw_type = isset($_GET['type']) ? $_GET['type'] : null;
if (!isset($raw_type) || $raw_type === '' || (!isset($_GET['id']) && $raw_type !== 'debug')) {
    include __DIR__ . '/public/index.php';
    exit;
}

$server = filter_input(INPUT_GET, 'server', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'netease';
$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_SPECIAL_CHARS);
$br = filter_input(INPUT_GET, 'br', FILTER_SANITIZE_SPECIAL_CHARS) ?: $config['default_br'];
$yrc = filter_input(INPUT_GET, 'yrc', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'false';
$handsome = filter_input(INPUT_GET, 'handsome', FILTER_SANITIZE_SPECIAL_CHARS);
// filter_input 不会读取运行时写入的 $_GET，这里兜底一次
if ($handsome === null || $handsome === false || $handsome === '') {
    $handsome = isset($_GET['handsome']) ? $_GET['handsome'] : 'false';
}
// 规范化为字符串 'true' 或 'false'
if (defined('HANDSOME_MODE') && HANDSOME_MODE === true) {
    $handsome = 'true';
} else {
    $handsome = strtolower(trim($handsome)) === 'true' ? 'true' : 'false';
}
$picsize = filter_input(INPUT_GET, 'picsize', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
$refresh = isset($_GET['refresh']) && ($_GET['refresh'] === 'true' || $_GET['refresh'] === '1');
$img_redirect = filter_input(INPUT_GET, 'img_redirect', FILTER_SANITIZE_SPECIAL_CHARS);
if ($img_redirect === null || $img_redirect === '') {
    $img_redirect = defined('FORCE_IMAGE_REDIRECT') && FORCE_IMAGE_REDIRECT ? 'true' : 'false';
} else {
    $img_redirect = strtolower(trim($img_redirect)) === 'true' ? 'true' : 'false';
}
$lrctype = filter_input(INPUT_GET, 'lrctype', FILTER_SANITIZE_SPECIAL_CHARS);
// 是否启用流式输出（仅用于 playlist）
$stream = filter_input(INPUT_GET, 'stream', FILTER_SANITIZE_SPECIAL_CHARS);
if ($stream === null || $stream === false || $stream === '') {
    $stream = isset($_GET['stream']) ? $_GET['stream'] : 'false';
}
$stream = strtolower(trim($stream));
$stream = ($stream === 'true' || $stream === '1');


if (AUTH) {
    $auth = isset($_GET['auth']) ? $_GET['auth'] : '';
    if (in_array($type, ['url', 'pic', 'lrc'])) {
        if ($auth == '' || $auth != auth($server . $type . $id)) {
            http_response_code(403);
            exit;
        }
    }
}

// PV统计 - 异步记录所有API请求，防止高并发时阻塞主请求
$ip = getIP();
$ref = getRef();
// 在后台异步运行PV记录脚本，不阻塞主请求
$pv_script = __DIR__ . '/src/record_pv_async.php';
// 兼容多种环境获取PHP执行路径
if (defined('PHP_EXECUTABLE')) {
    $php_path = PHP_EXECUTABLE;
} else {
    // 降级方案：尝试从常见位置找到php
    $php_path = null;
    foreach (['/usr/bin/php', '/usr/local/bin/php', 'php'] as $path) {
        if (is_file($path) || `which $path 2>/dev/null`) {
            $php_path = $path;
            break;
        }
    }
    if (!$php_path) {
        $php_path = 'php'; // 最后的备选方案
    }
}
// 使用 proc_open 在后台运行PV记录，立即返回
$descriptors = array();
$process = @proc_open(
    escapeshellcmd($php_path) . ' ' . escapeshellarg($pv_script) . ' ' .
    escapeshellarg($ip) . ' ' .
    escapeshellarg($ref) . ' ' .
    escapeshellarg($server) . ' ' .
    escapeshellarg($type) . ' ' .
    escapeshellarg($id),
    $descriptors,
    $pipes
);
if (is_resource($process)) {
    proc_close($process);
}

// 数据格式
if ($handsome == 'true' && in_array($type, ['song', 'playlist'])) {
    header('content-type: application/javascript; charset=utf-8;');
} else if (in_array($type, ['song', 'playlist', 'search', 'debug'])) {
    header('content-type: application/json; charset=utf-8;');
} else if (in_array($type, ['name', 'lrc', 'artist', 'album'])) {
    header('content-type: text/plain; charset=utf-8;');
}

// 允许跨站
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// include __DIR__ . '/vendor/autoload.php';
// you can use 'Meting.php' instead of 'autoload.php'
include __DIR__ . '/src/Meting.php';

use Metowolf\Meting;

$api = new Meting($server);
$api->format(true);
$api->lrctype($lrctype);

if (!defined('METING_API')) {
    define('METING_API', true);
}

// QQ音乐 Cookie 配置
$qmck_file = $config['qm_cookie_file'];

if (file_exists($qmck_file)) {
    require $qmck_file;
    if (!empty($QMCookie) && $QMCookie != '' && $QMCookie != 'null' && $QMCookie != 'undefined' && $QMCookie != null) {
        $tencent_cookie = $QMCookie;
    } else {
        $tencent_cookie = 'local';
    }
} else {
    $tencent_cookie = 'local';
};

// 设置cookie
if ($server == 'netease') {
    // 从配置读取网易云Cookie
    $netease_cookie = $config['netease_cookie'] ?? '';
    $api->cookie($netease_cookie);
} else if ($server == 'tencent' && $tencent_cookie == 'local') {
    $api->cookie('');
} else if ($server == 'tencent' && $tencent_cookie != 'local') {
    $api->cookie($tencent_cookie);
} else {
    echo '{"error":"不支持的音乐源"}';
    exit;
};

// 统一使用yrc参数，内部用dwrc变量处理
if ($yrc == 'true') {
    $api->dwrc(true);
    $api->bakdwrc(false);
    $dwrc = 'true';
} else if ($yrc == 'open') {
    $api->dwrc(true);
    $api->bakdwrc(true);
    $dwrc = 'open';
} else {
    $api->dwrc(false);
    $api->bakdwrc(false);
    $dwrc = 'false';
};

if ($type == 'debug') {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Surrogate-Control: no-store');
    $debug_profile = getRateLimitDebugProfile();
    $debug_profile['scope'] = 'debug';
    if (!checkRateLimit($ip, $debug_profile['ip'], $debug_profile['total'], $debug_profile['window'], $debug_profile['scope'])) {
        http_response_code(429);
        echo '{"error":"rate limit exceeded"}';
        exit;
    }

    $profiles = getRateLimitProfiles();
    $profiles['debug'] = $debug_profile;

    $status = getRateLimitStatus($ip, $profiles);

    $payload = array(
        'time' => date('c'),
        'ip' => $ip,
        'storage' => getRateLimitStorage(),
        'ip_counts' => $status['ip'],
        'total_counts' => $status['total']
    );
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
} else if ($type == 'playlist') {

    // 缓存键生成 - 不包含handsome参数，统一缓存
    $cache_key = $server . 'playlist' . $id . '_br' . $br . '_img' . $img_redirect . '_dwrc' . $dwrc . '_lrctype' . ($lrctype ?? 'default');

    if (APCU_CACHE) {
        // 强制刷新频率限制 (60秒)
        if ($refresh) {
            $lock_key = 'refresh_lock_' . $cache_key;
            if (apcu_exists($lock_key)) {
                $refresh = false; // 忽略刷新
            } else {
                apcu_store($lock_key, 1, 60);
            }
        }

        if (!$refresh && apcu_exists($cache_key)) {
            $rate_profile = getRateProfile($type, true);
            if (!checkRateLimit($ip, $rate_profile['ip'], $rate_profile['total'], $rate_profile['window'], $rate_profile['scope'])) {
                http_response_code(429);
                echo '{"error":"rate limit exceeded"}';
                exit;
            }
            setCacheHeaders('playlist');
            $cached_data = apcu_fetch($cache_key);
            // 根据当前请求的handsome状态动态转换数据
            if ($handsome == 'true') {
                $playlist_array = json_decode($cached_data, true);
                foreach ($playlist_array as &$item) {
                    // 转换 pic 为 cover
                    if (isset($item['pic'])) {
                        $item['cover'] = $item['pic'];
                        unset($item['pic']);
                    }
                    // 移除 album 字段
                    if (isset($item['album'])) {
                        unset($item['album']);
                    }
                }
                echo json_encode($playlist_array);
            } else {
                echo $cached_data;
            }
            exit;
        }
    }

    $rate_profile = getRateProfile($type, false);
    if (!checkRateLimit($ip, $rate_profile['ip'], $rate_profile['total'], $rate_profile['window'], $rate_profile['scope'])) {
        http_response_code(429);
        echo '{"error":"rate limit exceeded"}';
        exit;
    }

    $stream_enabled = ($stream === true);

    if ($stream_enabled && $server == 'netease') {
        $track_ids = $api->playlistTrackIds($id);
        if (empty($track_ids)) {
            echo '{"error":"歌单不存在或为空"}';
            exit;
        }
        // 流式输出：减少内存占用并尽快返回首包
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @ob_implicit_flush(1);
        header('X-Accel-Buffering: no');
        @set_time_limit(0);
        setCacheHeaders('playlist');

        echo '[';
        $cache_json = APCU_CACHE ? '[' : null;
        $first = true;
        $count = 0;
        $id_batches = array_chunk($track_ids, 500);
        foreach ($id_batches as $batch) {
            $songs = $api->songDetailBatch($batch);
            if (empty($songs)) {
                continue;
            }
            foreach ($songs as $raw_song) {
                $song = $api->formatSong($raw_song);
                if (empty($song)) {
                    continue;
                }

                $lrc_url = API_URI . '?server=' . $song['source'] . '&type=lrc&id=' . $song['lyric_id'] . (AUTH ? '&auth=' . auth($song['source'] . 'lrc' . $song['lyric_id']) : '');
                if ($dwrc == 'true') {
                    $lrc_url .= '&yrc=true';
                } else if ($dwrc == 'open') {
                    $lrc_url .= '&yrc=open';
                }
                if ($lrctype !== null) {
                    $lrc_url .= '&lrctype=' . $lrctype;
                }

                $item = array(
                    'name'   => $song['name'],
                    'artist' => implode('/', $song['artist']),
                    'album'  => $song['album'],
                    'url'    => API_URI . '?server=' . $song['source'] . '&type=url&id=' . $song['url_id'] . (AUTH ? '&auth=' . auth($song['source'] . 'url' . $song['url_id']) : ''),
                    'pic'    => get_pic_url($api, $song['source'], $song['pic_id'], $picsize, $img_redirect),
                    'lrc'    => $lrc_url
                );

                $output_item = $item;
                if ($handsome == 'true') {
                    $output_item['cover'] = $output_item['pic'];
                    unset($output_item['pic']);
                    unset($output_item['album']);
                }

                $is_first = $first;
                if ($is_first) {
                    $first = false;
                } else {
                    echo ',';
                }
                $item_json = json_encode($output_item);
                echo $item_json;
                if ($cache_json !== null) {
                    $cache_item_json = ($handsome == 'true') ? json_encode($item) : $item_json;
                    $cache_json .= $is_first ? $cache_item_json : ',' . $cache_item_json;
                }
                $count++;
                if (($count % 50) === 0) {
                    echo "\n";
                    @flush();
                }
            }
        }
        echo ']';
        if ($cache_json !== null) {
            $cache_json .= ']';
            apcu_store($cache_key, $cache_json, PLAYLIST_CACHE_TIME);
        }
        @flush();
        exit;
    }
    $data = $api->playlist($id);
    if ($data == '[]') {
        echo '{"error":"歌单不存在或为空"}';
        exit;
    }
    $data = json_decode($data);
    $playlist = array();

    // 统一使用标准格式构建数据（始终包含 album 和 pic 字段）
    foreach ($data as $song) {
        $lrc_url = API_URI . '?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($song->source . 'lrc' . $song->lyric_id) : '');
        if ($dwrc == 'true') {
            $lrc_url .= '&yrc=true';
        } else if ($dwrc == 'open') {
            $lrc_url .= '&yrc=open';
        }
        if ($lrctype !== null) {
            $lrc_url .= '&lrctype=' . $lrctype;
        }

        $playlist[] = array(
            'name'   => $song->name,
            'artist' => implode('/', $song->artist),
            'album'  => $song->album,  // 始终包含 album 字段
            'url'    => API_URI . '?server=' . $song->source . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($song->source . 'url' . $song->url_id) : ''),
            'pic'    => get_pic_url($api, $song->source, $song->pic_id, $picsize, $img_redirect),
            'lrc'    => $lrc_url
        );
    }

    $playlist_json = json_encode($playlist);

    if (APCU_CACHE && !$stream_enabled) {
        apcu_store($cache_key, $playlist_json, PLAYLIST_CACHE_TIME);
    }

    // 根据当前请求的handsome状态动态转换并输出
    setCacheHeaders('playlist');
    if ($handsome == 'true') {
        foreach ($playlist as &$item) {
            // 转换 pic 为 cover
            $item['cover'] = $item['pic'];
            unset($item['pic']);
            // 移除 album 字段
            unset($item['album']);
        }
        echo json_encode($playlist);
    } else {
        echo $playlist_json;
    }
} else if ($type == 'search') {
    if (!isset($_GET['keyword'])) {
        echo '{"error":"请输入搜索关键词"}';
        exit;
    }

    $keyword = filter_input(INPUT_GET, 'keyword', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'false';
    $option = array(
        'page' => isset($_GET['page']) ? $_GET['page'] : 1,
        'limit' => isset($_GET['limit']) ? $_GET['limit'] : 50,
    );

    $rate_profile = getRateProfile($type, false);
    if (!checkRateLimit($ip, $rate_profile['ip'], $rate_profile['total'], $rate_profile['window'], $rate_profile['scope'])) {
        http_response_code(429);
        echo '{"error":"rate limit exceeded"}';
        exit;
    }

    $data = $api->search($keyword, $option);
    $data_array = json_decode($data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo '{"error":"invalid JSON response"}';
        exit;
    }

    $search = array();

    // 统一使用标准格式'pic'构建数据
    foreach ($data_array as $song) {
        $lrc_url = API_URI . '?server=' . $song['source'] . '&type=lrc&id=' . $song['lyric_id'] . (AUTH ? '&auth=' . auth($song['source'] . 'lrc' . $song['lyric_id']) : '');
        if ($dwrc == 'true') {
            $lrc_url .= '&yrc=true';
        } else if ($dwrc == 'open') {
            $lrc_url .= '&yrc=open';
        }
        if ($lrctype !== null) {
            $lrc_url .= '&lrctype=' . $lrctype;
        }

        $search[] = array(
            'name'   => $song['name'],
            'artist' => implode('/', $song['artist']),
            'album'  => $song['album'],
            'url'    => API_URI . '?server=' . $song['source'] . '&type=url&id=' . $song['url_id'] . (AUTH ? '&auth=' . auth($song['source'] . 'url' . $song['url_id']) : ''),
            'pic'    => get_pic_url($api, $song['source'], $song['pic_id'], $picsize, $img_redirect),
            'lrc'    => $lrc_url,
            'source' => $song['source']
        );
    }

    // 如果是handsome模式，转换pic为cover
    if ($handsome == 'true') {
        foreach ($search as &$item) {
            $item['cover'] = $item['pic'];
            unset($item['pic']);
        }
    }

    $search = json_encode($search, JSON_UNESCAPED_UNICODE);
    header('Content-Type: application/json; charset=utf-8');
    setCacheHeaders('search');
    echo $search;
    exit;
} else {
    $need_song = !in_array($type, ['url', 'pic', 'lrc']);
    if ($need_song && !in_array($type, ['name', 'artist', 'album', 'song'])) {
        echo '{"error":"不支持的操作"}';
        exit;
    }

    $cache_hit = false;
    if (APCU_CACHE) {
        $apcu_time = $type == 'url' ? URL_CACHE_TIME : CACHE_TIME;
        // 根据不同类型构建缓存键
        if ($type == 'lrc') {
            // 歌词缓存需要包含dwrc参数（yrc/qrc会被统一为dwrc）
            $apcu_type_key = $server . $type . $id . '_dwrc' . $dwrc . '_lrctype' . ($lrctype ?? 'default');
        } else if ($type == 'url') {
            // url缓存需要包含音质参数
            $apcu_type_key = $server . $type . $id . '_br' . $br;
        } else if ($type == 'pic') {
            // 图片缓存按尺寸区分；无尺寸请求缓存为 default
            $size_key = ($picsize !== null && $picsize !== '') ? $picsize : 'default';
            $apcu_type_key = $server . $type . $id . '_size' . $size_key;
        } else if ($type == 'song') {
            // song 类型受 img_redirect 影响，但不包括 handsome 参数以统一缓存
            $apcu_type_key = $server . $type . $id . '_img' . $img_redirect . '_dwrc' . $dwrc . '_lrctype' . ($lrctype ?? 'default');
        } else {
            // 其他类型（name, artist, album等）
            $apcu_type_key = $server . $type . $id;
        }

        // 强制刷新频率限制 (60秒)
        if ($refresh) {
            $lock_key = 'refresh_lock_' . $apcu_type_key;
            if (apcu_exists($lock_key)) {
                $refresh = false; // 忽略刷新
            } else {
                apcu_store($lock_key, 1, 60);
            }
        }

        if (!$refresh && apcu_exists($apcu_type_key)) {
            $cache_hit = true;
            $rate_profile = getRateProfile($type, true);
            if (!checkRateLimit($ip, $rate_profile['ip'], $rate_profile['total'], $rate_profile['window'], $rate_profile['scope'])) {
                http_response_code(429);
                echo '{"error":"rate limit exceeded"}';
                exit;
            }
            $data = apcu_fetch($apcu_type_key);
            return_data($type, $data);
        }
        if ($need_song) {
            // song数据不依赖音质，可以共享缓存
            $apcu_song_id_key = $server . 'song_id' . $id;
            if (!$refresh && apcu_exists($apcu_song_id_key)) {
                $song = apcu_fetch($apcu_song_id_key);
                $cache_hit = true;
            }
        }
    }

    $rate_profile = getRateProfile($type, $cache_hit);
    if (!checkRateLimit($ip, $rate_profile['ip'], $rate_profile['total'], $rate_profile['window'], $rate_profile['scope'])) {
        http_response_code(429);
        echo '{"error":"rate limit exceeded"}';
        exit;
    }

    if (!$need_song) {
        $data = song2data($api, null, $type, $id, $dwrc, $picsize, $br, $handsome, $img_redirect, $lrctype);
    } else {
        if (!isset($song)) $song = $api->song($id);
        if ($song == '[]') {
            echo '{"error":"unknown song"}';
            exit;
        }
        if (APCU_CACHE) {
            apcu_store($apcu_song_id_key, $song, $apcu_time);
        }
        $data = song2data($api, json_decode($song)[0], $type, $id, $dwrc, $picsize, $br, $handsome, $img_redirect, $lrctype);
    }

    if (APCU_CACHE) {
        // 所有类型均写入缓存；pic 类型按尺寸独立缓存
        apcu_store($apcu_type_key, $data, $apcu_time);
    }

    return_data($type, $data);
}

function api_uri() // static
{
    if (defined('Only_Https') && Only_Https === true) {
        $protocol = 'https://';
    } else {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://');
    };

    return $protocol . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
}

function getIP()
{
    // 优先检测配置的自定义 Header
    if (defined('RATE_LIMIT_HEADER') && !empty(RATE_LIMIT_HEADER)) {
        $header = RATE_LIMIT_HEADER;
        if (isset($_SERVER[$header])) return $_SERVER[$header];
        // 尝试自动补全 HTTP_ 前缀
        if (strpos($header, 'HTTP_') !== 0) {
            $header = 'HTTP_' . str_replace('-', '_', strtoupper($header));
            if (isset($_SERVER[$header])) return $_SERVER[$header];
        }
    }

    if (getenv("HTTP_CLIENT_IP"))
        $ip = getenv("HTTP_CLIENT_IP");
    else if(getenv("HTTP_X_FORWARDED_FOR"))
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    else if(getenv("REMOTE_ADDR"))
        $ip = getenv("REMOTE_ADDR");
    else
        $ip = "Unknow";
    return $ip;
}

function getRef()
{
    if(isset($_SERVER['HTTP_REFERER'])){
        return $_SERVER['HTTP_REFERER'];
    } else {
        return "url";
    }
}

function auth($name)
{
    return hash_hmac('sha1', $name, AUTH_SECRET);
}

function get_pic_url($api, $source, $pic_id, $picsize, $img_redirect)
{
    if ($img_redirect === 'true') {
        if ($source == 'netease') {
            if (isset($picsize) && !empty($picsize)) {
                return 'https://p3.music.126.net/' . $api->netease_encryptId($pic_id) . '/' . $pic_id . '.jpg?param=' . $picsize . 'y' . $picsize;
            } else {
                return 'https://p3.music.126.net/' . $api->netease_encryptId($pic_id) . '/' . $pic_id . '.jpg';
            }
        } elseif ($source == 'tencent') {
            if (isset($picsize) && !empty($picsize)) {
                return 'https://y.gtimg.cn/music/photo_new/T002R' . $picsize . 'x' . $picsize . 'M000' . $pic_id . '.jpg';
            } else {
                return 'https://y.gtimg.cn/music/photo_new/T002M000' . $pic_id . '.jpg';
            }
        }
    }

    // Default to API proxy
    return API_URI . '?server=' . $source . '&type=pic&id=' . $pic_id . (AUTH ? '&auth=' . auth($source . 'pic' . $pic_id) : '');
}

/**
 * 获取限流配置
 * @return array
 */
function getRateProfile($type, $cache_hit)
{
    $profiles = getRateLimitProfiles();
    $is_list = in_array($type, ['playlist', 'search']);
    if ($is_list) {
        $profile = $cache_hit ? $profiles['list_cache'] : $profiles['list_nocache'];
        $profile['scope'] = $cache_hit ? 'list_cache' : 'list_nocache';
        return $profile;
    }

    $profile = $cache_hit ? $profiles['other_cache'] : $profiles['other_nocache'];
    $profile['scope'] = $cache_hit ? 'other_cache' : 'other_nocache';
    return $profile;
}

/**
 * 规范化限流作用域
 * @return string
 */
function normalizeRateScope($scope)
{
    return preg_replace('/[^a-z0-9_]/i', '_', $scope);
}

/**
 * 获取限流存储类型
 * @return string
 */
function getRateLimitStorage()
{
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        return 'apcu';
    }
    if (defined('CACHE') && CACHE) {
        return 'file';
    }
    return 'none';
}

/**
 * 获取限流配置
 * @return array
 */
function getRateLimitProfiles()
{
    global $config;
    $defaults = array(
        'list_nocache' => array('window' => 30, 'ip' => 30, 'total' => 70),
        'list_cache' => array('window' => 30, 'ip' => 90, 'total' => 180),
        'other_nocache' => array('window' => 30, 'ip' => 90, 'total' => 180),
        'other_cache' => array('window' => 30, 'ip' => 300, 'total' => 600),
    );

    $profiles = array();
    if (isset($config['rate_limit_profiles']) && is_array($config['rate_limit_profiles'])) {
        $profiles = $config['rate_limit_profiles'];
    }

    foreach ($defaults as $key => $def) {
        if (!isset($profiles[$key]) || !is_array($profiles[$key])) {
            $profiles[$key] = $def;
            continue;
        }
        $profiles[$key]['window'] = isset($profiles[$key]['window']) ? (int)$profiles[$key]['window'] : $def['window'];
        $profiles[$key]['ip'] = isset($profiles[$key]['ip']) ? (int)$profiles[$key]['ip'] : $def['ip'];
        $profiles[$key]['total'] = isset($profiles[$key]['total']) ? (int)$profiles[$key]['total'] : $def['total'];
    }

    return $profiles;
}

/**
 * 获取 debug 限流配置
 * @return array
 */
function getRateLimitDebugProfile()
{
    global $config;
    $default = array('window' => 60, 'ip' => 5, 'total' => 30);
    if (!isset($config['rate_limit_debug']) || !is_array($config['rate_limit_debug'])) {
        return $default;
    }
    $profile = $config['rate_limit_debug'];
    return array(
        'window' => isset($profile['window']) ? (int)$profile['window'] : $default['window'],
        'ip' => isset($profile['ip']) ? (int)$profile['ip'] : $default['ip'],
        'total' => isset($profile['total']) ? (int)$profile['total'] : $default['total'],
    );
}

/**
 * 获取限流状态
 * @return array
 */
function getRateLimitStatus($ip, $profiles)
{
    $result = array('ip' => array(), 'total' => array());
    foreach ($profiles as $scope => $profile) {
        $counts = getRateLimitCounts($ip, $scope);
        $result['ip'][$scope] = array(
            'count' => $counts['ip'],
            'limit' => $profile['ip'],
            'window' => $profile['window']
        );
        $result['total'][$scope] = array(
            'count' => $counts['total'],
            'limit' => $profile['total'],
            'window' => $profile['window']
        );
    }
    return $result;
}

/**
 * 获取限流计数
 * @return array
 */
function getRateLimitCounts($ip, $scope)
{
    $scope = normalizeRateScope($scope);
    $storage = getRateLimitStorage();

    if ($storage === 'apcu') {
        $key_ip = 'meting_rl_ip_' . $scope . '_' . md5($ip);
        $key_total = 'meting_rl_total_' . $scope;
        $ip_count = apcu_fetch($key_ip);
        $total_count = apcu_fetch($key_total);
        return array(
            'ip' => ($ip_count === false ? 0 : (int)$ip_count),
            'total' => ($total_count === false ? 0 : (int)$total_count)
        );
    }

    if ($storage === 'file') {
        $dir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache';
        $rl_dir = $dir . '/ratelimit';
        $file_ip = $rl_dir . '/' . $scope . '_' . md5($ip) . '.json';
        $file_total = $rl_dir . '/' . $scope . '_total.json';
        $ip_data = rateLimitFileRead($file_ip);
        $total_data = rateLimitFileRead($file_total);
        return array(
            'ip' => $ip_data['count'],
            'total' => $total_data['count']
        );
    }

    return array('ip' => 0, 'total' => 0);
}

/**
 * 检查 IP 限流
 * @return bool true=通过, false=超限
 */
function checkRateLimit($ip, $limit_ip = null, $limit_total = null, $window = null, $scope = 'default')
{
    if (!defined('RATE_LIMIT_ENABLE') || !RATE_LIMIT_ENABLE) {
        return true;
    }

    $window = $window !== null ? $window : (defined('RATE_LIMIT_WINDOW') ? RATE_LIMIT_WINDOW : 60);
    $limit_ip = $limit_ip !== null ? $limit_ip : (defined('RATE_LIMIT_COUNT') ? RATE_LIMIT_COUNT : 60);
    $limit_total = $limit_total !== null ? $limit_total : $limit_ip;

    // 1. 优先使用 APCu
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        $scope = normalizeRateScope($scope);
        $key_ip = 'meting_rl_ip_' . $scope . '_' . md5($ip);
        $key_total = 'meting_rl_total_' . $scope;

        $ip_ok = true;
        $total_ok = true;

        if (!apcu_exists($key_ip)) {
            apcu_store($key_ip, 1, $window);
        } else {
            $count_ip = apcu_inc($key_ip);
            $ip_ok = $count_ip <= $limit_ip;
        }

        if (!apcu_exists($key_total)) {
            apcu_store($key_total, 1, $window);
        } else {
            $count_total = apcu_inc($key_total);
            $total_ok = $count_total <= $limit_total;
        }

        return $ip_ok && $total_ok;
    }

    // 2. 降级使用文件缓存 (如果开启了文件缓存)
    if (defined('CACHE') && CACHE) {
        $dir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache';
        $rl_dir = $dir . '/ratelimit';
        if (!is_dir($rl_dir)) {
            if (!@mkdir($rl_dir, 0777, true)) return true; // 无法创建目录则跳过限流
        }

        $scope = normalizeRateScope($scope);
        $file_ip = $rl_dir . '/' . $scope . '_' . md5($ip) . '.json';
        $file_total = $rl_dir . '/' . $scope . '_total.json';

        $ip_ok = rateLimitFileCheck($file_ip, $window, $limit_ip);
        $total_ok = rateLimitFileCheck($file_total, $window, $limit_total);
        return $ip_ok && $total_ok;
    }

    return true;
}

/**
 * 文件限流检查
 * @return bool true=通过, false=超限
 */
function rateLimitFileCheck($file, $window, $limit)
{
    $current_time = time();
    $fp = @fopen($file, 'c+');
    if (!$fp) return true;

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return true;
    }

    $content = stream_get_contents($fp);
    $data = json_decode($content, true);
    if (!$data || $current_time - $data['start'] > $window) {
        // 新窗口
        $data = array(
            'start' => $current_time,
            'count' => 1
        );
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    if ($data['count'] >= $limit) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    $data['count']++;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

/**
 * 文件限流读取
 * @return array
 */
function rateLimitFileRead($file)
{
    if (!is_file($file)) {
        return array('count' => 0, 'start' => 0);
    }
    $content = @file_get_contents($file);
    if ($content === false) {
        return array('count' => 0, 'start' => 0);
    }
    $data = json_decode($content, true);
    if (!$data || !isset($data['count'])) {
        return array('count' => 0, 'start' => 0);
    }
    return array(
        'count' => (int)$data['count'],
        'start' => isset($data['start']) ? (int)$data['start'] : 0
    );
}

function song2data($api, $song, $type, $id, $dwrc, $picsize, $br, $handsome = 'false', $img_redirect = 'false', $lrctype = null)
{
    $data = '';
    switch ($type) {
        case 'name':
            $data = $song->name;
            break;

        case 'artist':
            $data = implode('/', $song->artist);
            break;

        case 'album':
            $data = $song->album;
            break;

        case 'url':
            $m_url = json_decode($api->url($id, $br))->url;
            if ($m_url == '') break;
            // url format
            if ($api->server == 'netease') {
                if ($m_url[4] != 's') $m_url = str_replace('http', 'https', $m_url);
            };
            if (strpos($m_url, 'http://') === 0) {
                $m_url = str_replace('http://', 'https://', $m_url);
            } elseif (strpos($m_url, 'https://') !== 0) {
                $m_url = 'https://' . ltrim($m_url, ':/');
            };
            $data = $m_url;
            break;

        case 'pic':
            $data = json_decode($api->pic($id, $picsize))->url;
            if (strpos($data, 'http://') === 0) {
                $data = str_replace('http://', 'https://', $data);
            } elseif (strpos($data, 'https://') !== 0) {
                $data = 'https://' . ltrim($data, ':/');
            };
            break;

        case 'lrc':
            $lrc_data = json_decode($api->lyric($id));
            if (empty($lrc_data) || $lrc_data->lyric == '') {
                $lrc = '';
            } else if ($lrc_data->tlyric == '') {
                $lrc = $lrc_data->lyric;
            } else if ($lrctype !== null) {
                if ($lrctype == '0') {
                    $lrc = $lrc_data->lyric;
                } else if ($lrctype == '2') {
                    $lrc = $lrc_data->tlyric;
                } else if ($lrctype == '1') {
                    $lrc_arr = explode("\n", $lrc_data->lyric);
                    $lrc_cn_arr = explode("\n", $lrc_data->tlyric);
                    $lrc_cn_map = array();
                    foreach ($lrc_cn_arr as $i => $v) {
                        if ($v == '') continue;
                        $line = explode(']', $v, 2);
                        // 格式化处理
                        $line[1] = isset($line[1]) ? trim(preg_replace('/\s\s+/', ' ', $line[1] ?? '')) : '';
                        $lrc_cn_map[$line[0]] = $line[1];
                    }
                    $final_lrc = [];
                    $lrc_count = count($lrc_arr);
                    for ($i = 0; $i < $lrc_count; $i++) {
                        $v = $lrc_arr[$i];
                        if ($v == '') continue;

                        $final_lrc[] = $v;

                        $parts = explode(']', $v, 2);
                        if (count($parts) >= 2) {
                            $key = $parts[0];
                            $content = isset($parts[1]) ? $parts[1] : '';

                            if (isset($lrc_cn_map[$key]) && $lrc_cn_map[$key] != '//') {
                                $trans = $lrc_cn_map[$key];
                                // Check if the translation is just whitespace or empty
                                if (trim($trans) !== '') {
                                    $should_output = true;

                                    // Check metadata
                                    if (preg_match('/(作词|作曲|制作人|编曲|歌手|演唱|专辑|发行)/u', $content)) {
                                        $conflict = false;
                                        $next_key = null;

                                        // Look ahead for next valid line
                                        for ($j = $i + 1; $j < $lrc_count; $j++) {
                                            if ($lrc_arr[$j] == '') continue;
                                            $p2 = explode(']', $lrc_arr[$j], 2);
                                            if (count($p2) >= 2) {
                                                $next_key = $p2[0];
                                                if ($next_key !== $key && isset($lrc_cn_map[$next_key]) && $lrc_cn_map[$next_key] != '//') {
                                                    $conflict = true;
                                                }
                                                break;
                                            }
                                        }

                                        if (!$conflict && $next_key) {
                                            // No conflict, move translation to next line
                                            $lrc_cn_map[$next_key] = $trans;
                                            $should_output = false;
                                        }
                                    }

                                    if ($should_output) {
                                        $final_lrc[] = $key . ']' . $trans;
                                    }
                                }
                            }
                        }
                    }
                    $lrc = implode("\n", $final_lrc);
                } else {
                    $lrc = $lrc_data->lyric;
                }
            } else {
                $lrc = $lrc_data->lyric;
            }
            // 移除时间戳后面紧跟的空格
            $lrc = preg_replace('/(\[[0-9:.]+\])[ \t]+/', '$1', $lrc);
            $data = $lrc;
            break;

        case 'song':
            $lrc_url = API_URI . '?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($song->source . 'lrc' . $song->lyric_id) : '');
            if ($dwrc == 'true') {
                $lrc_url .= '&yrc=true';
            } else if ($dwrc == 'open') {
                $lrc_url .= '&yrc=open';
            }
            if ($lrctype !== null) {
                $lrc_url .= '&lrctype=' . $lrctype;
            }

            // 构建统一的数据结构（始终包含 album 和 pic 字段）
            $song_data = array(
                'name'   => $song->name,
                'artist' => implode('/', $song->artist),
                'album'  => $song->album,  // 始终包含 album 字段
                'url'    => API_URI . '?server=' . $song->source . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($song->source . 'url' . $song->url_id) : ''),
                'pic'    => get_pic_url($api, $song->source, $song->pic_id, $picsize, $img_redirect),
                'lrc'    => $lrc_url
            );

            // 根据当前请求的 handsome 状态动态转换数据
            if ($handsome == 'true') {
                // 转换 pic 为 cover
                $song_data['cover'] = $song_data['pic'];
                unset($song_data['pic']);
                // 移除 album 字段
                unset($song_data['album']);
            }

            $data = json_encode(array($song_data));
            break;
    }
    if ($data == '') exit;
    return $data;
}

function return_data($type, $data)
{
    setCacheHeaders($type);
    if (in_array($type, ['url', 'pic'])) {
        header('HTTP/1.1 302 Temporary Redirect');
        header('Location: ' . $data);
    } else {
        echo $data;
    }
    exit;
}

/**
 * 按类型设置缓存时间
 */
function setCacheHeaders($type)
{
    $max_age = getCacheMaxAge($type);
    if ($max_age <= 0) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        return;
    }
    $max_age = (int)$max_age;
    header('Cache-Control: public, max-age=' . $max_age . ', s-maxage=' . $max_age);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
}

/**
 * 获取类型对应的缓存时间
 */
function getCacheMaxAge($type)
{
    if ($type == 'playlist') {
        return defined('PLAYLIST_CACHE_TIME') ? PLAYLIST_CACHE_TIME : 0;
    }
    if ($type == 'url') {
        return defined('URL_CACHE_TIME') ? URL_CACHE_TIME : 0;
    }
    if (in_array($type, ['pic', 'lrc', 'song', 'name', 'artist', 'album'])) {
        return defined('CACHE_TIME') ? CACHE_TIME : 0;
    }
    if ($type == 'search') {
        return 600;
    }
    return 0;
}
