<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Coures;
use App\Models\Couresmethod;
use App\Models\CourseSchool;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Order;
use App\Models\CourseRefTeacher;
use App\Models\WebLog;

class TeacherController extends Controller {
	protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['dns']])->first(); //改前
       // $this->school = $this->getWebSchoolInfo($this->data['school_dns']); //改后
       $this->userid = isset($_REQUEST['user_info']['user_id'])?$_REQUEST['user_info']['user_id']:0;
       print_r($this->userid);die;
    }
    //列表
	public function getList(){
		$type = !isset($this->data['type']) || $this->data['type']<=0 ?0:$this->data['type'];
        $teacherArr = Teacher::where(['school_id'=>$this->school['id'],'is_del'=>0,'is_forbid'=>0,'type'=>2])->select('id','head_icon','real_name','describe','number','is_recommend','teacher_icon','is_forbid')->orderBy('number','desc')->get()->toArray(); //自增讲师
		$natureTeacherArr = CourseRefTeacher::leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_ref_teacher.teacher_id')
							->where(['ld_course_ref_teacher.to_school_id'=>$this->school['id'],'ld_course_ref_teacher.is_del'=>0,'ld_lecturer_educationa.type'=>2,'ld_lecturer_educationa.is_forbid'=>0])
							->select('ld_lecturer_educationa.id','ld_lecturer_educationa.head_icon','ld_lecturer_educationa.real_name','ld_lecturer_educationa.describe','ld_lecturer_educationa.number','ld_lecturer_educationa.is_recommend','ld_lecturer_educationa.teacher_icon','ld_lecturer_educationa.is_forbid')->get()->toArray();//授权讲师
		if(!empty($natureTeacherArr)){
			foreach($natureTeacherArr as $key=>&$v){
				$natureCourseArr =  CourseSchool::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course_school.course_id')
						->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
						->where(['ld_course_school.is_del'=>0,'ld_course_school.to_school_id'=>$this->school['id'],'ld_course_school.status'=>1,'ld_lecturer_educationa.id'=>$v['id'],'ld_lecturer_educationa.is_forbid'=>0])
						->select('ld_course_school.id as course_id ','ld_course_school.cover','ld_course_school.title','ld_course_school.pricing','ld_course_school.buy_num','ld_lecturer_educationa.id','ld_lecturer_educationa.is_forbid')
						->get()->toArray();
				$courseIds = array_column($natureCourseArr, 'course_id');
				$v['number'] = count($natureCourseArr);//开课数量
				$sumNatureCourseArr = array_sum(array_column($natureCourseArr,'buy_num'));//虚拟购买量
				$realityBuyum= Order::whereIn('class_id',$courseIds)->where(['school_id'=>$this->school['id'],'nature'=>1,'status'=>2])->whereIn('pay_status',[3,4])->count();//实际购买量 （授权课程订单class_id 对应的是ld_course_school 的id ）
				$v['student_number'] = $sumNatureCourseArr+$realityBuyum;
				$v['grade'] =  '5.0';
				$v['star_num'] = 5;
				$v['is_nature'] = 1;
			}
        }
		if(!empty($teacherArr)){
			foreach($teacherArr as $key=>&$vv){
				$couresArr  = Coures::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course.id')
					->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
					->where(['ld_course.is_del'=>0,'ld_course.school_id'=>$this->school['id'],'ld_course.status'=>1,'ld_lecturer_educationa.id'=>$vv['id'],'ld_lecturer_educationa.is_forbid'=>0])
					->select('ld_course.cover','ld_course.title','ld_course.pricing','ld_course.buy_num','ld_lecturer_educationa.id','ld_course.id as course_id','ld_lecturer_educationa.is_forbid')
					->get()->toArray();

				$courseIds = array_column($couresArr, 'course_id');
				$vv['number'] = count($couresArr);//开课数量
				$sumNatureCourseArr = array_sum(array_column($couresArr,'buy_num'));//虚拟购买量
				$realityBuyum = Order::whereIn('class_id',$courseIds)->where(['school_id'=>$this->school['id'],'nature'=>0,'status'=>2])->whereIn('pay_status',[3,4])->count();//实际购买量
				$vv['student_number'] = $sumNatureCourseArr+$realityBuyum;
				$vv['grade'] =  '5.0';
				$vv['star_num'] = 5;
				$vv['is_nature'] = 0;
			}
        }
		if(!empty($natureTeacherArr) || !empty($teacherArr)){
			$teacherData = array_merge($natureTeacherArr,$teacherArr);
			if( $type==1 ){
				 $sort = array_column($teacherData, 'student_number');
       			 array_multisort($sort, SORT_DESC, $teacherData);
			}
			$teacherData = array_unique($teacherData, SORT_REGULAR);
		}else{
			$teacherData=[];
		}
        $teacherData = array_merge($teacherData);//重置索引, 使下方for取到正确的数据,2020/11/26 zhaolaoxian
		$pagesize = isset($this->data['pagesize']) && $this->data['pagesize'] > 0 ? $this->data['pagesize'] : 20;
        $page     = isset($this->data['page']) && $this->data['page'] > 0 ? $this->data['page'] : 1;
		$start=($page-1)*$pagesize;
        $limit_s=$start+$pagesize;
        $info=[];
        for($i=$start;$i<$limit_s;$i++){
            if(!empty($teacherData[$i])){
                array_push($info,$teacherData[$i]);
            }
        }
        //添加日志操作
        WebLog::insertWebLog([
            'school_id'      => $this->school->id,
            'admin_id'       =>  $this->userid ,
            'module_name'    =>  'Teacher' ,
            'route_url'      =>  'web/teacher/List' ,
            'operate_method' =>  'select' ,
            'content'        =>  '名师列表',
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
		return response()->json(['code'=>200,'msg'=>'Succes','data'=>$info,'total'=>count($teacherData)]);

	}
	//详情页
	public function dateils(){

		if(!isset($this->data['teacher_id']) || empty($this->data['teacher_id']) || $this->data['teacher_id'] < 0 ){
			return response()->json(['code'=>201,'msg'=>'教师标识为空或类型不合法']);
		}

		if(!isset($this->data['is_nature']) && $this->data['is_nature']<0 ){
			return response()->json(['code'=>201,'msg'=>'类型标识为空或类型不合法']);
		}
		$teacherInfo['teacher'] = Teacher::where(['id'=>$this->data['teacher_id']])->first();
		$teacherInfo['star'] = 5;//星数
		$teacherInfo['grade'] = '5.0';//评分
		$teacherInfo['evaluate'] = 0; //评论数
		$teacherInfo['class_number']= 0;//开课数量
		$teacherInfo['student_num']= 0;//学员数量
		$teacherInfo['comment'] = [];//评论
		$teacherInfo['course'] = [];//课程信息
		if($this->data['is_nature'] == 1){
			//授权讲师
			$arr= [];
			$data = CourseSchool::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course_school.course_id')
						->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
						->where(['ld_course_school.is_del'=>0,'ld_course_school.to_school_id'=>$this->school['id'],'ld_course_school.status'=>1,'ld_lecturer_educationa.id'=>$this->data['teacher_id']])
						->select('ld_course_school.cover','ld_course_school.title','ld_course_school.pricing','ld_course_school.buy_num','ld_lecturer_educationa.id as teacher_id','ld_course_school.course_id','ld_course_school.sale_price','ld_course_school.id')
						->get()->toArray();
			if(!empty($data)){
				foreach ($data as $k => &$nature) {
					$nature['nature'] = 1;
				}
			}
		}else{
			//自增讲师
			$data =Coures::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course.id')
						->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
						->where(['ld_course.is_del'=>0,'ld_course.school_id'=>$this->school['id'],'ld_course.status'=>1,'ld_lecturer_educationa.id'=>$this->data['teacher_id']])
						->select('ld_course.cover','ld_course.title','ld_course.pricing','ld_course.buy_num','ld_lecturer_educationa.id as teacher_id','ld_course.id','ld_course.sale_price','ld_course.id as course_id')
						->get()->toArray();
			if(!empty($data)){
				foreach ($data as $k => &$zizeng) {
					$zizeng['nature'] = 0;
				}
			}
		}
		if(!empty($data)){
			foreach($data as $key=>$v){

				if($v['teacher_id'] == $this->data['teacher_id']){
					$arr[] = $v;
				}
			}
			if(!empty($arr)){
				$teacherInfo['class_number'] = count($arr);//开课数量
				$sum =0;
				foreach($arr as $k=>&$v){
					$where=[
                        'course_id'=>$v['course_id'],
                        'is_del'=>0
                    ];
                    $method = Couresmethod::select('method_id')->where($where)->get()->toArray();
                    if(empty($method)){
                        unset($arr[$k]);
                    }else{
                        foreach ($method as $key=>&$val){
                            if($val['method_id'] == 1){
                                $val['method_name'] = '直播';
                            }
                            if($val['method_id'] == 2){
                                $val['method_name'] = '录播';
                            }
                            if($val['method_id'] == 3){
                                $val['method_name'] = '其他';
                            }
                        }
                        $v['method'] = $method;
                    }
					$v['buy_num'] += Order::where(['school_id'=>$this->school['id'],'nature'=>1,'status'=>2,'class_id'=>$v['course_id']])->whereIn('pay_status',[3,4])->count();
					$sum+=$v['buy_num'];
				}
				$teacherInfo['student_num'] = $sum;//学员数量
				$teacherInfo['course'] = $arr;
			}
		}
        //添加日志操作
        WebLog::insertWebLog([
            'school_id'      => $this->school->id,
            'admin_id'       =>  $this->userid ,
            'module_name'    =>  'Teacher' ,
            'route_url'      =>  'web/teacher/List' ,
            'operate_method' =>  'select' ,
            'content'        =>  '名师详情'.['teacher_id'=>$this->data['teacher_id'],'is_nature'=>$this->data['is_nature']],
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
		return ['code'=>200,'msg'=>'Success','data'=>$teacherInfo];
	}
    //列表
    public function getListByIndexSet(){

        $topNum = empty($this->data['top_num']) ? 1 : $this->data['top_num'];
        $isRecommend = isset($this->data['is_recommend']) ? $this->data['is_recommend'] : 1;
        $schoolId = $this->school->id;
        $limit = $topNum;

        $courseRefTeacherQuery = CourseRefTeacher::query()
            ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_ref_teacher.teacher_id')
            ->where([
                'ld_course_ref_teacher.to_school_id' => $schoolId,
                'ld_lecturer_educationa.type' => 2,
                'ld_course_ref_teacher.is_del' => 0,
            ])
            ->select(
                'ld_lecturer_educationa.id','ld_lecturer_educationa.head_icon',
                'ld_lecturer_educationa.real_name','ld_lecturer_educationa.describe',
                'ld_lecturer_educationa.number','ld_lecturer_educationa.teacher_icon','ld_lecturer_educationa.is_recommend'
            );
        //授权讲师
        if ($isRecommend == 1) {
            $courseRefTeacherQuery->orderBy('ld_lecturer_educationa.is_recommend', 'desc');
        }
        $courseRefTeacher = $courseRefTeacherQuery->orderBy('ld_course_ref_teacher.id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
        //授权讲师

        $courseRefTeacher = array_unique($courseRefTeacher, SORT_REGULAR);
        $count = count($courseRefTeacher);
        if($count >0){ //授权讲师信息
            foreach($courseRefTeacher as $key=>&$teacher){
                $natureCourseArr =  CourseSchool::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course_school.course_id')
                    ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                    ->where(['ld_course_school.is_del'=>0,'ld_course_school.to_school_id'=>$schoolId,'ld_course_school.status'=>1,'ld_lecturer_educationa.id'=>$teacher['id']])
                    ->select('ld_course_school.cover','ld_course_school.title','ld_course_school.pricing','ld_course_school.buy_num','ld_lecturer_educationa.id as teacher_id','ld_course_school.id as course_id')
                    ->get()
                    ->toArray();
                $courseIds = array_column($natureCourseArr, 'course_id');
                $teacher['number'] = count($natureCourseArr);//开课数量
                $sumNatureCourseArr = array_sum(array_column($natureCourseArr,'buy_num'));//虚拟购买量
                $realityBuyum= Order::whereIn('class_id',$courseIds)->where(['school_id'=>$schoolId,'nature'=>1,'status'=>2])->whereIn('pay_status',[3,4])->count();//实际购买量（授权课程订单class_id 对应的是ld_course_school 的id ）
                $teacher['student_number'] = $sumNatureCourseArr+$realityBuyum;
                $teacher['is_nature'] = 1;
                $teacher['star_num']= 5;
            }
        }
        //自增讲师信息
        $teacherDataQuery = Teacher::query()
            ->where([
                'school_id' => $schoolId,
                'is_del' => 0,
                'type' => 2,
                'is_forbid' => 0
            ])
            ->select('id','head_icon','real_name','describe','number','teacher_icon', 'is_recommend');
        if ($isRecommend == 1) {
            $teacherDataQuery->orderBy('is_recommend', 'desc');
        }

        $teacherData = $teacherDataQuery->orderBy('number','desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
        $teacherDataCount = count($teacherData);
        if($teacherDataCount >0){
            foreach($teacherData as $key=>&$vv){
                $couresArr  = Coures::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course.id')
                    ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                    ->where(['ld_course.is_del'=>0,'ld_course.school_id'=>$schoolId,'ld_course.status'=>1,'ld_lecturer_educationa.id'=>$vv['id']])
                    ->select('ld_course.cover','ld_course.title','ld_course.pricing','ld_course.buy_num','ld_lecturer_educationa.id as teacher_id','ld_course.id as course_id')
                    ->get()->toArray();

                $courseIds = array_column($couresArr, 'course_id');
                $vv['number'] = count($couresArr);//开课数量
                $sumNatureCourseArr = array_sum(array_column($couresArr,'buy_num'));//虚拟购买量
                $realityBuyum = Order::whereIn('class_id',$courseIds)->where(['school_id'=>$schoolId,'nature'=>0,'status'=>2])->whereIn('pay_status',[3,4])->count();//实际购买量
                $vv['student_number'] = $sumNatureCourseArr+$realityBuyum;
                $vv['grade'] =  '5.0';
                $vv['star_num'] = 5;
                $vv['is_nature'] = 0;
            }
        }
        $recomendTeacherArr=array_merge($courseRefTeacher,$teacherData);
        if ($isRecommend == 1) {
            $isRecommendList = array_column($recomendTeacherArr, 'is_recommend');
            array_multisort($isRecommendList, SORT_DESC, $recomendTeacherArr);
        }

        return response()->json(['code'=>200,'msg'=>'Success','data'=>array_slice($recomendTeacherArr, 0, $limit)]);

    }
}
