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

if (!isset($_GET['type']) || !isset($_GET['id'])) {
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
} else if (in_array($type, ['song', 'playlist', 'search'])) {
    header('content-type: application/json; charset=utf-8;');
} else if (in_array($type, ['name', 'lrc', 'artist'])) {
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

if ($type == 'playlist') {

    if (CACHE) {
        // 统一使用标准格式缓存，包含音质参数实现不同音质的独立缓存
        $cache_key = $server . '_' . $id . '_br' . $br;
        $file_path = $config['cache_dir'] . '/playlist/' . $cache_key . '.json';
        if (file_exists($file_path)) {
            if ($_SERVER['REQUEST_TIME'] - filemtime($file_path) < CACHE_TIME) {
                $cached_data = file_get_contents($file_path);
                // 如果是handsome模式，动态转换pic为cover
                if ($handsome == 'true') {
                    $playlist_array = json_decode($cached_data, true);
                    foreach ($playlist_array as &$item) {
                        if (isset($item['pic'])) {
                            $item['cover'] = $item['pic'];
                            unset($item['pic']);
                        }
                    }
                    echo json_encode($playlist_array);
                } else {
                    echo $cached_data;
                }
                exit;
            }
        }
    }

    $data = $api->playlist($id);
    if ($data == '[]') {
        echo '{"error":"id 为空，无法处理"}';
        exit;
    }
    $data = json_decode($data);
    $playlist = array();
    
    // 统一使用标准格式'pic'构建数据
    foreach ($data as $song) {
        $lrc_url = API_URI . '?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($song->source . 'lrc' . $song->lyric_id) : '');
        if ($dwrc == 'true') {
            $lrc_url .= '&yrc=true';
        } else if ($dwrc == 'open') {
            $lrc_url .= '&yrc=open';
        }

        $playlist[] = array(
            'name'   => $song->name,
            'artist' => implode('/', $song->artist),
            'url'    => API_URI . '?server=' . $song->source . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($song->source . 'url' . $song->url_id) : ''),
            'pic'    => API_URI . '?server=' . $song->source . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($song->source . 'pic' . $song->pic_id) : ''),
            'lrc'    => $lrc_url
        );
    }

    // 先保存标准格式的缓存
    $playlist_json = json_encode($playlist);
    if (CACHE) {
        // ! mkdir /cache/playlist
        file_put_contents($file_path, $playlist_json);
    }

    // 如果是handsome模式，转换pic为cover后再输出
    if ($handsome == 'true') {
        foreach ($playlist as &$item) {
            $item['cover'] = $item['pic'];
            unset($item['pic']);
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

        $search[] = array(
            'name'   => $song['name'],
            'artist' => implode('/', $song['artist']),
            'album'  => $song['album'],
            'url'    => API_URI . '?server=' . $song['source'] . '&type=url&id=' . $song['url_id'] . (AUTH ? '&auth=' . auth($song['source'] . 'url' . $song['url_id']) : ''),
            'pic'    => API_URI . '?server=' . $song['source'] . '&type=pic&id=' . $song['pic_id'] . (AUTH ? '&auth=' . auth($song['source'] . 'pic' . $song['pic_id']) : ''),
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
    echo $search;
    exit;
} else {
    $need_song = !in_array($type, ['url', 'pic', 'lrc']);
    if ($need_song && !in_array($type, ['name', 'artist', 'song'])) {
        echo '{"error":"不支持的操作"}';
        exit;
    }

    if (APCU_CACHE) {
        $apcu_time = $type == 'url' ? 600 : 36000;
        // 根据不同类型构建缓存键
        if ($type == 'lrc') {
            // 歌词缓存需要包含dwrc参数（yrc/qrc会被统一为dwrc）
            $apcu_type_key = $server . $type . $id . '_dwrc' . $dwrc;
        } else if ($type == 'url') {
            // url缓存需要包含音质参数
            $apcu_type_key = $server . $type . $id . '_br' . $br;
        } else {
            // 其他类型（pic, name, artist等）不受br和dwrc影响
            $apcu_type_key = $server . $type . $id;
        }
        if (apcu_exists($apcu_type_key)) {
            $data = apcu_fetch($apcu_type_key);
            return_data($type, $data);
        }
        if ($need_song) {
            // song数据不依赖音质，可以共享缓存
            $apcu_song_id_key = $server . 'song_id' . $id;
            if (apcu_exists($apcu_song_id_key)) {
                $song = apcu_fetch($apcu_song_id_key);
            }
        }
    }

    if (!$need_song) {
        $data = song2data($api, null, $type, $id, $dwrc, $picsize, $br, $handsome);
    } else {
        if (!isset($song)) $song = $api->song($id);
        if ($song == '[]') {
            echo '{"error":"unknown song"}';
            exit;
        }
        if (APCU_CACHE) {
            apcu_store($apcu_song_id_key, $song, $apcu_time);
        }
        $data = song2data($api, json_decode($song)[0], $type, $id, $dwrc, $picsize, $br, $handsome);
    }

    if (APCU_CACHE) {
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

function song2data($api, $song, $type, $id, $dwrc, $picsize, $br, $handsome = 'false')
{
    $data = '';
    switch ($type) {
        case 'name':
            $data = $song->name;
            break;

        case 'artist':
            $data = implode('/', $song->artist);
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
            if ($lrc_data->lyric == '') {
                $lrc = '';
            } else if ($lrc_data->tlyric == '') {
                $lrc = $lrc_data->lyric;
            } else if (TLYRIC) { // lyric_cn
                $lrc_arr = explode("\n", $lrc_data->lyric);
                $lrc_cn_arr = explode("\n", $lrc_data->tlyric);
                $lrc_cn_map = array();
                foreach ($lrc_cn_arr as $i => $v) {
                    if ($v == '') continue;
                    $line = explode(']', $v, 2);
                    // 格式化处理
                    $line[1] = isset($line[1]) ? trim(preg_replace('/\s\s+/', ' ', $line[1] ?? '')) : '';
                    $lrc_cn_map[$line[0]] = $line[1];
                    unset($lrc_cn_arr[$i]);
                }
                foreach ($lrc_arr as $i => $v) {
                    if ($v == '') continue;
                    $key = explode(']', $v, 2)[0];
                    if (!empty($lrc_cn_map[$key]) && $lrc_cn_map[$key] != '//') {
                        $lrc_arr[$i] .= ' (' . $lrc_cn_map[$key] . ')';
                        unset($lrc_cn_map[$key]);
                    }
                }
                $lrc = implode("\n", $lrc_arr);
            } else {
                $lrc = $lrc_data->lyric;
            }
            $data = $lrc;
            break;

        case 'song':
            $lrc_url = API_URI . '?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($song->source . 'lrc' . $song->lyric_id) : '');
            if ($dwrc == 'true') {
                $lrc_url .= '&yrc=true';
            } else if ($dwrc == 'open') {
                $lrc_url .= '&yrc=open';
            }

            // Handsome 主题兼容模式
            $pic_key = ($handsome == 'true') ? 'cover' : 'pic';

            $data = json_encode(array(array(
                'name'   => $song->name,
                'artist' => implode('/', $song->artist),
                'url'    => API_URI . '?server=' . $song->source . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($song->source . 'url' . $song->url_id) : ''),
                $pic_key    => API_URI . '?server=' . $song->source . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($song->source . 'pic' . $song->pic_id) : ''),
                'lrc'    => $lrc_url
            )));
            break;
    }
    if ($data == '') exit;
    return $data;
}

function return_data($type, $data)
{
    if (in_array($type, ['url', 'pic'])) {
        header('HTTP/1.1 302 Temporary Redirect');
        header('Location: ' . $data);
    } else {
        echo $data;
    }
    exit;
}
