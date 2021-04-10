<?php

namespace App\Http\Controllers\vpgame\api;


use App\Http\Controllers\BaseController;
use App\Models\Vp\GameConfigModel;
use App\Models\Vp\OrderModel;
use App\Service\CurlService;
use Illuminate\Support\Facades\Log;

/**
 * vp回调接口控制器
 * Class CallbackController
 * @package App\Http\Controllers\vpgame
 */
class QueryController extends BaseController
{
    public function queryOrderCanPay()
    {
        Log::info("vp find order trigger", ["request_data" => $this->request->all()]);
        $partner_order_sn = $this->request->input("partner_order_sn");
        $game = $this->request->input("game");
        $order = OrderModel::query()->where("channel_order_id", $partner_order_sn)->first();
        if (!$order) {
            #配置不存在，直接拒单
            $order_pay_status = 0;
            $this->success(["order_pay_status" => $order_pay_status], "查询不到订单");
        }
        #获取小游戏 检查url 回调url
        $config = GameConfigModel::query()->where("app_id", $order->app_id)->first();
        if (!$config) {
            #配置不存在，直接拒单
            Log::error("小游戏{$order->app_id}配置不存在，将拒绝订单{$partner_order_sn}", ["request_data"=>$this->request->all()]);
            $order_pay_status = 0;
            $this->success(["order_pay_status" => $order_pay_status], "配置不存在");
        }

        $res = null;
        try {
            $res = CurlService::curl($config->check_pay_status_url, ["order_id" => $order->order_id, "app_order_id" => $order->app_order_id], 1);
            #查询订单可支付状态，没有请求成功的话直接把这一单拒掉
            $res = json_decode($res, true);
        } catch (\Exception $e) {
            #过程出错，直接拒单
            Log::error("支付成功后询问小游戏情况出错", ["payment_order_info" => $order->order_id, "channel_order_info" => $order->app_order_id, "error_msg" => $e->getMessage()]);
            $this->success(["order_pay_status" => 0], "询问小游戏出错");
        }
        if (!$res) {
            Log::info("请求失败", ["request_url"=>$config->check_pay_status_url, "data"=>["order_id" => $order->order_id, "app_order_id" => $order->app_order_id]]);
            $this->success(["order_pay_status" => 0], "请求失败");
        }
        #code不等于0，拒单
        if ($res["code"] != 0 || !isset($res["data"]["order_pay_status"])) {
            Log::info("code不等于0，拒单", ["request_url"=>$config->check_pay_status_url, "data"=>["order_id" => $order->order_id, "app_order_id" => $order->app_order_id], "res" => $res]);
            $this->success(["order_pay_status" => 0], "小游戏拒单");
        }
        if ($res["data"]["order_pay_status"] != 1) {
            Log::info("order_pay_status != 1，拒单", ["request_url"=>$config->check_pay_status_url, "data"=>["order_id" => $order->order_id, "app_order_id" => $order->app_order_id], "res" => $res]);
            $this->success(["order_pay_status" => 0], "小游戏拒单2");
        }

        $this->success(["order_pay_status" => 1]);
    }


}
