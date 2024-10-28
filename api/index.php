<?php
error_reporting(0);
// 设置API路径
define('API_URI', getpageurl());
// 设置中文歌词
define('TLYRIC', true);
// 设置歌单文件缓存及时间
define('CACHE', true);
define('CACHE_TIME', 86400);
// 设置短期缓存-需要安装apcu
define('APCU_CACHE', false);
// 设置AUTH密钥-更改'meting-secret'
define('AUTH', false);
define('AUTH_SECRET', 'meting-secret');

if (!isset($_GET['type']) || !isset($_GET['id'])) {
    include __DIR__ . '/docs/index.php';
    exit;
}

$server = 'netease';
$type = $_GET['type'];
$id = $_GET['id'];

if (AUTH && !validate_auth($type, $server, $id)) {
    http_response_code(403);
    exit;
}

set_content_type($type);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

include __DIR__ . '/src/Meting.php';

use Metowolf\Meting;

$api = new Meting($server);
$api->format(true);

if ($type === 'playlist') {
    handle_playlist_request($api, $server, $id);
} else {
    handle_other_requests($api, $type, $id, $server);
}

// 生成API URI
function getpageurl()
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'https://') . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
}

// 验证auth
function validate_auth($type, $server, $id)
{
    if (in_array($type, ['url', 'pic', 'lrc'])) {
        $auth = $_GET['auth'] ?? '';
        return $auth && $auth === auth($server . $type . $id);
    }
    return true;
}

// 设置内容类型
function set_content_type($type)
{
    $content_type = 'application/json; charset=utf-8';
    if (in_array($type, ['name', 'lrc', 'artist'])) {
        $content_type = 'text/plain; charset=utf-8';
    }
    header("Content-Type: $content_type");
}

// 处理歌单请求
function handle_playlist_request($api, $server, $id)
{
    $file_path = __DIR__ . "/cache/playlist/{$server}_{$id}.json";

    if (CACHE && file_exists($file_path) && $_SERVER['REQUEST_TIME'] - filectime($file_path) < CACHE_TIME) {
        echo file_get_contents($file_path);
        exit;
    }

    $data = $api->playlist($id);
    if ($data === '[]') {
        echo '{"error":"unknown playlist id"}';
        exit;
    }

    $playlist = build_playlist_data(json_decode($data));
    if (CACHE) {
        file_put_contents($file_path, $playlist);
    }
    echo $playlist;
}

// 构建歌单数据
function build_playlist_data($data)
{
    $playlist = [];
    foreach ($data as $song) {
        $playlist[] = [
            'name'   => $song->name,
            'artist' => implode('/', $song->artist),
            'url'    => build_api_url($song->source, 'url', $song->url_id),
            'pic'    => build_api_url($song->source, 'pic', $song->pic_id),
            'lrc'    => build_api_url($song->source, 'lrc', $song->lyric_id)
        ];
    }
    return json_encode($playlist);
}

// 构造API URL
function build_api_url($source, $type, $id)
{
    return API_URI . "?server=$source&type=$type&id=$id" . (AUTH ? '&auth=' . auth($source . $type . $id) : '');
}

// 处理其他请求
function handle_other_requests($api, $type, $id, $server)
{
    $apcu_key = "{$server}{$type}{$id}";
    if (APCU_CACHE && apcu_exists($apcu_key)) {
        return_data($type, apcu_fetch($apcu_key));
    }

    $data = get_request_data($api, $type, $id, $server);
    if (APCU_CACHE) {
        apcu_store($apcu_key, $data, $type === 'url' ? 600 : 36000);
    }
    return_data($type, $data);
}

// 根据请求类型获取数据
function get_request_data($api, $type, $id)
{
    if (in_array($type, ['url', 'pic', 'lrc'])) {
        return song2data($api, null, $type, $id);
    }

    $song = json_decode($api->song($id))[0] ?? null;
    if (!$song) {
        echo '{"error":"unknown song"}';
        exit;
    }

    return song2data($api, $song, $type, $id);
}

function auth($name)
{
    return hash_hmac('sha1', $name, AUTH_SECRET);
}

function song2data($api, $song, $type, $id)
{
    switch ($type) {
        case 'name':
            return $song->name;
        case 'artist':
            return implode('/', $song->artist);
        case 'url':
            return get_formatted_url($api->url($id, 320));
        case 'pic':
            return json_decode($api->pic($id, 90))->url;
        case 'lrc':
            return get_lyrics_data($api, $id);
        case 'song':
            return build_song_data($song);
    }
    exit;
}

function get_formatted_url($url_data)
{
    $url = json_decode($url_data)->url;
    return $url && $url[4] !== 's' ? str_replace('http', 'https', $url) : $url;
}

function get_lyrics_data($api, $id)
{
    $lyrics = json_decode($api->lyric($id));
    if (empty($lyrics->lyric)) {
        return '[00:00.00]这似乎是一首纯音乐呢，请尽情欣赏它吧！';
    }
    return TLYRIC && !empty($lyrics->tlyric) ? merge_lyrics($lyrics) : $lyrics->lyric;
}

function merge_lyrics($lyrics)
{
    $lrc_arr = explode("\n", $lyrics->lyric);
    $lrc_cn_arr = explode("\n", $lyrics->tlyric);
    $lrc_cn_map = array();

    foreach ($lrc_cn_arr as $line) {
        if (empty($line)) continue;
        [$key, $value] = explode(']', $line, 2);
        $lrc_cn_map[trim($key)] = trim(preg_replace('/\s\s+/', ' ', $value));
    }

    foreach ($lrc_arr as &$line) {
        $key = explode(']', $line, 2)[0];
        if (isset($lrc_cn_map[$key])) {
            $line .= ' (' . $lrc_cn_map[$key] . ')';
        }
    }

    return implode("\n", $lrc_arr);
}

function build_song_data($song)
{
    return json_encode([[
        'name'   => $song->name,
        'artist' => implode('/', $song->artist),
        'url'    => build_api_url($song->source, 'url', $song->url_id),
        'pic'    => build_api_url($song->source, 'pic', $song->pic_id),
        'lrc'    => build_api_url($song->source, 'lrc', $song->lyric_id)
    ]]);
}

function return_data($type, $data)
{
    if (in_array($type, ['url', 'pic'])) {
        header("Location: $data");
    } else {
        echo $data;
    }
    exit;
}
