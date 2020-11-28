<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Converge;
use App\Models\Coures;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class NotifyController extends Controller {
    //支付宝 web端直接购买支付宝回调
    public function alinotify(){
        $arr = $_POST;
        file_put_contents('alinotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        if($arr['trade_status'] == 'TRADE_SUCCESS'){
            $orders = Order::where(['order_number'=>$arr['out_trade_no']])->first();
            if ($orders['status'] > 0) {
                return 'success';
            }else {
                try{
                    DB::beginTransaction();
                    //修改订单状态  增加课程  修改用户收费状态
                    if($orders['nature'] == 1){
                        $lesson = CourseSchool::where(['id'=>$orders['class_id']])->first();
                    }else{
                        $lesson = Coures::where(['id'=>$orders['class_id']])->first();
                    }
                    if($lesson['expiry'] ==0){
                        $validity = '3000-01-02 12:12:12';
                    }else{
                        $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
                    }
                    $arrs = array(
                        'third_party_number'=>$arr['trade_no'],
                        'validity_time'=>$validity,
                        'status'=>2,
                        'oa_status'=>1,
                        'pay_time'=>date('Y-m-d H:i:s'),
                        'update_at'=>date('Y-m-d H:i:s')
                    );
                    $res = Order::where(['order_number'=>$arr['out_trade_no']])->update($arrs);
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->count(); //用户所有订单
                    if($overorder == $userorder){
                        $state_status = 2;
                    }else{
                        if($overorder > 0 ){
                            $state_status = 1;
                        }else{
                            $state_status = 0;
                        }
                    }
                    Student::where(['id'=>$orders['student_id']])->update(['enroll_status'=>1,'state_status'=>$state_status]);
                    if (!$res) {
                        //修改用户类型
                        throw new Exception('回调失败');
                    }
                    DB::commit();
                    return 'success';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return 'fail';
                }
            }
        }else{
            return 'fail';
        }
    }
    public function hjwebnotify(){
        file_put_contents('hjwebnotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($_GET,true),FILE_APPEND);
        if($_GET['r6_Status'] == 100){
            $orders = Order::where(['order_number'=>$_GET['r2_OrderNo']])->first();
            if ($orders['status'] > 0) {
                return 'success';
            }else {
                try{
                    DB::beginTransaction();
                    //修改订单状态  增加课程  修改用户收费状态
                    if($orders['nature'] == 1){
                        $lesson = CourseSchool::where(['id'=>$orders['class_id']])->first();
                    }else{
                        $lesson = Coures::where(['id'=>$orders['class_id']])->first();
                    }
                    if($lesson['expiry'] ==0){
                        $validity = '3000-01-02 12:12:12';
                    }else{
                        $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
                    }
                    $arrs = array(
                        'third_party_number'=>$_GET['r7_TrxNo'],
                        'validity_time'=>$validity,
                        'status'=>2,
                        'oa_status'=>1,
                        'pay_time'=>date('Y-m-d H:i:s'),
                        'update_at'=>date('Y-m-d H:i:s')
                    );
                    $res = Order::where(['order_number'=>$_GET['r2_OrderNo']])->update($arrs);
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->count(); //用户所有订单
                    if($overorder == $userorder){
                        $state_status = 2;
                    }else{
                        if($overorder > 0 ){
                            $state_status = 1;
                        }else{
                            $state_status = 0;
                        }
                    }
                    Student::where(['id'=>$orders['student_id']])->update(['enroll_status'=>1,'state_status'=>$state_status]);
                    if (!$res) {
                        //修改用户类型
                        throw new Exception('回调失败');
                    }
                    DB::commit();
                    return 'success';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return 'fail';
                }
            }
        }else{
            return 'fail';
        }
    }
    public function ylwebnotify(){
        $xml = file_get_contents('php://input');
        $arr = $this->xmlstr_to_array($xml);
        file_put_contents('ylwebnotify.txt', '时间:' . date('Y-m-d H:i:s') . print_r($arr, true), FILE_APPEND);
        if($arr['status'] == 0){
            $orders = Order::where(['order_number'=>$arr['out_trade_no']])->first();
            if ($orders['status'] > 0) {
                return 'success';
            }else {
                try{
                    DB::beginTransaction();
                    //修改订单状态  增加课程  修改用户收费状态
                    if($orders['nature'] == 1){
                        $lesson = CourseSchool::where(['id'=>$orders['class_id']])->first();
                    }else{
                        $lesson = Coures::where(['id'=>$orders['class_id']])->first();
                    }
                    if($lesson['expiry'] ==0){
                        $validity = '3000-01-02 12:12:12';
                    }else{
                        $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
                    }
                    $arrs = array(
                        'third_party_number'=>$arr['trade_no'],
                        'validity_time'=>$validity,
                        'status'=>2,
                        'oa_status'=>1,
                        'pay_time'=>date('Y-m-d H:i:s'),
                        'update_at'=>date('Y-m-d H:i:s')
                    );
                    $res = Order::where(['order_number'=>$arr['out_trade_no']])->update($arrs);
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->count(); //用户所有订单
                    if($overorder == $userorder){
                        $state_status = 2;
                    }else{
                        if($overorder > 0 ){
                            $state_status = 1;
                        }else{
                            $state_status = 0;
                        }
                    }
                    Student::where(['id'=>$orders['student_id']])->update(['enroll_status'=>1,'state_status'=>$state_status]);
                    if (!$res) {
                        //修改用户类型
                        throw new Exception('回调失败');
                    }
                    DB::commit();
                    return 'success';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return 'fail';
                }
            }
        }else{
            return 'fail';
        }

    }
    public function hfwebnotify(){
        $arr = $_POST;
        file_put_contents('alinotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        if($arr['trade_status'] == 'TRADE_SUCCESS'){
            $orders = Order::where(['order_number'=>$arr['out_trade_no']])->first();
            if ($orders['status'] > 0) {
                return 'success';
            }else {
                try{
                    DB::beginTransaction();
                    //修改订单状态  增加课程  修改用户收费状态
                    if($orders['nature'] == 1){
                        $lesson = CourseSchool::where(['id'=>$orders['class_id']])->first();
                    }else{
                        $lesson = Coures::where(['id'=>$orders['class_id']])->first();
                    }
                    if($lesson['expiry'] ==0){
                        $validity = '3000-01-02 12:12:12';
                    }else{
                        $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
                    }
                    $arrs = array(
                        'third_party_number'=>$arr['trade_no'],
                        'validity_time'=>$validity,
                        'status'=>2,
                        'oa_status'=>1,
                        'pay_time'=>date('Y-m-d H:i:s'),
                        'update_at'=>date('Y-m-d H:i:s')
                    );
                    $res = Order::where(['order_number'=>$arr['out_trade_no']])->update($arrs);
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->count(); //用户所有订单
                    if($overorder == $userorder){
                        $state_status = 2;
                    }else{
                        if($overorder > 0 ){
                            $state_status = 1;
                        }else{
                            $state_status = 0;
                        }
                    }
                    Student::where(['id'=>$orders['student_id']])->update(['enroll_status'=>1,'state_status'=>$state_status]);
                    if (!$res) {
                        //修改用户类型
                        throw new Exception('回调失败');
                    }
                    DB::commit();
                    return 'success';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return 'fail';
                }
            }
        }else{
            return 'fail';
        }
    }

    /*==========================扫码支付===========================*/
    //银联回调地址
    public function ylnotify(){
        $xml = file_get_contents('php://input');
        $arr = $this->xmlstr_to_array($xml);
        file_put_contents('ylwebnotify.txt', '时间:' . date('Y-m-d H:i:s') . print_r($arr, true), FILE_APPEND);
        if($arr['status'] == 0){
            $orders = Converge::where(['order_number'=>$arr['out_trade_no']])->first();
            if ($orders['status'] > 0) {
                return 'success';
            }else {
                try{
                    DB::beginTransaction();
                    //修改订单状态  增加课程  修改用户收费状态
                    $up = Converge::where(['id'=>$orders['id']])->update(['status'=>1,'update_time'=>date('Y-m-d H:i:s')]);
                    if($up){
                        return "success";
                    }
                    DB::commit();
                    return 'success';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return 'fail';
                }
            }
        }else{
            return 'fail';
        }

    }
    //汇聚支付 回调
    public function hjnotify(){
        $order = Converge::where(['order_number' => $_GET['r2_OrderNo']])->first()->toArray();
        if($order['status'] > 0){
            return "success";
        }
        if($_GET['r6_Status'] == '100'){
            //只修改订单号
            $up = Converge::where(['id'=>$order['id']])->update(['status'=>1,'update_time'=>date('Y-m-d H:i:s'),'pay_time'=>date('Y-m-d H:i:s')]);
            if($up){
                return "success";
            }
        }
        if($_GET['r6_Status'] == '101'){
            $up = Converge::where(['id'=>$order['id']])->update(['status'=>2,'update_time'=>date('Y-m-d H:i:s')]);
            if($up){
                return "success";
            }
        }
    }
    //支付宝
    public function convergecreateNotifyPcPay(){
        $arr = $_POST;
        file_put_contents('alinotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        if($arr['trade_status'] == 'TRADE_SUCCESS'){
            $orders = Converge::where(['order_number'=>$arr['out_trade_no']])->first();
            if ($orders['status'] > 0) {
                return 'success';
            }else {
                try{
                    DB::beginTransaction();
                    //修改订单状态  增加课程  修改用户收费状态
                    $up = Converge::where(['id'=>$orders['id']])->update(['status'=>1,'update_time'=>date('Y-m-d H:i:s')]);
                    if($up){
                        return "success";
                    }
                    DB::commit();
                    return 'success';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return 'fail';
                }
            }
        }else{
            return 'fail';
        }
    }
    //汇付
    public function hfnotify(){

    }

    //xml转数组
    function xmlstr_to_array($xml){
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }
}

