<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/7/31
 * Time: 18:37
 */

namespace App\Models\Vp;


use Illuminate\Database\Eloquent\Model;

class CoinExchangeModel extends Model
{
    protected $connection = "mysql_vb";
    protected $table = "bc_cms_coin_exchange";
    public $timestamps = false;
    protected $fillable = [

    ];//开启白名单字段


    /**
     * 得到汇率对照表
     * @return mixed
     */
    public static function getExchange()
    {
        $exchange_map = self::query()->select(["coin_type", "exchange"])->get();
        $exchange_map = obj2arr($exchange_map);
        $key = array_column($exchange_map, "coin_type");
        $value = array_column($exchange_map, "exchange");
        return array_combine($key, $value);
    }

}
