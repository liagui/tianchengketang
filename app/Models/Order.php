<?php
namespace App\Models;

use App\Models\CouresSubject;
use App\Models\Student;
use App\Models\Video;
use App\Models\VideoLog;
use App\Models\CourseSchool;
use App\Models\Coureschapters;
use App\Providers\aop\AopClient\AopClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Order extends Model {
    //指定别的表名
    public $table = 'ld_order';
    //时间戳设置
    public $timestamps = false;
    /*
         * @param  订单列表
         * @param  $school_id  分校id
         * @param  $status  状态
         * @param  $state_time 开始时间
         * @param  $end_time 结束时间
         * @param  $order_number 订单号
         * @param  author  苏振文
         * @param  ctime   2020/5/4 14:41
         * return  array
         */
    public static function getList($data){


        unset($data['/admin/order/orderList']);
        //用户权限
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        //如果不是总校管理员，只能查询当前关联的网校订单
        if($role_id != 1){
            $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            $data['school_id'] = $school_id;
        }
        $begindata="2020-03-04";
        $enddate = date('Y-m-d');
        $statetime = !empty($data['state_time'])?$data['state_time']:$begindata;
        $endtime = !empty($data['end_time'])?$data['end_time']:$enddate;
        $state_time = $statetime." 00:00:00";
        $end_time = $endtime." 23:59:59";
        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //計算總數
        $count = self::leftJoin('ld_student','ld_student.id','=','ld_order.student_id')
            ->where(function($query) use ($data) {
                if(isset($data['school_id']) && !empty($data['school_id'])){
                    $query->where('ld_order.school_id',$data['school_id']);
                }
            })
            ->where(function($query) use ($data) {

                if(isset($data['status']) && $data['status'] != -1){
                    $query->where('ld_order.status',$data['status']);
                }
                if(isset($data['order_number']) && !empty($data['order_number'] != '')){
                    $query->where('ld_order.order_number','like','%'.$data['order_number'].'%')
                        ->orwhere('ld_student.phone','like',$data['order_number'])
                        ->orwhere('ld_student.real_name','like',$data['order_number']);
                }
            })
            ->whereBetween('ld_order.create_at', [$state_time, $end_time])
            ->count();
        $order = self::select('ld_order.id','ld_order.order_number','ld_order.order_type','ld_order.price','ld_order.pay_status','ld_order.pay_type','ld_order.status','ld_order.create_at','ld_order.oa_status','ld_order.student_id','ld_order.parent_order_number','ld_student.phone','ld_student.real_name','ld_student.school_id')
            ->leftJoin('ld_student','ld_student.id','=','ld_order.student_id')
            ->where(function($query) use ($data) {
                if(isset($data['school_id']) && !empty($data['school_id'])){
                    $query->where('ld_order.school_id',$data['school_id']);
                }
            })
            ->where(function($query) use ($data) {
                if(isset($data['order_number']) && !empty($data['order_number'] != '')){
                    $query->where('ld_order.order_number','like','%'.$data['order_number'].'%')
                        ->orwhere('ld_student.phone','like',$data['order_number'])
                        ->orwhere('ld_student.real_name','like',$data['order_number']);
                }
                if(isset($data['status'])&& $data['status'] != -1){
                    $query->where('ld_order.status',$data['status']);
                }

            })
            ->whereBetween('ld_order.create_at', [$state_time, $end_time])
            ->orderByDesc('ld_order.id')
            ->offset($offset)->limit($pagesize)->get()->toArray();
        $schooltype = Article::schoolANDtype($role_id);
        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];

        return ['code' => 200 , 'msg' => '查询成功','data'=>$order,'school'=>$schooltype[0],'where'=>$data,'page'=>$page];
    }
    /*
       * @param  线下学生报名 添加订单
       * @param  $student_id  用户id
       * @param  $lession_id 学科id
       * @param  $lession_price 原价
       * @param  $student_price 学员价格
       * @param  $payment_fee 付款金额
       * @param  $payment_type 1定金2尾款3最后一笔尾款4全款
       * @param  $payment_method 1微信2支付宝3银行转账
       * @param  $payment_time 支付时间
       * @param  author  苏振文
       * @param  ctime   2020/5/6 9:57
       * return  array
       */
    public static function offlineStudentSignup($arr){
        //判断传过来的数组数据是否为空
        if(!$arr || !is_array($arr)){
            return ['code' => 201 , 'msg' => '传递数据不合法'];
        }
        //判断学生id
        if(!isset($arr['student_id']) || empty($arr['student_id'])){
            return ['code' => 201 , 'msg' => '报名学生为空或格式不对'];
        }
        //判断学科id
        if(!isset($arr['lession_id']) || empty($arr['lession_id'])){
            return ['code' => 201 , 'msg' => '学科为空或格式不对'];
        }
        //判断原价
        if(!isset($arr['lession_price']) || empty($arr['lession_price'])){
            return ['code' => 201 , 'msg' => '原价为空或格式不对'];
        }
        //判断付款类型
        if(!isset($arr['payment_type']) || empty($arr['payment_type']) || !in_array(  $arr['payment_type'],[1,2,3,4])){
            return ['code' => 201 , 'msg' => '付款类型为空或格式不对'];
        }
        //判断原价
        if(!isset($arr['payment_method']) || empty($arr['payment_method'])|| !in_array($arr['payment_method'],[1,2,3])){
            return ['code' => 201 , 'msg' => '付款方式为空或格式不对'];
        }
        //判断支付时间
        if(!isset($arr['payment_time'])|| empty($arr['payment_time'])){
            return ['code' => 201 , 'msg' => '支付时间不能为空'];
        }
        //获取后端的操作员id
        $data['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;  //操作员id
        //根据用户id获得分校id
        $school = Student::select('school_id')->where('id',$arr['student_id'])->first();
        $data['order_number'] = date('YmdHis', time()) . rand(1111, 9999); //订单号  随机生成
        $data['order_type'] = 1;        //1线下支付 2 线上支付
        $data['student_id'] = $arr['student_id'];
        $data['price'] = $arr['payment_fee']; //应付价格
        $data['student_price'] = $arr['student_price'];
        $data['lession_price'] = $arr['lession_price'];
        $data['pay_status'] = $arr['payment_type'];
        $data['pay_type'] = $arr['payment_method'];
        $data['status'] = 1;                  //支付状态
        $data['pay_time'] = $arr['payment_time'];
        $data['oa_status'] = 0;              //OA状态
        $data['class_id'] = $arr['lession_id'];
        $data['school_id'] = $school['school_id'];
        $data['nature'] = $arr['nature'];
        $add = self::insert($data);
        if($add){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $data['admin_id']  ,
                'module_name'    =>  'Order' ,
                'route_url'      =>  'admin/Order/offlineStudentSignup' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '添加订单的内容,'.json_encode($data),
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return true;
        }else{
            return false;
        }
    }
    /*
         * @param  线上支付 生成预订单
         * @param  $student_id  学生id
         * @param  type  1安卓2ios3h5
         * @param  $class_id  课程id
         * @param  author  苏振文
         * @param  ctime   2020/5/6 14:53
         * return  array
         */
    public static function orderPayList($arr){
        if(!$arr || empty($arr)){
            return ['code' => 201 , 'msg' => '参数错误'];
        }
        //判断学生id
        if(!isset($arr['student_id']) || empty($arr['student_id'])){
            return ['code' => 201 , 'msg' => '学生id为空或格式不对'];
        }
        //根据用户id查询信息
        $student = Student::select('school_id','balance')->where('id',$arr['student_id'])->first();
        //判断课程id
        if(!isset($arr['class_id']) || empty($arr['class_id'])){
            return ['code' => 201 , 'msg' => '课程id为空或格式不对'];
        }
        //判断类型
        if(!isset($arr['type']) || empty($arr['type'] || !in_array($arr['type'],[1,2,3]))){
            return ['code' => 201 , 'msg' => '机型不匹配'];
        }
        // $nature = isset($arr['nature'])?$arr['nature']:0;
        //判断用户网校，根据网校查询课程信息
        // if($nature == 1){
        //授权课程
        //$course = CourseSchool::select('id','title','cover','pricing as price','sale_price as favorable_price')->where(['id'=>$arr['class_id'],'school_id'=>$student['school_id'],'is_del'=>0,'status'=>1])->first();
        // }else{
        //自增课程
        //$course = Coures::select('id','title','cover','pricing as price','sale_price as favorable_price')->where(['id'=>$arr['class_id'],'is_del'=>0,'status'=>1])->first();
        // }
        $course = Coures::select('id','title','cover','pricing as price','sale_price as favorable_price')->where(['id'=>$arr['class_id'],'is_del'=>0,'status'=>1,'school_id'=>$student['school_id']])->first();
        if(empty($course)){
            $course = CourseSchool::select('id','title','cover','pricing as price','sale_price as favorable_price')->where(['course_id'=>$arr['class_id'],'to_school_id'=>$student['school_id'],'is_del'=>0,'status'=>1])->first();
            $nature = 1;
        }else{
            $nature = 0;
        }
        if(!$course){
            return ['code' => 204 , 'msg' => '此课程选择无效'];
        }
        if(empty($course['favorable_price']) || empty($course['price'])){
            return ['code' => 204 , 'msg' => '此课程信息有误选择无效'];
        }
        //根据分校查询支付方式
        $payList = PaySet::where(['school_id'=>$student['school_id']])->first();
        if(empty($payList)) {
            $payList = PaySet::where(['school_id' => 1])->first();
        }
        $newpay=[];
        if($payList['wx_pay_state'] == 1){
            array_push($newpay,1);
        }
        if($payList['zfb_pay_state'] == 1){
            array_push($newpay,2);
        }
        if($payList['hj_wx_pay_state'] == 1){
            array_push($newpay,3);
        }
        if($payList['hj_zfb_pay_state'] == 1){
            array_push($newpay,4);
        }
        //查询用户有此类订单没有，有的话直接返回
//            $orderfind = self::where(['student_id'=>$arr['student_id'],'class_id'=>$arr['class_id'],'status'=>0])->first();
//            if($orderfind){
//                $lesson['order_id'] = $orderfind['id'];
//                $lesson['order_number'] = $orderfind['order_number'];
//                $lesson['user_balance'] = $student['balance'];
//                return ['code' => 200 , 'msg' => '生成预订单成功1','data'=>$lesson,'paylist'=>$newpay];
//            }
        //数据入库，生成订单
        $data['order_number'] = date('YmdHis', time()) . rand(1111, 9999);
        $data['admin_id'] = 0;  //操作员id
        $data['order_type'] = 2;        //1线下支付 2 线上支付
        $data['student_id'] = $arr['student_id'];
        $data['price'] = $course['favorable_price'];
        $data['student_price'] = $course['price'];
        $data['lession_price'] = $course['price'];
        $data['pay_status'] = 4;
        $data['pay_type'] = 0;
        $data['status'] = 0;
        $data['nature'] = $nature;
        $data['oa_status'] = 0;              //OA状态
        $data['class_id'] = $course['id'];
        $data['school_id'] = $student['school_id'];
        DB::beginTransaction();
        try {
            $add = self::insertGetId($data);
            if($add){
                $course['order_id'] = $add;
                $course['order_number'] = $data['order_number'];
                $course['user_balance'] = $student['balance'];
                DB::commit();
                return ['code' => 200 , 'msg' => '生成预订单成功','data'=>$course,'paylist'=>$newpay];
            }else{
                DB::rollback();
                return ['code' => 203 , 'msg' => '生成订单失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }
    }
    /*
         * @param  修改审核状态
         * @param  $order_id 订单id
         * @param  $status 1审核通过 status修改成2    2退回审核  status修改成4
         * @param  author  苏振文
         * @param  ctime   2020/5/6 10:56
         * return  array
         */
    public static function exitForIdStatus($data){
        if(!$data || !is_array($data)){
            return ['code' => 201 , 'msg' => '数据不合法'];
        }
        $order = self::where(['id'=>$data['order_id']])->first();
        if(!$order){
            return ['code' => 201 , 'msg' => '数据无效'];
        }
        if($order['status'] != 1){
            return ['code' => 201 , 'msg' => '订单无法审核'];
        }
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        if($order['status'] == 1) {
            if ($data['status'] == 2) {
                $update = self::where(['id' => $data['order_id']])->update(['status' => 2,'oa_status' => 1, 'update_at' => date('Y-m-d H:i:s')]);
                if ($update) {
                    //最后一笔款或全款 开课
                    if ($order['pay_status'] == 3 || $order['pay_status'] == 4) {
                        //修改学员报名  订单状态 课程有效期
                        if($order['nature'] == 1){
                            $lessons = CourseSchool::where(['id'=>$order['class_id']])->first();
                        }else{
                            $lessons = Coures::where(['id'=>$order['class_id']])->first();
                        }
                        //计算用户购买课程到期时间
                        if($lessons['expiry'] == 0){
                            $validity = '3000-01-02 12:12:12';
                        }else{
                            $validity = date('Y-m-d H:i:s', strtotime('+' . $lessons['expiry'] . ' day'));
                        }
                        //修改订单状态 课程有效期 oa状态
                        self::where(['id' => $order['id']])->update(['status' => 2, 'validity_time' => $validity, 'update_at' => date('Y-m-d H:i:s')]);
                        //修改用户报名状态
                        //判断此用户所有订单数量
                        $overorder = Order::where(['student_id'=>$order['student_id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                        $userorder = Order::where(['student_id'=>$order['student_id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
                        if($overorder == $userorder){
                            $state_status = 2;
                        }else{
                            if($overorder > 0 ){
                                $state_status = 1;
                            }else{
                                $state_status = 0;
                            }
                        }
                        Student::where(['id' => $order['student_id']])->update(['enroll_status' => 1, 'state_status' => $state_status]);
                        //添加日志操作
                        AdminLog::insertAdminLog([
                            'admin_id' => $admin_id,
                            'module_name' => 'Order',
                            'route_url' => 'admin/Order/exitForIdStatus',
                            'operate_method' => 'update',
                            'content' => '审核成功，修改id为' . $data['order_id'] . json_encode($data),
                            'ip' => $_SERVER['REMOTE_ADDR'],
                            'create_at' => date('Y-m-d H:i:s')
                        ]);
                        return ['code' => 200, 'msg' => '回审通过'];
                    }else{
                        return ['code' => 200, 'msg' => '回审通过'];
                    }
                } else {
                    return ['code' => 202, 'msg' => '操作失败'];
                }
           }else if($data['status'] == 3){
                $update = self::where(['id'=>$data['order_id']])->update(['status'=>3]);
                if($update){
                    //添加日志操作
                    AdminLog::insertAdminLog([
                        'admin_id'       =>   $admin_id  ,
                        'module_name'    =>  'Order' ,
                        'route_url'      =>  'admin/Order/exitForIdStatus' ,
                        'operate_method' =>  'update' ,
                        'content'        =>  '退回审核，修改id为'.$data['order_id'].json_encode($data) ,
                        'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                        'create_at'      =>  date('Y-m-d H:i:s')
                    ]);
                    return ['code' => 200 , 'msg' => '回审通过'];
                }else{
                    return ['code' => 202 , 'msg' => '操作失败'];
                }
            }
        }else{
            return ['code' => 203 , 'msg' => '此订单无法进行此操作'];
        }
    }
    /*
         * @param  单条查看详情
         * @param  order_id   订单id
         * @param  author  苏振文
         * @param  ctime   2020/5/6 11:15
         * return  array
         */
    public static function findOrderForId($data){
        if (empty($data['order_id'])) {
            return ['code' => 201, 'msg' => '订单id错误'];
        }
        $order_info = self::select('order_number', 'create_at', 'price', 'order_type', 'status', 'pay_time', 'student_id', 'class_id','nature','lession_price')->where(['id' => $data['order_id']])->first();
        if (!empty($order_info)) {
            if ($order_info['student_id'] != '') {
                $student = Student::select('real_name', 'phone', 'school_id')->where(['id' => $order_info['student_id']])->first();
                $order_info['real_name'] = $student['real_name'];
                $order_info['phone'] = $student['phone'];
                if ($student['school_id'] != '') {
                    $school = School::select('name')->where(['id' => $student['school_id']])->first();
                    $order_info['schoolname'] = $school['name'];
                }
            }
            if ($order_info['class_id'] != '') {
                if($order_info['nature'] == 1){
                    $lesson = CourseSchool::select('course_id as id', 'title','sale_price','parent_id','child_id')->where(['id' => $order_info['class_id']])->first();
                }else{
                    $lesson = Coures::select('id', 'title','sale_price','parent_id','child_id')->where(['id' => $order_info['class_id']])->first();
                }
                if (!empty($lesson)) {
                    $order_info['title'] = $lesson['title'];
                    $order_info['sale_price'] = $lesson['sale_price'];
                    $order_info['parent_id'] = CouresSubject::select('subject_name')->where(['id'=>$lesson['parent_id'],'is_del'=>0])->first()['subject_name'];
                    $order_info['child_id'] = CouresSubject::select('subject_name')->where(['id'=>$lesson['child_id'],'is_del'=>0])->first()['subject_name'];
                    $teacher = Couresteacher::where(['course_id' => $lesson['id']])->get()->toArray();
                    if (!empty($teacher)) {
                        foreach ($teacher as $k=>$v){
                            $lecturer_educationa = Lecturer::select('real_name')->where(['id' => $v['teacher_id']])->first();
                            $teacherrealname[] = $lecturer_educationa['real_name'];
                        }
                        $teacherrealnames = implode(',',$teacherrealname);
                        $order_info['real_names'] = $teacherrealnames;
                    }
                }
            }
            if ($order_info) {
                return ['code' => 200, 'msg' => '查询成功', 'data' => $order_info];
            } else {
                return ['code' => 202, 'msg' => '查询失败'];
            }
        }
    }
    /*
         * @param  订单修改oa状态
         * @param  $order_id
         * @param  $status
         * @param  author  苏振文
         * @param  ctime   2020/5/6 16:33
         * return  array
         */
    public static function orderUpOaForId($data){
        if(!$data || empty($data)){
            return ['code' => 201 , 'msg' => '参数为空或格式错误'];
        }
        if(empty($data['order_number'])){
            return ['code' => 201 , 'msg' => '订单号不能为空'];
        }
        if(!in_array($data['status'],['0','1'])){
            return ['code' => 201 , 'msg' => '状态传输错误'];
        }
        $order = self::where(['order_number'=>$data['order_number']])->first()->toArray();
        if(!$order){
            return ['code' => 201 , 'msg' => '订单号错误'];
        }
        DB::beginTransaction();
        try {
            if($data['status'] == 1){
                //修改学员报名  订单状态 课程有效期
                $lessons = Coures::where(['id'=>$order['class_id']])->first();
                //计算用户购买课程到期时间
                $validity = date('Y-m-d H:i:s',strtotime('+'.$lessons['ttl'].' day'));
                //修改订单状态 课程有效期 oa状态
                $update = self::where(['id'=>$order['id']])->update(['status'=>2,'validity_time'=>$validity,'oa_status'=>1,'update_at'=>date('Y-m-d H:i:s')]);
                //修改用户报名状态,修改开课状态
                Student::where(['id'=>$order['student_id']])->update(['enroll_status'=>1,'state_status'=>2]);
            }else{
                $update = self::where(['id'=>$order['id']])->update(['status'=>3,'oa_status'=>$data['status'],'update_at'=>date('Y-m-d H:i:s')]);
            }
            if($update){
                DB::commit();
                return ['code' => 200 , 'msg' => '修改成功'];
            }else{
                DB::rollback();
                return ['code' => 202 , 'msg' => '修改失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }
    }

    //根据用户查询订单
    public static function orderForStudent($data){
        if(!$data || empty($data)){
            return ['code' => 201 , 'msg' => '参数为空或格式错误'];
        }
        if(empty($data['student_id'])){
            return ['code' => 201 , 'msg' => '学员id为空'];
        }
        $query= "select * from ld_order where id in(SELECT max(id) FROM ld_order where student_id = ".$data['student_id']." GROUP BY class_id)";
        $order = DB::select($query);
        if(!empty($order)){
            foreach ($order as $k=>&$v){
                $v = (array)$v;
                if($v['pay_type'] == 0){
                    $v['pay_name'] = "未支付";
                }
                if($v['pay_type'] == 1){
                    $v['pay_name'] = "微信";
                }
                if($v['pay_type'] == 2){
                    $v['pay_name'] = "支付宝";
                }
                if($v['pay_type'] == 3){
                    $v['pay_name'] = "银行转账";
                }
                if($v['pay_type'] == 4){
                    $v['pay_name'] = "汇聚";
                }
                if($v['pay_type'] == 5){
                    $v['pay_name'] = "余额";
                }
                if($v['nature'] == 1){
                    $course = CourseSchool::where(['id'=>$v['class_id']])->first();
                }else{
                    $course = Coures::where(['id'=>$v['class_id']])->first();
                }
                $v['course_cover'] = $course['cover'];
                $v['course_title'] = $course['title'];
                if($v['status'] == 0){
                    $v['learning'] = "未支付";
                    $v['bgcolor'] = '#FF0000';
                }
                if($v['status'] == 1){
                    $v['learning'] = "待审核";
                    $v['bgcolor'] = '#FF7E00';
                }
                if($v['status'] == 2){
                    if($v['pay_status'] == 3 || $v['pay_status'] == 4){
                        if(strtotime($v['validity_time']) < time()){
                            $v['learning'] = "已过期";
                            $v['bgcolor'] = '#656565';
                            $v['status'] = '6';
                        }else{
                            $v['learning'] = "已开课";
                            $v['bgcolor'] = '#299E00';
                        }
                    }else{
                        $v['learning'] = "尾款未结清";
                        $v['bgcolor'] = '#9600FF';
                    }
                }
                if($v['status'] == 3){
                    $v['learning'] = "审核失败";
                    $v['bgcolor'] = '#FF006C';
                }
                if($v['status'] == 4){
                    $v['learning'] = "已退款";
                    $v['bgcolor'] = '#656565';
                }
                if($v['status'] == 5){
                    if($v['parent_order_number'] != ''){
                        $v['learning'] = "已转班";
                        $v['bgcolor'] = '#656565';
                    }else{
                        $v['learning'] = "已失效";
                        $v['bgcolor'] = '#656565';
                    }
                }
            }
        }
        return ['code' => 200 , 'msg' => '完成' , 'data'=>$order];
    }
    /*
       * @param  线下学生报名 添加订单  不需要审核 直接开课
       * @param  $student_id  用户id
       * @param  $lession_id 学科id
       * @param  $lession_price 原价
       * @param  $student_price 学员价格
       * @param  $payment_fee 付款金额
       * @param  $payment_type 1定金2尾款3最后一笔尾款4全款
       * @param  $payment_method 1微信2支付宝3银行转账
       * @param  $payment_time 支付时间
       * @param  author  苏振文
       * @param  ctime   2020/5/6 9:57
       * return  array
       */
    public static function offlineStudentSignupNotaudit($arr){
        //判断传过来的数组数据是否为空
        if(!$arr || !is_array($arr)){
            return ['code' => 201 , 'msg' => '传递数据不合法'];
        }
        //判断学生id
        if(!isset($arr['student_id']) || empty($arr['student_id'])){
            return ['code' => 201 , 'msg' => '报名学生为空或格式不对'];
        }
        //判断学科id
        if(!isset($arr['lession_id']) || empty($arr['lession_id'])){
            return ['code' => 201 , 'msg' => '学科为空或格式不对'];
        }
        //判断原价
        if(!isset($arr['lession_price']) || empty($arr['lession_price'])){
            return ['code' => 201 , 'msg' => '原价为空或格式不对'];
        }
        //判断付款类型
        if(!isset($arr['payment_type']) || empty($arr['payment_type']) || !in_array(  $arr['payment_type'],[1,2,3,4])){
            return ['code' => 201 , 'msg' => '付款类型为空或格式不对'];
        }
        //判断原价
        if(!isset($arr['payment_method']) || empty($arr['payment_method'])|| !in_array($arr['payment_method'],[1,2,3])){
            return ['code' => 201 , 'msg' => '付款方式为空或格式不对'];
        }
        //判断支付时间
        if(!isset($arr['payment_time'])|| empty($arr['payment_time'])){
            return ['code' => 201 , 'msg' => '支付时间不能为空'];
        }
        //获取后端的操作员id
        $data['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;  //操作员id
        //根据用户id获得分校id
        $school = Student::select('school_id')->where('id',$arr['student_id'])->first();
        $data['order_number'] = date('YmdHis', time()) . rand(1111, 9999); //订单号  随机生成
        $data['order_type'] = 1;        //1线下支付 2 线上支付
        $data['student_id'] = $arr['student_id'];
        $data['price'] = $arr['payment_fee']; //应付价格
        $data['student_price'] = $arr['student_price'];
        $data['lession_price'] = $arr['lession_price'];
        $data['pay_status'] = $arr['payment_type'];
        $data['pay_type'] = $arr['payment_method'];
        $data['status'] = 2;                  //支付状态
        $data['pay_time'] = $arr['payment_time'];
        $data['oa_status'] = 1;              //OA状态
        $data['class_id'] = $arr['lession_id'];
        $data['school_id'] = $school['school_id'];
        $data['nature'] = $arr['nature'];
        if($arr['nature'] == 1){
            $lesson = CourseSchool::where(['id'=>$arr['lession_id']])->first();
        }else{
            $lesson = Coures::where(['id'=>$arr['lession_id']])->first();
        }
        if($lesson['expiry'] ==0){
            $validity = '3000-01-02 12:12:12';
        }else{
            $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
        }
        $data['validity_time'] = $validity;
        $overorder = Order::where(['student_id'=>$arr['student_id'],'status'=>2])->count(); //用户已完成订单
        $userorder = Order::where(['student_id'=>$arr['student_id']])->count(); //用户所有订单
        if($overorder == $userorder){
            $state_status = 2;
        }else{
            if($overorder > 0 ){
                $state_status = 1;
            }else{
                $state_status = 0;
            }
        }
        $add = self::insert($data);
        if($add){
            Student::where(['id'=>$arr['student_id']])->update(['enroll_status'=>1,'state_status'=>$state_status,'update_at'=>date('Y-m-d H:i:s')]);
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $data['admin_id']  ,
                'module_name'    =>  'Order' ,
                'route_url'      =>  'admin/Order/offlineStudentSignup' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '添加订单的内容,'.json_encode($data),
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return true;
        }else{
            return false;
        }
    }

	/*
         * @param  财务收入详情
         * @param  school_id  网校id
         * @param  subject  学科分类 [一级分类,二级分类]
         * @param  $course_id  课程id
         * @param  $state_time 开始时间
         * @param  $end_time 结束时间
         * @param  $search_name 姓名/手机号
         * @param  author  sxh
         * @param  ctime   2020/11/6
         * return  array
         */
    public static function financeDetails($data){
        //判断网校id
         $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //初始化 开始/结束 时间
        $begindata= '2020-03-04';
        $enddate = date('Y-m-d');
        $statetime = !empty($data['state_time'])?$data['state_time']:$begindata;
        $endtime = !empty($data['end_time'])?$data['end_time']:$enddate;
        $state_time = $statetime." 00:00:00";
        $end_time = $endtime." 23:59:59";
        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
		//拆分学科分类
		$parent = [];
        if(isset($data['subject']) && !empty($data['subject'])){
            $parent = json_decode($data['subject'],true);
        }
        $order = self::select('ld_order.id','ld_order.price as order_price','ld_order.nature','ld_order.class_id','ld_student.phone','ld_student.real_name')
            ->leftJoin('ld_student','ld_student.id','=','ld_order.student_id')
            ->leftJoin('ld_course','ld_course.id','=','ld_order.class_id')
            ->where(['ld_order.status'=>2,'ld_order.school_id'=>$school_id])
            ->where(function($query) use ($data,$parent) {
                if(isset($data['search_name']) && !empty($data['search_name'])){
                    if ( is_numeric( $data['search_name'] ) ) {
                        $query->where('ld_student.phone','like','%'.$data['search_name'].'%');
                    } else {
                        $query->where('ld_student.real_name','like','%'.$data['search_name'].'%');
                    }
                }
                if(isset($data['course_id']) && !empty($data['course_id'])){
                    $query->where('ld_order.class_id',$data['course_id']);
                }
				if(isset($parent[0]) && !empty($parent[0])){
                    $query->where('ld_course.parent_id',$parent[0]);
                }
                if(isset($parent[1]) && !empty($parent[1])){
                    $query->where('ld_course.child_id',$parent[1]);
                }
                /*if(isset($data['status'])&& $data['status'] != -1){
                    $query->where('ld_order.status',$data['status']);
                }
                if(isset($data['order_number']) && !empty($data['order_number'] != '')){
                    $query->where('ld_order.order_number','like','%'.$data['order_number'].'%')
                        ->orwhere('ld_student.phone','like',$data['order_number'])
                        ->orwhere('ld_student.real_name','like',$data['order_number']);
                }*/
            })
            ->whereBetween('ld_order.create_at', [$state_time, $end_time])
            ->orderByDesc('ld_order.id')
            ->offset($offset)->limit($pagesize)->get();
        //获取订单对应课程的信息   1代表授权,0代表自增
        if(!empty($order)){
            $order = $order->toArray();
            foreach ($order as $k => $v){
                if($v['nature']==0){
                    $list = Coures::leftJoin('ld_course_subject','ld_course_subject.id','=','ld_course.parent_id')
                    ->select('ld_course.title','ld_course.sale_price','ld_course_subject.subject_name')->where(['ld_course.id'=>$v['class_id']])->first()->toArray();
                    $order[$k]['course_name'] = $list['title'];
                    $order[$k]['course_price'] = $list['sale_price'];
                    $order[$k]['subject_name'] = $list['subject_name'];
                }
                if($v['nature']==1){
                    $list = CourseSchool::leftJoin('ld_course_subject','ld_course_subject.id','=','ld_course_school.parent_id')
                    ->select('ld_course_school.title','ld_course_school.sale_price','ld_course_subject.subject_name')->where(['ld_course_school.id'=>$v['class_id']])->first()->toArray();
                    $order[$k]['course_name'] = $list['title'];
                    $order[$k]['course_price'] = $list['sale_price'];
                    $order[$k]['subject_name'] = $list['subject_name'];
                }
            }
        }
		$count_order = self::select('ld_order.id','ld_order.price as order_price','ld_order.nature','ld_order.class_id','ld_student.phone','ld_student.real_name')
            ->leftJoin('ld_student','ld_student.id','=','ld_order.student_id')
            ->leftJoin('ld_course','ld_course.id','=','ld_order.class_id')
            ->where(['ld_order.status'=>2,'ld_order.school_id'=>$school_id])
            ->where(function($query) use ($data,$parent) {
                if(isset($data['search_name']) && !empty($data['search_name'])){
                    if ( is_numeric( $data['search_name'] ) ) {
                        $query->where('ld_student.phone','like','%'.$data['search_name'].'%');
                    } else {
                        $query->where('ld_student.real_name','like','%'.$data['search_name'].'%');
                    }
                }
                if(isset($data['course_id']) && !empty($data['course_id'])){
                    $query->where('ld_order.class_id',$data['course_id']);
                }
				if(isset($parent[0]) && !empty($parent[0])){
                    $query->where('ld_course.parent_id',$parent[0]);
                }
                if(isset($parent[1]) && !empty($parent[1])){
                    $query->where('ld_course.child_id',$parent[1]);
                }
                /*if(isset($data['status'])&& $data['status'] != -1){
                    $query->where('ld_order.status',$data['status']);
                }
                if(isset($data['order_number']) && !empty($data['order_number'] != '')){
                    $query->where('ld_order.order_number','like','%'.$data['order_number'].'%')
                        ->orwhere('ld_student.phone','like',$data['order_number'])
                        ->orwhere('ld_student.real_name','like',$data['order_number']);
                }*/
            })
            ->whereBetween('ld_order.create_at', [$state_time, $end_time])
            ->orderByDesc('ld_order.id')
            ->count();

        return ['code' => 200 , 'msg' => '查询成功','data'=>$order,'where'=>$data,'total'=>$count_order];
    }

	/*
         * @param  学员学习记录-直播
         * @param  $student_id     参数
         *         $type           1 直播 2 录播
         * @param  author  sxh
         * @param  ctime   2020/10-28
         * return  array
         */
    public static function getStudentStudyList($data){

        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //判断学员信息是否为空
        if(empty($data['student_id']) || !is_numeric($data['student_id']) || $data['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不能为空' , 'data' => ''];
        }
        if(!in_array($data['type'],[1,2])){
            return ['code' => 202 , 'msg' => '教学形式参数有误' , 'data' => ''];
        }


        //获取学校id
        $user_id = $data['student_id'];
        $school_id = Student::select('school_id')->where("id",$user_id)->first()['school_id'];
        //获取头部信息
        $public_list = self::getStudyOrderInfo($data);


        if($data['type'] ==1){
            //直播课次
            $classInfo = self::getCourseClassInfo($public_list,$offset,$pagesize,$page);
            if(isset($data['pagesize']) && isset($data['page'])){
                $all = array_slice($classInfo, $offset, $pagesize);
                return ['code' => 200 , 'msg' => '获取学习记录成功-直播课' , 'study_list'=>$all, 'study_count'=>count($classInfo), 'public_list'=>$public_list];
            }

        }

        //录播
        $chapters = self::getCourseChaptersInfo($public_list,$user_id);
        if(isset($data['pagesize']) && isset($data['page'])){
            $all = array_slice($chapters, $offset, $pagesize);
            foreach($public_list as $k => $v){
                //获取课程录播总时长 除以学生总学习时长
                $course_id = CourseSchool::select('course_id')->where('id',$v['class_id'])->first();
                //dd($course_id['course_id']);
                $resource_id = Coureschapters::select('resource_id')->where(['course_id'=>$course_id['course_id'],'is_del'=>0])->get()->toArray();

                $resource_id = array_column($resource_id, 'resource_id');
                //dd($resource_id);
                //获取资源的总时长
                $mt_duration = Video::whereIn('id',$resource_id)->pluck('mt_duration')->toArray();
                $mt_duration = array_sum($mt_duration);
                //获取cc_video_id

                $cc_video_id = Video::whereIn('id',$resource_id)->pluck('cc_video_id')->toArray();
                $m_duration = VideoLog::whereIn('videoid',$cc_video_id)->where(['user_id'=>$user_id,'school_id'=>$school_id])->pluck('play_position')->toArray();
                $m_duration = array_sum($m_duration);
                if($mt_duration == 0 || $m_duration == 0){
                    $public_list[$k]['study_rate'] = 0;
                }else{
                    $public_list[$k]['study_rate'] = sprintf("%01.2f",$m_duration/$mt_duration);
                }

            }

            //获取该学生
            return ['code' => 200 , 'msg' => '获取学习记录成功-录播课' , 'study_list'=>$all, 'study_count'=>count($chapters), 'public_list'=>$public_list];

        }

    }
	//获取订单信息
	private static function getStudyOrderInfo($data){

        $list =Order::where(['student_id'=>$data['student_id'],'status'=>2])
            ->whereIn('pay_status',[3,4])
			 ->where(function ($query) use ($data) {
                if (isset($data['id']) && !empty($data['id'])) {
                    $query->where('class_id', $data['id']);
                }
            })
            ->select('id','pay_time','class_id','nature','class_id','school_id','student_id')
            ->orderByDesc('id')
            ->get()->toArray();
        $list = self::array_unique_fb($list,'class_id');
        $course_statistics= new CourseStatistics();
        if(!empty($list)){
            foreach ($list as $k=>$v){
                //1256
                // 这里  计算  课程 完成率
                // $list[$k]['study_rate'] = rand(1,100);

                $list[$k]['study_rate'] = $course_statistics->CalculateCourseRateBySchoolIdAndStudentId($v[ 'school_id'], $v[ 'class_id'], $v[ 'student_id']);

                if($v['nature'] == 1){
                    DB::enableQueryLog();
                    $course = CourseSchool::leftJoin('ld_course_method','ld_course_method.course_id','=','ld_course_school.course_id')
                        ->where(['ld_course_school.id'=>$v['class_id'],'ld_course_school.is_del'=>0,'ld_course_school.status'=>1,'ld_course_method.is_del'=>0])
                        ->where(function($query) use ($data){
                            //判断题库id是否为空
                            if(!empty($data['type']) && $data['type'] > 0){
                                $query->where('ld_course_method.method_id' , '=' , $data['type']);
                            }
                        })
                        ->whereIn('ld_course_method.method_id',[1,2])
                        ->select('ld_course_school.title')
                        ->first();
                    if(is_null($course)){
                        unset($list[$k]);
                    }else{
                        $list[$k]['course_name'] = $course['title'];
                    }

                }else {
                    $course = Coures::leftJoin('ld_course_method','ld_course_method.course_id','=','ld_course.id')
                        ->where(['ld_course.id' => $v['class_id'], 'ld_course.is_del' => 0, 'ld_course.status' => 1,'ld_course_method.method_id'=>1,'ld_course_method.method_id'=>2])
                        ->where(function($query) use ($data){
                            //判断题库id是否为空
                            if(!empty($data['type']) && $data['type'] > 0){
                                $query->where('ld_course_method.method_id' , '=' , $data['type']);
                            }
                        })
                        ->select('ld_course.title')
                        ->first();
                    if(empty($course)){
                        unset($list[$k]);
                    }else{
                        $list[$k]['course_name'] = $course['title'];
                    }

                }

            }
        }
        if(empty($list)){
            return $list = [];
        }else{
            return array_merge($list);
        }

    }
	//去重
    private static function array_unique_fb($arr, $key)
    {
        $tmp_arr = array();
        foreach ($arr as $k => $v) {
            if ( key_exists($key, $v) and in_array($v[ $key ], $tmp_arr)) {
                unset($arr[ $k ]);
            } else {
                (key_exists($key, $v)) ? $tmp_arr[] = $v[ $key ] : false;
            }
        }
        return $arr;
    }
	//获取直播课次
    private static function getCourseClassInfo($order_list_info, $offset, $pagesize, $page){
        $course_statistics = new CourseStatisticsDetail();

        //var_dump($list);
        foreach ($order_list_info as $k => $order_info){
            //授权课程
            if($order_info['nature'] == 1){
                $order_list_info[ $k][ 'coures_school'] = CourseSchool::leftJoin('ld_course','ld_course.id','=','ld_course_school.course_id')
                    ->leftJoin('ld_course_live_resource','ld_course_live_resource.course_id','=','ld_course.id')
                    ->leftJoin('ld_course_shift_no','ld_course_shift_no.id','=','ld_course_live_resource.shift_id')
                    ->leftJoin('ld_course_class_number','ld_course_class_number.shift_no_id','=','ld_course_shift_no.id')
                    ->leftJoin('ld_course_live_childs','ld_course_live_childs.class_id','=','ld_course_class_number.id')
                    ->where(['ld_course_school.id' => $order_info['class_id'], 'ld_course_school.is_del' => 0, 'ld_course_school.status' => 1])
                    ->select('ld_course_school.id as course_school_id','ld_course_school.title as coures_name',
                        'ld_course_school.course_id','ld_course_live_childs.id as cl_id','ld_course_live_childs.course_name as name','ld_course_live_childs.course_id as room_id')
                    ->get()->toArray();
                $coures_school[] = $order_list_info[ $k][ 'coures_school'];
                if(empty($coures_school)){
                    $coures_school_list = [];
                }else{
                    $coures_school_list = array_reduce($coures_school, 'array_merge', []);
                }

                foreach($coures_school_list as $ks=>$vs){
                    // [ 'rate' => $rate, "max_time" => $max_time,'last_time' => $last_time];
                    $ret_statistics_info = $course_statistics->CalculateLiveRate($vs[ 'room_id'], $order_info[ 'student_id'], $order_info[ 'school_id'],$order_info['class_id']);

                    if(empty($ret_statistics_info)){
                        $coures_school_list[$ks]['teaching_mode'] = '直播';
                        $coures_school_list[$ks]['last_class_time'] = date("Y-m-d  H:i:s",time());
                        $coures_school_list[$ks]['is_finish'] = '未完成';
                        $coures_school_list[$ks]['max_class_time'] = date("Y-m-d  H:i:s",time());
                    }else{
                        $coures_school_list[$ks]['teaching_mode'] = '直播';
                        $coures_school_list[$ks]['last_class_time'] = $ret_statistics_info['last_time'];
                        $coures_school_list[$ks]['is_finish'] = ($ret_statistics_info['rate'] < 95 )? '未完成':'已完成';
                        $coures_school_list[$ks]['max_class_time'] = $ret_statistics_info['max_time'];

                    }

                }
            }
            //自增课程
            if($order_info['nature'] == 0) {
                $order_list_info[ $k][ 'coures'] = Coures::leftJoin('ld_course_live_resource','ld_course_live_resource.course_id','=','ld_course.id')
                    ->leftJoin('ld_course_shift_no','ld_course_shift_no.id','=','ld_course_live_resource.shift_id')
                    ->leftJoin('ld_course_class_number','ld_course_class_number.shift_no_id','=','ld_course_shift_no.id')
                    ->leftJoin('ld_course_live_childs','ld_course_live_childs.class_id','=','ld_course_class_number.id')
                    ->where(['ld_course.id' => $order_info['class_id'], 'ld_course.is_del' => 0, 'ld_course.status' => 1])
                    ->select('ld_course.id as course_school_id','ld_course.title as coures_name',
                        'ld_course_live_childs.id as cl_id','ld_course_live_childs.course_name as name',
                        'ld_course_live_childs.course_id as room_id')
                    ->get()->toArray();
                $coures[] = $order_list_info[ $k][ 'coures'];
                if(empty($coures)){
                    $coures_list = [];
                }else{
                    $coures_list = array_reduce($coures, 'array_merge', []);
                }

                foreach($coures_list as $ks=>$vs){
                    // [ 'rate' => $rate, "max_time" => $max_time,'last_time' => $last_time];
                    $ret_statistics_info = $course_statistics->CalculateLiveRate($vs[ 'room_id'], $order_info[ 'student_id'], $order_info[ 'school_id'],$order_info['class_id']);

                    if(empty($ret_statistics_info)){
                        $coures_school_list[$ks]['teaching_mode'] = '直播';
                        $coures_school_list[$ks]['last_class_time'] = date("Y-m-d  H:i:s",time());
                        $coures_school_list[$ks]['is_finish'] = '未完成';
                        $coures_school_list[$ks]['max_class_time'] = date("Y-m-d  H:i:s",time());
                    }else{
                        $coures_school_list[$ks]['teaching_mode'] = '直播';
                        $coures_school_list[$ks]['last_class_time'] = $ret_statistics_info['last_time'];
                        $coures_school_list[$ks]['is_finish'] = ($ret_statistics_info['rate'] < 95 )? '未完成':'已完成';
                        $coures_school_list[$ks]['max_class_time'] = $ret_statistics_info['max_time'];

                    }
                }
            }
        }
        if(empty($coures_list) && empty($coures_school_list)){
            return $res = [];
        }else{
            if (empty($coures_list)) {
                $res = $coures_school_list;
            } elseif (empty($coures_school_list)) {
                $res = $coures_list;
            } else {
                $res = array_merge($coures_list, $coures_school_list);
            }
            foreach ($res as $k => $order_info) {
                if (isset($order_info[ 'cl_id' ]) and $order_info[ 'cl_id' ] == '') {
                    unset($res[ $k ]);
                }
            }
            $res = self::array_unique_fb($res, 'cl_id');
            $res = array_merge($res);
        }
        return $res;

    }

	//获取录播课次
    private static function getCourseChaptersInfo($list,$user_id){
        //study_rate
        //获取学校id
        //dd($list);
        $school_id = Student::select('school_id')->where("id",$user_id)->first()['school_id'];
        foreach ($list as $k => $v){
            //自增课程
            if($v['nature'] == 0) {
                $list[$k]['chapters_info'] = Coureschapters::where(['parent_id'=>0,'is_del'=>0,'course_id'=>$v['class_id']])->get();
                foreach($list[$k]['chapters_info'] as $ks => $vs){
                    $list[$k]['chapters_info'][$ks]['two'] = Coureschapters::where(['parent_id'=>$vs['id'],'school_id'=>$vs['school_id']])->select('name')->get()->toArray();
                    $coures[] = $list[$k]['chapters_info'][$ks]['two'];
                }
                if(empty($coures)){
                    $coures_school_list = [];
                }else{
                    $coures_school_list = array_reduce($coures, 'array_merge', []);
                }

                foreach($coures_school_list as $ks=>$vs){
                    $coures_school_list[$ks]['coures_name'] = $vs['name'];
                    $coures_school_list[$ks]['teaching_mode'] = '录播';
                    $coures_school_list[$ks]['is_finish'] = '未完成';
                }
            }
            if($v['nature'] == 1){
                $course_school = CourseSchool::where(['id'=>$v['class_id']])->select('course_id','title')->first();

                //章
                $list[$k]['chapters_info'] = Coureschapters::where(['parent_id'=>0,'is_del'=>0,'course_id'=>$course_school['course_id']])->select('id','school_id')->get();
                foreach($list[$k]['chapters_info'] as $ks => $vs){
                    //节
                    $list[$k]['chapters_info'][$ks]['two'] = Coureschapters::where(['parent_id'=>$vs['id'],'is_del'=>0,'school_id'=>$vs['school_id']])->select('name','id')->get()->toArray();
                    $coures[] = $list[$k]['chapters_info'][$ks]['two'];
                }
                if(empty($coures)){
                    $coures_list = [];
                }else{
                    $coures_list = array_reduce($coures, 'array_merge', []);
                }
                foreach($coures_list as $ks=>$vs){
                    $coures_list[$ks]['coures_name'] = $course_school['title'];
                    $coures_list[$ks]['teaching_mode'] = '录播';
                    $coures_list[$ks]['is_finish'] = '未完成';
                }
            }

        }
        if(empty($coures_list) && empty($coures_school_list)){
            return $res = [];
        }else{
            if(empty($coures_list)){
                $res = $coures_school_list;
            }elseif(empty($coures_school_list)){
                foreach($coures_list as $k => $v){
                    $res = Coureschapters::select('resource_id')->where(['id'=>$v['id']])->first();
                    $coures_list[$k]['resource_id'] = $res['resource_id'];
                    $are = Video::select('cc_video_id')->where(['id'=>$res['resource_id']])->first();
                    $coures_list[$k]['cc_video_id'] = $are['cc_video_id'];
                    $plan = VideoLog::where(['videoid'=>$are['cc_video_id'],'user_id'=>$user_id,'school_id'=>$school_id])->first();
                    if($plan['play_position'] == 0){
                        $coures_list[$k]['is_finish'] = '未完成';
                    }else{
                        $coures_list[$k]['is_finish'] = sprintf("%01.2f",$plan['play_position']/$plan['play_duration']).'%';
                    }
                }
                $res = $coures_list;
            }else{
                $res = array_merge($coures_list,$coures_school_list);
            }

            $res = array_merge($res);

        }
        return $res;
    }

	/*
         * @param  导出学员学习记录
         * @param  $student_id     学员id
         *         $type           1 直播 2 录播
         * @param  author  sxh
         * @param  ctime   2020/11/26
         * return  array
         */
	public static function exportStudentStudyList($data){
        //判断学员信息是否为空
        if(empty($data['student_id']) || !is_numeric($data['student_id']) || $data['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不能为空' , 'data' => ''];
        }
        if(!in_array($data['type'],[1,2])){
            return ['code' => 202 , 'msg' => '教学形式参数有误' , 'data' => ''];
        }
        $list =Order::where(['student_id'=>$data['student_id'],'status'=>2])
            ->whereIn('pay_status',[3,4])
            ->where(function ($query) use ($data) {
                if (isset($data['id']) && !empty($data['id'])) {
                    $query->where('class_id', $data['id']);
                }
            })
            ->select('id','pay_time','class_id','nature','class_id')
            ->orderByDesc('id')
            ->get();
        //获取头部信息
        $list = self::array_unique_fb($list,'class_id');
        if(!empty($list)){
            foreach ($list as $k=>$v){
                //$list[$k]['study_rate'] = rand(1,100);
                if($v['nature'] == 1){
                    $course = CourseSchool::leftJoin('ld_course_method','ld_course_method.course_id','=','ld_course_school.course_id')
                        ->where(['ld_course_school.id'=>$v['class_id'],'ld_course_school.is_del'=>0,'ld_course_school.status'=>1])
                        ->where(function($query) use ($data){
                            //判断题库id是否为空
                            if(!empty($data['type']) && $data['type'] > 0){
                                $query->where('ld_course_method.method_id' , '=' , $data['type']);
                            }
                        })
                        ->select('ld_course_school.title')
                        ->first();
                    if(empty($course)){
                        unset($list[$k]);
                    }else{
                        $list[$k]['course_name'] = $course['title'];
                    }

                }else {
                    $course = Coures::leftJoin('ld_course_method','ld_course_method.course_id','=','ld_course.id')
                        ->where(['ld_course.id' => $v['class_id'], 'ld_course.is_del' => 0, 'ld_course.status' => 1])
                        ->where(function($query) use ($data){
                            //判断题库id是否为空
                            if(!empty($data['type']) && $data['type'] > 0){
                                $query->where('ld_course_method.method_id' , '=' , $data['type']);
                            }
                        })
                        ->select('ld_course.title')
                        ->first();
                    if(empty($course)){
                        unset($list[$k]);
                    }else{
                        $list[$k]['course_name'] = $course['title'];
                    }

                }

            }
        }
		//直播
		if($data['type'] ==1){

            //直播课次
            foreach ($list as $k => $v){
				//授权课程
				if($v['nature'] == 1){
					$list[$k]['coures_school'] = CourseSchool::leftJoin('ld_course','ld_course.id','=','ld_course_school.course_id')
						->leftJoin('ld_course_live_resource','ld_course_live_resource.course_id','=','ld_course.id')
						->leftJoin('ld_course_shift_no','ld_course_shift_no.id','=','ld_course_live_resource.shift_id')
						->leftJoin('ld_course_class_number','ld_course_class_number.shift_no_id','=','ld_course_shift_no.id')
						->leftJoin('ld_course_live_childs','ld_course_live_childs.class_id','=','ld_course_class_number.id')
						->where(['ld_course_school.id' => $v['class_id'], 'ld_course_school.is_del' => 0, 'ld_course_school.status' => 1])
						->select('ld_course_school.id as course_school_id','ld_course_school.title as coures_name','ld_course_school.course_id','ld_course_live_childs.id as cl_id','ld_course_live_childs.course_name as name')
						->get()->toArray();
					$coures_school[] = $list[$k]['coures_school'];
					if(empty($coures_school)){
						$coures_school_list = [];
					}else{
						$coures_school_list = array_reduce($coures_school, 'array_merge', []);
					}
					foreach($coures_school_list as $ks=>$vs){
						$coures_school_list[$ks]['teaching_mode'] = '直播';
						$coures_school_list[$ks]['last_class_time'] = date("Y-m-d  H:i:s",time());
						$coures_school_list[$ks]['is_finish'] = '未完成';
						$coures_school_list[$ks]['max_class_time'] = date("Y-m-d  H:i:s",time());
					}
				}
				//自增课程
				if($v['nature'] == 0) {
					$list[$k]['coures'] = Coures::leftJoin('ld_course_live_resource','ld_course_live_resource.course_id','=','ld_course.id')
						->leftJoin('ld_course_shift_no','ld_course_shift_no.id','=','ld_course_live_resource.shift_id')
						->leftJoin('ld_course_class_number','ld_course_class_number.shift_no_id','=','ld_course_shift_no.id')
						->leftJoin('ld_course_live_childs','ld_course_live_childs.class_id','=','ld_course_class_number.id')
						->where(['ld_course.id' => $v['class_id'], 'ld_course.is_del' => 0, 'ld_course.status' => 1])
						->select('ld_course.id as course_school_id','ld_course.title as coures_name','ld_course_live_childs.id as cl_id','ld_course_live_childs.course_name as name')
						->get()->toArray();
					$coures[] = $list[$k]['coures'];
					if(empty($coures)){
						$coures_list = [];
					}else{
						$coures_list = array_reduce($coures, 'array_merge', []);
					}
					foreach($coures_list as $ks=>$vs){
						$coures_list[$ks]['teaching_mode'] = '直播';
						$coures_list[$ks]['last_class_time'] = date("Y-m-d  H:i:s",time());
						$coures_list[$ks]['is_finish'] = '未完成';
						$coures_list[$ks]['max_class_time'] = date("Y-m-d  H:i:s",time());
					}
				}
			}
			$res = new \Illuminate\Database\Eloquent\Collection();
			if(empty($coures_list) && empty($coures_school_list)){
				//var_dump(123);die();
				//return $res = new \Illuminate\Database\Eloquent\Collection();
				return ['code' => 200 , 'msg' => '获取学习记录成功-直播课' , 'data'=>$res];
			}else{
				if(empty($coures_list)){
					$res = $coures_school_list;
				}elseif(empty($coures_school_list)){
					$res = $coures_list;
				}else{
					$res = array_merge($coures_list,$coures_school_list);
				}
				foreach($res as $k => $v){
					if($v['cl_id'] == ''){
						unset($res[$k]);
					}
				}
				$res = self::array_unique_fb($res,'cl_id');
				$res = array_merge($res);
			}
            return ['code' => 200 , 'msg' => '获取学习记录成功-直播课' , 'data'=>$res];
        }
		//录播
		if($data['type'] ==2){

			foreach ($list as $k => $v){
            //自增课程
				if($v['nature'] == 0) {
					$list[$k]['chapters_info'] = Coureschapters::where(['parent_id'=>0,'is_del'=>0,'course_id'=>$v['class_id']])->get();
					foreach($list[$k]['chapters_info'] as $ks => $vs){
						$list[$k]['chapters_info'][$ks]['two'] = Coureschapters::where(['parent_id'=>$vs['id'],'school_id'=>$vs['school_id']])->select('name')->get()->toArray();
						$coures[] = $list[$k]['chapters_info'][$ks]['two'];
					}
					if(empty($coures)){
						$coures_school_list = [];
					}else{
						$coures_school_list = array_reduce($coures, 'array_merge', []);
					}
					foreach($coures_school_list as $ks=>$vs){
						$coures_school_list[$ks]['coures_name'] = $v['title'];
						$coures_school_list[$ks]['teaching_mode'] = '录播';
						$coures_school_list[$ks]['last_class_time'] = date("Y-m-d  H:i:s",time());
						$coures_school_list[$ks]['is_finish'] = '未完成';
						$coures_school_list[$ks]['max_class_time'] = date("Y-m-d  H:i:s",time());
					}
				}
				if($v['nature'] == 1){
					$course_school = CourseSchool::where(['id'=>$v['class_id']])->select('course_id','title')->first();
					$list[$k]['chapters_info'] =Coureschapters::where(['parent_id'=>0,'is_del'=>0,'course_id'=>$course_school['course_id']])->select('id','school_id')->get();
					foreach($list[$k]['chapters_info'] as $ks => $vs){
						$list[$k]['chapters_info'][$ks]['two'] = Coureschapters::where(['parent_id'=>$vs['id'],'school_id'=>$vs['school_id']])->select('name')->get()->toArray();
						$coures[] = $list[$k]['chapters_info'][$ks]['two'];
					}
					if(empty($coures)){
						$coures_list = [];
					}else{
						$coures_list = array_reduce($coures, 'array_merge', []);
					}
					foreach($coures_list as $ks=>$vs){
						$coures_list[$ks]['coures_name'] = $course_school['title'];
						$coures_list[$ks]['teaching_mode'] = '录播';
						$coures_list[$ks]['last_class_time'] = date("Y-m-d  H:i:s",time());
						$coures_list[$ks]['is_finish'] = '未完成';
						$coures_list[$ks]['max_class_time'] = date("Y-m-d  H:i:s",time());
					}
				}

			}
			$res = new \Illuminate\Database\Eloquent\Collection();
			if(empty($coures_list) && empty($coures_school_list)){
				return ['code' => 200 , 'msg' => '获取学习记录成功-直播课' , 'data'=>$res];
			}else{
				if(empty($coures_list)){
					$res = $coures_school_list;
				}elseif(empty($coures_school_list)){
					$res = $coures_list;
				}else{
					$res = array_merge($coures_list,$coures_school_list);
				}

				$res = array_merge($res);
			}
			return ['code' => 200 , 'msg' => '获取学习记录成功-录播课' , 'data'=>$res];
		}

    }


    /**
     *  通过学科分类 直播单元  课次单元 来查询
     *  根据客户端选择 各自属性来获取数据
     *          unit_id: "",   // 选中的单元 直报单元的id
     *          class_id:"",   // 选中的课次信息 选中的课次信息
     *          course_name: "", // 搜索的 课次名称  进行模糊搜索的
     *          timeRange: "",   // 时间段  限定的直播的
     * @param $queryParameters
     */
    public static function queryStudentLiveStatIsTicsFilters($queryParameters)
    {
        if (!isset($queryParameters[ 'school_id' ])) {
            //当前学校id
            $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        } else {
            $school_id = $queryParameters[ 'school_id' ];
        }
        $queryParameters['school_id'] = $school_id;
        //分页
        $pagesize = isset($queryParameters[ 'pagesize' ]) && $queryParameters[ 'pagesize' ] > 0 ? $queryParameters[ 'pagesize' ] : 20;
        $page = isset($queryParameters[ 'page' ]) && $queryParameters[ 'page' ] > 0 ? $queryParameters[ 'page' ] : 1;
        $offset = ($page - 1) * $pagesize;


        list($unit_list, $class_list, $ret_class_list_count, $res) = self::queryLiveRate($queryParameters, $school_id, $pagesize, $offset);


        return array(
            "unit_list"  => !empty($unit_list) ? $unit_list->toArray() : array(),
            "class_list" => !empty($class_list) ? $class_list->toArray() : array(),
            "ret_data"   => $res,
            "totalCount" => $ret_class_list_count,
            "queryParameters" => $queryParameters
        );

    }


    /**
     *  注意处理流程有问题
     *     1 当开始的时候 没有筛选的参数按照 直播统计表进行显示
     *     2 如果有参数 那么按照筛选的参数进行处理
     * @param $body
     * @return array
     */
    public static function getStudentLiveStatistics($body)
    {
        return  self::queryStudentLiveStatIsTicsFilters($body);

        // 这一部分代码暂时保留
        if(!isset($body['school_id'])){
            //当前学校id
            $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        }else{
            $school_id = $body['school_id'];
        }

        //分页
        $pagesize = isset($body[ 'pagesize' ]) && $body[ 'pagesize' ] > 0 ? $body[ 'pagesize' ] : 20;
        $page = isset($body[ 'page' ]) && $body[ 'page' ] > 0 ? $body[ 'page' ] : 1;
        $offset = ($page - 1) * $pagesize;
        //获取直播数据
        $res = CourseStatistics::query()->where([ 'school_id' => $school_id ])->get();
        $courseSchol = new CourseSchool();
        foreach ($res as $k => $v) {
            //公开课
            $course_open_live_childs = CourseOpenLiveChilds::query()
                ->rightJoin('ld_course_open', 'ld_course_open.id', '=', 'ld_course_open_live_childs.lesson_id')
                ->rightJoin('ld_course_subject', 'ld_course_subject.id', '=', 'ld_course_open.parent_id')
                ->where([ 'course_id' => $v[ 'room_id' ] ])
                ->select('ld_course_open.id', 'ld_course_open.child_id', 'ld_course_open.title', 'ld_course_subject.subject_name as parent_name')
                ->first();
            if ($course_open_live_childs) {
                $res[ $k ][ 'type' ] = 1;
                $res[ $k ][ 'coures_name' ] = $course_open_live_childs[ 'title' ];
                $res[ $k ][ 'parent_name' ] = $course_open_live_childs[ 'parent_name' ];
                $res[ $k ][ 'unit' ] = '';
                $res[ $k ][ 'class' ] = '';
                $res[ $k ][ 'child_name' ] = CouresSubject::where([ 'id' => $course_open_live_childs[ 'child_id' ] ])->select('subject_name')->first()[ 'subject_name' ];
            }

        }
        foreach ($res as $k => $v) {
            //课程 //这里 需要  处理 一下 自增课 和 授权课的 不同
            $class_list = CourseLiveClassChild::query()
                ->rightJoin('ld_course_class_number', 'ld_course_class_number.id', '=', 'ld_course_live_childs.class_id')
                ->rightJoin('ld_course_shift_no', 'ld_course_shift_no.id', '=', 'ld_course_class_number.shift_no_id')
                ->where([ 'course_id' => $v[ 'room_id' ] ])
                ->select('ld_course_live_childs.course_name as kecheng', 'ld_course_class_number.name as keci',
                    'ld_course_shift_no.name as banhao', 'ld_course_shift_no.resource_id')
                ->first();

            $course_live_resource = CourseLiveResource::query()->where([ 'shift_id' => $class_list[ 'resource_id' ] ])->select('course_id')->first()[ 'course_id' ];

            if ($course_live_resource && $v[ 'type' ] != 1) {
                $course = Coures::query()->rightJoin('ld_course_subject', 'ld_course_subject.id', '=', 'ld_course.parent_id')
                    ->where([ 'ld_course.id' => $course_live_resource ])->select('ld_course_subject.subject_name', 'ld_course.child_id')->first();
                $class_name = CouresSubject::where([ 'id' => $course[ 'child_id' ] ])->select('subject_name')->first()[ 'subject_name' ];
                $res[ $k ][ 'coures_name' ] = $class_list[ 'kecheng' ];
                $res[ $k ][ 'parent_name' ] = $course[ 'subject_name' ];
                $res[ $k ][ 'unit' ] = $class_list[ 'keci' ];
                $res[ $k ][ 'class' ] = $class_list[ 'banhao' ];
                $res[ $k ][ 'child_name' ] = $class_name;
            }

        }
        return [ 'code' => 200, 'msg' => '获取直播到课率成功', 'data' => $res->toArray() ];
    }

    /**
     * @param $queryParameters
     * @param int $school_id
     * @param int $pagesize
     * @param int $offset
     * @return array
     */
    public static function queryLiveRate($queryParameters, int $school_id, int $pagesize, int $offset): array
    {
        $parent_id = !empty($queryParameters[ 'parent_id' ]) ? $queryParameters[ 'parent_id' ] : "";  //分类数据

        $unit_id = !empty($queryParameters[ 'unit_id' ]) ? $queryParameters[ 'unit_id' ] : "";      // 课程单元id
        $class_id = !empty($queryParameters[ 'class_id' ]) ? $queryParameters[ 'class_id' ] : "";    // 课次id
        $course_name = !empty($queryParameters[ 'course_name' ]) ? $queryParameters[ 'course_name' ] : "";  // 模糊搜索的课程名称
        $time_range = !empty($queryParameters[ 'timeRange' ]) ? $queryParameters[ 'timeRange' ] : "";     // 直报的时间段

        $where_query = CourseLiveClassChild::query();
        $where_query->leftJoin("ld_course_class_number", "ld_course_live_childs.class_id", "=", "ld_course_class_number.id")
            ->leftJoin("ld_course_shift_no", "ld_course_class_number.shift_no_id", "=", "ld_course_shift_no.id")
            ->leftJoin("ld_course_live_resource", "ld_course_live_resource.shift_id", "=", "ld_course_class_number.shift_no_id")
            ->leftJoin("ld_course_livecast_resource", "ld_course_live_resource.resource_id", "=", "ld_course_livecast_resource.id")
            ->leftJoin("ld_course", "ld_course_live_resource.course_id", "=", "ld_course.id")
            ->leftJoin("ld_course_school", "ld_course_school.course_id", "=", "ld_course.id")
            ->leftJoin("ld_course_statistics", "ld_course_statistics.course_id", "=", "ld_course.id")
            ->where(function ($query) use ($school_id) {
                $query->where('ld_course_school.to_school_id', '=', $school_id)
                    ->orWhere('ld_course.school_id', '=', $school_id);
            });


        // 这里 一次查询 所有 直播单元 所有 课次 的 过滤信息 和 模糊 搜索的 条件
        $is_first = true;

        // 从 web 传递过来的是 字符串 这里 把他 换成 数组
        if (!empty($parent_id) and is_string($parent_id)) {
            $parent_id = json_decode($parent_id);
        }
        // parent_id 是一个 数组 这个数组 有两个元素 parent child
        if (!empty($parent_id) and is_array($parent_id)) {

            if (isset($parent_id[ 0 ])) {
                $where_query->where("ld_course.parent_id", "=", $parent_id[ 0 ]);
                $is_first = false;
            }
            if (isset($parent_id[ 1 ])) {
                $where_query->where("ld_course.child_id", "=", $parent_id[ 1 ]);
                $is_first = false;
            }
        }

        // 查询课程单元
        if (!empty($unit_id)) {
            $where_query->where("ld_course_livecast_resource.id", "=", $unit_id);
            $is_first = false;
        }

        // 查询课次
        if (!empty($class_id)) {
            $where_query->where("ld_course_class_number.id", "=", $class_id);
            $is_first = false;
        }

        // 模糊查询 课次 信息
        if (!empty($course_name)) {
            // 这里 使用 前缀固定的方式进行搜索 可以 使用 索引
            $where_query->where(function ($query) use ($course_name) {
                $query->where("ld_course.title", "like", "%" . $course_name . "%")
                    ->orWhere('ld_course_school.title', "like", "%" . $course_name . "%");
            });
            $is_first = false;

        }

        // 这里处理 直播课程 的 时间段 time_range 中有两个时间 格式已经 ok
        if (!empty($time_range) and is_array($time_range) and count($time_range) == 2) {
            $time_range[ 0 ] = strtotime($time_range[ 0 ]);
            $time_range[ 1 ] = strtotime($time_range[ 1 ]);
            $where_query->whereBetween("ld_course_live_childs.start_time", $time_range);
            $is_first = false;
        }

        // 开始 查询数据


        // 1 查询所有直播单元信息
        $unit_list = (clone $where_query)->select([ "ld_course_livecast_resource.id", "ld_course_livecast_resource.name" ])
            ->groupBy("ld_course_livecast_resource.id")->get();


        // 2 查询所有 课次信息
        $class_list = (clone $where_query)->select([ "ld_course_class_number.id", "ld_course_class_number.name" ])
            ->groupBy("ld_course_class_number.id")->get();

        $ret_class_list_count = 0;
        $res = array();
        if ($is_first == false) {

            // 3 按照分页 处理 表格的 数据
            $ret_class_list = (clone $where_query)->groupBy("ld_course_live_childs.id")->select("ld_course.id as course_id", "ld_course_school.id as school_course_id",
                "ld_course.id AS course_id", 'ld_course_live_childs.course_name as live_name', 'ld_course_class_number.name as class_name',
                'ld_course_shift_no.name as shift_name', "ld_course_livecast_resource.name as unit_name", 'ld_course_shift_no.resource_id',
                'ld_course.title as title', 'ld_course_school.title as title2',
                "ld_course_statistics.live_start_time",
                "ld_course_statistics.live_end_time",
                "ld_course_statistics.course_attendance",
                "ld_course_statistics.course_rate",
                "ld_course_statistics.statistics_time"
            );

            // 处理 一下 分页 的page size  如果 传递的 int 的最大值 那么 就是到处
            if ($pagesize != PHP_INT_MAX) {
                $ret_class_list = $ret_class_list->limit($pagesize)->offset($offset)->get();
            } else {
                //到处数据
                $ret_class_list = $ret_class_list->get();
            }


            // 3 按照分页 处理 表格的 数据
            $ret_class_list_count = (clone $where_query)->groupBy("ld_course_live_childs.id")->count("ld_course.id");


            $res = [];
            foreach ($ret_class_list as $k => $value) {

                $course = Coures::query()->rightJoin('ld_course_subject', 'ld_course_subject.id', '=', 'ld_course.parent_id')
                    ->where([ 'ld_course.id' => $value[ 'course_id' ] ])->select('ld_course_subject.subject_name', 'ld_course.child_id')->first();
                if ($course[ 'child_id' ] > 0) {
                    $class_name = CouresSubject::where([ 'id' => $course[ 'child_id' ] ])->select('subject_name')->first()[ 'subject_name' ];
                } else {
                    $class_name = "";
                }

                $res[ $k ][ 'coures_name' ] = !empty($value[ 'title2' ]) ? $value[ 'title2' ] : $value[ 'title' ];
                $res[ $k ][ 'parent_name' ] = $course[ 'subject_name' ];
                $res[ $k ][ 'unit' ] = $value[ 'unit_name' ];
                $res[ $k ][ 'class' ] = $value[ 'class_name' ];
                $res[ $k ][ 'child_name' ] = $class_name;
                if (isset($value[ 'school_course_id' ])) {
                    $res[ $k ][ 'course_id' ] = $value[ 'school_course_id' ];
                } else {
                    $res[ $k ][ 'course_id' ] = $value[ 'course_id' ];
                }
                // 设定 直报完成率的数字

                $res[ $k ][ "course_attendance" ] = !empty($value[ "course_attendance" ]) ? $value[ "course_attendance" ] : "0";
                $res[ $k ][ "course_rate" ] = !empty($value[ "course_rate" ]) ? $value[ "course_rate" ] : "0";
                $res[ $k ][ "live_start_time" ] = !empty($value[ "live_start_time" ]) ? $value[ "live_start_time" ] : "-";
                $res[ $k ][ "live_end_time" ] = !empty($value[ "live_end_time" ]) ? $value[ "live_start_time" ] : "-";
                $res[ $k ] [ "statistics_time" ] = !empty($value[ "statistics_time" ]) ? $value[ "statistics_time" ] : "-";

            }
        }
        return array( $unit_list, $class_list, $ret_class_list_count, $res );
    }


    public function  getOrdersBySchoolIdAndClassId($school_id, $calss_id ){
        $date = date('Y-m-d H:i:s');
        $order_list = $this->newQuery()->where(['status'=>2,'school_id'=>$school_id])
            ->where('validity_time','>',$date)
            ->where("class_id","=",$calss_id)
            ->whereIn('pay_status',[3,4])
            ->get();
        if ($order_list ->count() > 0 ){
            return $order_list->toArray();
        }

        return  array();

    }

    /**
     *  获取某一个学校下面 某一个课程的订单 已经支付的并且报名的总数
     * @param $school_id
     * @param $class_id
     * @return int
     */
    public function  getOrdersCountBySchoolIdAndClassId( string $school_id,  string $class_id ){
        //$date = date('Y-m-d H:i:s');
        return $this->newQuery()
            ->where( 'status',"=",2)
            ->where('school_id',"=" ,$school_id)
            //->where('validity_time','>',$date)
            ->where("class_id","=",$class_id)
            ->whereIn('pay_status',[3,4])
            ->count('id');

    }


    

}
