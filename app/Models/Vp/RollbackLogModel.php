<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/7/31
 * Time: 18:37
 */

namespace App\Models\Vp;


use Illuminate\Database\Eloquent\Model;

class RollbackLogModel extends Model
{
    protected $table = "vb_game_log_rollback";
    public $timestamps = false;
    protected $fillable = [
        "product_type",
        "product_id",
        "response_data",
    ];//开启白名单字段


    public function setResponseDataAttribute($value)
    {
        $this->attributes['response_data'] = json_encode($value);
    }


}
