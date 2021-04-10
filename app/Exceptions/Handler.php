<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        ModelNotFoundException::class,
        HttpException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception $e
     * @return void
     * @throws Exception
     */
    public function report(Exception $e)
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if ($e instanceof HttpException) {
            // 正常返回数据
            return response($e->getMessage(), $e->getStatusCode(), $e->getHeaders());
        }
        Log::critical($e->getMessage(), $e->getTrace());
        if ($e instanceof FatalThrowableError || (method_exists($e, 'getStatusCode') && $e->getStatusCode() == 500)) {
            if ($request->getBaseUrl() == "/v1" && $request->getMethod() == "POST") {
                // SDK API Format
                $message = [
                    'id' => $this->data['id'] ?? '',
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => 5000,
                        'msg' => 'Server Error'
                    ]
                ];
            } else {
                $message = [
                    "code" => 5000,
                    "msg" => 'Server Error'
                ];
            }
            return response($message, 200, []);
        } else {
            return parent::render($request, $e);
        }
    }
}
