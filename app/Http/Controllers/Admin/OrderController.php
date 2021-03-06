<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Converge;
use App\Models\Coures;
use App\Models\CouresSubject;
use App\Models\Course;
use App\Models\CourseSchool;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\StudentAccountlog;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
class OrderController extends Controller {
    /*
         * @param  订单列表
         * @param  $school_id  分校id
         * @param  $status  状态
         * @param  $state_time 开始时间
         * @param  $end_time 结束时间
         * @param  $order_number 订单号
         * @param  author  苏振文
         * @param  ctime   2020/5/4 11:29
         * return  array
         */
    public function orderList(){
        $list = Order::getList(self::$accept_data);
        return response()->json(['code' => 200 , 'msg' => '获取成功','data'=>$list]);
    }
    /*
         * @param  查看详情
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/5/6 9:56
         * return  array
         */
    public function findOrderForId(){
        //获取提交的参数
        try{
            $data = Order::findOrderForId(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
         * @param  用户订单
         * @param  author  苏振文
         * @param  ctime   2020/7/11 16:16
         * return  array
         */
    public function orderForStudent(){
        $data = Order::orderForStudent(self::$accept_data);
        return response()->json($data);
    }
    /*
         * @param  审核  通过/不通过   退回审核
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/5/6 9:56
         * return  array
         */
    public function auditToId(){
        //获取提交的参数
        try{
            $data = Order::exitForIdStatus(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  订单退回
         * @param  $order_id
         * @param  author  苏振文
         * @param  ctime   2020/6/10 10:37
         * return  array
         */
    public function orderBack(){
        $data = self::$accept_data;
        if(empty($data['order_id']) || $data['order_id'] == ''){
            return ['code' => 201 , 'msg' => '订单参数不能为空'];
        }
        $orderinfo = Order::where(['id'=>$data['order_id']])->first();
        if(!$orderinfo){
            return ['code' => 201 , 'msg' => '订单参数不对'];
        }
        if($orderinfo['status'] > 0 && $orderinfo['status'] < 3){
            DB::beginTransaction();
            try {
                //苹果内购 退回到余额
                if($orderinfo['pay_type'] == 5){
                    $user = Student::where(['id'=>$orderinfo['student_id']])->first();
                    $endprice = $user['balance'] + $orderinfo['price'];
                    Student::where(['id'=>$orderinfo['student_id']])->update(['balance'=>$endprice]);
                    StudentAccountlog::insert(['user_id'=>$orderinfo['student_id'],'price'=>$orderinfo['price'],'end_price'=>$endprice,'status'=>1]);
                    $up = Order::where(['id'=>$data['order_id']])->update(['status'=>4,'validity_time'=>null]);
                }else{
                    //其他修改状态
                    $up = Order::where(['id'=>$data['order_id']])->update(['status'=>4,'validity_time'=>null]);
                }
                if ($up) {
                    DB::commit();
                    return ['code' => 200 , 'msg' => '退回成功'];
                } else {
                    DB::rollback();
                    return ['code' => 202 , 'msg' => '退回失败'];
                }

            } catch (\Exception $e) {
                DB::rollback();
                return ['code' => 500 , 'msg' => $e->__toString()];
            }
        }else{
            return ['code' => 202 , 'msg' => '此订单无法进行此操作'];
        }
    }
    /*
         * @param  OA修改状态
         * @param  $order_id  订单id
         * @param  $status   状态码1成功2失败
         * @param  author  苏振文
         * @param  ctime   2020/5/6 16:30
         * return  array
         */
    public function orderUpOaForId(){
        //获取提交的参数
        try{
            $data = Order::orderUpOaForId(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  订单导出 excel表格
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/5/6 14:12
         * return  array
         */
    public function ExcelExport(){
            return Excel::download(new \App\Exports\OrderExport(self::$accept_data), 'order.xlsx');
    }
    /*
         * @param  对接oa
         * @param  $order_id
         * @param  author  苏振文
         * @param  ctime   2020/6/1 14:46
         * return  array
         */
    public function buttOa(){
        $data = self::$accept_data;
        $order = Order::where(['id'=>$data['order_id']])->first()->toArray();
        if($order['status'] < 1){
            return ['code' => 201 , 'msg' => '订单未支付，怎么对接OA'];
        }
        //根据订单  查询用户信息  课程信息
        $student = Student::where(['id'=>$order['student_id'],'is_forbid'=>1])->first();
        $lession = Lesson::where(['id'=>$order['class_id'],'is_del'=>0,'is_forbid'=>0])->first();
        $newarr = [
            'orderNo' => $order['order_number'],
            'mobile' => empty($student['phone'])?'17319397103':$student['phone'],
            'price' => $order['price'],
            'courseName' => $lession['title'],
            'createTime' => $order['create_at'],
            'payTime' =>$order['pay_time'],
            'payStatus' => 'PAY_SUCCESS',
            'payType' =>'PAY_OFFLINE_INPUT',
        ];
        $res = $this->curl($newarr);
        $return = json_decode($res,true);
        if($return['code'] == 0){
            return ['code' => 200 , 'msg' => '请求成功'];
        }else{
            return ['code' => 201 , 'msg' => '请求第三方出错','data'=>$return['code']];
        }
    }
    //curl【模拟http请求】
    public function curl($receiptData){
        $url = "47.110.127.119:8082/front/pay/syncOrder";
        //简单的curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $receiptData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }



    //订单未支付超过24小时  修改为无效订单
    public function orderUpinvalid(){
        //获取当前时间
        $validity = date('Y-m-d H:i:s',strtotime('- 1day'));
        $order = Order::where('create_at','<',$validity)->where(['oa_status'=>0,'status'=>0])->get()->toArray();
        //计划日志 失效订单
        file_put_contents('payorderinvalid', '时间:'.date('Y-m-d H:i:s').print_r($order,true),FILE_APPEND);
        if(!empty($order)){
            foreach ($order as $k=>$v){
                Order::where(['id'=>$v['id']])->update(['status'=>5,'update_at'=>date('Y-m-d H:i:s')]);
            }
        }
    }
    //财务报表导出
    public function orderForExceil(){
        return Excel::download(new \App\Exports\FinanceExport(self::$accept_data), '财务报表.xlsx');
    }

    public function scanOrderList(){
        //接受参数
        $data = self::$accept_data;
        //判断网校id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //时间处理
        $begindata="2020-03-04";
        $enddate = "3020-03-04";
        $statetime = !empty($data['state_time'])?$data['state_time']:$begindata;
        $endtime = !empty($data['end_time'])?$data['end_time']:$enddate;
        $state_time = $statetime." 00:00:00";
        $end_time = $endtime." 23:59:59";
        //计算总数
        $total = Converge::where(['school_id'=>$school_id])
            ->where(function($query) use ($data) {
                if(isset($data['real_name']) && !empty($data['real_name'] != '')){
                    $query->where('username','like','%'.$data['real_name'].'%')
                        ->orwhere('phone','like',$data['real_name']);
                }
            })
            ->whereBetween('add_time', [$state_time, $end_time])->count();
        $list=[];
        if($total > 0) {
            //获取数据
            $list = Converge::where(['school_id' => $school_id])
                ->where(function ($query) use ($data) {
                    if (isset($data['real_name']) && !empty($data['real_name'] != '')) {
                        $query->where('username', 'like', '%' . $data['real_name'] . '%')
                            ->orwhere('phone', 'like', $data['real_name']);
                    }
                })
                ->whereBetween('add_time', [$state_time, $end_time])
                ->orderByDesc('id')
                ->offset($offset)->limit($pagesize)->get();
            if (!empty($list)) {
                $list = $list->toArray();
                foreach ($list as $k => &$v) {
                    if ($v['pay_status'] == 1) {
                        $v['pay_status_text'] = '微信';
                    } else if ($v['pay_status'] == 2) {
                        $v['pay_status_text'] = '支付宝';
                    } else if ($v['pay_status'] == 3) {
                        $v['pay_status_text'] = '汇聚-微信';
                    } else if ($v['pay_status'] == 4) {
                        $v['pay_status_text'] = '汇聚-支付宝';
                    } else if ($v['pay_status'] == 5) {
                        $v['pay_status_text'] = '易联扫码';
                    } else if ($v['pay_status'] == 6) {
                        $v['pay_status_text'] = '汇付-支付宝';
                    } else if ($v['pay_status'] == 7) {
                        $v['pay_status_text'] = '汇付-微信';
                    } else if ($v['pay_status'] == 8) {
                        $v['pay_status_text'] = '易联-微信';
                    } else if ($v['pay_status'] == 9) {
                        $v['pay_status_text'] = '易联-支付宝';
                    }
                    if ($v['status'] == 0) {
                        $v['status_text'] = '未支付';
                    } else if ($v['status'] == 1) {
                        $v['status_text'] = '支付成功';
                    } else if ($v['status'] == 2) {
                        $v['status_text'] = '支付失败';
                    }
                    //查询课程名称
                    if($v['nature'] == 1){
                        $course = CourseSchool::where(['id'=>$v['course_id'],'is_del'=>0])->first();
                    }else{
                        $course = Coures::where(['id'=>$v['course_id'],'is_del'=>0])->first();
                    }
                    //大小类
                    $bigsubject = CouresSubject::where(['id'=>$course['parent_id'],'is_del'=>0])->first();
                    $smlsubject = CouresSubject::where(['id'=>$course['child_id'],'is_del'=>0])->first();
                    $v['course_name'] = $course['title'];
                    $v['subject'] = $bigsubject['subject_name'].'-'.$smlsubject['subject_name'];
                }
            }
        }
        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$total
        ];
        return response()->json(['code' => 200 , 'msg' => '查询成功','data'=>$list,'page'=>$page]);
    }
	//收入详情
    public function financeDetails()
    {
        try {
            $data = Order::financeDetails(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500, 'msg' => $ex->getMessage()]);
        }
    }

    //收入详情-搜索内容--学科
    public function search_subject()
    {
        try {
            $data = CouresSubject::couresWheres(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500, 'msg' => $ex->getMessage()]);
        }
    }

    //收入详情-搜索内容--课程
    public function search_course()
    {
        try {
            $data = Coures::courseList(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500, 'msg' => $ex->getMessage()]);
        }
    }
    
        










}
