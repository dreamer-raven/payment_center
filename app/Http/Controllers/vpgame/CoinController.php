<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/13
 * Time: 15:56
 */

namespace App\Http\Controllers\vpgame;

use App\Http\Controllers\BaseController;
use App\Models\Vp\CoinExchangeModel;
use Illuminate\Http\Request;

/**
 * 登塔控制器
 * Class UserController
 * @package App\Http\Controllers\v1
 */
class CoinController extends BaseController
{

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * 得到vp的货币汇率
     */
    public function getExchange()
    {
        $exchange_map = CoinExchangeModel::getExchange();
        $this->success($exchange_map);
    }

}
