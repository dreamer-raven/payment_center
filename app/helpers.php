<?php

use App\Service\ResponseService;
use Illuminate\Support\Facades\Request as Request;
use Illuminate\Support\Facades\Redis as Redis;
use Illuminate\Support\Facades\DB as DB;
use Illuminate\Support\Facades\Validator;


function paginate($data, $page, $per_page = 30)
{
    $data = array_chunk($data, $per_page);
    return isset($data[$page - 1]) ? $data[$page - 1] : [];
}

//简化请求对象
function input($key = null, $default = null)
{
    /** @var \Illuminate\Http\Request $request */
    $request = Request::instance();
    return $key ? $request->input($key, $default) : $request;
}

//对象转数组
function obj2arr($obj)
{
    return json_decode(json_encode($obj), true);
}

//格式化查询条件
function format_where($arr)
{
    foreach ($arr as $k => &$v) {
        if (!is_array($v)) {
            $v = [$k, '=', $v];
        }
    }
    return array_values($arr);
}

//
function redis()
{
    /** @var \Illuminate\Redis\RedisManager $reids */
    $reids = Redis::connection();
    return $reids;
}

/*
 * 数据库
 * */
function db($name = null)
{
    return DB::connection($name);
}

function getConfig($config = '')
{
    return env($config);
}

function array_orderby()
{
    $args = func_get_args();
    $data = array_shift($args);
    foreach ($args as $n => $field) {
        if (is_string($field)) {
            $tmp = array();
            foreach ($data as $key => $row)
                $tmp[$key] = $row[$field];
            $args[$n] = $tmp;
        }
    }
    $args[] = &$data;
    call_user_func_array('array_multisort', $args);
    return array_pop($args);
}




/**
 * 获得客户端ip
 * @return mixed
 */
function getClientIp()
{
    $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : input()->getClientIp();
    return $ip;
}

function checkParam($param, $rules)
{
    $validator = Validator::make($param, $rules);
    if ($validator->fails()) {
        $msg = $validator->errors()->first();
        fail(ResponseService::PARAM_ERROR, $msg);
    }
}

function getNo()
{
    return (date('y') + date('m') + date('d')) . str_pad((time() - strtotime(date('Y-m-d'))), 5, 0, STR_PAD_LEFT) . substr(microtime(), 2, 6) . sprintf('%03d', rand(0, 999));
}

/**
 * 输出毫秒
 * @return float
 */
function msectime()
{
    list($msec, $sec) = explode(' ', microtime());
    $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectime;
}


function randomFloat($min = 0, $max = 1)
{
    $num = $min + mt_rand() / mt_getrandmax() * ($max - $min);
    return sprintf("%.2f", $num);  //控制小数后几位
}

/**
 * @param $value
 * @param string $cookie_expire_at
 * @param null $domain
 * @param string $name
 * @return \Symfony\Component\HttpFoundation\Cookie
 */
function createCookie($value, $cookie_expire_at = '', $domain = null, $name = 'wb_token')
{
//    $domain = env("TOP_LEVEL_DOMAINS");
    $cookie = new \Symfony\Component\HttpFoundation\Cookie($name, $value, $cookie_expire_at, "/", $domain);
    return $cookie;
}

function getTopHost($url)
{
    $url = strtolower($url);  //首先转成小写
    $hosts = parse_url($url);
    $host = $hosts['host'];
    //查看是几级域名
    $data = explode('.', $host);
    $n = count($data);
    //判断是否是双后缀
    $preg = '/[\w].+\.(com|net|org|gov|edu)\.cn$/';
    if (($n > 2) && preg_match($preg, $host)) {
        //双后缀取后3位
        $host = $data[$n - 3] . '.' . $data[$n - 2] . '.' . $data[$n - 1];
    } else {
        //非双后缀取后两位
        $host = $data[$n - 2] . '.' . $data[$n - 1];
    }
    return $host;
}


function replaceSpecialChar($strParam){
    $strParam = htmlspecialchars_decode($strParam);
    $regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
    return preg_replace($regex,"",$strParam);
}


function success($data = '', $extMsg = '', $statusCode = 200, $lang = 'zh', $emptyDataFormat = 'object', $headers = [])
{
    if (empty($data)) {
        if ($emptyDataFormat == 'object') {
            $data = (object)null;
        } else {
            $data = [];
        }
    }
    if ($extMsg == '') $extMsg = ResponseService::ERROR_MSG[$lang][ResponseService::OK];
    $jsonData = [
        'code' => ResponseService::OK,
        'data' => $data,
        'msg' => $extMsg
    ];
    returnResponse(json_encode($jsonData), $statusCode, $headers);
}

function fail($code, $msg = '', $data = [], $lang = 'zh')
{
    if (empty($msg)) {
        $msg = ResponseService::ERROR_MSG[$lang][$code];
    }
    $jsonData = [
        'code' => $code,
        'msg' => $msg
    ];
    $jsonData['data'] = empty($data) ? (object)null : $data;

    $statusCode = isset(ResponseService::ERROR_CODE[$code]) ? ResponseService::ERROR_CODE[$code] : 200;
    returnResponse(json_encode($jsonData), $statusCode);
}

function returnResponse($jsonData, $statusCode = 200, $headers = [])
{
    abort($statusCode, $jsonData, $headers);
}

/*
 * 处理图片地址拼接问题
 * */
function img_url($url, $domain = true, $type = 'aws')
{
    $pos = strpos($url, 'uploads');
    if ($pos > 0) $url = substr($url, $pos);
    if (strstr($url, 'http')) {
        $domain = false;
    }
    if ($type == 'esports') {
        return $domain ? (env('ESPORTS_STATIC_URL') . "/" . $url) : $url;
    } else {
        return $domain ? (env('STATIC_RESOURCE_URL') . "/" . $url) : $url;
    }
}
