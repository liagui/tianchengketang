<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseAgreement;
use App\Models\Lesson;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\Couresmaterial;
use App\Models\CourseLivesResource;
use App\Models\Live;
use App\Models\Coureschapters;
use App\Models\LiveClass;
use App\Models\LiveChild;
use App\Models\Collection;
use App\Models\Comment;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Http\Request;
use App\Tools\MTCloud;
use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Validator;

class LessonController extends Controller {

    /**
     * @param  课程列表
     * @param  current_count   count
     * @param  author  zzk
     * @param  ctime   2020/7/3
     * return  array
     */
    public function index(Request $request){
        $pagesize = $request->input('pagesize') ?: 15;
        $page     = $request->input('page') ?: 1;
        $offset   = ($page - 1) * $pagesize;
        $parent_id = $request->input('subject_id') ?: 0;
        $child_id = $request->input('child_id') ?: 0;
        if($parent_id == 0 && $child_id == 0){
            $subjectId = 0;
        }elseif($parent_id != 0 && $child_id == 0){
            $subjectId = $parent_id;
        }elseif($parent_id != 0 && $child_id != 0){
            $subjectId = $child_id;
        }elseif($parent_id == 0 && $child_id != 0){
            $subjectId = $parent_id;
        }
        $keyWord = $request->input('keyword') ?: 0;
        $method = $request->input('method_id') ?: 0;
        $sort = $request->input('sort_id') ?: 0;
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
            $json_info = Redis::hGetAll($token_key); //获取请求的平台端
                if($sort == 0){
                    $sort_name = 'ld_course.create_at';
                }elseif($sort == 1){
                    $sort_name = 'ld_course.watch_num';
                }elseif($sort == 2){
                    $sort_name = 'ld_course.pricing';
                }elseif($sort == 3){
                    $sort_name = 'ld_course.pricing';
                }
                $where['ld_course.is_del'] = 0;
                $where['ld_course.status'] = 1;
                $where['ld_course_method.is_del'] = 0;
                $where['ld_course.school_id'] = $json_info['school_id'];
                if($parent_id > 0){
                    $where['ld_course.parent_id'] = $parent_id;
                }
                if($child_id > 0){
                    $where['ld_course.child_id'] = $child_id;
                }
                if($method > 0){
                    $where['ld_course_method.method_id'] = $method;
                }
                if(!empty($keyWord)){
                    $keyWord = "%$keyWord%";
                }
                $sort_type = $request->input('sort_type') ?: 'asc';
                $data_list =  Lesson::join("ld_course_subject","ld_course_subject.id","=","ld_course.parent_id")
                        ->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")
                        ->select('ld_course.id', 'ld_course.admin_id','ld_course.child_id','ld_course.parent_id', 'ld_course.title', 'ld_course.cover', 'ld_course.pricing as price', 'ld_course.sale_price as favorable_price','ld_course.buy_num','ld_course.is_del','ld_course.status','ld_course.watch_num','ld_course.keywords','ld_course_subject.subject_name')->where(function($query) use ($where){
                            $query->where($where);
                        })->where(function($query) use ($keyWord){
                            if(!empty($keyWord)){
                                $query->where('ld_course.title', 'like', $keyWord);
                                $query->orWhere('ld_course.keywords', 'like', $keyWord);
                            }})
                        ->orderBy($sort_name, $sort_type)
                        ->groupBy("ld_course.id")
                        ->get()->toArray();
                foreach($data_list as $k => &$v){
                    //二级分类
                    $res = DB::table('ld_course_subject')->select('subject_name')->where(['id'=>$v['child_id']])->first();
                    if(!empty($res)){
                        $v['subject_child_name']   = $res->subject_name;
                    }else{
                        $v['subject_child_name']   = "无二级分类";
                    }
                    //购买数量
                    $v['buy_num'] =  Order::where(['oa_status'=>1,'class_id'=>$v['id']])->count() + $v['buy_num'];
                    //获取授课模式
                    $v['methods'] = DB::table('ld_course')->select('method_id as id')->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")->where(['ld_course.id'=>$v['id'],"ld_course_method.is_del"=>0])->get();
                }
                if($sort == 0){
                    $sort_name = 'ld_course_school.create_at';
                }elseif($sort == 1){
                    $sort_name = 'ld_course_school.watch_num';
                }elseif($sort == 2){
                    $sort_name = 'ld_course_school.pricing';
                }elseif($sort == 3){
                    $sort_name = 'ld_course_school.pricing';
                }
                $where_two['ld_course_school.is_del'] = 0;
                $where_two['ld_course_school.status'] = 1;
                $where_two['ld_course_school.to_school_id'] = $json_info['school_id'];
                $where_two['ld_course_method.is_del'] = 0;
                if($parent_id > 0){
                    $where_two['ld_course_school.parent_id'] = $parent_id;
                }
                if($child_id > 0){
                    $where_two['ld_course_school.child_id'] = $child_id;
                }
                if($method > 0){
                    $where_two['ld_course_method.method_id'] = $method;
                }
                if(!empty($keyWord)){
                    $keyWord = "%$keyWord%";
                }
                $sort_type = $request->input('sort_type') ?: 'asc';
                $data_list_accredit = CourseSchool::join("ld_course_subject","ld_course_subject.id","=","ld_course_school.parent_id")
                        ->join("ld_course_method","ld_course_school.course_id","=","ld_course_method.course_id")
                        ->select('ld_course_school.to_school_id','ld_course_school.course_id as id', 'ld_course_school.admin_id','ld_course_school.child_id','ld_course_school.parent_id', 'ld_course_school.title', 'ld_course_school.cover', 'ld_course_school.pricing as price', 'ld_course_school.sale_price as favorable_price','ld_course_school.buy_num','ld_course_school.is_del','ld_course_school.status','ld_course_school.watch_num','ld_course_school.keywords','ld_course_subject.subject_name')->where(function($query) use ($where_two){
                            $query->where($where_two);
                        })->where(function($query) use ($keyWord){
                            if(!empty($keyWord)){
                                $query->where('ld_course_school.title', 'like', $keyWord);
                                $query->orWhere('ld_course_school.keywords', 'like', $keyWord);
                            }
                        })->groupBy("ld_course_school.id")->get()->toArray();
                foreach($data_list_accredit as $k => &$v){
                    //二级分类
                    $res = DB::table('ld_course_subject')->select('subject_name')->where(['id'=>$v['child_id']])->first();
                    if(!empty($res)){
                        $v['subject_child_name']   = $res->subject_name;
                    }else{
                        $v['subject_child_name']   = "无二级分类";
                    }
                    //购买数量
                    $v['buy_num'] =  Order::where(['oa_status'=>1,'class_id'=>$v['id']])->count() + $v['buy_num'];
                    //获取授课模式
                    $v['methods'] = DB::table('ld_course')->select('method_id as id')->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")->where(['ld_course.id'=>$v['id'],"ld_course_method.is_del"=>0])->get();
                }
                $data_list = array_merge($data_list,$data_list_accredit);
                //数据分页
                $start =($page - 1) * $pagesize;
                $limit_s= $start + $pagesize;
                $data = [];
                for ($i = $start; $i < $limit_s; $i++) {
                    if (!empty($data_list[$i])) {
                            array_push($data, $data_list[$i]);
                        }
                }
            }else{
                if($sort == 0){
                    $sort_name = 'ld_course.create_at';
                }elseif($sort == 1){
                    $sort_name = 'ld_course.watch_num';
                }elseif($sort == 2){
                    $sort_name = 'ld_course.pricing';
                }elseif($sort == 3){
                    $sort_name = 'ld_course.pricing';
                }
                $where['ld_course.is_del'] = 0;
                $where['ld_course.status'] = 1;
                $where['ld_course_method.is_del'] = 0;
                $where['ld_course.school_id'] = 30;
                if($parent_id > 0){
                    $where['ld_course.parent_id'] = $parent_id;
                }
                if($child_id > 0){
                    $where['ld_course.child_id'] = $child_id;
                }
                if($method > 0){
                    $where['ld_course_method.method_id'] = $method;
                }
                if(!empty($keyWord)){
                    $keyWord = "%$keyWord%";
                }
                $sort_type = $request->input('sort_type') ?: 'asc';
                $data_list =  Lesson::join("ld_course_subject","ld_course_subject.id","=","ld_course.parent_id")
                        ->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")
                        ->select('ld_course.id', 'ld_course.admin_id','ld_course.child_id','ld_course.parent_id', 'ld_course.title', 'ld_course.cover', 'ld_course.pricing as price', 'ld_course.sale_price as favorable_price','ld_course.buy_num','ld_course.is_del','ld_course.status','ld_course.watch_num','ld_course.keywords','ld_course_subject.subject_name')->where(function($query) use ($where){
                            $query->where($where);
                        })->where(function($query) use ($keyWord){
                            if(!empty($keyWord)){
                                $query->where('ld_course.title', 'like', $keyWord);
                                $query->orWhere('ld_course.keywords', 'like', $keyWord);
                            }})
                        ->orderBy($sort_name, $sort_type)
                        ->groupBy("ld_course.id")
                        ->get()->toArray();
                foreach($data_list as $k => &$v){
                    //二级分类
                    $res = DB::table('ld_course_subject')->select('subject_name')->where(['id'=>$v['child_id']])->first();
                    if(!empty($res)){
                        $v['subject_child_name']   = $res->subject_name;
                    }else{
                        $v['subject_child_name']   = "无二级分类";
                    }
                    //购买数量
                    $v['buy_num'] =  Order::where(['oa_status'=>1,'class_id'=>$v['id']])->count() + $v['buy_num'];
                    //获取授课模式
                    $v['methods'] = DB::table('ld_course')->select('method_id as id')->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")->where(['ld_course.id'=>$v['id'],"ld_course_method.is_del"=>0])->get();
                }
                if($sort == 0){
                    $sort_name = 'ld_course_school.create_at';
                }elseif($sort == 1){
                    $sort_name = 'ld_course_school.watch_num';
                }elseif($sort == 2){
                    $sort_name = 'ld_course_school.pricing';
                }elseif($sort == 3){
                    $sort_name = 'ld_course_school.pricing';
                }
                $where_two['ld_course_school.is_del'] = 0;
                $where_two['ld_course_school.status'] = 1;
                $where_two['ld_course_school.to_school_id'] = 30;
                $where_two['ld_course_method.is_del'] = 0;
                if($parent_id > 0){
                    $where_two['ld_course_school.parent_id'] = $parent_id;
                }
                if($child_id > 0){
                    $where_two['ld_course_school.child_id'] = $child_id;
                }
                if($method > 0){
                    $where_two['ld_course_method.method_id'] = $method;
                }
                if(!empty($keyWord)){
                    $keyWord = "%$keyWord%";
                }
                $sort_type = $request->input('sort_type') ?: 'asc';
                $data_list_accredit = CourseSchool::join("ld_course_subject","ld_course_subject.id","=","ld_course_school.parent_id")
                        ->join("ld_course_method","ld_course_school.course_id","=","ld_course_method.course_id")
                        ->select('ld_course_school.to_school_id','ld_course_school.course_id as id', 'ld_course_school.admin_id','ld_course_school.child_id','ld_course_school.parent_id', 'ld_course_school.title', 'ld_course_school.cover', 'ld_course_school.pricing as price', 'ld_course_school.sale_price as favorable_price','ld_course_school.buy_num','ld_course_school.is_del','ld_course_school.status','ld_course_school.watch_num','ld_course_school.keywords','ld_course_subject.subject_name')->where(function($query) use ($where_two){
                            $query->where($where_two);
                        })->where(function($query) use ($keyWord){
                            if(!empty($keyWord)){
                                $query->where('ld_course_school.title', 'like', $keyWord);
                                $query->orWhere('ld_course_school.keywords', 'like', $keyWord);
                            }
                        })->groupBy("ld_course_school.id")->get()->toArray();
                foreach($data_list_accredit as $k => &$v){
                    //二级分类
                    $res = DB::table('ld_course_subject')->select('subject_name')->where(['id'=>$v['child_id']])->first();
                    if(!empty($res)){
                        $v['subject_child_name']   = $res->subject_name;
                    }else{
                        $v['subject_child_name']   = "无二级分类";
                    }
                    //购买数量
                    $v['buy_num'] =  Order::where(['oa_status'=>1,'class_id'=>$v['id']])->count() + $v['buy_num'];
                    //获取授课模式
                    $v['methods'] = DB::table('ld_course')->select('method_id as id')->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")->where(['ld_course.id'=>$v['id'],"ld_course_method.is_del"=>0])->get();
                }
                $data_list = array_merge($data_list,$data_list_accredit);
                //数据分页
                $start =($page - 1) * $pagesize;
                $limit_s= $start + $pagesize;
                $data = [];
                for ($i = $start; $i < $limit_s; $i++) {
                    if (!empty($data_list[$i])) {
                            array_push($data, $data_list[$i]);
                        }
                }
            }
        foreach($data as $k => $v){
            foreach($v['methods'] as $kk => $vv){
                if($vv->id == 1){
                    $vv->name = "直播";
                }else if($vv->id == 2){
                    $vv->name = "录播";
                }else{
                    $vv->name = "其他";
                }
            }
        }


