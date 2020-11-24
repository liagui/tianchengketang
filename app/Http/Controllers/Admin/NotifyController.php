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
     *  这里 所有 的 操作 目前 都压在 redis 上
     *
     *
     * @return JsonResponse
     */
    public  function  CCUserCheckUrl(){

        $data = self::$accept_data;
        Log::info('CC CCUserCheckUrl 回调参数 :'.print_r($data,true));
        // todo cc的回调最好重新设计一下 这里的验证信息不是很好

        $CCCloud = new CCCloud();

        // 处理回放的情况的情况
        // 返回的data 类似 {"liveId":"524DB7D0E3E9DDD3","recordId":"02CAD72B9B7B85BA","roomId":"61DD535FDE8EBF829C33DC5901307461","startTime":"2020-11-17 09:22:10","type":"101","userId":"788A85F7657343C2","time":"1605577455","hash":"94C3C464062F8EB27877FD36C4805B47"}



         // 根据学校 group id 和  viewercustominfo 卡并发数
        if(isset($data['groupid']) and isset($data['viewercustominfo']) ){

            $school_id = $data['groupid'];
            $viewercustominfo = json_decode( $data['viewercustominfo'],true);

            if (!is_numeric($school_id)){
                // ios 有可能的 groupid 是 "(null)" 这里把他纠正过来
                $school_id = $viewercustominfo['school_id'];
            }



            $user_id = $viewercustominfo['id'];  //这个是用户的id
            $room_id = $data['roomid'];    //当前的房间号码

            if($school_id == 1){

                $ret = $CCCloud->cc_user_login_function(true, $viewercustominfo);
                Log::info('school id $school_id 不计入并发数');
                Log::info('CC CCUserCheckUrl ret:'.json_encode($ret));
                return  response()->json($ret);
            }

            // 网校 当前 的 并发数目
            $key = $school_id."_"."num_".date("Y_m");

            $num = Redis::get($key);
            Log::info('CC CCUserCheckUrl 回调参数 redis: :'.$key .":::"."$num");
            if(empty($num)){
                // 无法从redis 中获取到 并发数的数
                Log::info('CC CCUserCheckUrl 回调参数 : 没有足够的并发数目');
                $ret = $CCCloud->cc_user_login_function(false, $viewercustominfo,"网校系统繁忙！");
                Log::info('CC CCUserCheckUrl ret:'.json_encode($ret));
                return  response()->json($ret);
            }

            //当前已经使用的并发数
            $key_now_num = $school_id."_"."num_now_".date("Y_m_d");
            $now_num = Redis::get($key_now_num);

            // 当前 用户和 直播间的 关系 有关系表示 已经进入直播间 有可能掉线了
            $key_user_room=$school_id."_".$room_id."_".$user_id;
            $user_room_already_in = Redis::get($key_user_room);

            Log::info('CC CCUserCheckUrl 回调参数 redis: :'.$key_now_num .":::"."$now_num");
            Log::info('CC CCUserCheckUrl 回调参数 redis: :'.$key_user_room .":::"."$user_room_already_in");

            //  如果用户 已经进入了那么 不扣除并发数 直接返回
            if (!empty($user_room_already_in)){
                // 返回登录ok
                Log::info('CC CCUserCheckUrl 回调参数 : 重复进入');
                $ret = $CCCloud->cc_user_login_function(true, $viewercustominfo);
                Log::info('CC CCUserCheckUrl ret:'.json_encode($ret));
                return  response()->json($ret);
            }


            // 判断已经使用的并发数是否存在
            if(empty($now_num)){
                // 第一个人进入的情况
                $now_num = 0;
            }

            // 如果并发数目 不够了
            if(intval($now_num) >= intval($num)   ){
                // 阻止对方进入、
                Log::info('CC CCUserCheckUrl 回调参数 : 并发数目不足');
                $ret = $CCCloud->cc_user_login_function(false, array(),"网校直播系统繁忙！！");
                Log::info('CC CCUserCheckUrl ret:'. json_encode($ret));
                return  response()->json($ret);
            }
             // 设定用户和直播间和学校的信息
            Redis::set($key_user_room,"1");
            //  增加并发数目
            Redis::incr($key_now_num);
            Log::info('CC CCUserCheckUrl 回调参数 : 进入吧！！！！！');
            $ret = $CCCloud->cc_user_login_function(true, $viewercustominfo);
            Log::info('CC CCUserCheckUrl ret:'.json_encode($ret));
            return  response()->json($ret);
        }else{

            // 判断一下直播会看
            if (isset($data['liveid']) and !empty($data['liveid'])){
                $viewercustominfo = array(
                    "nickname" => $data['viewername'],
                    "school_id" => 0,
                    "id" => 0,
                );

                $ret = $CCCloud->cc_user_login_function(true, $viewercustominfo);
                Log::info('CC CCUserCheckUrl 忽略本次验证 直播回看 无需验证 ');
                Log::info('CC CCUserCheckUrl ret:'.json_encode($ret));
                return  response()->json($ret);
            }

            Log::info('CC CCUserCheckUrl 忽略本次验证 ！没有 groupid 和 viewercustominfo ');
            $ret = $CCCloud->cc_user_login_function(false, array(),"验证信息不正确！");
            Log::info('CC CCUserCheckUrl ret:'.print_r($ret,true));
            return  response()->json($ret);
        }




//        //获取用户token值
//        $token = $data[ 'user_token' ];
//
//        //判断用户token是否为空
//        if (!$token || empty($token)) {
//            //return [ 'code' => 401, 'msg' => '请登录账号' ];
//            return $this->response($CCCloud->cc_user_login_function(false, array()));
//        }
//
//        //hash中token赋值
//        $token_key = "user:regtoken:" . $platform . ":" . $token;
//
//        //判断token值是否合法
//        $redis_token = Redis::hLen($token_key);
//        if( !empty($redis_token) && $redis_token > 0) {
//            //解析json获取用户详情信息
//            $json_info = Redis::hGetAll($token_key);
//
//            //判断是正常用户还是游客用户
//            if($json_info['user_type'] && $json_info['user_type'] == 1){
//
//                //根据手机号获取用户详情
//                $user_info = User::where('school_id' , $json_info['school_id'])->where("phone" , $json_info['phone'])->first();
//
//                if(!$user_info || empty($user_info)){
//                    return  $this->response($CCCloud->cc_user_login_function(false, array()));
//                }
//
//                //判断用户是否被禁用
//                if($user_info['is_forbid'] == 2){
//                    return  $this->response($CCCloud->cc_user_login_function(false, array()));
//                }
//
//                return $this->response($CCCloud->cc_user_login_function(true, $user_info));
//            } else if($json_info['user_type'] && $json_info['user_type'] == 2){
//                //通过device获取用户信息
//                $user_info = User::select("id as user_id" , "is_forbid")->where("device" , $json_info['device'])->first();
//                if(!$user_info || empty($user_info)){
//                    return $this->response( $CCCloud->cc_user_login_function(false, array()));
//                }
//
//                //判断用户是否被禁用
//                if($user_info['is_forbid'] == 2){
//                    return  $this->response($CCCloud->cc_user_login_function(false, array()));
//                }
//                return $this->response($CCCloud->cc_user_login_function(true, $user_info));
//            }
//        } else {
//            return  $this->response($CCCloud->cc_user_login_function(false, array()));
//        }

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

        if ($status == "FAIL") {
            Log::error("视频处理失败[videoid:$videoid]");
            // todo 这里 cc 发送视频处理失败 后续处理这个问题

            $ret = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><result>OK</result>";
            return $ret;

        }

        $duration = $data[ 'duration' ];//	片长(单位:秒)
        $image = $data[ 'image' ];//	视频截图地址


        // 设定 cc 上传的视频 成功
        $video = new Video();
        // 默认上传后 把状态 改成 带转码中
        $ret = $video->auditVideo($videoid,1);

        if ($ret[ 'code' ] == 200) {
            // 更新 视频的 分类 将视频移动到 学校/分类/分类 目录下面

            if (isset($ret[ 'video_info' ])) {

                $school_id = $ret[ 'video_info' ][ 'school_id' ];
                $parent_id = $ret[ 'video_info' ][ 'parent_id' ];
                $child_id = $ret[ 'video_info' ][ 'child_id' ];
                $resource_name = $ret[ 'video_info' ][ 'resource_name' ];

                Log::error('CC 点播转换直播间创建:开始创建！');

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

//                // 处理完 分类后 按照  点播 直播 回访的 方式 进行 处理
//                $cc_cloud  = new CCCloud();
//
//                //$room_name = "[点播转直报专用**勿删**][". $resource_name."]";
//                $room_name =  $resource_name;
//
//                Log::error('CC 点播转换直播间创建失败:创建直报间：'.$room_name);
//                $password_user = $cc_cloud ->random_password();
//                $room_info = $cc_cloud->cc_room_create_by_video_id($videoid, $room_name, $room_name, 1,2
//                , $password_user, $password_user,$password_user,array());
//
//                if(!array_key_exists('code', $room_info) && !$room_info["code"] === 0 ){
//                    Log::error('CC 点播转换直播间创建失败:'.json_encode($room_info));
//                    // 等待后续的创建 返回false
//                    return false;
//                }
//
//                // $room_info['data']['room_id']
//
//                $cc_info[ 'cc_room_id' ] = $room_info[ 'data' ][ 'room_id' ];
//                // $cc_info['live_id']:"";
//                // $cc_info['record_id']:"";
//                $cc_info[ 'cc_view_pass' ] = $password_user;
//
//                $ret = $video->VideoToCCLive($videoid, $cc_info);
//
//                if(!array_key_exists('code', $ret) && !$ret["code"] === 200 ){
//                    Log::error('CC 点播转换直播间数据库更新失败:'.json_encode($ret));
//                    // 等待后续的创建 返回false
//                    return false;
//                }
//
//                Log::error('CC 点播转换直播间数据库成功!!:'.json_encode($cc_info));

            }


        }


        $ret = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><result>OK</result>";
        return $ret;
    }


    // CC 直播回调函数 当直播开始、结束和 直播回放 录制开始、结束的时候 CC 平台会进行回调
    public function ccliveCallback()
    {
        $data = self::$accept_data;

        Log::info('CC  ccliveCallback 回调参数 :'.json_encode($data));

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
                    if(!empty($live)){
                        $live->status = 2;
                        $live->save();
                        Log::info('CC直播更新课程:公开课或者质保科');
                    }else{
                        // 更新上传文件 这里的房间号 是cc的根据 cc的房间号找到对应的资源id
                        $video = Video::where([ 'cc_room_id' => $roomId ])->first();
                        if (!empty($video)) {
                            $video->cc_live_id = $liveId;
                            $video->save();
                            Log::info('CC直播更新课程:上传资源');
                        }

                    }



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
                    if(!empty($live)) {
                        // 更新课程状态
                        $live->status = 3;
                        $live->save();
                        Log::info('CC直播跟新课程:公开课或者直播课');
                    }


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

                    $recordStatus = isset($data[ 'recordStatus' ])?$data[ 'recordStatus' ]:0; //回放状态，10：回放处理成功，20：回放处理失败，30：录制时间过长（回调类型type为103时，会返回该参数）


                    if($type == "103" and $recordStatus == "10"){
                        // 当前 type = 103 并且 recordStatus = 10 的时候一下的参数才开始生效
                        $endTime = $data[ 'endTime' ];    //录制结束时间, 格式为"yyyy-MM-dd HH:mm:ss"（回调类型type为102或103时，会返回该参数）
                        $sourcetype = $data[ 'sourcetype' ];    //回放来源，0：录制； 1：合并； 2：迁移； 3：上传； 4:裁剪（回调类型type为103时，会返回该参数）
                        $recordVideoId = $data[ 'recordVideoId' ];    //回放视频ID（回放状态recordStatus为10时，会返回该参数）
                        $recordVideoDuration = $data[ 'recordVideoDuration' ];    //回放视频时长，单位：秒（回放状态recordStatus为10时，会返回该参数）
                        $replayUrl = $data[ 'replayUrl' ];    //回放观看地址（回放状态recordStatus为10时，会返回该参数）

                        Log::info('CC直播回放录制完成:'.json_encode($data));

                        $live = CourseLiveClassChild::where(['course_id' => $roomId])->first();
                        if(empty($live)){
                            $live =  OpenLivesChilds::where(['course_id' => $roomId])->first();//公开课
                        }
                        if(!empty($live)){

                            $live->playback = 1;
                            $live->playbackUrl = $replayUrl;
                            $live->duration = $recordVideoDuration;
                            $live->save();

                        }else{
                            // 更新上传文件 这里的房间号 是cc的根据 cc的房间号找到对应的资源id
                            $video = Video::where([ 'cc_room_id' => $roomId ])->first();
                            if (!empty($video)) {
                                // 直接把 record_id 传递上去
                                $video->cc_live_id = $liveId;
                                $video->cc_record_id = $recordId;
                                $video->audit = 1;
                                $video->save();
                                Log::info('CC直播更新课程:上传资料');
                            }

                        }

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

                    Log::info('CC直播 离线回放:'.json_encode($data));

                }
                break;

            default:

        }

        return response()->json( ["result" => "OK"]);

    }

    // endregion



}
