<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Article;
use App\Models\CourseClassNumber;
use App\Models\CourseClassTeacher;
use App\Models\CourseLiveClassChild;
use App\Models\CourseShiftNo;
use App\Models\Lecturer;
use App\Models\LessonTeacher;
use App\Models\Live;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller {
   /*
        * @param  学员统计
        * @param  school_id  分校id
        * @param  reg_source  0官网1手机端2线下
        * @param  enrill_status  0未消费1消费
        * @param  real_name  姓名
        * @param  phone  手机号
        * @param  time  查询类型1当天2昨天3七天4当月5三个月
        * @param  timeRange
        * @param  type  1统计表2趋势图
        * @param  num  每页条数
        * @param  author  苏振文
        * @param  ctime   2020/5/7 11:19
        * return  array
        */

   public function StudentList(){
       $data = self::$accept_data;
       //获取用户网校id
       $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
       if($role_id !=1 ){
           $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
       }
       //网校列表
       $schoolList = Article::schoolANDtype($role_id);
       //每页显示的条数
       $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 20;
       $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
       $offset   = ($page - 1) * $pagesize;
       //时间
       if(!empty($data['time'])){
           if($data['time'] == 1){
               $stime = date('Y-m-d');
               $etime = date('Y-m-d');
           }
           if($data['time'] == 2){
               $stime = date("Y-m-d",strtotime("-1 day"));
               $etime = date("Y-m-d",strtotime("-1 day"));
           }
           if($data['time'] == 3){
               $stime = date("Y-m-d",strtotime("-7 day"));
               $etime = date('Y-m-d');
           }
           if($data['time'] == 4){
               $statetimestamp = mktime(0, 0, 0, date('m'), 1, date('Y'));
               $stime =date('Y-m-d', $statetimestamp);
               $endtimestamp = mktime(23, 59, 59, date('m'), date('t'), date('Y'));
               $etime = date('Y-m-d', $endtimestamp);
           }
           if($data['time'] == 5){
               $stime = date("Y-m-d", strtotime("-3 month"));
               $etime = date('Y-m-d');
           }
       }else{
           if(!empty($data['timeRange'])){
               $datetime = json_decode($data['timeRange'],true);
               $statr = $datetime[0] * 0.001;
               $ends = $datetime[1] * 0.001;
               $stime = date("Y-m-d",$statr);
               $etime = date("Y-m-d",$ends);
           }else{
               $stime = date('Y-m-d');
               $etime = date('Y-m-d');
           }
       }
       $statetime = $stime . " 00:00:00";
       $endtime = $etime . " 23:59:59";

       //总条数
       $count = Student::leftJoin('ld_school','ld_school.id','=','ld_student.school_id')
           ->where(['ld_student.is_forbid'=>1,'ld_school.is_del'=>1,'ld_school.is_forbid'=>1])
           ->where(function($query) use ($data) {
               //分校
               if(!empty($data['school_id'])&&$data['school_id'] != '' && $data['school_id'] != 0){
                   $query->where('ld_student.school_id',$data['school_id']);
               }
               //来源
               if(isset($data['source'])){
                   $query->where('ld_student.reg_source',$data['source']);
               }
               //用户类型
               if(isset($data['enroll_status'])){
                   $query->where('ld_student.enroll_status',$data['enroll_status']);
               }
               //用户姓名
               if(!empty($data['real_name'])&&$data['real_name'] != ''){
                   $query->where('ld_student.real_name','like','%'.$data['real_name'].'%');
               }
               //用户手机号
               if(!empty($data['phone'])&&$data['phone'] != ''){
                   $query->where('ld_student.phone','like','%'.$data['phone'].'%');
               }
           })->whereBetween('ld_student.create_at', [$statetime, $endtime])->count();

       $studentList = Student::select('ld_student.phone','ld_student.real_name','ld_student.create_at','ld_student.reg_source','ld_student.enroll_status','ld_school.name')
           ->leftJoin('ld_school','ld_school.id','=','ld_student.school_id')
           ->where(['ld_student.is_forbid'=>1,'ld_school.is_del'=>1,'ld_school.is_forbid'=>1])
           ->where(function($query) use ($data) {
               //分校
               if(!empty($data['school_id'])&&$data['school_id'] != ''){
                   $query->where('ld_student.school_id',$data['school_id']);
               }
               //来源
               if(isset($data['source'])){
                   $query->where('ld_student.reg_source',$data['source']);
               }
               //用户类型
               if(isset($data['enroll_status'])){
                   $query->where('ld_student.enroll_status',$data['enroll_status']);
               }
               //用户姓名
               if(!empty($data['real_name'])&&$data['real_name'] != ''){
                   $query->where('ld_student.real_name','like','%'.$data['real_name'].'%');
               }
               //用户手机号
               if(!empty($data['phone'])&&$data['phone'] != ''){
                   $query->where('ld_student.phone','like','%'.$data['phone'].'%');
               }
           })
           ->whereBetween('ld_student.create_at', [$statetime, $endtime])
           ->orderByDesc('ld_student.id')
           ->offset($offset)->limit($pagesize)->get();
       //根据时间将用户分类查询总数
       $website = 0; //官网
       $offline = 0; //线下
       $mobile = 0; //手机端
       if(!empty($studentList)){
           foreach ($studentList as $k=>$v){
               if($v['reg_source'] == 0){
                   $website++;
               }
               if($v['reg_source'] == 1){
                   $mobile++;
               }
               if($v['reg_source'] == 2){
                   $offline++;
               }
           }
       }
       //学生趋势图
       if(!empty($data['type']) && $data['type'] == 2){
           //根据时间分组，查询出人数 ，时间列表
           $lists = Student::select(DB::raw("date_format(ld_student.create_at,'%Y-%m-%d') as time"),DB::raw('count(*) as num'))
               ->leftJoin('ld_school','ld_school.id','=','ld_student.school_id')
               ->where(['ld_student.is_forbid'=>1])
               ->where(function($query) use ($data) {
                   //分校
                   if(!empty($data['school_id'])&&$data['school_id'] != ''){
                       $query->where('ld_student.school_id',$data['school_id']);
                   }
                   //来源
                   if(isset($data['source'])){
                       $query->where('ld_student.reg_source',$data['source']);
                   }
                   //用户类型
                   if(isset($data['enroll_status'])){
                       $query->where('ld_student.enroll_status',$data['enroll_status']);
                   }
                   //用户姓名
                   if(!empty($data['real_name'])&&$data['real_name'] != ''){
                       $query->where('ld_student.real_name','like','%'.$data['real_name'].'%');
                   }
                   //用户手机号
                   if(!empty($data['phone'])&&$data['phone'] != ''){
                       $query->where('ld_student.phone','like','%'.$data['phone'].'%');
                   }
               })
               ->whereBetween('ld_student.create_at', [$statetime, $endtime])
               ->groupBy(DB::raw("date_format(ld_student.create_at,'%Y-%m-%d')"))
               ->get()->toArray();
           //循环出所有日期
           $stimestamp = strtotime($stime);
           $etimestamp = strtotime($etime);
           // 计算日期段内有多少天
           $days = ($etimestamp-$stimestamp)/86400+1;
           $arr=[];
           for($i=0; $i<$days; $i++) {
               $arr[] =['time'=> date('Y-m-d', $stimestamp + (86400 * $i)),'num'=>0];
           }
           //数组处理
           $xlen = [];
           $ylen = [];
           if(!empty($lists)){
               foreach ($arr as $k=>&$v){
                   foreach ($lists as $ks=>$vs){
                       if($v['time'] == $vs['time']){
                           $v['num'] = $vs['num'];
                       }
                   }
                   $xlen[]=$v['time'];
                   $ylen[]=$v['num'];
               }
           }else{
               foreach ($arr as $k=>&$v){
                   $xlen[]=$v['time'];
                   $ylen[]=$v['num'];
               }
           }
           $studentList=[
               'xlen'=>$xlen,
               'ylen'=>$ylen
           ];
       }
       $page=[
           'pageSize'=>$pagesize,
           'page' =>$page,
           'total'=>$count
       ];
       $studentcount=[
           'website'=>$website,
           'offline' =>$offline,
           'mobile'=>$mobile,
           'count' => $count
       ];
       return response()->json(['code'=>200,'msg'=>'获取成功','data'=>$studentList,'studentcount'=>$studentcount,'page'=>$page,'schoolList'=>$schoolList[0]]);
   }
   /*
        * @param  教师课时
        * @param  school_id  分校id
        * @param  real_name  讲师姓名
        * @param  phone  讲师手机号
        * @param  time  查询类型1当天2昨天3七天4当月5三个月
        * @param  statr_time  开始时间
        * @param  end_time 结束时间
        * @param  author  苏振文
        * @param  ctime   2020/5/7 17:03
        * return  array
        */
   public function TeacherList(){
       $data = self::$accept_data;
       //每页显示的条数
       $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 20;
       $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
       $offset   = ($page - 1) * $pagesize;

       //获取用户网校id
       $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
       if($role_id !=1 ){
           $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
       }
       //网校列表
       $schoolList = Article::schoolANDtype($role_id);

       if(!empty($data['time'])){
           if($data['time'] == 1){
               $stime = date('Y-m-d');
               $etime = date('Y-m-d');
           }
           if($data['time'] == 2){
               $stime = date("Y-m-d",strtotime("-1 day"));
               $etime = date("Y-m-d",strtotime("-1 day"));
           }
           if($data['time'] == 3){
               $stime = date("Y-m-d",strtotime("-7 day"));
               $etime = date('Y-m-d');
           }
           if($data['time'] == 4){
               $statetimestamp = mktime(0, 0, 0, date('m'), 1, date('Y'));
               $stime =date('Y-m-d', $statetimestamp);
               $endtimestamp = mktime(23, 59, 59, date('m'), date('t'), date('Y'));
               $etime = date('Y-m-d', $endtimestamp);
           }
           if($data['time'] == 5){
               $stime = date("Y-m-d", strtotime("-3 month"));
               $etime = date('Y-m-d');
           }
       }else{
           $stime = date('Y-m-d');
           $etime = date('Y-m-d');
       }
       $statetime = $stime . " 00:00:00";
       $endtime = $etime . " 23:59:59";
       //总条数
       $count = Lecturer::leftJoin('ld_school','ld_school.id','=','ld_lecturer_educationa.school_id')
           ->where(function($query) use ($data) {
               //分校
               if(!empty($data['school_id'])&&$data['school_id'] != ''){
                   $query->where('ld_lecturer_educationa.school_id',$data['school_id']);
               }
               //用户姓名
               if(!empty($data['real_name'])&&$data['real_name'] != ''){
                   $query->where('ld_lecturer_educationa.real_name','like','%'.$data['real_name'].'%');
               }
               //用户手机号
               if(!empty($data['phone'])&&$data['phone'] != ''){
                   $query->where('ld_lecturer_educationa.phone','like','%'.$data['phone'].'%');
               }
           })->where(['ld_lecturer_educationa.type'=>2,'ld_lecturer_educationa.is_del'=>0,'ld_lecturer_educationa.is_forbid'=>0])
           ->orderBy('ld_lecturer_educationa.id','desc')
           ->whereBetween('ld_lecturer_educationa.create_at', [$statetime, $endtime])
           ->count();
       $teacher = Lecturer::select('ld_lecturer_educationa.id','ld_lecturer_educationa.real_name','ld_lecturer_educationa.phone','ld_lecturer_educationa.number','ld_school.name')
            ->leftJoin('ld_school','ld_school.id','=','ld_lecturer_educationa.school_id')
            ->where(function($query) use ($data) {
                //分校
                if(isset($data['school_id'])&& !empty($data['school_id'] != '')){
                    $query->where('ld_lecturer_educationa.school_id',$data['school_id']);
                }
                //用户姓名
                if(isset($data['real_name'])&&!empty($data['real_name'] != '')){
                    $query->where('ld_lecturer_educationa.real_name','like','%'.$data['real_name'].'%');
                }
                //用户手机号
                if(isset($data['phone'])&&empty($data['phone'] != '')){
                    $query->where('ld_lecturer_educationa.phone','like','%'.$data['phone'].'%');
                }
            })
            ->where(['ld_lecturer_educationa.type'=>2,'ld_lecturer_educationa.is_del'=>0,'ld_lecturer_educationa.is_forbid'=>0])
            ->orderBy('ld_lecturer_educationa.id','desc')
           ->whereBetween('ld_lecturer_educationa.create_at', [$statetime, $endtime])
           ->offset($offset)->limit($pagesize)->get();
       $counttime = 0;
       if(!empty($teacher)){
           foreach ($teacher as $K=>&$v){
               $time=0;
               $live = CourseLiveClassChild::where(['nickname'=>$v['real_name']])->where(['is_del'=>0,'is_forbid'=>0])->get()->toArray();
               if(!empty($live)){
                    foreach ($live as $ks=>$vs){
                        $times = floor(($vs['end_time'] - $vs['start_time']) / 3600);
                        $time = $time + $times;
                    }
               }
               $v['times'] = $time;
               $counttime = $counttime + $time;
           }
       }
       $pages=[
           'pageSize'=>$pagesize,
           'page' =>$page,
           'total'=>$count
       ];
       return response()->json(['code'=>200,'msg'=>'获取成功','data'=>$teacher,'count'=>$counttime,'page'=>$pages,'schoolList'=>$schoolList[0]]);
   }

   /*
        * @param  讲师授课详情
        * @param  id    讲师授课详情
        * @param  parem    大小类
        * @param  start_time 开始时间
        * @param  end_time 结束时间
        * @param  name   课程单元名称
        * @param  author  苏振文
        * @param  ctime   2020/5/8 14:26
        * return  array
        */
   public function TeacherClasshour(){
       $data = self::$accept_data;
       //讲师信息
       $teacher = Lecturer::where(['id'=>$data['id'],'is_del'=>0,'is_forbid'=>0,'type'=>2])->first();
       //总时长
       $time=0;
       $live = CourseLiveClassChild::where(['nickname'=>$teacher['real_name']])->where(['is_del'=>0,'is_forbid'=>0])->get()->toArray();
       if(!empty($live)){
           foreach ($live as $ks=>$vs){
               $times = floor(($vs['end_time'] - $vs['start_time']) / 3600);
               $time = $time + $times;
           }
       }
       $where=[];
       //学科
       if(isset($data['parem']) && !empty($data['parem'])){
           $newarr = json_decode($data['parem'],true);
            $where['ld_course_livecast_resource.parent_id'] =$newarr[0];
            if(!empty($newarr[1])){
                $where['ld_course_livecast_resource.child_id'] = $newarr[1];
            }
       }
       //开始时间和结束时间
       if(isset($data['start_time']) && !empty($data['start_time'])){
           $start_time = $data['start_time']. " 00:00:00";
       }else{
           $start_time = "3000-01-01 23:59:59";
       }
       if(isset($data['end_time']) && !empty($data['end_time'])){
           $end_time = $data['end_time']. " 00:00:00";
       }else{
           $end_time = "1970-01-01 23:59:59";
       }
       if(!isset($data['name'])){
           $data['name'] = '';
       }
       //查询课次关联老师，通过课次，查询班号，通过班号查询直播资源id，通过直播信息拿到大小类
       $keci = CourseClassTeacher::where(['teacher_id'=>$data['id'],'is_del'=>0])->get();
       if(!empty($keci)){
           $keci = $keci->toArray();
           foreach ($keci as $k=>$v){
               //课次详细信息
               $kecidetail = CourseClassNumber::leftJoin('ld_course_shift_no','ld_course_shift_no.id','=','ld_course_class_number.shift_no_id')
                   ->leftJoin('ld_course_livecast_resource','ld_course_livecast_resource.id','=','ld_course_shift_no.resource_id')
                   ->select('ld_course_livecast_resource.name as kcname','ld_course_class_number.name as kcname','ld_course_class_number.class_hour','ld_course_class_number.create_at','ld_course_livecast_resource.parent_id','ld_course_livecast_resource.child_id')
                   ->where(['ld_course_class_number.id'=>$v['class_id'],'ld_course_class_number.is_del'=>0])
                   ->where($where)
                   ->where('ld_course_livecast_resource.name','like','%'.$data['name'].'%')
                   ->whereBetween('ld_course_class_number.create_at', [$start_time, $end_time])
                   ->get()->toArray();
               //查询大小类
               foreach ($kecidetail as $ks=>&$vs){
                   $vs['subject_name'] = Subject::where("is_del",0)->where("id",$vs['parent_id'])->select("subject_name")->first()['subject_name'];
                   $vs['subject_child_name'] = Subject::where("is_del",0)->where("id",$vs['child_id'])->select("subject_name")->first()['subject_name'];
               }
           }
       }

   }






   /*
        * @param  直播统计
        * @param
        * @param  author  苏振文
        * @param  ctime   2020/5/8 14:50
        * return  array
        */
   public function LiveList(){
       $data=[
           'school_id'=>isset($_POST['school_id'])?$_POST['school_id']:'',
           'real_name'=>isset($_POST['real_name'])?$_POST['real_name']:'',
           'phone'=>isset($_POST['phone'])?$_POST['phone']:'',
           'state_time'=>isset($_POST['state_time'])?$_POST['state_time']:'',
           'end_time'=>isset($_POST['end_time'])?$_POST['end_time']:'',
           'num'=>isset($_POST['num'])?$_POST['num']:20
       ];

   }
   /*
        * @param  直播详情
        * @param  $user_id     参数
        * @param  author  苏振文
        * @param  ctime   2020/5/8 20:24
        * return  array
        */
   public function LiveDetails(){
   }
}
