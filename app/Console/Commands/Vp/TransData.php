<?php

namespace App\Console\Commands\Vp;

use App\Models\Tower\TowerRecordModel;
use App\Models\Vp\OrderModel;
use App\Service\Vp\UCenterService;
use App\Service\Vp\VpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 主动查询vp待支付的订单状态
 * Class RuneCallback
 * @package App\Console\Commands\Fundata
 */
class TransData extends Command
{
    /**
     * 控制台命令 signature 的名称。
     *
     * @var string
     */
    protected $signature = 'TransData';

    /**
     * 控制台命令说明。
     *
     * @var string
     */
    protected $description = 'TransData';


    public function handle()
    {
        $i = 0;
        while (1){
            $start_date = "2020-08-19";
            $date = date("Y-m-d", strtotime($start_date) + ($i * 86400));
            echo $date . "\n";
            if($date == "2020-11-22"){
                echo "done";
                break;
            }
            $vb_mid_order = DB::connection("mysql_vb")
                ->table("bc_bet_record_middleware")
                ->whereDate("created_at", $date)
                ->get();
            try{
                DB::beginTransaction();
                foreach ($vb_mid_order as $v_o){
                    $v_u = $vb_mid_order = DB::connection("mysql_vb")
                        ->table("bc_cms_user")
                        ->where([
                            ["platform_id", "=", 10],
                            ["platform_uid", "=", $v_o->platform_uid]
                        ])->first();
                    if(!$v_u){
                        echo "error:" . json_encode($v_o);
                        die;
                    }
                    $info = json_decode($v_o->order_info, true);

                    $order = [
                        "order_id" => $v_o->order_no,
                        "user_id" => $v_u->id,
                        "app_id" => strtolower($v_o->type),
                        "app_order_id" => $v_o->order_no,
                        "channel_id" => "vp",
                        "channel_order_id" => $v_o->order_no,
                        "channel_uid" => $v_o->platform_uid,
                        "product_id" => $v_o->type == "TOWER" ? $v_o->order_no : $info["details"][0]["match_code"],
                        "product_title" => $v_o->type == "TOWER" ? "tower-" . $v_o->order_no : "prediction-" . $v_o->order_no,
                        "product_desc" => "",
                        "product_type" => $v_o->type == "TOWER" ? "tower" : $info["details"][0]["play_type"],
                        "order_amount" => $info["price"],
                        "order_status" => $v_o->status == 1 ? 1:2,
                        "currency_type" => $info["coin_type"],
                        "callback_times" => 1,
                        "callback_status" => 1,
                        "created_at" => $v_o->created_at,
                        "updated_at" => $v_o->updated_at,
                    ];

                    DB::connection("mysql_pay")
                        ->table("vb_game_payment_order")
                        ->insert($order);
                    echo "insert:" . json_encode($order) . "\n";

                    unset($info, $order);
                }

                DB::commit();
            }catch (\Exception $e){
                echo $e->getMessage();
                DB::rollBack();
            }



            unset($vb_mid_order);




            $i++;
        }

//        $vb_mid_order = DB::connection("mysql_vb")->table("bc_bet_record_middleware")->whereDate()

    }
}
