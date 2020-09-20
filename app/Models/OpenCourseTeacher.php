<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

use App\Models\Teacher;
use App\Models\Admin;
use App\Models\CouresSubject;
use App\Tools\CurrentAdmin;
class OpenCourseTeacher extends Model {
    //指定别的表名
    public $table = 'ld_course_open_teacher';
    //时间戳设置
    public $timestamps = false;

    //错误信息
    public static function message()
    {
        return [
            'openless_id.required'  => json_encode(['code'=>'201','msg'=>'公开课标识不能为空']),
            'openless_id.integer'   => json_encode(['code'=>'202','msg'=>'公开课标识类型不合法']),
            'page.required'  => json_encode(['code'=>'201','msg'=>'页码不能为空']),
            'page.integer'   => json_encode(['code'=>'202','msg'=>'页码类型不合法']),
            'limit.required' => json_encode(['code'=>'201','msg'=>'显示条数不能为空']),
            'limit.integer'  => json_encode(['code'=>'202','msg'=>'显示条数类型不合法']),
            'parent_id.required' => json_encode(['code'=>'201','msg'=>'一级学科标识不能为空']),
            'parent_id.integer'  => json_encode(['code'=>'202','msg'=>'一级学科标识不合法']),
            'child_id.required' => json_encode(['code'=>'201','msg'=>'二级学科标识不能为空']),
            'child_id.integer'  => json_encode(['code'=>'202','msg'=>'二级学科标识类型不合法']),
            'title.required' => json_encode(['code'=>'201','msg'=>'课程标题不能为空']),
            // 'name.unique' => json_encode(['code'=>'205','msg'=>'学校名称已存在']),
            'keywords.required' => json_encode(['code'=>'201','msg'=>'课程关键字不能为空']),
            'cover.required' => json_encode(['code'=>'201','msg'=>'课程封面不能为空']),
            'start_at.required' => json_encode(['code'=>'201','msg'=>'开始时间不能为空']),
            'end_at.required' => json_encode(['code'=>'201','msg'=>'结束时间不能为空']),
            'username.unique' => json_encode(['code'=>'205','msg'=>'账号已存在']),
            'is_barrage.required' => json_encode(['code'=>'201','msg'=>'弹幕ID不能为空']),
            'is_barrage.integer' => json_encode(['code'=>'202','msg'=>'弹幕ID不合法']),
            'live_type.required' => json_encode(['code'=>'201','msg'=>'直播类型不能为空']),
            'live_type.integer' => json_encode(['code'=>'202','msg'=>'直播类型不合法']),
            'edu_teacher_id.required'  => json_encode(['code'=>'201','msg'=>'教务标识不能为空']),
            'lect_teacher_id.required'  => json_encode(['code'=>'201','msg'=>'讲师标识不能为空']),
        ];
    }

    /*
         * @param  descriptsion 获取公开课信息
         * @param  $school_id  公开课id
         * @param  author       lys
         * @param  ctime   2020/4/29 
         * return  array
         */
    public static function getTeacherAll($where,$field = ['*']){
        $teacherInfo = self::where($where)->select($field)->get();
        if($teacherInfo){
            return ['code'=>200,'msg'=>'获取教师信息成功','data'=>$teacherInfo];
        }else{
            return ['code'=>204,'msg'=>'教师信息不存在'];
        }
    }



}
