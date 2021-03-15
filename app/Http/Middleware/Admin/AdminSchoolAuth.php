<?php

namespace App\Http\Middleware\Admin;
use App\Models\User;
use App\Models\School;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SCookie;
use App\Tools\CurrentAdmin;

class AdminSchoolAuth {
    public function handle($request, Closure $next){
        $user = CurrentAdmin::user();
        switch($user['role_id']){
            case 1:
                $schoolIds = School::where(['is_del'=>1,'is_forbid'=>1])->pluck('id')->toArray();
                if(!empty($request->input('schoolid')) && !in_array($request->input('schoolid'),$schoolIds)){
                   return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
                }
                if(!empty($request->input('school_id')) && !in_array($request->input('school_id'),$schoolIds)){
                    return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
                }
            break;
            default:

            break;
            // case 1: break;
            // case 1: break;
            // case 1: break;

        }
        return $next($request);
    }
}
