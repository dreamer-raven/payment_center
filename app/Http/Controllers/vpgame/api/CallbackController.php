<?php

namespace App\Http\Controllers\vpgame\api;


use App\Http\Controllers\BaseController;
use App\Jobs\Vp\CallbackSendGameJob;
use App\Models\Vp\GameConfigModel;
use App\Models\Vp\OrderModel;
use App\Service\CurlService;
use App\Utils\Checker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * vp回调接口控制器
 * Class CallbackController
 * @package App\Http\Controllers\vpgame
 */
class CallbackController extends BaseController
{
    private $callback_order_id;#回调的订单id
    private $callback_order_info;#回调的订单信息
    private $app_id;#回调的游戏id信息
    private $app_secret_key;#游戏id 的回调通知url
    private $app_callback_url;#游戏id 的回调通知url
    private $app_check_receive_url;#游戏id 的检查是否可收单的url
    private $payment_order_info;#支付中心内对应的本次回调的订单

    /**
     * 处理订单回调
     */
    public function orderCallback()
    {
        $request_json = file_get_contents("php://input");
        Log::info("收到订单支付回调", ["request_data" => $request_json, "headers" => $this->request->header()]);
        $request_json = json_decode($request_json, true);
        if (empty($request_json)) {
            $this->__response("order_refuse");
        }
        $this->checkParam($request_json, [
            "status" => "required|in:pending,paid,closed,failed,canceled"
        ]);

        #验证签名
        $res = $this->__checkSign($request_json["sign"], $request_json["amount"], $request_json["app_id"], $request_json["paid_at"], $request_json["partner_order_sn"], $request_json["pay_type"], $request_json["status"]);
        if (!$res) {
            Log::error("order_refuse sign_error", [$request_json]);
            $this->__response("order_refuse");
        }
        #拿到了app小游戏属性，进行全局设置
        $this->__setCallbackAttribute($request_json);


        $order = OrderModel::query()->where("channel_order_id", $this->callback_order_id)->first();
        if (!$order) {
            #订单不存在
            Log::error("order_refuse order_not_exist", ["order_id" => $this->callback_order_id]);
            $this->__response("order_not_exist");
        }
        if ($order->order_status != OrderModel::ORDER_STATUS_PENDING) {
            if($order->order_status == OrderModel::ORDER_STATUS_PAID){
                $this->__response("success");
            }
            if($order->order_status == OrderModel::ORDER_STATUS_CANCELED){
                $this->__response("order_refuse");
            }
        }
        $this->payment_order_info = $order;

        #获取小游戏 检查url 回调url
        $config = GameConfigModel::query()->where("app_id", $this->payment_order_info->app_id)->first();
        if (!$config) {
            #配置不存在，直接拒单
            Log::error("小游戏{$this->payment_order_info->app_id}配置不存在，将拒绝订单{$this->payment_order_info->channel_order_id}", ["callback_data"=>$request_json]);
            $this->__orderRefuse($this->payment_order_info->channel_order_id);
            $this->__response("order_refuse");
        }
        $this->__setAppAttribute($this->payment_order_info->app_id, $config->callback_url, $config->check_receive_url, $config->secret_key);

        if ($this->callback_order_info["status"] == "paid") {
            #支付成功，询问小游戏是否收这一单
            $res = null;
            try {
                $res = CurlService::curl($this->app_check_receive_url, ["order_id" => $this->payment_order_info->order_id, "app_order_id" => $this->payment_order_info->app_order_id, "paid_at" => $request_json["paid_at"]], 1);
                #查询订单可支付状态，没有请求成功的话直接把这一单拒掉
                $res = json_decode($res, true);
            } catch (\Exception $e) {
                #过程出错，直接拒单
                Log::error("callback-支付成功后询问小游戏支付情况出错", ["payment_order_info" => $this->payment_order_info, "channel_order_info" => $this->callback_order_info, "error_msg" => $e->getMessage()]);
                $this->__orderRefuse($this->payment_order_info->channel_order_id);
                $this->__response("order_refuse");
            }
            if (!$res) {
                Log::info("callback-请求失败", ["request_url"=>$this->app_check_receive_url, "data"=>["order_id" => $this->payment_order_info->order_id, "app_order_id" => $this->payment_order_info->app_order_id, "paid_at" => $request_json["paid_at"]]]);
                $this->__orderRefuse($this->payment_order_info->channel_order_id);
                $this->__response("order_refuse");
            }
            #code不等于0，拒单
            if ($res["code"] != 0 || !isset($res["data"]["order_can_receive"])) {
                Log::info("callback-code不等于0，拒单", ["request_url"=>$this->app_check_receive_url, "data"=>["order_id" => $this->payment_order_info->order_id, "app_order_id" => $this->payment_order_info->app_order_id, "paid_at" => $request_json["paid_at"]], "res" => $res]);
                $this->__orderRefuse($this->payment_order_info->channel_order_id);
                $this->__response("order_refuse");
            }
            if ($res["data"]["order_can_receive"] != 1) {
                Log::info("callback-order_can_accept != 1，拒单", ["request_url"=>$this->app_check_receive_url, "data"=>["order_id" => $this->payment_order_info->order_id, "app_order_id" => $this->payment_order_info->app_order_id, "paid_at" => $request_json["paid_at"]], "res" => $res]);
                $this->__orderRefuse($this->payment_order_info->channel_order_id);
                $this->__response("order_refuse");
            }
        }

        $mark_res = $this->__markOrderStatus($this->callback_order_id, $this->callback_order_info["status"], $this->callback_order_info);
        $this->__response($mark_res);
    }

