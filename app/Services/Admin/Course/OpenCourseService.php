<?php


namespace App\Services\Admin\Course;

use App\Models\CourseRefOpen;
use App\Models\CourseRefSubject;
use App\Models\CourseRefTeacher;
use App\Models\OpenCourse;
use App\Models\OpenCourseTeacher;
use App\Models\School;
use Illuminate\Support\Facades\DB;

class OpenCourseService
{

    public function openCourseEmpowerCron()
    {
        $maxSchoolId = 1;
        //@todo 课程授权
        $where = [
            'is_del' => 1,
            'is_forbid' => 1,
        ];
        while (true) {
            $schoolList = School::query()->where($where)
                ->where('id', '>', $maxSchoolId)
                ->orderBy('id', 'asc')
                ->limit(100)
                ->get()
                ->toArray();
            if (empty($schoolList)) {
                break;
            }

            foreach ($schoolList as $schoolInfo) {
                $maxSchoolId = $schoolInfo['id'];
                $return = self::openCourseEmpowerUpdateBySchoolId($schoolInfo['id']);
                echo '网校id：' . $schoolInfo['id'] . '处理完成,结果：' . $return;
                echo PHP_EOL;
            }
        }


    }

    public static function openCourseEmpowerUpdateBySchoolId($schoolId)
    {

        $fromSchoolId = 1; //当前登录的id
        $toSchoolId = $schoolId; //一对一的做法
        $userId = 0; //当前登录的用户id

        // foreach ($schoolIds as $k => $toSchoolId) {  //一对多的做法
        $courseIds = CourseRefOpen::query()
            ->where(['from_school_id'=>$fromSchoolId,'to_school_id'=>$toSchoolId,'is_del'=>0])
            ->pluck('course_id')
            ->toArray();
        if (!empty($courseIds)) {

            $ids = OpenCourseTeacher::query()
                ->whereIn('course_id',$courseIds)
                ->where('is_del',0)
                ->pluck('teacher_id')
                ->toArray(); //要授权的教师信息

            if(!empty($ids)){
                $ids = array_unique($ids);
                $teacherIds = CourseRefTeacher::query()
                    ->where(['from_school_id'=>$fromSchoolId,'to_school_id'=>$toSchoolId,'is_del'=>1])
                    ->pluck('teacher_id')
                    ->toArray();//已经授权过的讲师信息
                if(!empty($teacherIds)){
                    $teacherIdArr = array_diff($ids,$teacherIds);//不在授权讲师表里的数据
                }else{
                    $teacherIdArr = $ids;
                }

                if(! empty($teacherIdArr)){
                    foreach($teacherIdArr as $key => $id){
                        $InsertTeacherRef[$key]['from_school_id'] =$fromSchoolId;
                        $InsertTeacherRef[$key]['to_school_id'] =$toSchoolId;
                        $InsertTeacherRef[$key]['teacher_id'] =$id;
                        $InsertTeacherRef[$key]['is_public'] =1;
                        $InsertTeacherRef[$key]['admin_id'] = $userId;
                        $InsertTeacherRef[$key]['create_at'] = date('Y-m-d H:i:s');
                    }
                }
            }

            $natureSubject = OpenCourse::query()
                ->where(function($query) use ($fromSchoolId) {
                    $query->where('status',1);
                    $query->where('school_id',$fromSchoolId);
                    $query->where('is_del',0);
                })->select('parent_id','child_id')
                ->get()
                ->toArray(); //要授权的学科信息
            $natureSubject = array_unique($natureSubject,SORT_REGULAR);
            $subjectArr = CourseRefSubject::query()
                ->where(['to_school_id'=>$toSchoolId,'from_school_id'=>$fromSchoolId,'is_public'=>1,'is_del'=>0])
                ->select('parent_id','child_id')
                ->get();//已经授权的学科信息
            if (! empty($subjectArr)) {
                foreach($natureSubject as $k=>$v){
                    foreach($subjectArr as $kk=>$bv){
                        if($v == $bv){
                            unset($natureSubject[$k]);
                        }
                    }
                }
            }

            if(!empty($natureSubject)){
                foreach($natureSubject as $key=>&$vs){
                    $vs['is_public'] = 1;
                    $vs['from_school_id'] =$fromSchoolId;
                    $vs['to_school_id'] =$toSchoolId;
                    $vs['admin_id'] =$userId;
                    $vs['create_at'] =date('Y-m-d H:i:s');
                }
            }

            DB::beginTransaction();
            try{

                if(!empty($InsertTeacherRef)){
                    $teacherRes = CourseRefTeacher::query()
                        ->insert($InsertTeacherRef);
                    if(!$teacherRes){
                        DB::rollback();
                        return false;
                    }
                }

                if(!empty($natureSubject)){
                    $subjectRes = CourseRefSubject::insert($natureSubject);
                    if(!$subjectRes){
                        DB::rollback();
                        return false;
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
                return false;
            }
        }
        return true;
    }

}

