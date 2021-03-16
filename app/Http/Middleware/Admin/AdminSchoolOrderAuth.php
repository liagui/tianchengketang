<?php

namespace App\Http\Middleware\Admin;
use App\Models\User;
use App\Models\School;
use App\Models\SchoolOrder;
use App\Models\AdminManageSchool;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SCookie;
use App\Tools\CurrentAdmin;

class AdminSchoolOrderAuth {
    public function handle($request, Closure $next){
        $schoolIds = $request->get('schoolIds');
        if(empty($schoolIds)){
             return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
        }
        $schoolOrderIds = SchoolOrder::whereIn('school_id',$schoolIds)->pluck('id')->toArray();
        if(!empty($request->input('id')) && !in_array($request->input('id'),$schoolOrderIds)){
            return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
        }
        return $next($request);
    }
}
