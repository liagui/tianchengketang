<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use App\Models\AdminLog;
use App\Tools\MTCloud;
use Illuminate\Support\Facades\DB;

//教学模块Model
class Teach extends Model {

	//返回错误信息
	public static function message(){
		 return [
            'class_id.required'  => json_encode(['code'=>'201','msg'=>'课次id不能为空']),
            'class_id.integer'   => json_encode(['code'=>'202','msg'=>'课次id类型不合法']),
            'classno_id.required' => json_encode(['code'=>'201','msg'=>'班号id不能为空']),
            'classno_id.integer'  => json_encode(['code'=>'202','msg'=>'班号id类型不合法']),
            'is_public.required' => json_encode(['code'=>'201','msg'=>'是否公开课标识不能为空']),
            'id.required' =>        json_encode(['code'=>'201','msg'=>'id不能为空']),
            'file.required' =>       json_encode(['code'=>'201','msg'=>'课件不能为空']),
        ];
	}
	//教学列表
	public static function getList($body){
		$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前学校id
		$teacher_id = isset(AdminLog::getAdminInfo()->admin_user->teacher_id) ? AdminLog::getAdminInfo()->admin_user->teacher_id : 0; //当前学校id
		$teacher_type_arr  = Teacher::where(['id'=>$teacher_id,'school_id'=>$school_id,'is_del'=>0,'is_forbid'=>0])->select('type')->first();
        if(empty($teacher_type_arr)){
           $teacher_type_arr['type'] = '';
        }
		//公开课数据
		$openCourseArr = OpenCourse::rightJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_open.id')
						->rightJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_open.id')
						->rightJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_open_teacher.teacher_id')
						->where(function($query) use ($body,$school_id) {
						if(isset($body['time']) && !empty($body['time'])){
							switch ($body['time']) {
								case '1': //今天
									$query->where('ld_course_open.start_at','>',strtotime(date('Y-m-d')));
									$query->where('ld_course_open.end_at','<',strtotime(date('Y-m-d 23:59:59')));
									break;
								case '2': //明天
									$query->where('ld_course_open.start_at','>',strtotime(date("Y-m-d",strtotime("+1 day"))));
									$query->where('ld_course_open.end_at','<',strtotime(date("Y-m-d 23:59:59",strtotime("+1 day"))));
									break;
								case '3': //昨天
									$query->where('ld_course_open.start_at','>',strtotime(date("Y-m-d",strtotime("-1 day"))));
									$query->where('ld_course_open.end_at','<',strtotime(date("Y-m-d 23:59:59",strtotime("-1 day"))));
									break;
							}
						}
						if(isset($body['timerange']) && !empty($body['timerange'])){
							$time = json_decode($body['timerange'],1);
							if(!empty($time)){
								$query->where('ld_course_open.start_at','>',substr($time[0],0,10));
								$query->where('ld_course_open.end_at','<',substr($time[1],0,10));
							}
						}
						if(isset($body['status']) && !empty($body['status'])){
							switch ($body['status']) {
								case '2':
									$query->where('ld_course_open.start_at','>',time());
									break;
								case '1':
									$query->where('ld_course_open.start_at','<',time());
									$query->where('ld_course_open.end_at','>',time());
									break;
								case '3':
									$query->where('ld_course_open.end_at','<',time());
									break;
							}
						}
						if(isset($body['teacherSearch']) && !empty($body['teacherSearch'])){
							$query->where('ld_lecturer_educationa.real_name','like',"%".$body['teacherSearch']."%");
						}
						if(isset($body['classSearch']) && !empty($body['classSearch'])){
							$query->where('ld_course_open.title','like','%'.$body['classSearch'].'%');
						}
						$query->where('ld_course_open.nature',0);
						$query->where('ld_course_open.is_del',0);
						$query->where('ld_course_open.school_id',$school_id);
						$query->where('ld_lecturer_educationa.type',2);
					})->select('ld_course_open.title as class_name','ld_lecturer_educationa.real_name as teacher_name','ld_course_open.start_at','ld_course_open.end_at','ld_course_open_live_childs.watch_num','ld_course_open.id as class_id')
					->get()->toArray();
			if(isset($body['classNoSearch']) && !empty($body['classNoSearch'])){
				$openCourseArr = [];
			}
			$courseArr = [];
			//课程
			$resourceIds = Live::where(['school_id'=>$school_id,'is_del'=>0])->where('is_forbid','!=',2)->pluck('id')->toArray();

			if(!empty($resourceIds)){
				$courseArr = CourseShiftNo::rightJoin('ld_course_class_number','ld_course_class_number.shift_no_id','=','ld_course_shift_no.id')
					->rightJoin('ld_course_live_childs','ld_course_live_childs.class_id','=','ld_course_class_number.id')
					->rightJoin('ld_course_class_teacher','ld_course_class_number.id','=','ld_course_class_teacher.class_id')
					->rightJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_class_teacher.teacher_id')
					->where(function($query) use ($body,$school_id,$resourceIds) {
						if(isset($body['time']) && !empty($body['time'])){
							switch ($body['time']) {
								case '1': //今天
									$query->where('ld_course_class_number.start_at','>',strtotime(date('Y-m-d')));
									$query->where('ld_course_class_number.end_at','<',strtotime(date('Y-m-d 23:59:59')));
									break;
								case '2': //明天
									$query->where('ld_course_class_number.start_at','>',strtotime(date("Y-m-d",strtotime("+1 day"))));
									$query->where('ld_course_class_number.end_at','<',strtotime(date("Y-m-d 23:59:59",strtotime("+1 day"))));
									break;
								case '3': //昨天
									$query->where('ld_course_class_number.start_at','>',strtotime(date("Y-m-d",strtotime("-1 day"))));
									$query->where('ld_course_class_number.end_at','<',strtotime(date("Y-m-d 23:59:59",strtotime("-1 day"))));
									break;
							}
						}
						if(isset($body['timerange']) && !empty($body['timerange'])){
							$time = json_decode($body['timerange'],1);
							if(!empty($time)){
								$query->where('ld_course_class_number.start_at','>',substr($time[0],0,10));
								$query->where('ld_course_class_number.end_at','<',substr($time[1],0,10));
							}
						}
						if(isset($body['status']) && !empty($body['status'])){
							switch ($body['status']) {
								case '2':
									$query->where('ld_course_class_number.start_at','>',time());
									break;
								case '1':
									$query->where('ld_course_class_number.start_at','<',time());
									$query->where('ld_course_class_number.end_at','>',time());
									break;
								case '3':
									$query->where('ld_course_class_number.end_at','<',time());
									break;
							}
						}
						if(isset($body['teacherSearch']) && !empty($body['teacherSearch'])){
							$query->where('ld_lecturer_educationa.real_name','like',"%".$body['teacherSearch']."%");
						}
						if(isset($body['classSearch']) && !empty($body['classSearch'])){
							$query->where('ld_course_class_number.name','like','%'.$body['classSearch'].'%');
						}
						if(isset($body['classNoSearch']) && !empty($body['classNoSearch'])){ //
							$query->where('ld_course_shift_no.name','like','%'.$body['classNoSearch'].'%');
						}
						$query->where('ld_course_class_number.status',1);
						$query->where('ld_course_shift_no.is_del',0);
						$query->where('ld_course_shift_no.is_forbid',0);
						$query->where('ld_course_class_number.is_del',0);
						$query->whereIn('ld_course_shift_no.resource_id',$resourceIds);
						$query->where('ld_course_shift_no.school_id',$school_id);
						$query->where('ld_lecturer_educationa.type',2);
				})->select('ld_course_shift_no.name as classno_name','ld_course_class_number.name as class_name','ld_course_class_number.start_at','ld_course_class_number.end_at','ld_lecturer_educationa.real_name as teacher_name','ld_course_live_childs.watch_num','ld_course_class_number.id as class_id','ld_course_class_number.shift_no_id as classno_id')
				->get()->toArray();
			}
				$newcourseArr = [];
				if(!empty($openCourseArr)){
					foreach($openCourseArr as $k=>$v){ //公开课
						$openCourseArr[$k]['is_public'] = 1;
                        $teacherArr = OpenCourseTeacher::where(['class_id'=>$v['class_id'],'is_del'=>0])->select('teacher_id')->get()->toArray();
                        if(empty($teacherArr)){
                            $openCourseArr[$k]['teacherIds'] = '';
                        }else{
                            $openCourseArr[$k]['teacherIds'] = array_column($teacherArr,'teacher_id');
                        }
					}
				}
				if(!empty($courseArr)){ //课程
					foreach($courseArr as $k=>$v){
						$courseArr[$k]['is_public'] = 0;
                        $teacherArr = CourseClassTeacher::where(['class_id'=>$v['class_id'],'is_del'=>0])->select('teacher_id')->get()->toArray();
                        if(empty($teacherArr)){
                            $courseArr[$k]['teacherIds'] = '';
                        }else{
                            $courseArr[$k]['teacherIds'] = array_column($teacherArr,'teacher_id');
                        }
					}
				}
				$newcourseArr = array_merge($openCourseArr,$courseArr);
				if(!empty($newcourseArr) ){
					foreach($newcourseArr as $k=>$v){
						$time = (int)$v['end_at']-(int)$v['start_at'];
						$newcourseArr[$k]['time'] = timetodate($time);
						$newcourseArr[$k]['start_time'] = date('Y-m-d H:i',$v['start_at']);
						if(time()<$v['start_at']){
							$newcourseArr[$k]['sorts'] = 2;
							$newcourseArr[$k]['state'] = 1;
							$newcourseArr[$k]['status'] = '预开始';
							if($teacher_id <= 0){
								$newcourseArr[$k]['statusName'] = '进入直播间';
							}else{
								if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 1){
									$newcourseArr[$k]['statusName'] = '教务辅教';
								}
								if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 2){
									$newcourseArr[$k]['statusName'] = '讲师教学';
								}
							}
						}
						if(time()>$v['end_at']){
							$newcourseArr[$k]['sorts'] = 3;
							$newcourseArr[$k]['state'] = 3;
							$newcourseArr[$k]['status'] = '直播已结束';
							$newcourseArr[$k]['statusName']  =   '查看回放';
						}
						if(time()>$v['start_at'] && time()<$v['end_at']){
							$newcourseArr[$k]['sorts'] = 1;
							$newcourseArr[$k]['state'] = 2;
							$newcourseArr[$k]['status'] = '直播中';
							if($teacher_id <= 0){
								$newcourseArr[$k]['statusName'] = '进入直播间';
							}else{
								if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 1){
									$newcourseArr[$k]['statusName'] = '教务辅教';
								}
								if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 2){
									$newcourseArr[$k]['statusName'] = '讲师教学';
								}
							}
						}
					}
					array_multisort(array_column($newcourseArr,'sorts'),SORT_ASC,$newcourseArr);
				}


			return ['code'=>200,'msg'=>'Success','data'=>$newcourseArr,'where'=>$body];
	}
	//教学详情
	public static function details($body){
		$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前学校id
		$teacher_id = isset(AdminLog::getAdminInfo()->admin_user->teacher_id) ? AdminLog::getAdminInfo()->admin_user->teacher_id : 0; //当前学校id
		$teacher_type_arr  = Teacher::where(['id'=>$teacher_id,'school_id'=>$school_id,'is_del'=>0,'is_forbid'=>0])->select('type')->first();
        if(empty($teacher_type_arr)){
           $teacher_type_arr['type'] = '';
        }
		if($body['is_public'] == 1){  //公开课
			$openCourseArr = OpenCourse::where('id',$body['class_id'])->select('id','title','start_at','end_at')->first();//公开课名称
			$openChildsArr = OpenLivesChilds::where('lesson_id',$openCourseArr['id'])->select('watch_num','course_id')->first();
			$openCourseArr['watch_num'] = $openChildsArr['watch_num']; //观看人数（学员人数）
			$teacherIds = OpenCourseTeacher::where('course_id',$openCourseArr['id'])->pluck('teacher_id')->toArray(); //讲师id组
            $openCourseArr['teacherIds']  = $teacherIds;
			$openCourseArr['lect_teacher_name'] = Teacher::whereIn('id',$teacherIds)->where('type',2)->select('real_name')->first()['real_name'];//讲师
			$eduTeacherName = Teacher::whereIn('id',$teacherIds)->where('type',1)->pluck('real_name')->toArray(); //教务
			$openCourseArr['edu_teacher_name'] = '';
			if(!empty($eduTeacherName)){
				$openCourseArr['edu_teacher_name'] = implode(',', $eduTeacherName);
			}
			$openCourseArr['time'] = timetodate((int)$openCourseArr['end_at']-(int)$openCourseArr['start_at']);//时长

			$openCourseArr['start_at'] = date('Y-m-d H:i:s',$openCourseArr['start_at']);
			$openCourseArr['end_at'] = date('Y-m-d H:i:s',$openCourseArr['end_at']);
            // TODO:  这里替换欢托的sdk CC 直播的 课件相关 CC 暂时没有 暂时不修改
			$MTCloud = new MTCloud();
			$res =$MTCloud->courseDocumentList($openChildsArr['course_id'],1);
			$openCourseArr['courseware'] = $newArr = [];
			if(!empty($res['data'])){
				foreach($res['data'] as $key =>$v){
					$arr= $MTCloud->documentGet($v['id']);
					$newArr[] =$arr['data'];
				}
				$openCourseArr['courseware'] = $newArr;  //欢拓课件信息
			}
			$openCourseArr['class_id'] = $body['class_id'];
			$openCourseArr['is_public'] = $body['is_public'];
			if($openCourseArr['start_at']>time()){
				$openCourseArr['state'] = 1;
				$openCourseArr['status'] = '预开始';
				if($teacher_id <= 0){
					$openCourseArr['statusName'] = '进入直播间';
				}else{
					if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 1){
						$openCourseArr['statusName'] = '教务辅教';
					}
					if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 2){
						$openCourseArr['statusName'] = '讲师教学';
					}
				}
			}
			if($openCourseArr['end_at']<time()){
				$openCourseArr['state'] = 3;
				$openCourseArr['status'] = '直播已结束';
				$openCourseArr['statusName']  = '查看回放';

			}
			if($openCourseArr['start_at']<time() && $openCourseArr['end_at']>time()){
				$openCourseArr['state'] = 2;
				$openCourseArr['status'] = '直播中';
				if($teacher_id <= 0){
					$openCourseArr['statusName'] = '进入直播间';
				}else{
					if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 1){
						$openCourseArr['statusName'] = '教务辅教';
					}
					if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 2){
						$openCourseArr['statusName'] = '讲师教学';
					}
				}
			}
			return ['code'=>200,'msg'=>'Success','data'=>$openCourseArr];
		}
		if($body['is_public'] == 0){  //课程
			if(!isset($body['classno_id'])||empty($body['classno_id']) || $body['classno_id']<=0){ //班号
				return ['code'=>201,'data'=>'班号标识为空或者不合法'];
			}
			$live = [];
			$LiveChildArr  = LiveChild::where('id',$body['class_id'])->select('name','start_at','end_at')->first();//课次名称
			$liveChildClassArr	= CourseLiveClassChild::where('class_id',$body['class_id'])->select('start_time as start_at','end_time as end_at','watch_num','status','course_id')->first();//开始/结束时间/时长/观看人数/课程id(欢拓)
			$classno_id = LiveClass::where('id',$body['classno_id'])->select('name')->first();//班号名称
			$teacherIds = LiveClassChildTeacher::where('class_id',$body['class_id'])->pluck('teacher_id'); //教师id组
            $live['teacherIds'] = $teacherIds;
			$live['lect_teacher_name'] = Teacher::whereIn('id',$teacherIds)->where('type',2)->select('real_name')->first()['real_name'];//讲师
			$eduTeacherName = Teacher::whereIn('id',$teacherIds)->where('type',1)->pluck('real_name')->toArray(); //教务
			$live['edu_teacher_name'] = '';
			if(!empty($eduTeacherName)){
				$live['edu_teacher_name'] = implode(',', $eduTeacherName);
			}
            // TODO:  这里替换欢托的sdk CC 直播 课件相关暂时没有 暂时不修改
			$MTCloud = new MTCloud();
			$res =$MTCloud->courseDocumentList($liveChildClassArr['course_id'],1);
			$live['courseware'] = $newArr = [];
			if(!empty($res['data'])){
				foreach($res['data'] as $key =>$v){
					$arr= $MTCloud->documentGet($v['id']);
					$newArr[] =$arr['data'];
				}
				$live['courseware'] = $newArr;  //欢拓课件信息
			}

			$live = [
				'class_name'=>$classno_id['name'],
				'title'=>$LiveChildArr['name'],
				'start_at'=>date('Y-m-d H:i:s',$liveChildClassArr['start_at']),
				'end_at'=>date('Y-m-d H:i:s',$liveChildClassArr['end_at']),
				'watch_num'=>$liveChildClassArr['watch_num'],
				'status'=> $liveChildClassArr['status'] == 1?'预直播':($liveChildClassArr['status']==2?'直播中':'直播已结束'),
				'duration'=>timetodate((int)$liveChildClassArr['end_at']-(int)$liveChildClassArr['start_at']),
				'courseware'=>$live['courseware'],
				'lect_teacher_name'=>$live['lect_teacher_name'],
				'edu_teacher_name'=>$live['edu_teacher_name'],
				'is_public' =>$body['is_public'],
				'classno_id' =>$body['classno_id'],
				'class_id'=>$body['class_id'],
				'time' => timetodate((int)$liveChildClassArr['end_at']-(int)$liveChildClassArr['start_at'])//时长
			];
			if($liveChildClassArr['start_at']>time()){
				$live['state'] = 1;
				if($teacher_id <= 0){
					$live['statusName'] = '进入直播间';
				}else{
					if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 1){
						$live['statusName'] = '教务辅教';
					}
					if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 2){
						$live['statusName'] = '讲师教学';
					}
				}
			}
			if($liveChildClassArr['end_at']<time()){
				$live['state'] = 3;
				$live['statusName']  = '查看回放';
			}
			if($liveChildClassArr['start_at']<time() && $liveChildClassArr['end_at']>time()){
				$live['state'] = 2;
				if($teacher_id <= 0){
					$live['statusName'] = '进入直播间';
				}else{
					if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 1){
						$live['statusName'] = '教务辅教';
					}
					if(isset($teacher_type_arr['type'])  && $teacher_type_arr['type'] == 2){
						$live['statusName'] = '讲师教学';
					}
				}
			}
			return ['code'=>200,'msg'=>'Success','data'=>$live];
		}
	}
}
