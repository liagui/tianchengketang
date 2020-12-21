<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Converge;
use App\Models\Coures;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\Pay_order_external;
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
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
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
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
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
                        'third_party_number'=>$arr['transaction_id'],
                        'validity_time'=>$validity,
                        'status'=>2,
                        'oa_status'=>1,
                        'pay_time'=>date('Y-m-d H:i:s'),
                        'update_at'=>date('Y-m-d H:i:s')
                    );
                    $res = Order::where(['order_number'=>$arr['out_trade_no']])->update($arrs);
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
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
        $arr = json_decode($_REQUEST,true);
        file_put_contents('hfwebnotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        if($arr['transStat'] == "S" && $arr['respCode'] == "000000" ){ //支付成功
            $orders = Order::where(['order_number'=>$arr['termOrdId']])->first();
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
                        'third_party_number'=>$arr['termOrdId'],
                        'validity_time'=>$validity,
                        'status'=>2,
                        'oa_status'=>1,
                        'pay_time'=>date('Y-m-d H:i:s'),
                        'update_at'=>date('Y-m-d H:i:s')
                    );
                    $res = Order::where(['order_number'=>$arr['termOrdId']])->update($arrs);
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
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
        }
    }
    public function wxwebnotify(){
        libxml_disable_entity_loader(true);
        $postStr = $this->post_data();  #接收微信返回数据xml格式
        $result = $this->XMLDataParse($postStr);
        $arr = $this->object_toarray($result); #对象转成数组
        file_put_contents('wxwebnotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        if ($arr['return_code'] == 'SUCCESS' && $arr['result_code'] == 'SUCCESS') {
            $orders = Order::where(['order_number'=>$arr['out_trade_no']])->first();
            if ($orders['status'] > 0) {
                return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
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
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
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
                    return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
                }
            }
        }else{
            return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
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
    //微信
    public function wxnotify(){
        libxml_disable_entity_loader(true);
        $postStr = $this->post_data();  #接收微信返回数据xml格式
        $result = $this->XMLDataParse($postStr);
        $arr = $this->object_toarray($result); #对象转成数组
        file_put_contents('wxnotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        if ($arr['return_code'] == 'SUCCESS' && $arr['result_code'] == 'SUCCESS') {
            $orders = Converge::where(['order_number'=>$arr['out_trade_no']])->first()->toArray();
            print_r($orders);die;
            if ($orders['status'] > 0) {
                return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            }else {
                try{
                    DB::beginTransaction();
                    //修改订单状态  增加课程  修改用户收费状态
                    $up = Converge::where(['id'=>$orders['id']])->update(['status'=>1,'update_time'=>date('Y-m-d H:i:s')]);
                    if($up){
                        return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                    }
                    DB::commit();
                    return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
                }
            }
        }else{
            return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
        }
    }

    //xml转数组
    function xmlstr_to_array($xml){
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }
    //xml格式数据解析函数
    function XMLDataParse($data){
        $xml = simplexml_load_string($data, NULL, LIBXML_NOCDATA);
        return $xml;
    }
    //把对象转成数组
    public function object_toarray($arr){
        if (is_object($arr)) {
            $arr = (array)$arr;
        }
        if (is_array($arr)) {
            foreach ($arr as $key => $value) {
                $arr[$key] = $this->object_toarray($value);
            }
        }
        return $arr;
    }
    public function post_data(){
        $receipt = $_REQUEST;
        if ($receipt == null) {
            $receipt = file_get_contents("php://input");
            if ($receipt == null) {
                $receipt = $GLOBALS['HTTP_RAW_POST_DATA'];
            }
        }
        return $receipt;
    }
}

