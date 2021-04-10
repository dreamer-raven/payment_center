<?php

namespace App\Service;
use Illuminate\Support\Facades\Validator;

class ResponseService
{
    const OK = 0;

    //错误相关
    const PARAM_ERROR = 4100;
    const ERROR_NO_PERMISSION = 4106;
    const SIGN_ERROR = 4447;
    const BET_ERROR = 4501;
    const BET_NUM_ERROR = 4502;

    const TIME_EXPIRED = 4504;
    const SIGN_EXPIRED = 4505;
    
    const ADD_ERROR = 4601;
    const LOCAL_ORDER_NOT_EXIST = 6006;
    const ORDER_ID_ALREADY_EXIST = 6007;
    const ORDER_ID_NOT_EXIST = 6008;

    //服务器内部错误
    const SERVER_ERROR = 9100;

    const ERROR_CODE = [
        self::BET_ERROR => 200,
        self::BET_NUM_ERROR => 200,
    ];

    const ERROR_USER_INFO_SYNCHRONIZE = 9900;


    const ERROR_MSG = [
        'en' => [
            self::OK => 'Success',
            self::PARAM_ERROR => 'Wrong param',
            self::ERROR_NO_PERMISSION => 'No permission to do this',
            self::SERVER_ERROR => 'server error',
            self::SIGN_ERROR => 'wrong sign',
            self::TIME_EXPIRED => 'Request timed out',
            self::SIGN_EXPIRED => 'sign expired',
            self::ADD_ERROR => 'add error',
            self::LOCAL_ORDER_NOT_EXIST => 'local order not exist',
            self::ERROR_USER_INFO_SYNCHRONIZE => "something wrong with user info, please try again later.",
            self::ORDER_ID_ALREADY_EXIST => "order id already exist",
            self::ORDER_ID_NOT_EXIST => "order id not exist",
        ],
        'zh' => [
            self::OK => '操作成功',
            self::PARAM_ERROR => '字段错误',
            self::ERROR_NO_PERMISSION => '用户无权限',
            self::SERVER_ERROR => '服务器错误，请稍后再试',
            self::SIGN_ERROR => '签名错误',
            self::TIME_EXPIRED => '请求超时',
            self::SIGN_EXPIRED => '签名过期',
            self::ADD_ERROR => '添加失败',
            self::LOCAL_ORDER_NOT_EXIST => '本地订单不存在，无法取消',
            self::ERROR_USER_INFO_SYNCHRONIZE => "用户信息同步失败，请稍后再试",
            self::ORDER_ID_ALREADY_EXIST => "order id 已存在",
            self::ORDER_ID_NOT_EXIST => "order id 不存在",
        ],
    ];

    const SUCCESS_MSG = [
        'en' => [

        ],
        'zh' => [

        ],
    ];
    
    
    private static function response($jsonData, $statusCode = 200)
    {
        $response = response($jsonData, $statusCode)
        ->header('Access-Control-Allow-Credentials', "true")
        ->header('Content-Type', 'application/json; charset=utf-8')//Content-Type:  application/json; charset=utf-8
        ->header("Access-Control-Allow-Methods", "GET,HEAD,PUT,POST,DELETE,PATCH,OPTIONS");
        
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'null';
        $response = $response->header('Access-Control-Allow-Origin', $origin);
        $response = $response->header('Access-Control-Allow-Headers', 'x-requested-with,content-type');
        $response->send();
        exit();
    }
    
    /**
     * 返回正确数据
     * @param string $data
     * @param string $extMsg
     * @param int $statuCode
     */
    public static function success($data = '', $extMsg = '', $statuCode = 200, $lang = 'zh')
    {
        if (empty($data)) $data = (object)null;
        if ($extMsg == '') $extMsg = self::ERROR_MSG[$lang][self::OK];//暂时只一种语言
        $jsonData = [
            'code' => self::OK,
            'data' => $data,
            'msg' => $extMsg
        ];
        self::response($jsonData, $statuCode);
    }
    
    /**
     * 检查参数
     * @param $param
     * @param $rules
     */
    public static function checkParam($param, $rules)
    {
        $validator = Validator::make($param, $rules);
        if ($validator->fails()) {
            $msg = $validator->errors()->first();
            self::fail(self::PARAM_ERROR, $msg);
        }
    }
    
    /**
     * 返回错误码
     * @param $code
     * @param string $msg
     */
    public static function fail($code, $msg = '', $lang = 'zh')
    {
        if (empty($msg)) {
            $msg = self::ERROR_MSG[$lang][$code];
        }
        $jsonData = [
            'code' => $code,
            'msg' => $msg
        ];
        isset(self::ERROR_CODE[$code]) ? $statusCode = self::ERROR_CODE[$code] : $statusCode = 200;
        self::response($jsonData, $statusCode);
    }

}