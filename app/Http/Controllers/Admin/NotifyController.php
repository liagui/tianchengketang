<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Coures;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\Pay_order_external;
use App\Models\Student;
use Illuminate\Support\Facades\DB;


class NotifyController extends Controller{

    //汇聚支付 回调
    public function hjnotify(){
        $order = Pay_order_external::where(['order_no' => $_GET['r2_OrderNo']])->first()->toArray();
        if($order['pay_status'] > 0){
            return "success";
        }
        file_put_contents('hjnotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($_GET,true),FILE_APPEND);
        if($_GET['r6_Status'] == '100'){
            //只修改订单号
            $up = Pay_order_external::where(['id'=>$order['id']])->update(['pay_status'=>1,'pay_time'=>date('Y-m-d H:i:s')]);
            if($up){
                return "success";
            }
        }
        if($_GET['r6_Status'] == '101'){
            $up = Pay_order_external::where(['id'=>$order['id']])->update(['status'=>2]);
            if($up){
                return "success";
            }
        }
    }
    //支付宝支付  回调
    public function zfbnotify(){
        $arr = $_POST;
        file_put_contents('alinotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        if($arr['trade_status'] == 'TRADE_SUCCESS'){
            $orders = Pay_order_external::where(['order_no'=>$arr['out_trade_no']])->first();
            if ($orders['pay_status'] > 0) {
                return 'success';
            }else {
                try{
                    DB::beginTransaction();
                    Pay_order_external::where(['id'=>$orders['id']])->update(['pay_status'=>1,'pay_time'=>date('Y-m-d H:i:s')]);
                    DB::commit();
                    return 'success';
                } catch (Exception $ex) {
                    DB::rollback();
                    return 'fail';
                }
            }
        }else{
            return 'fail';
        }
    }
    //微信回调
    public function wxnotify(){

    }


}
