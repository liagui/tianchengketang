<?php

namespace App\Http\Middleware;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SCookie;

class UserAuthToken {
    public function handle($request, Closure $next){
        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'pc';

        //判断是否设置了记住我的功能
        $user_phone    = Cookie::get('user_phone');
        $user_password = Cookie::get('user_password');
        if(!empty($user_phone) && !empty($user_password)){
            //hash中token赋值
            $token_key   = "user:regtoken:".$platform.":".$user_phone;
        } else {
            //获取用户token值
            $token = $request->input('user_token');

            //判断用户token是否为空
            if(!$token || empty($token)){
                return ['code' => 401 , 'msg' => '请登录账号'];
            }

            //hash中token赋值
            $token_key   = "user:regtoken:".$platform.":".$token;
        }

        //判断token值是否合法
        $redis_token = Redis::hLen($token_key);
        if($redis_token && $redis_token > 0) {
            //解析json获取用户详情信息
            $json_info = Redis::hGetAll($token_key);

            //判断是正常用户还是游客用户
            if($json_info['user_type'] && $json_info['user_type'] == 1){
                //根据手机号获取用户详情
                $user_info = User::where('school_id' , $json_info['school_id'])->where("phone" , $json_info['phone'])->first();
                if(!$user_info || empty($user_info)){
                    return ['code' => 401 , 'msg' => '请登录账号'];
                }

                //判断用户是否被禁用
                if($user_info['is_forbid'] == 2){
                    return response()->json(['code' => 207 , 'msg' => '账户已禁用']);
                }
            } else if($json_info['user_type'] && $json_info['user_type'] == 2){
                //通过device获取用户信息
                $user_info = User::select("id as user_id" , "is_forbid")->where("device" , $json_info['device'])->first();
                if(!$user_info || empty($user_info)){
                    return ['code' => 401 , 'msg' => '请登录账号'];
                }

                //判断用户是否被禁用
                if($user_info['is_forbid'] == 2){
                    return response()->json(['code' => 207 , 'msg' => '账户已禁用']);
                }
            }

        } else {
            return ['code' => 401 , 'msg' => '请登录账号'];
        }
        $json_info = ['json_info'=>$json_info];
        $request->attributes->add($json_info);
        $_REQUEST['user_info'] = $json_info;
        return $next($request);//进行下一步(即传递给控制器)
    }

    /**
     *   检查一下用户的登录状态
     *   从 Request 中获取到user_toke 检查他的状态是否正确 如果正确那么就像用户 登录一样
     *
     * @param $request
     * @return bool
     */
    public static function CheckCurrentByRequest(Request $request){
        // 模拟检查用户登录
        $UserAuthToken = new UserAuthToken();
        $ret =  $UserAuthToken ->handle($request, function ($request){
            //  如果检查成功那么 返回这个数组 详情请查阅 handle 的逻辑
            return [ 'code'=> 0, 'msg' => 'ok' ];
        });
        // 检查返回值
        if($ret['code'] == 0){
            $request ->merge($_REQUEST);
            return true;
        }else{
            return false;
        }
    }

}
