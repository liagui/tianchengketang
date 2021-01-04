<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Collection;
use App\Models\Coures;
use App\Models\Couresmethod;
use App\Models\Couresteacher;
use App\Models\Course;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\Region;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentCollect;
use App\Models\StudentMessage;
use App\Models\Teacher;
use App\Models\MyMessage;
use App\Models\Comment;
use App\Models\Answers;
use App\Models\AnswersReply;
use App\Models\Video;
use App\Models\VideoLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller {
    protected $school;
    protected $data;
    protected $userid;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['school_dns']])->first(); //改前
        //$this->school = $this->getWebSchoolInfo($this->data['school_dns']); //改后
        $this->userid = isset($_REQUEST['user_info']['user_id'])?$_REQUEST['user_info']['user_id']:0;
    }
    /*
         * @param  个人信息
         * @param  author  苏振文
         * @param  ctime   2020/7/8 16:32
         * return  array
         */
    public function userDetail(){
        $user = Student::where(['id'=>$this->userid,'is_forbid'=>1])->orderBy('id','desc')->first();
        if(empty($user)){
            return response()->json(['code' => 201 , 'msg' => '成员不存在']);
        }
        $user = $user->toArray();
        unset($user['token']);
        unset($user['password']);
        //查询省
        if($user['province_id'] != ''){
            $province = Region::where(['id'=>$user['province_id']])->first();
            $user['province'] = $province['name'];
        }
        if($user['city_id'] != ''){
            $city = Region::where(['id'=>$user['city_id']])->first();
            $user['city'] = $city['name'];
        }
        return response()->json(['code' => 200 , 'msg' => '查询成功','data'=>$user]);
    }
    //用户更改手机号
    public function userUpPhone(){
        if(!isset($this->data['phone'])|| empty($this->data['phone'])){
            return response()->json(['code' => 201 , 'msg' => '手机号不能为空']);
        }
        //判断验证码是否为空
        if(!isset($this->data['verifycode']) || empty($this->data['verifycode'])){
            return response()->json(['code' => 201 , 'msg' => '请输入验证码']);
        }
        //验证码合法验证
        $verify_code = Redis::get('user:register:'.$this->data['phone']);
        if(!$verify_code || empty($verify_code)){
            return ['code' => 201 , 'msg' => '请先获取验证码'];
        }
        //判断验证码是否一致
        if($verify_code != $this->data['verifycode']){
            return ['code' => 202 , 'msg' => '验证码错误'];
        }
        $first = Student::where(['phone'=>$this->data['phone']])->orderBy('id','desc')->first();
        if(!empty($first)){
            $first = $first->toArray();
            if($first['is_forbid'] == 2){
                return response()->json(['code' => 201 , 'msg' => '手机号已被禁用']);
            }
            if($first['is_forbid'] == 3){
                return response()->json(['code' => 201 , 'msg' => '手机号已被删除']);
            }
            return response()->json(['code' => 201 , 'msg' => '手机号已被占用']);
        }
        if(!preg_match('#^13[\d]{9}$|^14[\d]{9}$|^15[\d]{9}$|^17[\d]{9}$|^18[\d]{9}|^16[\d]{9}$#', $this->data['phone'])) {
            return response()->json(['code' => 202 , 'msg' => '手机号不合法']);
        }
        $up = Student::where(['id'=>$this->userid])->update(['phone'=>$this->data['phone']]);
        if($up){
            return response()->json(['code' => 200 , 'msg' => '修改成功']);
        }else{
            return response()->json(['code' => 203 , 'msg' => '修改失败']);
        }
    }
    //用户更改邮箱
    public function userUpEmail(){
        if(!isset($this->data['email']) || empty($this->data['email'])){
            return response()->json(['code' => 201 , 'msg' => '邮箱地址不能为空']);
        }
        if (filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {
            $up = Student::where(['id'=>$this->userid])->update(['email'=>$this->data['email']]);
            if($up){
                return response()->json(['code' => 200 , 'msg' => '修改成功']);
            }else{
                return response()->json(['code' => 202 , 'msg' => '修改失败']);
            }
        }else{
            return response()->json(['code' => 201 , 'msg' => '邮箱格式不正确']);
        }
    }
    //地区三级联动
    public function address(){
        $this->data['region_id'] = isset($this->data['region_id'])?$this->data['region_id']:0;
        $address = $this->getRegionDataList($this->data);
        return response()->json($address);
    }
    //用户修改基本信息
    public function userUpDetail(){
        if(!isset($this->data['real_name']) || empty($this->data['real_name'])){
            return response()->json(['code' => 201 , 'msg' => '姓名不能为空']);
        }
        $res['real_name'] = $this->data['real_name'];
        $res['update_at'] = date('Y-m-d H:i:s');
        if(isset($this->data['sex'])){
            $res['sex'] = $this->data['sex'];
        }
        if(isset($this->data['nickname'])){
            $res['nickname'] = $this->data['nickname'];
        }
        if(isset($this->data['age'])){
            $res['age'] = $this->data['age'];
        }
        if(isset($this->data['papers_type'])){
            $res['papers_type'] = $this->data['papers_type'];
        }
        if(isset($this->data['educational'])){
        }
        if(isset($this->data['papers_num'])){
            $res['papers_num'] = $this->data['papers_num'];
        }
        if(isset($this->data['address_locus'])){
            $res['address_locus'] = $this->data['address_locus'];
        }
        if(isset($this->data['birthday'])){
            $res['birthday'] = $this->data['birthday'];
        }
        if(isset($this->data['head_icon'])){
            $res['head_icon'] = $this->data['head_icon'];
        }
        if(isset($this->data['sign'])){
            $res['sign'] = $this->data['sign'];
        }
        $up = Student::where(['id'=>$this->userid])->update($res);
        if($up){
            return response()->json(['code' => 200 , 'msg' => '修改成功']);
        }else{
            return response()->json(['code' => 203 , 'msg' => '修改失败']);
        }
    }
    //用户修改联系方式
    public function userUpRelation(){
        $res['update_at'] = date('Y-m-d H:i:s');
        if(isset($this->data['family_phone']) || !empty($this->data['family_phone'])){
            $res['family_phone'] = $this->data['family_phone'];
        }
        if(isset($this->data['office_phone']) || !empty($this->data['office_phone'])){
            $res['office_phone'] = $this->data['office_phone'];
        }
        if(isset($this->data['contact_people']) || !empty($this->data['contact_people'])){
            $res['contact_people'] = $this->data['contact_people'];
        }
        if(isset($this->data['contact_phone']) || !empty($this->data['contact_phone'])){
            $res['contact_phone'] = $this->data['contact_phone'];
        }
        if(isset($this->data['email']) || !empty($this->data['email'])){
            $res['email'] = $this->data['email'];
        }
        if(isset($this->data['qq']) || !empty($this->data['qq'])){
            $res['qq'] = $this->data['qq'];
        }
        if(isset($this->data['wechat']) || !empty($this->data['wechat'])){
            $res['wechat'] = $this->data['wechat'];
        }
        $up = Student::where(['id'=>$this->userid])->update($res);
        if($up){
            return response()->json(['code' => 200 , 'msg' => '修改成功']);
        }else{
            return response()->json(['code' => 203 , 'msg' => '修改失败']);
        }
    }
    //用户修改头像
    public function userUpImg(){
        if(!isset($this->data['head_icon']) || empty($this->data['head_icon'])){
            return response()->json(['code' => 201 , 'msg' => '头像为空']);
        }
        $up = Student::where(['id'=>$this->userid])->update(['user_icon'=>$this->data['head_icon']]);
        if($up){
            return response()->json(['code' => 200 , 'msg' => '修改成功']);
        }else{
            return response()->json(['code' => 203 , 'msg' => '修改失败']);
        }
    }
    //用户修改密码
    public function userUpPass(){
        if(!isset($this->data['old_pass']) || empty($this->data['old_pass'])){
            return response()->json(['code' => 201 , 'msg' => '请输入旧密码']);
        }
        if(!isset($this->data['new_pass']) || empty($this->data['new_pass'])){
            return response()->json(['code' => 201 , 'msg' => '请输入新密码']);
        }
        if(!isset($this->data['news_pass']) || empty($this->data['news_pass'])){
            return response()->json(['code' => 201 , 'msg' => '请再次输入新密码']);
        }
        if(strlen($this->data['new_pass']) <6 || strlen($this->data['new_pass']) >15){
            return response()->json(['code' => 201 , 'msg' => '密码长度为6到15']);
        }
        $user = Student::where(['id'=>$this->userid])->first()->toArray();
        //验证密码是否合法
        if(password_verify($this->data['old_pass']  , $user['password']) === false){
            return response()->json(['code' => 203 , 'msg' => '密码错误']);
        }
        if($this->data['new_pass'] != $this->data['news_pass']){
            return response()->json(['code' => 202 , 'msg' => '两次输入不一致']);
        }
        $news_pass = password_hash($this->data['new_pass'] , PASSWORD_DEFAULT);
        $up = Student::where(['id'=>$this->userid])->update(['password'=>$news_pass]);
        if($up){
            return response()->json(['code' => 200 , 'msg' => '修改成功']);
        }else{
            return response()->json(['code' => 203 , 'msg' => '修改失败']);
        }
    }
    /*
         * @param  个人信息
         * @param  author  苏振文
         * @param  ctime   2020/7/9 19:38
         * return  array
         */

    //我的收藏
    public function myCollect(){
        $methods = isset($this->data['method'])?$this->data['method']:0;
        $collect = Collection::where(['student_id'=>$this->userid,'is_del'=>0])->get();
        $coursearr=[];
        if(!empty($collect)) {
            foreach ($collect as $k => &$v) {
                if ($v['nature'] == 1) {
                    $course = CourseSchool::where(['id' => $v['lesson_id'], 'is_del' => 0, 'status' => 1])->first();
                    $courseid = $course['course_id'];
                    $course['nature'] = 1;
                } else {
                    $course = Coures::where(['id' => $v['lesson_id'], 'is_del' => 0, 'status' => 1])->first();
                    $courseid = $course['id'];
                    $course['nature'] = 0;
                }
                $method = Couresmethod::select('method_id')->where(['course_id' => $courseid, 'is_del' => 0])
                    ->where(function ($query) use ($methods) {
                        if ($methods != '' && $methods != 0) {
                            $query->where('method_id', $methods);
                        }
                    })->get()->toArray();
//                $coursemethod= array_column($method, 'method_id');
                if (!empty($method)) {
                    foreach ($method as $key => &$val) {
                        if ($val['method_id'] == 1) {
                            $val['method_name'] = '直播';
                        }
                        if ($val['method_id'] == 2) {
                            $val['method_name'] = '录播';
                        }
                        if ($val['method_id'] == 3) {
                            $val['method_name'] = '其他';
                        }
                    }
                    $course['method'] = $method;
                    $coursearr[] = $course;
                } else {
                    unset($collect[$k]);
                }
            }
        }
        return response()->json(['code' => 200 , 'msg' => '获取成功','data'=>$coursearr]);
    }

    //我的课程
    public function myCourse(){
        $date = date('Y-m-d H:i:s');
        $order = Order::where(['student_id'=>$this->userid,'status'=>2,'school_id'=>$this->school['id']])
            ->where('validity_time','>',$date)
            ->whereIn('pay_status',[3,4])
            ->get()->toArray();
        $courses = [];
        if(!empty($order)){
            foreach ($order as $k=>$v){
                if($v['nature'] == 1){
                    $course = CourseSchool::where(['id'=>$v['class_id'],'is_del'=>0,'status'=>1])->first();
                    if(!empty($course)){
                        //购买数
                        $ordernum = Order::where(['class_id'=>$v['class_id'],'nature'=>1])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count();
                        $course['buy_num'] = $ordernum + $course['buy_num'];
                        $course['nature'] = 1;
                        $ordernum = Order::where(['class_id'=>$v['class_id'],'nature'=>1])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count();
                        $course['buy_num'] = $ordernum + $course['buy_num'];
                        //查讲师
                        $teacherlist = Couresteacher::where(['course_id'=>$course['course_id'],'is_del'=>0])->get();
                        $string=[];
                        if(!empty($teacherlist)){
                            foreach ($teacherlist as $ks=>$vs){
                                $teacher = Teacher::where(['id'=>$vs['teacher_id'],'is_del'=>0,'type'=>2])->first();
                                $string[] = $teacher['real_name'];
                            }
                            $course['teachername'] = implode(',',$string);
                        }else{
                            $course['teachername']='';
                        }
                        //查授课方式
                        $method = Couresmethod::select('method_id')->where(['course_id' => $course['course_id'], 'is_del' => 0])->get()->toArray();
                        if (!empty($method)) {
                            foreach ($method as $key => &$val) {
                                if ($val['method_id'] == 1) {
                                    $val['method_name'] = '直播';
                                }
                                if ($val['method_id'] == 2) {
                                    $val['method_name'] = '录播';
                                }
                                if ($val['method_id'] == 3) {
                                    $val['method_name'] = '其他';
                                }
                            }
                            $course['method'] = $method;
                        }
                        //查询有效期
                        $date1 = time();
                        $date2 = strtotime($v['validity_time']);
                        if($date1 >= $date2){
                            $course['day'] = '已过期';
                        }else{
                            $interval = $date2 -$date1;
                            $a = $interval / 86400;
                            $day = floor($a);
                            if($course['expiry'] == 0){
                                $course['day'] = '无期限';
                            }else{
                                if($day > 0){
                                    $course['day'] = $day.'天';
                                }else{
                                    $course['day'] = '已过期';
                                }
                            }
                        }
                        $courses[] = $course;
                    }
                }else {
                    $course = Coures::where(['id' => $v['class_id'], 'is_del' => 0, 'status' => 1])->first();
                    if (!empty($course)) {
                        $course['nature'] = 0;
                        $ordernum = Order::where(['class_id'=>$v['class_id'],'nature'=>0])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count();
                        $course['buy_num'] = $ordernum + $course['buy_num'];
                        //查讲师
                        $teacherlist = Couresteacher::where(['course_id' => $v['class_id'], 'is_del' => 0])->get();
                        $string = [];
                        if (!empty($teacherlist)) {
                            foreach ($teacherlist as $ks => $vs) {
                                $teacher = Teacher::where(['id' => $vs['teacher_id'], 'is_del' => 0, 'type' => 2])->first();
                                $string[] = $teacher['real_name'];
                            }
                            $course['teachername'] = implode(',', $string);
                        } else {
                            $course['teachername'] = '';
                        }
                        //查授课方式
                        $method = Couresmethod::select('method_id')->where(['course_id' => $v['class_id'], 'is_del' => 0])->get()->toArray();
                        if (!empty($method)) {
                            foreach ($method as $key => &$val) {
                                if ($val['method_id'] == 1) {
                                    $val['method_name'] = '直播';
                                }
                                if ($val['method_id'] == 2) {
                                    $val['method_name'] = '录播';
                                }
                                if ($val['method_id'] == 3) {
                                    $val['method_name'] = '其他';
                                }
                            }
                            $course['method'] = $method;
                        }
                        //查询有效期
                        $date1 = time();
                        $date2 = strtotime($v['validity_time']);
                        if($date1 >= $date2){
                            $course['day'] = '已过期';
                        }else{
                            $interval = $date2 -$date1;
                            $a = $interval / 86400;
                            $day = floor($a);
                            if($course['expiry'] == 0){
                                $course['day'] = '无期限';
                            }else{
                                if($day > 0){
                                    $course['day'] = $day.'天';
                                }else{
                                    $course['day'] = '已过期';
                                }
                            }
                        }
                        $courses[] = $course;
                    }
                }
            }
        }
        return response()->json(['code' => 200 , 'msg' => '获取成功','data'=>$courses]);
    }
    //我的订单  status 1已完成2未完成3已失效
    public function myOrder(){
        $status = isset($this->data['status'])?$this->data['status']:'';
        $order = Order::where(['student_id'=>$this->userid])
            ->where(function($query) use ($status) {
                //状态判断
                if($status == 1){
                    $query->where('status',1)
                    ->orwhere('status',2);
                }
                if($status == 2){
                    $query->where('status',0);
                }
                if($status == 3){
                    $query->where('status',5);
                }
            })
            ->where('status','!=',3)
            ->where('status','!=',4)
            ->orderByDesc('id')->get()->toArray();

        if(!empty($order)){
            foreach ($order as $k=>&$v){
                if($v['nature'] == 1){
                    $course = CourseSchool::select('title')->where(['id'=>$v['class_id'],'is_del'=>0])->first();
                }else{
                    $course = Coures::select('title')->where(['id'=>$v['class_id'],'is_del'=>0])->first();
                }
                $v['title'] = isset($course['title'])?$course['title']:'';
            }
        }
        //所有总数
        $success1 = Order::where(['student_id'=>$this->userid,'status'=>1])->count();
        $success2 = Order::where(['student_id'=>$this->userid,'status'=>2])->count();
        $success = $success1 + $success2;
        $unfinished = Order::where(['student_id'=>$this->userid,'status'=>0])->count();
        $error = Order::where(['student_id'=>$this->userid,'status'=>5])->count();
        $count = [
            0=>!empty($success)?$success:0,
            1=>!empty($unfinished)?$unfinished:0,
            2=>!empty($error)?$error:0
        ];
        return response()->json(['code' => 200, 'msg' => '获取成功','data'=>$order,'count'=>$count]);
    }
    //订单单条详情
    public function orderFind(){
        if(!isset($this->data['id'])||empty($this->data['id'])){
            return response()->json(['code' => 201 , 'msg' => '订单id为空']);
        }
        $order = Order::where('id',$this->data['id'])->first()->toArray();
        if(!empty($order)){
            if($order['nature'] == 1){
                $course = CourseSchool::select('title')->where(['id'=>$order['class_id'],'is_del'=>0,'status'=>1])->first()->toArray();
            }else{
                $course = Coures::select('title')->where(['id'=>$order['class_id'],'is_del'=>0,'status'=>1])->first()->toArray();
            }
            $order['title'] = isset($course['title'])?$course['title']:'';
        }
        return response()->json(['code' => 200, 'msg' => '获取成功','data'=>$order]);
    }

    /*
     * @param  description   用户退出登录接口
     * @param author    dzj
     * @param ctime     2020-10-15
     * return string
     */
    public function doLoginOut(){
        //获取用户id
        $user_id =   $this->data['user_info']['user_id'];
        //获取用户token
        $token   =   $this->data['user_info']['user_token'];
        //获取手机号
        $phone   =   $this->data['user_info']['phone'];

        //开启事务
        DB::beginTransaction();
        try {

            //更新用户信息
            $rs = Student::where("id" , $user_id)->update(['login_at' => date('Y-m-d H:i:s')]);
            if($rs && !empty($rs)){
                //hash中的token的key值
                $token_key   = "user:regtoken:pc:".$token;
                $token_phone = "user:regtoken:pc:".$phone;

                //删除redis中用户token
                Redis::del($token_key);
                Redis::del($token_phone);

                //事务提交
                DB::commit();
                return response()->json(['code' => 200 , 'msg' => '退出成功']);
            } else {
                //事务回滚
                DB::rollBack();
                return response()->json(['code' => 203 , 'msg' => '退出失败']);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

	/*
         * @param  myMessage     我的消息列表
         * @param  $user_token   用户token
         * @param  $school_dns   网校域名bitian
         * @param  $page
         * @param  $pagesize
         * @param  author  sxh
         * @param  ctime   2020/11/2
         * return  array
         */
    public function myMessageOld()
    {

        $pagesize = isset($this->data['pagesize']) && $this->data['pagesize'] > 0 ? $this->data['pagesize'] : 20;
        $page     = isset($this->data['page']) && $this->data['page'] > 0 ? $this->data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

		$message_count = StudentMessage::query()
            ->where(['student_id'=>$this->userid,'school_id'=>$this->school['id']])
            ->count();
        $meMessageList = StudentMessage::query()
            ->where(['student_id'=>$this->userid,'school_id'=>$this->school['id']])
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($pagesize)
            ->get();
        // 查询 数据
        if($meMessageList ->count() > 0){
            $meMessageList->toArray();
        }

        foreach($meMessageList as $k =>$message_info){
            $teacherlist = Couresteacher::where(['course_id' => $message_info['course_id'], 'is_del' => 0])->get();
            $order_info  = Order::query()->where("id","=",$message_info['order_id'])->get();
            if($message_info['nature'] == 0){
                $course_info = Course::query()->where("id","=",$message_info['course_id']) ->first();
            }else{
                $course_info = CourseSchool::query()->where("id","=",$message_info['course_id'])->first();
            }

            // 设定 web 的 返回值
            $ret[$k]=array(
                        'course_name'       =>   $course_info['title']  ,
                        'course_id'         =>   $message_info['course_id'] ,
                        'course_cover'      =>   $course_info['cover'] ,
                        'course_expiry'     =>   $course_info['expiry'] ,
                        'watch_num'         =>   $course_info['watch_num'] ,
                        'live_time'         =>   $course_info['start_time'] ,
                        'time'              =>   $message_info['msg_time'] ,
                        'uid'               =>  $this->userid ,
                        'school_id'         =>  $this->school['id'] ,
                        'order_id'          =>  $message_info['order_id'] ,
                        'validity_time'     =>  $course_info['validity_time'] ,
                        'nature'            =>  $message_info['nature'] ,
                        'prompt'            =>  $message_info['msg_type'],
                        'create_at'         =>  $message_info['msg_time']
            );


            $string = [];
            if (!empty($teacherlist)) {
                foreach ($teacherlist as $ks => $teacher_info) {
                    $teacher = Teacher::where(['id' => $teacher_info['teacher_id'], 'is_del' => 0, 'type' => 2])->first();
                    $string[] = $teacher['real_name'];
                }
                $ret[$k]['teachername'] = implode(',', $string);
            } else {
                $ret[$k]['teachername'] = '';
            }
            $date1 = time();
            $date2 = strtotime($course_info['validity_time']);
            if ($date1 >= $date2) {
                $ret[$k]['day'] = '已过期';
            } else {
                $interval = $date2 - $date1;
                $d = floor($interval/3600/24);
                $h = floor(($interval%(3600*24))/3600);
                $m = floor(($interval%(3600*24))%3600/60);
                $s = floor(($interval%(3600*24))%60);
                if ($course_info['course_expiry'] == 0) {
                    $ret[$k]['day'] = '无期限';
                } else {
                    if ($s > 0) {
                        $ret[$k]['day'] = $d.'天'.$h.'小时'.$m.'分'.$s.'秒';
                    } else {
                        $ret[$k]['day'] = '已过期';
                    }
                }
            }
            $ret[$k]['live_day'] = date('Y-m-d H:i:s',$course_info['start_time']);
        }

        return ['code' => 200, 'msg' => '获取我的消息列表成功', 'data' => $ret, 'count' => $message_count];
//        //return ['code' => 200, 'msg' => '获取我的消息列表成功', 'data' => $meMessageList];
//        $meMessage = MyMessage::orderByDesc('id')->pluck('order_id')->toArray();
//        $meMessage = array_unique($meMessage);
//        $list = $this->getMyMessageInfo();
//        foreach($list as $k => $message_info){
//            if(!in_array($message_info['order_id'],$meMessage)){
//                $list_time = strtotime("-2 hours",$message_info['start_time']);
//                if(time() > $list_time){
//                    //添加我的消息
//                    MyMessage::insertGetId([
//                        'course_name'       =>   $message_info['title']  ,
//                        'course_id'         =>   $message_info['id'] ,
//                        'course_cover'      =>   $message_info['cover'] ,
//                        'course_expiry'     =>   $message_info['expiry'] ,
//                        'watch_num'         =>   $message_info['watch_num'] ,
//                        'live_time'         =>   $message_info['start_time'] ,
//                        'time'              =>  $list_time ,
//                        'uid'               =>  $this->userid ,
//                        'school_id'         =>  $this->school['id'] ,
//                        'order_id'          =>  $message_info['order_id'] ,
//                        'validity_time'     =>  $message_info['validity_time'] ,
//                        'nature'            =>  $message_info['nature'] ,
//                        'prompt'            =>  1 ,
//                        'create_at'         =>  date('Y-m-d H:i:s')
//                    ]);
//                }
//                if(time() > $message_info['start_time']){
//                    //添加我的消息
//                    MyMessage::insertGetId([
//                        'course_name'       =>   $message_info['title']  ,
//                        'course_id'         =>   $message_info['id'] ,
//                        'course_cover'      =>   $message_info['cover'] ,
//                        'course_expiry'     =>   $message_info['expiry'] ,
//                        'watch_num'         =>   $message_info['watch_num'] ,
//                        'live_time'         =>   $message_info['start_time'] ,
//                        'time'              =>  $list_time ,
//                        'uid'               =>  $this->userid ,
//                        'school_id'         =>  $this->school['id'] ,
//                        'order_id'          =>  $message_info['order_id'] ,
//                        'validity_time'     =>  $message_info['validity_time'] ,
//                        'nature'            =>  $message_info['nature'] ,
//                        'prompt'              =>  2 ,
//                        'create_at'         =>  date('Y-m-d H:i:s')
//                    ]);
//                }
//            }
//        }

    }

	public function myMessageDetail()
    {
        $id = $this->data['id'];
        if(empty($id) || !isset($id)){
            return response()->json(['code' => 201, 'msg' => '消息id为空']);
        }
        $meMessageList = StudentMessage::query()->where(['student_id'=>$this->userid,'id'=>$id])->first();
        if(!$meMessageList){
            return response()->json(['code' => 201, 'msg' => '消息不存在']);
        }
        // 更改 消息 的 状态
		StudentMessage::query()->where(['student_id'=>$this->userid,'id'=>$id])->update(['msg_status' => 2]);
        $teacherlist = Couresteacher::where(['course_id' => $meMessageList['course_id'], 'is_del' => 0])->get();

        $order_info  = Order::query()->where("id","=",$meMessageList['order_id'])->get();
        if($meMessageList['nature'] == 0){
            $course_info = Course::query()->where("id","=",$meMessageList['course_id']) ->get();
        }else{
            $course_info = CourseSchool::query()->where("id","=",$meMessageList['course_id'])->get();
        }
        // 设定 web 的 返回值
        $meMessageList=array(
            'course_name'       =>   $course_info['title']  ,
            'course_id'         =>   $meMessageList['course_id'] ,
            'course_cover'      =>   $course_info['cover'] ,
            'course_expiry'     =>   $course_info['expiry'] ,
            'watch_num'         =>   $course_info['watch_num'] ,
            'live_time'         =>   $course_info['start_time'] ,
            'time'              =>   $meMessageList['msg_time'] ,
            'uid'               =>  $this->userid ,
            'school_id'         =>  $this->school['id'] ,
            'order_id'          =>  $meMessageList['order_id'] ,
            'validity_time'     =>  $course_info['validity_time'] ,
            'nature'            =>  $meMessageList['nature'] ,
            'prompt'            =>  $meMessageList['msg_type']
        );


        $string = [];
        if (!empty($teacherlist)) {
            foreach ($teacherlist as $ks => $vs) {
                $teacher = Teacher::where(['id' => $vs['teacher_id'], 'is_del' => 0, 'type' => 2])->first();
                $string[] = $teacher['real_name'];
            }
            $meMessageList['teachername'] = implode(',', $string);
        } else {
            $meMessageList['teachername'] = '';
        }
        $date1 = time();
        $date2 = strtotime($course_info['validity_time']);
        if ($date1 >= $date2) {
            $meMessageList['day'] = '已过期';
        } else {
            $interval = $date2 - $date1;
            $d = floor($interval/3600/24);
            $h = floor(($interval%(3600*24))/3600);
            $m = floor(($interval%(3600*24))%3600/60);
            $s = floor(($interval%(3600*24))%60);
            if ($meMessageList['course_expiry'] == 0) {
                $meMessageList['day'] = '无期限';
            } else {
                if ($s > 0) {
                    $meMessageList['day'] = $d.'天'.$h.'小时'.$m.'分'.$s.'秒';
                } else {
                    $meMessageList['day'] = '已过期';
                }
            }
        }
        $meMessageList['live_day'] = date('Y-m-d H:i:s',$course_info['start_time']);
        return ['code' => 200, 'msg' => '获取我的消息详情成功', 'data' => $meMessageList ];
    }

	/*
     * @param  myCommen    我的评论列表
     * @param  参数说明
     *      user_token   用户token
     *      page
     *      pagesize
     * @param  author          sxh
     * @param  ctime           2020-11-3
     * return  array
     */
    public function myCommen(){
        try {

            //每页显示的条数
            $pagesize = isset($this->data['pagesize']) && $this->data['pagesize'] > 0 ? $this->data['pagesize'] : 20;
            $page     = isset($this->data['page']) && $this->data['page'] > 0 ? $this->data['page'] : 1;
            $offset   = ($page - 1) * $pagesize;
            //获取列表
            $list = Comment::leftJoin('ld_student','ld_student.id','=','ld_comment.uid')
                ->leftJoin('ld_school','ld_school.id','=','ld_comment.school_id')
                ->where(['ld_comment.status'=>1,'ld_comment.uid'=>$this->userid])
                ->select('ld_comment.id','ld_comment.create_at','ld_comment.content','ld_comment.course_name','ld_comment.score','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon')
                ->orderByDesc('ld_comment.create_at')->offset($offset)->limit($pagesize)
                ->get()->toArray();
            foreach($list as $k=>$v){
                $list[$k]['user_name'] = empty($v['real_name']) ? $v['nickname'] : $v['real_name'];
            }
            return ['code' => 200 , 'msg' => '获取评论列表成功' , 'data' => ['list' => $list , 'total' => count($list) , 'pagesize' => $pagesize , 'page' => $page]];

        } catch (\Exception $ex) {
            return ['code' => 204, 'msg' => $ex->getMessage()];
        }
    }

	/*
         * @param  answersList    获取问答列表-我的提问
         * @param  $page
         * @param  $pagesize
         * @param  author  sxh
         * @param  ctime   2020/11/4
         * return  array
         */
    public function answersList()
    {
        //每页显示的条数
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset = ($page - 1) * $pagesize;

        //获取列表
        $list = Answers::where(['ld_answers.is_check' => 1,'uid'=>$this->userid])
            ->select('ld_answers.id', 'ld_answers.create_at', 'ld_answers.content', 'ld_answers.title', 'ld_answers.is_check', 'ld_answers.is_top')
            ->orderByDesc('ld_answers.is_top')
            ->orderByDesc('ld_answers.create_at')
            ->offset($offset)->limit($pagesize)
            ->get()->toArray();
        foreach ($list as $k=>$v){
            $list[$k]['count'] = AnswersReply::where(['status'=>1,'answers_id'=>$v['id']])->count();
        }
        return ['code' => 200, 'msg' => '获取问答-我的提问成功', 'data' => ['list' => $list, 'total' => count($list), 'pagesize' => $pagesize, 'page' => $page]];
    }

    /*
         * @param  replyList    获取问答列表-我的回答
         * @param  $page
         * @param  $pagesize
         * @param  author  sxh
         * @param  ctime   2020/11/4
         * return  array
         */
    public function replyList()
    {
        //每页显示的条数
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset = ($page - 1) * $pagesize;

        //获取列表
        $list = AnswersReply::leftJoin('ld_answers','ld_answers.id','=','ld_answers_reply.answers_id')
            ->where(['ld_answers_reply.status'=>1,'ld_answers_reply.user_id'=>$this->userid,'ld_answers_reply.user_type'=>1])
            ->select('ld_answers_reply.answers_id','ld_answers_reply.content as reply_con','ld_answers.id','ld_answers.title','ld_answers.content as answers_con','ld_answers.create_at')
            ->orderByDesc('ld_answers_reply.create_at')
			->offset($offset)->limit($pagesize)
            ->get()->toArray();
		$list_count = AnswersReply::leftJoin('ld_answers','ld_answers.id','=','ld_answers_reply.answers_id')
            ->where(['ld_answers_reply.status'=>1,'ld_answers_reply.user_id'=>$this->userid,'ld_answers_reply.user_type'=>1])->count();
        //$res = $this->more_array_unique($list);
        return ['code' => 200, 'msg' => '获取问答-我的回答成功', 'data' => ['list' => $list,  'count' => $list_count]];
    }

    function more_array_unique($arr=array()){
        $array=[];
		$arrs = [];
        foreach($arr as $key=>$v){
            if(!in_array($v['answers_id'],$array)){
                $array[]=$v['answers_id'];
                $arrs[] = $v;
            }
        }
        return $arrs;
    }
	//dingdan
    public function getMyMessageInfo(){
        $order = Order::where(['ld_order.student_id'=>$this->userid,'ld_order.status'=>2,'oa_status'=>1])->select('id as order_id','class_id','nature','validity_time')->orderByDesc('id')->get();
        $order = $this->array_unique_fb($order->toArray(),'class_id');
		$coures_list = [];
		$coures_school_list = [];
        foreach($order as $k => $v){
            //自增课程

            if($v['nature'] == 0) {
                $order[$k]['coures'] = Coures::leftJoin('ld_course_method', 'ld_course_method.course_id', '=', 'ld_course.id')
                    ->leftJoin('ld_course_live_resource','ld_course_live_resource.course_id','=','ld_course.id')
                    ->leftJoin('ld_course_shift_no','ld_course_shift_no.id','=','ld_course_live_resource.shift_id')
                    ->leftJoin('ld_course_class_number','ld_course_class_number.shift_no_id','=','ld_course_shift_no.id')
                    ->leftJoin('ld_course_live_childs','ld_course_live_childs.class_id','=','ld_course_class_number.id')
                    ->where(['ld_course.id' => $v['class_id'], 'ld_course.is_del' => 0, 'ld_course.status' => 1, 'ld_course_method.method_id' => 1])
                    ->select('ld_course.id','ld_course.title','ld_course.cover','ld_course.expiry','ld_course.watch_num','ld_course_live_childs.start_time','ld_course_live_childs.end_time','ld_course_live_childs.id as livi_id','ld_course_live_childs.create_at as live_times')
                    ->get()->toArray();
                foreach($order[$k]['coures'] as $ks => $vs){
                    $order[$k]['coures'][$ks]['order_id'] = $v['order_id'];
                    $order[$k]['coures'][$ks]['validity_time'] = $v['validity_time'];
                    $order[$k]['coures'][$ks]['nature'] = 0;
                }
                $coures[] = $order[$k]['coures'];
                $coures_list = array_reduce($coures, 'array_merge', []);
            }
            if($v['nature'] == 1){
                $order[$k]['coures_school'] = CourseSchool::leftJoin('ld_course','ld_course.id','=','ld_course_school.course_id')
                    ->leftJoin('ld_course_method', 'ld_course_method.course_id', '=', 'ld_course.id')
                    ->leftJoin('ld_course_live_resource','ld_course_live_resource.course_id','=','ld_course.id')
                    ->leftJoin('ld_course_shift_no','ld_course_shift_no.id','=','ld_course_live_resource.shift_id')
                    ->leftJoin('ld_course_class_number','ld_course_class_number.shift_no_id','=','ld_course_shift_no.id')
                    ->leftJoin('ld_course_live_childs','ld_course_live_childs.class_id','=','ld_course_class_number.id')
                    ->where(['ld_course_school.id' => $v['class_id'], 'ld_course_school.is_del' => 0, 'ld_course_school.status' => 1, 'ld_course_method.method_id' => 1])
                    ->select('ld_course.id','ld_course.title','ld_course.cover','ld_course.expiry','ld_course.watch_num','ld_course_live_childs.start_time','ld_course_live_childs.end_time','ld_course_live_childs.id as livi_id','ld_course_live_childs.create_at as live_times')
                    ->get()->toArray();
                foreach($order[$k]['coures_school'] as $ks => $vs){
                    $order[$k]['coures_school'][$ks]['order_id'] = $v['order_id'];
                    $order[$k]['coures_school'][$ks]['validity_time'] = $v['validity_time'];
                    $order[$k]['coures_school'][$ks]['nature'] = 1;
                }
                $coures_school[] = $order[$k]['coures_school'];
                $coures_school_list = array_reduce($coures_school, 'array_merge', []);
            }
        }
		if(empty($coures_list) && empty($coures_school_list)){
            return $list = [];
        }else{
			if(empty($coures_list)){
                $list = $coures_school_list;
            }elseif(empty($coures_school_list)){
                $list = $coures_list;
            }else{
                $list = array_merge($coures_list,$coures_school_list);
            }

			foreach($list as $k => $v){
				if($v['livi_id'] == ''){
					unset($list[$k]);
				}
			}
			$list = $this->array_unique_fb($list,'order_id');
			return array_merge($list);
		}
    }

	 public function array_unique_fb($arr,$key){
        $tmp_arr = array();
        foreach($arr as $k => $v){
            if(in_array($v[$key],$tmp_arr)){
                unset($arr[$k]);
            }else{
                $tmp_arr[] = $v[$key];
            }
        }
        return $arr;
    }

	 public function myMessageType()
    {
        $status = $this->data['status'];

        $pagesize = isset($this->data['pagesize']) && $this->data['pagesize'] > 0 ? $this->data['pagesize'] : 20;
        $page     = isset($this->data['page']) && $this->data['page'] > 0 ? $this->data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        $messageCount = StudentMessage::query()->where(['student_id'=>$this->userid,'msg_status'=>$status,'school_id'=>$this->school['id']])->count();
        $meMessageList = StudentMessage::query()->where(['student_id'=>$this->userid,'msg_status'=>$status,'school_id'=>$this->school['id']])->orderByDesc('id')->offset($offset)->limit($pagesize)->get()->toArray();
        $ret= array();
        foreach($meMessageList as $k =>$message_info){
            $teacherlist = Couresteacher::where(['course_id' => $message_info['course_id'], 'is_del' => 0])->get();
            $order_info  = Order::query()->where("id","=",$message_info['order_id'])->get();
            if($message_info['nature'] == 0){
                $course_info = Course::query()->where("id","=",$message_info['course_id']) ->first();
            }else{
                $course_info = CourseSchool::query()->where("id","=",$message_info['course_id'])->first();
            }

            // 设定 web 的 返回值
            $ret[$k]=array(
                'course_name'       =>   $course_info['title']  ,
                'course_id'         =>   $message_info['course_id'] ,
                'course_cover'      =>   $course_info['cover'] ,
                'course_expiry'     =>   $course_info['expiry'] ,
                'watch_num'         =>   $course_info['watch_num'] ,
                'live_time'         =>   $course_info['start_time'] ,
                'time'              =>   $message_info['msg_time'] ,
                'uid'               =>  $this->userid ,
                'school_id'         =>  $this->school['id'] ,
                'order_id'          =>  $message_info['order_id'] ,
                'validity_time'     =>  $course_info['validity_time'] ,
                'nature'            =>  $message_info['nature'] ,
                'prompt'            =>  $message_info['msg_type'],
                'create_at'         =>  $message_info['msg_time']
            );



            $string = [];
            if (!empty($teacherlist)) {
                foreach ($teacherlist as $ks => $teacher_info) {
                    $teacher = Teacher::where(['id' => $teacher_info['teacher_id'], 'is_del' => 0, 'type' => 2])->first();
                    $string[] = $teacher['real_name'];
                }
                $ret[$k]['teachername'] = implode(',', $string);
            } else {
                $ret[$k]['teachername'] = '';
            }
            $date1 = time();
            $date2 = strtotime($course_info['validity_time']);
            if ($date1 >= $date2) {
                $ret[$k]['day'] = '已过期';
            } else {
                $interval = $date2 - $date1;
                $d = floor($interval/3600/24);
                $h = floor(($interval%(3600*24))/3600);
                $m = floor(($interval%(3600*24))%3600/60);
                $s = floor(($interval%(3600*24))%60);
                if ($course_info['course_expiry'] == 0) {
                    $ret[$k]['day'] = '无期限';
                } else {
                    if ($s > 0) {
                        $ret[$k]['day'] = $d.'天'.$h.'小时'.$m.'分'.$s.'秒';
                    } else {
                        $ret[$k]['day'] = '已过期';
                    }
                }
            }
            $ret[$k]['live_day'] = date('Y-m-d H:i:s',$course_info['start_time']);
        }

        return ['code' => 200, 'msg' => '获取我的消息列表成功', 'data' => $ret , 'count' => $messageCount];
    }

    public function timetable(){

        $data = $this->data;
        $validator = Validator::make($data, [
            'start_time' => 'required|date'
        ], School::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $student_id = $data["user_info"]['user_id'];
        $school_id  = $data['user_info']['school_id'];

        $limit = 31; // 默认
        if(isset($data['limit'])){
            $limit =  intval( $data['limit']);
        }

        $arr = Course::getClassTimetableByDate($student_id,$school_id,$data['start_time'],$limit);
        return ['code'=>200,'msg'=>'success','data'=>$arr];
    }

    /**
     *  我的 消息
     */
    public function myMessage(){
        $data = $this->data;
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        // 获取 登录 的 两个数据
        $student_id = $data["user_info"]['user_id'];
        $school_id  = $data['user_info']['school_id'];

        // 按照 消息的 状态 进行 查询
        $msg_status  = 0 ;
        if(isset($data['status'])){
            $msg_status = $data['status'];
        }

        $student_meaasge  = new StudentMessage();
        $arr = $student_meaasge->getMessageByStudentAndSchoolId($student_id,$school_id,$msg_status,$offset,$pagesize,'pc');

        return ['code'=>200,'msg'=>'success','data'=>$arr['data'],'count'=>$arr['count']];

    }
    public function MessageCount(){
        $data = $this->data;

        // 获取 登录 的 两个数据
        $student_id = $data["user_info"]['user_id'];
        $school_id  = $data['user_info']['school_id'];

        $student_meaasge  = new StudentMessage();

        // 这个 接口 中 涉及到 一个 功能 将 消息设定成 已读
        if(isset($data['id'])){
            $student_meaasge ->setMessageRead($data['id']);
        }

        //获取 已读 未读 消息 列表
        $ret_date = $student_meaasge ->getMessageStatistics($student_id,$school_id);
        return ['code'=>200,'msg'=>'success','data'=> $ret_date ];
    }
    public function AddvideoLog(){
        $data = $this->data;
        $arr['user_id'] = $data["user_info"]['user_id'];
        $arr['school_id']  = $data['user_info']['school_id'];
        $arr['videoid'] = $data['videoid'];//视频id
        //通过视频id获取播放时间
        $play_duration = Video::select("mt_duration")->where(['cc_video_id'=>$data['videoid']])->first();
        $arr['play_duration'] = $play_duration['mt_duration'];//播放时长
        $arr['play_position'] = $data['play_position'];//最后播放位置
        $res = VideoLog::where(['user_id'=>$arr['user_id'],'school_id'=>$arr['school_id'],'videoid'=>$arr['videoid']])->first();
        if(is_null($res)){
            //不存在 新增用户数据
            $res =  VideoLog::insertGetId($arr);
        }else{
            //查询最后播放时长  如果当前传的时长小于库里存的时长  保留库里时长
            if($res['play_position'] > $data['play_position']){
                $arr['play_position'] = $res['play_position'];
            }else{
                $arr['play_position'] = $data['play_position'];
            }
            //存在 修改已观看时长
            $videolog = VideoLog::find($res['id']);
            $res = $videolog->update(['play_position' => $arr['play_position']]);
        }
        if($res){
            return ['code'=>200,'msg'=>'success'];
        }else{
            return ['code'=>500,'msg'=>'服务错误'];
        }
    }
}
