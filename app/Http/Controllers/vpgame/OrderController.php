<?php

namespace App\Http\Controllers\vpgame;


use App\Http\Controllers\BaseController;
use App\Jobs\Vp\CallbackSendGameJob;
use App\Jobs\Vp\ModifyOrderJob;
use App\Models\Vp\GameConfigModel;
use App\Models\Vp\OrderModel;
use App\Models\Vp\RollbackLogModel;
use App\Service\ResponseService;
use App\Service\Vp\UCenterService;
use App\Service\Vp\VpService;
use App\Utils\Checker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 支付中心内部提供的订单相关接口
 * Class OrderController
 * @package App\Http\Controllers\vpgame
 */
class OrderController extends BaseController
{
    private $vp_service;
    private $ucenter_service;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->vp_service = new VpService();
        $this->ucenter_service = new UCenterService(env("UCENTER_DOMAIN"));
    }

    /**
     * 创建订单
     */
    public function createOrder()
    {
        $this->checkParam($this->request->all(), [
            "app_id" => "required|in:rune,portal,tower,flip,midas,football",
            "app_order_id" => "required",
            "user_id" => "required",
            "currency_type" => "required|in:gold,diamond",
            "channel_id" => "required",
            "order_amount" => "required",
            "product_type" => "required",
            "product_id" => "required",
            "product_title" => "required",
//            "product_desc" => "required",
        ]);
        $app_id = $this->request->input("app_id");
        $app_order_id = $this->request->input("app_order_id");
        $user_id = $this->request->input("user_id");
        $currency_type = $this->request->input("currency_type");
        $channel_id = $this->request->input("channel_id");
        $order_amount = $this->request->input("order_amount");
        $product_type = $this->request->input("product_type");
        $product_id = $this->request->input("product_id");
        $product_title = $this->request->input("product_title");
        $product_desc = $this->request->input("product_desc", "");
        $order_info = get_defined_vars();

        if (OrderModel::query()->where("app_id", $app_id)->where("app_order_id", $app_order_id)->exists()) {
            $this->fail(ResponseService::ORDER_ID_ALREADY_EXIST);
        }

        $user = $this->ucenter_service->getUserByUid($user_id);
        if ($user === false || !isset($user[$user_id]["platform_uid"])) {
            $this->fail(ResponseService::ERROR_USER_INFO_SYNCHRONIZE);
        }
        $vp_uid = $user[$user_id]["platform_uid"];
        $partner_order_sn = getNo();
        if ($currency_type == "diamond") $order_amount = VpService::transDiamondToCent($order_amount);
        $response = $this->vp_service->createPartnerOrder($app_id, $partner_order_sn, $vp_uid, $product_title, $currency_type, $order_amount, $product_type, $product_id);
        if ($response === false) {
            $this->fail(ResponseService::SERVER_ERROR, "远端创建支付订单失败，请稍后再试");
        }

        $decode_response = json_decode($response, true);
        if (isset($decode_response["data"]["partner_order_sn"]) && $decode_response["data"]["status"] == "pending") {
            $order_info["status"] = 0;
            $order_info["order_id"] = $partner_order_sn;
            $order_info["channel_order_id"] = $partner_order_sn;
            $order_info["channel_uid"] = $vp_uid;
            OrderModel::query()->create($order_info);
//            $job = new ModifyOrderJob("create", $order_info);
//            dispatch($job);
            $this->success(["channel_order_id" => $decode_response["data"]["partner_order_sn"]]);
        } else {
            if(isset($decode_response["code"]) && $decode_response["message"]){
                $this->fail($decode_response["code"], $decode_response["message"]);
            }else{
                $this->fail(ResponseService::SERVER_ERROR);
            }

        }

    }


    /**
     * 结算订单
     */
    public function settleOrder()
    {
        $this->checkParam($this->request->all(), [
            "settled_data" => "required|json",
        ]);
        $settled_data = $this->request->input("settled_data");
        $settled_data = json_decode($settled_data, true);

        if(!is_array($settled_data)){
            $this->fail(ResponseService::PARAM_ERROR, "settled data invalid");
        }

        $this->checkParam($settled_data, [
            "app_id" => "required",
            "product_id" => "required",
            "product_type" => "required",
            "orders" => "required",
        ]);
        $app_id = $settled_data["app_id"];
        $product_id = $settled_data["product_id"];
        $product_type = $settled_data["product_type"];
        Log::info("game_settle_trigger", ["app_id" => $app_id, "data" => $settled_data]);
        $order_ids = array_column($settled_data["orders"], "order_id");
        $settled_orders = $settled_data["orders"];
        $settled_orders = array_combine($order_ids, $settled_orders);

        $payment_orders = OrderModel::query()->where([
            "app_id" => $app_id,
        ])->whereIn("app_order_id", $order_ids)->get();;

        $settle_details = [];
        foreach ($payment_orders as $p_o) {
            $app_order = $settled_orders[$p_o["app_order_id"]];
            $this->checkParam($app_order, [
                "user_id" => "required",
                "order_id" => "required",
                "currency_type" => "required|in:gold,diamond,item",
                "settle_amount" => "required",
                "result" => "required",
            ]);
            if ($app_order["currency_type"] == "diamond") {
                $app_order["settle_amount"] = VpService::transDiamondToCent($app_order["settle_amount"]);
            }
            $settle_details[] = [
                "user_id" => $p_o["channel_uid"],
                "partner_order_sn" => $p_o["order_id"],
                "pay_type" => $app_order["currency_type"],
                "settle_amount" => $app_order["settle_amount"],
                "result" => $app_order["result"]
            ];
        }

        $vp_settled_data = [
            "product_type" => $product_type,
            "product_id" => $product_id,
            "settle_details" => $settle_details
        ];


        $response = $this->vp_service->settlePartnerGame($vp_settled_data);
        if ($response === false) {
            $this->fail(ResponseService::SERVER_ERROR, "请求失败，请稍后再试");
        }
        $decode_response = json_decode($response, true);
        if (isset($decode_response["data"]["settle_result"]) && $decode_response["data"]["settle_result"] == "accepted") {
            $this->success();
        } else {
            Log::warning("vp 游戏结算api返回值错误：" . json_encode($decode_response));
            if(isset($decode_response["code"]) && $decode_response["message"]){
                $this->fail($decode_response["code"], $decode_response["message"]);
            }else{
                $this->fail(ResponseService::SERVER_ERROR);
            }
        }
    }

    /**
     * 回滚订单
     */
    public function rollback()
    {
        $this->checkParam($this->request->all(), [
            "app_id" => "required|in:rune,portal,tower,flip,midas,football",
            "product_type" => "required",
            "product_id" => "required",
        ]);
        $product_type = $this->request->input("product_type");
        $product_id = $this->request->input("product_id");

        $response = $this->vp_service->rollback($product_type, $product_id);
        if ($response === false) {
            $this->fail(ResponseService::SERVER_ERROR, "请求失败，请稍后再试");
        }
        $decode_response = json_decode($response, true);

        if (isset($decode_response["data"]["rollback_result"]) && $decode_response["data"]["rollback_result"] == "accepted") {
            $log_data = [
                "product_type" => $product_type,
                "product_id" => $product_id,
                "response_data" => $decode_response,
            ];
            RollbackLogModel::query()->create($log_data);
            $this->success();
        } else {
            Log::error("vp 游戏回滚api返回值错误：" . json_encode($decode_response));
            if(isset($decode_response["code"]) && $decode_response["message"]){
                $this->fail($decode_response["code"], $decode_response["message"]);
            }else{
                $this->fail(ResponseService::SERVER_ERROR);
            }
        }
    }

    /**
     * 取消订单
     */
    public function cancelOrder()
    {
        $this->checkParam($this->request->all(), [
            "app_id" => "required|in:rune,portal,tower,flip,midas,football",
            "app_order_id" => "required",
        ]);
        $app_id = $this->request->input("app_id");
        $app_order_id = $this->request->input("app_order_id");

        if (!$local_record = OrderModel::query()->where("app_id", $app_id)->where("app_order_id", $app_order_id)->first()) {
            $this->fail(ResponseService::ORDER_ID_NOT_EXIST);
        }
        $channel_order_id = OrderModel::query()->where("app_id", $app_id)->where("app_order_id", $app_order_id)->value("channel_order_id");
        $response = $this->vp_service->cancelOrder($channel_order_id);

        if ($response === false) {
            $this->fail(ResponseService::SERVER_ERROR, "请求失败，请稍后再试");
        }

        $decode_response = json_decode($response, true);
        if (isset($decode_response["data"]) && ($decode_response["data"] == true)) {
            $update_info = [
                "order_id" => $channel_order_id,
                "status" => 2
            ];
            $job = new ModifyOrderJob("update", $update_info);
            dispatch($job);

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
            if(isset($decode_response["code"]) && $decode_response["message"]){
                $this->fail($decode_response["code"], $decode_response["message"]);
            }else{
                $this->fail(ResponseService::SERVER_ERROR);
            }
        }

    }


    /**
     * 取消订单
     */
    public function queryOrder()
    {
        $this->checkParam($this->request->all(), [
            "app_id" => "required",
            "app_order_id" => "required",
            "uid" => "required",
        ]);
        $app_id = $this->request->input("app_id");
        $app_order_id = $this->request->input("app_order_id");
        $uid = $this->request->input("uid");

        if (!$order = OrderModel::query()->where("app_id", $app_id)->where("app_order_id", $app_order_id)->where("user_id", $uid)->first()) {
            $this->fail(ResponseService::ORDER_ID_NOT_EXIST);
        }
        $channel_order_id = $order->channel_order_id;
        $channel_uid = $order->channel_uid;
        $query_order = $this->vp_service->queryPartnerOrderState($channel_order_id, $channel_uid);
        if ($query_order === false) {
            $this->fail(ResponseService::SERVER_ERROR, "请求失败，请稍后再试");
        }
        if ($query_order == 404) {
            $this->fail(ResponseService::ORDER_ID_NOT_EXIST);
        }
        $order_status = OrderModel::transOrderStatus($query_order["data"]["status"]);
        $this->success([
            "app_order_id" => $app_order_id,
            "order_status" => $order_status
        ]);
    }


}
