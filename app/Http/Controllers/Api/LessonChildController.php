<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonVideo;
use App\Models\Coureschapters;
use App\Models\Video;
use App\Models\VideoLog;
use App\Models\AppLog;
use Illuminate\Http\Request;
use App\Tools\MTCloud;
use Validator;
use Illuminate\Support\Facades\Redis;

class LessonChildController extends Controller {



		/**
	     * @param  小节列表
	     * @param  pagesize   page
	     * @param  author  马哥（原孙晓丽）
	     * @param  ctime   2021/03/02
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
	        $video_log = new VideoLog();


	        // 这里吧查询到的数据进行 一次 缓存
	        // 这里 把循环查找 改成  redis 缓存的模式 这里的是
	        $chapters = RedisTryLockGetOrSet("lessonChild:course_info:".$course_id,function () use($course_id){

	            $chapters =  Coureschapters::select('id', 'name', 'parent_id as pid')
	                ->where(['is_del'=> 0,'parent_id' => 0, 'course_id' => $course_id])
	                ->orderBy('sort', 'asc')->get()->toArray();

	            foreach ($chapters as $key => $value) {
	                //查询小节
	                $chapters[$key]['childs'] = Coureschapters::join("ld_course_video_resource","ld_course_chapters.resource_id","=","ld_course_video_resource.id")
	                    ->select('ld_course_chapters.id','ld_course_chapters.name','ld_course_chapters.resource_id','ld_course_video_resource.course_id',
	                        'ld_course_video_resource.mt_video_id','ld_course_video_resource.mt_duration','ld_course_video_resource.cc_video_id')
	                    ->where(['ld_course_chapters.is_del'=> 0, 'ld_course_chapters.parent_id' => $value['id'], 'ld_course_chapters.course_id' => $course_id])
	                    ->orderBy('sort', 'asc')->get()->toArray();
	            }

	            // 获取 所有章节的 课程id 的信息
	            $cc_video_id_list = [];
	            foreach ($chapters as $k => &$v) {
	                foreach ($v[ 'childs' ] as $k1 => &$vv) {
	                    $cc_video_id_list[] = $vv[ 'cc_video_id' ];
	                }
	            }
	            // 将 本次课程的 所有的章节的的 video的 course_id (也就是cc的 直播间的id) 单独 整理出来
	            $chapters['cc_video_list'] = $cc_video_id_list;
	            return $chapters;

	        },300,60*60*24);

	        $all_user_course_rate_list = [];
	        if(!empty($chapters['cc_video_list']) and !empty($uid)){
	            $cc_video_list = $chapters['cc_video_list'];
	            $all_user_course_rate_list = $video_log ->CalculateCourseRateByVideoIdList($uid,$cc_video_list);
	            unset($chapters['cc_video_list']);
	        }

	        if(isset($chapters['cc_video_list'])){
	            unset($chapters['cc_video_list']);
	        }


	        foreach($chapters as $k => &$v){
	                foreach($v['childs'] as $k1 => &$vv){

	                    $vv['use_duration'] = "开始学习";
	                    $cc_video_id = $vv['cc_video_id']; //
	                    $out_play_position = 0;
	                    if(!empty($cc_video_id)){

	                        //首先判断 全部的数据中获取 从缓存中获取到数据
	                        if (!empty($all_user_course_rate_list) and isset($all_user_course_rate_list[$cc_video_id])){
	                            // 从全部的数据中获取到数据
	                            $cc_video_all_rate = $all_user_course_rate_list[$cc_video_id];
	                            $rate = $cc_video_all_rate['rate'];
	                            $out_play_position = $cc_video_all_rate['play_position'];
	                        }else{

	                            if(!empty($uid)){
	                                // 这个房改 改成 一次查询多次返回的方式 使用从ongoing从返回的 数据
	                                $rate = $video_log->CalculateCourseRateByVideoId($uid,$cc_video_id,$out_play_position);
	                            }else{
	                                $rate = 0;
	                            }
	                        }

	                        // mt_duration  老版本的兼容字段
	                        if ($rate == 0){
	                            $vv['learn_rate_format']  = '未开始';
	                            $vv['learn_rate']  = '0';
	                            $vv['mt_duration'] = "开始学习";
	                        }else if($rate < 100) {
	                            $vv['learn_rate']  = "".$rate;
	                            $vv['learn_rate_format']  = $rate.'%';
	                            $seconds = $out_play_position;
	                            $hours = intval($seconds/3600);
	                            $vv['mt_duration'] = $hours.":".gmdate('i:s', $seconds);

	                        }else{
	                            $vv['learn_rate']  = '100';
	                            $vv['learn_rate_format']  = '已完成';
	                            $seconds = $out_play_position;
	                            $hours = intval($seconds/3600);
	                            $vv['mt_duration'] = $hours.":".gmdate('i:s', $seconds);
	                        }
	                    }else{
	                        $vv['learn_rate']  = '0';
	                        $vv['learn_rate_format']  = '';
	                        $vv['mt_duration'] = "0";
	                    }
	                }

	            }
            //添加日志操作
            AppLog::insertAppLog([
               'school_id'      =>  !isset(self::$accept_data['user_info']['school_id'])?0:self::$accept_data['user_info']['school_id'],
               'admin_id'       =>  !isset(self::$accept_data['user_info']['user_id'])?0:self::$accept_data['user_info']['user_id'],
                'module_name'    =>  'Lesson' ,
                'route_url'      =>  'api/lessonChild' ,
                'operate_method' =>  'select' ,
                'content'        =>  '小节列表'.json_encode(['data'=>$chapters]) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
	        return $this->response($chapters);
	    }

    // /**
    //  * @param  小节列表
    //  * @param  pagesize   page
    //  * @param  author  孙晓丽
    //  * @param  ctime   2020/5/26
    //  * @return  array
    //  */
    // public function index(Request $request){
    //     $validator = Validator::make($request->all(), [
    //         'lesson_id' => 'required',
    //     ]);
    //     if ($validator->fails()) {
    //         return $this->response($validator->errors()->first(), 202);
    //     }
    //     $course_id = $request->input('lesson_id');
    //     if(isset(self::$accept_data['user_token']) && !empty(self::$accept_data['user_token'])){
    //         //判断token值是否合法
    //         //获取请求的平台端
    //         $platform = verifyPlat() ? verifyPlat() : 'pc';

