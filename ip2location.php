<?php
/**
 * Created by PhpStorm.
 * User: qiao
 * Date: 2016/3/9
 * Time: 15:47
 */
require_once 'vendor/autoload.php';
use GeoIp2\Database\Reader;

/**
 * curl模拟get请求
 * @param $url
 * @param array $header
 * @param string $encoding
 * @return mixed
 */
function http_get($url, $header = [], $encoding = '')
{
    $ch = curl_init();
    //设置URL和相应选项
    $options = [
        CURLOPT_HEADER => false, //不返回头信息
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_ENCODING => $encoding,//处理编码
        CURLOPT_URL => $url
    ];
    curl_setopt_array($ch, $options);

    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

/**
 * 请参考 str_split http://php.net/manual/zh/function.str-split.php
 * @param $string
 * @param int $string_length
 * @return array
 */
function mb_str_split($string, $string_length = 1)
{
    if (mb_strlen($string) > $string_length || !$string_length) {
        do {
            $c = mb_strlen($string);
            $parts[] = mb_substr($string, 0, $string_length);
            $string = mb_substr($string, $string_length);
        } while (!empty($string));
    } else {
        $parts = array($string);
    }
    return $parts;
}

class Ip2location
{
    const BAIDU_AK = ''; //http://lbsyun.baidu.com/index.php?title=webapi/ip-api
    const BAIDU_APISTORE_AK = ''; //http://apistore.baidu.com/apiworks/servicedetail/114.html

    public static function find($ip, $type)
    {
        if (empty($ip) === true) {
            return "N/A";
        }

        $static_method = 'getLocFrom' . ucfirst(strtolower(trim($type)));

        $arr = forward_static_call([__CLASS__, $static_method], $ip);

        return implode($arr);
    }


    /**
     * 根据本地ip库获取信息
     * http://maxmind.github.io/GeoIP2-php/
     * @param $ip
     * @return array
     */
    private static function getLocFromLocal($ip)
    {
        $reader = new Reader('GeoLite2-City.mmdb');
        $record = $reader->city($ip);
        $province = $record->mostSpecificSubdivision->names['zh-CN'];
        $city = $record->city->names['zh-CN'];
        if ($province == $city . '市')
            $city = '';
        return [$province, $city];
    }

    /**
     * 根据126获得信息
     * @param $ip
     * @return array 省和市
     */
    private static function getLocFrom126($ip)
    {
        $url = sprintf("http://ip.ws.126.net/ipquery?ip=%s", $ip);
        $res = http_get($url);
        $str = mb_convert_encoding($res, 'UTF-8', 'GB2312,GBK');//将字符串转为utf8编码
        $str = str_replace(PHP_EOL, '', $str); //去除换行
        $pattern = '/^var lo=\"(\S+)\", lc=\"(\S*)\";var localAddress=\{.+\}$/';
        preg_match($pattern, $str, $matches);
        return [$matches[1], $matches[2]];
    }

    /**
     * 从360获取信息
     * http://ip.360.cn
     * @param $ip
     * @return array
     */
    private static function getLocFrom360($ip)
    {
        $url = sprintf("http://ip.360.cn/IPQuery/ipquery?ip=%s", $ip);
        $res = http_get($url);
        $obj = json_decode($res);
        $arr = [];
        if ($obj->errno == 0) {
            $tmp_arr = preg_split("/\s+/", $obj->data);
            $arr = [$tmp_arr[0], ''];
        } else {
            $arr = [$obj->errno, $obj->errmsg];
        }
        return $arr;
    }

    /**
     * 百度地图api获得ip信息
     * http://lbsyun.baidu.com/index.php?title=webapi/ip-api
     * 每个key支持10万次/天
     * @param $ip
     * @return array
     */
    private static function getLocFromBaidu($ip)
    {
        $url = sprintf("http://api.map.baidu.com/location/ip?ak=%s&ip=%s", static::BAIDU_AK, $ip);

        $res = http_get($url);
        $obj = json_decode($res);
        $arr = [];

        if ($obj->status == 0) {
            $arr = [$obj->content->address, ''];
        } else {
            $arr = [$obj->status, $obj->message];
        }
        return $arr;
    }

    /**
     * 根据百度api商店获得ip信息      * 250qps
     * http://apistore.baidu.com/apiworks/servicedetail/114.html
     * @param $ip
     * @return array
     */
    private static function getLocFromBaiduapi($ip)
    {
        $url = sprintf("http://apis.baidu.com/apistore/iplookupservice/iplookup?ip=%s", $ip);
        $header = ['apikey: ' . static::BAIDU_APISTORE_AK];

        $res = http_get($url, $header);
        $obj = json_decode($res);

        if ($obj->errNum == 0) {
            $province = $obj->retData->province;
            $city = $obj->retData->city;
            $city = $city == $province ? '' : $city;
            $arr = [$province, $city];
        } else {
            $arr = [$obj->errNum, $obj->errMsg];
        }

        return $arr;
    }

    /**
     * 从freegeoip.net获得ip信息
     * http://freegeoip.net/
     * @param $ip
     * @return array
     */
    private static function getLocFromFreegeoip($ip)
    {
        $url = sprintf("http://freegeoip.net/json/%s", $ip);
        $header = ["Accept-Language: zh-CN"];

        $res = http_get($url, $header);
        $obj = json_decode($res);

        if ($obj) {
            $province = $obj->region_name;
            $city = $obj->city;
            $city = $province == $city . '市' ? '' : $city;
            $arr = [$province, $city];
        } else {
            $arr = ['', 'not found'];
        }

        return $arr;
    }

    /**
     * http://ip138.com/ips138.asp
     * @param $ip
     * @return array
     */
    private static function getLocFromIp138($ip)
    {
        $url = sprintf("http://ip138.com/ips138.asp?ip=%s&action=2", $ip);

        $res = http_get($url, [], 'gb2312');
        $result = mb_convert_encoding($res, "utf-8", "gb2312");

        preg_match("@<li>(.+)</li><li>@iU", $result, $ipArray);
        $loc = $ipArray[1];
        $tmp_arr = preg_split("/\s+/", $loc);
        $tmp_str = mb_substr($tmp_arr[0], 6);
        $strlen = mb_strlen($tmp_str, 'utf-8');

        if ($strlen & 1) {
            $arr = [$tmp_str, ''];
        } else {
            //处理直辖市
            $tmp_arr1 = mb_str_split($tmp_str, $strlen / 2);
            if ($tmp_arr1[0] == $tmp_arr1[1]) {
                $arr = [$tmp_arr1[0], ''];
            } else {
                $arr = [$tmp_str, ''];
            }
        }

        return $arr;
    }

    /**
     * https://www.ipip.net/api.html | 每天1000次 |容易401 谨慎使用
     * @param $ip
     * @return array
     */
    private static function getLocFromIpip($ip)
    {
        $url = sprintf("http://freeapi.ipip.net/%s", $ip);

        $res = http_get($url, ["Referer: http://ipip.net/"]);
        $arr = json_decode($res, 1);

        $province = $arr[1];
        $city = $arr[2];
        $city = $province == $city ? '' : $city;

        return [$province, $city];
    }

    /**
     * http://whois.pconline.com.cn/
     * @param $ip
     * @return array
     */
    private static function getLocFromPac($ip)
    {
        $url = sprintf("http://whois.pconline.com.cn/ip.jsp?level=2&ip=%s", $ip);

        $res = http_get($url);
        $address = mb_convert_encoding($res, 'UTF-8', 'GB2312,GBK');

        return [$address, ''];
    }

    /**
     * http://ip.qq.com
     * @param $ip
     * @return array
     */
    private static function getLocFromQq($ip)
    {
        $url = sprintf("http://ip.qq.com/cgi-bin/searchip?searchip1=%s", $ip);
        $encoding = 'gb2312';

        $res = http_get($url, [], $encoding);
        $res = mb_convert_encoding($res, "utf-8", "gb2312"); // 编码转换，否则乱码

        preg_match("@<span>(.*)</span></p>@iU", $res, $matches);

        $loc = $matches[1];
        $tmp_arr = explode('&nbsp;', $loc);
        $arr = [$tmp_arr[0], ''];

        return $arr;
    }

    /**
     * 根据新浪api获取
     * @param $ip
     * @return array
     */
    private static function getLocFromSina($ip)
    {
        $url = sprintf("http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=%s", $ip);

        $res = http_get($url);
        if ($res < 0) {
            $arr = [$res, 'not found'];
        } else {
            $obj = json_decode($res);
            $province = $obj->province;
            $city = $obj->city;
            $city = $province == $city ? '' : $city;
            $arr = [$province, $city];
        }

        return $arr;
    }

    /**
     * http://ip.taobao.com | <10qps
     * @param $ip
     * @return array
     */
    private static function getLocFromTaobao($ip)
    {
        $url = sprintf("http://ip.taobao.com/service/getIpInfo2.php?ip=%s", $ip);

        $res = http_get($url);
        $obj = json_decode($res);

        if ($obj->code == 0) {
            $province = $obj->data->region;
            $city = $obj->data->city;
            $city = $province == $city ? '' : $city;
            $arr = [$province, $city];
        } else {
            $arr = [1, 'not found'];
        }

        return $arr;
    }

   /*利用搜狐获取ip和地区
    *<script type="text/javascript" src="http://pv.sohu.com/cityjson?ie=utf-8"></script>
    *<script>
    *   var result = returnCitySN;
    *    var city = returnCitySN.cname;
    *</script>
    */ 

}
