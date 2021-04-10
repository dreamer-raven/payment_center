<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/14
 * Time: 14:32
 */

namespace App\Service\Vp;


use App\Models\Coin\CoinExchangeModel;

/**
 * 汇率service
 * Class CallbackService
 * @package App\Service
 */
class ExchangeService
{
    public static function getExchange()
    {
        return CoinExchangeModel::getExchange();
    }

    public static function getCoinExchange($coin_type)
    {
        $exchange_map = self::getExchange();
        return $exchange_map[$coin_type] ?? 0;
    }

    /**
     * 获得平台币在该币种汇率下的数额
     * @param $coin_type
     * @param $coin_exchange
     * @param $value
     * @return string
     */
    public static function getCoinExchangeValue($coin_type, $coin_exchange, $value)
    {
        $exchange_value = bcdiv($value, $coin_exchange, 2);
        if($coin_type == "gold"){
            $exchange_value = numberFormat($exchange_value, 0);
        }
        return $exchange_value;
    }


}