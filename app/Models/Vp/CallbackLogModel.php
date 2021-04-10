<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/7/31
 * Time: 18:37
 */

namespace App\Models\Vp;


use Illuminate\Database\Eloquent\Model;

class CallbackLogModel extends Model
{
//    protected $connection = "mysql_log";
    protected $table = "vb_game_log_callback";
    public $timestamps = false;
    protected $fillable = [
        "app_id",
        "url",
        "request_data",
        "response_data",
        "time",
        "status",
        "try_time",
    ];//开启白名单字段

    public function setRequestDataAttribute($value)
    {
        $this->attributes['request_data'] = json_encode($value);
    }

    public function setResponseDataAttribute($value)
    {
        $this->attributes['response_data'] = json_encode($value);
    }


}
