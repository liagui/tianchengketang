<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Coures;
use App\Models\CouresSubject;
use App\Models\CourseLiveClassChild;
use App\Models\CourseSchool;
use App\Models\OpenLivesChilds;
use App\Models\Order;
use App\Models\Pay_order_external;
use App\Models\Student;
use App\Models\User;
use App\Models\Video;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;


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
                DB::beginTransaction();
                try{
                    Pay_order_external::where(['id'=>$orders['id']])->update(['pay_status'=>1,'pay_time'=>date('Y-m-d H:i:s')]);
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
    //微信回调
    public function wxnotify(){

    }
//汇付
public function hfnotify(){
    file_put_contents('hfnotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($_REQUEST,true),FILE_APPEND);
}

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
        Log::info('CC 视频上传 回调参数 :'.json_encode($data));

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
            //$video_info = $ret[ 'info' ];

            if (isset($ret[ 'video_info' ])) {

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
                        $CCCloud -> move_video_category($videoid,$category_id);
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
                            $CCCloud -> move_video_category($videoid,$category_id);
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

                                $CCCloud -> move_video_category($videoid,$child_category['id']);
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
        if(!isset($data[ 'type' ])){
            $data[ 'type' ] = 0;
        }
        $type = $data[ 'type' ];// 回调 类型
        switch ($type) {
            case "1":  //直播开始
                {
                    $userId = $data[ 'userId' ];    //CC账号ID
                    $roomId = $data[ 'roomId' ];    //直播间ID
                    $liveId = $data[ 'liveId' ];    //直播ID
                    $startTime = $data[ 'startTime' ]; //直播开始时间, 格式为"yyyy-MM-dd HH:mm:ss"
                    Log::info('CC直播开始:'.json_encode($data));
                    // 更新 课程的直报状 3 表示已经 结束
                    $live = CourseLiveClassChild::where(['course_id' => $roomId])->first();
                    if(empty($live)){
                        $live =  OpenLivesChilds::where(['course_id' => $roomId])->first(); //公开课
                    }
                    $live->status = 2;
                    $live->save();



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

                    Log::info('CC直播结束:'.json_encode($data));

                }
                break;

            default:

        }

        return response()->json( ["result" => "OK"]);

    }

    // endregion



}
