<?php

namespace App\Http\Middleware;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SCookie;
use App\Models\Admin;

class AdminToken {
    public function handle($request, Closure $next){
        //获取用户token值
        $Authorization = $request->header('Authorization');
        $token = substr($Authorization,strlen('Bearer'));
        if($token == '' || $token == null){
            return response()->json(['code'=>403,'msg'=>'TOEKN缺失']);
        }

        $admintoken = Admin::where('token',$token)->select('token')->first();
        if(is_null($admintoken) || empty($admintoken) ){
            return response()->json(['code'=>403,'msg'=>'TOEKN无效']);
        }else{
             return $next($request);//进行下一步(即传递给控制器)
        }


    }
}
