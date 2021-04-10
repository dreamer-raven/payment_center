<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['namespace' => 'vpgame', "prefix" => "v1"], function () use ($router) {
    $router->group(['prefix' => 'vpgame'], function () use ($router) {
        $router->get('currency/rate', 'CoinController@getExchange');
        $router->group(['namespace' => 'web'], function () use ($router) {
            $router->post('order/close', 'OrderController@closeOrder');
        });
    });
    $router->get('currency/rate', 'CoinController@getExchange');;

    $router->group(['namespace' => 'web'], function () use ($router) {
        $router->post('order/close', 'OrderController@closeOrder');
    });

    $router->group(['namespace' => 'api'], function () use ($router) {
        $router->post('order/callback', 'CallbackController@orderCallback');
        $router->get('order/status', 'QueryController@queryOrderCanPay');
    });

    $router->get('currency/rate', 'CoinController@getExchange');
    $router->group(['prefix' => 'service'], function () use ($router) {
        $router->post('order', 'OrderController@createOrder');
        $router->get('order/status', 'OrderController@queryOrder');
        $router->post('order/canceled', 'OrderController@cancelOrder');
        $router->post('rollback', 'OrderController@rollback');
        $router->post('order/settled', 'OrderController@settleOrder');
        $router->get('coin/exchange', 'CoinController@getExchange');
    });
});



