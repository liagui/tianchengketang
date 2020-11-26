<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coures;
use App\Models\CouresSubject;
use App\Models\CourseLiveClassChild;
use App\Models\CourseSchool;
use App\Models\Lesson;
use App\Models\OpenLivesChilds;
use App\Models\Order;
use App\Models\Student;
use App\Models\StudentAccountlog;
use App\Models\StudentAccounts;
use App\Models\User;
use App\Models\Video;
use App\Providers\aop\AopClient\AopClient;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use OSS\Result\GetWebsiteResult;


class NotifyController extends Controller {
    // region 汇聚 微信 支付宝 等支付平台的回调函数
    //汇聚  购买 支付宝回调接口
    public function hjAlinotify(){
        $json = file_get_contents("php://input");
        Storage ::disk('hjAlinotify')->append('hjAlinotify.txt', 'time:'.date('Y-m-d H:i:s')."\nresponse:".$json);
    }
    //汇聚 购买 微信回调接口
    public function hjWxnotify(){
        $json = file_get_contents("php://input");
        Storage ::disk('hjAlinotify')->append('hjAlinotify.txt', 'time:'.date('Y-m-d H:i:s')."\nresponse:".$json);
    }
    //微信 购买 回调接口
    public function wxnotify($xml){
        if(!$xml) {
            return ['code' => 201 , 'msg' => '参数错误'];
        }
        $data =  self::xmlToArray($xml);
        Storage ::disk('logs')->append('wxpaynotify.txt', 'time:'.date('Y-m-d H:i:s')."\nresponse:".json_encode($data));
        if($data && $data['result_code']=='SUCCESS' && $data['result_code'] == 'SUCCESS') {
            $orderinfo = Order::where(['order_number'=>$data['out_trade_no']])->first()->toArray();
            if (!$orderinfo) {
                return ['code' => 202 , 'msg' => '订单不存在'];
            }
            //完成支付
            if ($orderinfo['status'] > 0 ) {
                return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            }
            DB::beginTransaction();
            try{
                    //修改订单状态  增加用户购买课程有效期
                    $arr = array(
                        'third_party_number'=>$data['transaction_id'],
                        'status'=>1,
                        'pay_time'=>date('Y-m-d H:i:s'),
                        'update_at'=>date('Y-m-d H:i:s')
                    );
                    $res = Order::where(['order_number'=>$data['out_trade_no']])->update($arr);
                    if (!$res) {
                        throw new Exception('回调失败');
                    }
                DB::commit();
                return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            } catch (\Exception $ex) {
                DB::rollback();
                return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
            }
        } else {
            return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
        }
    }
    //支付宝 购买 回调接口
    public function alinotify(){
        $arr = $_POST;
        file_put_contents('alipaylog.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
//        require_once './App/Tools/Ali/aop/AopClient.php';
//        require_once('./App/Tools/Ali/aop/request/AlipayTradeAppPayRequest.php');
//        $aop = new AopClient();
//        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAh8I+MABQoa5Lr0hnb9+UeAgHCtZlwJ84+c18Kh/JWO+CAbKqGkmZ6GxrWo2X/vnY2Qf6172drEThHwafNrUqdl/zMMpg16IlwZqDeQuCgSM/4b/0909K+RRtUq48/vRM6denyhvR44fs+d4jZ+4a0v0m0Kk5maMCv2/duWejrEkU7+BG1V+YXKOb0++n8We/ZIrG/OiiXedViwSW3il9/Q5xa21KlcDPjykWyoPolR2MIFqu8PLh2z8uufCPSlFuABMyL+djo8y9RMzTWH+jN2WxcqMSDMIcwGFk3emZKzoy06a5k4Ea8/l3uHq8sbbepvpmC/dZZ0+CZdXgPnVRywIDAQAB';
//        $flag = $aop->rsaCheckV1($arr, NULL, "RSA2");
//        Storage ::disk('logs')->append('alipaynotify.txt', 'time:'.date('Y-m-d H:i:s')."\nresponse:".$arr);

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

    //汇聚  充值 支付宝回调接口
    public function hjAliTopnotify(){
    }
    //汇聚 充值 微信回调接口
    public function hjWxTopnotify(){
    }
    //微信 充值 回调接口
    public function wxTopnotify(){
    }
    //支付宝 充值 回调接口
    public function aliTopnotify(){
    }
    //iphone 内部支付 回调
    public function iphonePaynotify(){
        $data = self::$accept_data;
        $receiptData = $data['receiptData'];
        $order_number = $data['order_number'];
        $file = "./orderpaylog";
        if (file_exists($file) == false) {
            //检查是否有该文件夹，如果没有就创建，并给予最高权限
            mkdir($file, 0700, true);
        }
        file_put_contents('./orderpaylog/'.$order_number.'.txt', '时间:'.date('Y-m-d H:i:s').print_r($data,true),FILE_APPEND);
        if(!isset($data['receiptData']) ||empty($receiptData)){
            return response()->json(['code' => 201 , 'msg' => 'receiptData没有']);
        }
        if(!isset($data['order_number']) ||empty($order_number)){
            return response()->json(['code' => 201 , 'msg' => 'order_number没有']);
        }
        // 验证参数
        if (strlen($receiptData) < 20) {
            return response()->json(['code' => 201 , 'msg' => '不能读取你提供的JSON对象']);
        }
        $studentprice = StudentAccounts::where(['order_number'=>$order_number])->first();
        if($studentprice['status'] > 0){
            return response()->json(['code' => 200 , 'msg' => '此订单已处理']);
        }
        // 请求验证【默认向真实环境发请求】
        $html = $this->acurl($receiptData);
        $arr = json_decode($html, true);//接收苹果系统返回数据并转换为数组，以便后续处理
        // 如果是沙盒数据 则验证沙盒模式
        if ($arr['status'] == '21007') {
            // 请求验证  1代表向沙箱环境url发送验证请求
            $html = $this->acurl($receiptData, 1);
            $arr = json_decode($html, true);
            //获取域名   正式环境 沙箱请求 不处理
            $url = $_SERVER["SERVER_NAME"];
            if($url == 'api.longde999.cn'){
                $arr['pay_namess'] = "沙箱环境，不予处理金额变动";
                $arr['http_referer'] = $_SERVER["SERVER_NAME"];
                file_put_contents('./orderpaylog/'.$order_number.'.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
                return response()->json(['code' => 200 , 'msg' =>'支付成功']);
            }
        }
        file_put_contents('./orderpaylog/'.$order_number.'.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        // 判断是否购买成功  【状态码,0为成功（无论是沙箱环境还是正式环境只要数据正确status都会是：0）】
        if (intval($arr['status']) === 0) {
            $codearr=[
                'tc001'=>6,
                'tc003'=>18,
                'tc004'=>68,
                'tc005'=>168,
                'tc006'=>388,
                'tc007'=>698,
                'tc008'=>1998,
                'tc009'=>3998,
                'tc0010'=>6498,
            ];
            if(!isset($arr['receipt']['in_app']) || empty($arr['receipt']['in_app'])){
                return response()->json(['code' => 200 , 'msg' => '无充值记录']);
            }

            DB::beginTransaction();
            try {
                //用户余额信息
                $student = Student::where(['id'=>$studentprice['user_id']])->first();
                foreach ($arr['receipt']['in_app']  as $k=>$v){
                    if($codearr[$v['product_id']] == $studentprice['price']){
                        $endbalance = $student['balance'] + $studentprice['price']; //用户充值后的余额
                        Student::where(['id'=>$studentprice['user_id']])->update(['balance'=>$endbalance]);  //修改用户余额
                        $userorder = StudentAccounts::where(['user_id'=>$studentprice['user_id'],'order_number'=>$order_number,'price'=>$studentprice['price'],'pay_type'=>5,'order_type'=>1])->update(['third_party_number'=>$v['transaction_id'],'content'=>$arr['receipt']['in_app'][$k],'status'=>1,'update_at'=>date('Y-m-d H:i:s')]);
                        if($userorder){
                            StudentAccountlog::insert(['user_id'=>$studentprice['user_id'],'price'=>$studentprice['price'],'end_price'=>$endbalance,'status'=>1]);
                        }
                    }
                }
                DB::commit();

            } catch (\Exception $ex) {
                DB::rollBack();
                return response()->json(['code' => 500 , 'msg' => $ex->__toString()]);
            }
            return response()->json(['code' => 200 , 'msg' => '支付成功']);
        }else{
            if(in_array('Failed',$arr)){
                StudentAccounts::where(['order_number'=>$order_number])->update(['content'=>$html,'status'=>2,'update_at'=>date('Y-m-d H:i:s')]);
                return response()->json(['code' => 207 , 'msg' =>'支付失败']);
            }
            if(in_array('Deferred',$arr)){
                return response()->json(['code' => 207 , 'msg' =>'等待确认，儿童模式需要询问家长同意']);
            }
        }
    }

    // endregion



    // region curl 和 xmlToArray 等工具函数

    //curl【模拟http请求】
    public function acurl($receiptData, $sandbox = 0){
        //小票信息
        $POSTFIELDS = array("receipt-data" => $receiptData);
        $POSTFIELDS = json_encode($POSTFIELDS);
        //正式购买地址 沙盒购买地址
        $urlBuy = "https://buy.itunes.apple.com/verifyReceipt";
        $urlSandbox = "https://sandbox.itunes.apple.com/verifyReceipt";
        $url = $sandbox ? $urlSandbox : $urlBuy;//向正式环境url发送请求(默认)
        //简单的curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POSTFIELDS);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    //region cc 直播回调函数



    //endregion


    //xml转换数组
    public static function xmlToArray($xml) {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }
    // endregion

}
