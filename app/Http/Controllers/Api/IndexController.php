<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\OpenCourse;
use App\Models\SchoolConfig;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Order;
use App\Models\Subject;
use App\Models\CourseSchool;
use App\Models\CourseRefOpen;
use App\Models\CourseRefSubject;
use App\Models\LessonTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;


class IndexController extends Controller {
    /*
     * @param  description   首页轮播图接口
     * @param author    dzj
     * @param ctime     2020-05-25
     * return string
     */
    public function getChartList() {
        //获取提交的参数
        try{
            $rotation_chart_list = [
                [
                    'chart_id'     =>   1 ,
                    'title'        =>   '轮播图1' ,
                    'jump_url'     =>   '' ,
                    'pic_image'    =>   "https://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-06-17/159238101090725ee9ce52b4dbc.jpg" ,
                    'type'         =>   1 ,
                    'lession_info' => [
                        'lession_id'  => 1 ,
                        'lession_name'=> '课程名称1'
                    ]
                ] ,
                [
                    'chart_id'     =>   2 ,
                    'title'        =>   '轮播图2' ,
                    'jump_url'     =>   '' ,
                    'pic_image'    =>   "https://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-06-17/159238104323565ee9ce73db673.jpg" ,
                    'type'         =>   1 ,
                    'lession_info' =>   [
                        'lession_id'  => 0 ,
                        'lession_name'=> ''
                    ]
                ] ,
                [
                    'chart_id'     =>   3 ,
                    'title'        =>   '轮播图3' ,
                    'jump_url'     =>   '' ,
                    'pic_image'    =>   "https://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-06-17/159238106166285ee9ce85ea7e0.jpg" ,
                    'type'         =>   1 ,
                    'lession_info' => [
                        'lession_id'  => 2 ,
                        'lession_name'=> '课程名称2'
                    ]
                ]
            ];
            return response()->json(['code' => 200 , 'msg' => '获取轮播图列表成功' , 'data' => $rotation_chart_list]);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   首页公开课接口
     * @param author    dzj
     * @param ctime     2020-05-25
     * return string
     */
    public function getOpenClassList(Request $request) {
        //获取提交的参数
        try{
            //判断公开课列表是否为空
            $open_class_count = OpenCourse::where('status' , 1)->where('is_del' , 0)->where('is_recommend', 1)->count();
            if($open_class_count && $open_class_count > 0){
                //登录状态
                //获取请求的平台端
                $platform = verifyPlat() ? verifyPlat() : 'pc';
                //获取用户token值
                $token = $request->input('user_token');
                //hash中token赋值
                $token_key   = "user:regtoken:".$platform.":".$token;
                //判断token值是否合法
                $redis_token = Redis::hLen($token_key);
                if($redis_token && $redis_token > 0) {
                    //解析json获取用户详情信息
                    $json_info = Redis::hGetAll($token_key);
                    //登录显示用户分校
                    //获取公开课列表
                    if($json_info['school_id'] == 1){
                        $open_class_list = OpenCourse::join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select('ld_course_open.id' , 'ld_course_open.cover' ,"ld_course_open_live_childs.course_id", 'ld_course_open.start_at' , 'ld_course_open.end_at')
                        ->where('ld_course_open.status' , 1)->where('ld_course_open.is_del' , 0)->where('ld_course_open.is_recommend', 1)->where('ld_course_open.school_id', 1)
                        ->orderBy('ld_course_open.start_at' , 'ASC')->offset(0)->limit(3)->get()->toArray();

                    }else{
                        //自增公开课
                        $open_class_list1 = OpenCourse::join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select('ld_course_open.id' , 'ld_course_open.cover' ,"ld_course_open_live_childs.course_id", 'ld_course_open.start_at' , 'ld_course_open.end_at')
                        ->where('ld_course_open.status' , 1)->where('ld_course_open.is_del' , 0)->where('ld_course_open.is_recommend', 1)->where('ld_course_open.school_id',$json_info['school_id'])
                        ->orderBy('ld_course_open.start_at' , 'ASC')->get()->toArray();
                        //授权公开课
                        $open_class_list2 = CourseRefOpen::join("ld_course_open","ld_course_ref_open.course_id","=","ld_course_open.id")
                        ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select('ld_course_open.id' , 'ld_course_open.cover' ,"ld_course_open_live_childs.course_id", 'ld_course_open.start_at' , 'ld_course_open.end_at')
                        ->where('ld_course_open.status' , 1)->where('ld_course_open.is_del' , 0)->where('ld_course_open.is_recommend', 1)->where('ld_course_ref_open.to_school_id',$json_info['school_id'])
                        ->orderBy('ld_course_open.start_at' , 'ASC')->get()->toArray();
                        $open_class_list = array_merge($open_class_list1,$open_class_list2);
                        $open_class_list = array_slice($open_class_list,0 ,3);

                    }
                }else{
                    //未登录显示总校
                    //获取公开课列表
                    $open_class_list = CourseRefOpen::join("ld_course_open","ld_course_ref_open.course_id","=","ld_course_open.id")
                        ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select('ld_course_open.id' , 'ld_course_open.cover' ,"ld_course_open_live_childs.course_id", 'ld_course_open.start_at' , 'ld_course_open.end_at')
                        ->where('ld_course_open.status' , 1)->where('ld_course_open.is_del' , 0)->where('ld_course_open.is_recommend', 1)->where('ld_course_ref_open.to_school_id',30)->orderBy('ld_course_open.start_at' , 'ASC')->offset(0)->limit(3)->get()->toArray();
                    // $open_class_list = OpenCourse::join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                    // ->select('ld_course_open.id' , 'ld_course_open.cover' ,"ld_course_open_live_childs.course_id", 'ld_course_open.start_at' , 'ld_course_open.end_at')
                    // ->where('ld_course_open.status' , 1)->where('ld_course_open.is_del' , 0)->where('ld_course_open.is_recommend', 1)->where('ld_course_open.school_id', 1)
                    // ->orderBy('ld_course_open.start_at' , 'ASC')->offset(0)->limit(3)->get()->toArray();
                }
                //新数组赋值
                $lession_array = [];
                //循环公开课列表

                foreach($open_class_list as $k=>$v){
                    //根据课程id获取讲师姓名
                    $info = DB::table('ld_course_open')
                    ->select("ld_lecturer_educationa.real_name")->where("ld_course_open.id" , $v['id'])->leftJoin('ld_course_open_teacher' , function($join){
                        $join->on('ld_course_open.id', '=', 'ld_course_open_teacher.course_id');
                    })->leftJoin("ld_lecturer_educationa" , function($join){
                        $join->on('ld_course_open_teacher.teacher_id', '=', 'ld_lecturer_educationa.id')->where("ld_lecturer_educationa.type" , 2);
                    })->first();
                    //判断课程状态
                    if($v['end_at'] < time()){
                        $status = 3;
                    } elseif($v['start_at'] > time()){
                        $status = 2;
                    } else {
                        $status = 1;
                    }

                    //新数组赋值
                    $lession_array[] = [
                        'open_class_id'  =>  $v['id'] ,
                        'cover'          =>  $v['cover'] && !empty($v['cover']) ? $v['cover'] : '' ,
                        'teacher_name'   =>  $info && !empty($info) ? $info->real_name : '' ,
                        'start_date'     =>  date('Y-m-d' , $v['start_at']) ,
                        'start_time'     =>  date('H:i' , $v['start_at']) ,
                        'end_time'       =>  date('H:i' , $v['end_at']) ,
                        'status'         =>  $status,
                        'course_id'      => $v['course_id']

                    ];
                }
                return response()->json(['code' => 200 , 'msg' => '获取公开课列表成功' , 'data' => $lession_array]);
            } else {
                return response()->json(['code' => 200 , 'msg' => '获取公开课列表成功' , 'data' => []]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

   /*
        * @param  description   首页讲师接口
        * @param author    dzj
        * @param ctime     2020-05-25
        * return string
        */
       public function getTeacherList(Request $request) {
           //获取提交的参数
           try{
               //获取请求的平台端
               $platform = verifyPlat() ? verifyPlat() : 'pc';
               //获取用户token值
               $token = $request->input('user_token');

               //hash中token赋值
               $token_key   = "user:regtoken:".$platform.":".$token;
               //判断token值是否合法
               $redis_token = Redis::hLen($token_key);


               if($redis_token && $redis_token > 0) {
                   //解析json获取用户详情信息
                   $json_info = Redis::hGetAll($token_key);

                   //登录显示属于分校的课程
                   if($json_info['school_id'] == 1){
                       //判断讲师列表是否为空
                           $teacher_count = Teacher::where("is_del" , 0)->where("is_forbid" , 0)->where("is_recommend" , 1)->where("type" , 2)->where("school_id" , $json_info['school_id'])->count();
                           if($teacher_count && $teacher_count > 0){
                               //新数组赋值
                               $teacher_array = [];
                               //获取讲师列表
                               $teacher_list  = Teacher::withCount('lessons as lesson_number')->where("is_del" , 0)->where("is_forbid" , 0)->where("is_recommend" , 1)->where("type" , 2)->where("school_id" , $json_info['school_id'])->offset(0)->limit(6)->get()->toArray();
                               foreach($teacher_list as $k=>$v){
                                   //根据大分类的id获取大分类的名称
                                   if($v['parent_id'] && $v['parent_id'] > 0){
                                       $lession_parent_name = Subject::where("id" , $v['parent_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                                   }

                                   //根据小分类的id获取小分类的名称
                                   if($v['child_id'] && $v['child_id'] > 0){
                                       $lession_child_name  = Subject::where("id" , $v['child_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                                   }

                                   //数组赋值
                                   $teacher_array[] = [
                                       'teacher_id'   =>   $v['id'] ,
                                       'teacher_name' =>   $v['real_name'] ,
                                       'teacher_icon' =>   $v['head_icon'] ,
                                       'lession_parent_name' => $v['parent_id'] > 0 ? !empty($lession_parent_name) ? $lession_parent_name : '' : '',
                                       'lession_child_name'  => $v['child_id']  > 0 ? !empty($lession_child_name)  ? $lession_child_name  : '' : '',
                                       'star_num'     => $v['star_num'],
                                       'lesson_number'=> $v['lesson_number'] ,
                                       'student_number'=>$v['student_number']
                                   ];
                               }
                               return response()->json(['code' => 200 , 'msg' => '获取讲师列表成功' , 'data' => $teacher_array]);
                           } else {
                               return response()->json(['code' => 200 , 'msg' => '获取讲师列表成功' , 'data' => []]);
                           }
                   }else{
                       //自增老师
                       //判断讲师列表是否为空
                       $teacher_count1 = Teacher::where("is_del" , 0)->where("is_forbid" , 0)->where("is_recommend" , 1)->where("type" , 2)->where("school_id" , $json_info['school_id'])->count();

                       //授权老师
                       $teacher_count2 = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->where("ld_lecturer_educationa.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.is_recommend" , 1)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , $json_info['school_id'])->count();

                       if( $teacher_count1 > 0  || $teacher_count2 > 0){

                           $teacher_list1  = Teacher::withCount('lessons as lesson_number')->where("is_del" , 0)->where("is_forbid" , 0)->where("is_recommend" , 1)->where("type" , 2)->where("school_id" , $json_info['school_id'])->get()->toArray();
                           $teacher_list2  = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->withCount('lessons as lesson_number')->where("ld_lecturer_educationa.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.is_recommend" , 1)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , $json_info['school_id'])->get()->toArray();
                           $teacher_list = array_merge($teacher_list1,$teacher_list2);
                           $teacher_list = array_unique($teacher_list,SORT_REGULAR);
                               foreach($teacher_list as $k=>$v){
                                   //根据大分类的id获取大分类的名称
                                   if($v['parent_id'] && $v['parent_id'] > 0){
                                       $lession_parent_name = Subject::where("id" , $v['parent_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                                   }

                                   //根据小分类的id获取小分类的名称
                                   if($v['child_id'] && $v['child_id'] > 0){
                                       $lession_child_name  = Subject::where("id" , $v['child_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                                   }

                                   //数组赋值
                                   $teacher_array[] = [
                                       'teacher_id'   =>   $v['id'] ,
                                       'teacher_name' =>   $v['real_name'] ,
                                       'teacher_icon' =>   $v['head_icon'] ,
                                       'lession_parent_name' => $v['parent_id'] > 0 ? !empty($lession_parent_name) ? $lession_parent_name : '' : '',
                                       'lession_child_name'  => $v['child_id']  > 0 ? !empty($lession_child_name)  ? $lession_child_name  : '' : '',
                                       'star_num'     => $v['star_num'],
                                       'lesson_number'=> $v['lesson_number'] ,
                                       'student_number'=>$v['student_number']
                                   ];
                               }
                               return response()->json(['code' => 200 , 'msg' => '获取讲师列表成功' , 'data' => $teacher_array]);
                       }else{
                           return response()->json(['code' => 200 , 'msg' => '获取讲师列表成功' , 'data' => []]);
                       }


                   }
               }else{

                   //自增老师
                       //判断讲师列表是否为空
                       $teacher_count1 = Teacher::where("is_del" , 0)->where("is_forbid" , 0)->where("is_recommend" , 1)->where("type" , 2)->where("school_id" , 30)->count();

                       //授权老师
                       $teacher_count2 = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->where("ld_lecturer_educationa.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.is_recommend" , 1)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , 30)->count();
                       if( $teacher_count1 > 0 || $teacher_count2 > 0){
                           $teacher_list1  = Teacher::withCount('lessons as lesson_number')->where("is_del" , 0)->where("is_forbid" , 0)->where("is_recommend" , 1)->where("type" , 2)->where("school_id" , 30)->get()->toArray();
                           $teacher_list2  = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->withCount('lessons as lesson_number')->where("ld_lecturer_educationa.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.is_recommend" , 1)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , 30)->get()->toArray();
                           $teacher_list = array_merge($teacher_list1,$teacher_list2);
                           $teacher_list = array_unique($teacher_list,SORT_REGULAR);
                               foreach($teacher_list as $k=>$v){
                                   //根据大分类的id获取大分类的名称
                                   if($v['parent_id'] && $v['parent_id'] > 0){
                                       $lession_parent_name = Subject::where("id" , $v['parent_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                                   }

                                   //根据小分类的id获取小分类的名称
                                   if($v['child_id'] && $v['child_id'] > 0){
                                       $lession_child_name  = Subject::where("id" , $v['child_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                                   }

                                   //数组赋值
                                   $teacher_array[] = [
                                       'teacher_id'   =>   $v['id'] ,
                                       'teacher_name' =>   $v['real_name'] ,
                                       'teacher_icon' =>   $v['head_icon'] ,
                                       'lession_parent_name' => $v['parent_id'] > 0 ? !empty($lession_parent_name) ? $lession_parent_name : '' : '',
                                       'lession_child_name'  => $v['child_id']  > 0 ? !empty($lession_child_name)  ? $lession_child_name  : '' : '',
                                       'star_num'     => $v['star_num'],
                                       'lesson_number'=> $v['lesson_number'] ,
                                       'student_number'=>$v['student_number']
                                   ];
                               }
                               return response()->json(['code' => 200 , 'msg' => '获取讲师列表成功' , 'data' => $teacher_array]);
                       }else{
                           return response()->json(['code' => 200 , 'msg' => '获取讲师列表成功' , 'data' => []]);
                       }
               }

           } catch (\Exception $ex) {
               return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
           }
       }
    /*
     * @param  description   APP版本升级接口
     * @param author    dzj
     * @param ctime     2020-05-27
     * return string
     */
    public function checkVersion(Request $request) {
        try {
            //获取请求的平台端
            $platform = $request->ostype;
            $version = $request->version;
            //判断是否是安卓平台还是ios平台
            if($platform == 'android'){
                //获取渠道码
                /*$channelCode = isset(self::$accept_data['channelCode']) && !empty(self::$accept_data['channelCode']) ? self::$accept_data['channelCode'] : '';
                if(!$channelCode || empty($channelCode)){
                    return response()->json(['code' => 201 , 'msg' => '渠道码为空']);
                }

                //通过渠道码获取所在的信息
                $channel_info = DB::table('ld_channel')->where("code" , $channelCode)->first();
                if(!$channel_info || empty($channel_info)){
                    return response()->json(['code' => 203 , 'msg' => '此渠道不存在']);
                }*/

                //获取版本的最新更新信息

                $version_info = DB::table('ld_version')->select('is_online','is_mustup','version','content','download_url')->where(["ostype"=>$platform])->orderBy('create_at' , 'DESC')->first();
                // if(!is_null($version_info)){
                //     $version_info->content = json_decode($version_info->content , true);
                // }else{
                //     $version_info = [];
                // }

                // //对比版本
                // $version_info = DB::table('ld_version')->select('is_online','is_mustup','version','content','download_url')->where(["ostype"=>$platform])->orderBy('create_at' , 'DESC')->first();

                //判断两个版本是否相等
                // if(empty($channel_info->version) || $version_info->version != $channel_info->version){
                //     $version_info->content = json_decode($version_info->content , true);
                //     //根据渠道码更新版本号
                //     DB::table('ld_channel')->where("code" , $channelCode)->update(['version' => $version_info->version]);
                //     return response()->json(['code' => 200 , 'msg' => '获取版本升级信息成功' , 'data' => $version_info]);
                // } else {
                //     return response()->json(['code' => 205 , 'msg' => '已是最新版本']);
                // }
            } else {
                //获取版本的最新更新信息
                $version_info = DB::table('ld_version')->select('is_online','is_mustup','version','content','download_url')->where(["ostype"=>$platform])->orderBy('create_at' , 'DESC')->first();
                if(!is_null($version_info)){
                    $version_info->content = json_decode($version_info->content , true);
                    $version_info->download_url = 'https://itunes.apple.com/cn/app/linkmore/id1504209758?mt=8';
                }
            }
            return response()->json(['code' => 200 , 'msg' => '获取版本升级信息成功' , 'data' => $version_info]);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   公开课列表接口
     * @param author    dzj
     * @param ctime     2020-05-25
     * return string
     */
    public function getOpenPublicList(Request $request) {
        //获取提交的参数
        try{
            $pagesize = isset(self::$accept_data['pagesize']) && self::$accept_data['pagesize'] > 0 ? self::$accept_data['pagesize'] : 15;
            $page     = isset(self::$accept_data['page']) && self::$accept_data['page'] > 0 ? self::$accept_data['page'] : 1;
            $offset   = ($page - 1) * $pagesize;
            $today_class     = [];
            $tomorrow_class  = [];
            $over_class      = [];
            $arr             = [];
            //登录状态
                //获取请求的平台端
                $platform = verifyPlat() ? verifyPlat() : 'pc';
                //获取用户token值
                $token = $request->input('user_token');
                //hash中token赋值
                $token_key   = "user:regtoken:".$platform.":".$token;
                //判断token值是否合法
                $redis_token = Redis::hLen($token_key);
                if($redis_token && $redis_token > 0) {
                    //解析json获取用户详情信息
                    $json_info = Redis::hGetAll($token_key);
                    if($json_info['school_id'] == 1){
                        $lession_list= DB::table('ld_course_open')
                        ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select(
                        DB::raw("any_value(ld_course_open.id) as id") ,
                        DB::raw("any_value(ld_course_open.cover) as cover") ,
                        DB::raw("any_value(ld_course_open.start_at) as start_at") ,
                        DB::raw("any_value(ld_course_open.end_at) as end_at") ,
                        DB::raw("any_value(ld_course_open_live_childs.course_id) as course_id") ,
                        DB::raw("from_unixtime(ld_course_open.start_at , '%Y-%m-%d') as start_time")
                        )
                        ->where('ld_course_open.school_id',1)
                        ->where('ld_course_open.is_del',0)
                        ->where('ld_course_open.status',1)
                        ->orderBy('ld_course_open.start_at' , 'DESC')
                        ->groupBy('ld_course_open.start_at')
                        ->offset($offset)->limit($pagesize)->get()->toArray();
                    }else{
                        //自增
                        $lession_list1 = DB::table('ld_course_open')
                        ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select(
                        DB::raw("any_value(ld_course_open.id) as id") ,
                        DB::raw("any_value(ld_course_open.cover) as cover") ,
                        DB::raw("any_value(ld_course_open.start_at) as start_at") ,
                        DB::raw("any_value(ld_course_open.end_at) as end_at") ,
                        DB::raw("any_value(ld_course_open_live_childs.course_id) as course_id") ,
                        DB::raw("from_unixtime(ld_course_open.start_at , '%Y-%m-%d') as start_time")
                        )
                        ->where('ld_course_open.school_id',$json_info['school_id'])
                        ->where('ld_course_open.is_del',0)
                        ->where('ld_course_open.status',1)
                        ->orderBy('ld_course_open.start_at' , 'DESC')
                        ->groupBy('ld_course_open.start_at')
                        ->get()->toArray();
                        //授权
                        $lession_list2 = DB::table('ld_course_ref_open')
                        ->join("ld_course_open","ld_course_ref_open.course_id","=","ld_course_open.id")
                        ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select(
                            DB::raw("any_value(ld_course_open.id) as id") ,
                            DB::raw("any_value(ld_course_open.cover) as cover") ,
                            DB::raw("any_value(ld_course_open.start_at) as start_at") ,
                            DB::raw("any_value(ld_course_open.end_at) as end_at") ,
                            DB::raw("any_value(ld_course_open_live_childs.course_id) as course_id") ,
                            DB::raw("from_unixtime(ld_course_open.start_at , '%Y-%m-%d') as start_time")
                        )
                        ->where('ld_course_ref_open.to_school_id',$json_info['school_id'])
                        ->where('ld_course_open.is_del',0)
                        ->where('ld_course_open.status',1)
                        ->orderBy('ld_course_open.start_at' , 'DESC')
                        ->groupBy('ld_course_open.start_at')
                        ->get()->toArray();
                        $lession_list = array_merge($lession_list1,$lession_list2);
                        $start =($page - 1) * $pagesize;
                        $limit_s= $start + $pagesize;
                        $data = [];
                        for ($i = $start; $i < $limit_s; $i++) {
                            if (!empty($lession_list[$i])) {
                                    array_push($data, $lession_list[$i]);
                                }
                        }
                        $lession_list = $data;
                    }
                }else{
                    //自增
                    $lession_list1 = DB::table('ld_course_open')
                    ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                    ->select(
                    DB::raw("any_value(ld_course_open.id) as id") ,
                    DB::raw("any_value(ld_course_open.cover) as cover") ,
                    DB::raw("any_value(ld_course_open.start_at) as start_at") ,
                    DB::raw("any_value(ld_course_open.end_at) as end_at") ,
                    DB::raw("any_value(ld_course_open_live_childs.course_id) as course_id") ,
                    DB::raw("from_unixtime(ld_course_open.start_at , '%Y-%m-%d') as start_time")
                    )
                    ->where('ld_course_open.school_id',$json_info['school_id'])
                    ->where('ld_course_open.is_del',0)
                    ->where('ld_course_open.status',1)
                    ->orderBy('ld_course_open.start_at' , 'DESC')
                    ->groupBy('ld_course_open.start_at')
                    ->get()->toArray();
                    //授权
                    $lession_list2 = DB::table('ld_course_ref_open')
                    ->join("ld_course_open","ld_course_ref_open.course_id","=","ld_course_open.id")
                    ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                    ->select(
                        DB::raw("any_value(ld_course_open.id) as id") ,
                        DB::raw("any_value(ld_course_open.cover) as cover") ,
                        DB::raw("any_value(ld_course_open.start_at) as start_at") ,
                        DB::raw("any_value(ld_course_open.end_at) as end_at") ,
                        DB::raw("any_value(ld_course_open_live_childs.course_id) as course_id") ,
                        DB::raw("from_unixtime(ld_course_open.start_at , '%Y-%m-%d') as start_time")
                    )
                    ->where('ld_course_ref_open.to_school_id',$json_info['school_id'])
                    ->where('ld_course_open.is_del',0)
                    ->where('ld_course_open.status',1)
                    ->orderBy('ld_course_open.start_at' , 'DESC')
                    ->groupBy('ld_course_open.start_at')
                    ->get()->toArray();
                    $lession_list = array_merge($lession_list1,$lession_list2);
                    $start =($page - 1) * $pagesize;
                    $limit_s= $start + $pagesize;
                    $data = [];
                    for ($i = $start; $i < $limit_s; $i++) {
                        if (!empty($lession_list[$i])) {
                                array_push($data, $lession_list[$i]);
                            }
                    }
                    $lession_list = $data;
                }
            //判读公开课列表是否为空
            if($lession_list && !empty($lession_list)){
                foreach($lession_list as $k=>$v){
                    //获取当天公开课列表的数据
                    if($v->start_time == date('Y-m-d')){
                        //根据开始日期和结束日期进行查询
                        $class_list = DB::table('ld_course_open')
                        ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select('ld_course_open.id as open_class_id','ld_course_open_live_childs.course_id','title' , 'cover' , DB::raw("from_unixtime(start_at , '%H:%i') as start_time") , DB::raw("from_unixtime(end_at , '%H:%i') as end_time") , 'start_at' , 'end_at')->where('start_at' , '>=' , strtotime($v->start_time.' 00:00:00'))->where('end_at' , '<=' , strtotime($v->start_time.' 23:59:59'))->where('ld_course_open.is_del',0)->where('ld_course_open.status',1)->orderBy('start_at' , 'ASC')->get()->toArray();
                        $today_arr = [];
                        foreach($class_list as $k1=>$v1){
                            //判断课程状态
                            if($v1->end_at < time()){
                                $status = 3;
                            } elseif($v1->start_at > time()){
                                $status = 2;
                            } else {
                                $status = 1;
                            }
                            //封装数组
                            $today_arr[] = [
                                'open_class_id'       =>   $v1->open_class_id  ,
                                'cover'               =>   $v1->cover ,
                                'start_time'          =>   $v1->start_time ,
                                'end_time'            =>   $v1->end_time ,
                                'open_class_name'     =>   $v1->title ,
                                'course_id'           =>   $v1->course_id ,
                                'status'              =>   $status
                            ];
                        }
                        //课程时间点排序
                        array_multisort(array_column($today_arr, 'start_time') , SORT_ASC , $today_arr);
                        //公开课日期赋值
                        $today_class[$v->start_time]['open_class_date']   = $v->start_time;
                        //公开课列表赋值
                        $today_class[$v->start_time]['open_class_list']   = $today_arr;
                    } else if($v->start_time > date('Y-m-d')) {
                        //公开课日期赋值
                        $class_list = DB::table('ld_course_open')
                        ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select('ld_course_open.id as open_class_id' ,'ld_course_open_live_childs.course_id', 'title' , 'cover' , DB::raw("from_unixtime(start_at , '%H:%i') as start_time") , DB::raw("from_unixtime(end_at , '%H:%i') as end_time") , 'start_at' , 'end_at')->where("start_at" , ">" , strtotime($v->start_time.' 00:00:00'))->where("end_at" , "<" , strtotime($v->start_time.' 23:59:59'))->where('ld_course_open.is_del',0)->where('ld_course_open.status',1)->orderBy('start_at' , 'ASC')->get()->toArray();
                        $date2_arr = [];
                        foreach($class_list as $k2=>$v2){
                            $date2_arr[] = [
                                'open_class_id'       =>   $v2->open_class_id  ,
                                'cover'               =>   $v2->cover ,
                                'start_time'          =>   $v2->start_time ,
                                'end_time'            =>   $v2->end_time ,
                                'open_class_name'     =>   $v2->title ,
                                'course_id'           =>   $v2->course_id ,
                                'status'              =>   2
                            ];
                        }
                        //课程时间点排序
                        array_multisort(array_column($date2_arr, 'start_time') , SORT_ASC , $date2_arr);
                        //公开课日期赋值
                        $tomorrow_class[$v->start_time]['open_class_date']   = $v->start_time;
                        //公开课列表赋值
                        $tomorrow_class[$v->start_time]['open_class_list']   = $date2_arr;
                    } else {
                        //公开课日期赋值
                        $class_list = DB::table('ld_course_open')
                        ->join("ld_course_open_live_childs","ld_course_open.id","=","ld_course_open_live_childs.lesson_id")
                        ->select('ld_course_open.id as open_class_id' ,'ld_course_open_live_childs.course_id', 'title' , 'cover' , DB::raw("from_unixtime(start_at , '%H:%i') as start_time") , DB::raw("from_unixtime(end_at , '%H:%i') as end_time") , 'start_at' , 'end_at')->where("start_at" , ">" , strtotime($v->start_time.' 00:00:00'))->where("end_at" , "<" , strtotime($v->start_time.' 23:59:59'))->where('ld_course_open.is_del',0)->where('ld_course_open.status',1)->orderBy('start_at' , 'ASC')->get()->toArray();
                        $date_arr = [];
                        foreach($class_list as $k2=>$v2){
                            $date_arr[] = [
                                'open_class_id'       =>   $v2->open_class_id  ,
                                'cover'               =>   $v2->cover ,
                                'start_time'          =>   $v2->start_time ,
                                'end_time'            =>   $v2->end_time ,
                                'open_class_name'     =>   $v2->title ,
                                'course_id'           =>   $v2->course_id ,
                                'status'              =>   3
                            ];
                        }
                        //课程时间点排序
                        array_multisort(array_column($date_arr, 'start_time') , SORT_ASC , $date_arr);
                        //公开课日期赋值
                        $over_class[$v->start_time]['open_class_date']   = $v->start_time;
                        //公开课列表赋值
                        $over_class[$v->start_time]['open_class_list']   = $date_arr;
                    }
                }
                //判断明天课程是否为空
                if($tomorrow_class && !empty($tomorrow_class)){
                    //课程时间点排序
                    array_multisort(array_column($tomorrow_class, 'open_class_date') , SORT_ASC , $tomorrow_class);
                }
                $arr =  array_merge(array_values($today_class) , array_values($tomorrow_class) , array_values($over_class));
            }
            return response()->json(['code' => 200 , 'msg' => '获取公开课列表成功' , 'data' => $arr]);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   名师列表接口
     * @param author    dzj
     * @param ctime     2020-06-02
     * return string
     */
    public function getFamousTeacherList(Request $request){
        //获取提交的参数
        try{
            //每页显示条数
            $pagesize = isset(self::$accept_data['pagesize']) && self::$accept_data['pagesize'] > 0 ? self::$accept_data['pagesize'] : 15;
            //当前页数
            $page     = isset(self::$accept_data['page']) && self::$accept_data['page'] > 0 ? self::$accept_data['page'] : 1;
            //分页标识符
            $offset   = ($page - 1) * $pagesize;
            //类型(0表示综合,1表示人气,2表示好评)
            $type     = isset(self::$accept_data['type']) && self::$accept_data['type'] > 0 ? self::$accept_data['type'] : 0;
            //获取请求的平台端
            $platform = verifyPlat() ? verifyPlat() : 'pc';
            //获取用户token值
            $token = $request->input('user_token');
            //hash中token赋值
            $token_key   = "user:regtoken:".$platform.":".$token;
            //判断token值是否合法
            $redis_token = Redis::hLen($token_key);
            if($redis_token && $redis_token > 0) {
                //解析json获取用户详情信息
                $json_info = Redis::hGetAll($token_key);
                if($json_info['school_id'] == 1){
                    //主校
                            //根据人气、好评、综合进行排序
                            if($type == 1){ //人气排序|好评排序
                                //获取名师列表
                                $famous_teacher_list = Teacher::withCount('lessons as lesson_number')->where('type' , 2)->where('is_del' , 0)->where('is_forbid' , 0)->offset($offset)->limit($pagesize)->get();
                            } else {  //综合排序|好评
                                //获取名师列表
                                $famous_teacher_list = Teacher::withCount('lessons as lesson_number')->where('type' , 2)->where('is_del' , 0)->where('is_forbid' , 0)->orderBy('is_recommend' , 'DESC')->offset($offset)->limit($pagesize)->get();
                            }
                            //判断讲师是否为空
                            if($famous_teacher_list && !empty($famous_teacher_list)){
                                //将对象转化为数组信息
                                $famous_teacher_list = $famous_teacher_list->toArray();
                                if($type == 1){
                                    $sort_field = $type == 1 ? 'student_number' : 'star_num';
                                    array_multisort(array_column($famous_teacher_list, $sort_field) , SORT_DESC , $famous_teacher_list);
                                    $famous_teacher_list = array_slice($famous_teacher_list,$offset,$pagesize);
                                }

                                //空数组
                                $teacher_list = [];
                                foreach($famous_teacher_list as $k=>$v){
                                    //根据大分类的id获取大分类的名称
                                    if($v['parent_id'] && $v['parent_id'] > 0){
                                        $lession_parent_name = Subject::where("id" , $v['parent_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                                    }

                                    //根据小分类的id获取小分类的名称
                                    if($v['child_id'] && $v['child_id'] > 0){
                                        $lession_child_name  = Subject::where("id" , $v['child_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                                    }

                                    //数组数值信息赋值
                                    $teacher_list[] = [
                                        'teacher_id'          =>  $v['id'] ,
                                        'teacher_icon'        =>  $v['head_icon'] ,
                                        'teacher_name'        =>  $v['real_name'] ,
                                        'lession_parent_name' =>  $v['parent_id'] > 0 ? !empty($lession_parent_name) ? $lession_parent_name : '' : '',
                                        'lession_child_name'  =>  $v['child_id']  > 0 ? !empty($lession_child_name)  ? $lession_child_name  : '' : '',
                                        'star_num'            =>  $v['star_num'] ,
                                        'lesson_number'       =>  $v['lesson_number'] ,
                                        'student_number'      =>  $v['student_number']
                                    ];
                                }
                            } else {
                                $teacher_list = "";
                            }
                            return response()->json(['code' => 200 , 'msg' => '获取名师列表成功' , 'data' => $teacher_list]);
                }else{
                    //分校
                    //根据人气、好评、综合进行排序
                    if($type == 1){ //人气排序|好评排序
                        //获取名师列表
                        $famous_teacher_list = Teacher::withCount('lessons as lesson_number')->where('type' , 2)->where('is_del' , 0)->where('is_forbid' , 0)->get();
                        //自增
                        $famous_teacher_count1 = Teacher::withCount('lessons as lesson_number')->where("is_del" , 0)->where("is_forbid" , 0)->where("type" , 2)->where("school_id" , $json_info['school_id'])->count();
                        //授权老师
                        $famous_teacher_count2 = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->where("ld_lecturer_educationa.is_del" , 0)->where("ld_course_ref_teacher.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , $json_info['school_id'])->count();
                            $famous_teacher_list1  = Teacher::withCount('lessons as lesson_number')->where("is_del" , 0)->where("is_forbid" , 0)->where("type" , 2)->where("school_id" , $json_info['school_id'])->get()->toArray();
                            $famous_teacher_list2  = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->withCount('lessons as lesson_number')->where("ld_course_ref_teacher.is_del" , 0)->where("ld_lecturer_educationa.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , $json_info['school_id'])->get()->toArray();
                            $famous_teacher_list = array_merge($famous_teacher_list1,$famous_teacher_list2);
                            $famous_teacher_list = array_unique($famous_teacher_list,SORT_REGULAR);
                    } else {  //综合排序|好评
                        //获取名师列表
                        //$famous_teacher_list = Teacher::withCount('lessons as lesson_number')->where('type' , 2)->where('is_del' , 0)->where('is_forbid' , 0)->orderBy('is_recommend' , 'DESC')->offset($offset)->limit($pagesize)->get();
                        //获取名师列表
                        $famous_teacher_list = Teacher::withCount('lessons as lesson_number')->where('type' , 2)->where('is_del' , 0)->where('is_forbid' , 0)->get();
                        //自增
                        $famous_teacher_count1 = Teacher::withCount('lessons as lesson_number')->where("is_del" , 0)->where("is_forbid" , 0)->where("type" , 2)->where("school_id" , $json_info['school_id'])->count();
                        //授权老师
                        $famous_teacher_count2 = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->where("ld_lecturer_educationa.is_del" , 0)->where("ld_course_ref_teacher.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , $json_info['school_id'])->count();

                            $famous_teacher_list1  = Teacher::withCount('lessons as lesson_number')->where("is_del" , 0)->where("is_forbid" , 0)->where("type" , 2)->where("school_id" , $json_info['school_id'])->orderBy('is_recommend' , 'DESC')->get()->toArray();

                            $famous_teacher_list2  = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->withCount('lessons as lesson_number')->where("ld_course_ref_teacher.is_del" , 0)->where("ld_lecturer_educationa.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , $json_info['school_id'])->orderBy('is_recommend' , 'DESC')->get()->toArray();
                            $famous_teacher_list = array_merge($famous_teacher_list1,$famous_teacher_list2);
                            $famous_teacher_list = array_unique($famous_teacher_list,SORT_REGULAR);
                    }
                    //判断讲师是否为空
                    if($famous_teacher_list && !empty($famous_teacher_list)){
                        //将对象转化为数组信息
                        //$famous_teacher_list = $famous_teacher_list->toArray();
                        if($type == 1){
                            $sort_field = $type == 1 ? 'student_number' : 'star_num';
                            array_multisort(array_column($famous_teacher_list, $sort_field) , SORT_DESC , $famous_teacher_list);
                            $famous_teacher_list = array_slice($famous_teacher_list,$offset,$pagesize);
                        }

                        //空数组
                        $teacher_list = [];
                        foreach($famous_teacher_list as $k=>$v){
                            //根据大分类的id获取大分类的名称
                            if($v['parent_id'] && $v['parent_id'] > 0){
                                $lession_parent_name = Subject::where("id" , $v['parent_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                            }

                            //根据小分类的id获取小分类的名称
                            if($v['child_id'] && $v['child_id'] > 0){
                                $lession_child_name  = Subject::where("id" , $v['child_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                            }

                            //数组数值信息赋值
                            $teacher_list[] = [
                                'teacher_id'          =>  $v['id'] ,
                                'teacher_icon'        =>  $v['head_icon'] ,
                                'teacher_name'        =>  $v['real_name'] ,
                                'lession_parent_name' =>  $v['parent_id'] > 0 ? !empty($lession_parent_name) ? $lession_parent_name : '' : '',
                                'lession_child_name'  =>  $v['child_id']  > 0 ? !empty($lession_child_name)  ? $lession_child_name  : '' : '',
                                'star_num'            =>  $v['star_num'] ,
                                'lesson_number'       =>  $v['lesson_number'] ,
                                'student_number'      =>  $v['student_number']
                            ];
                        }

                        //数据分页
                        $start =($page - 1) * $pagesize;
                        $limit_s= $start + $pagesize;
                        $data = [];
                        for ($i = $start; $i < $limit_s; $i++) {
                            if (!empty($teacher_list[$i])) {
                                    array_push($data, $teacher_list[$i]);
                                }
                        }
                        $teacher_list = $data;
                    } else {
                        $teacher_list = "";
                    }
                    return response()->json(['code' => 200 , 'msg' => '获取名师列表成功' , 'data' => $teacher_list]);

                }
            }else{
                        //根据人气、好评、综合进行排序
                    if($type == 1){ //人气排序|好评排序
                        //获取名师列表
                        $famous_teacher_list  = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->withCount('lessons as lesson_number')->where("ld_course_ref_teacher.is_del" , 0)->where("ld_lecturer_educationa.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , 30)->get();
                        // $famous_teacher_list = Teacher::withCount('lessons as lesson_number')->where("school_id",1)->where('type' , 2)->where('is_del' , 0)->where('is_forbid' , 0)->get();
                    } else {  //综合排序|好评
                        //获取名师列表

                        $famous_teacher_list  = Teacher::join("ld_course_ref_teacher","ld_lecturer_educationa.id","=","ld_course_ref_teacher.teacher_id")->withCount('lessons as lesson_number')->where("ld_course_ref_teacher.is_del" , 0)->where("ld_lecturer_educationa.is_del" , 0)->where("ld_lecturer_educationa.is_forbid" , 0)->where("ld_lecturer_educationa.type" , 2)->where("to_school_id" , 30)->orderBy('is_recommend' , 'DESC')->get();
                        // $famous_teacher_list = Teacher::withCount('lessons as lesson_number')->where("school_id",1)->where('type' , 2)->where('is_del' , 0)->where('is_forbid' , 0)->orderBy('is_recommend' , 'DESC')->offset($offset)->limit($pagesize)->get();
                    }

                    //判断讲师是否为空
                    if($famous_teacher_list && !empty($famous_teacher_list)){
                        //将对象转化为数组信息
                        $famous_teacher_list = $famous_teacher_list->toArray();
                        if($type == 1){
                            $sort_field = $type == 1 ? 'student_number' : 'star_num';
                            array_multisort(array_column($famous_teacher_list, $sort_field) , SORT_DESC , $famous_teacher_list);
                            $famous_teacher_list = array_slice($famous_teacher_list,$offset,$pagesize);
                        }

                        //空数组
                        $teacher_list = [];
                        foreach($famous_teacher_list as $k=>$v){
                            //根据大分类的id获取大分类的名称
                            if($v['parent_id'] && $v['parent_id'] > 0){
                                $lession_parent_name = Subject::where("id" , $v['parent_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                            }

                            //根据小分类的id获取小分类的名称
                            if($v['child_id'] && $v['child_id'] > 0){
                                $lession_child_name  = Subject::where("id" , $v['child_id'])->where("is_del" , 0)->where("is_open" , 0)->value("subject_name");
                            }

                            //数组数值信息赋值
                            $teacher_list[] = [
                                'teacher_id'          =>  $v['id'] ,
                                'teacher_icon'        =>  $v['head_icon'] ,
                                'teacher_name'        =>  $v['real_name'] ,
                                'lession_parent_name' =>  $v['parent_id'] > 0 ? !empty($lession_parent_name) ? $lession_parent_name : '' : '',
                                'lession_child_name'  =>  $v['child_id']  > 0 ? !empty($lession_child_name)  ? $lession_child_name  : '' : '',
                                'star_num'            =>  $v['star_num'] ,
                                'lesson_number'       =>  $v['lesson_number'] ,
                                'student_number'      =>  $v['student_number']
                            ];
                        }
                    } else {
                        $teacher_list = "";
                    }
                    return response()->json(['code' => 200 , 'msg' => '获取名师列表成功' , 'data' => $teacher_list]);
            }

        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   名师详情接口
     * @param author    dzj
     * @param ctime     2020-06-02
     * return string
     */
    public function getFamousTeacherInfo(){
        //获取提交的参数
        try{
            //获取名师id
            $teacher_id  = isset(self::$accept_data['teacher_id']) && !empty(self::$accept_data['teacher_id']) && self::$accept_data['teacher_id'] > 0 ? self::$accept_data['teacher_id'] : 0;
            if(!$teacher_id || $teacher_id <= 0 || !is_numeric($teacher_id)){
                return response()->json(['code' => 202 , 'msg' => '名师id不合法']);
            }

            //空数组赋值
            $teacher_array = "";

            //根据名师的id获取名师的详情信息
            $teacher_info  =  Teacher::where('id' , $teacher_id)->where('type' , 2)->where('is_del' , 0)->where('is_forbid' , 0)->first();
            if($teacher_info && !empty($teacher_info)){
                //名师数组信息
                $teacher_array = [
                    'teacher_icon'   =>   $teacher_info->head_icon  ,
                    'teacher_name'   =>   $teacher_info->real_name  ,
                    'teacher_content'=>   $teacher_info->content
                ];
            }
            return response()->json(['code' => 200 , 'msg' => '获取名师详情成功' , 'data' => $teacher_array]);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   名师课程列表接口
     * @param author    dzj
     * @param ctime     2020-06-02
     * return string
     */
    public function getTeacherLessonList(Request $request){
        //获取提交的参数
        try{
            //获取名师id
            $teacher_id  = isset(self::$accept_data['teacher_id']) && !empty(self::$accept_data['teacher_id']) && self::$accept_data['teacher_id'] > 0 ? self::$accept_data['teacher_id'] : 0;
            if(!$teacher_id || $teacher_id <= 0 || !is_numeric($teacher_id)){
                return response()->json(['code' => 202 , 'msg' => '名师id不合法']);
            }

            //分页相关的参数
            $pagesize = isset(self::$accept_data['pagesize']) && self::$accept_data['pagesize'] > 0 ? self::$accept_data['pagesize'] : 15;
            $page     = isset(self::$accept_data['page']) && self::$accept_data['page'] > 0 ? self::$accept_data['page'] : 1;
            $offset   = ($page - 1) * $pagesize;

            //获取名师课程列表
             //获取请求的平台端
             $platform = verifyPlat() ? verifyPlat() : 'pc';
             //获取用户token值
             $token = $request->input('user_token');
             //hash中token赋值
             $token_key   = "user:regtoken:".$platform.":".$token;
             //判断token值是否合法
             $redis_token = Redis::hLen($token_key);
             if($redis_token && $redis_token > 0) {
                 //解析json获取用户详情信息
                 $json_info = Redis::hGetAll($token_key);
                 //自增课程
                 $teacher_lesson_list = Lesson::join('ld_course_teacher','ld_course_teacher.course_id','=','ld_course.id')
                 ->select('ld_course.id', 'admin_id', 'title', 'cover', 'pricing as price', 'sale_price as favorable_price', 'buy_num', 'status', 'ld_course.is_del')
                 ->where(['ld_course.is_del'=> 0, 'ld_course.status' => 1,'ld_course.school_id' => $json_info['school_id'],'ld_course_teacher.teacher_id'=>$teacher_id])
                 ->groupBy("ld_course.id")
                 ->get()->toArray();
                 foreach($teacher_lesson_list as $k => &$v){
                     //获取授课模式
                     $v['methods'] = DB::table('ld_course')->select('method_id as id')->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")->where(['ld_course.id'=>$v['id']])->get();
                     $v['nature'] = 0;
                 }
                 //授权课程   先取授权课程  通过course_id 获取讲师
                 $teacher_lesson_accredit_list = CourseSchool::join('ld_course_teacher','ld_course_teacher.course_id','=','ld_course_school.course_id')
                 ->select('ld_course_school.course_id as id','ld_course_school.id as school_course_id','admin_id', 'title', 'cover', 'pricing as price', 'sale_price as favorable_price', 'buy_num', 'status', 'ld_course_school.is_del')
                 ->where(['ld_course_school.is_del'=> 0, 'ld_course_school.status' => 1,'ld_course_school.to_school_id' => $json_info['school_id'],'ld_course_teacher.teacher_id'=>$teacher_id])
                 ->groupBy("ld_course_school.id")
                 ->get()->toArray();

                 foreach($teacher_lesson_list as $k => &$v){
                     //获取授课模式
                     $v['methods'] = DB::table('ld_course')->select('method_id as id')->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")->where(['ld_course.id'=>$v['id']])->get();
                     $v['nature'] = 1;
                 }

                 foreach($teacher_lesson_accredit_list as $k => &$v){
                    //获取授课模式
                    $v['methods'] = DB::table('ld_course_school')->select('method_id as id')->join("ld_course_method","ld_course_school.course_id","=","ld_course_method.course_id")->where(['ld_course_school.id'=>$v['school_course_id']])->get();
                }
                $teacher_lesson_list = array_merge($teacher_lesson_list,$teacher_lesson_accredit_list);
                //数据分页
                $start =($page - 1) * $pagesize;
                $limit_s= $start + $pagesize;
                $teacher_lesson = [];
                for ($i = $start; $i < $limit_s; $i++) {
                    if (!empty($teacher_lesson_list[$i])) {
                            array_push($teacher_lesson, $teacher_lesson_list[$i]);
                        }
                }
             }else{
                //解析json获取用户详情信息
                $json_info = Redis::hGetAll($token_key);
                //自增课程
                $teacher_lesson_list = Lesson::join('ld_course_teacher','ld_course_teacher.course_id','=','ld_course.id')
                ->select('ld_course.id', 'admin_id', 'title', 'cover', 'pricing as price', 'sale_price as favorable_price', 'buy_num', 'status', 'ld_course.is_del')
                ->where(['ld_course.is_del'=> 0, 'ld_course.status' => 1,'ld_course.school_id' => 30,'ld_course_teacher.teacher_id'=>$teacher_id])
                ->groupBy("ld_course.id")
                ->get()->toArray();
                foreach($teacher_lesson_list as $k => &$v){
                    //获取授课模式
                    $v['methods'] = DB::table('ld_course')->select('method_id as id')->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")->where(['ld_course.id'=>$v['id']])->get();
                    $v['nature'] = 0;
                }
                //授权课程   先取授权课程  通过course_id 获取讲师
                $teacher_lesson_accredit_list = CourseSchool::join('ld_course_teacher','ld_course_teacher.course_id','=','ld_course_school.course_id')
                ->select('ld_course_school.course_id as id','ld_course_school.id as school_course_id','admin_id', 'title', 'cover', 'pricing as price', 'sale_price as favorable_price', 'buy_num', 'status', 'ld_course_school.is_del')
                ->where(['ld_course_school.is_del'=> 0, 'ld_course_school.status' => 1,'ld_course_school.to_school_id' => 30,'ld_course_teacher.teacher_id'=>$teacher_id])
                ->groupBy("ld_course_school.id")
                ->get()->toArray();

                foreach($teacher_lesson_list as $k => &$v){
                    //获取授课模式
                    $v['methods'] = DB::table('ld_course')->select('method_id as id')->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")->where(['ld_course.id'=>$v['id']])->get();
                    $v['nature'] = 1;
                }

                foreach($teacher_lesson_accredit_list as $k => &$v){
                   //获取授课模式
                   $v['methods'] = DB::table('ld_course_school')->select('method_id as id')->join("ld_course_method","ld_course_school.course_id","=","ld_course_method.course_id")->where(['ld_course_school.id'=>$v['school_course_id']])->get();
               }
               $teacher_lesson_list = array_merge($teacher_lesson_list,$teacher_lesson_accredit_list);
               //数据分页
               $start =($page - 1) * $pagesize;
               $limit_s= $start + $pagesize;
               $teacher_lesson = [];
               for ($i = $start; $i < $limit_s; $i++) {
                   if (!empty($teacher_lesson_list[$i])) {
                           array_push($teacher_lesson, $teacher_lesson_list[$i]);
                       }
               }
             }
            foreach($teacher_lesson as $k => &$v){
                foreach($v['methods'] as $kk => &$vv){
                    if($vv->id == 1){
                        $vv->name = "直播";
                    }else if($vv->id == 2){
                        $vv->name = "录播";
                    }else{
                        $vv->name = "其他";
                    }
                }
            }
            if($teacher_lesson && !empty($teacher_lesson)){
                return response()->json(['code' => 200 , 'msg' => '获取名师课程列表成功' , 'data' => $teacher_lesson]);
            } else {
                return response()->json(['code' => 200 , 'msg' => '获取名师课程列表成功' , 'data' => []]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /**
     * @param  description   首页学科接口
     * @param author    sxl
     * @param ctime     2020-05-28
     * @return string
     */
    public function getSubjectList(Request $request) {
        //获取提交的参数
        try{

            //获取请求的平台端
            $platform = verifyPlat() ? verifyPlat() : 'pc';
            //获取用户token值
            $token = $request->input('user_token');
            //hash中token赋值
            $token_key   = "user:regtoken:".$platform.":".$token;
            //判断token值是否合法
            $redis_token = Redis::hLen($token_key);
            if($redis_token && $redis_token > 0) {
                //解析json获取用户详情信息
                $json_info = Redis::hGetAll($token_key);
                //登录显示属于分校的课程
                if($json_info['school_id'] == 1){
                    $subject = Subject::select('id', 'subject_name as name')->where(['is_del' => 0,'parent_id' => 0])->limit(6)->get();
                    return $this->response($subject);
                }else{
                    //查询分校学科id
                    //自增科目
                    $subject2 = Subject::select('id', 'subject_name as name')
                    ->where(['is_del' => 0,'parent_id' => 0,"school_id" => $json_info['school_id']])
                    ->get()->toArray();
                    //授权科目
                    $subject1 = CourseRefSubject::join("ld_course_subject","ld_course_ref_subject.parent_id","=","ld_course_subject.id")
                    ->select('ld_course_subject.id', 'subject_name as name')
                    ->where(['ld_course_subject.is_del' => 0,'ld_course_subject.parent_id' => 0,'to_school_id'=>$json_info['school_id']])
                    ->get()->toArray();
                    $subject = array_merge($subject1,$subject2);
                    $subject = array_unique($subject,SORT_REGULAR);
                    return $this->response(array_slice($subject,0,6));
                }
            }else{

                //查询分校学科id
                    //自增科目
                    $subject2 = Subject::select('id', 'subject_name as name')
                    ->where(['is_del' => 0,'parent_id' => 0,"school_id" => 30])
                    ->get()->toArray();
                    //授权科目
                    $subject1 = CourseRefSubject::join("ld_course_subject","ld_course_ref_subject.parent_id","=","ld_course_subject.id")
                    ->select('ld_course_subject.id', 'subject_name as name')
                    ->where(['ld_course_subject.is_del' => 0,'ld_course_subject.parent_id' => 0,'to_school_id'=> 30])
                    ->get()->toArray();
                    $subject = array_merge($subject1,$subject2);
                    $subject = array_unique($subject,SORT_REGULAR);
                    return $this->response(array_slice($subject,0,6));
            }

        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /**
     * @param  description   首页课程接口
     * @param author    sxl
     * @param ctime     2020-05-28
     * @return string
     */
    public function getLessonList(Request $request) {
        //获取提交的参数
        try{
                //获取请求的平台端
                $platform = verifyPlat() ? verifyPlat() : 'pc';
                //获取用户token值
                $token = $request->input('user_token');
                //hash中token赋值
                $token_key   = "user:regtoken:".$platform.":".$token;
                //判断token值是否合法
                $redis_token = Redis::hLen($token_key);
                if($redis_token && $redis_token > 0) {
                    //解析json获取用户详情信息
                    $json_info = Redis::hGetAll($token_key);
                    //登录显示属于分校的课程
                    if($json_info['school_id'] == 1){
                        $subject = Subject::select('id', 'subject_name as name')
                        ->where(['is_del' => 0,'parent_id' => 0,"school_id" => $json_info['school_id']])
                        ->get()->toArray();
                    }else{
                        //自增科目
                        $subject2 = Subject::select('id', 'subject_name as name')
                        ->where(['is_del' => 0,'parent_id' => 0,"school_id" => $json_info['school_id']])
                        ->get()->toArray();

                        //授权科目
                        $subject1 = CourseRefSubject::join("ld_course_subject","ld_course_ref_subject.parent_id","=","ld_course_subject.id")
                        ->select('ld_course_subject.id', 'subject_name as name')
                        ->where(['ld_course_subject.is_del' => 0,'ld_course_subject.parent_id' => 0,'to_school_id'=>$json_info['school_id']])
                        ->get()->toArray();
                        $subject = array_merge($subject1,$subject2);
                        $subject = array_unique($subject,SORT_REGULAR);
                    }
                        $subject = array_slice($subject,0,5);
                        $lessons = [];
                        //dd($subject);
                        foreach($subject as $k =>$v){
                            $lesson = Lesson::join("ld_course_subject","ld_course_subject.id","=","ld_course.parent_id")
                            ->select('ld_course.id', 'ld_course.title', 'ld_course.cover', 'ld_course.buy_num', 'ld_course.pricing as old_price', 'ld_course.sale_price as favorable_price','ld_course.is_recommend')
                            ->where(['ld_course.is_del' => 0,'ld_course.school_id' => $json_info['school_id'], 'ld_course.is_recommend' => 1, 'ld_course.status' => 1,'ld_course.parent_id' => $v['id']])
                            ->get();
                            $lesson_school = CourseSchool::join("ld_course_subject","ld_course_subject.id","=","ld_course_school.parent_id")
                            ->select('ld_course_school.id as course_id', 'ld_course_school.course_id as id','ld_course_school.title', 'ld_course_school.cover', 'ld_course_school.buy_num', 'ld_course_school.pricing as old_price', 'ld_course_school.sale_price as favorable_price','ld_course_school.is_recommend')
                            ->where(['ld_course_school.is_del' => 0,'ld_course_school.to_school_id' => $json_info['school_id'], 'ld_course_school.is_recommend' => 1, 'ld_course_school.status' => 1,'ld_course_school.parent_id' => $v['id']])
                            ->get();

                            if(!empty($lesson->toArray())){
                                $arr = [
                                    'subject' => $v,
                                    'lesson' => $lesson,
                                ];
                                $lessons[] = $arr;
                            }

                            if(!empty($lesson_school->toArray())){
                                $arr = [
                                    'subject' => $v,
                                    'lesson' => $lesson_school,
                                ];
                                $lessons[] = $arr;
                            }
                        }
                        foreach($lessons as $k => $v){

                            foreach($v['lesson'] as $kk => $vv){

                                $token = isset($_REQUEST['user_token']) ? $_REQUEST['user_token'] : 0;
                                $student = Student::where('token', $token)->first();
                                //购买人数  基数加真是购买人数
                                $vv['buy_num'] =  Order::where(['oa_status'=>1,'class_id'=>$vv['id']])->count() + $vv['buy_num'];
                                if(!empty($student)){
                                    $num = Order::where(['student_id'=>$student['id'],'status'=>2,'class_id'=>$vv['id']])->count();
                                    if($num > 0){
                                        $vv['is_buy'] = 1;
                                    }else{
                                        $vv['is_buy'] = 0;
                                    }
                                }else{
                                    $vv['is_buy'] = 0;
                                }
                            }
                        }

                } else {
                        //未登录显示主校自己的课程
                        //自增科目
                        $subject2 = Subject::select('id', 'subject_name as name')
                        ->where(['is_del' => 0,'parent_id' => 0,"school_id" => 30])
                        ->get()->toArray();

                        //授权科目
                        $subject1 = CourseRefSubject::join("ld_course_subject","ld_course_ref_subject.parent_id","=","ld_course_subject.id")
                        ->select('ld_course_subject.id', 'subject_name as name')
                        ->where(['ld_course_subject.is_del' => 0,'ld_course_subject.parent_id' => 0,'to_school_id'=>30])
                        ->get()->toArray();
                        $subject = array_merge($subject1,$subject2);
                        $subject = array_unique($subject,SORT_REGULAR);
                        $subject = array_slice($subject,0,5);
                        $lessons = [];
                        foreach($subject as $k =>$v){

                            $lesson = Lesson::join("ld_course_subject","ld_course_subject.id","=","ld_course.parent_id")
                            ->select('ld_course.id', 'ld_course.title', 'ld_course.cover', 'ld_course.buy_num', 'ld_course.pricing as old_price', 'ld_course.sale_price as favorable_price')
                            ->where(['ld_course.is_del' => 0,'ld_course.school_id' => 30, 'ld_course.is_recommend' => 1, 'ld_course.status' => 1,'ld_course.parent_id' => $v['id']])
                            ->get();
                            $lesson_school = CourseSchool::join("ld_course_subject","ld_course_subject.id","=","ld_course_school.parent_id")
                            ->select('ld_course_school.id as course_id', 'ld_course_school.course_id as id','ld_course_school.title', 'ld_course_school.cover', 'ld_course_school.buy_num', 'ld_course_school.pricing as old_price', 'ld_course_school.sale_price as favorable_price')
                            ->where(['ld_course_school.is_del' => 0,'ld_course_school.to_school_id' => 30, 'ld_course_school.is_recommend' => 1, 'ld_course_school.status' => 1,'ld_course_school.parent_id' => $v['id']])
                            ->get();

                            if(!empty($lesson->toArray())){
                                $arr = [
                                    'subject' => $v,
                                    'lesson' => $lesson,
                                ];
                                $lessons[] = $arr;
                            }

                            if(!empty($lesson_school->toArray())){
                                $arr = [
                                    'subject' => $v,
                                    'lesson' => $lesson_school,
                                ];
                                $lessons[] = $arr;
                            }
                        }
                        foreach($lessons as $k => $v){

                            foreach($v['lesson'] as $kk => $vv){

                                $token = isset($_REQUEST['user_token']) ? $_REQUEST['user_token'] : 0;
                                $student = Student::where('token', $token)->first();
                                //购买人数  基数加真是购买人数
                                $vv['buy_num'] =  Order::where(['oa_status'=>1,'class_id'=>$vv['id']])->count() + $vv['buy_num'];
                                if(!empty($student)){
                                    $num = Order::where(['student_id'=>$student['id'],'status'=>2,'class_id'=>$vv['id']])->count();
                                    if($num > 0){
                                        $vv['is_buy'] = 1;
                                    }else{
                                        $vv['is_buy'] = 0;
                                    }
                                }else{
                                    $vv['is_buy'] = 0;
                                }
                            }
                        }
                    }
                return $this->response($lessons);
        } catch (\Exception $ex) {
            return $this->response($ex->getMessage());
        }
    }

    /**
     * 获取关于我们设置
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAbout(Request $request){
        //获取提交的参数
        try{
            //获取请求的平台端
            $platform = verifyPlat() ? verifyPlat() : 'pc';
            //获取用户token值
            $token = $request->input('user_token');
            //hash中token赋值
            $tokenKey   = "user:regtoken:".$platform.":".$token;
            //判断token值是否合法
            $redisToken = Redis::hLen($tokenKey);
            if($redisToken && $redisToken > 0) {
                //解析json获取用户详情信息
                $jsonInfo = Redis::hGetAll($redisToken);
                $schoolId = $jsonInfo['school_id'];
            }else{
                $schoolId = 30;
            }
            $aboutConfig = SchoolConfig::query()
                ->where('school_id', $schoolId)
                ->value('about_config');

            if (empty($aboutConfig)) {
                $aboutConfig = '';
            }
            return response()->json(['code'=>200,'msg'=>'Success','data'=> ['data' => $aboutConfig]]);

        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


}
