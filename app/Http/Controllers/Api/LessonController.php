<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
                $where['ld_course.school_id'] = 1;
                $where['ld_course_method.is_del'] = 0;
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
                $data =  Lesson::join("ld_course_subject","ld_course_subject.id","=","ld_course.parent_id")
                        ->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")
                        ->select('ld_course.id', 'ld_course.admin_id','ld_course.child_id','ld_course.parent_id', 'ld_course.title', 'ld_course.cover', 'ld_course.pricing as price', 'ld_course.sale_price as favorable_price','ld_course.buy_num','ld_course.is_del','ld_course.status','ld_course.watch_num','ld_course.keywords','ld_course_subject.subject_name')->where(function($query) use ($where,$keyWord){
                            $query->where($where);
                            if(!empty($keyWord)){
                                $query->where('ld_course.title', 'like', $keyWord);
                                $query->orWhere('ld_course.keywords', 'like', $keyWord);
                            }
                        })->orderBy($sort_name, $sort_type)
                        ->skip($offset)->take($pagesize)
                        ->groupBy("ld_course.id")
                        ->get();
                foreach($data as $k => $v){
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
                    ->where(["ld_course_live_resource.course_id"=>$lesson['course_id']])->get();
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
                    ->where(["ld_course_live_resource.course_id"=>$lesson['id']])->get();
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
            ->where(["ld_course_live_resource.course_id"=>$lesson['id']])->get();
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
                $res = $MTCloud->courseAccess($course_id_ht, $student_id, $nickname, 'user');
                $res['data']['is_live'] = 1;
            }else{
                $res = $MTCloud->courseAccessPlayback($course_id_ht, $student_id, $nickname, 'user');
                $res['data']['is_live'] = 0;
                $res['data']['course_id'] = $course_id;
                if($res['code'] == '1203'){
                    return $this->response('该课程没有回放记录', 500);
                }
                if(!array_key_exists('code', $res) && !$res['code'] == 0){
                    Log::error('进入直播间失败:'.json_encode($res));
                    return $this->response('进入直播间失败', 500);
                }
            }
            $res['data']['service'] = 'MT';


        } else {

            /**
            $CCCloud ->get_room_live_code($data[ 'course_id' ], $data[ 'school_id' ], $data[ 'nickname' ], $data[ 'user_key' ]);
            */

            //CC
            $CCCloud = new CCCloud();
            if($res->status == 2 or $res->status == 1 ){

                $viewercustominfo= array(
                    "school_id"=>$school_id,
                    "id" => $student_id,
                    "nickname" => $nickname
                );
               // $res = $CCCloud->get_room_live_code($course_id_ht);
                $res = $CCCloud->get_room_live_code($course_id_ht, $school_id, $nickname, $res ->user_key,
                    $viewercustominfo);
                $res['data']['is_live'] = 1;
            }else{

                $res = $CCCloud -> get_room_live_recode_code($course_id_ht);
                $res['data']['is_live'] = 0;

                if($res['code'] == '1203'){
                    return $this->response('该课程没有回放记录', 500);
                }
                if(!array_key_exists('code', $res) && !$res['code'] == 0){
                    Log::error('进入直播间失败:'.json_encode($res));
                    return $this->response('进入直播间失败', 500);
                }
            }

            $res['data']['service'] = 'CC';

        }
        $res['data']['course_id'] = $course_id;

        return $this->response($res['data']);
    }
}
