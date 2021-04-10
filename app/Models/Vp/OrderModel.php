<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/7/31
 * Time: 18:37
 */

namespace App\Models\Vp;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class OrderModel extends Model
{
    const ORDER_STATUS_PENDING = 0;
    const ORDER_STATUS_PAID = 1;
    const ORDER_STATUS_CANCELED = 2;
    CONST ORDER_STATUS_REFUND = 3;
    protected $table = "vb_game_payment_order";
    public $timestamps = false;
    protected $fillable = [
        "order_id",
        "user_id",
        "app_id",
        "app_order_id",
        "channel_id",
        "channel_uid",
        "channel_order_id",
        "product_id",
        "product_title",
        "product_desc",
        "product_type",
        "order_amount",
        "order_status",
        "currency_type",
    ];//开启白名单字段



    /**
     * 将vp的订单状态转换为本地状态
     * @param $callback_status
     * @return int|null
     */
    public static function transOrderStatus($callback_status)
    {

        switch ($callback_status){
            case "pending":
                $order_status = 0;
                break;
            case "paid":
                #已支付
                $order_status = 1;
                break;
            case "failed":
                #支付失败
                $order_status = 2;
                break;
            case "canceled":
                #取消订单
                $order_status = 2;
                break;
            default:
                $order_status = null;
                Log::error("Payment CallbackController 转换时遇到了没有定义过的状态：$callback_status");
        }
        return $order_status;
    }

}
