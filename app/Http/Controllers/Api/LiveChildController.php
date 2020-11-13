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
            'course_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 201);
        }
        $course_id = $request->input('course_id');
        if($course_id == 0){
            return $this->response('该课程未发布！', 202);
        }
        $student_id = self::$accept_data['user_info']['user_id'];
        if(empty(self::$accept_data['user_info']['nickname'])){
            $nickname = self::$accept_data['user_info']['real_name'];
        }else{
            $nickname = self::$accept_data['user_info']['nickname'];
        }
        //@todo 处理CC的返回数据
        //优先查找直播
        $liveChild = CourseLiveClassChild::where('course_id', $course_id)->first();
        if(! empty($liveChild)){
            //欢拓
            if ($liveChild->bid > 0) {

                $MTCloud = new MTCloud();
                if ($liveChild->status == 2){
                    $res = $MTCloud->courseAccess($course_id, $student_id, $nickname, 'user');
                    $res['data']['is_live'] = 1;
                } elseif ($liveChild->status == 3 && $liveChild->playback == 1){
                    $res = $MTCloud->courseAccessPlayback($course_id, $student_id, $nickname, 'user');
                    $res['data']['is_live'] = 0;
                } else {
                    return $this->response('不是进行中的直播', 202);
                }
                $res['data']['service'] = 'MT';

            } else {

                //CC
                $CCCloud = new CCCloud();
                if($liveChild->status == 2){
                    //$res = $CCCloud->get_room_live_code($course_id);
                    $res = $CCCloud-> $res = $CCCloud->get_room_live_code($course_id, '', $nickname, $liveChild ->user_key);
                    $res['data']['is_live'] = 1;
                }elseif($liveChild->status == 3 && $liveChild->playback == 1){
                    $res = $CCCloud ->get_room_live_recode_code($course_id);
                    $res['data']['is_live'] = 0;
                }else{
                    return $this->response('不是进行中的直播', 202);
                }

                $res['data']['service'] = 'CC';

            }

        } else {

            //查找录播
            $video = Video::where('course_id', $course_id)->first();
            if (! empty($video)) {

                switch ($video->service) {
                    case 'MT':

                        $MTCloud = new MTCloud();
                        $res = $MTCloud->courseAccessPlayback($course_id, $student_id, $nickname, 'user');
                        $res['data']['is_live'] = 0;

                        break;
                    case 'CC':

                        $CCCloud = new CCCloud();
                        $res = $CCCloud ->get_room_live_recode_code($course_id);
                        $res['data']['is_live'] = 0;

                        break;
                }
                $res['data']['service'] = $video->service;

            } else {
                //直播和录播都不存在 返回
                return $this->response('course_id不存在', 202);
            }
        }

        if(!array_key_exists('code', $res) && !$res['code'] == 0){
            Log::error('进入直播间失败:'.json_encode($res));
            return $this->response('进入直播间失败', 500);
        }
        return $this->response($res['data']);
    }
}
