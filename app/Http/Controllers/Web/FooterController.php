<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Coures;
use App\Models\Coureschapters;
use App\Models\Couresmethod;
use App\Models\CouresSubject;
use App\Models\Couresteacher;
use App\Models\CourseLiveResource;
use App\Models\CourseRefResource;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Video;
use App\Models\FootConfig;
class FooterController extends Controller {
    protected $school;
    protected $data;

    public function __construct(){
        $this->data = $_REQUEST;
        //$this->school = School::where(['dns'=>$this->data['school_dns']])->first();//改前
        $this->school = $this->getWebSchoolInfo($this->data['school_dns']); //改后
    }
    //详情
    public function details(){
    	if(!isset($this->data['parent_id']) || $this->data['parent_id']<=0){ //
    		return response()->json(['code'=>201,'msg'=>'父级标识为空或数据不合法']);
    	}
    	if(!isset($this->data['id']) || $this->data['id']<=0){
    		return response()->json(['code'=>201,'msg'=>'id为空或数据不合法']);
    	}
        if(!isset($this->data['type']) || $this->data['type']<=0){
            return response()->json(['code'=>201,'msg'=>'类型为空或数据不合法']);
        }

        if($this->data['type'] == 2){
            $left_navigation_bar = FootConfig::where(['parent_id'=>$this->data['parent_id'],'is_del'=>0,'is_open'=>0])->get();
            $data = FootConfig::where(['id'=>$this->data['id'],'is_del'=>0,'is_open'=>0])->select('text')->first();
            if($data['name'] == '对公账户'){
                $data['company'] = $this->school;
            }
            if($data['name'] == '名师简介'){
                    $teacherArr = Teacher::where(['school_id'=>$this->school['id'],'is_del'=>0,'is_forbid'=>0,'type'=>2])->select('id','head_icon','real_name','describe','number','is_recommend')->orderBy('number','desc')->get()->toArray();

                    $natureTeacherArr = CourseRefTeacher::leftJoin('ld_lectuer_educationa','ld_lectuer_educationa.id','=','ld_course_ref_teacher.teacher_id')
                                        ->where(['ld_lectuer_educationa.type'=>2,'ld_course_ref_teacher.is_del'=>0,'ld_course_ref_teacher.to_school_id'=>$this->school['id']])
                                        ->select('ld_lectuer_educationa.id','ld_lectuer_educationa.head_icon','ld_lectuer_educationa.real_name','ld_lectuer_educationa.describe','ld_lectuer_educationa.number','ld_lectuer_educationa.is_recommend')->get()->toArray();
                    $data['teacher'] = array_unique(array_merge($teacherArr,$natureTeacherArr),SORT_REGULAR);
            }
            return response()->json(['code'=>200,'msg'=>'Success','data'=>$data,'left_list'=>$left_navigation_bar]);
        }
    }


}
