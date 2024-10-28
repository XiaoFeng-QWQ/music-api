<?php

/**
 * Meting music framework
 * https://i-meto.com
 * https://github.com/metowolf/Meting
 * Version 1.5.10.
 *
 * Copyright 2019, METO Sheel <i@i-meto.com>
 * Released under the MIT license
 */

namespace Metowolf;

class Meting
{
    const VERSION = '1.5.10';

    public $raw;
    public $data;
    public $info;
    public $error;
    public $status;

    public $server;
    public $proxy = null;
    public $format = false;
    public $header;

    public function __construct($value = 'netease')
    {
        $this->site($value);
    }

    public function site($value)
    {
        $suppose = array('netease', 'tencent', 'xiami', 'kugou', 'baidu', 'kuwo');
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
                    'url'    => 'http://music.163.com/api/cloudsearch/pc',
                    'body'   => array(
                        's'      => $keyword,
                        'type'   => isset($option['type']) ? $option['type'] : 1,
                        'limit'  => isset($option['limit']) ? $option['limit'] : 30,
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
                        'n'        => isset($option['limit']) ? $option['limit'] : 30,
                        'w'        => $keyword,
                        'aggr'     => 1,
                        'lossless' => 1,
                        'cr'       => 1,
                        'new_json' => 1,
                    ),
                    'format' => 'data.song.list',
                );
                break;
            case 'xiami':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://acs.m.xiami.com/h5/mtop.alimusic.search.searchservice.searchsongs/1.0/',
                    'body'   => array(
                        'data' => array(
                            'key'      => $keyword,
                            'pagingVO' => array(
                                'page'     => isset($option['page']) ? $option['page'] : 1,
                                'pageSize' => isset($option['limit']) ? $option['limit'] : 30,
                            ),
                        ),
                        'r' => 'mtop.alimusic.search.searchservice.searchsongs',
                    ),
                    'encode' => 'xiami_sign',
                    'format' => 'data.data.songs',
                );
                break;
            case 'kugou':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://mobilecdn.kugou.com/api/v3/search/song',
                    'body'   => array(
                        'api_ver'   => 1,
                        'area_code' => 1,
                        'correct'   => 1,
                        'pagesize'  => isset($option['limit']) ? $option['limit'] : 30,
                        'plat'      => 2,
                        'tag'       => 1,
                        'sver'      => 5,
                        'showtype'  => 10,
                        'page'      => isset($option['page']) ? $option['page'] : 1,
                        'keyword'   => $keyword,
                        'version'   => 8990,
                    ),
                    'format' => 'data.info',
                );
                break;
            case 'baidu':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://musicapi.taihe.com/v1/restserver/ting',
                    'body'   => array(
                        'from'      => 'qianqianmini',
                        'method'    => 'baidu.ting.search.merge',
                        'isNew'     => 1,
                        'platform'  => 'darwin',
                        'page_no'   => isset($option['page']) ? $option['page'] : 1,
                        'query'     => $keyword,
                        'version'   => '11.2.1',
                        'page_size' => isset($option['limit']) ? $option['limit'] : 30,
                    ),
                    'format' => 'result.song_info.song_list',
                );
                break;
            case 'kuwo':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://www.kuwo.cn/api/www/search/searchMusicBykeyWord',
                    'body'   => array(
                        'key'         => $keyword,
                        'pn'          => isset($option['page']) ? $option['page'] : 1,
                        'rn'          => isset($option['limit']) ? $option['limit'] : 30,
                        'httpsStatus' => 1,
                    ),
                    'format' => 'data.list',
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
                    'url'    => 'http://music.163.com/api/v3/song/detail/',
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
            case 'xiami':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://acs.m.xiami.com/h5/mtop.alimusic.music.songservice.getsongdetail/1.0/',
                    'body'   => array(
                        'data' => array(
                            'songId' => $id,
                        ),
                        'r' => 'mtop.alimusic.music.songservice.getsongdetail',
                    ),
                    'encode' => 'xiami_sign',
                    'format' => 'data.data.songDetail',
                );
                break;
            case 'kugou':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'http://m.kugou.com/app/i/getSongInfo.php',
                    'body'   => array(
                        'cmd'  => 'playInfo',
                        'hash' => $id,
                        'from' => 'mkugou',
                    ),
                    'format' => '',
                );
                break;
            case 'baidu':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://musicapi.taihe.com/v1/restserver/ting',
                    'body'   => array(
                        'from'     => 'qianqianmini',
                        'method'   => 'baidu.ting.song.getInfos',
                        'songid'   => $id,
                        'res'      => 1,
                        'platform' => 'darwin',
                        'version'  => '1.0.0',
                    ),
                    'encode' => 'baidu_AESCBC',
                    'format' => 'songinfo',
                );
                break;
            case 'kuwo':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://www.kuwo.cn/api/www/music/musicInfo',
                    'body'   => array(
                        'mid'         => $id,
                        'httpsStatus' => 1,
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
                    'url'    => 'http://music.163.com/api/v1/album/' . $id,
                    'body'   => array(
                        'total'         => 'true',
                        'offset'        => '0',
                        'id'            => $id,
                        'limit'         => '1000',
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
            case 'xiami':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://acs.m.xiami.com/h5/mtop.alimusic.music.albumservice.getalbumdetail/1.0/',
                    'body'   => array(
                        'data' => array(
                            'albumId' => $id,
                        ),
                        'r' => 'mtop.alimusic.music.albumservice.getalbumdetail',
                    ),
                    'encode' => 'xiami_sign',
                    'format' => 'data.data.albumDetail.songs',
                );
                break;
            case 'kugou':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://mobilecdn.kugou.com/api/v3/album/song',
                    'body'   => array(
                        'albumid'   => $id,
                        'area_code' => 1,
                        'plat'      => 2,
                        'page'      => 1,
                        'pagesize'  => -1,
                        'version'   => 8990,
                    ),
                    'format' => 'data.info',
                );
                break;
            case 'baidu':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://musicapi.taihe.com/v1/restserver/ting',
                    'body'   => array(
                        'from'     => 'qianqianmini',
                        'method'   => 'baidu.ting.album.getAlbumInfo',
                        'album_id' => $id,
                        'platform' => 'darwin',
                        'version'  => '11.2.1',
                    ),
                    'format' => 'songlist',
                );
                break;
            case 'kuwo':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://www.kuwo.cn/api/www/playlist/playListInfo',
                    'body'   => array(
                        'pid'         => $id,
                        'httpsStatus' => 1,
                    ),
                    'format' => 'data.musicList',
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
                    'url'    => 'http://music.163.com/api/v1/artist/' . $id,
                    'body'   => array(
                        'ext'           => 'true',
                        'private_cloud' => 'true',
                        'ext'           => 'true',
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
            case 'xiami':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://acs.m.xiami.com/h5/mtop.alimusic.music.songservice.getartistsongs/1.0/',
                    'body'   => array(
                        'data' => array(
                            'artistId' => $id,
                            'pagingVO' => array(
                                'page'     => 1,
                                'pageSize' => $limit,
                            ),
                        ),
                        'r' => 'mtop.alimusic.music.songservice.getartistsongs',
                    ),
                    'encode' => 'xiami_sign',
                    'format' => 'data.data.songs',
                );
                break;
            case 'kugou':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://mobilecdn.kugou.com/api/v3/singer/song',
                    'body'   => array(
                        'singerid'  => $id,
                        'area_code' => 1,
                        'page'      => 1,
                        'plat'      => 0,
                        'pagesize'  => $limit,
                        'version'   => 8990,
                    ),
                    'format' => 'data.info',
                );
                break;
            case 'baidu':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://musicapi.taihe.com/v1/restserver/ting',
                    'body'   => array(
                        'from'     => 'qianqianmini',
                        'method'   => 'baidu.ting.artist.getSongList',
                        'artistid' => $id,
                        'limits'   => $limit,
                        'platform' => 'darwin',
                        'offset'   => 0,
                        'tinguid'  => 0,
                        'version'  => '11.2.1',
                    ),
                    'format' => 'songlist',
                );
                break;
            case 'kuwo':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://www.kuwo.cn/api/www/artist/artistMusic',
                    'body'   => array(
                        'artistid'    => $id,
                        'httpsStatus' => 1,
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
                $api = array(
                    'method' => 'POST',
                    'url'    => 'http://music.163.com/api/v6/playlist/detail',
                    'body'   => array(
                        's'  => '0',
                        'id' => $id,
                        'n'  => '1000',
                        't'  => '0',
                    ),
                    'encode' => 'netease_AESCBC',
                    'format' => 'playlist.tracks',
                );
                break;
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
            case 'xiami':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://acs.m.xiami.com/h5/mtop.alimusic.music.list.collectservice.getcollectdetail/1.0/',
                    'body'   => array(
                        'data' => array(
                            'listId'     => $id,
                            'isFullTags' => false,
                            'pagingVO'   => array(
                                'page'     => 1,
                                'pageSize' => 1000,
                            ),
                        ),
                        'r' => 'mtop.alimusic.music.list.collectservice.getcollectdetail',
                    ),
                    'encode' => 'xiami_sign',
                    'format' => 'data.data.collectDetail.songs',
                );
                break;
            case 'kugou':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://mobilecdn.kugou.com/api/v3/special/song',
                    'body'   => array(
                        'specialid' => $id,
                        'area_code' => 1,
                        'page'      => 1,
                        'plat'      => 2,
                        'pagesize'  => -1,
                        'version'   => 8990,
                    ),
                    'format' => 'data.info',
                );
                break;
            case 'baidu':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://musicapi.taihe.com/v1/restserver/ting',
                    'body'   => array(
                        'from'     => 'qianqianmini',
                        'method'   => 'baidu.ting.diy.gedanInfo',
                        'listid'   => $id,
                        'platform' => 'darwin',
                        'version'  => '11.2.1',
                    ),
                    'format' => 'content',
                );
                break;
            case 'kuwo':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://www.kuwo.cn/api/www/playlist/playListInfo',
                    'body'   => array(
                        'pid'         => $id,
                        'httpsStatus' => 1,
                    ),
                    'format' => 'data.musicList',
                );
                break;
        }

        return $this->exec($api);
    }

    public function url($id, $br = 320)
    {
        switch ($this->server) {
            case 'netease':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'http://music.163.com/api/song/enhance/player/url',
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
            case 'xiami':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://acs.m.xiami.com/h5/mtop.alimusic.music.songservice.getsongs/1.0/',
                    'body'   => array(
                        'data' => array(
                            'songIds' => array(
                                $id,
                            ),
                        ),
                        'r' => 'mtop.alimusic.music.songservice.getsongs',
                    ),
                    'encode' => 'xiami_sign',
                    'decode' => 'xiami_url',
                );
                break;
            case 'kugou':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'http://media.store.kugou.com/v1/get_res_privilege',
                    'body'   => json_encode(
                        array(
                            'relate'    => 1,
                            'userid'    => '0',
                            'vip'       => 0,
                            'appid'     => 1000,
                            'token'     => '',
                            'behavior'  => 'download',
                            'area_code' => '1',
                            'clientver' => '8990',
                            'resource'  => array(array(
                                'id'   => 0,
                                'type' => 'audio',
                                'hash' => $id,
                            )),
                        )
                    ),
                    'decode' => 'kugou_url',
                );
                break;
            case 'baidu':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://musicapi.taihe.com/v1/restserver/ting',
                    'body'   => array(
                        'from'     => 'qianqianmini',
                        'method'   => 'baidu.ting.song.getInfos',
                        'songid'   => $id,
                        'res'      => 1,
                        'platform' => 'darwin',
                        'version'  => '1.0.0',
                    ),
                    'encode' => 'baidu_AESCBC',
                    'decode' => 'baidu_url',
                );
                break;
            case 'kuwo':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://www.kuwo.cn/url',
                    'body'   => array(
                        'rid'         => $id,
                        'response'    => 'url',
                        'type'        => 'convert_url3',
                        'br'          => '128kmp3',
                        'from'        => 'web',
                        't'           => time(),
                        'httpsStatus' => 1,
                    ),
                    'decode' => 'kuwo_url',
                );
                break;
        }
        return $this->exec($api);
    }

    public function lyric($id)
    {
        switch ($this->server) {
            case 'netease':
                $api = array(
                    'method' => 'POST',
                    'url'    => 'http://music.163.com/api/song/lyric',
                    'body'   => array(
                        'id' => $id,
                        'os' => 'linux',
                        'lv' => -1,
                        'kv' => -1,
                        'tv' => -1,
                    ),
                    'encode' => 'netease_AESCBC',
                    'decode' => 'netease_lyric',
                );
                break;
            case 'tencent':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://c.y.qq.com/lyric/fcgi-bin/fcg_query_lyric_new.fcg',
                    'body'   => array(
                        'songmid' => $id,
                        'g_tk'    => '5381',
                    ),
                    'decode' => 'tencent_lyric',
                );
                break;
            case 'xiami':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'https://acs.m.xiami.com/h5/mtop.alimusic.music.lyricservice.getsonglyrics/1.0/',
                    'body'   => array(
                        'data' => array(
                            'songId' => $id,
                        ),
                        'r' => 'mtop.alimusic.music.lyricservice.getsonglyrics',
                    ),
                    'encode' => 'xiami_sign',
                    'decode' => 'xiami_lyric',
                );
                break;
            case 'kugou':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://krcs.kugou.com/search',
                    'body'   => array(
                        'keyword'  => '%20-%20',
                        'ver'      => 1,
                        'hash'     => $id,
                        'client'   => 'mobi',
                        'man'      => 'yes',
                    ),
                    'decode' => 'kugou_lyric',
                );
                break;
            case 'baidu':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://musicapi.taihe.com/v1/restserver/ting',
                    'body'   => array(
                        'from'     => 'qianqianmini',
                        'method'   => 'baidu.ting.song.lry',
                        'songid'   => $id,
                        'platform' => 'darwin',
                        'version'  => '1.0.0',
                    ),
                    'decode' => 'baidu_lyric',
                );
                break;
            case 'kuwo':
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://m.kuwo.cn/newh5/singles/songinfoandlrc',
                    'body'   => array(
                        'musicId'     => $id,
                        'httpsStatus' => 1,
                    ),
                    'decode' => 'kuwo_lyric',
                );
                break;
        }

        return $this->exec($api);
    }

    public function pic($id, $size = 300)
    {
        switch ($this->server) {
            case 'netease':
                $url = 'https://p3.music.126.net/' . $this->netease_encryptId($id) . '/' . $id . '.jpg?param=' . $size . 'y' . $size;
                break;
            case 'tencent':
                $url = 'https://y.gtimg.cn/music/photo_new/T002R' . $size . 'x' . $size . 'M000' . $id . '.jpg?max_age=2592000';
                break;
            case 'xiami':
                $format = $this->format;
                $data = $this->format(false)->song($id);
                $this->format = $format;
                $data = json_decode($data, true);
                $url = $data['data']['data']['songDetail']['albumLogo'];
                $url = str_replace('http:', 'https:', $url) . '@1e_1c_100Q_' . $size . 'h_' . $size . 'w';
                break;
            case 'kugou':
                $format = $this->format;
                $data = $this->format(false)->song($id);
                $this->format = $format;
                $data = json_decode($data, true);
                $url = $data['imgUrl'];
                $url = str_replace('{size}', '400', $url);
                break;
            case 'baidu':
                $format = $this->format;
                $data = $this->format(false)->song($id);
                $this->format = $format;
                $data = json_decode($data, true);
                $url = isset($data['songinfo']['pic_radio']) ? $data['songinfo']['pic_radio'] : $data['songinfo']['pic_small'];
                break;
            case 'kuwo':
                $format = $this->format;
                $data = $this->format(false)->song($id);
                $this->format = $format;
                $data = json_decode($data, true);
                $url = isset($data['data']['pic']) ? $data['data']['pic'] : $data['data']['albumpic'];
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
                    'Cookie'          => 'EVNSM=1.0.0; NMCID=utfemb.1688318360708.01.4; versioncode=8010011; MUSIC_A_T=1622879183215; URS_APPID=0A269C9EF77B633F6EB1F84538B628589793EAA9876217298CA0FFC429FD3D99B6F0E44087D61EFC06BE92279CD6EEC6; buildver=230615142817; resolution=1280x720; sDeviceId=CTYwOjkxOmYzOjEzOjk3OjBlCTgyMWQ0MmQ0MzRkYTIzNGMJOTY5NGExZDc2NjVkZDMxMg%3D%3D; ntes_kaola_ad=1; mobilename=vivoY66; __csrf=e19d59f0548282ab8285d75856c61efe; osver=6.0.1; MUSIC_U=00C27F86075E72A2E92A449DF3A697FEEFAF31414577567F5F0C4E3E898FE45DC9799EF1B82B168F619242D84502E4BB304B7E0ED771B8FE8CD6742C45E460402289698AB6F6435FE355170C53130A5E1B290AFCE2FB47002BA3B8822E037464A211D90A793224C9D3690705FCFCCD38C51AE38423B4662E559DB9291CFD17CBBCACAF241137A296CE7BF631313F2121D05F7C648BD23D671F72EEEBC4B4A7815071A7FD98E68A2C79E532200CF62F19E4EB3812E5FBE9D490F4CF97FAF1CEA2FA1A68E51574D9E193B75141BB7D7BDF0E26EBFBA3B88A6762E137307A5531093E81946F46B8FEC209158042E0CF8DCD2F68E529C1C02BC5B69C731079E0499A5C069595E5A9106D18D3E47C8927412E930FE1F4CF88367AD0063B97DB26A93EC24DBDFE6D2876F4E90CA8A41012107A0B72BCC14FA9DC74DE84F3614EA273D58D1C823025FD84A8CD6C4061931C8FA4E453AA4F97D59DE1875B98E5251F00E3AD63B3A2EE6AFF68631E6F26598EF61AEE403A19301F37EE0C5D204B7C6B932553; channel=bubugao1; os=android; deviceId=CTYwOjkxOmYzOjEzOjk3OjBlCTgyMWQ0MmQ0MzRkYTIzNGMJOTY5NGExZDc2NjVkZDMxMg%3D%3D; appver=8.10.11; NMDI=Q1NKTQkBDABTDOA6gFpt5VBjstNUAAAAykTBiMM5he%2Ff0FiOhsk%2FzZ7sEleXNdG4VhcYc8cGOIulUv8DQlT0ctn8gpeK4wMFFHwofvSNztRk%2FRMLEiZy3xVRe6cUvf5310n%2FnOxOGfX8qrUo; MUSIC_R_T=1622879987493; NMTID=00OkgU1S3cUZfB84E28gUk3iiD1jCwAAAGJF53ARw; packageType=release',
                    'User-Agent'      => 'NeteaseMusic/8.9.40.230301161729(8009040);Dalvik/2.1.0 (Linux; U; Android 6.0.1; vivo Y66 Build/MMB29M)',
                    'X-Real-IP'       => long2ip(mt_rand(1884815360, 1884890111)),
                    'Accept'          => '*/*',
                    'Accept-Language' => 'zh-CN,zh;q=0.9',
                    'Connection'      => 'keep-alive',
                    'Content-Type'    => 'application/x-www-form-urlencoded',
                );
            case 'tencent':
                return array(
                    'Referer'         => 'http://y.qq.com',
                    'Cookie'          => 'tvfe_boss_uuid=dadcf19aaf29f1b5; fqm_pvqid=2ee67ebb-8362-409c-9ee5-2fd86cf6ec1b; fqm_sessionid=3c63ea8e-1b7e-4573-a49c-1de68cac6a53; pvpqqcomrouteLine=index_proj0_index_proj0_index_proj0_index; RK=gGAgKd0qmn; pac_uid=1_2818872729; iip=0; verifysession=h01963a83c11d5f90b4e24d9906cdfd72997963f5e7c8480dc36f623dfc12470966dbba0d3923e91ab5; vversion_name=8.2.95; video_omgid=1b9abd9bb42448bf; o_cookie=2818872729; _qpsvr_localtk=0.4366826001392654; ts_refer=ADTAGmyqq; ptui_loginuin=2818872729; ptcz=8475f6fe0c61101f80688c9d7d786fd01d37f961f925b3895b0dd1e9a75f9141; login_type=1; psrf_qqaccess_token=16BA5781A1AACD58A096FC893D8C53F3; psrf_qqrefresh_token=9DADDF8426BDD879ADAA971EEDBD081E; psrf_qqunionid=2D62187998D5A796BD7CF449B252DDC1; tmeLoginType=2; wxunionid=; euin=owc5NeclowSANv**; qm_keyst=Q_H_L_57X4jyb0I8faYJlZHjwLS3DQjFpOu5eEmOHF0lio4NoHwjWgvvZjrNQ; psrf_qqopenid=E59DE7DFB8E5359AF08B8D57E9F7D4AC; qm_keyst=Q_H_L_57X4jyb0I8faYJlZHjwLS3DQjFpOu5eEmOHF0lio4NoHwjWgvvZjrNQ; qqmusic_key=Q_H_L_57X4jyb0I8faYJlZHjwLS3DQjFpOu5eEmOHF0lio4NoHwjWgvvZjrNQ; psrf_access_token_expiresAt=1690643128; wxopenid=; psrf_musickey_createtime=1682867128; wxrefresh_token=; uin=2818872729; pgv_info=ssid=s390846; ts_last=y.qq.com/; pgv_pvid=8545524908; ts_uid=7424733059',
                    'User-Agent'      => 'QQ%E9%9F%B3%E4%B9%90/54409 CFNetwork/901.1 Darwin/17.6.0 (x86_64)',
                    'Accept'          => '*/*',
                    'Accept-Language' => 'zh-CN,zh;q=0.8,gl;q=0.6,zh-TW;q=0.4',
                    'Connection'      => 'keep-alive',
                    'Content-Type'    => 'application/x-www-form-urlencoded',
                );
            case 'xiami':
                return array(
                    'Cookie'          => '_m_h5_tk=15d3402511a022796d88b249f83fb968_1511163656929; _m_h5_tk_enc=b6b3e64d81dae577fc314b5c5692df3c',
                    'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/537.36 (KHTML, like Gecko) XIAMI-MUSIC/3.1.1 Chrome/56.0.2924.87 Electron/1.6.11 Safari/537.36',
                    'Accept'          => 'application/json',
                    'Content-type'    => 'application/x-www-form-urlencoded',
                    'Accept-Language' => 'zh-CN',
                );
            case 'kugou':
                return array(
                    'User-Agent'      => 'IPhone-8990-searchSong',
                    'UNI-UserAgent'   => 'iOS11.4-Phone8990-1009-0-WiFi',
                );
            case 'baidu':
                return array(
                    'Cookie'          => 'BAIDUID=' . $this->getRandomHex(32) . ':FG=1',
                    'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) baidu-music/1.2.1 Chrome/66.0.3359.181 Electron/3.0.5 Safari/537.36',
                    'Accept'          => '*/*',
                    'Content-type'    => 'application/json;charset=UTF-8',
                    'Accept-Language' => 'zh-CN',
                );
            case 'kuwo':
                return array(
                    'Cookie'          => 'Hm_lvt_cdb524f42f0ce19b169a8071123a4797=1623339177,1623339183; _ga=GA1.2.1195980605.1579367081; Hm_lpvt_cdb524f42f0ce19b169a8071123a4797=1623339982; kw_token=3E7JFQ7MRPL; _gid=GA1.2.747985028.1623339179; _gat=1',
                    'csrf'            => '3E7JFQ7MRPL',
                    'Host'            => 'www.kuwo.cn',
                    'Referer'         => 'http://www.kuwo.cn/',
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36',
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


        $body = openssl_encrypt($body, 'aes-128-cbc', $nonce, false, $vi);
        $body = openssl_encrypt($body, 'aes-128-cbc', $skey, false, $vi);

        if (extension_loaded('bcmath')) {
            // 用 mb_convert_encoding 替代 utf8_encode
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

    private function baidu_AESCBC($api)
    {
        $key = 'DBEECF8C50FD160E';
        $vi = '1231021386755796';

        $data = 'songid=' . $api['body']['songid'] . '&ts=' . intval(microtime(true) * 1000);

        $data = openssl_encrypt($data, 'aes-128-cbc', $key, false, $vi);

        $api['body']['e'] = $data;

        return $api;
    }

    private function xiami_sign($api)
    {
        $data = $this->curl('https://acs.m.xiami.com/h5/mtop.alimusic.recommend.songservice.getdailysongs/1.0/?appKey=12574478&t=1560663823000&dataType=json&data=%7B%22requestStr%22%3A%22%7B%5C%22header%5C%22%3A%7B%5C%22platformId%5C%22%3A%5C%22mac%5C%22%7D%2C%5C%22model%5C%22%3A%5B%5D%7D%22%7D&api=mtop.alimusic.recommend.songservice.getdailysongs&v=1.0&type=originaljson&sign=22ad1377ee193f3e2772c17c6192b17c', null, 1);
        preg_match_all('/_m_h5[^;]+/', $data->raw, $match);
        $this->header['Cookie'] = $match[0][0] . '; ' . $match[0][1];
        $data = json_encode(array(
            'requestStr' => json_encode(array(
                'header' => array(
                    'platformId' => 'mac',
                ),
                'model' => $api['body']['data'],
            )),
        ));
        $appkey = '12574478';
        $cookie = $this->header['Cookie'];
        preg_match('/_m_h5_tk=([^_]+)/', $cookie, $match);
        $token = $match[1];
        $t = time() * 1000;
        $sign = md5(sprintf('%s&%s&%s&%s', $token, $t, $appkey, $data));
        $api['body'] = array(
            'appKey'   => $appkey,
            't'        => $t,
            'dataType' => 'json',
            'data'     => $data,
            'api'      => $api['body']['r'],
            'v'        => '1.0',
            'type'     => 'originaljson',
            'sign'     => $sign,
        );

        return $api;
    }

    private function netease_encryptId($id)
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
                'br'   => $data['data'][0]['br'] / 1000,
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
            array('size_flac', 999, 'F000', 'flac'),
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
            'url'    => 'https://u.y.qq.com/cgi-bin/musicu.fcg',
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
            if ($data['data'][0]['file'][$vo[0]] && $vo[1]) {
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

    private function xiami_url($result)
    {
        $data = json_decode($result, true);

        $type = array(
            's' => 740,
            'h' => 320,
            'l' => 128,
            'f' => 64,
            'e' => 32,
        );
        $max = 0;
        $url = array();
        foreach ($data['data']['data']['songs'][0]['listenFiles'] as $vo) {
            if ($type[$vo['quality']] && $type[$vo['quality']] > $max) {
                $max = $type[$vo['quality']];
                $url = array(
                    'url'  => $vo['listenFile'],
                    'size' => $vo['fileSize'],
                    'br'   => $type[$vo['quality']],
                );
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

    private function kugou_url($result)
    {
        $data = json_decode($result, true);

        $max = 0;
        $url = array();
        foreach ($data['data'][0]['relate_goods'] as $vo) {
            if ($vo['info']['bitrate'] && $vo['info']['bitrate'] > $max) {
                $api = array(
                    'method' => 'GET',
                    'url'    => 'http://trackercdn.kugou.com/i/v2/',
                    'body'   => array(
                        'hash'     => $vo['hash'],
                        'key'      => md5($vo['hash'] . 'kgcloudv2'),
                        'pid'      => 3,
                        'behavior' => 'play',
                        'cmd'      => '25',
                        'version'  => 8990,
                    ),
                );
                $t = json_decode($this->exec($api), true);
                if (isset($t['url'])) {
                    $max = $t['bitRate'] / 1000;
                    $url = array(
                        'url'  => reset($t['url']),
                        'size' => $t['fileSize'],
                        'br'   => $t['bitRate'] / 1000,
                    );
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

    private function baidu_url($result)
    {
        $data = json_decode($result, true);

        $max = 0;
        $url = array();
        foreach ($data['songurl']['url'] as $vo) {
            if ($vo['file_bitrate'] && $vo['file_bitrate'] > $max) {
                $url = array(
                    'url' => $vo['file_link'],
                    'br'  => $vo['file_bitrate'],
                );
            }
        }
        if (!isset($url['url'])) {
            $url = array(
                'url' => '',
                'br'  => -1,
            );
        }

        return json_encode($url);
    }

    private function kuwo_url($result)
    {
        $data = json_decode($result, true);

        $url = array();
        if ($data['code'] == 200 && isset($data['url'])) {
            $url = array(
                'url' => $data['url'],
                'br'  => 128,
            );
        } else {
            $url = array(
                'url' => '',
                'br'  => -1,
            );
        }

        return json_encode($url);
    }

    private function netease_lyric($result)
    {
        $result = json_decode($result, true);
        $data = array(
            'lyric'  => isset($result['lrc']['lyric']) ? $result['lrc']['lyric'] : '',
            'tlyric' => isset($result['tlyric']['lyric']) ? $result['tlyric']['lyric'] : '',
        );

        return json_encode($data, JSON_UNESCAPED_UNICODE);
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

    private function xiami_lyric($result)
    {
        $result = json_decode($result, true);

        if (count($result['data']['data']['lyrics'])) {
            $data = $result['data']['data']['lyrics'][0]['content'];
            $data = preg_replace('/<[^>]+>/', '', $data);
            preg_match_all('/\[([\d:\.]+)\](.*)\s\[x-trans\](.*)/i', $data, $match);
            if (count($match[0])) {
                for ($i = 0; $i < count($match[0]); $i++) {
                    $A[] = '[' . $match[1][$i] . ']' . $match[2][$i];
                    $B[] = '[' . $match[1][$i] . ']' . $match[3][$i];
                }
                $arr = array(
                    'lyric'  => str_replace($match[0], $A, $data),
                    'tlyric' => str_replace($match[0], $B, $data),
                );
            } else {
                $arr = array(
                    'lyric'  => $data,
                    'tlyric' => '',
                );
            }
        } else {
            $arr = array(
                'lyric'  => '',
                'tlyric' => '',
            );
        }

        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    private function kugou_lyric($result)
    {
        $result = json_decode($result, true);
        $api = array(
            'method' => 'GET',
            'url'    => 'http://lyrics.kugou.com/download',
            'body'   => array(
                'charset'   => 'utf8',
                'accesskey' => $result['candidates'][0]['accesskey'],
                'id'        => $result['candidates'][0]['id'],
                'client'    => 'mobi',
                'fmt'       => 'lrc',
                'ver'       => 1,
            ),
        );
        $data = json_decode($this->exec($api), true);
        $arr = array(
            'lyric'  => base64_decode($data['content']),
            'tlyric' => '',
        );

        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    private function baidu_lyric($result)
    {
        $result = json_decode($result, true);
        $data = array(
            'lyric'  => isset($result['lrcContent']) ? $result['lrcContent'] : '',
            'tlyric' => '',
        );

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function kuwo_lyric($result)
    {
        $result = json_decode($result, true);
        if (count($result['data']['lrclist'])) {
            $kuwolrc = '';
            for ($i = 0; $i < count($result['data']['lrclist']); $i++) {
                $otime = $result['data']['lrclist'][$i]['time'];
                $osec = explode('.', $otime)[0];
                $min = str_pad(floor($osec / 60), 2, "0", STR_PAD_LEFT);
                $sec = str_pad($osec - $min * 60, 2, "0", STR_PAD_LEFT);
                $msec = explode('.', $otime)[1];
                $olyric = $result['data']['lrclist'][$i]['lineLyric'];
                $kuwolrc = $kuwolrc . '[' . $min . ':' . $sec . '.' . $msec . ']' . $olyric . "\n";
            }
            $arr = array(
                'lyric'  => $kuwolrc,
                'tlyric' => '',
            );
        } else {
            $arr = array(
                'lyric'  => '',
                'tlyric' => '',
            );
        }
        return json_encode($arr, JSON_UNESCAPED_UNICODE);
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

    protected function format_xiami($data)
    {
        $result = array(
            'id'       => $data['songId'],
            'name'     => $data['songName'],
            'artist'   => array(),
            'album'    => $data['albumName'],
            'pic_id'   => $data['songId'],
            'url_id'   => $data['songId'],
            'lyric_id' => $data['songId'],
            'source'   => 'xiami',
        );
        foreach ($data['singerVOs'] as $vo) {
            $result['artist'][] = $vo['artistName'];
        }

        return $result;
    }

    protected function format_kugou($data)
    {
        $result = array(
            'id'       => $data['hash'],
            'name'     => isset($data['filename']) ? $data['filename'] : $data['fileName'],
            'artist'   => array(),
            'album'    => isset($data['album_name']) ? $data['album_name'] : '',
            'url_id'   => $data['hash'],
            'pic_id'   => $data['hash'],
            'lyric_id' => $data['hash'],
            'source'   => 'kugou',
        );
        list($result['artist'], $result['name']) = explode(' - ', $result['name'], 2);
        $result['artist'] = explode('、', $result['artist']);

        return $result;
    }

    protected function format_baidu($data)
    {
        $result = array(
            'id'       => $data['song_id'],
            'name'     => $data['title'],
            'artist'   => explode(',', $data['author']),
            'album'    => $data['album_title'],
            'pic_id'   => $data['song_id'],
            'url_id'   => $data['song_id'],
            'lyric_id' => $data['song_id'],
            'source'   => 'baidu',
        );

        return $result;
    }

    protected function format_kuwo($data)
    {
        $result = array(
            'id'       => $data['rid'],
            'name'     => $data['name'],
            'artist'   => explode(',', $data['artist']),
            'album'    => $data['album'],
            'pic_id'   => $data['rid'],
            'url_id'   => $data['rid'],
            'lyric_id' => $data['rid'],
            'source'   => 'kuwo',
        );

        return $result;
    }
}
