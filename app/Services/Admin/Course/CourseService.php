<?php


namespace App\Services\Admin\Course;


use App\Models\Coures;
use App\Models\Coureschapters;
use App\Models\Couresteacher;
use App\Models\CourseLivesResource;
use App\Models\CourseRefBank;
use App\Models\CourseRefResource;
use App\Models\CourseRefSubject;
use App\Models\CourseRefTeacher;
use App\Models\CourseSchool;
use App\Models\QuestionBank;
use App\Models\School;
use Illuminate\Support\Facades\DB;

class CourseService
{

    public function courseEmpowerCron()
    {
        $maxSchoolId = 1;
        //课程授权
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
                $return = self::courseEmpowerUpdateBySchoolId($schoolInfo['id']);
                echo '网校id：' . $schoolInfo['id'] . '处理完成,结果：' . $return;
                echo PHP_EOL;
            }
        }

    }


    public static function courseEmpowerUpdateBySchoolId($schoolId)
    {
        $bankids = [];
        $fromSchoolId = 1; //当前登录的id
        $toSchoolId = $schoolId; //一对一的做法
        $userId = 0; //当前登录的用户id

        $courseIds = CourseSchool::query()
            ->where(['from_school_id'=>$fromSchoolId,'to_school_id'=>$toSchoolId,'is_del'=>0])
            ->pluck('course_id')
            ->toArray();//已经授权过的课程id

        if(!empty($courseIds)) {

            $courseArr = Coures::query()
                ->whereIn('id',$courseIds)
                ->select('id','parent_id','child_id')
                ->get()
                ->toArray();

            if(empty($courseArr)){
                return false;
            }

            foreach ($courseArr as $k => $vc) {
                $updateSchoolCourseSubjectArr[$k]['parent_id'] = $vc['parent_id'];
                $updateSchoolCourseSubjectArr[$k]['child_id'] = $vc['child_id'];
                $updateSchoolCourseSubjectArr[$k]['course_id'] = $vc['id'];
                $courseSubjectArr[$k]['parent_id'] = $vc['parent_id'];
                $courseSubjectArr[$k]['child_id'] = $vc['child_id'];
            }
            $courseSubjectArr = array_unique($courseSubjectArr,SORT_REGULAR);

            $ids = Couresteacher::query()
                ->whereIn('course_id',$courseIds)
                ->where('is_del',0)
                ->pluck('teacher_id')
                ->toArray(); //要授权的教师信息

            if(!empty($ids)){

                $ids = array_unique($ids);
                $teacherIds = CourseRefTeacher::query()
                    ->where(['from_school_id'=>$fromSchoolId,'to_school_id'=>$toSchoolId,'is_del'=>0])
                    ->pluck('teacher_id')
                    ->toArray();//已经授权过的讲师信息

                if(!empty($teacherIds)){
                    $teacherIdArr = array_diff($ids,$teacherIds);//不在授权讲师表里的数据
                }else{
                    $teacherIdArr = $ids;
                }
                if(!empty($teacherIdArr)){
                    foreach($teacherIdArr as $key => $id){
                        $InsertTeacherRef[$key]['from_school_id'] =$fromSchoolId;
                        $InsertTeacherRef[$key]['to_school_id'] =$toSchoolId;
                        $InsertTeacherRef[$key]['teacher_id'] =$id;
                        $InsertTeacherRef[$key]['is_public'] =0;
                        $InsertTeacherRef[$key]['admin_id'] = $userId;
                        $InsertTeacherRef[$key]['create_at'] = date('Y-m-d H:i:s');
                    }
                }
            }

            //题库
            foreach($courseSubjectArr as $key=>&$vs){
                $bankIdArr = QuestionBank::query()
                    ->where(['parent_id'=>$vs['parent_id'],'child_id'=>$vs['child_id'],'is_del'=>0,'school_id'=>$fromSchoolId])
                    ->pluck('id')
                    ->toArray();
                if(!empty($bankIdArr)){
                    foreach($bankIdArr as $k=>$vb){
                        array_push($bankids,$vb);
                    }
                }
            }

            if(!empty($bankids)){
                $bankids = array_unique($bankids);
                $natureQuestionBank = CourseRefBank::query()
                    ->where(['from_school_id'=>$fromSchoolId,'to_school_id'=>$toSchoolId,'is_del'=>0])
                    ->pluck('bank_id')
                    ->toArray();
                $bankids = array_diff($bankids,$natureQuestionBank);
                foreach($bankids as $key=>$bankid){
                    $InsertQuestionArr[$key]['bank_id'] =$bankid;
                    $InsertQuestionArr[$key]['from_school_id'] = $fromSchoolId;
                    $InsertQuestionArr[$key]['to_school_id'] = $toSchoolId;
                    $InsertQuestionArr[$key]['admin_id'] = $userId;
                    $InsertQuestionArr[$key]['create_at'] = date('Y-m-d H:i:s');
                }
            }
            //学科
            $subjectArr = CourseRefSubject::query()
                ->where(['to_school_id'=>$toSchoolId,'from_school_id'=>$fromSchoolId,'is_del'=>0])
                ->select('parent_id','child_id')
                ->get()
                ->toArray();  //已经授权过的学科

            if(!empty($subjectArr)){
                foreach($courseSubjectArr as $k=>$v){
                    foreach($subjectArr as $kk=>$bv){
                        if($v == $bv){
                            unset($courseSubjectArr[$k]);
                        }
                    }
                }
            }

            foreach($courseSubjectArr as $key=>$v){
                $InsertSubjectRef[$key]['is_public'] = 0;
                $InsertSubjectRef[$key]['parent_id'] = $v['parent_id'];
                $InsertSubjectRef[$key]['child_id'] = $v['child_id'];
                $InsertSubjectRef[$key]['from_school_id'] = $fromSchoolId;
                $InsertSubjectRef[$key]['to_school_id'] = $toSchoolId;
                $InsertSubjectRef[$key]['admin_id'] = $userId;
                $InsertSubjectRef[$key]['create_at'] = date('Y-m-d H:i:s');
            }
            //录播资源授权更新未成功！
            $recordVideoIds = Coureschapters::query()
                ->whereIn('course_id',$courseIds)->where(['is_del'=>0])
                ->pluck('resource_id as id')
                ->toArray(); //要授权的录播资源

            if(!empty($recordVideoIds)){
                $narturecordVideoIds = CourseRefResource::query()
                    ->where(['from_school_id'=>$fromSchoolId,'to_school_id'=>$toSchoolId,'type'=>0,'is_del'=>0])
                    ->pluck('resource_id as id ')
                    ->toArray(); //已经授权过的录播资源
                $recordVideoIds = array_diff($recordVideoIds,$narturecordVideoIds);
                foreach ($recordVideoIds as $key => $v) {
                    $InsertRecordVideoArr[$key]['resource_id']=$v;
                    $InsertRecordVideoArr[$key]['from_school_id'] = $fromSchoolId;
                    $InsertRecordVideoArr[$key]['to_school_id'] = $toSchoolId;
                    $InsertRecordVideoArr[$key]['admin_id'] = $userId;
                    $InsertRecordVideoArr[$key]['type'] = 0;
                    $InsertRecordVideoArr[$key]['create_at'] = date('Y-m-d H:i:s');
                }
            }

            //直播资源
            $zhiboVideoIds = CourseLivesResource::query()
                ->whereIn('course_id',$courseIds)
                ->where(['is_del'=>0])
                ->pluck('id')
                ->toArray();//要授权的直播资源
            if(!empty($zhiboVideoIds)){
                $narturezhiboVideoIds = CourseRefResource::query()
                    ->where(['from_school_id'=>$fromSchoolId,'to_school_id'=>$toSchoolId,'type'=>1,'is_del'=>0])
                    ->pluck('resource_id as id ')
                    ->toArray();
                $zhiboVideoIds = array_diff($zhiboVideoIds,$narturezhiboVideoIds);
                foreach ($zhiboVideoIds as $key => $v) {
                    $InsertZhiboVideoArr[$key]['resource_id']=$v;
                    $InsertZhiboVideoArr[$key]['from_school_id'] = $fromSchoolId;
                    $InsertZhiboVideoArr[$key]['to_school_id'] = $toSchoolId;
                    $InsertZhiboVideoArr[$key]['admin_id'] = $userId;
                    $InsertZhiboVideoArr[$key]['type'] = 1;
                    $InsertZhiboVideoArr[$key]['create_at'] = date('Y-m-d H:i:s');
                }
            }

            DB::beginTransaction();
            try{
                if(!empty($InsertTeacherRef)){
                    $teacherRes = CourseRefTeacher::insert($InsertTeacherRef);//教师
                    if(!$teacherRes){
                        DB::rollback();
                        return false;
                    }
                }
                if (!empty($InsertSubjectRef)) {
                    $subjectRes = CourseRefSubject::insert($InsertSubjectRef);//学科
                    if(!$subjectRes){
                        DB::rollback();
                        return false;
                    }
                }

                if(!empty($InsertRecordVideoArr)){

                    $InsertRecordVideoArr = array_chunk($InsertRecordVideoArr,500);
                    foreach($InsertRecordVideoArr as $key=>$lvbo){
                        $recordRes = CourseRefResource::insert($lvbo); //录播
                        if (!$recordRes) {
                            DB::rollback();
                            return false;
                        }
                    }
                }

                if(!empty($InsertZhiboVideoArr)){
                    $InsertZhiboVideoArr = array_chunk($InsertZhiboVideoArr,500);
                    foreach($InsertZhiboVideoArr as $key=>$zhibo){
                        $zhiboRes = CourseRefResource::insert($zhibo); //直播
                        if(!$zhiboRes){
                            DB::rollback();
                            return false;
                        }
                    }
                }
                if(!empty($InsertQuestionArr)){
                    $bankRes = CourseRefBank::insert($InsertQuestionArr); //题库
                    if(!$bankRes){
                        DB::rollback();
                        return false;
                    }
                }

                $updateSchoolCourseSubjectArr=array_unique($updateSchoolCourseSubjectArr,SORT_REGULAR);

                foreach($updateSchoolCourseSubjectArr as $key=>$courses){

                    $where = ['from_school_id'=>$fromSchoolId,'to_school_id'=>$toSchoolId,'course_id'=>$courses['course_id']];
                    $update = ['parent_id'=>$courses['parent_id'],'child_id'=>$courses['child_id'],'update_at'=>date('Y-m-d H:i:s'),'admin_id'=>$userId];
                    $courseRes = CourseSchool::query()->where($where)->update($update);
                    if(!$courseRes){
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
        // }
        return true;
    }


}

