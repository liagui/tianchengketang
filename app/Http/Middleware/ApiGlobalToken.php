<?php

namespace App\Http\Middleware;
use App\Models\Adminuser;
use App\Models\Role;
use App\Models\Admin;
use App\Models\School;
use App\Services\Admin\Role\RoleService;
use App\Services\Admin\Rule\RuleService;
use Closure;
use App\Tools\CurrentAdmin;

class ApiGlobalToken {
    public function handle($request, Closure $next){
        //获取token值
        $token = $request->input('token_iden');
        //判断token是否为空
        if(!$token || empty($token)){
            return response()->json(['code'=>403,'msg'=>'非法请求！！！']);
        } else {
            $tokenstr = ENV(API_TOKEN);
            $token_str = base64_decode($token);
            $token_key   = "user:regtoken:".$platform.":".$token;

            //解析json获取用户详情信息
            $json_info = Redis::hGetAll($token_key);
        }
        return $next($request);//进行下一步(即传递给控制器)
    }

}
