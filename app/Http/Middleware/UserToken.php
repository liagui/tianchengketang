<?php

namespace App\Http\Middleware;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SCookie;

class UserToken {
    public function handle($request, Closure $next){
        //获取用户token值
        $token = $request->input('user_token');

        //判断用户token是否为空
        if(!$token || empty($token)){
            $json_info = [];
        } else {
            //获取请求的平台端
            $platform = verifyPlat() ? verifyPlat() : 'pc';

            //hash中token赋值
            $token_key   = "user:regtoken:".$platform.":".$token;
    
            //解析json获取用户详情信息
            $json_info = Redis::hGetAll($token_key);
        }
       
        $_REQUEST['user_info'] = $json_info;
        return $next($request);//进行下一步(即传递给控制器)
    }
}

