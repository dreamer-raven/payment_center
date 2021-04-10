<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/14
 * Time: 14:32
 */

namespace App\Service\Vp;


class PaymentService
{
    private $payment_domain;

    public function __construct($payment_domain)
    {
        $this->payment_domain = $payment_domain;
    }

    /**
     * 获得不同货币对应的汇率（存储表可能用到）
     * @return bool|mixed
     */
    public function getExchange()
    {
        $params = get_defined_vars();
        $uri = "/v1/service/coin/exchange";
        $response = $this->__request($uri, "get", $params);
        return $response;
    }

    /**
     * 获得不同货币对应的汇率（存储表可能用到）
     * @return bool|mixed
     */
    public function getOrderStatus($app_id, $app_order_id, $uid)
    {
        $params = get_defined_vars();
        $uri = "/v1/service/order/status";
        $response = $this->__request($uri, "get", $params);
        return $response;
    }

    /**
     * 生成vp待支付订单，返回订单号
     * @param $app_id
     * @param $app_order_id
     * @param $user_id
     * @param $currenct_type
     * @param $channel_id
     * @param $order_amount
     * @param $product_type
     * @param $product_id
     * @param $product_title
     * @param $product_desc
     * @return bool|mixed
     */
    public function createOrder($app_id, $app_order_id, $user_id, $currency_type, $channel_id, $order_amount, $product_type, $product_id, $product_title, $product_desc)
    {
        $params = get_defined_vars();
        $uri = "/v1/service/order";
        $response = $this->__request($uri, "post", $params);
        return $response;
    }

    /**
     * 结算订单
     * @param $settled_data
     * @return bool|mixed
     */
    public function settled($settled_data)
    {
        $params = get_defined_vars();
        $uri = "/v1/service/order/settled";
        $response = $this->__request($uri, "post", $params);
        return $response;
    }

    /**
     *赛果回滚订单重置
     * @param $product_type
     * @param $product_id
     * @return bool|mixed
     */
    public function rollback($app_id, $product_type, $product_id)
    {
        $params = get_defined_vars();
        $uri = "/v1/service/order/rollback";
        $response = $this->__request($uri, "post", $params);
        return $response;
    }

    /**
     * 取消vp待支付订单
     * @param $partner_order_sn
     * @return bool|mixed
     */
    public function cancelOrder($partner_order_sn)
    {
        $params = get_defined_vars();
        $uri = "/v1/service/order/cancel";
        $response = $this->__request($uri, "post", $params);
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
            return $response;
        } catch (\Exception $e) {
            return false;
        }

    }

}
