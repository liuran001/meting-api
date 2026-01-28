<?php

/**
 * Meting music framework
 * https://i-meto.com
 * https://github.com/metowolf/Meting
 * Version 1.5.11.
 *
 * Copyright 2019, METO Sheel <i@i-meto.com>
 * Released under the MIT license
 */

namespace Metowolf;

include __DIR__ . '/QrcDecode.php';

use QrcDecode\Decoder;

class Meting
{
    const VERSION = '1.5.11';

    public $raw;
    public $data;
    public $info;
    public $error;
    public $status;

    public $server;
    public $proxy = null;
    public $format = false;
    public $dwrc = false;
    public $bakdwrc = false;
    public $lrctype = 0;
    public $header;
    private $temp;

    public function dwrc($value = false)
    {
        $this->dwrc = $value;
        return $this;
    }

    public function bakdwrc($value = false)
    {
        $this->bakdwrc = $value;
        return $this;
    }

    public function lrctype($value = 0)
    {
        $this->lrctype = $value;
        return $this;
    }

    public function __construct($value = 'netease')
    {
        $this->site($value);
    }

    public function site($value)
    {
        $suppose = array('netease', 'tencent');
        $this->server = in_array($value, $suppose) ? $value : 'netease';
        $this->header = $this->curlset();

        return $this;
    }

    public function cookie($value)
    {
        $this->header['Cookie'] = $value;

        return $this;
    }

    public function format($value = true)
    {
        $this->format = $value;

        return $this;
    }

    public function proxy($value)
    {
        $this->proxy = $value;

        return $this;
    }

    private function exec($api)
    {
        if (isset($api['encode'])) {
            $api = call_user_func_array(array($this, $api['encode']), array($api));
        }
        if ($api['method'] == 'GET') {
            if (isset($api['body'])) {
                $api['url'] .= '?' . http_build_query($api['body']);
                $api['body'] = null;
            }
        }

        $this->curl($api['url'], $api['body']);

        if (!$this->format) {
            return $this->raw;
        }

        $this->data = $this->raw;

        if (isset($api['decode'])) {
            $this->data = call_user_func_array(array($this, $api['decode']), array($this->data));
        }
        if (isset($api['format'])) {
            $this->data = $this->clean($this->data, $api['format']);
        }

        return $this->data;
    }

    private function curl($url, $payload = null, $headerOnly = 0)
    {
        $header = array_map(function ($k, $v) {
            return $k . ': ' . $v;
        }, array_keys($this->header), $this->header);
        $curl = curl_init();
        if (!is_null($payload)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($payload) ? http_build_query($payload) : $payload);
        }
        curl_setopt($curl, CURLOPT_HEADER, $headerOnly);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_IPRESOLVE, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        if ($this->proxy) {
            curl_setopt($curl, CURLOPT_PROXY, $this->proxy);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->raw = curl_exec($curl);
            $this->info = curl_getinfo($curl);
            $this->error = curl_errno($curl);
            $this->status = $this->error ? curl_error($curl) : '';
            if (!$this->error) {
                break;
            }
        }
        curl_close($curl);

