<?php

namespace App\Console\Commands\Vp;

use App\Jobs\Vp\CallbackSendGameJob;
use App\Jobs\Vp\ModifyOrderJob;
use App\Models\Vp\GameConfigModel;
use App\Models\Vp\OrderModel;
use App\Service\Vp\UCenterService;
use App\Service\Vp\VpService;
use App\Utils\Checker;
use Illuminate\Console\Command;

/**
 * 主动查询vp待支付的订单状态
 * Class RuneCallback
 * @package App\Console\Commands\Fundata
 */
class FlipCancelExpiredOrder extends Command
{
    /**
     * 控制台命令 signature 的名称。
     *
     * @var string
     */
    protected $signature = 'FlipCancelExpiredOrder';

    /**
     * 控制台命令说明。
     *
     * @var string
     */
    protected $description = 'FlipCancelExpiredOrder';


    public function handle()
    {
        $config = GameConfigModel::query()->where("app_id", "flip")->first();
        $records = OrderModel::query()
            ->where("order_status", 0)
            ->where("app_id", "flip")
            ->whereRaw("created_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)")
            ->get();
        $vp_service = new VpService();
        foreach ($records as $r) {
            $response = $vp_service->cancelOrder($r->channel_order_id);

            if ($response === false) {
                continue;
            }

            $decode_response = json_decode($response, true);
            if (isset($decode_response["data"]) && ($decode_response["data"] == true)) {
                $update_info = [
                    "order_id" => $r->channel_order_id,
                    "status" => 2
                ];
                $job = new ModifyOrderJob("update", $update_info);
                dispatch($job);

                $app_callback_data = [
                    "order_id" => $r->order_id,
                    "app_order_id" => $r->app_order_id,
                    "currency_type" => $r->currency_type,
                    "order_amount" => $r->order_amount,
                    "order_status" => OrderModel::ORDER_STATUS_CANCELED,
                    "paid_at" => time(),
                ];
                $sign = Checker::getHmacSign($app_callback_data, $config->secret_key);
                $app_callback_data['sign'] = $sign;
                if ($config->callback_url) {
                    $job = new CallbackSendGameJob($r->app_id, $config->callback_url, $app_callback_data);
                    dispatch($job);
                }
            }
        }
    }
}
