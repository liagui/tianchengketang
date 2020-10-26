<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminLog;
use App\Models\CouresSubject;
use App\Models\OpenCourse;
use App\Models\OpenCourseTeacher;
use Illuminate\Support\Facades\DB;
use App\Models\Teacher;
use App\Tools\CurrentAdmin;
use App\Tools\MTCloud;
use App\Models\OpenLivesChilds;
use App\Models\CourseRefOpen;
use App\Models\CourseRefSubject;


class OpenCourseController extends Controller {

	public function subject(){
		$data = OpenCourse::subject();
		return response()->json($data);
	}
    /*
    * @param  公开课列表
    * @param  author  lys
    * @param  ctime   2020/6/28 9:30
    * return  array
    */
	public function getList(){
		$data = OpenCourse::getList(self::$accept_data);
		return response()->json($data);
	}
	  /*
    * @param  直播类型
    * @param  author  lys
    * @param  ctime   2020/6/28 9:30
    * return  array
    */
	public function zhiboMethod(){
		$arr = [
			['id'=>1,'name'=>'语音云'],
			['id'=>3,'name'=>'大班'],
			['id'=>5,'name'=>'小班'],
			['id'=>6,'name'=>'大班互动'],
		];
		return response()->json(['code'=>200,'msg'=>'Success','data'=>$arr]);
	}
   /*
    * @param  添加公开课
    * @param  author  lys
    * @param  ctime   2020/6/25 17:25
    * return  array
    */
    public function doInsertOpenCourse(){
        $openCourseArr = self::$accept_data;
        $validator = Validator::make($openCourseArr,
                [
                	'subject' => 'required',
                	'title' => 'required',
                	// 'keywords' => 'required',
                	'cover' => 'required',
                	'date' => 'required',
                	'time' => 'required',

                	'is_barrage' => 'required',
                	'live_type' => 'required',
                	'introduce'=>'required',
                	'lect_teacher_id'=>'required',
               	],
                OpenCourse::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $openCourseArr['keywords'] = !isset($openCourseArr['keywords']) || empty($openCourseArr['keywords'])?'':$openCourseArr['keywords'];
        try{
        	$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0 ;
        	$admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0 ;
        	unset($openCourseArr['/admin/opencourse/doInsertOpenCourse']);
	        DB::beginTransaction();
	        $openCourseArr['subject'] = json_decode($openCourseArr['subject'],1);
	        $parent_id =$openCourseArr['subject'][0]<0 ? 0: $openCourseArr['subject'][0];
	        $child_id = !isset($openCourseArr['subject'][1]) && empty($openCourseArr['subject'][1]) ? 0 : $openCourseArr['subject'][1];
	        $openCourseArr['parent_id'] =  $parent_id ;
	        $openCourseArr['child_id'] = $child_id;
	        $count = OpenCourse::where(['school_id'=>$school_id,'parent_id'=>$parent_id,'child_id'=>$child_id,'is_del'=>0])->count(); //看学科大类小类是否为授权还是自增

	        if($count<=0){

	        	$courseData =CourseRefSubject::where(['to_school_id'=>$school_id,'parent_id'=>$parent_id,'child_id'=>$child_id,'is_public'=>0,'is_del'=>0])->first();
	        	$OpenCourseCount =CourseRefSubject::where(['to_school_id'=>$school_id,'parent_id'=>$parent_id,'child_id'=>$child_id,'is_public'=>1,'is_del'=>0])->count();
	        	if(!empty($courseData) && $OpenCourseCount <=0 ){
	        		$insert['from_school_id'] = $courseData['from_school_id'];
	        		$insert['to_school_id'] = $school_id;
	        		$insert['parent_id'] = $parent_id;
	        		$insert['child_id'] = $child_id;
	        		$insert['is_public'] = 1;
	        		$insert['admin_id'] = $admin_id;
	        		$insert['create_at'] = date('Y-m-d H:i:s');
	        		$openCourseSubjectId = CourseRefSubject::insertGetId($insert);
			        if($openCourseSubjectId <0){
			        	DB::rollBack();
			            return response()->json(['code'=>203,'msg'=>'公开课学科创建未成功']);
			        }
	        	}
	        	if(empty($courseData)&&$OpenCourseCount <=0){
	        		//自增
	        		$pid = CouresSubject::where(['id'=>$parent_id,'is_open'=>0,'is_del'=>0])->first();
	        		$childId = CouresSubject::where(['id'=>$child_id,'is_open'=>0,'is_del'=>0])->first();
	        		if(empty($pid) && empty($childId)){
	        			return response()->json(['code'=>203,'msg'=>'公开课学科不存在']);
	        		}
	        	}
	        }
	     	$eduTeacherArr = !isset($openCourseArr['edu_teacher_id']) && empty($openCourseArr['edu_teacher_id'])?[]:json_decode($openCourseArr['edu_teacher_id'],1);

	        $lectTeacherId  = json_decode($openCourseArr['lect_teacher_id'],1);
	        $time = json_decode($openCourseArr['time'],1); //时间段
	      	$start_at = $openCourseArr['date']." ".$time[0];
	      	$end_at = $openCourseArr['date']." ".$time[1];

	        unset($openCourseArr['edu_teacher_id']);
	        unset($openCourseArr['lect_teacher_id']);
	        unset($openCourseArr['subject']);
	        unset($openCourseArr['time']);
	        unset($openCourseArr['date']);
	        if(strtotime($start_at)<time()){
	        	return response()->json(['code'=>207,'msg'=>'开始时间不能小于当前时间']);
	        }
	        if(strtotime($start_at) >  strtotime($end_at) ){
	        	return response()->json(['code'=>207,'msg'=>'开始时间不能大于结束时间']);
	        }

	        $openCourseArr['start_at'] = strtotime($start_at);
	        $openCourseArr['end_at'] = strtotime($end_at);

	        $openCourseArr['admin_id']  = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0 ;
	        $openCourseArr['describe']  = isset($openCourseArr['describe']) ?$openCourseArr['describe']:'';
	   		$openCourseArr['create_at'] = date('Y-m-d H:i:s');
	   	    $openCourseArr['school_id']  = $school_id;
			$openCourseId = OpenCourse::insertGetId($openCourseArr);
	        if($openCourseId <0){
	        	DB::rollBack();
	            return response()->json(['code'=>203,'msg'=>'公开课创建未成功']);
	        }
	        array_push($eduTeacherArr,$lectTeacherId);

	      	foreach ($eduTeacherArr as $key => $val) {
	      		if($val== '' || $val == null){
	      			unset($eduTeacherArr[$key]);
	      		}else{
	      			$addTeacherArr[$key]['course_id'] = (int)$openCourseId;
		        	$addTeacherArr[$key]['teacher_id'] = $val;
		        	$addTeacherArr[$key]['create_at'] = date('Y-m-d H:i:s');
	      		}

	        }

	       	$openCourseTeacher = new OpenCourseTeacher();

            $res = $openCourseTeacher->insert($addTeacherArr);

            if(!$res){
            	DB::rollBack();
	            return response()->json(['code'=>203,'msg'=>'教师创建未成功']);
            }
            	$openCourseData['barrage']= $openCourseArr['is_barrage'];
            	$openCourseData['modetype']= $openCourseArr['live_type'];
            	$openCourseData['title']= $openCourseArr['title'];
            	$openCourseData['start_at']= date('Y-m-d H:i:s',$openCourseArr['start_at']);
            	$openCourseData['end_at']= date('Y-m-d H:i:s',$openCourseArr['end_at']);

            	$openCourseData['teacher_id']= $lectTeacherId;
            	$openCourseData['nickname'] = Teacher::where('id',$lectTeacherId)->select('real_name')->first()['real_name'];
            	$res = $this->addLive($openCourseData,$openCourseId);
            	if(!$res){
            		AdminLog::insertAdminLog([
		                'admin_id'       =>   isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0  ,
		                'module_name'    =>  'OpenCourse' ,
		                'route_url'      =>  'admin/OpenCourse/doInsertOpenCourse' ,
		                'operate_method' =>  'insert',
		                'content'        =>  json_encode($openCourseArr) ,
		                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
		                'create_at'      =>  date('Y-m-d H:i:s')
	            	]);
            		return response()->json(['code'=>203,'msg'=>'公开课创建房间未成功，请重试！']);
            	}
            	DB::commit();
            	return response()->json(['code'=>200,'msg'=>'公开课创建成功']);



	    } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
    * @param  是否推荐
    * @param  author  lys
    * @param  ctime   2020/6/28 9:30
    * return  array
    */
   	public function doUpdateRecomend(){
   		$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登录的学校id

	    $openCourseArr = self::$accept_data;
	    $validator = Validator::make($openCourseArr,
	        [
	        	'openless_id' => 'required|integer',
	        	'nature' =>'required|integer'  //是否为授权 1 自增  2授权
	       	],
	    OpenCourse::message());
	    if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        if($openCourseArr['nature'] == 1 || $openCourseArr['nature'] == 2 ){
        	if($openCourseArr['nature'] == 1){
        		//自增
	        	$data = OpenCourse::getOpenLessById(['id'=>$openCourseArr['openless_id'],'is_del'=>0],['id','is_recommend']);
			    if($data['code']!= 200 ){
			    	 return response()->json($data);
			    }
			    try {
				    $update['is_recommend'] = $data['data']['is_recommend'] >0 ? 0:1;
				    $update['update_at'] = date('Y-m-d H:i:s');
				    $update['id'] =  $data['data']['id'];
				    $update['admin_id'] =  isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0 ;
				    $res = openCourse::where('id',$data['data']['id'])->update($update);
			        if($res){
			        	AdminLog::insertAdminLog([
			                'admin_id'       =>   isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0  ,
			                'module_name'    =>  'OpenCourse' ,
			                'route_url'      =>  'admin/OpenCourse/doUpdateRecomend' ,
			                'operate_method' =>  'update',
			                'content'        =>  json_encode($update) ,
			                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
			                'create_at'      =>  date('Y-m-d H:i:s')
		            	]);
			    		return response()->json(['code'=>200,'msg'=>'更改成功']);
				    }else{
				    	return response()->json(['code'=>203,'msg'=>'更改成功']);
				    }
		       	} catch (\Exception $ex) {
		            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
		        }
        	}
        	if($openCourseArr['nature'] == 2){
        		//授权
        		$natureOpenCourseArr  = CourseRefOpen::where(['to_school_id'=>$school_id,'is_del'=>0,'course_id'=>$openCourseArr['openless_id']])->first();
        		if(!$natureOpenCourseArr){
        			return response()->json(['code'=>204,'msg'=>'公开课信息不存在']);
        		}
        		try {
	        		$update['is_recommend'] = $natureOpenCourseArr['is_recommend'] >0 ? 0:1;
				    $update['update_at'] = date('Y-m-d H:i:s');
				    $update['admin_id'] =  isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0 ;

	        		$res = CourseRefOpen::where('id',$natureOpenCourseArr['id'])->update($update);
		        	if($res){
				        	AdminLog::insertAdminLog([
				                'admin_id'       =>   isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0  ,
				                'module_name'    =>  'OpenCourse' ,
				                'route_url'      =>  'admin/OpenCourse/doUpdateRecomend' ,
				                'operate_method' =>  'update',
				                'content'        =>  json_encode($update) ,
				                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
				                'create_at'      =>  date('Y-m-d H:i:s')
			            	]);
				    		return response()->json(['code'=>200,'msg'=>'更改成功']);
					    }else{
					    	return response()->json(['code'=>203,'msg'=>'更改成功']);
					    }
			       	} catch (\Exception $ex) {
			            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
			        }
        	}
        }else{
        	 return response()->json(['code'=>400,'msg'=>'非法请求']);
        }
    }
    /*
    * @param  修改课程状态
    * @param  author  lys
    * @param  ctime   2020/6/28 10:00
    * return  array
    */
   	public function doUpdateStatus(){
   		$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登录的学校id
	    $openCourseArr = self::$accept_data;
	    $validator = Validator::make($openCourseArr,
	        [
	        	'openless_id' => 'required|integer',
	        	'nature' =>'required|integer'
	       	],
	    OpenCourse::message());
	    if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
       	if($openCourseArr['nature'] == 1 || $openCourseArr['nature'] == 2){
   			$data = OpenCourse::getOpenLessById(['id'=>$openCourseArr['openless_id'],'is_del'=>0],['id','status','start_at','end_at']);
		    if($data['code']!= 200 ){
		    	 return response()->json($data);
		    }
       		if($openCourseArr['nature'] == 1){

       			if($data['data']['status'] <1){
			    	$update['status'] = 1;
			    }else if($data['data']['status'] == 1){
			    	if($data['data']['start_at'] <time() && $data['data']['end_at'] >time()){
			    		return response()->json(['code'=>207,'msg'=>'直播中，无法停售!']);
			    	}
			    	$update['status'] = 2;
			    }else if($data['data']['status'] == 2){
			    	$update['status'] = 1;
			    }
       			//自增
			    try {
				    $update['admin_id'] =  isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0 ;
				    $update['update_at'] = date('Y-m-d H:i:s');
				    $update['id'] =  $data['data']['id'];
				    $res = OpenCourse::where('id',$data['data']['id'])->update($update);
				    if($res){
				    	AdminLog::insertAdminLog([
			                'admin_id'       =>   isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0  ,
			                'module_name'    =>  'OpenCourse' ,
			                'route_url'      =>  'admin/OpenCourse/doUpdateStatus' ,
			                'operate_method' =>  'update',
			                'content'        =>  json_encode(array_merge($data['data'],$update)) ,
			                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
			                'create_at'      =>  date('Y-m-d H:i:s')
		            	]);
				    	return response()->json(['code'=>200,'msg'=>'更改成功']);
				    }else{
				    	return response()->json(['code'=>203,'msg'=>'更改成功']);
				    }
				} catch (\Exception $ex) {
		            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
		        }


       		}
       		if($openCourseArr['nature'] == 2){
       			//授权
       			$natureOpenCourseArr =  CourseRefOpen::where(['course_id'=>$data['data']['id'],'to_school_id'=>$school_id])->first();
       			if($natureOpenCourseArr['status'] <1){
			    	$update['status'] = 1;
			    }else if($natureOpenCourseArr['status'] == 1){
			    	if($data['data']['start_at'] <time() && $data['data']['end_at'] >time()){
			    		return response()->json(['code'=>207,'msg'=>'直播中，无法停售!']);
			    	}
			    	$update['status'] = 2;
			    }else if($natureOpenCourseArr['status'] == 2){
			    	$update['status'] = 1;
			    }
       			try {
				    $update['admin_id'] =  isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0 ;
				    $update['update_at'] = date('Y-m-d H:i:s');
				    $res = CourseRefOpen::where(['course_id'=>$data['data']['id'],'to_school_id'=>$school_id])->update($update);
				    if($res){
				    	AdminLog::insertAdminLog([
			                'admin_id'       =>   isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0  ,
			                'module_name'    =>  'OpenCourse' ,
			                'route_url'      =>  'admin/OpenCourse/doUpdateStatus' ,
			                'operate_method' =>  'update',
			                'content'        =>  json_encode(array_merge($data['data'],$update)) ,
			                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
			                'create_at'      =>  date('Y-m-d H:i:s')
		            	]);
				    	return response()->json(['code'=>200,'msg'=>'更改成功']);
				    }else{
				    	return response()->json(['code'=>203,'msg'=>'更改成功']);
				    }
				} catch (\Exception $ex) {
		            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
		        }

       		}
       	}else{
       		return response()->json(['code'=>400,'msg'=>'非法请求']);
       	}
    }
    /*
    * @param  是否删除
    * @param  author  lys
    * @param  ctime   2020/6/28 9:30
    * return  array
    */
   	public function doUpdateDel(){
	    $openCourseArr = self::$accept_data;
	    $validator = Validator::make($openCourseArr,
	        [
	        	'openless_id' => 'required|integer',
	        	'nature' => 'required|integer' // 1自增 2授权
	       	],
	    OpenCourse::message());
	    if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        if($openCourseArr['nature'] == 2){
        	return response()->json(['code'=>207,'msg'=>'授权公开课，无法删除']);
        }
	    $data = OpenCourse::getOpenLessById(['id'=>$openCourseArr['openless_id'],'is_del'=>0],['id','is_del','start_at','end_at']);
	    if($data['data']['start_at'] <time() && $data['data']['end_at'] >time()){
	    	return response()->json(['code'=>207,'msg'=>'直播中，无法不能删除']);
	    }
	    if($data['code']!= 200 ){
	    	 return response()->json($data);
	    }
	    try {
	        DB::beginTransaction();
		    $update['is_del'] = 1;
		    $update['admin_id'] =  isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0 ;
		    $update['update_at'] = date('Y-m-d H:i:s');
		    $update['id'] =  $data['data']['id'];
		    $res = OpenCourse::where('id',$data['data']['id'])->update($update);
		    if(!$res){
		    	DB::rollBack();
		    	return response()->json(['code'=>203,'msg'=>'删除成功!']);
		   	}
		   	$res = OpenCourseTeacher::where('course_id',$update['id'])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
		    if(!$res){
		    	DB::rollBack();
		    	return response()->json(['code'=>203,'msg'=>'删除成功!!']);
		    }
            $result = OpenLivesChilds::where('lesson_id',$data['data']['id'])->update(['is_del'=>1,
            	'update_at'=>date('Y-m-d H:i:s')]); //不删除欢拓数据
		    // $course_id  = openLivesChilds::where('lesson_id',$openCourseArr['openless_id'])->select('course_id')->first()['course_id'];
		    // $res = $this->courseDelete($course_id);
		    if(!$result){
		    	DB::rollBack();
		    	return response()->json(['code'=>203,'msg'=>'删除成功!!!']);
		    }
		    AdminLog::insertAdminLog([
                'admin_id'       =>   isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0  ,
                'module_name'    =>  'OpenCourse' ,
                'route_url'      =>  'admin/OpenCourse/doUpdateDel' ,
                'operate_method' =>  'update',
                'content'        =>  json_encode($update) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
        	]);
	    	DB::commit();
	    	return response()->json(['code'=>200,'msg'=>'删除成功']);
	    } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
    * @param  公开课修改(获取)
    * @param  author  lys
    * @param  ctime   2020/6/28 9:30
    * return  array
    */
   	public function getOpenLessById(){
	    $openCourseArr = self::$accept_data;
	    $validator = Validator::make($openCourseArr,
	        [
	        	'openless_id' => 'required|integer',
	       	],
	    OpenCourse::message());
	    if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
	    $data = OpenCourse::getOpenLessById(['id'=>$openCourseArr['openless_id'],'is_del'=>0],['id','parent_id','child_id','title','keywords','cover','start_at','end_at','describe','introduce','is_barrage','live_type']);
	    if($data['code']!= 200 ){
	    	return response()->json($data);
	    }


	    $data['data']['subject'] = [];

	   	if($data['data']['parent_id']>0){
	   		array_push($data['data']['subject'],$data['data']['parent_id']);
	   	}


	   	if($data['data']['child_id']>0){
	   		array_push($data['data']['subject'], $data['data']['child_id']);
	   	}
	    $data['data']['date'] = date('Y-m-d',$data['data']['start_at']);
	    $data['data']['time'] = [date('H:i:s',$data['data']['start_at']),date('H:i:s',$data['data']['end_at'])];
	    $data['data']['openless_id'] = $data['data']['id'];

	    $teacher_id = OpenCourseTeacher::where(['course_id'=>$data['data']['id'],'is_del'=>0])->get(['teacher_id'])->toArray();
	    $teacherArr = array_column($teacher_id,'teacher_id');
    	$teacherData = Teacher::whereIn('id',$teacherArr)->where('is_del',0)->select('id','type')->get()->toArray();
    	$lectTeacherArr = $eduTeacherArr =  $data['data']['edu_teacher_id'] = [];
    	if(!empty($teacherData)){
    		foreach($teacherData as $key =>$v){
    			if($v['type'] == 1){//教务
    				$data['data']['edu_id'][] = Teacher::where(['type'=>1,'id'=>$v['id']])->select('id as teacher_id','real_name')->first()->toArray();
    			}else if($v['type'] == 2){//讲师
    				$data['data']['lect_id'] = Teacher::where(['type'=>2,'id'=>$v['id']])->select('id as teacher_id','real_name')->get()->toArray();
    			}
    		}

    		if(!empty($data['data']['edu_id'])){
    			foreach($data['data']['edu_id'] as $key=>$v){
    				array_push($data['data']['edu_teacher_id'],$v['teacher_id']);
    			}
    		}
    		if(!empty($data['data']['lect_id'])){
    			$data['data']['lect_teacher_id'] = $data['data']['lect_id'][0]['teacher_id'];
    		}
    	}
    	$arr = ['openless'=>$data['data']];
    	return response()->json(['code'=>200,'msg'=>'获取成功','data'=>$arr]);
    }
    /*
    * @param  公开课修改
    * @param  author  lys
    * @param  ctime   2020/6/28 9:30
    * return  array
    */
   	public function doOpenLessById(){
	    $openCourseArr = self::$accept_data;
	    $validator = Validator::make($openCourseArr,
	        [
	        	'openless_id' => 'required|integer',
	       		'subject' =>'required',
            	'title' => 'required',
            	// 'keywords' => 'required',
            	'cover' => 'required',
            	'date'=>'required',
            	'time' => 'required',
            	'is_barrage' => 'required',
            	'live_type' => 'required',
            	'introduce' => 'required',
            	// // 'edu_teacher_id' => 'required',
            	'nature'=>'required',   //是否授权  1自增  2 授权
            	'lect_teacher_id'=>'required',
	       	],
	    OpenCourse::message());
	    if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        //是否授权
        if($openCourseArr['nature'] == 2){
        	return response()->json(['code'=>207,'msg'=>'授权公开课，无法修改']);
        }

	    $data = OpenCourse::getOpenLessById(['id'=>$openCourseArr['openless_id'],'is_del'=>0],['id','start_at','end_at']);
	    if($data['code']!= 200 ){
	    	return response()->json($data);
	    }

	    $time = json_decode($openCourseArr['time'],1); //时间段
     	$start_at = $openCourseArr['date']." ".$time[0];
	    $end_at = $openCourseArr['date']." ".$time[1];
	    if($data['data']['start_at'] <time() && $data['data']['end_at'] >time()){
	    	return response()->json(['code'=>207,'msg'=>'直播中，无法修改']);
	    }
	    if($data['data']['end_at'] <time()){
	    	return response()->json(['code'=>207,'msg'=>'课程已结束，无法修改！！！']);
	    }

        if(strtotime($start_at)<time()){
	        	return response()->json(['code'=>207,'msg'=>'开始时间不能小于当前时间']);
        }
        if(strtotime($start_at) >  strtotime($end_at) ){
        	return response()->json(['code'=>207,'msg'=>'开始时间不能大于结束时间']);
        }
	     try{
	        DB::beginTransaction();
	        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0 ;
        	$admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0 ;
	     	if(isset($openCourseArr['/admin/opencourse/doOpenLessById'])){
	     		unset($openCourseArr['/admin/opencourse/doOpenLessById']);
	     	}
	     	$openCourseArr['subject'] = json_decode($openCourseArr['subject'],1);
	    	$parent_id = $openCourseArr['subject'][0]<0 ? 0: $openCourseArr['subject'][0];
	    	$child_id = !isset($openCourseArr['subject'][1]) || empty($openCourseArr['subject'][1]) ? 0 : $openCourseArr['subject'][1];
	     	$openCourseArr['parent_id'] = $parent_id;
	        $openCourseArr['child_id'] = $child_id;
	  		$count = OpenCourse::where(['school_id'=>$school_id,'parent_id'=>$parent_id,'child_id'=>$child_id,'is_del'=>0])->count(); //看学科大类小类是否为授权还是自增
	        if($count<=0){
	        	$courseData =CourseRefSubject::where(['to_school_id'=>$school_id,'parent_id'=>$parent_id,'child_id'=>$child_id,'is_public'=>0,'is_del'=>0])->first();
	        	$OpenCourseCount =CourseRefSubject::where(['to_school_id'=>$school_id,'parent_id'=>$parent_id,'child_id'=>$child_id,'is_public'=>1,'is_del'=>0])->count();
	        	if(!empty($courseData) && $OpenCourseCount <=0 ){
	        		$insert['from_school_id'] = $courseData['from_school_id'];
	        		$insert['to_school_id'] = $school_id;
	        		$insert['parent_id'] = $parent_id;
	        		$insert['child_id'] = $child_id;
	        		$insert['is_public'] = 1;
	        		$insert['admin_id'] = $admin_id;
	        		$insert['create_at'] = date('Y-m-d H:i:s');
	        		$openCourseSubjectId = CourseRefSubject::insertGetId($insert);
			        if($openCourseSubjectId <0){
			        	DB::rollBack();
			            return response()->json(['code'=>203,'msg'=>'公开课学科更改未成功']);
			        }
	        	}
	        	if(empty($courseData)&&$OpenCourseCount <=0){
	        		//自增
	        		$pid = CouresSubject::where(['id'=>$parent_id,'is_open'=>0,'is_del'=>0])->first();
	        		$childId = CouresSubject::where(['id'=>$child_id,'is_open'=>0,'is_del'=>0])->first();
	        		if(empty($pid) && empty($childId)){
	        			return response()->json(['code'=>203,'msg'=>'公开课学科不存在']);
	        		}
	        	}
	        }
	       	$eduTeacherArr = !isset($openCourseArr['edu_teacher_id']) && empty($openCourseArr['edu_teacher_id'])?[]:json_decode($openCourseArr['edu_teacher_id'],1);
	        $lectTeacherId = $openCourseArr['lect_teacher_id'];
	        unset($openCourseArr['edu_teacher_id']);
	        unset($openCourseArr['lect_teacher_id']);
	        unset($openCourseArr['openless_id']);
	        unset($openCourseArr['subject']);
	        unset($openCourseArr['time']);
	        unset($openCourseArr['date']);
	        $openCourseArr['start_at'] = strtotime($start_at);
	        $openCourseArr['end_at'] = strtotime($end_at);
	       	$openCourseArr['admin_id']  = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0 ;
	        $openCourseArr['describe']  = !isset($openCourseArr['describe']) ?'':$openCourseArr['describe'];
	   		$openCourseArr['update_at'] = date('Y-m-d H:i:s');
			$res = OpenCourse::where('id',$data['data']['id'])->update($openCourseArr);
	        if(!$res){
	        	DB::rollBack();
	            return response()->json(['code'=>203,'msg'=>'公开课更改未成功']);
	        }
	        $openless_id = $data['data']['id'];
	        $openCourseTeacher = new OpenCourseTeacher();
	        $res = $openCourseTeacher->where('course_id',$openless_id)->delete();
	        if(!$res){
            	DB::rollBack();
	            return response()->json(['code'=>203,'msg'=>'教师更改未成功']);
	        }
	        array_push($eduTeacherArr,$lectTeacherId);

	        foreach ($eduTeacherArr as $key => $val) {
	        	$addTeacherArr[$key]['course_id'] = (int)$openless_id;
	        	$addTeacherArr[$key]['teacher_id'] = $val;
	        	$addTeacherArr[$key]['create_at'] = date('Y-m-d H:i:s');
	        	$addTeacherArr[$key]['update_at'] = date('Y-m-d H:i:s');
	        }
            $res = $openCourseTeacher->insert($addTeacherArr);
            if(!$res){
            	DB::rollBack();
	            return response()->json(['code'=>203,'msg'=>'教师更改未成功']);
            }
            $openLivesChilds=OpenLivesChilds::where('lesson_id',$openless_id)->select('id','course_id')->first();
            $openCourseData['course_id'] = $openLivesChilds['course_id'];
            $openCourseData['barrage']= $openCourseArr['is_barrage'];
        	$openCourseData['modetype']= $openCourseArr['live_type'];
        	$openCourseData['title']= $openCourseArr['title'];
        	$openCourseData['start_at']= date('Y-m-d H:i:s',$openCourseArr['start_at']);
        	$openCourseData['end_at']= date('Y-m-d H:i:s',$openCourseArr['end_at']);
        	$openCourseData['teacher_id']= $lectTeacherId;
        	$openCourseData['nickname'] = Teacher::where('id',$lectTeacherId)->select('real_name')->first()['real_name'];
        	$res = $this->courseUpdate($openCourseData);
        	if(!$res){
            	DB::rollBack();
	            return response()->json(['code'=>203,'msg'=>'公开课更改未成功']);
            }
            AdminLog::insertAdminLog([
                'admin_id'       =>   isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0  ,
                'module_name'    =>  'OpenCourse' ,
                'route_url'      =>  'admin/OpenCourse/doOpenLessById' ,
                'operate_method' =>  'update',
                'content'        =>  json_encode($openCourseData) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
        	]);
        	DB::commit();
        	return response()->json(['code'=>200,'msg'=>'公开课更改成功']);


	    } catch (\Exception $ex) {
             DB::rollBack();

            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }



    //公开课创建直播（欢拓）
    public function addLive($data, $lesson_id)
    {
        $user = CurrentAdmin::user();
        try {

// 临时 屏蔽 欢托的课程
//            $MTCloud = new MTCloud();
//            $res = $MTCloud->courseAdd($data['title'], $data['teacher_id'], $data['start_at'], $data['end_at'],
//                $data['nickname'],
//                '', [
//                    'barrage' => $data['barrage'],
//                    'modetype' => $data['modetype'],
//                ]
//            );

            //todo: 这里替换了 欢托的sdk ok
            $CCCloud = new CCCloud();
            //产生 教师端 和 助教端 的密码 默认一致
            $password= $CCCloud ->random_password();
            $password_user = $CCCloud ->random_password();
            $room_info = $CCCloud ->create_room($data['title'], $data['title'],$password,$password,$password_user);

            if(!array_key_exists('code', $room_info) && $room_info["code"] != 0){
            	return response()->json($room_info);

            }
            $result =  OpenLivesChilds::insert([
                            'lesson_id'    =>$lesson_id,
                            'course_name' => $data['title'],
                            'account'     => $data['teacher_id'],
                            'start_time'  => $data['start_at'],
                            'end_time'    => $data['end_at'],
                            'nickname'    => $data['nickname'],
        //                     'modetype'    => $data['modetype'],
 							// 'barrage'    => $data['barrage'],


                            // 这两个数值是欢托有的但是CC没有的 因此 这两个保持空
                            // 'partner_id'  => $room_info['data']['partner_id'],
                            // 'bid'         => $room_info['data']['bid'],
                            'partner_id'  => "",
                            'bid'         => "",

                            // 这里存放的是 欢托的课程id 但是这里 改成 cc 的 直播id 直接进入直播间
                            // 'course_id'   => $room_info['data']['course_id'],
                            'course_id'   => $room_info['data']['room']['id'],

                            // 主播端 助教端 用户端的密码
                            'zhubo_key'   => $password,
                            'admin_key'   => $password,
                            'user_key'    => $password_user,
                            // add time 是欢托存在的但是cc 没 这里默认获取系统时间戳
                            // 'add_time'    => $room_info['data']['add_time'],
                            'add_time'    => time(),
                            'create_at'   =>date('Y-m-d H:i:s'),
                            'status' =>1
                        ]);
            if($result) return true;
            else return false;
        } catch (\Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return false;
        }
        return true;
    }
    //公开课更改直播 （欢拓）
    public function courseUpdate($data)
    {
        try {

            // todo: 这替换 cc直播 公开课修改直播 ok
            // 这里直接调用CC 的更新房间函数来 更新

            $CCCloud = new CCCloud();
            $room_info = $CCCloud ->update_room_info($data['course_id'],$data['title'],$data['title'],$data['barrage']);

//            $MTCloud = new MTCloud();
//            $res = $MTCloud->courseUpdate($data['course_id'], $data['teacher_id'], $data['title'], $data['start_at'],
//                $data['end_at'], $data['nickname'], '', [
//                    'barrage' => $data['barrage'],
//                    'modetype' => $data['modetype'],
//                ]
//            );

            if(!array_key_exists('code', $room_info) && $room_info["code"] != 0){
            	Log::error('CC 直播更改失败:'.json_encode($room_info));
            	return response()->json($room_info);

            }
            $update = [
            	'course_name'=>$data['title'],
            	'start_time'=>date('Y-m-d H:i:s',$data['start_at']),
            	// todo: 这里的结束时间待定
                //'end_time'=>date('Y-m-d H:i:s',$res['data']['end_time']),
            	// CC 直播 bid 暂时没哟
                //'bid'=>$res['data']['bid'],
            	'bid'=>"",
            	'update_at'=>date('Y-m-d H:i:s'),
            ];
          $result = OpenLivesChilds::where('course_id',$data['course_id'])->update($update);
          if($result) return true;
          else return false;
        } catch (\Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return false;
        }
        return true;
    }

    /**
     *   公开课删除直播 （欢拓）
     *
     *   fix 增加cc 直播后 后这里 传递的参数是直播间
     * @param $course_id cc 直播的 房间号
     * @return bool
     */
    public function courseDelete($course_id)
    {
        try {

            // todo: 这替换 cc直播 这里是类似删除的功能 待定
            // CC 没有这个功能 删除 这一部分代码

//            $MTCloud = new MTCloud();
//            $res = $MTCloud->courseDelete($course_id);
//
//            if(!array_key_exists('code', $res) && !$res["code"] == 0){
//                Log::error('欢拓删除失败:'.json_encode($res));
//                return false;
//            }

            $update = [
            	'is_del'=>1,
            	'update_at'=>date('Y-m-d H:i:s'),
            ];
          $result = OpenLivesChilds::where('course_id',$course_id)->update($update);
          if($result) return true;
          else return false;
        } catch (\Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return false;
        }
        return true;
    }


}
