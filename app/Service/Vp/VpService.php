<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2020/7/7
 * Time: 17:29
 */

namespace App\Service\Vp;


use Illuminate\Support\Facades\Log;

/**
 * vp相关请求service
 * Class VpService
 * @package App\Service\Vp
 */
class VpService
{
    const ERROR_USER_INFO_SYNCHRONIZE = 9900;


    const ERROR_MSG = [
        "zh" => [
            self::ERROR_USER_INFO_SYNCHRONIZE => "用户信息同步失败，请稍后再试"
        ],
        "en" => [
            self::ERROR_USER_INFO_SYNCHRONIZE => "something wrong with user info, please try again later."
        ],
    ];

    private $vp_domain;
    private $vp_api_key;
    private $vp_api_secret;
    private $vp_partner_id;

    public function __construct()
    {
        $this->__setAttribute();
    }

    /**
     * env变量初始化赋值
     */
    private function __setAttribute()
    {
        $this->vp_domain = env("VP_API_DOMAIN");
        $this->vp_api_key = env("VP_API_KEY");
        $this->vp_api_secret = env("VP_API_SECRET");
        $this->vp_partner_id = env("VP_PARTNER_ID");
    }

    /**
     * 静态方法获得vp平台的id
     * @return mixed
     */
    public static function getPlatformId()
    {
        return env("VP_PLATFORM_ID");
    }

    /**
     * 获得用户对应钱包余额
     * @param $user_id
     * @param $wallet_type
     * @return bool|int
     */
    public function getUserWallet($user_id, $wallet_type)
    {
        $user_info = $this->getUserInfo($user_id);
        if ($user_info === false) {
            return false;
        }
        return $user_info[$wallet_type] ?? 0;
    }

    public static function transDiamondCentToDiamond($diamond_cent)
    {
        $diamond = bcdiv($diamond_cent, 100, 2);
        return $diamond;
    }

    public static function transDiamondToCent($diamond)
    {
        return bcmul($diamond, 100, 0);
    }

    public static function getSettleAmount($coin_type, $amount)
    {
        switch ($coin_type) {
            case "gold":
                $settle_amount = $amount > 0 ? numberFormat($amount, 0) : 0;#这个输的传0
                break;
            case "diamond":
                $settle_amount = $amount > 0 ? VpService::transDiamondToCent($amount) : 0;#这个输的传0
                break;
            default:
                $settle_amount = $amount > 0 ? $amount : 0;#这个输的传0
        }
        return $settle_amount;
    }

    /**
     * 获得vp用户信息
     * @param $user_id
     * @return bool
     */
    public function getUserInfo($user_id)
    {
        $uri = "/partner/user/info";
        $response = $this->__request("get", $uri, ["user_id" => $user_id]);
        if ($response === false) {
            return false;
        }
        if (isset($response["data"]["diamond"])) {
            $response["data"]["diamond"] = self::transDiamondCentToDiamond($response["data"]["diamond"]);
        }
        return $response["data"];
    }

    /**
     * POST 服务端调用此接口生成支付订单, 用于下一步 JS 发起支付
     * 合作方订单号 @param $partner_order_sn
     * 用户id @param $user_id
     * 标题 @param $title
     * 支付方式 @param $pay_type
     * 订单支付金额, 单位 P豆或者钻石的分, int, (1钻=1美元, 这里钻石单位等同与美分) @param $amount
     * 商品类型, 如游戏玩法代号 @param $product_type
     * 合作方商品 ID, string, 如某一局游戏的编号 @param $product_id
     * @return
     */
    public function createPartnerOrder($game, $partner_order_sn, $user_id, $title, $pay_type, $amount, $product_type, $product_id)
    {
        $data = get_defined_vars();
        $uri = "/partner/order";
        $method = "post";
        $response = $this->__request($method, $uri, $data);
//        if ($response === false) {
//            Log::error("vp 生成合作订单api失败", ["method" => $method, "uri" => $uri, "data" => $data]);
//            return false;
//        }
//        if (!isset($response["data"]["partner_order_sn"])) {
//            Log::error("vp 生成合作订单错误：未定义partner_order_sn，响应信息：", ["method" => $method, "uri" => $uri, "data" => $data, "response"=>$response]);
//            return false;
//        }
//        if ($response["data"]["status"] != "pending") {
//            Log::error("vp 生成合作订单错误：订单状态错误，响应信息：", ["method" => $method, "uri" => $uri, "data" => $data, "response"=>$response]);
//            return false;
//        }
        return $response;
    }

    /**
     * POST 结算接口, 每局批量结算
     * @param $settle_data
     * @return bool
     */
    public function settlePartnerGame($settle_data)
    {
        $method = "post";
        $uri = "/partner/game/settle";
        $response = $this->__request($method, $uri, $settle_data);
        if ($response === false) {
            Log::error("vp 结算接口api失败", ["method" => $method, "uri" => $uri, "data" => $settle_data]);
            return false;
        }
        return $response;
//        if (isset($response["data"]["settle_result"]) && $response["data"]["settle_result"] == "accepted") {
//            return $response;
//        } else {
//            Log::error("vp 结算接口api返回值错误：" . json_encode($response));
//            return false;
//        }
    }