    /**
     * 通过各项检查后，对订单进行处理
     * @param $channel_order_id
     * @param $status
     * @param $callback_data
     * @return string
     */
    public function __markOrderStatus($channel_order_id, $status, $callback_data)
    {
        $result = null;
        try {
            DB::beginTransaction();
            #锁单
            $order = OrderModel::query()->where("channel_order_id", $channel_order_id)->lockForUpdate()->first();
            if (!$this->app_callback_url) {
                #小游戏回调地址不存在
                Log::error("callback error:游戏{$order->app_id}未配置回调地址，将拒绝收单{$channel_order_id}", ["app_id" => $this->app_id, "channel_order_id" => $this->callback_order_id, "callback_data" => $this->callback_order_info]);
                DB::rollBack();
                return "order_refuse";
            }
            if ($order->order_status != OrderModel::ORDER_STATUS_PENDING) {
                #不是pending，订单已被处理，返回处理成功
                DB::rollBack();
                return "success";
            }
            #转换得到本地订单状态
            $order_status = OrderModel::transOrderStatus($status);
            OrderModel::query()->where("channel_order_id", $channel_order_id)->update(["order_status" => $order_status]);
            DB::commit();
            #准备回调数据
            $app_callback_data = [
                "order_id" => $order->order_id,
                "app_order_id" => $order->app_order_id,
                "currency_type" => $order->currency_type,
                "order_amount" => $order->order_amount,
                "order_status" => $order_status,
                "paid_at" => $callback_data["paid_at"],
            ];
            $sign = Checker::getHmacSign($app_callback_data, $this->app_secret_key);
            $app_callback_data['sign'] = $sign;
            #进行异步回调
            $job = new CallbackSendGameJob($order->app_id, $this->app_callback_url, $app_callback_data);
            dispatch($job);
            return "success";
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("支付中心最终处理订单异常:", ["channel_order_info" => $this->callback_order_info, "error_msg" => $e->getMessage()]);
            return "order_refuse";
        }
    }

    /**
     * 拒绝订单
     * @param $channel_order_id
     * @return string
     */
    public function __orderRefuse($channel_order_id)
    {
        try {
            DB::beginTransaction();
            #数据库逻辑部分
            #最终回调的data
            $order = OrderModel::query()->where("channel_order_id", $channel_order_id)->lockForUpdate()->first();
            if ($order->status != OrderModel::ORDER_STATUS_PENDING) {
                #订单已被处理，返回处理成功
                DB::rollBack();
                return "order_refuse";
            }
            OrderModel::query()->where("channel_order_id", $channel_order_id)->update(["order_status" => OrderModel::ORDER_STATUS_CANCELED]);
            DB::commit();

            if ($this->app_callback_url) {
                #准备回调数据
                $app_callback_data = [
                    "order_id" => $order->order_id,
                    "app_order_id" => $order->app_order_id,
                    "currency_type" => $order->currency_type,
                    "order_amount" => $order->order_amount,
                    "order_status" => OrderModel::ORDER_STATUS_CANCELED,
                    "paid_at" => 0,
                    "sign" => "",
                ];
                $sign = Checker::getHmacSign($app_callback_data, $this->app_secret_key);
                $app_callback_data['sign'] = $sign;
                #进行异步回调
                $job = new CallbackSendGameJob($order->app_id, $this->app_callback_url, $app_callback_data);
                dispatch($job);
            } else {
                #小游戏回调地址不存在
                Log::error("callback error:游戏{$order->app_id}未配置回调地址，通知将失败{$channel_order_id}");
            }
            return "order_refuse";
        } catch (\Exception $e) {
            #数据库逻辑出错
            Log::error("支付中i性能拒单后更新本地记录失败", ["channel_order_info" => $this->callback_order_info, "error_msg" => $e->getMessage()]);
            DB::rollBack();
            return "fail";
        }
    }


    /**
     * 返回与vp沟通定好的格式数据
     * @param $result
     */
    public function __response($result)
    {
        $jsonData = ["data" => ["result" => $result]];
        $jsonData = json_encode($jsonData);
        returnResponse($jsonData);
    }

    /**
     * 设置订单对应的小游戏属性
     * @param $app_id
     * @param $app_callback_url
     * @param $app_check_receive_url
     */
    private function __setAppAttribute($app_id, $app_callback_url, $app_check_receive_url, $secret_key)
    {
        $this->app_id = $app_id;
        $this->app_callback_url = $app_callback_url;
        $this->app_check_receive_url = $app_check_receive_url;
        $this->app_secret_key = $secret_key;
    }

    /**
     * 设置回调信息的全局变量
     * @param $callback_info
     */
    private function __setCallbackAttribute($callback_info)
    {
        $this->callback_order_id = $callback_info["partner_order_sn"];
        $this->callback_order_info = $callback_info;
    }

    /**
     * 验证签名
     * @param $sign
     * @param $amount
     * @param $app_id
     * @param $paid_at
     * @param $partner_order_sn
     * @param $pay_type
     * @param $status
     * @return bool
     */
    private function __checkSign($sign, $amount, $app_id, $paid_at, $partner_order_sn, $pay_type, $status)
    {
        $sign_str = "amount={$amount}&app_id={$app_id}&paid_at={$paid_at}&partner_order_sn={$partner_order_sn}&pay_type={$pay_type}&status={$status}" . env("VP_PLATFORM_SECRET");
        $local_sign = md5($sign_str);
        Log::info("订单验证签名", ["partner_order_sn" => $partner_order_sn, "result" => $sign == $local_sign]);
        return $sign == $local_sign;
    }

}
