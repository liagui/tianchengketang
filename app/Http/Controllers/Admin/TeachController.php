<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Live;
use App\Models\School;
use App\Models\Subject;
use App\Models\Teach;
use App\Models\Teacher;
use Illuminate\Http\Request;
use App\Tools\CurrentAdmin;
use Validator;
use App\Tools\MTCloud;
use App\Tools\CCCloud\CCCloud;
use App\Models\LiveChild;
use App\Models\LiveClassChild;
use App\Models\OpenLivesChilds;
use App\Models\CourseLiveClassChild;
use Log;
use App\Models\AdminLog;
use App\Listeners\LiveListener;
//教学模块
class TeachController extends Controller {

	//教学列表
	public function getList(){
		$result = Teach::getList(self::$accept_data);
        if($result['code'] == 200){
            return response()->json($result);
        }else{
            return response()->json($result);
        }
	}
	//教学详情
	public function details(){
		$validator = Validator::make(self::$accept_data,
                [
                	'class_id' => 'required',//课次 （公开课时为课程id）
                	// 'classno_id' => 'required',//班号
                	'is_public' => 'required',//是否未公开课   (1 公开课  0课程)
               	],
                Teach::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
		$result = Teach::details(self::$accept_data);
		if($result['code'] == 200){
            return response()->json($result);
        }else{
            return response()->json($result);
        }
	}
	 /**
     * 启动直播
     * @param   auther  lys  2020.7.2
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function startLive()
    {


        $user_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        $real_name = isset(AdminLog::getAdminInfo()->admin_user->real_name) ? AdminLog::getAdminInfo()->admin_user->real_name : $this->make_password();
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $teacher_id = isset(AdminLog::getAdminInfo()->admin_user->teacher_id) ? AdminLog::getAdminInfo()->admin_user->teacher_id : 0;
        // if($teacher_id <= 0){
        //   return response()->json(['code'=>207,'msg'=>'非讲师教务进入直播间']);
        // }
        $teacherArr = Teacher::where(['school_id'=>$school_id,'id'=>$teacher_id,'is_del'=>0,'is_forbid'=>0])->first();
        $data = self::$accept_data;
        $validator = Validator::make($data, [
            'is_public'=>'required',
            'id' => 'required',
        ],Teach::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        if(isset($data['teacher_id'])&& !empty($data['teacher_id'])){ // 优化讲师/教务与房间号不符不让进  （lys）20201120
            $teacherIdsArr = json_decode($data['teacher_id'],1);
            if(!empty($teacherIdsArr)){
                if(!in_array($teacher_id,$teacherIdsArr)){
                    return response()->json(['code'=>203,'msg'=>'讲师/教务与房间号不符']);
                }
            }else{
                return response()->json(['code'=>203,'msg'=>'暂无讲师教务信息']);
            }
        }else{
            return response()->json(['code'=>203,'msg'=>'暂无讲师教务信息']);
        }
        if($data['is_public'] == 1){ //公开课
            OpenLivesChilds::increment('watch_num',1);
            $live = OpenLivesChilds::where('lesson_id',$data['id'])->first();
        }
     	if($data['is_public']== 0){  //课程

           CourseLiveClassChild::increment('watch_num',1);
		   $live = CourseLiveClassChild::where('class_id',$data['id'])->first();
     	}


        if(isset($teacherArr['type']) && $teacherArr['type'] == 1){

            //教务
            $liveArr['course_id'] = $live['course_id'];
            $liveArr['uid'] = $teacherArr['id'];
            $liveArr['nickname'] = $teacherArr['real_name'];
            $liveArr['role'] = 'admin';

            // 获取观看端的密码
            $liveArr[ 'user_key' ] = $live[ 'user_key' ];
            $liveArr[ 'zhubo_key' ] = $live[ 'zhubo_key' ];
            $liveArr[ 'admin_key' ] = $live[ 'admin_key' ];

            $liveArr[ 'school_id' ]  = "";
            // 加入学校id 是用来 区分 网校不同的学员观看同一场直播
            if(isset($this->data[ 'school_dns' ])){
                $school = School::where('is_forbid', '=', 1)->where('dns', "=", $this->data[ 'school_dns' ])->first();
                $liveArr[ 'school_id' ] = $school->school_id;
            }

            $res = $this->courseAccess($liveArr);
            if($res['code'] == 1203){ //该课程没有回放记录!
                return response()->json($res);
            }
        }

        if(isset($teacherArr['type']) && $teacherArr['type'] == 2){
            //讲师
            // todo 这里替换欢托的sdk 改成CC 直播 ok
            //$MTCloud = new MTCloud();
            //$res = $MTCloud->courseLaunch($live['course_id']);

            $CCCloud = new CCCloud();
            $res=$CCCloud ->start_live($live ->course_id,$live->zhubo_key,$live->admin_key,$live->user_key,
            $teacherArr['real_name']);

            Log::error('直播器启动:'.json_encode($res));
            if(!array_key_exists('code', $res) && !$res["code"] == 0){
                return $this->response('直播器启动失败', 500);
            }
        }
        if(!isset($teacherArr['type'])){
            $liveArr['course_id'] = $live['course_id'];
            $liveArr['uid'] = $user_id;
            $liveArr['nickname'] = $real_name;
            $liveArr['role'] = 'user';

            // 获取观看端的密码
            $liveArr[ 'user_key' ] = $live[ 'user_key' ];
            $liveArr[ 'zhubo_key' ] = $live[ 'zhubo_key' ];
            $liveArr[ 'admin_key' ] = $live[ 'admin_key' ];

            $liveArr[ 'school_id' ]  = "";
                // 加入学校id 是用来 区分 网校不同的学员观看同一场直播
            if(isset($this->data[ 'school_dns' ])){
                $school = School::where('is_forbid', '=', 1)->where('dns', "=", $this->data[ 'school_dns' ])->first();
                $liveArr[ 'school_id' ] = $school->school_id;
            }

            $res = $this->courseAccess($liveArr);
            if($res['code'] == 1203){ //该课程没有回放记录!
                return response()->json($res);
            }
        }

        AdminLog::insertAdminLog([
            'admin_id'       =>   CurrentAdmin::user()['cur_admin_id'] ,
            'module_name'    =>  'Teach' ,
            'route_url'      =>  'admin/teach/startLiveChild' ,
            'operate_method' =>  'insert' ,
            'content'        =>  json_encode($data),
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return $this->response($res['data']);
    }
    //进入直播间
    public function liveInRoom(){
        // TODO:  这里替换欢托的sdk CC 直播的 但是这里好像没有用

        $user_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        $real_name = isset(AdminLog::getAdminInfo()->admin_user->real_name) ? AdminLog::getAdminInfo()->admin_user->real_name : $this->make_password();
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $teacher_id = isset(AdminLog::getAdminInfo()->admin_user->teacher_id) ? AdminLog::getAdminInfo()->admin_user->teacher_id : 0;
        // if($teacher_id <= 0){
        //   return response()->json(['code'=>207,'msg'=>'非讲师教务进入直播间']);
        // }
        $teacherArr = Teacher::where(['school_id'=>$school_id,'id'=>$teacher_id,'is_del'=>0,'is_forbid'=>0])->first();
        // if(empty($teacherArr)){
        //   return response()->json(['code'=>207,'msg'=>'非讲师教务进入直播间']);
        // }
        $data = self::$accept_data;
        $validator = Validator::make($data, [
          'is_public'=>'required',
          'id' => 'required',
        ],Teach::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        if(isset($data['teacher_id'])&& !empty($data['teacher_id'])){  // 优化讲师/教务与房间号不符不让进  （lys）20201120
            $teacherIdsArr = json_decode($data['teacher_id'],1);
            if(!empty($teacherIdsArr)){
                if(!in_array($teacher_id,$teacherIdsArr)){
                   return response()->json(['code'=>203,'msg'=>'讲师/教务与房间号不符']);
                }
           }else{
                return response()->json(['code'=>203,'msg'=>'暂无讲师教务信息']);
           }
        }else{
           return response()->json(['code'=>203,'msg'=>'暂无讲师教务信息']);
        }
        if($data['is_public'] == 1){ //公开课
          OpenLivesChilds::increment('watch_num',1);
          $live = OpenLivesChilds::where('lesson_id',$data['id'])->select('course_id','bid','zhubo_key','admin_key','user_key')->first();
        }
        if($data['is_public']== 0){  //课程
            CourseLiveClassChild::increment('watch_num',1);
            $live = CourseLiveClassChild::where('class_id',$data['id'])->select('course_id','bid','zhubo_key','admin_key','user_key')->first();
        }
        if(isset($teacherArr['type']) and $teacherArr['type'] == 1){
            //教务
            $liveArr['course_id'] = $live['course_id'];
            $liveArr['uid'] = $teacherArr['id'];
            $liveArr['nickname'] = $teacherArr['real_name'];
            $liveArr['role'] = 'admin';

            // 获取观看端的密码
            $liveArr[ 'user_key' ] = $live[ 'user_key' ];
            $liveArr[ 'zhubo_key' ] = $live[ 'zhubo_key' ];
            $liveArr[ 'admin_key' ] = $live[ 'admin_key' ];

            $liveArr[ 'school_id' ]  = "";
            // 加入学校id 是用来 区分 网校不同的学员观看同一场直播
            if(isset($this->data[ 'school_dns' ])){
                $school = School::where('is_forbid', '=', 1)->where('dns', "=", $this->data[ 'school_dns' ])->first();
                $liveArr[ 'school_id' ] = $school->school_id;
            }

            $res = $this->courseAccess($liveArr);
            if($res['code'] == 1203){ //该课程没有回放记录!
                return response()->json($res);
            }
        }

        if(isset($teacherArr['type']) && $teacherArr['type'] == 2){
            //讲师
            // todo 这里替换 欢托的sdk 改成CC 直播 ok

//          $CCCloud = new CCCloud();
//          $room_info=$CCCloud ->start_live($live[ 'course_id'], $live[ 'zhubo_key'], $live[ 'admin_key'], $live[ 'user_key']);
            // 欢托的 这个 数值肯定大于0 但是
            if ($live['bid'] == 0){
                $CCCloud = new CCCloud();
                $res=$CCCloud ->start_live($live[ 'course_id'], $live[ 'zhubo_key'], $live[ 'admin_key'],
                $live[ 'user_key'], $teacherArr['real_name']);
            }else{
                $MTCloud = new MTCloud();
                $res = $MTCloud->courseLaunch($live['course_id']);
            }

            // todo 这里需要 做兼容的工作 兼容 cc 和 欢托
            Log::error('直播器启动:'.json_encode($res));
            if(!array_key_exists('code', $res) && !$res["code"] == 0){
                return $this->response('直播器启动失败', 500);
            }

        }
        if(!isset($teacherArr['type'])){

            $liveArr['course_id'] = $live['course_id'];
            $liveArr['uid'] = $user_id;
            $liveArr['nickname'] = $real_name;
            $liveArr['role'] = 'user';


            // 获取观看端的密码
            $liveArr[ 'user_key' ] = $live[ 'user_key' ];
            $liveArr[ 'zhubo_key' ] = $live[ 'zhubo_key' ];
            $liveArr[ 'admin_key' ] = $live[ 'admin_key' ];

            $liveArr[ 'school_id' ]  = "";
            // 加入学校id 是用来 区分 网校不同的学员观看同一场直播
            if(isset($this->data[ 'school_dns' ])){
                $school = School::where('is_forbid', '=', 1)->where('dns', "=", $this->data[ 'school_dns' ])->first();
                $liveArr[ 'school_id' ] = $school->school_id;
            }

            $res = $this->courseAccess($liveArr);
            if($res['code'] == 1203){ //该课程没有回放记录!
            return response()->json($res);
            }
        }

        AdminLog::insertAdminLog([
            'admin_id'       =>   CurrentAdmin::user()['cur_admin_id'] ,
            'module_name'    =>  'Teach' ,
            'route_url'      =>  'admin/teach/liveInRoom' ,
            'operate_method' =>  'insert' ,
            'content'        =>  json_encode($data),
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return $this->response($res['data']);

    }

   	/**
     * 查看回放
     * @param   auther  lys  2020.7.2
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
   	public function livePlayback(){
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $teacher_id = isset(AdminLog::getAdminInfo()->admin_user->teacher_id) ? AdminLog::getAdminInfo()->admin_user->teacher_id : 0;
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        $real_name = isset(AdminLog::getAdminInfo()->admin_user->real_name) ? AdminLog::getAdminInfo()->admin_user->real_name :$this->make_password();
        // if($teacher_id <= 0){
        //   return response()->json(['code'=>207,'msg'=>'非讲师教务查看回放']);
        // }
        $teacherArr = Teacher::where(['school_id'=>$school_id,'id'=>$teacher_id,'is_del'=>0,'is_forbid'=>0])->first();
        // if(empty($teacherArr)){
        //   return response()->json(['code'=>207,'msg'=>'非讲师教务查看回放']);
        // }
   		$data = self::$accept_data;
   		$validator = Validator::make($data, [
   			  'is_public'=>'required',
          'id' => 'required',
        ],Teach::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        if($data['is_public'] == 1){ //公开课
            OpenLivesChilds::increment('watch_num',1);
            $live = OpenLivesChilds::where('lesson_id',$data['id'])->select('course_id','bid','zhubo_key','admin_key','user_key')->first();
        }
     	if($data['is_public']== 0){  //课程
        CourseLiveClassChild::increment('watch_num',1);
			    $live = CourseLiveClassChild::where('class_id',$data['id'])->select('course_id','bid','zhubo_key','admin_key','user_key')->first();
     	}

      $liveArr['course_id'] = $live['course_id'];
      $liveArr['uid'] = !isset($teacherArr['id'])?$teacherArr['id']:$user_id;
      $liveArr['nickname'] = !isset($teacherArr['real_name'])?$teacherArr['real_name']:$real_name;
      $liveArr['role'] = !isset($teacherArr['id'])?'admin':'user';
      $liveArr['bid'] = ($teacherArr['bid']);
      $liveArr['user_key'] = ($live['user_key']);

      $liveArr['school_id'] =$school_id;


        $viewercustominfo= array(
            "school_id"=>$school_id,
            "id" => $teacher_id,
            "nickname" => $real_name,
        );


      $res = $this->courseAccessPlayback($liveArr,$viewercustominfo);
      if($res['code'] == 1203){ //该课程没有回放记录!
          return response()->json($res);
      }
      AdminLog::insertAdminLog([
        'admin_id'       =>   CurrentAdmin::user()['cur_admin_id'] ,
        'module_name'    =>  'Teach' ,
        'route_url'      =>  'admin/teach/livePlayback' ,
        'operate_method' =>  'insert' ,
        'content'        =>  json_encode($data),
        'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
        'create_at'      =>  date('Y-m-d H:i:s')
      ]);

     	return ['code'=>200,'msg'=>'Success','data'=>$res['data']];
   	}

 	/**
     * 课件上传
     * @param   auther  lys  2020.7.2
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function courseUpload(){
        // TODO:  这里替换欢托的sdk CC 直播的 但是这里好像没有用
  		$data = self::$accept_data;
      $validator = Validator::make($data, [
      	'is_public'=>'required',
        'class_id' => 'required', //公开课 课程id  课程 课次id
      ],Teach::message());
      if ($validator->fails()) {
          return response()->json(json_decode($validator->errors()->first(),1));
      }
      $file = isset($_FILES['file']) && !empty($_FILES['file']) ? $_FILES['file'] : '';
      //判断是否有文件上传
      if(!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])){
          return response()->json(['code' => 201 , 'msg' => '请上传课件文件']);
      }
      //存放文件路径
      $file_path= app()->basePath() . "/public/upload/editor/" . date('Y-m-d') . '/';
      //判断上传的文件夹是否建立
      if(!file_exists($file_path)){
          mkdir($file_path , 0777 , true);
      }
      //重置文件名
      $filename = time() . rand(1,10000) . uniqid() . substr($file['name'], stripos($file['name'], '.'));
      $path     = $file_path.$filename;
      //判断文件是否是通过 HTTP POST 上传的
      if(is_uploaded_file($_FILES['file']['tmp_name'])){
          //上传文件方法
          $rs =  move_uploaded_file($_FILES['file']['tmp_name'], $path);
          if($rs && !empty($rs)){
              $file=array(
                'name'=>$_FILES['file']['name'],
          			'file'=>$_SERVER['DOCUMENT_ROOT']."/upload/editor/" . date('Y-m-d') . '/'.$filename,
          		);
          	if($data['is_public'] == 1){ //公开课
			        $live = OpenLivesChilds::where('lesson_id',$data['class_id'])->select('course_id')->first();
		        }
		       	if($data['is_public']== 0){  //课程
		 			    $live = LiveChild::where('class_id',$data['class_id'])->select('course_id')->first();
		       	}
		       	// todo 这里修改成CC 直报的 但是CC 没有类似功能 待定
		       	$MTCloud = new MTCloud();
		       	$res = $MTCloud->courseDocumentUpload($live['course_id'],$file);
	       	  if(array_key_exists('code', $res) && $res["code"] != 0){
               return  response()->json($res);
            }
            AdminLog::insertAdminLog([
              'admin_id'       =>   CurrentAdmin::user()['cur_admin_id'] ,
              'module_name'    =>  'Teach' ,
              'route_url'      =>  'admin/teach/coursewareUpload' ,
              'operate_method' =>  'insert' ,
              'content'        =>  json_encode(array_merge($data,$file)),
              'ip'             =>  $_SERVER['REMOTE_ADDR'],
              'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return  response()->json(['code'=>200,'msg'=>'上传课件成功']);
          } else {
              return response()->json(['code' => 203 , 'msg' => '上传课件失败']);
          }
      } else {
          return response()->json(['code' => 202 , 'msg' => '上传方式非法']);
      }
    }
    /**
     * 课件删除
     * @param   auther  lys  2020.7.2
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function coursewareDel(){

        // TODO:  这里替换欢托的sdk CC 直播的 但是这里好像没有用

        $validator = Validator::make(self::$accept_data, [

            'id' => 'required', //课件id
        ],Teach::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        // todo 这里修改成CC 直报的 但是CC 没有类似功能
        $MTCloud = new MTCloud();
        $res = $MTCloud->documentDelete(self::$accept_data['id']);
        if(!array_key_exists('code', $res) && !$res["code"] == 0){
            return $this->response('课件删除失败', 500);
        }
        AdminLog::insertAdminLog([
          'admin_id'       =>   CurrentAdmin::user()['cur_admin_id'] ,
          'module_name'    =>  'Teach' ,
          'route_url'      =>  'admin/teach/coursewareDel' ,
          'operate_method' =>  'insert' ,
          'content'        =>  json_encode(array_merge(self::$accept_data,$res)),
          'ip'             =>  $_SERVER['REMOTE_ADDR'],
          'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return ['code'=>200,'msg'=>'课件删除成功'];
    }
    //观看直播【欢拓】  lys
    public function courseAccess($data){

        // TODO:  这里替换欢托的sdk CC 直播的 这里是观看直播回放 ok
        // 这个 courseAccess
      //$MTCloud = new MTCloud();
      //$res = $MTCloud->courseAccess($data['course_id'],$data['uid'],$data['nickname'],$data['role']);
        $CCCloud = new  CCCloud();

        if($data['role'] == 'admin'){
            $room_info =$CCCloud ->get_room_live_code_for_assistant($data[ 'course_id' ], $data[ 'school_id' ], $data[ 'nickname' ], $data[ 'admin_key' ]);
        }else{
            // 从后台进入的角色只要 助教和讲师
            // todo 这里的查看回放目前没有相关的检查
            $room_info = $CCCloud ->get_room_live_code($data[ 'course_id' ], $data[ 'school_id' ], $data[ 'nickname' ], $data[ 'user_key' ]);
        }


      if(!array_key_exists('code', $room_info) && !$room_info["code"] == 0){

          return $this->response('观看直播失败，请重试！', 500);
      }
      return $room_info;
    }

     //查看回放[欢拓]  lys

    public function courseAccessPlayback($data,$viewercustominfo){
        // TODO:  这里替换欢托的sdk CC 直播的 is ok
        // bid 合作方id 这个只有欢托有 CC 没有 默认0
        if($data['bid'] > 0){
            $MTCloud = new MTCloud();
            $res = $MTCloud->courseAccessPlayback($data['course_id'],$data['uid'],$data['nickname'],$data['role']);
        }else{
            $CCCloud = new CCCloud();
            // ($course_id_ht,$school_id, $nickname, $res ->user_key,
            $res = $CCCloud ->get_room_live_recode_code($data[ 'course_id'],$data[ 'school_id'],$data['nickname'],
                $data['user_key'],$viewercustominfo);
        }



        if(!array_key_exists('code', $res) && !$res["code"] == 0){
            return $this->response('课程查看回放失败，请重试！', 500);
        }
        return $res;
    }

    public function make_password( $length = 8 ){

    // 密码字符集，可任意添加你需要的字符
      $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
      'i', 'j', 'k', 'l','m', 'n', 'o', 'p', 'q', 'r', 's',
      't', 'u', 'v', 'w', 'x', 'y','z', 'A', 'B', 'C', 'D',
      'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L','M', 'N', 'O',
      'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y','Z',
      '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '!');

      // 在 $chars 中随机取 $length 个数组元素键名
      $keys = array_rand($chars, $length);
      $password ='';
      for($i = 0; $i < $length; $i++)
      {
      // 将 $length 个数组元素连接成字符串
        $password .= $chars[$keys[$i]];
      }
      return $password;
    }


}
