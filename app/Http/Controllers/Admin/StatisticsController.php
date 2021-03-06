<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\CourseClassNumber;
use App\Models\CourseClassTeacher;
use App\Models\CourseLiveClassChild;
use App\Models\Lecturer;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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
        $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
       //获取用户网校id
//       $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
//       if($role_id !=1 ){

//       }
       //网校列表
//       $schoolList = Article::schoolANDtype($role_id);
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
           $v['phone'] = substr_replace($v['phone'],'****',3,4);
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
       return response()->json(['code'=>200,'msg'=>'获取成功','data'=>$studentList,'studentcount'=>$studentcount,'page'=>$page]);
   }
   /**
     * 學員統計導出
     */
    public function StudentExport(Request $request)
    {
        //定义一个用于判断导出的参数
        $date = date("Y-m-d");
        return Excel::download(new \App\Exports\StudentExport($request->all()), "学员统计数据-{$date}.xlsx");
    }

   /*
        * @param  课时统计
        * @param  school_id  分校id
        * @param  real_name  讲师姓名
        * @param  phone  讲师手机号
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
       $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
       $school = School::where(['id'=>$school_id])->first();

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
       //先查询课时   再查询讲师
       $keci = CourseClassTeacher::where(['is_del'=>0])->whereBetween('create_at', [$statetime, $endtime])->groupBy('teacher_id')->get();
       $dataArr=[];
       $counttime = 0;
       if(!empty($keci)){
           $keci = $keci->toArray();

           foreach ($keci as $k=>$v){

               //讲师信息    课时总数
               $teacher = Teacher::where(['id' => $v['teacher_id']])
                   ->where(function ($query) use ($data) {
                       if (!empty($data['real_name']) && $data['real_name'] != '') {
                           $query->where('real_name', 'like', '%' . $data['real_name'] . '%');
                       }
                       if (!empty($data['phone']) && $data['phone'] != '') {
                           $query->where('phone', 'like', '%' . $data['phone'] . '%');
                       }
                   })->first();
               if (!empty($teacher)) {
                   $keshicount = CourseClassTeacher::where(['is_del' => 0, 'teacher_id' => $v['teacher_id']])->whereBetween('create_at', [$statetime, $endtime])->get();
                   $keshicounts = 0;
                   if (!empty($keshicount)) {
                       $keshicount = $keshicount->toArray();
                       $ids = array_column($keshicount, 'class_id');
                       $keshicounts = CourseClassNumber::whereIn('id', $ids)->where(['is_del' => 0, 'status' => 1])->sum('class_hour');
                   }
                   $dataArr[] = [
                       'id' => $v['teacher_id'],
                       'school_name' => $school['name'],
                       'phone' => $teacher['phone'],
                       'real_name' => $teacher['real_name'],
                       'times' => $keshicounts
                   ];
                   $counttime = $counttime + $keshicounts;
               }
           }
       }
       return response()->json(['code'=>200,'msg'=>'获取成功','data'=>$dataArr,'count'=>$counttime]);

   }
   //TeacherExport
   //讲师课时导出
   public function TeacherExport(Request $request)
   {
       //定义一个用于判断导出的参数
       $date = date("Y-m-d");
       return Excel::download(new \App\Exports\TeacherClassExport($request->all()), "讲师课时统计数据-{$date}.xlsx");
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
       if(isset($data['coursesubjectOne']) && !empty($data['coursesubjectOne'])){
            $where['ld_course_livecast_resource.parent_id'] =$data['coursesubjectOne'];
       }
       if(isset($data['coursesubjectTwo']) && !empty($data['coursesubjectTwo'])){
           $where['ld_course_livecast_resource.child_id'] =$data['coursesubjectTwo'];
       }
       if(isset($data['timeRange']) && !empty($data['timeRange'])){
           $datetime = json_decode($data['timeRange'],true);
           $statr = $datetime[0] * 0.001;
           $ends = $datetime[1] * 0.001;
           $stime = date("Y-m-d",$statr);
           $etime = date("Y-m-d",$ends);
           $start_time = $stime. " 00:00:00";
           $end_time = $etime. " 23:59:59";
       }else{
           $start_time = "1970-01-01 23:59:59";
           $end_time = "3000-01-01 23:59:59";
       }
       if(!isset($data['search_name'])){
           $data['search_name'] = '';
       }
       //查询课次关联老师，通过课次，查询班号，通过班号查询直播资源id，通过直播信息拿到大小类
       $keci = CourseClassTeacher::where(['teacher_id'=>$data['id'],'is_del'=>0])->whereBetween('create_at', [$start_time, $end_time])->get()->toArray();
       $kecidetails=[];
       $kecitime=0;
       if(!empty($keci)){
           foreach ($keci as $k=>&$v){
               //课次详细信息
               $kecidetail = CourseClassNumber::leftJoin('ld_course_shift_no','ld_course_shift_no.id','=','ld_course_class_number.shift_no_id')
                   ->leftJoin('ld_course_livecast_resource','ld_course_livecast_resource.id','=','ld_course_shift_no.resource_id')
                   ->select('ld_course_livecast_resource.name as kcname','ld_course_class_number.name as kciname','ld_course_class_number.class_hour','ld_course_class_number.create_at','ld_course_livecast_resource.parent_id','ld_course_livecast_resource.child_id')
                   ->where(['ld_course_class_number.id'=>$v['class_id'],'ld_course_class_number.is_del'=>0])
                   ->where($where)
                   ->where('ld_course_livecast_resource.name','like','%'.$data['search_name'].'%')
                   ->first();
                  //查询大小类
                   $kecitime = $kecitime + $kecidetail['class_hour'];
                   if(!empty($kecidetail['parent_id'])){
                       $kecidetail['subject_name'] = Subject::where("is_del",0)->where("id",$kecidetail['parent_id'])->select("subject_name")->first()['subject_name'];
                   }else{
                       $kecidetail['subject_name']='';
                   }
                   if(!empty($kecidetail['child_id'])){
                       $kecidetail['subject_child_name'] = Subject::where("is_del",0)->where("id",$kecidetail['child_id'])->select("subject_name")->first()['subject_name'];
                   }else{
                       $kecidetail['subject_child_name']='';
                   }
               $kecidetails[] = $kecidetail;
           }
       }
       return response()->json(['code'=>200,'msg'=>'获取成功','data'=>$kecidetails,'count'=>$kecitime]);
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
