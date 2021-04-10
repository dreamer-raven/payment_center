<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2020/5/15
 * Time: 17:31
 */

namespace App\Service\Vp;


use App\Jobs\Alchemy\PaymentCallbackJob;
use App\Models\BetRecordDetailModel;
use App\Models\BetRecordModel;
use App\Models\Tower\TowerRecordModel;
use App\Models\Vp\BetRecordMiddlewareModel;
use App\Service\Lottery\LotteryService;
use App\Service\Tower\TowerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallbackService
{

    /**
     * 处理支付后的订单
     * @param $partner_order_sn
     * @param $partner_order_status
     * @return string
     */
    public function settleMiddlewareRecord($partner_order_sn, $partner_order_status)
    {
        try {
            DB::beginTransaction();
            #数据库逻辑部分
            #最终回调的data
            $callback_data = ["partner_order_sn" => $partner_order_sn, "status" => $partner_order_status];
            $middleware_record = BetRecordMiddlewareModel::query()->where("order_no", $partner_order_sn)->lockForUpdate()->first();
            if (!$middleware_record) {
                #订单不存在，打log，回滚
                echo "远端订单本地没有查到，订单号：$partner_order_sn\n";
                Log::error("远端订单本地没有查到，订单号：$partner_order_sn");
                DB::rollBack();
                return "failed";
            }
            if ($middleware_record->status != 0) {
                #订单已被处理，返回处理成功
                echo "订单已被处理\n";
                DB::rollBack();
                return "success";
            }
            #区分支付状态
            switch ($partner_order_status) {
                case "pending":
                    #待支付，不做处理
                    echo "待支付，不做处理\n";
                    break;
                case "paid":
                    #已支付
                    echo "支付成功\n";
                    $this->paySuc($middleware_record);
                    $this->markMiddlewareRecord($partner_order_sn, 1, json_encode($callback_data));
                    break;
                case "closed":
                    echo "支付失败\n";
                    #已关闭，没有支付，直接把中间件订单标记为支付失败
                    $this->payFailed($middleware_record);
                    $this->markMiddlewareRecord($partner_order_sn, -1, json_encode($callback_data));
                    break;
                case "failed":
                    echo "支付失败\n";
                    #已关闭，没有支付，直接把中间件订单标记为支付失败
                    $this->payFailed($middleware_record);
                    $this->markMiddlewareRecord($partner_order_sn, -2, json_encode($callback_data));
                    break;
                case "canceled":
                    echo "取消订单\n";
                    #取消订单，没有支付
                    $this->payFailed($middleware_record);
                    $this->markMiddlewareRecord($partner_order_sn, -1, json_encode($callback_data));
                    break;
            }
            DB::commit();
        } catch (\Exception $e) {
            #数据库逻辑出错
            echo "执行Vp订单回调/查询订单最终支付状态后数据库逻辑出错：" . $e->getMessage()  . "\n";
            Log::error("执行Vp订单回调/查询订单最终支付状态后数据库逻辑出错：" . $e->getMessage());
            DB::rollBack();
            return "failed";
        }

        $middleware_record = BetRecordMiddlewareModel::query()->where("order_no", $partner_order_sn)->first();
        if ($middleware_record->type == "TOWER" && $partner_order_status == "paid") {
            echo "开始创建塔\n";
            $this->remoteTowerStart($middleware_record);
        }
        echo "成功 \n";

        return "success";
    }


    /**
     * 远端不存在，标记订单
     * @param $partner_order_sn
     * @return string
     */
    public function cancelMiddlewareRecord($partner_order_sn)
    {
        try {
            DB::beginTransaction();
            #数据库逻辑部分
            #最终回调的data
            $middleware_record = BetRecordMiddlewareModel::query()->where("order_no", $partner_order_sn)->lockForUpdate()->first();
            if (!$middleware_record) {
                #订单不存在，打log，回滚
                echo "远端订单本地没有查到，订单号：$partner_order_sn\n";
                Log::error("远端订单本地没有查到，订单号：$partner_order_sn");
                DB::rollBack();
                return "failed";
            }
            if ($middleware_record->status != 0) {
                #订单已被处理，返回处理成功
                echo "订单已被处理\n";
                DB::rollBack();
                return "success";
            }
            $this->markMiddlewareRecord($partner_order_sn, -1, 404);
            DB::commit();
        } catch (\Exception $e) {
            #数据库逻辑出错
            echo "执行Vp订单回调/查询订单最终支付状态后数据库逻辑出错：" . $e->getMessage()  . "\n";
            Log::error("执行Vp订单回调/查询订单最终支付状态后数据库逻辑出错：" . $e->getMessage());
            DB::rollBack();
            return "failed";
        }

        return "success";
    }

    /**
     * 远端启动塔
     * @param $middleware_record
     */
    public function remoteTowerStart($middleware_record)
    {
        $order_info = json_decode($middleware_record->order_info, true);
        $towerService = new TowerService($order_info["platform_id"], $order_info["uid"], $order_info["platform_uid"], $order_info["agent_id"]);
        echo "启动远端塔，塔信息：{$order_info["mode"]}{$order_info["coin_type"]}{$order_info["price"]}\n";
        $towerInfo = $towerService->remoteStartGame($order_info["mode"], $order_info["coin_type"], $order_info["price"]);
        if ($towerInfo === false) {
            #远端执行塔失败，打log后不继续执行操作，靠定时任务扫到然后再进行处理
            Log::error("Vp CallbackController remote start tower error : order[{$order_info["order_no"]}]");
        }
    }

    /**
     * 标记中间件订单表最终状态
     * @param $order_no
     * @param $status
     * @param $callback_data
     */
    public function markMiddlewareRecord($order_no, $status, $callback_data)
    {
        BetRecordMiddlewareModel::query()->where("order_no", $order_no)->update(["status" => $status, "callback_data" => $callback_data]);
    }

    /**
     * 创建电竞表的订单
     * @param $order_info
     */
    private function createEsportsRecords($order_info)
    {
        $order_info = json_decode($order_info, true);
        $details = $order_info["details"];
        unset($order_info["details"]);
        BetRecordModel::query()->insert($order_info);
        BetRecordDetailModel::query()->insert($details);
    }

    /**
     * 创建本地库的塔
     * @param $order_info
     */
    private function createLocalTowerRecord($order_info)
    {
        $order_info = json_decode($order_info,true);
        TowerRecordModel::query()->firstOrCreate(["order_no" => $order_info["order_no"]], $order_info);
    }

    /**
     * 把支付成功的订单从中间件订单表取出来，生成对应的数据在订单表
     * @param $middleware_record
     */
    private function paySuc($middleware_record)
    {
        switch ($middleware_record->type) {
            case "RUNE":
                //猜神符，正常入bet_record库
                $this->createEsportsRecords($middleware_record->order_info);
                break;
            case "PORTAL":
                //时空之门，正常入bet_record库
                $this->createEsportsRecords($middleware_record->order_info);
                $order_info = json_decode($middleware_record->order_info, true);
                LotteryService::portalPredictMoneyRecord($order_info["caiex_info"], $order_info["price"]);
                break;
            case "TOWER":
                //英雄塔，创建本地塔&远端请求塔赋值
                $this->createLocalTowerRecord($middleware_record->order_info);
                break;
            case "FLIP":
                //抢卡炼金，通知抢卡炼金项目支付成功，入库
                $order_info = json_decode($middleware_record->order_info, true);
                $job = (new PaymentCallbackJob($order_info["uid"], $order_info["platform_id"], $order_info["game_id"], $order_info["room_id"], $order_info["card_num"], $order_info["total"], PaymentCallbackJob::PAY_SUCCESS_STATUS, $order_info["order_no"]));
                dispatch($job);
                break;
        }
    }

    private function payFailed($middleware_record)
    {
        switch ($middleware_record->type) {
            case "FLIP":
                //抢卡炼金，通知抢卡炼金项目支付成功，入库
                $order_info = json_decode($middleware_record->order_info, true);
                $job = (new PaymentCallbackJob($order_info["uid"], $order_info["platform_id"], $order_info["game_id"], $order_info["room_id"], $order_info["card_num"], $order_info["total"], PaymentCallbackJob::PAY_FAILED_STATUS, $order_info["order_no"]));
                dispatch($job);
                break;
        }
    }
}