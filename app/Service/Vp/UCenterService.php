<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/14
 * Time: 14:32
 */

namespace App\Service\Vp;


use Illuminate\Support\Facades\Log;

class UCenterService
{
    private $payment_domain;

    public function __construct($payment_domain)
    {
        $this->payment_domain = $payment_domain;
    }

    /**
     * 根据uid获取用户基本信息
     * @param $uid
     * @return bool|mixed
     */
    public function getUserByUid($uid)
    {
        $params = get_defined_vars();
        $uri = "/v1/service/user/info/uid";
        $response = $this->__request($uri, "get", $params);
        if ($response === false) {
            return false;
        }
        return $response["data"];
    }

    public function getUserToken($param)
    {
        $params = get_defined_vars();
        $uri = "/v1/user/token";
        $response = $this->__request($uri, "get", $params);
        if ($response === false) {
            return false;
        }
        return $response;
    }

    public function getUserByToken($token)
    {
        $params = get_defined_vars();
        $uri = "/v1/service/user/info/token";
        $response = $this->__request($uri, "get", $params);
        return $response;
    }

    public function getAssetByUid($uid)
    {
        $params = get_defined_vars();
        $uri = "/v1/service/user/assets/uid";
        $response = $this->__request($uri, "get", $params);
        return $response;
    }


    /**
     * @param $uri
     * @param $method
     * @param bool $params 请求参数
     * @param int $https https协议
     * @return bool|mixed
     */
    private function __request($uri, $method, $params = false, $https = 0)
    {
        $ispost = 0;
        $url = $this->payment_domain . $uri;
        if ($method == "post") $ispost = 1;
        try {
            $httpInfo = array();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.118 Safari/537.36');
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if ($https) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // 对认证证书来源的检查
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // 从证书中检查SSL加密算法是否存在
            }
            if ($ispost) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                curl_setopt($ch, CURLOPT_URL, $url);
            } else {
                if ($params) {
                    if (is_array($params)) {
                        $params = http_build_query($params);
                    }
                    curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
                } else {
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
            }

            $response = curl_exec($ch);

            if ($response === FALSE) {
                //echo "cURL Error: " . curl_error($ch);
                return false;
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
            curl_close($ch);
            Log::info("ucenter_curl", ["url" => $url, "param" => $params, "is_post"=> $ispost, "res"=>$response]);
            $response = json_decode($response, true);
            return $response;
        } catch (\Exception $e) {
            Log::error("Payment 请求失败： ucenter请求失败", ["request_url" => $url, "method" => $method, "param" => $params, "error" => $e->getMessage()]);
            return false;
        }

    }

}
