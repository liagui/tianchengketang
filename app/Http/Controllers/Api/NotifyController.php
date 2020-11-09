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
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;


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
            try{
                DB::beginTransaction();
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
                return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                DB::commit();
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
            DB::beginTransaction();
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
            return response()->json(['code' => 200 , 'msg' => '支付成功']);
        }else{
            if(in_array('Failed',$arr)){
                StudentAccounts::where(['order_number'=>$order_number])->update(['content'=>$html,'status'=>2,'update_at'=>date('Y-m-d H:i:s')]);
                return response()->json(['code' => 207 , 'msg' =>'支付失败']);
            }
            if(in_array('Deferred',$arr)){
                return response()->json(['code' => 207 , 'msg' =>'等待确认，儿童模式需要询问家长同意']);
            }
            DB::rollBack();
        }
    }
    // endregion

    // region CC 直播 验证登录函数


    /**
     *
     *  CC 直播 自动登录需要的 回调函数
     * @return JsonResponse
     */
    public  function  CCUserCheckUrl(){
        $data = self::$accept_data;
        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'pc';
        $CCCloud = new CCCloud();

        //获取用户token值
        $token = $data[ 'user_token' ];

        //判断用户token是否为空
        if (!$token || empty($token)) {
            //return [ 'code' => 401, 'msg' => '请登录账号' ];
            return $this->response($CCCloud->cc_user_login_function(false, array()));
        }

        //hash中token赋值
        $token_key = "user:regtoken:" . $platform . ":" . $token;

        //判断token值是否合法
        $redis_token = Redis::hLen($token_key);
        if( !empty($redis_token) && $redis_token > 0) {
            //解析json获取用户详情信息
            $json_info = Redis::hGetAll($token_key);

            //判断是正常用户还是游客用户
            if($json_info['user_type'] && $json_info['user_type'] == 1){

                //根据手机号获取用户详情
                $user_info = User::where('school_id' , $json_info['school_id'])->where("phone" , $json_info['phone'])->first();

                if(!$user_info || empty($user_info)){
                    return  $this->response($CCCloud->cc_user_login_function(false, array()));
                }

                //判断用户是否被禁用
                if($user_info['is_forbid'] == 2){
                    return  $this->response($CCCloud->cc_user_login_function(false, array()));
                }

                return $this->response($CCCloud->cc_user_login_function(true, $user_info));
            } else if($json_info['user_type'] && $json_info['user_type'] == 2){
                //通过device获取用户信息
                $user_info = User::select("id as user_id" , "is_forbid")->where("device" , $json_info['device'])->first();
                if(!$user_info || empty($user_info)){
                    return $this->response( $CCCloud->cc_user_login_function(false, array()));
                }

                //判断用户是否被禁用
                if($user_info['is_forbid'] == 2){
                      return  $this->response($CCCloud->cc_user_login_function(false, array()));
                }
                return $this->response($CCCloud->cc_user_login_function(true, $user_info));
            }
        } else {
            return  $this->response($CCCloud->cc_user_login_function(false, array()));
        }

    }


    /**
     *  cc 点播 视频上传成功 cc平台直播进行回调
     */
    public function CCUploadVideo()
    {
        $data = self::$accept_data;

        $videoid = $data[ 'videoid' ];//	视频 id，16位 hex 字符串
        $status = $data[ 'status' ];//	视频状态。”OK”表示视频处理成功，”FAIL”表示视频处理失败。
        $duration = $data[ 'duration' ];//	片长(单位:秒)
        $image = $data[ 'image' ];//	视频截图地址

        if ($status == "FAIL") {
            Log::error("视频处理失败[videoid:$videoid]");
        }

        // 设定 cc 上传的视频 成功
        $video = new Video();
        $ret = $video->auditVideo($videoid);

        if ($ret[ 'code' ] == 200) {
            // 更新 视频的 分类 将视频移动到 学校/分类/分类 目录下面
            $video_info = $ret[ 'info' ];

            if (!isset($ret[ 'video_info' ])) {

                $school_id = $ret[ 'video_info' ][ 'school_id' ];
                $parent_id = $ret[ 'video_info' ][ 'parent_id' ];
                $child_id = $ret[ 'video_info' ][ 'child_id' ];

                $path_info = CouresSubject::GetSubjectNameById($school_id, $parent_id, $child_id);

                $CCCloud = new CCCloud();
                $ret = $CCCloud->cc_spark_video_category_v2();

                if (!empty($ret)) {
                    $cc_category = $ret[ 'data' ];
                    $first_category = array();
                    foreach ($cc_category as $first_item) {
                        // 如果找到了 一级分类 学校
                        if ($path_info[ 'school_name' ] == $first_item[ 'name' ]) {
                            $first_category = $first_item;
                        }
                    }

                    if (empty($first_category)) {
                        // 如果没有找到一级分类
                        $category_id = $CCCloud->makeCategory('',
                            [ $path_info[ 'school_name' ], $path_info[ 'parent_name' ], $path_info[ 'children_name' ] ]);
                        $CCCloud -> move_video_category($video,$category_id);
                    } else {
                        $sub_category = array();
                        // 处理二级 目录
                        foreach ($first_category[ 'sub-category' ] as $sub_item) {
                            // 如果找到了 一级分类 学校
                            if ($path_info[ 'parent_name' ] == $sub_item[ 'name' ]) {
                                $sub_category = $sub_item;
                            }
                        }
                        if (empty($sub_category)) {
                            // 如果没有找到二级目录
                            $category_id = $CCCloud->makeCategory($first_category[ 'id' ],
                                [ $path_info[ 'parent_name' ], $path_info[ 'children_name' ] ]);
                            $CCCloud -> move_video_category($video,$category_id);
                        } else {
                            //  处理三级目录

                            $child_category = array();
                            //  遍历 三级 目录
                            foreach ($sub_category[ 'sub-category' ] as $child) {
                                // 如果找到了 一级分类 学校
                                if ($path_info[ 'children_name' ] == $child[ 'name' ]) {
                                    $child_category = $child;
                                }
                            }
                            if (empty($child_category)) {
                                // 如果没有找到一级分类
                                $category_id = $CCCloud->makeCategory($sub_category[ 'id' ], [ $path_info[ 'children_name' ] ]);
                                $CCCloud -> move_video_category($video,$category_id);
                            }else{

                                $CCCloud -> move_video_category($video,$child_category['id']);
                            }

                        }

                    }
                }
            }
        }

        $ret = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><result>OK</result>";
        return $ret;
    }


    // CC 直播回调函数 当直播开始、结束和 直播回放 录制开始、结束的时候 CC 平台会进行回调
    public function ccliveCallback()
    {
        $data = self::$accept_data;

        Log::info('CC 回调参数 :'.json_encode($data));

        // CC 直播 的 回调 类型
        $CC_CALLBACK_TYPE = array(
            "1"   => "直播开始",
            "2"   => "直播结束",
            "101" => "录制开始",
            "102" => "录制结束",
            "103" => "录制完成",
            "200" => "离线回放",
        );
        $type = $data[ 'type' ];// 回调 类型
        switch ($type) {
            case "1":  //直播开始
                {
                    $userId = $data[ 'userId' ];    //CC账号ID
                    $roomId = $data[ 'roomId' ];    //直播间ID
                    $liveId = $data[ 'liveId' ];    //直播ID
                    $startTime = $data[ 'startTime' ]; //直播开始时间, 格式为"yyyy-MM-dd HH:mm:ss"



                }
                break;
            case "2": // 直播结束
                {
                    $userId = $data[ 'userId' ];    //CC账号ID
                    $roomId = $data[ 'roomId' ];    //直播间ID
                    $liveId = $data[ 'liveId' ];//	直播ID

                    $startTime = $data[ 'startTime' ];    //直播开始时间, 格式为"yyyy-MM-dd HH:mm:ss"
                    $endTime = $data[ 'endTime' ];    //直播结束时间, 格式为"yyyy-MM-dd HH:mm:ss"
                    $stopStatus = $data[ 'stopStatus' ];    //直播结束状态，10：正常结束，20：非正常结束

                    Log::info('CC直播结束:'.json_encode($data));
                    // 更新 课程的直报状 3 表示已经 结束
                    $live = CourseLiveClassChild::where(['course_id' => $roomId])->first();
                    if(empty($live)){
                        $live =  OpenLivesChilds::where(['course_id' => $roomId])->first(); //公开课
                    }
                    $live->status = 3;
                    $live->save();


                }
                break;
            case "101": // 录制完成
            case "102": // 录制结束
            case "103": // 录制完成
                {
                    $userId = $data[ 'userId' ];    //CC账号
                    $roomId = $data[ 'roomId' ];    //直播间ID
                    $liveId = $data[ 'liveId' ];    //直播ID
                    $recordId = $data[ 'recordId' ];    //回放ID

                    $startTime = $data[ 'startTime' ];    //录制开始时间, 格式为"yyyy-MM-dd HH:mm:ss"
                    $endTime = $data[ 'endTime' ];    //录制结束时间, 格式为"yyyy-MM-dd HH:mm:ss"（回调类型type为102或103时，会返回该参数）
                    $recordStatus = $data[ 'recordStatus' ]; //回放状态，10：回放处理成功，20：回放处理失败，30：录制时间过长（回调类型type为103时，会返回该参数）
                    $sourcetype = $data[ 'sourcetype' ];    //回放来源，0：录制； 1：合并； 2：迁移； 3：上传； 4:裁剪（回调类型type为103时，会返回该参数）
                    $recordVideoId = $data[ 'recordVideoId' ];    //回放视频ID（回放状态recordStatus为10时，会返回该参数）
                    $recordVideoDuration = $data[ 'recordVideoDuration' ];    //回放视频时长，单位：秒（回放状态recordStatus为10时，会返回该参数）
                    $replayUrl = $data[ 'replayUrl' ];    //回放观看地址（回放状态recordStatus为10时，会返回该参数）

                    if($type == "103" and $recordStatus == "10"){
                        Log::info('CC直播回放录制完成:'.json_encode($data));

                        $live = CourseLiveClassChild::where(['course_id' => $roomId])->first();
                        if(empty($live)){
                            $live =  OpenLivesChilds::where(['course_id' => $roomId])->first();//公开课
                        }
                        $live->playback = 1;
                        $live->playbackUrl = $replayUrl;
                        $live->duration = $recordVideoDuration;
                        $live->save();
                    }

                }
            break;
            case "200": // 离线回放
                {
                    $userId = $data[ 'userId' ];    //CC账号
                    $roomId = $data[ 'roomId' ];    //直播间ID
                    $liveId = $data[ 'liveId' ];    //直播ID
                    $recordId = $data[ 'recordId' ];    //回放ID

                    $offlineStatus = $data[ 'offlineStatus' ];    //离线包可用状态（10：可用，20：不可用）
                    $offlineMd5 = $data[ 'offlineMd5' ];    //离线包MD5
                    $offlineUrl = $data[ 'offlineUrl' ];    //离线包地址

                }
                break;

            default:

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
