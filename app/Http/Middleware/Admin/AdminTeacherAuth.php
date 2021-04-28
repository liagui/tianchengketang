<?php

namespace App\Http\Middleware\Admin;
use App\Models\User;
use App\Models\School;
use App\Models\Teacher;
use App\Models\AdminManageSchool;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SCookie;
use App\Tools\CurrentAdmin;
use App\Models\CourseRefTeacher;

class AdminTeacherAuth {
    public function handle($request, Closure $next){
        $user = CurrentAdmin::user();
        //总控 只有自增讲师
        $selfAddTacherIds = Teacher::where('school_id',$user['school_id'])->pluck('id')->toArray(); //自增的讲师

        if($user['school_id'] != 1){
           //授权讲师
           $refTeacherIds = CourseRefTeacher::where('to_school_id',$user['school_id'])->pluck('teacher_id')->toArray(); //授权的讲师
           $selfAddTacherIds = array_merge($selfAddTacherIds,$refTeacherIds);
        }
        if(!empty($request->input('teacher_id')) && !in_array($request->input('teacher_id'),$selfAddTacherIds)){
            return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
        }
        return $next($request);
    }
}