    /**
     * POST 游戏结算回滚 r瑞星
     * 商品类型, 如游戏玩法代号  @param $product_type
     * 合作方商品 ID, string, 如某一局游戏的编号  @param $product_id
     * @return bool
     */
    public function rollback($product_type, $product_id)
    {
        $data = get_defined_vars();
        $method = "post";
        $uri = "/partner/game/settle/rollback";
        $response = $this->__request($method, $uri, $data);
        if ($response === false) {
            Log::error("vp 游戏结算回滚api失败", ["method" => $method, "uri" => $uri, "data" => $data]);
            return false;
        }
        return $response;
//        if (isset($response["data"]["rollback_result"]) && $response["data"]["rollback_result"] == "accepted") {
//            return $response;
//        } else {
//            Log::error("vp 结算接口api返回值错误：" . json_encode($response));
//            return false;
//        }
    }

    /**
     * POST 游戏结算回滚 r瑞星
     * 商品类型, 如游戏玩法代号
     * @param $order_no
     * @return bool
     */
    public function cancelOrder($order_no)
    {
        $data = [
            "partner_order_sn" => [$order_no]
        ];
        $method = "post";
        $uri = "/partner/order/close";
        $response = $this->__request($method, $uri, $data);
        if ($response === false) {
            Log::error("vp 游戏结算回滚api失败", ["method" => $method, "uri" => $uri, "data" => $data]);
            return false;
        }
        return $response;
//        if (isset($response["data"]) && ($response["data"] == true)) {
//            return true;
//        } else {
//            Log::warning("vp 关闭订单接口api返回值错误：" . json_encode($response));
//            return false;
//        }
    }

    /**
     * GET  用于确认订单是否支付成功
     * @param $partner_order_sn
     * @param $user_id
     * @return array|bool
     */
    public function queryPartnerOrderState($partner_order_sn, $user_id)
    {
        $query_condition = get_defined_vars();
        $method = "get";
        $uri = "/partner/order/status";
        try {
            $response = $this->__request($method, $uri, $query_condition);
            $response = json_decode($response, true);
            if ($response === false) {
                Log::error("vp 用于确认订单是否支付成功api失败", ["method" => $method, "uri" => $uri, "data" => $query_condition]);
                return false;
            }
            if (isset($response["code"]) && $response["code"] == 3000404) {
                return 404;
            }
            if (isset($response["data"]["partner_order_sn"]) && isset($response["data"]["status"])) {
                return $response;
            } else {
                Log::error("vp 用于确认订单是否支付成功api返回值错误：" . json_encode($response));
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

    }

    /**
     * 请求方法
     * @param $method
     * @param $uri
     * @param $param
     * @return bool|int|mixed|\Requests_Response|string
     */
    private function __request($method, $uri, $param)
    {
        $domain = $this->vp_domain;
        $url = $domain . $uri;
        $sign = $this->getSign($method, $uri, $param);
        $header = [
            "Accept-ApiKey" => $this->vp_api_key,
            "Accept-ApiSign" => $sign,
            "Accept-ApiTime" => time(),
            "Accept-PartnerId" => $this->vp_partner_id,
        ];
        $options['timeout'] = 10;
        try {
            switch ($method) {
                case "get":
                    $url .= "?" . http_build_query($param);
                    $response = \Requests::get($url, $header, $options);
                    break;
                case "post":
                    $param = json_encode($param, JSON_UNESCAPED_UNICODE);
                    $header["Content-Type"] = "application/json; charset=utf-8";
                    $response = \Requests::post($url, $header, $param, $options);
                    break;
                default:
                    $response = "default method";
            }
            $res = $response->body;
            Log::info("vp_api_request:$url", ["url" => $url, "method" => $method, "param" => $param, "response" => $res, "header" => $header]);
            return $res;
        } catch (\Exception $e) {
            Log::warning("warning :vp_api_request_failed", ["url" => $url, "method" => $method, "param" => $param, "header" => $header, "exception" => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 获得加密sign
     * @param $method
     * @param $uri
     * @param $params
     * @return string
     */
    private function getSign($method, $uri, $params)
    {
        $uri = "user-pay" . $uri;
        $signString = $this->getSignString($method, $params);
        $api_secret = $this->vp_api_secret;
        return md5($signString . $uri . $api_secret . time());
    }

    /**
     * 获得用于加密sign的请求参数的字符串
     * @param $method
     * @param $params
     * @return string
     */
    private function getSignString($method, $params)
    {
        $string = "";
        if ($method == "get") {
            ksort($params);
            $string = "";
            foreach ($params as $p) {
                $string .= $p;
            }
//            $string = http_build_query($params);
        }
        if ($method == "post") {
            $params = json_encode($params);
            $string = $params;
        }
        return $string;
    }

}
