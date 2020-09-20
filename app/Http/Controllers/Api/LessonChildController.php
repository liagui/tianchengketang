<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonVideo;
use App\Models\Coureschapters;
use App\Models\Video;
use Illuminate\Http\Request;
use App\Tools\MTCloud;
use Validator;
use Illuminate\Support\Facades\Redis;

class LessonChildController extends Controller {

    /**
     * @param  小节列表
     * @param  pagesize   page
     * @param  author  孙晓丽
     * @param  ctime   2020/5/26
     * @return  array
     */
    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $course_id = $request->input('lesson_id');
        if(isset(self::$accept_data['user_token']) && !empty(self::$accept_data['user_token'])){
            //判断token值是否合法
            //获取请求的平台端
            $platform = verifyPlat() ? verifyPlat() : 'pc';

            $key  = "user:regtoken:".$platform.":".self::$accept_data['user_token'];
            $redis_token = Redis::hLen($key);
                if($redis_token && $redis_token > 0) {
                    //通过token获取用户信息
                    $json_info = Redis::hGetAll($key);
                    $uid = $json_info['user_id'];
                } else {
                    return $this->response('请登录账号', 401);
                }

        }
        //查询章
        $chapters =  Coureschapters::select('id', 'name', 'parent_id as pid')
                ->where(['is_del'=> 0,'parent_id' => 0, 'course_id' => $course_id])
                ->orderBy('create_at', 'asc')->get()->toArray();

        foreach ($chapters as $key => $value) {
            //查询小节
            $chapters[$key]['childs'] = Coureschapters::join("ld_course_video_resource","ld_course_chapters.resource_id","=","ld_course_video_resource.id")
                ->select('ld_course_chapters.id','ld_course_chapters.name','ld_course_chapters.resource_id','ld_course_video_resource.course_id','ld_course_video_resource.mt_video_id','ld_course_video_resource.mt_duration')
                ->where(['ld_course_chapters.is_del'=> 0, 'ld_course_chapters.parent_id' => $value['id'], 'ld_course_chapters.course_id' => $course_id])->get()->toArray();
        }

        //进行缓存

        // foreach ($chapters as $k => &$v) {
        //     //获取用户使用课程时长
        //     foreach($v['childs'] as $kk => &$vv){
        //         if(isset(self::$accept_data['user_token']) && !empty(self::$accept_data['user_token'])){
        //             $course_id = $vv['course_id'];
        //             //获取缓存  判断是否存在
        //             if(Redis::get('VisitorList')){
        //                 //存在
        //                 $data  = Redis::get('VisitorList');
        //             }else{
        //                 //不存在
        //                 $MTCloud = new MTCloud();
        //                 $VisitorList =  $MTCloud->coursePlaybackVisitorList($course_id,1,50);
        //                 Redis::set('VisitorList', json_encode($VisitorList));
        //                 Redis::expire('VisitorList',600);
        //                 $data  = Redis::get('VisitorList');
        //             }
        //             // $MTCloud = new MTCloud();
        //             // $res  =  $MTCloud->coursePlaybackVisitorList($course_id,1,50);
        //             $res = json_decode($data,1);
        //             if(!empty($res['data'])){
        //                 $vv['use_duration']  = $res['data'];
        //             }else{
        //                 $vv['use_duration']  = array();
        //             }
        //         }else{
        //             $vv['use_duration']  = array();
        //         }
        //     }
        // }

        // foreach($chapters as $k => &$v){
        //     foreach($v['childs'] as $kk => &$vv){
        //         if(count($vv['use_duration']) > 0){
        //             foreach($vv['use_duration'] as $kkk => $vvv){
        //                 if($vvv['uid'] == $uid){
        //                     $vv['use_duration'] = $vvv['duration'];
        //                 }else{
        //                     if(is_array($vv['use_duration'])){
        //                         $vv['use_duration'] = 0;
        //                     }
        //                 }
        //             }
        //         }else{
        //             $vv['use_duration'] = 0;
        //         }
        //     }
        // }
        foreach($chapters as $k => &$v){
                foreach($v['childs'] as $k1 => &$vv){
                    //if($vv['use_duration'] == 0){
                        $vv['use_duration'] = "开始学习";
                    // }else{
                    //     $vv['use_duration'] =  "已学习".  sprintf("%01.2f", $vv['use_duration']/$vv['mt_duration']*100).'%';;
                    // }
                    $seconds = $vv['mt_duration'];
                    $hours = intval($seconds/3600);
                    $vv['mt_duration'] = $hours.":".gmdate('i:s', $seconds);
                }

            }

        return $this->response($chapters);
    }


    /**
     * @param  小节详情
     * @param  课程id
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1
     * return  array
     */
    public function show(Request $request) {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson = LessonChild::with(['lives' => function ($query) {
                $query->with('childs');
            }])->find($request->input('id'));
        if(empty($lesson)){
            return $this->response('课程小节不存在', 404);
        }
        return $this->response($lesson);
    }
}
