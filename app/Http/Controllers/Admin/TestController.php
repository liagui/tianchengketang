<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\LiveChild;
use Log;
use App\Models\Lesson;
use App\Models\LessonChild;
use App\Models\LessonVideo;
use App\Models\CourseSchool;
use App\Models\Bank;
use App\Models\CourseRefBank;
use App\Models\Coureschapters;
use App\Models\SubjectLesson;
use Illuminate\Support\Facades\DB;
use App\Tools\MTCloud;
use App\Exports\InvoicesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;




class TestController extends Controller
{


    public function insertcrosschool(){
        $arr = [
            'http://localhost',
            'https://localhost',
            'http://localhost:8080',
            'https://localhost:8080',
            'http://localhost:8081',
            'https://localhost:8081',
            'http://192.168.1.12:8080',
            'https://192.168.1.12:8080',
            'http://192.168.1.12:8081',
            'https://192.168.1.12:8081',
            'http://testwo.admin.longde999.cn',
            'https://testwo.admin.longde999.cn',
            'http://ketang.longde999.cn',
            'https://ketang.longde999.cn',
            'http://tiancheng.admin.longde999.cn',
            'https://tiancheng.admin.longde999.cn',
            'http://tiancheng.longde999.cn',
            'https://tiancheng.longde999.cn',
            'http://neibu.testwo.longde999.cn',
            'https://neibu.testwo.longde999.cn',
            'http://neibu.tiancheng.longde999.cn',
            'https://neibu.tiancheng.longde999.cn',
            'http://neibu1.testwo.longde999.cn',
            'https://neibu1.testwo.longde999.cn',

        ];
        foreach($arr as $k=>$v){
            $data[$k]['create_time'] = date('Y-m-d H:i:s');
            $data[$k]['school_dns'] = $v;
        }
        DB::table('ld_cross_school')->insert($data);
    }


