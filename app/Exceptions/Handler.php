<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof UnauthorizedHttpException) {
            $preException = $exception->getPrevious();
            if ($preException instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
                return response()->json(['code' => 401, 'msg' => 'Token过期']);
            } else if ($preException instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
                return response()->json(['code' => 401, 'msg' => 'Token无效']);
            } else if ($preException instanceof \Tymon\JWTAuth\Exceptions\TokenBlacklistedException) {
                 return response()->json(['code' => 401, 'msg' => 'Token黑名单']);
           }
           if ($exception->getMessage() === 'Token not provided') {
               return response()->json(['code' => 401, 'msg' => '未授权']);
           }
        }
        return response()->json(['code' => $exception->getCode(), 'msg' => $exception->getCode(), 'msg_dedescription' => $this->enrichException($exception)]);
    }

    /**
     * 丰富异常
     */

    /**
     * 丰富异常
     */
    private function enrichException(Throwable $e)
    {

        $requestTime = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);
        $appRequest = app(Request::class);
        $requestUrl = $appRequest->url();
        if (!$requestUrl) {
            $requestUrl = '-';
        }
        $requestParams = '-';
        if ($appRequest->isMethod('GET') || $appRequest->isMethod('POST')) {
            $requestParams = $appRequest->all();
        }
        $excptMsg = $e->getMessage();
        $traceDetail = array_slice($e->getTrace(), 0, 2);
        $traceLines = explode("\n", $e->getTraceAsString());

        // 请求地址描述
        $requestUrlDesc = $appRequest->getPathInfo();
        if (!$requestUrlDesc) {
            $requestUrlDesc = '-';
        }
        // 异常消息描述
        $excptMsgDesc = mb_substr($excptMsg, 0, 50);
        $excptMsgDesc = str_replace(["\n", "\r", "'", '"', "\t", "\0", "\x0B"], '', $excptMsgDesc);
        $excptMsgDesc = trim($excptMsgDesc);

        $subject = "请求{$requestUrlDesc}异常：{$excptMsgDesc}";
        $info = [
            '请求地址' => $requestUrl,
            '异常类' => [
                '文件' => $e->getFile(),
                '行数' => $e->getLine(),
                '异常类' => get_class($e),
                '异常code' => $e->getCode(),
                '异常消息' => $excptMsg,
            ],
            '异常StackTrace概述' => $traceLines,
            '请求' => [
                '请求时间' => $requestTime,
                '请求方法' => self::getServerVariable('REQUEST_METHOD'),
                '请求地址' => $requestUrl,
                '请求参数' => $requestParams,
                '变量SERVER_PORT' => self::getServerVariable('SERVER_PORT'),
                '变量REQUEST_URI' => self::getServerVariable('REQUEST_URI'),
                '变量QUERY_STRING' => self::getServerVariable('QUERY_STRING'),
                '变量SCRIPT_FILENAME' => self::getServerVariable('SCRIPT_FILENAME'),
            ],
            '环境' => [
                '站点名称' => self::getServerVariable('SERVER_NAME'),
                '服务器IP' => self::getServerVariable('SERVER_ADDR'),
                '变量SITE_ENV' => self::getServerVariable('SITE_ENV'),
                '变量SITE_LOG_DIR' => self::getServerVariable('SITE_LOG_DIR'),
            ],
            '客户端' => [
                '客户端IP' => $appRequest->ips(),
                '浏览器Agent' => self::getServerVariable('HTTP_USER_AGENT'),
                'COOKIE' => $_COOKIE,
            ],
            '已经或准备发送的HTTP响应头' => headers_list(),
            '异常StackTrace详情' => $traceDetail,
            'subject' => $subject,
        ];

        return $info;
    }

    private static function getServerVariable($varName)
    {
        return isset($_SERVER[$varName]) ? $_SERVER[$varName] : '无';
    }


}
