<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;
use App\Service\ResponseService;

class BaseController extends Controller
{
    CONST LANG = ['zh', 'en'];
    public $request;
    protected $lang;

    public function __construct(Request $request)
    {
        $this->request = $request;
        if (!$this->lang = $this->request->input('lang')) {
            $this->lang = 'zh';
        }
    }

    
    
    
    /**
     * 返回正确数据
     * @param string $data
     * @param string $extMsg
     * @param int $statuCode
     */
    public function success($data = '', $extMsg = '', $statuCode = 200)
    {
        ResponseService::success($data, $extMsg, $statuCode, $this->lang);
    }
    
    /**
     * 检查参数
     * @param $param
     * @param $rules
     */
    public function checkParam($param, $rules)
    {
        ResponseService::checkParam($param, $rules);
    }
    
    /**
     * 返回错误码
     * @param $code
     * @param string $msg
     */
    public function fail($code, $msg = '')
    {
        ResponseService::fail($code, $msg, $this->lang);
    }


}