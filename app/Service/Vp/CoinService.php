<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/14
 * Time: 14:32
 */

namespace App\Service\Vp;


/**
 * 汇率service
 * Class CallbackService
 * @package App\Service
 */
class CoinService
{
    public static function transCoin($coin_type, $value)
    {
        switch ($coin_type){
            case "gold":
                #p豆强行转换下注额为整数
                $value = numberFormat($value, 0);
                break;
            case "diamond":
                #钻石强行转换下注额为两位小数
                $value = numberFormat($value, 2);
                break;
            default:

        }
        return $value;
    }


}