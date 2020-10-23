<?php
namespace App\Models;

use App\Models\CourseStocks;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use Illuminate\Support\Facades\DB;

class Enrolment extends Model {
    //指定别的表名
    public $table      = 'ld_student_enrolment';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   添加报名方法
     * @param  data          数组数据
     * @param  author        dzj
     * @param  ctime         2020-04-28
     * return  int
     */
    public static function insertEnrolment($data) {
        return self::insertGetId($data);
    }

    /*
     * @param  description   学员报名的方法
     * @param  参数说明       body包含以下参数[
     *     student_id     学员id
     *     parent_id      学科分类id
     *     lession_id     课程id
     *     lession_price  课程原价
     *     student_price  学员价格
     *     payment_type   付款类型
     *     payment_method 付款方式
     *     payment_fee    付款金额
     * ]
     * @param author    dzj
     * @param ctime     2020-04-28
     * return string
     */
    public static function doStudentEnrolment($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断学校id是否合法
        if(!isset($body['school_id']) || empty($body['school_id']) || $body['school_id'] <= 0){
            return ['code' => 202 , 'msg' => '学校id不合法'];
        }

        //判断学员id是否合法
        if(!isset($body['student_id']) || empty($body['student_id']) || $body['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不合法'];
        }

        //判断学科分类id是否合法
        if(!isset($body['parent_id']) || empty($body['parent_id'])){
            return ['code' => 202 , 'msg' => '学科分类id不合法'];
        }

        //判断课程id是否合法
        if(!isset($body['lession_id']) || empty($body['lession_id']) || $body['lession_id'] <= 0){
            return ['code' => 202 , 'msg' => '课程id不合法'];
        }

        //判断课程原价是否为空
        if(!isset($body['lession_price']) || empty($body['lession_price'])){
            return ['code' => 201 , 'msg' => '请输入课程原价'];
        }

        //判断学员原价是否为空
        if(!isset($body['student_price']) || empty($body['student_price'])){
            return ['code' => 201 , 'msg' => '请输入学员原价'];
        }

        //判断付款类型是否合法
        if(!isset($body['payment_type']) || empty($body['payment_type']) || $body['payment_type'] <= 0 || !in_array($body['payment_type'],[1,2,3,4])){
            return ['code' => 202 , 'msg' => '付款类型不合法'];
        }

        //判断付款方式是否合法
        if(!isset($body['payment_method']) || empty($body['payment_method']) || $body['payment_method'] <= 0 || !in_array($body['payment_method'],[1,2,3])){
            return ['code' => 202 , 'msg' => '付款方式不合法'];
        }

        //判断付款金额是否为空
        if(!isset($body['payment_fee']) || empty($body['payment_fee'])){
            return ['code' => 201 , 'msg' => '请输入付款金额'];
        }

        //判断付款时间是否为空
        if(!isset($body['payment_time']) || empty($body['payment_time'])){
            return ['code' => 201 , 'msg' => '请输入付款时间'];
        }

        //判断是否是自增还是授权
        if(!isset($body['nature']) || !in_array($body['nature'],[0,1])){
            return ['code' => 201 , 'msg' => '课程类型不合法'];
        }

        //判断此学员是否购买过此课程
        $count = Order::where('student_id' , $body['student_id'])->where('class_id' , $body['lession_id'])->where('nature' ,  $body['nature'])->where('status' , 2)->where('oa_status' , 1)->whereIn('pay_status' , [3,4])->where('validity_time' , '>' , date('Y-m-d H:i:s'))->count();
        if($count && $count > 0){
            return ['code' => 202 , 'msg' => '该课程已经开课成功，请不要重复添加'];
        }

        //添加库存总数量
        if($body['nature'] == 1){
            $courseschool = CourseSchool::where(['id'=>$body['lession_id'],'is_del'=>0])->first();
            $add_number = CourseStocks::where('school_id' , $body['school_id'])->where('course_id' , $courseschool['course_id'])->where('is_del' , 0)->where('is_forbid' , 0)->sum('add_number');
            //已使用的库存量
            $use_number = Order::where('class_id' , $body['lession_id'])->where('nature' ,  $body['nature'])->where('status' , 2)->where('oa_status' , 1)->whereIn('pay_status' , [3,4])->count();
            if($use_number >= $add_number){
                return ['code' => 202 , 'msg' => '该课程已售完'];
            }
        }
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //学科分类得转换
        $parent_info = json_decode($body['parent_id'] , true);

        //报名数据信息追加
        $enroll_array = [
            'school_id'      =>   $body['school_id'] ,
            'student_id'     =>   $body['student_id'] ,
            'parent_id'      =>   isset($parent_info[0]) && $parent_info[0] > 0 ? $parent_info[0] : 0 ,
            'child_id'       =>   isset($parent_info[1]) && $parent_info[1] > 0 ? $parent_info[1] : 0 ,
            'lession_id'     =>   $body['lession_id'] ,
            'lession_price'  =>   $body['lession_price'] ,
            'student_price'  =>   $body['student_price'] ,
            'payment_type'   =>   $body['payment_type'] ,
            'payment_method' =>   $body['payment_method'] ,
            'payment_fee'    =>   $body['payment_fee'] ,
            'payment_time'   =>   $body['payment_time'] ,
            'admin_id'       =>   $admin_id ,
            'status'         =>   1 ,
            'create_at'      =>   date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();

        //将数据插入到表中
        if(false !== self::insertEnrolment($enroll_array)){
            //订单表插入逻辑
            $enroll_array['nature']  =  $body['nature'];
            Order::offlineStudentSignup($enroll_array);

            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Enrolment' ,
                'route_url'      =>  'admin/student/doStudentEnrolment' ,
                'operate_method' =>  'insert' ,
                'content'        =>  json_encode($body) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            //事务提交
            DB::commit();
            return ['code' => 200 , 'msg' => '报名成功'];
        } else {
            //事务回滚
            DB::rollBack();
            return ['code' => 203 , 'msg' => '报名失败'];
        }
    }
}
