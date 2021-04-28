<?php

namespace App\Http\Middleware\Admin;
use App\Models\Admin;
use App\Models\School;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SCookie;
use App\Tools\CurrentAdmin;

class AdminUserAuth {
    public function handle($request, Closure $next){
        $schoolIds = $request->get('schoolIds');
        $user = CurrentAdmin::user();
        switch($user['school_id']){
            case 1:
                $admin = Admin::where(['school_id'=>$user['school_id'],'is_del'=>1,'is_forbid'=>1])->pluck('id')->toArray();
                if(!empty($request->input('id')) && !in_array($request->input('id'),$schoolIds)){
                   return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
                }
                // if(!empty($request->input('school_id')) && !in_array($request->input('school_id'),$schoolIds)){
                //     return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
                // }
            break;
            default:

            break;
        }
        return $next($request);
    }
}
