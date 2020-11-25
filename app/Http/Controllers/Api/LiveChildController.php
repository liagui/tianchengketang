<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Http\Request;
use Validator;
use App\Tools\MTCloud;
use Log;
use App\Models\Lesson;
use App\Models\LessonLive;
use App\Models\LiveChild;
use App\Models\CourseLiveClassChild;
use App\Models\CourseLiveResource;
use App\Models\Video;
use Illuminate\Support\Facades\DB;

class LiveChildController extends Controller {



    //课程直播目录
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'lesson_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }

        $courseArr = CourseLiveResource::select('shift_id as shift_no_id')->where(['course_id'=>$request->input('lesson_id'),'is_del'=>0])->get()->toArray();
        //获取班号
        //获取班号下所有课次'
        $childs = [];
        if(!empty($courseArr) && count($courseArr) > 0){
            foreach ($courseArr as $key => $value) {
                //直播中
                $live = LiveChild::join("ld_course_live_childs","ld_course_class_number.id","=","ld_course_live_childs.class_id")
                ->join("ld_course_shift_no","ld_course_class_number.shift_no_id","=","ld_course_shift_no.id")
                ->select('ld_course_class_number.id', 'ld_course_class_number.name as course_name', 'ld_course_class_number.start_at as start_time', 'ld_course_class_number.end_at as end_time', 'ld_course_live_childs.course_id', 'ld_course_live_childs.status','ld_course_shift_no.name as class_name')->where([
                    'ld_course_live_childs.is_del' => 0,'ld_course_class_number.is_del'=>0,'ld_course_live_childs.is_forbid' => 0, 'ld_course_live_childs.status' => 2,'shift_no_id'=>$value['shift_no_id']
                ])->get();
                //预告未发布
                $advance1 = LiveChild::join("ld_course_shift_no","ld_course_class_number.shift_no_id","=","ld_course_shift_no.id")
                ->select('ld_course_class_number.id', 'ld_course_class_number.name as course_name', 'ld_course_class_number.start_at as start_time', 'ld_course_class_number.end_at as end_time','ld_course_shift_no.name as class_name')->where([
                    'ld_course_class_number.is_del' => 0,'ld_course_class_number.is_del'=>0,'ld_course_class_number.status' => 0,'shift_no_id'=>$value['shift_no_id']
                ])->get()->toArray();
                foreach($advance1 as $k => &$v){
                    $v['course_id'] = 0;
                }
                //预告已发布
                $advance2 = LiveChild::join("ld_course_live_childs","ld_course_class_number.id","=","ld_course_live_childs.class_id")
                ->join("ld_course_shift_no","ld_course_class_number.shift_no_id","=","ld_course_shift_no.id")
                ->select('ld_course_class_number.id', 'ld_course_class_number.name as course_name', 'ld_course_class_number.start_at as start_time', 'ld_course_class_number.end_at as end_time', 'ld_course_live_childs.course_id', 'ld_course_live_childs.status','ld_course_shift_no.name as class_name')
                ->where(['ld_course_live_childs.is_del' => 0,'ld_course_class_number.is_del'=>0,'ld_course_live_childs.is_forbid' => 0, 'ld_course_live_childs.status' => 1,'shift_no_id'=>$value['shift_no_id']
                ])->get()->toArray();
                if(!empty($advance2) && !empty($advance1)){
                    $advance = array_merge($advance1,$advance2);
                }else if(empty($advance2)){
                    $advance = $advance1;
                }else{
                    $advance = $advance2;
                }
                //回放
                $playback = LiveChild::join("ld_course_live_childs","ld_course_class_number.id","=","ld_course_live_childs.class_id")
                ->join("ld_course_shift_no","ld_course_class_number.shift_no_id","=","ld_course_shift_no.id")
                ->select('ld_course_class_number.id', 'ld_course_class_number.name as course_name', 'ld_course_class_number.start_at as start_time', 'ld_course_class_number.end_at as end_time', 'ld_course_live_childs.course_id', 'ld_course_live_childs.status','ld_course_shift_no.name as class_name')->where([
                    'ld_course_live_childs.is_del' => 0,'ld_course_class_number.is_del'=>0,'ld_course_live_childs.is_forbid' => 0, 'ld_course_live_childs.status' => 3,'shift_no_id'=>$value['shift_no_id']
                ])->get();

                if(!empty($live->toArray())){
                    array_push($childs, [
                        'title' => '正在播放',
                        'data'  => $live,
                    ]);
                }
                if(!empty($advance)){
                    array_push($childs, [
                            'title' => '播放预告',
                            'data'  => $advance,
                        ]);
                }
                if(!empty($playback->toArray())){
                    array_push($childs, [
                            'title' => '历史课程',
                            'data'  => $playback,
                        ]);
                }

            }

        }
        foreach($childs as $k => &$v){
            foreach($v['data'] as $kk =>&$vv){
                    $vv['start_time']  = date("Y-m-d H:i:s",$vv['start_time']);
                    $vv['end_time']  = date("Y-m-d H:i:s",$vv['end_time']);
            }
        }
        return $this->response($childs);
    }




    //进入直播课程
    public function courseAccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required'
        ]);


        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 201);
        }
        $course_id = $request->input('course_id');

        $resource_id = $request->input('resource_id');

        if( empty( $course_id ) and empty($resource_id)){
            return $this->response('该课程未发布！', 202);
        }
        $student_id = self::$accept_data['user_info']['user_id'];
        if(empty(self::$accept_data['user_info']['nickname'])){
            $nickname = self::$accept_data['user_info']['real_name'];
        }else{
            $nickname = self::$accept_data['user_info']['nickname'];
        }

        $school_id = self::$accept_data['user_info']['school_id'];
        $phone = self::$accept_data['user_info']['phone'];
        $platform = verifyPlat() ? verifyPlat() : 'pc';
        $user_token = $platform.":".$request['user_token'];


        //@todo 处理CC的返回数据
        //优先查找直播
        $liveChild = CourseLiveClassChild::where('course_id', $course_id)->first();
        if(! empty($liveChild)){
            //欢拓
            if ($liveChild->bid > 0) {

                $MTCloud = new MTCloud();
                if ($liveChild->status == 2){
                    $res_info = $MTCloud->courseAccess($course_id, $student_id, $nickname, 'user');

                    // 检查 api 的返回结果
                    if(!array_key_exists('code', $res_info) && !$res_info['code'] == 0){
                        Log::error('进入直播间失败:'.json_encode($res_info));
                        return $this->response('进入直播间失败', 500);
                    }

                    $res['data']['is_live'] = 1;
                    $res['data']['type'] = "live";
                    $res['data']['mt_live_info'] = $res_info['data'];
                } elseif ($liveChild->status == 3 && $liveChild->playback == 1){
                    $res_info = $MTCloud->courseAccessPlayback($course_id, $student_id, $nickname, 'user');

                    // 检查 api 的返回结果
                    if(!array_key_exists('code', $res_info) && !$res_info['code'] == 0){
                        Log::error('进入回放失败:'.json_encode($res_info));
                        return $this->response('进入回放失败', 500);
                    }

                    $res['data']['is_live'] = 0;
                    $res['data']['type'] = "recode";
                    $res['data']['mt_live_info'] = $res_info['data'];
                } else {
                    return $this->response('不是进行中的直播', 202);
                }
                $res['data']['service'] = 'MT';

            } else {

                //CC
                $CCCloud = new CCCloud();
                if($liveChild->status == 2){
                    $viewercustominfo= array(
                        "school_id"=>$school_id,
                        "id" => $student_id,
                        "nickname" => $nickname,
                        'phone' => $phone
                    );
                    //$res = $CCCloud->get_room_live_code($course_id);
                    // $res_info =   $CCCloud->get_room_live_code($course_id, $school_id, $nickname,$liveChild ->user_key,$viewercustominfo);
                    $res_info =   $CCCloud->get_room_live_code($course_id, $school_id, $nickname,$user_token,$viewercustominfo);

                    // 检查 api 的返回结果
                    if(!array_key_exists('code', $res_info) && !$res_info['code'] == 0){
                        Log::error('进入直播间失败:'.json_encode($res_info));
                        return $this->response('进入直播间失败', 500);
                    }

                    $res['data']['is_live'] = 1;
                    $res['data']['type'] = "live";
                    $res['data']['cc_live_info'] = $res_info['data']['cc_info'];
                }elseif($liveChild->status == 3 && $liveChild->playback == 1){

                    $viewercustominfo= array(
                        "school_id"=>$school_id,
                        "id" => $student_id,
                        "nickname" => $nickname,
                        'phone' => $phone
                    );
                    // 获取 直播回放的代码
                    $res_info = $CCCloud ->get_room_live_recode_code($course_id,$school_id,$nickname, $user_token,$viewercustominfo);

                    // 检查 api 的返回结果
                    if(!array_key_exists('code', $res_info) && !$res_info['code'] == 0){
                        Log::error('进入回放失败:'.json_encode($res_info));
                        return $this->response('进入回放失败', 500);
                    }

                    $res['data']['is_live'] = 0;
                    $res['data']['type'] = "recode";
                    $res['data']['cc_live_info'] = $res_info['data']['cc_info'];;
                }else{
                    return $this->response('不是进行中的直播', 202);
                }

                $res['data']['service'] = 'CC';

            }

        } else {

            //查找录播
            if($course_id !== 0){
                $video = Video::where('course_id', $course_id)->first();
            }else
            {
                $video = Video::where('resource_id', $resource_id)->first();
            }

            if (! empty($video)) {

                switch ($video->service) {
                    case 'MT':

                        $MTCloud = new MTCloud();
                        $res_info = $MTCloud->courseAccessPlayback($course_id, $student_id, $nickname, 'user');

                        // 检查 api 的返回结果
                        if(!array_key_exists('code', $res_info) && !$res_info['code'] == 0){
                            Log::error('进入直播间失败:'.json_encode($res_info));
                            return $this->response('进入直播间失败', 500);
                        }

                        $res['data']['is_live'] = 0;
                        $res['data']['type'] = "vod";
                        $res['data']['mt_live_info'] = $res_info['data'];

                        break;
                    case 'CC':
                        //todo 这里修改成cc 的点播地址
                        $CCCloud = new CCCloud();
                        //$res = $CCCloud ->get_room_live_recode_code($course_id);
                        $res_info = $CCCloud ->get_video_code($school_id ,$video->cc_video_id, $nickname);

                        // 检查 api 的返回结果
                        if(!array_key_exists('code', $res_info) && !$res_info['code'] == 0){
                            Log::error('进入直播间失败:'.json_encode($res_info));
                            return $this->response('进入直播间失败', 500);
                        }

                        $res['data']['is_live'] = 0;
                        $res['data']['type'] = "vod";
                        $res['data']['cc_vod_info'] = $res_info['data']['cc_info'];

                        break;
                }
                $res['data']['service'] = $video->service;

            } else {
                //直播和录播都不存在 返回
                return $this->response('course_id 或者 resource_id 不存在', 202);
            }
        }

        // 检查一下默认的数据是否存在

        if(!isset($res['data']['cc_vod_info'])){
            $res['data']['cc_vod_info'] = array(
                "userid" => "",
                "videoid" => "",
                "customid" => "",
            );
        }

        if(!isset($res['data']['cc_live_info'])){
            $res['data']['cc_live_info'] = array(
                "userid" => "",
                "roomid" => "",
                "liveid" => "",
                "recordid" => "",//这里只能返回空
                "autoLogin" => "true",
                "viewername" => "", //绑定用户名
                "viewertoken" => "", //绑定用户token
                "viewercustominfo" => "",   //重要填入school_id
                "viewercustomua" => "",   //重要填入school_id
                "groupid" =>  ""
            );
        }

        if(!isset($res['data']['mt_live_info'])){
            $res['data']['mt_live_info']=array(
                "playbackUrl"    => "",             // 回放地址
                "liveUrl"        => "",             // 直播地址
                "liveVideoUrl"   => "",        // 直播视频外链地址
                "access_token"   => "",        // 用户的access_token
                "playbackOutUrl" => "",      // 回放视频播放地址
                "miniprogramUrl" => ""     // 小程序web-view的直播或回放地址

            );
        }

        /** 这里 处理原来的欢托和cc 的兼容 */
        // 如果发现是cc的直播有 返回空数据
        // 如果发现有欢托的的直播信息 合并一下欢托的结果

        if(isset($res['data']['mt_live_info'])){
            $res['data'] = array_merge($res['data'],$res['data']['mt_live_info']);
        }


        /** 结束兼容性代码 */



        return $this->response($res['data']);
    }


}
