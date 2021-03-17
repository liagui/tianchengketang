<?php

namespace App\Http\Middleware\Admin;
use App\Models\User;
use App\Models\School;
use App\Models\AdminManageSchool;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SCookie;
use App\Tools\CurrentAdmin;

class AdminSchoolAuth {
    public function handle($request, Closure $next){
        $user = CurrentAdmin::user();
        if($user['school_status']  == 1){
            //总控
            switch($user['role_id']){
                case 1: //超级管理员角色
                    $schoolIds = School::where(['is_del'=>1,'is_forbid'=>1])->pluck('id')->toArray();
                    if(!empty($request->input('schoolid')) && !in_array($request->input('schoolid'),$schoolIds)){
                       return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
                    }
                    if(!empty($request->input('school_id')) && !in_array($request->input('school_id'),$schoolIds)){
                        return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
                    }
                break;
                default: //非超级管理员角色
                    $schoolIds = AdminManageSchool::where(['is_del'=>0])->pluck('id')->toArray();
                    if(!empty($request->input('schoolid')) && !in_array($request->input('schoolid'),$schoolIds)){
                       return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
                    }
                    if(!empty($request->input('school_id')) && !in_array($request->input('school_id'),$schoolIds)){
                        return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
                    }
                break;
            }
        }else if($user['school_status']  == 0){
            //中控id
            $schoolIds = School::where(['id'=>$user['school_id'],'is_del'=>1,'is_forbid'=>1])->pluck('id')->toArray();
            if(!empty($request->input('schoolid')) && !in_array($request->input('schoolid'),$schoolIds)){
               return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
            }
            if(!empty($request->input('school_id')) && !in_array($request->input('school_id'),$schoolIds)){
                return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
            }
        }
		$schoolIds = ['schoolIds'=>$schoolIds];
		$request->attributes->add($schoolIds);
        return $next($request);
    }
}
