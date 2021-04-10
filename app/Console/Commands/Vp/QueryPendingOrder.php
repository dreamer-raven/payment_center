<?php

namespace App\Console\Commands\Vp;

use App\Jobs\Vp\CallbackSendGameJob;
use App\Models\Vp\GameConfigModel;
use App\Models\Vp\OrderModel;
use App\Service\Vp\VpService;
use App\Utils\Checker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 主动查询vp待支付的订单状态
 * Class RuneCallback
 * @package App\Console\Commands\Fundata
 */
class QueryPendingOrder extends Command
{
    /**
     * 控制台命令 signature 的名称。
     *
     * @var string
     */
    protected $signature = 'QueryPendingOrder';

    /**
     * 控制台命令说明。
     *
     * @var string
     */
    protected $description = 'QueryPendingOrder';


    public function handle()
    {
        $records = OrderModel::query()
            ->whereRaw("created_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)")
            ->where("order_status", 0)
            ->get();
        foreach ($records as $r) {
            try {
                $vp_service = new VpService();
                echo "正在查找订单{$r->order_id} ... \n";;;
                $response = $vp_service->queryPartnerOrderState($r->channel_order_id, $r->channel_uid);
                if ($response === false) {
                    continue;
                }
                if (isset($response["code"]) && $response["code"] == 3000404) {
                    echo "没有找到订单{$r->channel_order_id}\n";
                    #没有找到订单
                    $response = [
                        "data" => [
                            "partner_order_sn" => $r->partner_order_sn,
                            "status" => 2
                        ],
                    ];
                    $this->__markOrderStatus($response["data"]["partner_order_sn"], $response["data"]["status"], $response);
                    continue;
                }
                if (isset($response["data"]["partner_order_sn"]) && isset($response["data"]["status"])) {
                    $response["pay_type"] = $r->pay_type;
                    $response["amount"] = $r->amount;
                    $response["paid_at"] = time();
                    $this->__markOrderStatus($response["data"]["partner_order_sn"], $response["data"]["status"], $response);
                } else {
                    Log::error("支付中心 用于确认订单是否支付成功api返回值错误：" . json_encode($response));
                    continue;
                }
            } catch (\Exception $e) {
                Log::error("主动查询vp订单{$r->partner_order_sn}状态失败，原因:" . $e->getMessage());
            }
        }
    }

    public function __markOrderStatus($partner_order_sn, $status, $callback_data)
    {
        $result = null;
        try {
            DB::beginTransaction();
            $order = OrderModel::query()->where("channel_order_id", $partner_order_sn)->lockForUpdate()->first();
            $config = GameConfigModel::query()->where("app_id", $order->app_id)->first();
            if (!$config->callback_url) {
                Log::error("callback error:游戏{$order->app_id}未配置回调地址，无法通知到{$partner_order_sn}");
                DB::rollBack();
                echo "callback error:游戏{$order->app_id}未配置回调地址，无法通知到{$partner_order_sn}";
                return "order_refuse";
            }
            if ($order->status != 0) {
                #订单已被处理，返回处理成功
                echo "订单已被处理";
                DB::rollBack();
                return "success";
            }
            $status = OrderModel::transOrderStatus($status);
            OrderModel::query()->where("channel_order_id", $partner_order_sn)->update(["order_status" => $status]);
            DB::commit();

            if($status != 0){
                #准备回调数据
                $app_callback_data = [
                    "order_id" => $order->order_id,
                    "app_order_id" => $order->app_order_id,
                    "currency_type" => $order->currency_type,
                    "order_amount" => $order->order_amount,
                    "order_status" => $status,
                    "paid_at" => time(),
                ];
                $sign = Checker::getHmacSign($app_callback_data, $config->secret_key);
                $app_callback_data['sign'] = $sign;
                #进行异步回调
                echo "异步回调开启：" . json_encode($app_callback_data);
                $job = new CallbackSendGameJob($order->app_id, $config->callback_url, $app_callback_data);
                dispatch($job);
            }
            return "success";
        } catch (\Exception $e) {
            echo $e->getMessage() . "||" . $e->getLine();
            DB::rollBack();
            return "order_refuse";
        }
    }


}
