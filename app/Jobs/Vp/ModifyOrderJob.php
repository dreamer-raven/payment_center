<?php

namespace App\Jobs\Vp;

use App\Jobs\Job;
use App\Models\Vp\OrderModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModifyOrderJob extends Job
{


    private $action;
    private $order_info;

    public function __construct($action, $order_info)
    {
        $this->action = $action;
        $this->order_info = $order_info;
    }


    public function handle()
    {
        echo "ModifyOrderJob action:{$this->action} info:" . json_encode($this->order_info);
        switch ($this->action){
            case "create" :
                $this->__createOrder();
                break;
            case "update":
                $this->__updateOrder();
                break;
            default:
        }

    }

    private function __createOrder()
    {
        OrderModel::query()->create($this->order_info);
    }

    private function __updateOrder()
    {
        try{
            DB::beginTransaction();
            $order = OrderModel::query()->where("order_id", $this->order_info["order_id"])->lockForUpdate()->first();
            if(!in_array($order->status, ["pending", "paid"])){
                #订单状态已结束
                DB::rollBack();
                return;
            }
            OrderModel::query()->where("order_id", $this->order_info["order_id"])->update($this->order_info);
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            Log::error("ModifyOrderJob error:" . $e->getMessage());
        }
    }




}