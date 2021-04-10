<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/7/31
 * Time: 18:37
 */

namespace App\Models\Vp;


use Illuminate\Database\Eloquent\Model;

class BetRecordMiddlewareModel extends Model
{
//    protected $connection = "mysql_log";
    protected $table = "bc_bet_record_middleware";
    public $timestamps = false;
    protected $fillable = [
        "type",
        "order_no",
        "order_info",
        "statue",
        "platform_uid",
    ];//开启白名单字段




}