    //         $key  = "user:regtoken:".$platform.":".self::$accept_data['user_token'];
    //         $redis_token = Redis::hLen($key);
    //             if($redis_token && $redis_token > 0) {
    //                 //通过token获取用户信息
    //                 $json_info = Redis::hGetAll($key);
    //                 $uid = $json_info['user_id'];
    //             } else {
    //                 return $this->response('请登录账号', 401);
    //             }

    //     }
    //     $video_log = new VideoLog();
    //     //查询章
    //     $chapters =  Coureschapters::select('id', 'name', 'parent_id as pid')
    //             ->where(['is_del'=> 0,'parent_id' => 0, 'course_id' => $course_id])
    //             ->orderBy('sort', 'asc')->get()->toArray();

    //     foreach ($chapters as $key => $value) {
    //         //查询小节
    //         $chapters[$key]['childs'] = Coureschapters::join("ld_course_video_resource","ld_course_chapters.resource_id","=","ld_course_video_resource.id")
    //             ->select('ld_course_chapters.id','ld_course_chapters.name','ld_course_chapters.resource_id','ld_course_video_resource.course_id',
    //                 'ld_course_video_resource.mt_video_id','ld_course_video_resource.mt_duration','ld_course_video_resource.cc_video_id')
    //             ->where(['ld_course_chapters.is_del'=> 0, 'ld_course_chapters.parent_id' => $value['id'], 'ld_course_chapters.course_id' => $course_id])->orderBy('sort', 'asc')->get()->toArray();
    //     }

    //     //进行缓存

