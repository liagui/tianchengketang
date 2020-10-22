<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Coures;
use App\Models\Couresmethod;
use App\Models\CourseRefResource;
use App\Models\CourseSchool;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Articletype;
use App\Models\FootConfig;
use App\Models\Admin;
use App\Models\CouresSubject;
use App\Models\Article;
use App\Models\Student;
use App\Models\OpenCourse;
use App\Models\CourseRefTeacher;
use App\Models\CourseRefOpen;
use App\Models\OpenLivesChilds;
use App\Tools\CCCloud\CCCloud;
use App\Tools\MTCloud;

class OpenCourseController extends Controller {
    protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['dns']])->first();
        // $this->school = School::where(['dns'=>$_SERVER['SERVER_NAME']])->first();
    }
    //公开课列表
    public function getList(){
        $school = $this->school;
        //自增的公开课
        $page = !isset($this->data['page']) || $this->data['page'] <=1 ? 1:$this->data['page'];
        $pagesize = !isset($this->data['pagesize']) || $this->data['pagesize'] <=1 ? 10:$this->data['pagesize'];
        $openCourse = OpenCourse::leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_open.id')
            ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_open.id')
            ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
            ->where(function($query) use ($school) {//自增
                $query->where('ld_course_open.school_id',$school['id']);
                $query->where('ld_course_open.is_del',0);
                $query->where('ld_course_open.status',1);
                // $query->where('ld_course_open_live_childs.status',3);//已结束
                $query->where('ld_lecturer_educationa.type',2);
            })->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name','ld_course_open.start_at','ld_course_open.end_at')
        ->orderBy('ld_course_open.id','desc')
        ->get()->toArray();

        //授权的公开课
        $natureOpenCourse = CourseRefOpen::leftJoin('ld_course_open','ld_course_open.id','=','ld_course_ref_open.course_id')
                ->leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_ref_open.id')
                ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_ref_open.id')
                ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
                ->where(function($query) use ($school) {//自增
                    $query->where('ld_course_ref_open.to_school_id',$school['id']);
                    $query->where('ld_course_open.is_del',0);
                    $query->where('ld_course_open.status',1);
                    // $query->where('ld_course_open_live_childs.status',3);//已结束
                    $query->where('ld_lecturer_educationa.type',2);
                })->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name','ld_course_open.start_at','ld_course_open.end_at')
            ->orderBy('ld_course_open.id','desc')
            ->get()->toArray();

        $openCourseArr = array_merge($openCourse,$natureOpenCourse);
        $openCourseArr = array_unique($openCourseArr,SORT_REGULAR);
        if(!empty($openCourseArr)){
            foreach($openCourseArr as $key=>&$v){
                if($v['start_at']>time()){
                    $v['status'] = 1;
                    $v['sort'] = 2;
                }
                if($v['start_at']<time() && $v['end_at']>time()){
                    $v['status'] = 2;
                    $v['sort'] = 1;
                }
                if($v['end_at']<time()){
                    $v['status'] = 3;
                    $v['sort'] = 3;
                }
                $v['data'] = date('Y-m-d',$v['start_at']);
                $v['time'] = date('H:i',$v['start_at']).'-'.date('H:i',$v['end_at']);;
                $v['start_at'] = date('Y-m-d H:i:s',$v['start_at']);
                $v['end_at'] = date('Y-m-d H:i:s',$v['end_at']);
            }
            array_multisort(array_column($openCourseArr,'sort'),SORT_ASC,$openCourseArr);
        }
        $start=($page-1)*$pagesize;
        $limit_s=$start+$pagesize;
        $data=[];
        for($i=$start;$i<$limit_s;$i++){
            if(!empty($openCourseArr[$i])){
                array_push($data,$openCourseArr[$i]);
            }
        }
        return response()->json(['code'=>200,'msg'=>'Success','data'=>$data,'total'=>count($openCourseArr)]);
    }




    //大家都在看
    public function hotList(){
        //自增的公开课
        $data = $this->school;
        $openCourse = OpenCourse::leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_open.id')
            ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_open.id')
            ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
            ->where(function($query) use ($data) {//自增
                $query->where('ld_course_open.school_id',$data['id']);
                $query->where('ld_course_open.is_del',0);
                $query->where('ld_course_open.status',1);
                $query->where('ld_lecturer_educationa.type',2);
            })->select('ld_course_open.id as lesson_id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name')
        ->orderBy('ld_course_open_live_childs.watch_num','desc')
        ->limit(4)
        ->get()->toArray();

        $count = count($openCourse);
        if($count<4){
              $natureOpenCourse = CourseRefOpen::leftJoin('ld_course_open','ld_course_open.id','=','ld_course_ref_open.course_id')
                ->leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_ref_open.id')
                ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_ref_open.id')
                ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
                ->where(function($query) use ($data) {//自增
                    $query->where('ld_course_open.school_id',$data['id']);
                    $query->where('ld_course_open.is_del',0);
                    $query->where('ld_course_open.status',1);
                    $query->where('ld_lecturer_educationa.type',2);
                })->select('ld_course_open.id as lesson_id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name')
            ->orderBy('ld_course_open_live_childs.watch_num','desc')
            // ->limit(4-$count)
            ->get()->toArray();

            $openCourse = array_merge($natureOpenCourse,$openCourse);
        }

        return response()->json(['code'=>200,'msg'=>'Success','data'=>$openCourse]);
    }
    //预开始
    public function preStart(){
        //自增的公开课
        $school = $this->school;
        $openCourse = OpenCourse::leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_open.id')
            ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_open.id')
            ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
            ->where(function($query) use ($school) {//自增
                // $query->where('ld_course_open_live_childs.status',1);//预开始
                $query->where('ld_course_open.start_at','>',time());
                $query->where('ld_course_open.school_id',$school['id']);
                $query->where('ld_course_open.is_del',0);
                $query->where('ld_course_open.status',1);
                $query->where('ld_lecturer_educationa.type',2);
            })
        ->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name','ld_course_open.start_at','ld_course_open.end_at','ld_course_open_live_childs.status')
        ->orderBy('ld_course_open.id','desc')
        ->get()->toArray();

        //授权的公开课
        $natureOpenCourse = CourseRefOpen::leftJoin('ld_course_open','ld_course_open.id','=','ld_course_ref_open.course_id')
                ->leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_ref_open.course_id')
                ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_ref_open.course_id')
                ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
                ->where(function($query) use ($school) {
                    $query->where('ld_course_ref_open.to_school_id',$school['id']);
                    $query->where('ld_course_open.is_del',0);
                    $query->where('ld_course_open.status',1);
                    // $query->where('ld_course_open_live_childs.status',1);//预开始
                    $query->where('ld_course_open.start_at','>',time());
                    $query->where('ld_lecturer_educationa.type',2);
                })->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name','ld_course_open.start_at','ld_course_open.end_at','ld_course_open_live_childs.status')
            ->orderBy('ld_course_open.id','desc')
            ->get()->toArray();
        $openCourseArr = array_merge($openCourse,$natureOpenCourse);
        if(!empty($openCourseArr)){
            foreach($openCourseArr as $key=>&$v){
                $v['start_at'] = date('Y-m-d H:i:s',$v['start_at']);
                $v['end_at'] = date('Y-m-d H:i:s',$v['end_at']);
            }
        }
        return response()->json(['code'=>200,'msg'=>'Success','data'=>$openCourseArr]);
    }
    //直播中
    public function underway(){
        $school = $this->school;
        //自增的公开课
        $openCourse = OpenCourse::leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_open.id')
            ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_open.id')
            ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
            ->where(function($query) use ($school) {//自增
                $query->where('ld_course_open.school_id',$school['id']);
                $query->where('ld_course_open.is_del',0);
                $query->where('ld_course_open.status',1);
                // $query->where('ld_course_open_live_childs.status',2);//进行中
                 $query->where('ld_course_open.start_at','<',time());
                  $query->where('ld_course_open.end_at','>',time());
                $query->where('ld_lecturer_educationa.type',2);
            })->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name','ld_course_open.start_at','ld_course_open.end_at')
        ->orderBy('ld_course_open.id','desc')
        ->get()->toArray();

        //授权的公开课
        $natureOpenCourse = CourseRefOpen::leftJoin('ld_course_open','ld_course_open.id','=','ld_course_ref_open.course_id')
                ->leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_ref_open.id')
                ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_ref_open.id')
                ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
                ->where(function($query) use ($school) {
                    $query->where('ld_course_ref_open.to_school_id',$school['id']);
                    $query->where('ld_course_open.is_del',0);
                    $query->where('ld_course_open.status',1);
                    // $query->where('ld_course_open_live_childs.status',2);//进行中
                    $query->where('ld_course_open.start_at','<',time());
                    $query->where('ld_course_open.end_at','>',time());
                     $query->where('ld_course_open.end_at','<',time());
                    $query->where('ld_lecturer_educationa.type',2);
                })->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name','ld_course_open.start_at','ld_course_open.end_at')
            ->orderBy('ld_course_open.id','desc')
            ->get()->toArray();
        $openCourseArr = array_merge($openCourse,$natureOpenCourse);
        if(!empty($openCourseArr)){
            foreach($openCourseArr as $key=>&$v){
                $v['start_at'] = date('Y-m-d H:i:s',$v['start_at']);
                $v['end_at'] = date('Y-m-d H:i:s',$v['end_at']);
            }
        }
        return response()->json(['code'=>200,'msg'=>'Success','data'=>$openCourseArr]);
    }
    //已结束
    //暂时先不做分页
    public function end(){
        $school = $this->school;
        //自增的公开课
        $page = !isset($this->data['page']) || $this->data['page'] <=1 ? 1:$this->data['page'];
        $pagesize = !isset($this->data['pagesize']) || $this->data['pagesize'] <=1 ? 20:$this->data['pagesize'];
        $openCourse = OpenCourse::leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_open.id')
            ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_open.id')
            ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
            ->where(function($query) use ($school) {//自增
                $query->where('ld_course_open.school_id',$school['id']);
                $query->where('ld_course_open.is_del',0);
                $query->where('ld_course_open.status',1);
                // $query->where('ld_course_open_live_childs.status',3);//已结束
                $query->where('ld_course_open.end_at','<',time());
                $query->where('ld_lecturer_educationa.type',2);
            })->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name','ld_course_open.start_at','ld_course_open.end_at')
        ->orderBy('ld_course_open.id','desc')
        ->get()->toArray();

        //授权的公开课
        $natureOpenCourse = CourseRefOpen::leftJoin('ld_course_open','ld_course_open.id','=','ld_course_ref_open.course_id')
                ->leftJoin('ld_course_open_live_childs','ld_course_open_live_childs.lesson_id','=','ld_course_ref_open.id')
                ->leftJoin('ld_course_open_teacher','ld_course_open_teacher.course_id','=','ld_course_ref_open.id')
                ->leftJoin('ld_lecturer_educationa','ld_course_open_teacher.teacher_id','=','ld_lecturer_educationa.id')
                ->where(function($query) use ($school) {//自增
                    $query->where('ld_course_ref_open.to_school_id',$school['id']);
                    $query->where('ld_course_open.is_del',0);
                    $query->where('ld_course_open.status',1);
                    // $query->where('ld_course_open_live_childs.status',3);//已结束
                    $query->where('ld_course_open.end_at','<',time());
                    $query->where('ld_lecturer_educationa.type',2);
                })->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_lecturer_educationa.real_name','ld_course_open.start_at','ld_course_open.end_at')
            ->orderBy('ld_course_open.id','desc')
            ->get()->toArray();
        $openCourseArr = array_merge($openCourse,$natureOpenCourse);
        $openCourseArr = array_unique($openCourseArr,SORT_REGULAR);
        if(!empty($openCourseArr)){
            foreach($openCourseArr as $key=>&$v){
                $v['start_at'] = date('Y-m-d H:i:s',$v['start_at']);
                $v['end_at'] = date('Y-m-d H:i:s',$v['end_at']);
            }
        }
        $start=($page-1)*$pagesize;
        $limit_s=$start+$pagesize;
        $data=[];
        for($i=$start;$i<$limit_s;$i++){
            if(!empty($openCourseArr[$i])){
                array_push($data,$openCourseArr[$i]);
            }
        }
        return response()->json(['code'=>200,'msg'=>'Success','data'=>$data,'total'=>count($openCourseArr)]);
    }
    //详情
    public function details(){
        if(!isset($this->data['course_id'])  || $this->data['course_id'] <=0){
            return response()->json(['code'=>201,'msg'=>'course_id为空或不合法']);
        }
        if(!isset($this->data['user_id'])  || $this->data['user_id'] <=0){
            return response()->json(['code'=>201,'msg'=>'user_id为空或不合法']);
        }
        if(!isset($this->data['nickname'])  || empty($this->data['nickname'])){

            $StudentData = Student::where('id',$this->data['user_id'])->select('real_name','nickname')->first();
            if(empty($StudentData)){
                $this->data['nickname']=$this->make_password();
            }else{
                $this->data['nickname'] = $StudentData['nickname'] != '' ?$StudentData['nickname']: ($StudentData['real_name'] != '' ?$StudentData['real_name']:$this->make_password());
            }

        }
        OpenLivesChilds::increment('watch_num',1);
        $openCourse = OpenLivesChilds::where(['lesson_id'=>$this->data['course_id'],'is_del'=>0,'is_forbid'=>0])->first();
        if(empty($openCourse)){
            return response()->json(['code'=>201,'msg'=>'非法请求！！！']);
        }

        $data['course_id'] = $openCourse['course_id'];
        $data['uid'] = $this->data['user_id'];
        $data['nickname'] =$this->data['nickname'];
        $data['role'] = 'user';
        if($openCourse['status'] == 1 || $openCourse['status'] == 2){
            $result=$this->courseAccess($data);
            $result['code'] = 200;
            $result['msg'] = 'success';
        }
        if($openCourse['status'] == 3){
            $result=$this->courseAccessPlayback($data);
            if($result['code'] ==1203){ //暂时没有公开课回放记录
                return response()->json($result);
            }
            $result['code'] = 200;
            $result['msg'] = 'success';
        }
        return response()->json($result);

    }
     /**
     * 启动直播
     * @param
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function startLive($course_id)
    {
        // todo 这里是替换欢托的sdk 改成cc 直播的 ok
        // 这里直接获取cc直播的播放地址
        //$MTCloud = new MTCloud();
        //$res = $MTCloud->courseLaunch($course_id);

        $CCCloud = new CCCloud();
        $res = $CCCloud ->get_room_live_code();
        if(!array_key_exists('code', $res) && !$res["code"] == 0){
            return $this->response('直播器启动失败', 500);
        }
        return $res['data'];
    }
     //观看直播【欢拓】  lys
    public function courseAccess($data){
        // TODO:  这里替换欢托的sdk CC 直播的 ok
        //$MTCloud = new MTCloud();
        $CCCloud = new CCCloud();
      //$res = $MTCloud->courseAccess($data['course_id'],$data['uid'],$data['nickname'],$data['role']);
        $res = $CCCloud ->get_room_live_code($data['course_id']);
      if(!array_key_exists('code', $res) && !$res["code"] == 0){
          return $this->response('观看直播失败，请重试！', 500);
      }
      return $res;
    }

     //查看回放[欢拓]  lys
    public function courseAccessPlayback($data){
        // TODO:  这里替换欢托的sdk CC 直播的 ok
        //$MTCloud = new MTCloud();
        $CCCloud = new CCCloud();
        //$res = $MTCloud->courseAccessPlayback($data['course_id'],$data['uid'],$data['nickname'],$data['role']);
        $res = $CCCloud ->get_room_live_recode_code($data['course_id']);
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