        $total = count($data);
        $lessons = $data;
        $data = [
            'page_data' => $lessons,
            'total' => $total,
        ];
        return $this->response($data);
    }


    /**
     * @param  课程详情
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
                $lesson = Lesson::select("*","pricing as price","sale_price as favorable_price","expiry as ttl","introduce as introduction","describe as description")->where(["school_id"=>$json_info['school_id'],'is_del'=>0])->find($request->input('id'));
                if(empty($lesson)){
                    //查询授权课程id
                    $lesson = CourseSchool::select("*","pricing as price","sale_price as favorable_price","expiry as ttl","introduce as introduction","describe as description")->where(["to_school_id"=>$json_info['school_id'],'is_del'=>0])->where("course_id",$request->input('id'))->first();
                    if(empty($lesson)){
                        return $this->response('课程不存在', 404);
                    }
                    //is_collection   是否收藏
                    $is_collection = Collection::where(['student_id'=>$json_info['user_id'],'is_del'=>0,'lesson_id'=>$lesson['id']])->first();
                    $is_collection1 = Collection::where(['student_id'=>$json_info['user_id'],'is_del'=>0,'lesson_id'=>$lesson['course_id']])->first();
                    if($is_collection || $is_collection1){
                        $lesson['is_collection'] = 1;
                    }else{
                        $lesson['is_collection'] = 0;
                    }
                    //is_buy  是否购买
                    $is_buy = Order::where(['student_id'=>$json_info['user_id'],'status'=>2,'oa_status'=>1,'class_id'=>$lesson['id']])->first();
                    if($is_buy){
                        $lesson['is_buy'] = 1;
                    }else{
                        $lesson['is_buy'] = 0;
                    }
                    //课程资料
                    //获取该课程下所有的资料   直播班号 课次
                    $lesson['url'] = CourseLivesResource::join("ld_course_livecast_resource","ld_course_live_resource.resource_id","=","ld_course_livecast_resource.id")
                    ->join("ld_course_shift_no","ld_course_livecast_resource.id","=","ld_course_shift_no.resource_id")
                    ->join("ld_course_class_number","ld_course_shift_no.id","=","ld_course_class_number.shift_no_id")
                    ->select("ld_course_shift_no.id as shift_no_id","ld_course_class_number.id as class_id")
                    ->where(["ld_course_live_resource.course_id"=>$lesson['course_id'],"ld_course_live_resource.is_del"=>0])->get();
                    //该课程下直播课时
                    $lesson['class_num'] = CourseLivesResource::join("ld_course_livecast_resource","ld_course_live_resource.resource_id","=","ld_course_livecast_resource.id")
                    ->join("ld_course_shift_no","ld_course_livecast_resource.id","=","ld_course_shift_no.resource_id")
                    ->join("ld_course_class_number","ld_course_shift_no.id","=","ld_course_class_number.shift_no_id")
                    ->select("ld_course_shift_no.id as shift_no_id","ld_course_class_number.id as class_id")
                    ->where(["ld_course_live_resource.course_id"=>$lesson['course_id'],"ld_course_class_number.status"=>1,"ld_course_class_number.is_del"=>0])->sum("ld_course_class_number.class_hour");
                    //课程资料
                    $arr = [];
                    $newhello = [];
                    $ziyuan = [];
                    //录播小节
                    //获取该课程下所有录播的资料   小节
                    //录播资料
                    $jie = Coureschapters::where(['course_id'=>$lesson['course_id'],'is_del'=>0])->where('parent_id','>',0)->get();
                    if(!empty($jie)){
                        foreach ($jie as $k=>$v){
                            $ziliao = Couresmaterial::select('material_name as name','material_url  as url','material_size as size','type')->where(['parent_id'=>$v['id'],'is_del'=>0,'mold'=>1])->get();
                            if(!empty($ziliao)){
                                foreach ($ziliao as $kss=>$vss){
                                    $ziyuan[] = $vss;
                                }
                            }
                        }
                    }
                    foreach($lesson['url'] as $k => $v){
                        $class = Couresmaterial::select("material_name as name","material_size as size","material_url as url","type","parent_id","mold")->where(["parent_id"=>$v['shift_no_id'],"mold"=>2,'is_del'=>0])->get()->toArray();
                        if(!empty($class)){
                            array_push($arr,$class);
                        }
                        $child = Couresmaterial::select("material_name as name","material_size as size","material_url as url","type","parent_id","mold")->where(["parent_id"=>$v['class_id'],"mold"=>3,'is_del'=>0])->get()->toArray();
                        if(!empty($child)){
                            array_push($arr,$child);
                        }
                    }
                    $k = 0;
                    foreach ($arr as $key => $val) {
                        foreach ($val as $key2 => $val2) {
                            $newhello[$k]['name'] = $val2['name'];
                            $newhello[$k]['size'] = $val2['size'];
                            $newhello[$k]['url'] = $val2['url'];
                            $newhello[$k]['type'] = $val2['type'];
                            $newhello[$k]['parent_id'] = $val2['parent_id'];
                            $newhello[$k]['mold'] = $val2['mold'];
                            $k++;
                        }
                    }
                    if(empty($ziyuan)){
                        $ziyuan = [];
                    }
                    if(empty($newhello)){
                        $newhello = [];
                    }
                    $lesson['url'] = array_merge($newhello,$ziyuan);
                    //授权课程
                    CourseSchool::where('course_id', $request->input('id'))->update(['watch_num' => DB::raw('watch_num + 1'),'update_at'=>date('Y-m-d H:i:s')]);
                }else{
                    //is_collection   是否收藏
                    $is_collection = Collection::where(['student_id'=>$json_info['user_id'],'is_del'=>0,'lesson_id'=>$lesson['id']])->first();
                    if($is_collection){
                        $lesson['is_collection'] = 1;
                    }else{
                        $lesson['is_collection'] = 0;
                    }
                    //is_buy  是否购买
                    $is_buy = Order::where(['student_id'=>$json_info['user_id'],'status'=>2,'oa_status'=>1,'class_id'=>$lesson['id']])->first();
                    if($is_buy){
                        $lesson['is_buy'] = 1;
                    }else{
                        $lesson['is_buy'] = 0;
                    }
                    //课程资料
                    //获取该课程下所有直播的资料   直播班号 课次
                    $lesson['url'] = CourseLivesResource::join("ld_course_livecast_resource","ld_course_live_resource.resource_id","=","ld_course_livecast_resource.id")
                    ->join("ld_course_shift_no","ld_course_livecast_resource.id","=","ld_course_shift_no.resource_id")
                    ->join("ld_course_class_number","ld_course_shift_no.id","=","ld_course_class_number.shift_no_id")
                    ->select("ld_course_shift_no.id as shift_no_id","ld_course_class_number.id as class_id")
                    ->where(["ld_course_live_resource.course_id"=>$lesson['id'],"ld_course_live_resource.is_del"=>0])->get();
                    //该课程下直播课时
                    $lesson['class_num'] = CourseLivesResource::join("ld_course_livecast_resource","ld_course_live_resource.resource_id","=","ld_course_livecast_resource.id")
                    ->join("ld_course_shift_no","ld_course_livecast_resource.id","=","ld_course_shift_no.resource_id")
                    ->join("ld_course_class_number","ld_course_shift_no.id","=","ld_course_class_number.shift_no_id")
                    ->select("ld_course_shift_no.id as shift_no_id","ld_course_class_number.id as class_id")
                    ->where(["ld_course_live_resource.course_id"=>$lesson['id'],"ld_course_class_number.status"=>1,"ld_course_class_number.is_del"=>0])->sum("ld_course_class_number.class_hour");
                    //课程资料
                    $arr = [];
                    $newhello = [];
                    $ziyuan = [];
                    //录播小节
                    //获取该课程下所有录播的资料   小节
                    //录播资料
                    $jie = Coureschapters::where(['course_id'=>$lesson['id'],'is_del'=>0])->where('parent_id','>',0)->get();
                    if(!empty($jie)){
                        foreach ($jie as $k=>$v){
                            $ziliao = Couresmaterial::select('material_name as name','material_url  as url','material_size as size','type')->where(['parent_id'=>$v['id'],'is_del'=>0,'mold'=>1])->get();
                            if(!empty($ziliao)){
                                foreach ($ziliao as $kss=>$vss){
                                    $ziyuan[] = $vss;
                                }
                            }
                        }
                    }
                    //直播资料
                    foreach($lesson['url'] as $k => $v){
                        $class = Couresmaterial::select("material_name as name","material_size as size","material_url as url","type","parent_id","mold")->where(["parent_id"=>$v['shift_no_id'],"mold"=>2,'is_del'=>0])->get()->toArray();
                        if(!empty($class)){
                            array_push($arr,$class);
                        }
                        $child = Couresmaterial::select("material_name as name","material_size as size","material_url as url","type","parent_id","mold")->where(["parent_id"=>$v['class_id'],"mold"=>3,'is_del'=>0])->get()->toArray();
                        if(!empty($child)){
                            array_push($arr,$child);
                        }
                    }
                    $k = 0;
                    foreach ($arr as $key => $val) {
                        foreach ($val as $key2 => $val2) {
                            $newhello[$k]['name'] = $val2['name'];
                            $newhello[$k]['size'] = $val2['size'];
                            $newhello[$k]['url'] = $val2['url'];
                            $newhello[$k]['type'] = $val2['type'];
                            $newhello[$k]['parent_id'] = $val2['parent_id'];
                            $newhello[$k]['mold'] = $val2['mold'];
                            $k++;
                        }
                    }
                    if(empty($ziyuan)){
                        $ziyuan = [];
                    }
                    if(empty($newhello)){
                        $newhello = [];
                    }
                    $lesson['url'] = array_merge($newhello,$ziyuan);
                }
        }else{
            $lesson = Lesson::select("*","pricing as price","sale_price as favorable_price","expiry as ttl","introduce as introduction","describe as description")->where("school_id",1)->find($request->input('id'));
            if(empty($lesson)){
                return $this->response('课程不存在', 404);
            }
            //获取该课程下所有的资料   直播班号 课次
            $lesson['url'] = CourseLivesResource::join("ld_course_livecast_resource","ld_course_live_resource.resource_id","=","ld_course_livecast_resource.id")
            ->join("ld_course_shift_no","ld_course_livecast_resource.id","=","ld_course_shift_no.resource_id")
            ->join("ld_course_class_number","ld_course_shift_no.id","=","ld_course_class_number.shift_no_id")
            ->select("ld_course_shift_no.id as shift_no_id","ld_course_class_number.id as class_id")
            ->where(["ld_course_live_resource.course_id"=>$lesson['id'],"ld_course_live_resource.is_del"=>0])->get();
            //该课程下直播课时
            $lesson['class_num'] = CourseLivesResource::join("ld_course_livecast_resource","ld_course_live_resource.resource_id","=","ld_course_livecast_resource.id")
            ->join("ld_course_shift_no","ld_course_livecast_resource.id","=","ld_course_shift_no.resource_id")
            ->join("ld_course_class_number","ld_course_shift_no.id","=","ld_course_class_number.shift_no_id")
            ->select("ld_course_shift_no.id as shift_no_id","ld_course_class_number.id as class_id")
            ->where(["ld_course_live_resource.course_id"=>$lesson['id'],"ld_course_class_number.status"=>1,"ld_course_class_number.is_del"=>0])->sum("ld_course_class_number.class_hour");
            $arr = [];
            $newhello = [];
            $ziyuan = [];
            //录播小节
            //获取该课程下所有录播的资料   小节
            //录播资料
            $jie = Coureschapters::where(['course_id'=>$lesson['id'],'is_del'=>0])->where('parent_id','>',0)->get();
            if(!empty($jie)){
                foreach ($jie as $k=>$v){
                    $ziliao = Couresmaterial::select('material_name as name','material_url  as url','material_size as size','type')->where(['parent_id'=>$v['id'],'is_del'=>0,'mold'=>1])->get();
                    if(!empty($ziliao)){
                        foreach ($ziliao as $kss=>$vss){
                            $ziyuan[] = $vss;
                        }
                    }
                }
            }
            foreach($lesson['url'] as $k => $v){
                $class = Couresmaterial::select("material_name as name","material_size as size","material_url as url","type","parent_id","mold")->where(["parent_id"=>$v['shift_no_id'],"mold"=>2,'is_del'=>0])->get()->toArray();
                if(!empty($class)){
                    array_push($arr,$class);
                }
                $child = Couresmaterial::select("material_name as name","material_size as size","material_url as url","type","parent_id","mold")->where(["parent_id"=>$v['class_id'],"mold"=>3,'is_del'=>0])->get()->toArray();
                if(!empty($child)){
                    array_push($arr,$child);
                }
            }
            $k = 0;
            foreach ($arr as $key => $val) {
                foreach ($val as $key2 => $val2) {
                    $newhello[$k]['name'] = $val2['name'];
                    $newhello[$k]['size'] = $val2['size'];
                    $newhello[$k]['url'] = $val2['url'];
                    $newhello[$k]['type'] = $val2['type'];
                    $newhello[$k]['parent_id'] = $val2['parent_id'];
                    $newhello[$k]['mold'] = $val2['mold'];
                    $k++;
                }
            }
            if(empty($ziyuan)){
                $ziyuan = [];
            }
            if(empty($newhello)){
                $newhello = [];
            }
            $lesson['url'] = array_merge($newhello,$ziyuan);
            $lesson['is_collection'] = 0;
            $lesson['is_buy'] = 0;
            //学习人数   基数+订单数

        }
        $lesson['class_num'] = "".round($lesson['class_num'])."";
        $ordernum = Order::where(['class_id' => $lesson['id'], 'status' => 2, 'oa_status' => 1])->count();
        $lesson['buy_num'] = $lesson['buy_num'] + $ordernum;
        //自增课程
        Lesson::where('id', $request->input('id'))->update(['watch_num' => DB::raw('watch_num + 1'),'update_at'=>date('Y-m-d H:i:s')]);
        return $this->response($lesson);
    }


    /**
     * @param  公开课
     * @param  author  zzk
     * @param  ctime   2020/6/16
     * return  array
     */
    public function OpenCourse(Request $request) {

        $course_id = $request->input('course_id');
        $student_id = self::$accept_data['user_info']['user_id'];
        $nickname = self::$accept_data['user_info']['nickname'];
        $school_id = self::$accept_data['user_info']['school_id'];
        $phone = self::$accept_data['user_info']['phone'];
        //
        $platform = verifyPlat() ? verifyPlat() : 'pc';
        $user_token = $platform.":".$request['user_token'];

        if(empty($course_id)){
            return $this->response('course_id错误', 202);
        }
        if(empty($student_id)){
            return $this->response('student_id不存在', 202);
        }
        if(empty($nickname)){
            return $this->response('nickname不存在', 202);
        }
        //查询公开课course_id_ht
        $res = DB::table('ld_course_open_live_childs')->select("course_id","status",
            "partner_id", "bid","course_id","zhubo_key","admin_key","user_key","playback","playbackUrl")->where("lesson_id",$course_id)->first();
        if (empty($res)) {
            return $this->response('course_id不存在', 202);
        }

        $course_id_ht = $res->course_id;
        //@todo 处理CC的返回数据
        if ($res->bid > 0) {

            //欢拓
            $MTCloud = new MTCloud();

            if($res->status == 2){
                $res_info = $MTCloud->courseAccess($course_id_ht, $student_id, $nickname, 'user');
                $res_ret['data']['is_live'] = 1;
                $res_ret['data']['mt_live_info'] = $res_info;
                $res_ret['data']['type'] = "live";

            }else{
                $res_info = $MTCloud->courseAccessPlayback($course_id_ht, $student_id, $nickname, 'user');
                $res_ret['data']['is_live'] = 0;
                $res_ret['data']['course_id'] = $course_id;
                if($res_info['code'] == '1203'){
                    return $this->response('该课程没有回放记录', 500);
                }
                if(!array_key_exists('code', $res_info) && !$res_info['code'] == 0){
                    Log::error('进入直播间失败:'.json_encode($res_info));
                    return $this->response('进入直播间失败', 500);
                }
                $res_ret['data']['mt_live_info'] = $res_info;
                $res_ret['data']['type'] = "live";

            }
            $res_ret['data']['service'] = 'MT';


        } else {

            //CC
            $CCCloud = new CCCloud();
            if($res->status == 2 or $res->status == 1 ){

                $viewercustominfo= array(
                    "school_id"=>$school_id,
                    "id" => $student_id,
                    "nickname" => $nickname,
                    'phone' =>$phone
                );
                //  传递的时候 user_key 变成 用户传递的 user_token
                // $res = $CCCloud->get_room_live_code($course_id_ht, $school_id, $nickname, $res ->user_key,$viewercustominfo);
                $res_info = $CCCloud->get_room_live_code($course_id_ht, $school_id, $nickname, $user_token,$viewercustominfo);
                $res_ret['data']['is_live'] = 1;
                $res_ret['data']['cc_live_info'] = $res_info['data']['cc_info'];
                $res_ret['data']['type'] = "live";

            }else{
                $viewercustominfo= array(
                    "school_id"=>$school_id,
                    "id" => $student_id,
                    "nickname" => $nickname,
                    'phone' =>$phone
                );

                // $res = $CCCloud -> get_room_live_recode_code($course_id_ht,$school_id, $nickname, $res ->user_key, $viewercustominfo);
                $res_info = $CCCloud -> get_room_live_recode_code($course_id_ht,$school_id, $nickname, $user_token, $viewercustominfo);
                $res_ret['data']['is_live'] = 0;
                $res_ret['data']['cc_live_info'] = $res_info['data']['cc_info'];
                $res_ret['data']['type'] = "recode";

                if($res_info['code'] == '1203'){
                    return $this->response('该课程没有回放记录', 500);
                }
                if(!array_key_exists('code', $res_info) && !$res_info['code'] == 0){
                    Log::error('进入直播间失败:'.json_encode($res_info));
                    return $this->response('进入直播间失败', 500);
                }
            }

            $res_ret['data']['service'] = 'CC';

        }
        // 检查一下默认的数据是否存在

        if(!isset($res_ret['data']['cc_vod_info'])){
            $res_ret['data']['cc_vod_info'] = array(
                "userid" => "",
                "videoid" => "",
                "customid" => "",
            );
        }

        if(!isset($res_ret['data']['cc_live_info'])){
            $res_ret['data']['cc_live_info'] = array(
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

        if(!isset($res_ret['data']['mt_live_info'])){
            $res_ret['data']['mt_live_info']=array(
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

        if(isset($res_ret['data']['mt_live_info'])){
            $res_ret['data'] = array_merge($res_ret['data'],$res_ret['data']['mt_live_info']);
        }


        /** 结束兼容性代码 */
        $res_ret['data']['course_id'] = $course_id;

        return $this->response($res_ret['data']);
    }


    /**
     * 获取学生课程协议内容
     * @return array
     */
    public function getCourseAgreement()
    {
        $studentId = self::$accept_data['user_info']['user_id'];;
        $schoolId = self::$accept_data['user_info']['school_id'];
        $courseId = array_get($this->data, 'course_id', 0);
        $stepType = array_get($this->data, 'step_type', 0);
        $nature = array_get($this->data, 'nature', 0);

        //判断基础数据
        if (empty($studentId) || empty($schoolId) || empty($courseId) || ! in_array($stepType, [1, 2]) || ! in_array($nature, [0, 1])) {
            return ['code' => 204, 'msg' => '参数错误，请核实'];
        }

        return CourseAgreement::getCourseAgreement($schoolId, $studentId, $courseId, $nature, $stepType);

    }
    /**
     * 获取学生课程协议内容
     * @return array
     */
    public function setCourseAgreement()
    {
        $studentId = self::$accept_data['user_info']['user_id'];;
        $schoolId = self::$accept_data['user_info']['school_id'];
        $courseId = array_get($this->data, 'course_id', 0);
        $stepType = array_get($this->data, 'step_type', 0);
        $nature = array_get($this->data, 'nature', 0);

        //判断基础数据
        if (empty($studentId) || empty($schoolId) || empty($courseId) || ! in_array($stepType, [1, 2]) || ! in_array($nature, [0, 1])) {
            return ['code' => 204, 'msg' => '参数错误，请核实'];
        }

        return CourseAgreement::setCourseAgreement($schoolId, $studentId, $courseId, $nature, $stepType);

    }
    /**
     * 添加课程评论
     * @return array
     */
    public function commentAdd(Request $request)
    {

            $course_id = $request->input('course_id');
            $content = $request->input('content');
            $score = $request->input('score');
            $student_id = self::$accept_data['user_info']['user_id'];
            $schoolId = self::$accept_data['user_info']['school_id'];
            // $nature = $request->input('nature');
            // if(!isset($nature) || (!in_array($nature,[0,1]))){
            //     $nature = 1;
            // }
            //验证参数
            if(!isset($course_id)||empty($course_id)){
                return response()->json(['code' => 201, 'msg' => '课程id为空']);
            }
            if(!isset($content)||empty($content)){
                return response()->json(['code' => 201, 'msg' => '课程评论内容为空']);
            }
            // if(!isset($nature) || (!in_array($nature,[0,1]))){
            //     return response()->json(['code' => 201, 'msg' => '课程类型有误']);
            // }
            //一分钟内只能提交两次
                $time = date ( "Y-m-d H:i:s" , strtotime ( "-1 minute" ));
                $data = date ( "Y-m-d H:i:s" , time());
                $list = Comment::where(['school_id'=>$schoolId,'course_id'=>$course_id,/**'nature'=>$nature,**/'uid'=>$student_id])->whereBetween('create_at',[$time,$data])->orderByDesc('create_at')->count();
                if($list>=2){
                    return response()->json(['code' => 202, 'msg' => '操作太频繁,1分钟以后再来吧']);
                }
            //获取课程名称

            $course = Lesson::where(['id'=>$course_id,'is_del'=>0,'status'=>1,"school_id"=>$schoolId])->select('title')->first();
            if(empty($course)){
                $course = CourseSchool::where(['course_id'=>$course_id,'is_del'=>0,'status'=>1,"to_school_id"=>$schoolId])->select('title')->first();
            }
            //判断课程是否存在
            if(empty($course)){
                return response()->json(['code' => 202, 'msg' => '该课程不存在']);
            }else{
                $course = $course->toArray();
            }
            //开启事务
            DB::beginTransaction();
            try {
                //拼接数据
                $add = Comment::insert([
                    'school_id'    => $schoolId,
                    'status'       => 0,
                    'course_id'    => $course_id,
                    'course_name'  => $course['title'],
                    'create_at'    => date('Y-m-d H:i:s'),
                    'content'      => addslashes($content),
                    'uid'          => $student_id,
                    'score'        => empty($score) ? 1 : $score,
                ]);
                if($add){
                    DB::commit();
                    return response()->json(['code' => 200, 'msg' => '发表评论成功,等待后台的审核']);
                }else{
                    DB::rollBack();
                    return response()->json(['code' => 203, 'msg' => '发表评论失败']);
                }
            } catch (\Exception $ex) {
                //事务回滚
                DB::rollBack();
                return ['code' => 204, 'msg' => $ex->getMessage()];
            }
    }
    /**
     * 课程评论列表
     * @return array
     */
    public function commentList(Request $request){
        try {
            $pagesize = $request->input('pagesize') ?: 15;
            $page     = $request->input('page') ?: 1;
            $offset   = ($page - 1) * $pagesize;
            $course_id = $request->input('course_id');
            // $nature = $request->input('nature');
            // if(!isset($nature) || (!in_array($nature,[0,1]))){
            //     $nature = 1;
            // }
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
                //登录显示属于分的课程
                $schoolId = $json_info['school_id'];
            }else{
                //未登录默认观看学校30
                $schoolId = 30;
            }
            //验证参数
            if(!isset($course_id)||empty($course_id)){
                return response()->json(['code' => 201, 'msg' => '课程id为空']);
            }
            // if(!isset($nature) || (!in_array($nature,[0,1]))){
            //     return response()->json(['code' => 201, 'msg' => '课程类型有误']);
            // }
            //获取总数
            $count_list = Comment::leftJoin('ld_student','ld_student.id','=','ld_comment.uid')
                ->leftJoin('ld_school','ld_school.id','=','ld_comment.school_id')
                ->where(['ld_comment.school_id' => $schoolId, 'ld_comment.course_id'=>$course_id, /**'ld_comment.nature'=>$nature,**/'ld_comment.status'=>1])
                ->count();
            //dd($a);
            //每页显示的条数
            $pagesize = isset($pagesize) && $pagesize > 0 ? $pagesize : 20;
            $page     = isset($page) && $page > 0 ? $page : 1;
            $offset   = ($page - 1) * $pagesize;

			//获取列表
            $list = Comment::leftJoin('ld_student','ld_student.id','=','ld_comment.uid')
                ->leftJoin('ld_school','ld_school.id','=','ld_comment.school_id')
                ->where(['ld_comment.school_id' => $schoolId, 'ld_comment.course_id'=>$course_id, /**'ld_comment.nature'=>$nature,**/'ld_comment.status'=>1])
                ->select('ld_comment.id','ld_comment.create_at','ld_comment.content','ld_comment.course_name','ld_comment.teacher_name','ld_comment.score','ld_comment.anonymity','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon','ld_school.name as school_name')
                ->orderByDesc('ld_comment.create_at')->offset($offset)->limit($pagesize)
                ->get()->toArray();
            foreach($list as $k=>$v){
                if($v['anonymity']==1){
                    $list[$k]['user_name'] = empty($v['real_name']) ? $v['nickname'] : $v['real_name'];
                }else{
                    $list[$k]['user_name'] = '匿名';
                }
            }
            return ['code' => 200 , 'msg' => '获取评论列表成功' , 'data' => ['list' => $list , 'total' => $count_list , 'pagesize' => $pagesize , 'page' => $page]];

        } catch (\Exception $ex) {
            return ['code' => 204, 'msg' => $ex->getMessage()];
        }
    }
    /**
     * 我的课程评论列表
     * @return array
     */
    public function MycommentList(Request $request){
    try {
            $student_id = self::$accept_data['user_info']['user_id'];
            $schoolId = self::$accept_data['user_info']['school_id'];
            $pagesize = $request->input('pagesize') ?: 15;
            $page     = $request->input('page') ?: 1;
            $offset   = ($page - 1) * $pagesize;
            //获取我的评论列表
            //获取总数
            $count_list = Comment::leftJoin('ld_student','ld_student.id','=','ld_comment.uid')
                ->leftJoin('ld_school','ld_school.id','=','ld_comment.school_id')
                ->where(['ld_comment.school_id' => $schoolId,'ld_comment.uid' => $student_id,'ld_comment.status'=>1])
                ->count();
            //每页显示的条数
            $pagesize = isset($pagesize) && $pagesize > 0 ? $pagesize : 20;
            $page     = isset($page) && $page > 0 ? $page : 1;
            $offset   = ($page - 1) * $pagesize;

			//获取列表
            $list = Comment::leftJoin('ld_student','ld_student.id','=','ld_comment.uid')
                ->leftJoin('ld_school','ld_school.id','=','ld_comment.school_id')
                ->where(['ld_comment.school_id' => $schoolId,'ld_comment.uid' => $student_id,'ld_comment.status'=>1])
                ->select('ld_comment.id','ld_comment.create_at','ld_comment.content','ld_comment.course_name','ld_comment.teacher_name','ld_comment.score','ld_comment.anonymity','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon','ld_school.name as school_name')
                ->orderByDesc('ld_comment.create_at')->offset($offset)->limit($pagesize)
                ->get()->toArray();
            return ['code' => 200 , 'msg' => '获取评论列表成功' , 'data' => ['list' => $list , 'total' => $count_list , 'pagesize' => $pagesize , 'page' => $page]];
        } catch (\Exception $ex) {
            return ['code' => 204, 'msg' => $ex->getMessage()];
        }
    }

}
