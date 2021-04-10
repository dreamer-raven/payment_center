<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/14
 * Time: 14:32
 */

namespace App\Service\Vp;


use App\Jobs\Vp\SettleSucJob;
use App\Models\BetRecordModel;
use App\Models\PlatformConfigModel;
use App\Models\Tower\TowerRecordModel;
use Illuminate\Support\Facades\Log;

/**
 * 第三方回调接口
 * Class CallbackService
 * @package App\Service
 */
class NotifyService
{
    private $platform_id;
    private $prediction_success_url;
    private $settle_success_url;

    public function __construct($platform_id)
    {
        $this->__setAttribute($platform_id);
    }

    /**
     * 属性初始化
     * @param $platform_id
     */
    private function __setAttribute($platform_id)
    {
        $this->platform_id = $platform_id;

//        $this->prediction_success_url = PlatformConfigModel::query()
//            ->where("platform_id", $platform_id)
//            ->where("name", "prediction_success_url")
//            ->where("status", 1)
//            ->value("value");
//        $this->settle_success_url = PlatformConfigModel::query()
//            ->where("platform_id", $platform_id)
//            ->where("name", "settle_success_url")
//            ->where("status", 1)
//            ->value("value");
    }


    /**
     * 结算成功的通知
     * @param $award_at
     * @param $settle_data
     * @param array $broadcast_uids
     */
    public function SettleSuccessNotify($award_at, $settle_data, $broadcast_uids = [])
    {
//        if (!$this->settle_success_url) {
//            Log::error("结算成功第三方回调失败：平台{$this->platform_id}的回调地址为空");
//            return;
//        }
        $job = (new SettleSucJob($this->platform_id, $award_at, $settle_data, 1, $broadcast_uids));
        dispatch($job);
    }

    public function transRuneRecord($channel, $match_code, $records, $is_award, $winner)
    {
        $product_type = $channel == 4 ? "Rune" : "Portal";
        $settled_callback_data = [
            "product_type" => $product_type,
            "product_id" => $match_code,
            "settle_details" => []
        ];
        foreach ($records as $r) {
            if ($r["status"] != BetRecordModel::STATUS_SETTLED) {
                //订单状态有问题，不进行推送，打log
                Log::error("RuneCallback error:订单status不为已结束，订单信息：" . json_encode($r));
                continue;
            }
            if ($r["pay_out_odds"] == 1 && $r["bonus"] == 0) {
                $settle_amount = $r["price"];
                $settle_amount = $this->__getSettleAmount($r["coin_type"], $settle_amount);
            } else {
                $settle_amount = $this->__getSettleAmount($r["coin_type"], $r["bonus"]);
            }

            if ($is_award == 1 && $winner == "R") {
                $result = "canceled";
            } else if ($is_award == -1) {
                $result = "draw";
            } else {
                $result = "settled";
            }

            $settled_callback_data["settle_details"][] = [
                "user_id" => $r["platform_uid"],
                "partner_order_sn" => $r["order_no"],
                "pay_type" => $r["coin_type"],
                "settle_amount" => $settle_amount,
                "result" => $result
            ];
        }
        return $settled_callback_data;
    }

    public function transTowerRecordToSettleData($uid, $towerId)
    {
        $record = TowerRecordModel::query()->where([["uid", "=", $uid], ["tower_id", "=", $towerId]])->first();
        if ($record->status != 2) {
            return false;
        }

        $settle_amount = $this->__getSettleAmount($record->coin_type, $record->bonus);
        $settled_callback_data = [
            "product_type" => "Tower",
            "product_id" => $towerId,
            "settle_details" => [
                [
                    "user_id" => $record->platform_uid,
                    "partner_order_sn" => $record->order_no,
                    "pay_type" => $record->coin_type,
                    "settle_amount" => $settle_amount,
                    "result" => $record->pay_out_floor == 0 ? "canceled" : "settled"
                ]
            ]
        ];
        return $settled_callback_data;
    }

    private function __getSettleAmount($coin_type, $amount)
    {
        switch ($coin_type) {
            case "vb":
                $settle_amount = $amount > 0 ? $amount : 0;#这个输的传0
                break;
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


}