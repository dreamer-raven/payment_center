<?php

namespace App\Http\Controllers\vpgame\web;


use App\Http\Controllers\BaseController;
use App\Jobs\Vp\CallbackSendGameJob;
use App\Models\Vp\GameConfigModel;
use App\Models\Vp\OrderModel;
use App\Service\ResponseService;
use App\Service\Vp\UCenterService;
use App\Service\Vp\VpService;
use App\Utils\Checker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends BaseController
{
    private $vp_service;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->vp_service = new VpService();
    }


    /**
     * 取消订单
     * @return void
     */
    public function closeOrder()
    {
        $this->checkParam($this->request->all(), [
            "order_id" => "required",
        ]);
        $order_id = $this->request->input("order_id");
//        $token = $this->request->header("wb_token");
        $Authorization = $this->request->header("Authorization");
        if(empty($Authorization)){
            #header里没有Authorization
            $this->fail(ResponseService::ERROR_USER_INFO_SYNCHRONIZE);
        }
        parse_str($Authorization, $aParam);
        if(!isset($aParam["wb_token"])){
            #Authorization里没有wb_token参数
            $this->fail(ResponseService::ERROR_USER_INFO_SYNCHRONIZE);
        }
        $token = $aParam["wb_token"];
        $ucenter_service = new UCenterService(env("UCENTER_DOMAIN"));
        $user = $ucenter_service->getUserByToken($token);
        Log::info("close_order_user_sync", ["token" => $token, "user"=>$user]);
        if ($user === false || !isset($user["data"]["id"])) {
            $this->fail(ResponseService::ERROR_USER_INFO_SYNCHRONIZE);
        }
        $user_id = $user["data"]["id"];

        if(!$local_record = OrderModel::query()->where([["order_id", "=", $order_id], ["user_id", "=", $user_id]])->first()){
            $this->fail(ResponseService::LOCAL_ORDER_NOT_EXIST);
        }
        $vp_service = new VpService();
        $query_order = $vp_service->cancelOrder($order_id);
        if ($query_order === false) {
            $this->fail(ResponseService::SERVER_ERROR);
        }

        $decode_response = json_decode($query_order, true);
        if (isset($decode_response["data"]) && ($decode_response["data"] == true)) {
            $config = GameConfigModel::query()->where("app_id", $local_record->app_id)->first();
            $app_callback_data = [
                "order_id" => $local_record->order_id,
                "app_order_id" => $local_record->app_order_id,
                "currency_type" => $local_record->currency_type,
                "order_amount" => $local_record->order_amount,
                "order_status" => OrderModel::ORDER_STATUS_CANCELED,
                "paid_at" => time(),
            ];
            $sign = Checker::getHmacSign($app_callback_data, $config->secret_key);
            $app_callback_data['sign'] = $sign;
            if ($config->callback_url) {
                $job = new CallbackSendGameJob($local_record->app_id, $config->callback_url, $app_callback_data);
                dispatch($job);
            }
            $this->success();
        } else {
            $this->fail($decode_response["code"], $decode_response["message"]);
        }
        $this->success();
    }


}
