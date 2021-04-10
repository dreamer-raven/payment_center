<?php

namespace App\Console\Commands\Vp;

use App\Models\Tower\TowerRecordModel;
use App\Models\Vp\OrderModel;
use App\Service\Vp\UCenterService;
use App\Service\Vp\VpService;
use Illuminate\Console\Command;

/**
 * 主动查询vp待支付的订单状态
 * Class RuneCallback
 * @package App\Console\Commands\Fundata
 */
class CancelExpiredOrder extends Command
{
    /**
     * 控制台命令 signature 的名称。
     *
     * @var string
     */
    protected $signature = 'CancelExpiredOrder';

    /**
     * 控制台命令说明。
     *
     * @var string
     */
    protected $description = 'CancelExpiredOrder';


    public function handle()
    {
        $records = OrderModel::query()
            ->where("order_status", 0)
            ->where("app_id", "!=", "flip")
            ->whereRaw("created_at <= DATE_SUB(NOW(), INTERVAL 3 MINUTE)")
            ->get();
        $vp_service = new VpService();
        foreach ($records as $r) {
            $res = $vp_service->cancelOrder($r->channel_order_id);
        }
    }
}