    //     // foreach ($chapters as $k => &$v) {
    //     //     //获取用户使用课程时长
    //     //     foreach($v['childs'] as $kk => &$vv){
    //     //         if(isset(self::$accept_data['user_token']) && !empty(self::$accept_data['user_token'])){
    //     //             $course_id = $vv['course_id'];
    //     //             //获取缓存  判断是否存在
    //     //             if(Redis::get('VisitorList')){
    //     //                 //存在
    //     //                 $data  = Redis::get('VisitorList');
    //     //             }else{
    //     //                 //不存在
    //     //                 $MTCloud = new MTCloud();
    //     //                 $VisitorList =  $MTCloud->coursePlaybackVisitorList($course_id,1,50);
    //     //                 Redis::set('VisitorList', json_encode($VisitorList));
    //     //                 Redis::expire('VisitorList',600);
    //     //                 $data  = Redis::get('VisitorList');
    //     //             }
    //     //             // $MTCloud = new MTCloud();
    //     //             // $res  =  $MTCloud->coursePlaybackVisitorList($course_id,1,50);
    //     //             $res = json_decode($data,1);
    //     //             if(!empty($res['data'])){
    //     //                 $vv['use_duration']  = $res['data'];
    //     //             }else{
    //     //                 $vv['use_duration']  = array();
    //     //             }
    //     //         }else{
    //     //             $vv['use_duration']  = array();
    //     //         }
    //     //     }
    //     // }

    //     // foreach($chapters as $k => &$v){
    //     //     foreach($v['childs'] as $kk => &$vv){
    //     //         if(count($vv['use_duration']) > 0){
    //     //             foreach($vv['use_duration'] as $kkk => $vvv){
    //     //                 if($vvv['uid'] == $uid){
    //     //                     $vv['use_duration'] = $vvv['duration'];
    //     //                 }else{
    //     //                     if(is_array($vv['use_duration'])){
    //     //                         $vv['use_duration'] = 0;
    //     //                     }
    //     //                 }
    //     //             }
    //     //         }else{
    //     //             $vv['use_duration'] = 0;
    //     //         }
    //     //     }
    //     // }
    //     foreach($chapters as $k => &$v){
    //             foreach($v['childs'] as $k1 => &$vv){
    //                 $vv['use_duration'] = "开始学习";
    //                 $cc_video_id = $vv['cc_video_id']; //
    //                 $out_duration = 0;
    //                 if(!empty($cc_video_id)){

    //                     if(!empty($uid)){
    //                         $rate = $video_log->CalculateCourseRateByVideoId($uid,$cc_video_id,$out_duration);
    //                     }else{
    //                         $rate = 0;
    //                     }

    //                     // mt_duration  老版本的兼容字段
    //                     if ($rate == 0){
    //                         $vv['learn_rate_format']  = '未开始';
    //                         $vv['learn_rate']  = '0';
    //                         $vv['mt_duration'] = "开始学习";
    //                     }else if($rate < 100) {
    //                         $vv['learn_rate']  = "".$rate;
    //                         $vv['learn_rate_format']  = $rate.'%';
    //                         $seconds = $out_duration;
    //                         $hours = intval($seconds/3600);
    //                         $vv['mt_duration'] = $hours.":".gmdate('i:s', $seconds);

    //                     }else{
    //                         $vv['learn_rate']  = '100';
    //                         $vv['learn_rate_format']  = '已完成';
    //                         $seconds = $out_duration;
    //                         $hours = intval($seconds/3600);
    //                         $vv['mt_duration'] = $hours.":".gmdate('i:s', $seconds);
    //                     }
    //                 }else{
    //                     $vv['learn_rate']  = '0';
    //                     $vv['learn_rate_format']  = '';
    //                     $vv['mt_duration'] = "0";
    //                 }
    //             }

    //         }

    //     return $this->response($chapters);
    // }


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
        //添加日志操作
        AppLog::insertAppLog([
           'school_id'      =>  !isset(self::$accept_data['user_info']['school_id'])?0:self::$accept_data['user_info']['school_id'],
           'admin_id'       =>  !isset(self::$accept_data['user_info']['user_id'])?0:self::$accept_data['user_info']['user_id'],
            'module_name'    =>  'Lesson' ,
            'route_url'      =>  'api/lessonChildShow' ,
            'operate_method' =>  'select' ,
            'content'        =>  '小节详情'.json_encode(['data'=>$lesson]) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return $this->response($lesson);
    }
}
