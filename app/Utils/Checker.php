<?php

namespace App\Utils;


class Checker
{
    /**
     * 校验HMAC签名
     * @param $params
     * @param $secret_key
     * @return bool
     */
    public static function checkHmacSign($params, $sign, $secret_key)
    {
        ksort($params);
        foreach ($params as $key => $value) {
            $tmp[] = $key . '=' . $value;
        }
        $string = implode('&', $tmp);
        $right_sign = base64_encode(hash_hmac('sha256', $string, $secret_key, true));
        $sign = str_replace([' '], ['+'], $sign);
        return $sign === $right_sign;
    }
    
    /**
     * 获取HMAC签名
     * @param unknown $params
     * @param unknown $secret_key
     * @return string
     */
    public static function getHmacSign($params, $secret_key)
    {
        ksort($params);
        foreach ($params as $key => $value) {
            $tmp[] = $key . '=' . $value;
        }
        $string = implode('&', $tmp);
        $sign = base64_encode(hash_hmac('sha256', $string, $secret_key, true));
        return $sign;
    }

}