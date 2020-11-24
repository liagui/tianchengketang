<?php
namespace App\Models;

use App\Providers\aop\AopClient\AopClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
        $order = self::select('ld_order.id','ld_order.order_number','ld_order.order_type','ld_order.price','ld_order.pay_status','ld_order.pay_type','ld_order.status','ld_order.create_at','ld_order.oa_status','ld_order.student_id','ld_order.parent_order_number','ld_student.phone','ld_student.real_name')
            ->leftJoin('ld_student','ld_student.id','=','ld_order.student_id')
            ->where(function($query) use ($data) {
                if(isset($data['school_id']) && !empty($data['school_id'])){
                    $query->where('ld_order.school_id',$data['school_id']);
                }
                if(isset($data['status'])&& $data['status'] != -1){
                    $query->where('ld_order.status',$data['status']);
                }
                if(isset($data['order_number']) && !empty($data['order_number'] != '')){
                    $query->where('ld_order.order_number','like','%'.$data['order_number'].'%')
                        ->orwhere('ld_student.phone','like',$data['order_number'])
                        ->orwhere('ld_student.real_name','like',$data['order_number']);
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
                        $overorder = Order::where(['student_id'=>$order['student_id'],'status'=>2])->count(); //用户已完成订单
                        $userorder = Order::where(['student_id'=>$order['student_id']])->count(); //用户所有订单
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
                    $lesson = CourseSchool::select('course_id as id', 'title','sale_price')->where(['id' => $order_info['class_id']])->first();
                }else{
                    $lesson = Coures::select('id', 'title','sale_price')->where(['id' => $order_info['class_id']])->first();
                }
                if (!empty($lesson)) {
                    $order_info['title'] = $lesson['title'];
                    $order_info['sale_price'] = $lesson['sale_price'];
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
                            $v['learning'] = "已过期123";
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
        //获取头部信息
        $public_list = self::getStudyOrderInfo($data);

        if($data['type'] ==1){
            //直播课次
            $classInfo = self::getCourseClassInfo($public_list,$offset,$pagesize,$page);
            if(isset($data['pagesize']) && isset($data['page'])){
                $all = array_slice($classInfo, $offset, $pagesize);
                return ['code' => 200 , 'msg' => '获取学习记录成功-直播课' , 'study_list'=>$all, 'study_count'=>count($classInfo), 'public_list'=>$public_list];
            }
            foreach($classInfo as $k=>$v){
                unset($classInfo[$k]['course_school_id']);
                unset($classInfo[$k]['cl_id']);
                unset($classInfo[$k]['course_id']);
            }
            return ['code' => 200 , 'msg' => '获取导出学习记录成功-直播课' , 'data'=>$classInfo];
        }
        //录播
        $chapters = self::getCourseChaptersInfo($public_list);
        if(isset($data['pagesize']) && isset($data['page'])){
            $all = array_slice($chapters, $offset, $pagesize);
            return ['code' => 200 , 'msg' => '获取学习记录成功-录播课' , 'study_list'=>$all, 'study_count'=>count($chapters), 'public_list'=>$public_list];
        }
        foreach($chapters as $k=>$v){
            $chapters[$k]['coures_name'] = array_unshift($chapters[$k],$v['coures_name']);
            unset( $chapters[$k]['coures_name']);
        }
        return ['code' => 200 , 'msg' => '获取导出学习记录成功-录播课' , 'data'=>$chapters];
    }
	
	private static function getStudyOrderInfo($data){

        $list =Order::where(['student_id'=>$data['student_id'],'status'=>2])
            ->whereIn('pay_status',[3,4])
			 ->where(function ($query) use ($data) {
                if (isset($data['id']) && !empty($data['id'])) {
                    $query->where('class_id', $data['id']);
                }
            })
            ->select('id','pay_time','class_id','nature','class_id')
            ->orderByDesc('id')
            ->get()->toArray();
        $list = self::array_unique_fb($list,'class_id');
        if(!empty($list)){
            foreach ($list as $k=>$v){
                $list[$k]['study_rate'] = rand(1,100);
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
        if(empty($list)){
            return $list = [];
        }else{
            return array_merge($list);
        }

    }
	private static function array_unique_fb($arr,$key){
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
	//获取直播课次
    private static function getCourseClassInfo($list,$offset,$pagesize,$page){
        //var_dump($list);
        foreach ($list as $k => $v){
            //授权课程
            if($v['nature'] == 1){
                $list[$k]['coures_school'] = CourseSchool::leftJoin('ld_course','ld_course.id','=','ld_course_school.course_id')
                    ->leftJoin('ld_course_live_resource','ld_course_live_resource.course_id','=','ld_course.id')
                    ->leftJoin('ld_course_shift_no','ld_course_shift_no.id','=','ld_course_live_resource.shift_id')
                    ->leftJoin('ld_course_class_number','ld_course_class_number.shift_no_id','=','ld_course_shift_no.id')
                    ->leftJoin('ld_course_live_childs','ld_course_live_childs.class_id','=','ld_course_class_number.id')
                    ->where(['ld_course_school.id' => $v['class_id'], 'ld_course_school.is_del' => 0, 'ld_course_school.status' => 1])
                    ->select('ld_course_school.id as course_school_id','ld_course_school.title as course_title','ld_course_school.course_id','ld_course_live_childs.id as cl_id','ld_course_live_childs.course_name as course_live_childs_name')
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
                    ->select('ld_course.id as course_school_id','ld_course.title as course_title','ld_course_live_childs.id as cl_id','ld_course_live_childs.course_name as course_live_childs_name')
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
        if(empty($coures_list) && empty($coures_school_list)){
            return $res = [];
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
        return $res;

    }
	
	//获取录播课次
    private static function getCourseChaptersInfo($list){

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
                    $coures_school_list[$ks]['teaching_mode'] = '直播';
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
                    $coures_list[$ks]['teaching_mode'] = '直播';
                    $coures_list[$ks]['last_class_time'] = date("Y-m-d  H:i:s",time());
                    $coures_list[$ks]['is_finish'] = '未完成';
                    $coures_list[$ks]['max_class_time'] = date("Y-m-d  H:i:s",time());
                }
            }

        }

        if(empty($coures_list) && empty($coures_school_list)){
            return $res = [];
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
        return $res;
    }
}
