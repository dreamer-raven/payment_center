<?php
/**
 * CORS route Middleware.
 */

namespace App\Http\Middleware;

use App\Service\Validate;
use App\Utils\DomainIp;
use Closure;
use Illuminate\Http\Response;

class CorsMiddleware
{
    private $allow_origin;


    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->allow_origin = [

        ];
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        //如果origin不在允许列表内，直接返回403
//        if (!in_array($origin, $this->allow_origin) && !empty($origin))
//            return new Response('Forbidden', 403);
        if ($request->getMethod() == "OPTIONS") {
            $response = new Response('', 200);
        } else {
            $response = $next($request);
            $response->header('Content-Type', 'application/json; charset=utf-8');
        }
        $response->header('Access-Control-Allow-Origin', $origin);
        #apache如果模块不支持会无法显示，同时php环境需要打开ini中的compress相关选项
//        $response->header('Content-Encoding', 'gzip');
        if ($origin) {
            $response->header("Access-Control-Allow-Methods", "GET,HEAD,PUT,POST,DELETE,PATCH,OPTIONS");
            $response->header('Access-Control-Allow-Headers', "Content-Type,X-Auth-Token,Origin,Authorization,X-Requested-With");
            $response->header('Access-Control-Allow-Credentials', "true");
        }
        return $response;
    }

}