    public function diff(){
        $bankids =[];
        $to_school_id = 8;
        $from_school_id = 1;






        $courseArr = CourseSchool::where(['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'is_del'=>0])->select('id','parent_id','child_id')->get()->toArray();
        foreach ($courseArr as $k => $vc) {
            $courseSubjectArr[$k]['parent_id'] = $vc['parent_id'];
            $courseSubjectArr[$k]['child_id'] = $vc['child_id'];
        }
        $courseSubjectArr = array_unique($courseSubjectArr,SORT_REGULAR);
        // print_r($courseSubjectArr);die;
        foreach($courseSubjectArr as $key=>&$vs){
            $bankIdArr = Bank::where(['parent_id'=>$vs['parent_id'],'child_id'=>$vs['child_id'],'is_del'=>0,'school_id'=>$from_school_id])->pluck('id')->toArray();
       
            if(!empty($bankIdArr)){
                foreach($bankIdArr as $k=>$vb){
                    array_push($bankids,$vb);
                }
            }
        }
        sort($bankids);
      print_r($bankids);
        if(!empty($bankids)){
            $bankids=array_unique($bankids);
            $natureQuestionBank = CourseRefBank::where(['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'is_del'=>0])->pluck('bank_id')->toArray();
            sort($natureQuestionBank);
            print_r($natureQuestionBank);
            $bankids = array_diff($natureQuestionBank,$bankids);
        }
         sort($bankids);
        print_r($bankids);
       
    }





    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function index(Request $request)
    {

        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if ('HTTP_' == substr($key, 0, 5)) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }
        echo '<pre>';
        print_r($headers);

die;
        $MTCloud = new MTCloud();
        $res = $MTCloud->courseGet("1250785");
        dd($res);
        $data['course_id'] = $res['data']['course_id'];
        $data = [];
        $d = DB::table("ld_course_live_childs")->insert($data);
        // $file = $_FILES['file'];
        // $is_correct_extensiton = self::detectUploadFileMIME($file);
        // $excel_extension       = substr($_FILES['file']['name'], strrpos($_FILES['file']['name'], '.')+1);   //获取excel后缀名
        // if($is_correct_extensiton <= 0 || !in_array($excel_extension , ['xlsx' , 'xls'])){
        //     return ['code' => 202 , 'msg' => '上传文件格式非法'];
        // }
        // //存放文件路径
        // $file_path= app()->basePath() . "/public/upload/excel/";
        // //判断上传的文件夹是否建立
        // if(!file_exists($file_path)){
        //     mkdir($file_path , 0777 , true);
        // }
        // //重置文件名
        // $filename = time() . rand(1,10000) . uniqid() . substr($file['name'], stripos($file['name'], '.'));
        // $path     = $file_path.$filename;
        // //判断文件是否是通过 HTTP POST 上传的
        // if(is_uploaded_file($_FILES['file']['tmp_name'])){
        //     //上传文件方法
        //     move_uploaded_file($_FILES['file']['tmp_name'], $path);
        // }
        // //获取excel表格中试题列表

        // $exam_list = self::doImportExcel(new \App\Imports\UsersImport , $path);
        // foreach ($exam_list['data'] as $k=>$v){

        //     //插入数据
        //     //
        //     //更新每一条数据

        // }
        // $data = $request->all();
        // $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 15;
        // $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        // $offset   = ($page - 1) * $pagesize;
        // $testt = DB::table("ttt")->offset($offset)->limit($pagesize)->get()->toArray();
        // foreach ($testt as $k=>$v){


        //     $res['update_at'] = date('Y-m-d H:i:s');
        //     $res['resource_id'] = $v->resource_id;
        //     $dd = Coureschapters::where(['id'=>$v->chapters_id])->update($res);
        //     Log::info('数据', ['id' => $v->chapters_id,'res'=>$dd,'resource_id'=>$v->resource_id]);
        // }
            // "data" => array:37 [
            //     "course_id" => "1058554" 课程id
            //     "partner_id" => "12572"合作方id

            //     "course_name" => "2020年一级消防工程师--3合1课程--消防基础知识"课程名

            //     "bid" => "320593"欢拓系统的主播id

            //     "start_time" => "1592910000"课程开始时间，时间戳，精确到秒，下同

            //     "end_time" => "1592919000"课程结束时间

            //     "add_time" => "1592790981"课程创建时间

            //     "status" => "0"状态： 0 正常，-1 已删除

            //     "live_stime" => "1592909945"直播开始时间

            //     "live_etime" => "1592920975"直播结束时间

            //     "duration" => "11030" 时长(秒)

            //     "chatTotal" => "1118" 聊天总数

            //     "zhubo_key" => "5050" 主播登录秘钥

            //     "admin_key" => "1210" 助教登录秘钥

            //     "user_key" => "5306"  学生登录秘钥

            //     "questionTotal" => "14"  问题总数

            //     "voteTotal" => "0" 投票总数

            //     "flowerTotal" => "477"  鲜花总数

            //     "lotteryTotal" => "0"  抽奖总数

            //     "livePv" => "206" 直播观看次数

            //     "liveUv" => "168" 回放观看次数

            //     "liveUvPeak" => "124"直播观看人数

            //     "pbPv" => "1414" 回放观看人数

            //     "pbUv" => "335" 回放观看人数


            //     "clipid" => "0"
            //     "departmentID" => "0"
            //     "sid" => "11"
            //     "updateTime" => "1594904881"
            //     "scenes" => "1"
            //     "zhubo" => array:10 [
            //       "bid" => "320593"  欢拓系统的主播id

            //       "partner_id" => "12572"  合作方id

            //       "thirdAccount" => "35"  发起直播课程的合作方主播唯一账号或ID

            //       "nickname" => "赵老师"  主播昵称

            //       "intro" => ""
            //       "p_150" => "https://static-1.talk-fun.com/open/cms_v2/css/common/portraits/spadmin_3.png"
            //       "p_40" => "https://static-1.talk-fun.com/open/maituo/static/css/img/_40.png?v=bdb80"
            //       "portraitUpdate" => "0"
            //       "departmentID" => "0"
            //       "power" => "1"
            //     ]
            //     "onlineTotal" => 0
            //     "playbackUrl" => "http://open.talk-fun.com/play/PD46KCQnJ2gsaiIu.html?st=6fE595C9hkT1gVT6SfX6Fg&e=1594992427&from=api"  回放地址

            //     "filesize" => 0
            //     "playback" => 1  playback 0为未生成，1为已生成
            //     "playbackOutUrl" => "http://open.talk-fun.com/playout/PD46KCQnJ2gsaiIu.html?st=6fE595C9hkT1gVT6SfX6Fg&e=1594992427&from=api"
            //     "robotTotal" => 1850   机器人数量

            //     "liveStatus" => 3  直播状态：1 未开始；2 正在直播；3 已结束
            //   ]
            //   "cache" => true
            //   "code" => 0
            // if($res['code']  == 0){
            //     $test['mt_video_id'] = $res['data']['videoId'];
            //     $test['mt_video_name'] = $res['data']['title'];
            //     $test['mt_url'] = $res['data']['videoUrl'];
            //     $test['mt_duration'] = $res['data']['duration'];
            //     $test['resource_size'] = $res['data']['filesize'];
            //     $d = DB::table("test")->insert($test);
            //     Log::info('数据', ['res' => $d,'videoId'=>$v->videoId]);
            // }else{
            //     Log::info('数据', ['res' => $res['code'],'videoId'=>$v->videoId]);
            // }
        }
        //
        //写入数据
        // foreach($res as $k => $v){
        //     dd($v->videoId);
        //     $MTCloud = new MTCloud();
        //     $res = $MTCloud->videoGet($v->videoId);
        //     dd($res);
        // }


        // dd($d);
        //获取数据

        //获取excel表数据

        //获取录播数据
        //通过视频id获取视频数据
        //添加到录播资源表中




//        $MTCloud = new MTCloud();
//        $res = $MTCloud->courseGet(1048458);
//        if(!array_key_exists('code', $res) && !$res['code'] == 0){
//            Log::error('进入直播间失败:'.json_encode($res));
//            return $this->response('进入直播间失败', 500);
//        }
//        return $this->response($res['data']);
        // $file = isset($_FILES['file']) && !empty($_FILES['file']) ? $_FILES['file'] : '';

        // //存放文件路径
        // $file_path= app()->basePath() . "/public/upload/excel/";
        // //判断上传的文件夹是否建立
        // if(!file_exists($file_path)){
        //     mkdir($file_path , 0777 , true);
        // }

        // //重置文件名
        // $filename = time() . rand(1,10000) . uniqid() . substr($file['name'], stripos($file['name'], '.'));
        // $path     = $file_path.$filename;

        // //判断文件是否是通过 HTTP POST 上传的
        // if(is_uploaded_file($_FILES['file']['tmp_name'])){
        //     //上传文件方法
        //     move_uploaded_file($_FILES['file']['tmp_name'], $path);
        // }
        // $exam_array = Excel::toArray(new \App\Imports\VideoImport , $path);
        // dd(count($exam_array));
        //$exam_list = self::doImportExcel(new \App\Imports\UsersImport , $path);
       //导入资源数据
        /*foreach($exam_list['data'] as $key=>$value){
            $video = Video::where(['course_id' => trim($value[4]), 'name' => trim($value[3])])->get();
            if(empty($video->toArray())){
                Video::create([
                    'admin_id' => 1,
                    'name' => trim($value[3]),
                    'category' => 1,
                    'url' => 'test.mp4',
                    'course_id' => trim($value[4])
                ]);
            }
        }
        return $this->response('success');
        */
        //资源学科

        /*foreach($exam_list['data'] as $key=>$value){
            $lesson = Lesson::where('title', $value[0])->first();
            if(!empty($lesson)){
                $subject = SubjectLesson::where('lesson_id', $lesson['id'])->get();
                $video = Video::where('course_id' , $value[4])->get();
                dd($video->toArray());
                // $child1 = LessonChild::where(['lesson_id' => $lesson['id'], 'name' => $value[1], 'pid' => 0])->first();
                // if(!empty($child1)){
                //     $child2 = LessonChild::where(['lesson_id' => $lesson['id'], 'pid' => $child1['id'], 'name' => $value[2]])->first();
                //     if (!empty($child2)) {
                //         $video = Video::where('name' , $value[3])->first();
                //         if(!empty($video)){
                //             LessonVideo::create([
                //                 'video_id' => $video['id'],
                //                 'child_id' => $child2['id'],
                //             ]);
                //         }
                //     }
                // }
            }
        }
        return $this->response('success');*/

        //课程关联资源
        /*foreach ($exam_list['data'] as $key=>$value) {
            $lesson = Lesson::where('title', trim($value[0]))->first();
            if(!empty($lesson)){
                //dd(1);
                $child1 = LessonChild::where(['lesson_id' => $lesson['id'], 'name' => trim($value[1]), 'pid' => 0])->first();
                if(!empty($child1)){
                    $child2 = LessonChild::where(['lesson_id' => $lesson['id'], 'pid' => $child1['id'], 'name' => trim($value[2])])->first();
                    if (!empty($child2)) {
                        $video = Video::where('name' , trim($value[3]))->first();
                        if(!empty($video)){
                            LessonVideo::create([
                                'video_id' => $video['id'],
                                'child_id' => $child2['id'],
                            ]);
                        }
                    }
                }
            }
        }*/
        //资源关联学科
        // foreach ($exam_list['data'] as $key=>$value) {
        //     $lesson = Lesson::where('title', trim($value[0]))->first();
        //     if(!empty($lesson)){

        //         $subject = SubjectLesson::where('lesson_id', $lesson['id'])->get();
        //         if (!empty($subject)) {
        //                 $subject_id = $subject->pluck('subject_id');
        //                 $video = Video::where('name' , trim($value[3]))->first();
        //                 if(!empty($video)){
        //                     //dd($subject_id);
        //                     $video->subjects()->attach($subject_id);

        //                 }
        //         }
        //     }
        // }
        // return $this->response('success');



        public function test(){
            $school_id = 17;
            $course_id = 12;

        }

    }
