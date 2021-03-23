<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Coures;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\School;
use App\Models\Student;

class PublicpayController extends Controller {
    protected $school;
    protected $data;
    protected $userid;
    public function __construct(){
        $this->data = $_REQUEST;
        if(!isset($this->data['school_dns']) || empty($this->data['school_dns'])){
            return response()->json(['code' => 201 , 'msg' => '请传域名']);
        }
        $this->school = School::where(['dns'=>$this->data['school_dns'],'is_del'=>1])->first();//改前
        if(count($this->school)<=0){
             return ['code' => 201 , 'msg' => '该网校不存在,请联系管理员！'];exit;
        }
        //$this->school = $this->getWebSchoolInfo($this->data['school_dns']); //改后
        $this->userid = isset(AdminLog::getAdminInfo()->admin_user->id)?AdminLog::getAdminInfo()->admin_user->id:0;
    }
   /*
        * @param  OA流转订单
        * @param  author  苏振文
        * student_id  用户id
        * order_number 订单号
        * pay_status 1定金2尾款3最后一笔款4全款
        * pay_type 1微信2支付宝3银行转账4汇聚5余额
        * pay_time 支付时间
        * class_id 课程id
        * nature 1 授权 0自增
        * @param  ctime   2020/7/8 15:55
        * return  array
        */
   public function orderOAtoPay(){
       if(!isset($this->data['nature'])){
           return response()->json(['code' => 201 , 'msg' => '课程类型(授权自增)不能为空']);
       }
       if(!isset($this->data['class_id']) || empty($this->data['class_id'])){
           return response()->json(['code' => 201 , 'msg' => '课程不能为空']);
       }
       if(!isset($this->data['order_number']) || empty($this->data['order_number'])){
           return response()->json(['code' => 201 , 'msg' => '订单号不能为空']);
       }
       if(!isset($this->data['pay_time']) || empty($this->data['pay_time'])){
           return response()->json(['code' => 201 , 'msg' => '订单支付时间']);
       }
       if(!isset($this->data['pay_type']) || empty($this->data['pay_type'])){
           return response()->json(['code' => 201 , 'msg' => '支付方式不能为空']);
       }
       if(!isset($this->data['pay_status']) || empty($this->data['pay_status'])){
           return response()->json(['code' => 201 , 'msg' => '支付类型不能为空']);
       }
       if(!isset($this->data['student_id']) || empty($this->data['student_id'])){
           return response()->json(['code' => 201 , 'msg' => '学生不能为空']);
       }
       //查询课程信息
       if($this->data['nature'] == 1){
          $couser = CourseSchool::where(['to_school_id'=>$this->school['id'],'course_id'=>$this->data['class_id'],'is_del'=>0])->first()->toArray();
          $school_id = $couser['to_school_id'];
       }else{
          $couser = Coures::where(['id'=>$this->data['class_id'],'is_del'=>0])->first()->toArray();
          $school_id = $couser['school_id'];
       }
       if(!$couser){
           return response()->json(['code' => 201 , 'msg' => '课程id错误']);
       }
       $find = Order::where(['order_number'=>$this->data['order_number']])->first();
       if(!empty($find)){
           return response()->json(['code' => 202 , 'msg' => '此订单已存在']);
       }
       $arr=[
           'order_number' => $this->data['order_number'],
           'order_type' => 1,
           'student_id' => $this->userid,
           'price' => $couser['pricing'],
           'lession_price' =>$couser['sale_price'],
           'pay_status' =>$this->data['pay_status'],
           'pay_type' =>$this->data['pay_type'],
           'status' =>1,
           'pay_time' =>$this->data['pay_time'],
           'oa_status' =>0,
           'class_id' =>$this->data['class_id'],
           'nature' =>$this->data['nature'],
           'school_id' =>$school_id
       ];
       Order::insert($arr);
       //修改用户报名状态
//       Student::where(['id'=>$this->data['class_id']])->update(['enroll_status'=>1,'update_at'=>date('Y-m-d H:i:s')]);
       return response()->json(['code' => 200 , 'msg' => '成功']);
   }
}
