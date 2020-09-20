<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Coures;
use App\Models\Couresmethod;
use App\Models\CourseSchool;
use App\Models\Lesson;
use App\Models\LessonMethod;
use App\Models\LessonSchool;
use App\Models\Method;
use App\Models\Order;
use App\Models\PaySet;
use App\Models\Student;
use App\Models\StudentAccounts;
use App\Models\StudentAccountlog;
use App\Models\Subject;
use App\Tools\AlipayFactory;
use App\Tools\WxpayFactory;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller{
    /*
         * @param  我的订单
         * @param  $type    参数
         * @param  author  苏振文
         * @param  ctime   2020/5/28 10:32
         * return  array
         */
    public function myOrderlist(){
        $data = self::$accept_data;
        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 10;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        $type = isset($data['type'])?$data['type']:0;
        $count = Order::where(['student_id'=>$data['user_info']['user_id']])->count(); //全部条数
        $success = Order::where(['student_id'=>$data['user_info']['user_id'],'status'=>2])->count(); //完成
        $fily = Order::where(['student_id'=>$data['user_info']['user_id'],'status'=>'< 2'])->count(); //未完成
        $orderlist = [];
        if($count >0){
            $orderlist =Order::select('ld_order.id','ld_order.order_number','ld_order.create_at','ld_order.price','ld_order.status','ld_order.pay_time','ld_order.class_id','ld_order.nature')
                ->where(['ld_order.student_id'=>$data['user_info']['user_id']])
                ->where(function($query) use ($type) {
                    if($type == 1){
                        $query->where('ld_order.status','=',1)
                            ->orwhere('ld_order.status','=',2);
                    }
                    if($type == 2){
                        $query->where('ld_order.status','=',0);
                    }
                })
                ->orderByDesc('ld_order.id')
                ->offset($offset)->limit($pagesize)
                ->get()->toArray();
            foreach ($orderlist as $k=>&$v){
                if($v['nature'] == 1){
                    $course = CourseSchool::where(['id'=>$v['class_id'],'is_del'=>0,'status'=>1])->first();
                    $v['title'] = $course['title'];
                }else{
                    $course = Coures::where(['id'=>$v['class_id'],'is_del'=>0,'status'=>1])->first();
                    $v['title'] = $course['title'];
                }
                if($v['status'] == 2){
                    $orderlist[$k]['status'] = 1;
                }else if($v['status'] == 3 || $v['status'] == 4){
                    $orderlist[$k]['status'] = 2;
                }
            }
        }
        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        $arrcount =[
            'count'=>$count,
            'success'=>$success,
            'fily'=>$fily
        ];
        return ['code' => 200 , 'msg' => '获取成功','data'=>$orderlist,'arrcount'=>$arrcount,'page'=>$page];
    }
    /*
         * @param 我的余额日志
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/5/28 15:07
         * return  array
         */
    public function myPricelist(){
        $data = self::$accept_data;
        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 10;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        $count = StudentAccountlog::where(['user_id'=>$data['user_info']['user_id']])->count();
        $pricelog = [];
        if($count > 0){
            $pricelog = StudentAccountlog::select('price','status','create_at')->where(['user_id'=>$data['user_info']['user_id']])
                ->orderByDesc('id')
                ->offset($offset)->limit($pagesize)
                ->get()->toArray();
        }
        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        return ['code' => 200 , 'msg' => '获取成功','data'=>$pricelog,'page'=>$page];
    }
    /*
         * @param  我的课程
         * @param  author  苏振文
         * @param  ctime   2020/6/1 10:33
         * return  array
         */
    public function myLessionlist(){
        $data = self::$accept_data;
        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 10;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        $student_id = $data['user_info']['user_id'];
        $count = Order::where(['student_id'=>$data['user_info']['user_id'],'status'=>2,'oa_status'=>1])
            ->where('validity_time','>',date('Y-m-d H:i:s'))
            ->whereIn('pay_status',[3,4])
            ->count();
        $courses=[];
        $orderlist = Order::select('id as orderid','class_id','nature')
            ->where(['student_id'=>$student_id,'status'=>2,'oa_status'=>1])
            ->where('validity_time','>',date('Y-m-d H:i:s'))
            ->whereIn('pay_status',[3,4])
            ->orderByDesc('id')
            ->offset($offset)->limit($pagesize)->get()->toArray();
        foreach ($orderlist as $k=>&$v) {
            //查询课程
            if ($v['nature'] == 1) {
                $course = CourseSchool::select('id as course_id','admin_id', 'title', 'cover', 'pricing as price', 'sale_price as favorable_price', 'buy_num', 'status', 'is_del', 'course_id as id')->where(['id' => $v['class_id'], 'is_del' => 0, 'status' => 1])->first();
                $method = Couresmethod::select('method_id as id')->where(['course_id' => $course['id'],"ld_course_method.is_del"=>0])->get()->toArray();
                foreach ($method as $key => &$val) {
                    if ($val['id'] == 1) {
                        $val['name'] = '直播';
                    }
                    if ($val['id'] == 2) {
                        $val['name'] = '录播';
                    }
                    if ($val['id'] == 3) {
                        $val['name'] = '其他';
                    }
                }
                //学习人数   基数+订单数
                $ordernum = Order::where(['class_id' => $course['course_id'], 'status' => 2, 'oa_status' => 1,'nature'=>1])->count();
                $course['buy_num'] = $course['buy_num'] + $ordernum;
                $course['methods'] = $method;
            } else {
                $course = Coures::select('id', 'admin_id', 'title', 'cover', 'pricing as price', 'sale_price as favorable_price', 'buy_num', 'status', 'is_del')->where(['id' => $v['class_id'], 'is_del' => 0, 'status' => 1])->first();
                $method = Couresmethod::select('method_id as id')->where(['course_id' => $course['id'],"ld_course_method.is_del"=>0])->get()->toArray();
                foreach ($method as $key => &$val) {
                    if ($val['id'] == 1) {
                        $val['name'] = '直播';
                    }
                    if ($val['id'] == 2) {
                        $val['name'] = '录播';
                    }
                    if ($val['id'] == 3) {
                        $val['name'] = '其他';
                    }
                }
                //学习人数   基数+订单数
                $ordernum = Order::where(['class_id' => $course['id'], 'status' => 2, 'oa_status' => 1,'nature'=>1])->count();
                $course['buy_num'] = $course['buy_num'] + $ordernum;
                $course['methods'] = $method;
            }
            $courses[] = $course;
        }
        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        return ['code' => 200 , 'msg' => '获取成功','data'=>$courses,'page'=>$page];
    }
    /*
         * @param  ceshi
         * @param  author  苏振文
         * @param  ctime   2020/6/10 18:10
         * return  array
         */
    public function myPutclassList(){
        $data = self::$accept_data;

        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 10;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        $count = Collection::where(['student_id'=>$data['user_info']['user_id'],'is_del'=>0])->count();
        if($count > 0){
            $list = Collection::select('ld_lessons.id','ld_lessons.title','ld_lessons.cover','ld_lessons.method','ld_lessons.buy_num')
                ->leftJoin('ld_lessons','ld_lessons.id','=','ld_collections.lesson_id')
                ->where(['ld_collections.is_del'=>0,'ld_lessons.is_del'=>0,'status'=>2])
                ->orderByDesc('ld_collections.created_at')
                ->offset($offset)->limit($pagesize)
                ->get()->toArray();
        }else{
            $list = [];
        }
        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        return ['code' => 200 , 'msg' => '获取成功','data'=>$list,'page'=>$page];
    }
    /*
         * @param  客户端生成预订单
         * @param  type 1安卓2苹果3h5
         * @param  class_id 课程id
         * @param  author  苏振文
         * @param  ctime   2020/5/25 11:20
         * return  array
         */
    public function createOrder(){
        $data = self::$accept_data;
        $data['student_id'] = $data['user_info']['user_id'];
        $orderid = Order::orderPayList($data);
        return response()->json($orderid);
    }

    /*
         * @param  支付
         * type=1购买 传参
         *    user_id
         *    order_id
         *    pay_type(1微信2支付宝3汇聚微信4汇聚支付宝5余额支付
         * type=2充值 传参
         *    user_id
         *    price
         *    pay_type(1微信2支付宝3汇聚微信4汇聚支付宝5余额支付)
         * @param  author  苏振文
         * @param  ctime   2020/5/25 17:34
         * return  array
         */
    public function orderPay(){
        $data = self::$accept_data;
        //获取用户信息
        $user_id = $data['user_info']['user_id'];
        //获取用户信息
        $student = Student::where(['id'=>$user_id])->first()->toArray();
        $user_school_id = $student['school_id'];
        $user_balance = $student['balance'];
        //判断支付类型
        if (empty($data['type']) || !isset($data['type']) || !in_array($data['type'], [1, 2])) {
            return ['code' => 201, 'msg' => '请选择类型'];
        }
        //判断支付方式
        if (empty($data['pay_type']) || !isset($data['pay_type']) || !in_array($data['pay_type'], [1, 2, 3, 4, 5])) {
            return ['code' => 201, 'msg' => '请选择支付方式'];
        }
        if ($data['type'] == 1) {
            //判断订单id
            if (empty($data['order_id']) || !isset($data['order_id'])) {
                return ['code' => 201, 'msg' => '请选择订单'];
            }
            //获取订单信息
            $order = Order::where(['id' => $data['order_id'], 'student_id' => $user_id])->first();
            if(!$order){
                return ['code' => 202, 'msg' => '订单数据有误'];
            }
            if ($order['status'] > 0) {
                return ['code' => 202, 'msg' => '此订单已支付'];
            }
            //订单查询课程
            if($order['nature'] == 1){
                $lesson = CourseSchool::where(['id' => $order['class_id'], 'is_del' => 0, 'status' => 1])->first();
            }else{
                $lesson = Coures::where(['id'=>$order['class_id'],'is_del'=>0,'status'=>1])->first();
            }
            if (!$lesson) {
                return ['code' => 202, 'msg' => '此课程选择无效'];
            }
//            $lesson = Coures::select('id','title','cover','pricing as price','sale_price as favorable_price')->where(['id'=>$order['class_id'],'is_del'=>0,'status'=>1,'school_id'=>$student['school_id']])->first();
//            if(empty($lesson)){
//                $lesson = CourseSchool::select('id','title','cover','pricing as price','sale_price as favorable_price')->where(['id'=>$order['class_id'],'to_school_id'=>$student['school_id'],'is_del'=>0,'status'=>1])->first();
//            }s
//            if(!$lesson){
//                return ['code' => 204 , 'msg' => '此课程选择无效'];
//            }
            if ($data['pay_type'] == 5) {
                if ($lesson['sale_price'] > $user_balance) {
                    return ['code' => 210, 'msg' => '余额不足，请充值！！！！！'];
                } else {
                    DB::beginTransaction();
                    //2020.06.09  订单支付为2，算出课程有效期
                    //扣除用户余额 修改订单信息 加入用户消费记录日志
                    $end_balance = $user_balance - $lesson['sale_price'];
                    $studentstatus = Student::where(['id' => $user_id])->update(['balance' => $end_balance]);
                    //计算用户购买课程到期时间
                    if($lesson['expiry'] ==0){
                        $validity = '3000-01-02 12:12:12';
                    }else{
                        $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
                    }
                    //修改用户报名状态 开课状态
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
                    Student::where(['id'=>$order['student_id']])->update(['enroll_status'=>1,'state_status'=>$state_status]);
                    $orderstatus = Order::where(['id' => $data['order_id']])->update(['pay_type' => 5, 'status' => 2,'oa_status'=>1,'validity_time'=>$validity,'pay_time' => date('Y-m-d H:i:s'),'update_at' =>date('Y-m-d H:i:s')]);
                    $studentlogstatus = StudentAccountlog::insert(['user_id' => $user_id, 'price' => $lesson['sale_price'], 'end_price' => $end_balance, 'status' => 2, 'class_id' => $order['class_id']]);
                    if($studentstatus && $orderstatus&&$studentlogstatus){
                        DB::commit();
                        return response()->json(['code' => 200, 'msg' => '购买成功']);
                    }else{
                        DB::rollback();
                        return ['code' => 203 , 'msg' => '购买失败'];
                    }
                }
            } else {
                 Order::where(['id' => $data['order_id']])->update(['pay_type' =>$data['pay_type'],'update_at' =>date('Y-m-d H:i:s')]);
                $return = $this->payStatus($lesson['title'],$order['order_number'], $data['pay_type'], $lesson['sale_price'],$user_school_id,1);
                return response()->json(['code' => 200, 'msg' => '生成预订单成功', 'data' => $return]);
            }
        } else {
            $sutdent_price = [
                'user_id' => $user_id,
                'order_number' => date('YmdHis', time()) . rand(1111, 9999),
                'price' => $data['price'],
                'pay_type' => $data['pay_type'],
                'order_type' => 1,
                'status' => 0
            ];
            $add = StudentAccounts::insert($sutdent_price);
            if ($add) {
                $return = self::payStatus('充值',$sutdent_price['order_number'], $data['type'], $data['price'],$user_school_id,2);
                return response()->json(['code' => 200, 'msg' => '生成预订单成功', 'data' => $return]);
            }
        }
    }
    //title  商品名
    //$type  1微信2支付宝3汇聚微信4汇聚支付宝
    //$price 钱
    //$school_id学校id
    //$pay_type 1购买2充值
    public function payStatus($title,$order_number, $type, $price,$school_id,$pay_type){
        //判断分校是否开通支付
        if($school_id != 1){
            $branchChool = PaySet::where(['school_id'=>$school_id])->first();
            if(empty($branchChool)){
                $school_id =1;
            }
        }
        switch($type) {
            case "1":
                $wxpay = new WxpayFactory();
                return $return = $wxpay->getPrePayOrder($title,$order_number, $price,$school_id, $pay_type);
            case "2":
                $alipay = new AlipayFactory($school_id);
//                $url = $_SERVER["SERVER_NAME"];
//                if($url == 'testwo.longde999.cn'){
//                    $return = $alipay->createAppPay($title,$order_number, 0.01,$pay_type);
//                }else{
                    $return = $alipay->createAppPay($title,$order_number, $price,$pay_type);
//                }
                $alipay = [
                    'alipay' => $return
                ];
                return $alipay;
            case "3":
                //根据分校查询对应信息
                if ($pay_type == 1) {
                    $notify = "http://" . $_SERVER['HTTP_HOST'] . "/Api/notify/hjWxnotify";
                } else {
                    $notify = "http://" . $_SERVER['HTTP_HOST'] . "/Api/notify/hjWxTopnotify";
                }
                if($school_id == ''){
                    $paylist=[
                        'hj_md_key' => '3f101520d11240299b25b2d2608b03a3',
                        'hj_commercial_tenant_number' => '888107600008111',
                        'hj_wx_commercial_tenant_deal_number' => '777183300269333'
                    ];
                }else{
                    $paylist = PaySet::select('hj_commercial_tenant_number','hj_md_key','hj_wx_commercial_tenant_deal_number')->where(['school_id'=>$school_id])->first();
                    if(empty($paylist) || empty($paylist['hj_commercial_tenant_number'])){
                        return response()->json(['code' => 202, 'msg' => '商户号错误']);
                    }
                }
                $arr = [
                    'p0_Version' => '1.0',
                    'p1_MerchantNo' => $paylist['hj_commercial_tenant_number'],
                    'p2_OrderNo' => $order_number,
                    'p3_Amount' => $price,
                    'p4_Cur' => 1,
                    'p5_ProductName' => $title,
                    'p9_NotifyUrl' => $notify,
                    'q1_FrpCode' => 'WEIXIN_APP',
                    'q7_AppId' => '',
                    'qa_TradeMerchantNo' => $paylist['hj_wx_commercial_tenant_deal_number']
                ];
                $str = $paylist['hj_md_key'];
                $token = $this->hjHmac($arr, $str);
                $arr['hmac'] = $token;
                if (strlen($token) == 32) {
                    $aaa = $this->hjpost($arr);
                    print_r($aaa);die;
                }
                return $arr;
            case "4":
                //根据分校查询对应信息
                if($pay_type == 1){
                    $notify = "http://".$_SERVER['HTTP_HOST']."/Api/notify/hjAlinotify";
                }else{
                    $notify = "http://".$_SERVER['HTTP_HOST']."/Api/notify/hjAliTopnotify";
                }
                if($school_id == ''){
                    $paylist=[
                        'hj_md_key' => '3f101520d11240299b25b2d2608b03a3',
                        'hj_commercial_tenant_number' => '888107600008111',
                        'hj_zfb_commercial_tenant_deal_number' => '777183300269333'
                    ];
                }else{
                    $paylist = PaySet::select('hj_commercial_tenant_number','hj_md_key','hj_zfb_commercial_tenant_deal_number')->where(['school_id'=>$school_id])->first();
                    if(empty($paylist) || empty($paylist['hj_commercial_tenant_number'])){
                        return response()->json(['code' => 202, 'msg' => '商户号错误']);
                    }
                }
                $arr=[
                    'p0_Version'=>'1.0',
                    'p1_MerchantNo'=>$paylist['hj_commercial_tenant_number'],
                    'p2_OrderNo'=>$order_number,
                    'p3_Amount'=>$price,
                    'p4_Cur'=>1,
                    'p5_ProductName'=>$title,
                    'p9_NotifyUrl'=>$notify,
                    'q1_FrpCode'=>'ALIPAY_APP',
                    'qa_TradeMerchantNo'=>$paylist['hj_zfb_commercial_tenant_deal_number']
                ];
                $str = $paylist['hj_md_key'];
                $token = $this->hjHmac($arr,$str);
                $arr['hmac'] = $token;
                if(strlen($token) ==32){
                    $aaa = $this->hjpost($arr);
                    print_r($aaa);die;
                }
        }
    }

    //  苹果内购 充值余额 生成预订单
    public function iphonePayCreateOrder(){
        $data = self::$accept_data;
        $user_id = $data['user_info']['user_id'];
        //生成预订单
        $sutdent_price = [
            'user_id' => $user_id,
            'order_number' => date('YmdHis', time()) . rand(1111, 9999),
            'price' => $data['price'],
            'pay_type' => 5,
            'order_type' => 1,
            'status' => 0
        ];
        $add = StudentAccounts::insert($sutdent_price);
        if($add){
            return response()->json(['code' => 200, 'msg' => '生成预订单成功', 'data' => $sutdent_price]);
        }else{
            return response()->json(['code' => 201, 'msg' => '系统错误']);
        }

    }
    // ios轮询查看订单是否成功
    public function iosPolling(){
        $data = self::$accept_data;
        $user_id = $data['user_info']['user_id'];
        $list = StudentAccounts::where(['user_id'=>$user_id,'order_number'=>$data['order_number']])->first()->toArray();
        if($list['status'] == 0){
            return response()->json(['code' => 202, 'msg' => '暂未支付']);
        }
        if($list['status'] == 1){
            return response()->json(['code' => 200, 'msg' => '支付成功']);
        }
        if($list['status'] == 2){
            return response()->json(['code' => 201, 'msg' => '支付失败']);
        }
    }
    //汇聚签名
    public function hjHmac($arr,$str){
        $newarr = '';
        foreach ($arr as $k=>$v){
            $newarr =$newarr.$v;
        }
        return md5($newarr.$str);
    }
    public function hjpost($data){
        //简单的curl
        $ch = curl_init("https://www.joinpay.com/trade/uniPayApi.action");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