        return $this;
    }

    private function pickup($array, $rule)
    {
        $t = explode('.', $rule);
        foreach ($t as $vo) {
            if (!isset($array[$vo])) {
                return array();
            }
            $array = $array[$vo];
        }

        return $array;
    }

    private function clean($raw, $rule)
    {
        $raw = json_decode($raw, true);
        if (!empty($rule)) {
            $raw = $this->pickup($raw, $rule);
        }
        if (!isset($raw[0]) && count($raw)) {
            $raw = array($raw);
        }
        $result = array_map(array($this, 'format_' . $this->server), $raw);

        return json_encode($result);
    }

    public function search($keyword, $option = null)
    {
        switch ($this->server) {
            case 'netease':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'https://music.163.com/api/cloudsearch/pc',
                    'body'   => array(
                        's'      => $keyword,
                        'type'   => isset($option['type']) ? $option['type'] : 1,
                        'limit'  => isset($option['limit']) ? $option['limit'] : 50,
                        'total'  => 'true',
                        'offset' => isset($option['page']) && isset($option['limit']) ? ($option['page'] - 1) * $option['limit'] : 0,
                    ),
                    'encode' => 'netease_AESCBC',
                    'format' => 'result.songs',
                );
                break;
            case 'tencent':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://c.y.qq.com/soso/fcgi-bin/client_search_cp',
                    'body'   => array(
                        'format'   => 'json',
                        'p'        => isset($option['page']) ? $option['page'] : 1,
                        'n'        => isset($option['limit']) ? $option['limit'] : 50,
                        'w'        => $keyword,
                        'aggr'     => 1,
                        'lossless' => 1,
                        'cr'       => 1,
                        'new_json' => 1,
                    ),
                    'format' => 'data.song.list',
                );
                break;
        }

        return $this->exec($api);
    }

    public function song($id)
    {
        switch ($this->server) {
            case 'netease':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'https://music.163.com/api/v3/song/detail/',
                    'body'   => array(
                        'c' => '[{"id":' . $id . ',"v":0}]',
                    ),
                    'encode' => 'netease_AESCBC',
                    'format' => 'songs',
                );
                break;
            case 'tencent':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_play_single_song.fcg',
                    'body'   => array(
                        'songmid'  => $id,
                        'platform' => 'yqq',
                        'format'   => 'json',
                    ),
                    'format' => 'data',
                );
                break;
        }

        return $this->exec($api);
    }

    public function album($id)
    {
        switch ($this->server) {
            case 'netease':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'https://music.163.com/api/v1/album/' . $id,
                    'body'   => array(
                        'total'         => 'true',
                        'offset'        => '0',
                        'id'            => $id,
                        'limit'         => '100000',
                        'ext'           => 'true',
                        'private_cloud' => 'true',
                    ),
                    'encode' => 'netease_AESCBC',
                    'format' => 'songs',
                );
                break;
            case 'tencent':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_v8_album_detail_cp.fcg',
                    'body'   => array(
                        'albummid' => $id,
                        'platform' => 'mac',
                        'format'   => 'json',
                        'newsong'  => 1,
                    ),
                    'format' => 'data.getSongInfo',
                );
                break;
        }

        return $this->exec($api);
    }

    public function artist($id, $limit = 50)
    {
        switch ($this->server) {
            case 'netease':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'https://music.163.com/api/v1/artist/' . $id,
                    'body'   => array(
                        'ext'           => 'true',
                        'private_cloud' => 'true',
                        'top'           => $limit,
                        'id'            => $id,
                    ),
                    'encode' => 'netease_AESCBC',
                    'format' => 'hotSongs',
                );
                break;
            case 'tencent':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_v8_singer_track_cp.fcg',
                    'body'   => array(
                        'singermid' => $id,
                        'begin'     => 0,
                        'num'       => $limit,
                        'order'     => 'listen',
                        'platform'  => 'mac',
                        'newsong'   => 1,
                    ),
                    'format' => 'data.list',
                );
                break;
        }

        return $this->exec($api);
    }

    public function playlist($id)
    {
        switch ($this->server) {
            case 'netease':
                $api = [
                    "method" => "POST",
                    "url" => "https://music.163.com/api/v6/playlist/detail",
                    "body" => [
                        "id" => $id,
                        "offset" => "0",
                        "total" => "True",
                        "limit" => "100000",
                        "n" => "100000",
                    ],
                    "encode" => "netease_AESCBC",
                ];
                $playlistData = json_decode($this->exec($api), true);
                if (!isset($playlistData["playlist"]["trackIds"])) {
                    return json_encode([]);
                }
                $trackIds = $playlistData["playlist"]["trackIds"];
                $allTracks = [];
                $idBatches = array_chunk(array_column($trackIds, "id"), 500);

                foreach ($idBatches as $batch) {
                    // Convert the batch into the proper format
                    $songIds = array_map(function ($id) {
                        return ["id" => $id, "v" => 0]; // Using 'v' => 0 as per your suggestion
                    }, $batch);

                    // Fetch the song details for each batch
                    $songApi = [
                        "method" => "POST",
                        "url" => "https://music.163.com/api/v3/song/detail/",
                        "body" => [
                            "c" => json_encode($songIds),
                        ],
                        "encode" => "netease_AESCBC",
                    ];
                    $songData = $this->exec($songApi);
                    // Merge songs data into allTracks
                    $allTracks = array_merge(
                        $allTracks,
                        json_decode($songData, true)["songs"]
                    );
                }
                $tmp = array_map(function ($data) {
                    $result = [
                        "id" => $data["id"],
                        "name" => $data["name"],
                        "artist" => [],
                        "album" => $data["al"]["name"],
                        "pic_id" => isset($data["al"]["pic_str"])
                            ? $data["al"]["pic_str"]
                            : $data["al"]["pic"],
                        "url_id" => $data["id"],
                        "lyric_id" => $data["id"],
                        "source" => "netease",
                    ];
                    if (isset($data["al"]["picUrl"])) {
                        preg_match(
                            "/\/(\d+)\./",
                            $data["al"]["picUrl"],
                            $match
                        );
                        $result["pic_id"] = $match[1];
                    }
                    foreach ($data["ar"] as $vo) {
                        $result["artist"][] = $vo["name"];
                    }
                    return $result;
                }, $allTracks);
                return json_encode($tmp);
            case 'tencent':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_v8_playlist_cp.fcg',
                    'body'   => array(
                        'id'       => $id,
                        'format'   => 'json',
                        'newsong'  => 1,
                        'platform' => 'jqspaframe.json',
                    ),
                    'format' => 'data.cdlist.0.songlist',
                );
                break;
        }

        return $this->exec($api);
    }

    public function url($id, $br = 2147483)
    {
        switch ($this->server) {
            case 'netease':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'https://music.163.com/api/song/enhance/player/url',
                    'body'   => array(
                        'ids' => array($id),
                        'br'  => $br * 1000,
                    ),
                    'encode' => 'netease_AESCBC',
                    'decode' => 'netease_url',
                );
                break;
            case 'tencent':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_play_single_song.fcg',
                    'body'   => array(
                        'songmid'  => $id,
                        'platform' => 'yqq',
                        'format'   => 'json',
                    ),
                    'decode' => 'tencent_url',
                );
                break;
        }
        $this->temp['br'] = $br;

        return $this->exec($api);
    }

    public function lyric($id)
    {
        switch ($this->server) {
            case 'netease':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'https://music.163.com/api/song/lyric',
                    'body'   => array(
                        'id'        => $id,
                        'os'        => 'pc',
                        'lv'        => -1,
                        'kv'        => -1,
                        'tv'        => -1,
                        'rv'        => -1,
                        'yv'        => 1,
                        'showRole'  => 'False',
                        'cp'        => 'False',
                        'e_r'       => 'False',
                    ),
                    'encode' => 'netease_AESCBC',
                );
                // 发送请求，获取结果
                $result = $this->exec($api);
                $tmp = json_decode($result, true);
                // 手动处理返回结果
                if (isset($tmp['yrc'])) {
                    // 如果存在 yrc 字段，进一步判断 dwrc 参数的值
                    if ($this->dwrc) {
                        // dwrc 参数为 true，手动处理 netease_lyric_yrc 解析
                        $decoded_result = $this->netease_lyric_yrc($result);
                    } else {
                        // dwrc 参数为 false，手动处理普通 netease_lyric 解析
                        $decoded_result = $this->netease_lyric($result);
                    }
                } else {
                    if ($this->bakdwrc) {
                        // 如果打开了备用模式，不存在逐字歌词直接返回空。
                        $decoded_result = null;
                    } else {
                        // 不存在 yrc 字段，直接执行普通 netease_lyric 解析
                        $decoded_result = $this->netease_lyric($result);
                    }
                }
                // 返回最终处理后的结果
                return $decoded_result;
            case 'tencent':
                if ($this->dwrc) {
                    $api = array(
                        'method' => 'POST',
                        'url'    => 'https://u6.y.qq.com/cgi-bin/musicu.fcg',
                        'body'   => json_encode(array(
                            'comm' => array(
                                '_channelid' => '0',
                                '_os_version' => '6.2.9200-2',
                                'authst' => '',
                                'ct' => '19',
                                'cv' => '1873',
                                'patch' => '118',
                                'psrf_access_token_expiresAt' => 0,
                                'psrf_qqaccess_token' => '',
                                'psrf_qqopenid' => '',
                                'psrf_qqunionid' => '',
                                'tmeAppID' => 'qqmusic',
                                'tmeLoginType' => 2,
                                'uin' => '0',
                                'wid' => '0',
                            ),
                            'req_1' => array(
                                'method' => 'GetPlayLyricInfo',
                                'module' => 'music.musichallSong.PlayLyricInfo',
                                'param' => array(
                                    'songMID' => $id,
                                    'qrc' => $this->dwrc ? 1 : 0,
                                ),
                            )
                        )),
                        'decode' => 'tencent_lyric_qrc',
                    );
                } else {
                    $api = array(
                        'method' => 'GET',
                        'url'    => 'https://c.y.qq.com/lyric/fcgi-bin/fcg_query_lyric_new.fcg',
                        'body'   => array(
                            'songmid' => $id,
                            'g_tk'    => '5381',
                        ),
                        'decode' => 'tencent_lyric',
                    );
                };
                break;
        }
        return $this->exec($api);
    }

    public function pic($id, $size)
    {
        switch ($this->server) {
            case 'netease':
                if (isset($size) && !empty($size)) {
                    $url = 'https://p3.music.126.net/' . $this->netease_encryptId($id) . '/' . $id . '.jpg?param=' . $size . 'y' . $size;
                } else {
                    $url = 'https://p3.music.126.net/' . $this->netease_encryptId($id) . '/' . $id . '.jpg';
                };
                break;
            case 'tencent':
                if (isset($size) && !empty($size)) {
                    $url = 'https://y.gtimg.cn/music/photo_new/T002R' . $size . 'x' . $size . 'M000' . $id . '.jpg';
                } else {
                    $url = 'https://y.gtimg.cn/music/photo_new/T002M000' . $id . '.jpg';
                };
                break;
        }

        return json_encode(array('url' => $url));
    }

    private function curlset()
    {
        switch ($this->server) {
            case 'netease':
                return array(
                    'Referer'         => 'https://music.163.com/',
                    'Cookie'          => 'appver=8.2.30; os=iPhone OS; osver=15.0; EVNSM=1.0.0; buildver=2206; channel=distribution; machineid=iPhone13.3',
                    'User-Agent'      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 CloudMusic/0.1.1 NeteaseMusic/8.2.30',
                    'X-Real-IP'       => long2ip(mt_rand(1884815360, 1884890111)),
                    'Accept'          => '*/*',
                    'Accept-Language' => 'zh-CN,zh;q=0.8,gl;q=0.6,zh-TW;q=0.4',
                    'Connection'      => 'keep-alive',
                    'Content-Type'    => 'application/x-www-form-urlencoded',
                );
            case 'tencent':
                return array(
                    'Referer'         => 'https://y.qq.com',
                    'Cookie'          => 'pgv_pvi=22038528; pgv_si=s3156287488; pgv_pvid=5535248600; yplayer_open=1; ts_last=y.qq.com/portal/player.html; ts_uid=4847550686; yq_index=0; qqmusic_fromtag=66; player_exist=1',
                    'User-Agent'      => 'QQ%E9%9F%B3%E4%B9%90/54409 CFNetwork/901.1 Darwin/17.6.0 (x86_64)',
                    'Accept'          => '*/*',
                    'Accept-Language' => 'zh-CN,zh;q=0.8,gl;q=0.6,zh-TW;q=0.4',
                    'Connection'      => 'keep-alive',
                    'Content-Type'    => 'application/x-www-form-urlencoded',
                );
        }
    }

    private function getRandomHex($length)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        }
    }

    private function bchexdec($hex)
    {
        $dec = 0;
        $len = strlen($hex);
        for ($i = 1; $i <= $len; $i++) {
            $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
        }

        return $dec;
    }

    private function bcdechex($dec)
    {
        $hex = '';
        do {
            $last = bcmod($dec, 16);
            $hex = dechex($last) . $hex;
            $dec = bcdiv(bcsub($dec, $last), 16);
        } while ($dec > 0);

        return $hex;
    }

    private function str2hex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $ord = ord($string[$i]);
            $hexCode = dechex($ord);
            $hex .= substr('0' . $hexCode, -2);
        }

        return $hex;
    }

    private function netease_AESCBC($api)
    {
        $modulus = '157794750267131502212476817800345498121872783333389747424011531025366277535262539913701806290766479189477533597854989606803194253978660329941980786072432806427833685472618792592200595694346872951301770580765135349259590167490536138082469680638514416594216629258349130257685001248172188325316586707301643237607';
        $pubkey = '65537';
        $nonce = '0CoJUm6Qyw8W8jud';
        $vi = '0102030405060708';

        if (extension_loaded('bcmath')) {
            $skey = $this->getRandomHex(16);
        } else {
            $skey = 'B3v3kH4vRPWRJFfH';
        }

        $body = json_encode($api['body']);

        if (function_exists('openssl_encrypt')) {
            // 使用 openssl_encrypt 进行加密
            $pad = 16 - (strlen($body) % 16);
            $body = $body . str_repeat(chr($pad), $pad);
            $body = openssl_encrypt($body, 'aes-128-cbc', $nonce, OPENSSL_RAW_DATA, $vi);
            $body = base64_encode($body);

            $pad = 16 - (strlen($body) % 16);
            $body = $body . str_repeat(chr($pad), $pad);
            $body = openssl_encrypt($body, 'aes-128-cbc', $skey, OPENSSL_RAW_DATA, $vi);
            $body = base64_encode($body);
        }


        if (extension_loaded('bcmath')) {
            $skey = strrev(mb_convert_encoding($skey, 'UTF-8', 'ISO-8859-1'));
            $skey = $this->bchexdec($this->str2hex($skey));
            $skey = bcpowmod($skey, $pubkey, $modulus);
            $skey = $this->bcdechex($skey);
            $skey = str_pad($skey, 256, '0', STR_PAD_LEFT);
        } else {
            $skey = '85302b818aea19b68db899c25dac229412d9bba9b3fcfe4f714dc016bc1686fc446a08844b1f8327fd9cb623cc189be00c5a365ac835e93d4858ee66f43fdc59e32aaed3ef24f0675d70172ef688d376a4807228c55583fe5bac647d10ecef15220feef61477c28cae8406f6f9896ed329d6db9f88757e31848a6c2ce2f94308';
        }

        $api['url'] = str_replace('/api/', '/weapi/', $api['url']);
        $api['body'] = array(
            'params'    => $body,
            'encSecKey' => $skey,
        );

        return $api;
    }

    public function netease_encryptId($id)
    {
        $magic = str_split('3go8&$8*3*3h0k(2)2');
        $song_id = str_split($id);
        for ($i = 0; $i < count($song_id); $i++) {
            $song_id[$i] = chr(ord($song_id[$i]) ^ ord($magic[$i % count($magic)]));
        }
        $result = base64_encode(md5(implode('', $song_id), 1));
        $result = str_replace(array('/', '+'), array('_', '-'), $result);

        return $result;
    }

    private function netease_url($result)
    {
        $data = json_decode($result, true);
        if (isset($data['data'][0]['uf']['url'])) {
            $data['data'][0]['url'] = $data['data'][0]['uf']['url'];
        }
        if (isset($data['data'][0]['url'])) {
            $url = array(
                'url'  => $data['data'][0]['url'],
                'size' => $data['data'][0]['size'],
                'br'   => $data['data'][0]['br'] / 999999,
            );
        } else {
            $url = array(
                'url'  => '',
                'size' => 0,
                'br'   => -1,
            );
        }

        return json_encode($url);
    }

    private function tencent_url($result)
    {
        $data = json_decode($result, true);
        $guid = mt_rand() % 10000000000;

        $type = array(
            array('size_flac', 999999, 'F000', 'flac'),
            array('size_320mp3', 320, 'M800', 'mp3'),
            array('size_192aac', 192, 'C600', 'm4a'),
            array('size_128mp3', 128, 'M500', 'mp3'),
            array('size_96aac', 96, 'C400', 'm4a'),
            array('size_48aac', 48, 'C200', 'm4a'),
            array('size_24aac', 24, 'C100', 'm4a'),
        );

        $uin = '0';
        preg_match('/uin=(\d+)/', $this->header['Cookie'], $uin_match);
        if (count($uin_match)) {
            $uin = $uin_match[1];
        }

        $payload = array(
            'req_0' => array(
                'module' => 'vkey.GetVkeyServer',
                'method' => 'CgiGetVkey',
                'param'  => array(
                    'guid'      => (string) $guid,
                    'songmid'   => array(),
                    'filename'  => array(),
                    'songtype'  => array(),
                    'uin'       => $uin,
                    'loginflag' => 1,
                    'platform'  => '20',
                ),
            ),
        );

        foreach ($type as $vo) {
            $payload['req_0']['param']['songmid'][] = $data['data'][0]['mid'];
            $payload['req_0']['param']['filename'][] = $vo[2] . $data['data'][0]['file']['media_mid'] . '.' . $vo[3];
            $payload['req_0']['param']['songtype'][] = $data['data'][0]['type'];
        }

        $api = array(
            'method' => 'GET',
            'url'    => 'https://u6.y.qq.com/cgi-bin/musicu.fcg',
            'body'   => array(
                'format'      => 'json',
                'platform'    => 'yqq.json',
                'needNewCode' => 0,
                'data'        => json_encode($payload),
            ),
        );
        $response = json_decode($this->exec($api), true);
        $vkeys = $response['req_0']['data']['midurlinfo'];

        foreach ($type as $index => $vo) {
            if ($data['data'][0]['file'][$vo[0]] && $vo[1] <= $this->temp['br']) {
                if (!empty($vkeys[$index]['vkey'])) {
                    $url = array(
                        'url'  => $response['req_0']['data']['sip'][0] . $vkeys[$index]['purl'],
                        'size' => $data['data'][0]['file'][$vo[0]],
                        'br'   => $vo[1],
                    );
                    break;
                }
            }
        }
        if (!isset($url['url'])) {
            $url = array(
                'url'  => '',
                'size' => 0,
                'br'   => -1,
            );
        }

        return json_encode($url);
    }

    private function netease_lyric_yrc($result)
    {
        // 检查 $result 是否需要解码
        if (is_string($result)) {
            $result = json_decode($result, true);
        }

        if ($this->lrctype == '2') {
            $lyric = isset($result['lrc']['lyric']) ? $result['lrc']['lyric'] : '';
        } else {
            $lyric = isset($result['yrc']['lyric']) ? $result['yrc']['lyric'] : '';
        }

        $tlyric = isset($result['tlyric']['lyric']) ? $result['tlyric']['lyric'] : '';
        
        // 简单的检查是否为 YRC 格式
        if ($this->lrctype != '2' && $lyric && preg_match('/^\[\d+,\d+\]/', $lyric)) {
            $lyric = $this->yrcToVerbatim($lyric, ($this->lrctype == '1') ? $tlyric : '');
        }

        $data = array(
            'lyric'  => $lyric,
            'tlyric' => $tlyric,
        );

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function netease_lyric($result)
    {
        // 检查 $result 是否需要解码
        if (is_string($result)) {
            $result = json_decode($result, true);
        }

        $data = array(
            'lyric'  => isset($result['lrc']['lyric']) ? $result['lrc']['lyric'] : '',
            'tlyric' => isset($result['tlyric']['lyric']) ? $result['tlyric']['lyric'] : '',
        );

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function tencent_lyric_qrc($result)
    {
        $result = json_decode($result, true);
        $lrc = $result['req_1']['data']['lyric'];
        if ($result['req_1']['data']['qrc'] == 0) {
            return json_encode(array(
                'lyric'  => base64_decode($lrc),
                'tlyric' => '',
            ), JSON_UNESCAPED_UNICODE);
        }
        $decoder = new Decoder();
        $xmlContent = $decoder->decode($lrc);
        $pattern = '/<Lyric_1\s+[^>]*LyricContent="([^"]+)"/';
        preg_match($pattern, $xmlContent, $matches);
        $pureLyric = isset($matches[1]) ? html_entity_decode($matches[1]) : '';
        $qrcf = json_encode(['lyric'  => $pureLyric, 'tlyric' => ''], JSON_UNESCAPED_UNICODE);
        return $qrcf;
    }

    private function tencent_lyric($result)
    {
        $result = substr($result, 18, -1);
        $result = json_decode($result, true);
        $data = array(
            'lyric'  => isset($result['lyric']) ? base64_decode($result['lyric']) : '',
            'tlyric' => isset($result['trans']) ? base64_decode($result['trans']) : '',
        );

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function format_netease($data)
    {
        $result = array(
            'id'       => $data['id'],
            'name'     => $data['name'],
            'artist'   => array(),
            'album'    => $data['al']['name'],
            'pic_id'   => isset($data['al']['pic_str']) ? $data['al']['pic_str'] : $data['al']['pic'],
            'url_id'   => $data['id'],
            'lyric_id' => $data['id'],
            'source'   => 'netease',
        );
        if (isset($data['al']['picUrl'])) {
            preg_match('/\/(\d+)\./', $data['al']['picUrl'], $match);
            $result['pic_id'] = $match[1];
        }
        foreach ($data['ar'] as $vo) {
            $result['artist'][] = $vo['name'];
        }

        return $result;
    }

    protected function format_tencent($data)
    {
        if (isset($data['musicData'])) {
            $data = $data['musicData'];
        }
        $result = array(
            'id'       => $data['mid'],
            'name'     => $data['name'],
            'artist'   => array(),
            'album'    => trim($data['album']['title']),
            'pic_id'   => $data['album']['mid'],
            'url_id'   => $data['mid'],
            'lyric_id' => $data['mid'],
            'source'   => 'tencent',
        );
        foreach ($data['singer'] as $vo) {
            $result['artist'][] = $vo['name'];
        }

        return $result;
    }

    private function yrcToVerbatim($lyric, $tlyric = "")
    {
        $lines = preg_split('/\\\\n|\n|\r\n|\r/', $lyric);
        
        // Parse translation lyrics if provided
        $transMap = [];
        if (!empty($tlyric)) {
            $tlines = preg_split('/\\\\n|\n|\r\n|\r/', $tlyric);
            foreach ($tlines as $tline) {
                $tline = trim($tline);
                if (empty($tline)) continue;
                if (preg_match('/^\[(\d+):(\d+)(\.(\d+))?\](.*)$/', $tline, $matches)) {
                    $min = intval($matches[1]);
                    $sec = intval($matches[2]);
                    $ms = isset($matches[4]) ? intval(str_pad($matches[4], 3, '0', STR_PAD_RIGHT)) : 0;
                    if (isset($matches[4]) && strlen($matches[4]) == 2) $ms = intval($matches[4]) * 10;
                    
                    $timeMs = $min * 60000 + $sec * 1000 + $ms;
                    $transMap[$timeMs] = isset($matches[5]) ? trim($matches[5]) : '';
                }
            }
        }

        $result = [];

        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];
            $line = trim($line);
            if (empty($line)) continue;

            // Parse line header [start, duration]
            if (!preg_match('/^\[(\d+),(\d+)\]/', $line, $matches)) {
                // Not a YRC line, check if it's metadata or standard lrc
                // For now, if we are parsing YRC, we might want to keep other lines or ignore?
                // If the whole file is YRC, metadata also has [d,d] usually.
                // If it's a mix or invalid, let's keep it as is?
                // Or if we return mixed content it might break players expecting strict format.
                // But the user example shows strict YRC.
                // Let's assume if it doesn't match, we append it (e.g. metadata without timestamps if any?)
                // Actually user example: [0,1000](0,1000,0) Metadata
                // So metadata is also timestamped.
                // If strict YRC, maybe ignore lines that don't match?
                // Let's keep lines that don't match for safety? No, that might produce garbage.
                // Let's skip.
                if (preg_match('/^\[.*?\]/', $line)) {
                    // It looks like a tag but not [d,d]. Maybe [ti:title]?
                    // Standard LRC tags. Keep them.
                    $result[] = $line;
                }
                continue;
            }

            $lineStart = intval($matches[1]);
            $lineDuration = intval($matches[2]);
            $lineEnd = $lineStart + $lineDuration;

            $content = substr($line, strlen($matches[0]));

            if (preg_match_all('/\((\d+),(\d+),(\d+)\)(.*?)(?=\(\d+,\d+,\d+\)|$)/', $content, $wordMatches, PREG_SET_ORDER)) {
                $newLine = "";
                $lastWordEnd = 0;
                
                foreach ($wordMatches as $wm) {
                    $start = intval($wm[1]);
                    $duration = intval($wm[2]);
                    $text = $wm[4];

                    $formattedStart = $this->formatTime($start);
                    $newLine .= $formattedStart . $text;
                    $lastWordEnd = $start + $duration;
                }
                $newLine .= $this->formatTime($lastWordEnd);
                $result[] = $newLine;
            } else {
                // Fallback for lines without word timestamps
                $result[] = $this->formatTime($lineStart) . $content . $this->formatTime($lineEnd);
            }
            
            // Find best matching translation line
            $bestMatchKey = null;
            ksort($transMap); // Ensure chronological order

            $isLineMetadata = preg_match('/(作词|作曲|制作人|编曲|歌手|演唱|专辑|发行)/u', $content) > 0;

            foreach ($transMap as $time => $txt) {
                $isTransMetadata = preg_match('/(作词|作曲|制作人|编曲|歌手|演唱|专辑|发行|by:|歌词|字幕|翻译|校对)/iu', $txt) > 0;

                // 核心逻辑：元数据行只能匹配元数据翻译，普通歌词行只能匹配普通歌词翻译
                if ($isLineMetadata !== $isTransMetadata) continue;

                $diff = abs($time - $lineStart);
                if ($diff < 1500) { // Tolerance
                    $bestMatchKey = $time;
                    break; // 优先取时间轴上最早匹配的，以保持歌词序列一致
                }
            }
            
            if ($bestMatchKey !== null) {
                $result[] = $transMap[$bestMatchKey];
                unset($transMap[$bestMatchKey]);
            }
        }

        return implode("\n", $result);
    }

    private function formatTime($ms)
    {
        $seconds = floor($ms / 1000);
        $milliseconds = $ms % 1000;
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        $cs = floor($milliseconds / 10);
        
        return sprintf("[%02d:%02d.%02d]", $minutes, $seconds, $cs);
    }
}
