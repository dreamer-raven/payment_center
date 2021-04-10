<?php

namespace App\Jobs\Vp;

use App\Jobs\Job;
use App\Models\Vp\CallbackLogModel;
use App\Models\Vp\OrderModel;
use Illuminate\Support\Facades\Log;

/**
 * 通知下游小游戏的回调job
 * Class CallbackSendGameJob
 * @package App\Jobs\Vp
 */
class CallbackSendGameJob extends Job
{
    const DELAY_TIME_ARRAY = [
        1 => 0,
        2 => 5,
        3 => 10,
        4 => 30,
        5 => 300,
        6 => 600,
    ];

    private $try_time;
    private $callback_url;
    private $request_data;
    private $response_data;
    private $game;

    public function __construct($game, $callback_url, $request_data, $try_time = 1)
    {
        $this->game = $game;
        $this->callback_url = $callback_url;
        $this->request_data = $request_data;
        $this->try_time = $try_time;
        $this->response_data = "";
    }


    public function handle()
    {
        try {
            $options['timeout'] = 10;
            $response = \Requests::post($this->callback_url, [], $this->request_data, $options);
            Log::info("callback 通知下游action", ["url"=>$this->callback_url, "data"=>$this->request_data, "res"=>$response->body]);
            if (empty($response)) {
                #重试
                $this->__tryAgain();
            }
            $this->response_data = $response->body;
            if($response->body == "success")$response->body = '{"code":0,"msg":"success"}';
            $res = json_decode($response->body, true);
            $this->response_data = $res;
            if ($res["code"] == 0 || $res == "success") {
                #成功
                $data = [
                    "app_id" => $this->game,
                    "url" => $this->callback_url,
                    "request_data" => $this->request_data,
                    "response_data" => $this->response_data,
                    "time" => time(),
                    "status" => 1,
                    "try_time" => $this->try_time,
                ];
                CallbackLogModel::query()->create($data);
                OrderModel::query()->where("order_id", $this->request_data["order_id"])->update(["callback_times"=>$this->try_time, "callback_status"=>1]);
            } else {
                #失败
                $this->__tryAgain();
            }
        } catch (\Exception $e) {
            if ($this->try_time == 6) {
                Log::error("callback 通知小游戏6次全部失败", ["url" => $this->callback_url, "data" => $this->request_data, "error_msg" => $e->getMessage()]);
            }
            $this->__tryAgain();
        }

    }

    /**
     * 幂等回调重试
     */
    private function __tryAgain()
    {
        if ($this->try_time == 6) {
            return;
        }
        $data = [
            "app_id" => $this->game,
            "url" => $this->callback_url,
            "request_data" => $this->request_data,
            "response_data" => $this->response_data,
            "time" => time(),
            "status" => 0,
            "try_time" => $this->try_time,
        ];
        CallbackLogModel::query()->create($data);
        OrderModel::query()->where("order_id", $this->request_data["order_id"])->update(["callback_times"=>$this->try_time]);
        $this->try_time++;
        $job = (new CallbackSendGameJob($this->game, $this->callback_url, $this->request_data, $this->try_time))->delay(self::DELAY_TIME_ARRAY[$this->try_time]);
        dispatch($job);
    }


}