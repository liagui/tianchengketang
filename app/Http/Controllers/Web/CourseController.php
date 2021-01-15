<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Collection;
use App\Models\Coures;
use App\Models\Coureschapters;
use App\Models\Couresmaterial;
use App\Models\Couresmethod;
use App\Models\CouresSubject;
use App\Models\Couresteacher;
use App\Models\CourseAgreement;
use App\Models\CourseLiveClassChild;
use App\Models\CourseLiveResource;
use App\Models\CourseSchool;
use App\Models\CourseStatistics;
use App\Models\CourseStatisticsDetail;
use App\Models\CourseStocks;
use App\Models\Lecturer;
use App\Models\LiveChild;
use App\Models\LiveClass;
use App\Models\LiveClassChildTeacher;
use App\Models\Order;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Video;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use App\Tools\CCCloud\CCCloud;
use App\Tools\MTCloud;
use Illuminate\Support\Facades\Redis;

class CourseController extends Controller {
    protected $school;
    protected $data;
    protected $userid;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['school_dns']])->first();//改前
        //$this->school = $this->getWebSchoolInfo($this->data['school_dns']); //改后
        $this->userid = isset($this->data['user_info']['user_id'])?$this->data['user_info']['user_id']:0;
    }
    /*
         * @param  学科列表
         * @param  author  苏振文
         * @param  ctime   2020/7/6 15:38
         * return  array
         */
    public function subjectList(){
        //自增学科
        $subject = [];
        $subject = CouresSubject::where(['school_id'=>$this->school['id'],'parent_id'=>0,'is_open'=>0,'is_del'=>0])->get()->toArray();
        if(!empty($subject)){
            foreach ($subject as $k=>&$v){
                $subjects = CouresSubject::where(['parent_id'=>$v['id'],'is_open'=>0,'is_del'=>0])->get();
                if(!empty($subjects)){
                    $v['son'] = $subjects;
                }
            }
        }
        //授权学科
        $course = CourseSchool::select('ld_course.parent_id','ld_course.child_id')->leftJoin('ld_course','ld_course.id','=','ld_course_school.course_id')
            ->where(['ld_course_school.to_school_id'=>$this->school['id'],'ld_course_school.is_del'=>0,'ld_course.is_del'=>0])->groupBy('ld_course.parent_id')->get()->toArray();
        if(!empty($course)){
            foreach ($course as $ks=>$vs){
                $ones = CouresSubject::where(['id'=>$vs['parent_id'],'parent_id'=>0,'is_open'=>0,'is_del'=>0])->first();
                if(!empty($ones)){
                    $ones['son'] = CouresSubject::where(['parent_id'=>$vs['parent_id'],'id'=>$vs['child_id'],'is_open'=>0,'is_del'=>0])->get();
                    array_push($subject,$ones);
                }else{
                    unset($course[$ks]);
                }
            }
        }
        return response()->json(['code' => 200 , 'msg' => '获取成功','data'=>$subject]);
    }
    /*
         * @param  课程列表
         * @param  author  苏振文
         * @param  ctime   2020/7/4 17:09
         * return  array
     */
    public function courseList()
    {
            $school_id = $this->school['id'];
            //每页显示的条数
            $pagesize = (int)isset($this->data['pageSize']) && $this->data['pageSize'] > 0 ? $this->data['pageSize'] : 20;
            $page = isset($this->data['page']) && $this->data['page'] > 0 ? $this->data['page'] : 1;
            $offset = ($page - 1) * $pagesize;
            //学科大类小类条件
            $parent = [];
            if (!empty($this->data['parent'])) {
                $parent = json_decode($this->data['parent'], true);
            }
            //授课类型条件
            $methodwhere = isset($this->data['method'])?$this->data['method']:'';
            $name = isset($this->data['name']) ? $this->data['name'] : '';
            $count = 0;
            //自增课程
        if(!empty($name)){
            $course = Coures::select('ld_course.id', 'ld_course.title', 'ld_course.cover', 'ld_course.pricing','ld_course.sale_price', 'ld_course.buy_num', 'ld_course.nature', 'ld_course.watch_num', 'ld_course.create_at')
                ->leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course.id')
                ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                ->where(function ($query) use ($parent,$name) {
                    if (!empty($parent[0]) && $parent[0] != ''&& $parent[0] != 0) {
                        $query->where('ld_course.parent_id', $parent[0]);
                    }
                    if (!empty($parent[1]) && $parent[1] != ''&& $parent[1] != 0) {
                        $query->where('ld_course.child_id', $parent[1]);
                    }
                    if(!empty($name)){
                        $query->where('ld_course.title', 'like', '%'.$name.'%');
                        $query->orwhere('ld_lecturer_educationa.real_name','like', '%'.$name.'%');
                        $query->groupBy('ld_course_teacher.course_id');
                    }
                })
                ->where(['ld_course.school_id' => $school_id, 'ld_course.is_del' => 0, 'ld_course.status' => 1])
                ->get()->toArray();
        }else{
            $course = Coures::select('ld_course.id', 'ld_course.title', 'ld_course.cover', 'ld_course.pricing','ld_course.sale_price', 'ld_course.buy_num', 'ld_course.nature', 'ld_course.watch_num', 'ld_course.create_at')
                ->where(function ($query) use ($parent) {
                    if (!empty($parent[0]) && $parent[0] != ''&& $parent[0] != 0) {
                        $query->where('ld_course.parent_id', $parent[0]);
                    }
                    if (!empty($parent[1]) && $parent[1] != ''&& $parent[1] != 0) {
                        $query->where('ld_course.child_id', $parent[1]);
                    }
                })
                ->where(['ld_course.school_id' => $school_id, 'ld_course.is_del' => 0, 'ld_course.status' => 1])
                ->get()->toArray();
        }
            if(!empty($course)) {
                foreach ($course as $k => &$v) {
                    //查询课程购买数
                    $buynum =   Order::where(['class_id'=>$v['id'],'nature'=>0,'status'=>2])->whereIn('pay_status',[3,4])->count();
                    $v['buy_num'] = $v['buy_num'] + $buynum;

                    $method = Couresmethod::select('method_id')->where(['course_id' => $v['id'], 'is_del' => 0])
                        ->where(function ($query) use ($methodwhere) {
                            if ($methodwhere != '') {
                                $query->where('method_id', $methodwhere);
                            }
                        })->get()->toArray();
                    if (!empty($method)) {
                        $count = $count + 1;
                        foreach ($method as $key => &$val) {
                            if ($val['method_id'] == 1) {
                                $val['method_name'] = '直播';
                            }
                            if ($val['method_id'] == 2) {
                                $val['method_name'] = '录播';
                            }
                            if ($val['method_id'] == 3) {
                                $val['method_name'] = '其他';
                            }
                        }
                        $v['method'] = $method;
                    } else {
                        unset($course[$k]);
                    }
                }
            }
            //授权课程
        if(!empty($name)){
            $ref_course = CourseSchool::select('ld_course_school.id', 'ld_course_school.title', 'ld_course_school.cover', 'ld_course_school.pricing','ld_course_school.sale_price', 'ld_course_school.buy_num', 'ld_course_school.watch_num', 'ld_course_school.create_at', 'ld_course_school.course_id')
                ->leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course_school.course_id')
                ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                ->where(function ($query) use ($parent,$name) {
                    if (!empty($parent[0]) && $parent[0] != ''&& $parent[0] != 0) {
                        $query->where('ld_course_school.parent_id', $parent[0]);
                    }
                    if (!empty($parent[1]) && $parent[1] != ''&& $parent[1] != 0) {
                        $query->where('ld_course_school.child_id', $parent[1]);
                    }
                    $query->where('ld_course_school.title', 'like', '%'.$name.'%');
                    $query->orwhere('ld_lecturer_educationa.real_name','like', '%'.$name.'%');
                    $query->groupBy('ld_course_teacher.course_id');
                })
                ->where(['ld_course_school.to_school_id' => $school_id, 'ld_course_school.is_del' => 0, 'ld_course_school.status' => 1])
                ->get()->toArray();
        }else {
            $ref_course = CourseSchool::select('ld_course_school.id', 'ld_course_school.title', 'ld_course_school.cover', 'ld_course_school.pricing', 'ld_course_school.sale_price', 'ld_course_school.buy_num', 'ld_course_school.watch_num', 'ld_course_school.create_at', 'ld_course_school.course_id')
                ->where(function ($query) use ($parent) {
                    if (!empty($parent[0]) && $parent[0] != '' && $parent[0] != 0) {
                        $query->where('ld_course_school.parent_id', $parent[0]);
                    }
                    if (!empty($parent[1]) && $parent[1] != '' && $parent[1] != 0) {
                        $query->where('ld_course_school.child_id', $parent[1]);
                    }
                })
                ->where(['ld_course_school.to_school_id' => $school_id, 'ld_course_school.is_del' => 0, 'ld_course_school.status' => 1])
                ->get()->toArray();
        }
            foreach ($ref_course as $ks => &$vs) {
                //获取库存计算总数  订单总数   判断 相等或大于就删除，否则展示
//                $add_number = CourseStocks::where(['course_id' => $vs['course_id'], 'school_id' => $school_id, 'is_del' => 0])->get();
//                if (!empty($add_number)) {
//                    //库存总数
//                    $stocknum = 0;
//                    foreach ($add_number as $kstock => $vstock) {
//                        $stocknum = $stocknum + $vstock['add_number'];
//                    }
                       //查订单表
                        $ordercount = Order::where(['status' => 2, 'oa_status' => 1, 'school_id' => $school_id, 'class_id' => $vs['id'], 'nature' => 1])->whereIn('pay_status',[3,4])->count();
//                        if ($ordercount <= $stocknum) {
                            $vs['buy_num'] = $vs['buy_num'] + $ordercount;
                            $method = Couresmethod::select('method_id')->where(['course_id' => $vs['course_id'], 'is_del' => 0])
                                ->where(function ($query) use ($methodwhere) {
                                    if ($methodwhere != '') {
                                        $query->where('method_id', $methodwhere);
                                    }
                                })->get()->toArray();
                            if (!empty($method)) {
                                $count = $count +1;
                                foreach ($method as $key => &$val) {
                                    if ($val['method_id'] == 1) {
                                        $val['method_name'] = '直播';
                                    }
                                    if ($val['method_id'] == 2) {
                                        $val['method_name'] = '录播';
                                    }
                                    if ($val['method_id'] == 3) {
                                        $val['method_name'] = '其他';
                                    }
                                }
                                $vs['method'] = $method;
                                $vs['nature'] = 1;
                            } else {
                                unset($ref_course[$ks]);
                            }
//                        } else {
//                            unset($ref_course[$ks]);
//                        }
//                    } else {
//                        unset($ref_course[$ks]);
//                    }
//                } else {
//                    unset($ref_course[$ks]);
//                }
            }
            //两数组合并 排序
            if (!empty($course) && !empty($ref_course)) {
                $all = array_merge($course, $ref_course);//合并两个二维数组
            } else {
                $all = !empty($course) ? $course : $ref_course;
            }
            //sort 1最新2最热  默认最新
            $sort = isset($this->data['sort']) ? $this->data['sort'] : 1;
            if ($sort == 1) {
                $date = array_column($all, 'create_at');
                array_multisort($date, SORT_DESC, $all);
            } else if ($sort == 2) {
                $date = array_column($all, 'buy_num');
                array_multisort($date, SORT_DESC, $all);
            }
            $res = array_slice($all, $offset, $pagesize);
            if(empty($res)){
                $res = array_slice($all, 1, $pagesize);
            }
            $page = [
                'pageSize' => $pagesize,
                'page' => $page,
                'total' => $count
            ];
            return response()->json(['code' => 200, 'msg' => '获取成功', 'data' => $res, 'page' => $page, 'where' => $this->data]);
    }

    /**
     * 课程列表 自定义首页
     * @return \Illuminate\Http\JsonResponse
     */
    public function courseListByIndexSet(){
        //获取提交的参数
        try{
            $data = Coures::courseListByIndexSet($this->data, $this->school['id']);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
         * @param  课程详情
         * @param  author  苏振文
         * @param  ctime   2020/7/6 17:50
         * return  array
         */
    public function courseDetail(){
        if(!isset($this->data['id']) || empty($this->data['id'])){
            return response()->json(['code' => 201 , 'msg' => '课程id不能为空']);
        }
        $this->data['nature'] = isset($this->data['nature'])?$this->data['nature']:0;
        //课程基本信息
        //授权
        if($this->data['nature'] == 1){
            $course = CourseSchool::where(['id'=>$this->data['id'],'is_del'=>0])->first();

            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无此课程']);
            }
            $course['nature'] = 1;
            //修改观看数
            CourseSchool::where(['id'=>$this->data['id']])->update(['watch_num'=>$course['watch_num']+1]);
            //授课方式
            $method = Couresmethod::select('method_id')->where(['course_id' => $course['course_id'],'is_del'=>0])->get()->toArray();
            if (!empty($method)) {
                $course['method'] = array_column($method, 'method_id');
            }
            if(in_array('1',$course['method'])){
                //获取所有的班号
                $shift = CourseLiveResource::where(['course_id'=>$course['course_id'],'is_del'=>0])->get();
                if(!empty($shift)){
                    $classtime = 0;
                    foreach ($shift as $ks=>$vs){
                        $time = LiveChild::where(['shift_no_id'=>$vs['shift_id'],'is_del'=>0,'status'=>1])->sum('class_hour');
                        $classtime = $classtime + $time;
                    }
                    $course['classtime'] = $classtime;
                }else{
                    $course['classtime'] = 0;
                }
            }else{
                $course['classtime'] = 0;
            }
            //学习人数   基数+订单数
            $ordernum = Order::where(['class_id'=>$this->data['id'],'nature'=>1])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count();

            $course['buy_num'] = $ordernum + $course['buy_num'];

            //讲师信息
            $teacher = [];
            $teacherlist = Couresteacher::where(['course_id' => $course['course_id'], 'is_del' => 0])->get();
            if (!empty($teacherlist)) {
                foreach ($teacherlist as $k => $v) {
                    if(!empty($v['teacher_id'])){
                        $oneteacher = Teacher::where(['id' => $v['teacher_id'], 'is_del' => 0])->first();
                        if(!empty($oneteacher)){
                            $oneteacher = Teacher::where(['id' => $v['teacher_id'], 'is_del' => 0])->first()->toArray();
                            array_push($teacher, $oneteacher);
                        }
                    }
                }
            }
            //收藏数量
            $collect = Collection::where(['lesson_id'=>$this->data['id'],'is_del'=>0,'nature'=>1])->count();
            $course['collect'] = $collect;
        }else{
            $course = Coures::where(['id'=>$this->data['id'],'is_del'=>0])->first();
            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无此课程']);
            }
            $course['nature'] = 0;
            //修改观看数
            Coures::where(['id'=>$this->data['id']])->update(['watch_num'=>$course['watch_num']+1]);
            //授课方式
            $method = Couresmethod::select('method_id')->where(['course_id' =>$this->data['id'],'is_del'=>0])->get()->toArray();
            if (!empty($method)) {
                $course['method'] = array_column($method, 'method_id');
            }
            if(in_array('1',$course['method'])){
                //获取所有的班号
                $shift = CourseLiveResource::where(['course_id'=>$course['id'],'is_del'=>0])->get();
                if(!empty($shift)){
                    $classtime = 0;
                    foreach ($shift as $ks=>$vs){
                        $time = LiveChild::where(['shift_no_id'=>$vs['shift_id'],'is_del'=>0,'status'=>1])->sum('class_hour');
                        $classtime = $classtime + $time;
                    }
                    $course['classtime'] = $classtime;
                }else{
                    $course['classtime'] = 0;
                }
            }else{
                $course['classtime'] = 0;
            }
            //学习人数   基数+订单数
            $ordernum = Order::where(['class_id'=>$this->data['id'],'nature'=>0])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count();

            $course['buy_num'] = $ordernum + $course['buy_num'];

            //讲师信息
            $teacher = [];
            $teacherlist = Couresteacher::where(['course_id' => $this->data['id'], 'is_del' => 0])->get();
            if (!empty($teacherlist)) {
                foreach ($teacherlist as $k => $v) {
                    if(!empty($v['teacher_id'])){
                        $oneteacher = Teacher::where(['id' => $v['teacher_id'], 'is_del' => 0])->first();
                        if(!empty($oneteacher)){
                            $oneteacher = Teacher::where(['id' => $v['teacher_id'], 'is_del' => 0])->first()->toArray();
                            array_push($teacher, $oneteacher);
                        }
                    }
                }
            }
            //收藏数量
            $collect = Collection::where(['lesson_id'=>$this->data['id'],'is_del'=>0,'nature'=>0])->count();
            $course['collect'] = $collect;
        }
        //分类信息
        $parent = CouresSubject::select('id', 'subject_name')->where(['id' => $course['parent_id'], 'parent_id' => 0, 'is_del' => 0, 'is_open' => 0])->first();
        $child = CouresSubject::select('subject_name')->where(['id' => $course['child_id'], 'parent_id' => $parent['id'], 'is_del' => 0, 'is_open' => 0])->first();
        $course['parent_name'] = $parent['subject_name'];
        $course['child_name'] = $child['subject_name'];
        unset($course['parent_id']);
        unset($course['child_id']);
        return response()->json(['code' => 200, 'msg' => '查询成功', 'data' => $course]);
    }
    //用户与课程关系
    public function courseToUser(){
        $nature = isset($this->data['nature'])?$this->data['nature']:0;
        $data=[];
        if($nature == 1){
            //获取被授权的课程id
            $courseschool = CourseSchool::where(['id'=>$this->data['id'],'is_del'=>0])->first();
            //获取库存计算总数  订单总数 判断
            $add_number = CourseStocks::where(['course_id' => $courseschool['course_id'], 'school_id' => $this->school['id'], 'is_del' => 0])->get();
            $stocknum = 0;
            if (!empty($add_number)) {
                //库存总数
                foreach ($add_number as $kstock => $vstock) {
                    $stocknum = $stocknum + $vstock['add_number'];
                }
            }
            $ordercount = Order::where(['status' => 2, 'oa_status' => 1, 'school_id' => $this->school['id'], 'class_id' => $this->data['id'], 'nature' => 1])->whereIn('pay_status',[3,4])->count();
            if($this->userid != 0){
                $order = Order::where(['student_id' => $this->userid, 'class_id' =>$this->data['id'], 'status' => 2,'nature'=>1])->whereIn('pay_status',[3,4])->orderByDesc('id')->first();
                //看订单里面的到期时间 进行判断
                if (date('Y-m-d H:i:s') >= $order['validity_time']) {
                    if($ordercount >= $stocknum){
                        //库存不足
                        $data['is_pay'] = 2;
                    }else {
                        //课程到期 需要购买
                        $data['is_pay'] = 0;
                    }
                } else {
                    //已经购买
                    $data['is_pay'] = 1;
                }
            }else{
                if($ordercount >= $stocknum){
                    //库存不足
                    $data['is_pay'] = 2;
                }else{
                    //需要购买
                    $data['is_pay'] = 0;
                }

            }
            //判断用户是否收藏
            if($this->userid != 0){
                $collects = Collection::where(['lesson_id'=>$this->data['id'],'student_id'=>$this->userid,'is_del'=>0,'nature'=>1])->count();
                if($collects != 0){
                    $data['is_collect'] = 1;
                }else{
                    $data['is_collect'] = 0;
                }
            }else{
                $data['is_collect'] = 0;
            }
        }else{
            //获取库存计算总数  订单总数   判断 相等或大于就删除，否则展示
//            $add_number = CourseStocks::where(['course_id' => $this->data['id'], 'school_id' => $this->school['id'], 'is_del' => 0])->get();
//            $stocknum = 0;
//            if (!empty($add_number)) {
//                //库存总数
//                foreach ($add_number as $kstock => $vstock) {
//                    $stocknum = $stocknum + $vstock['add_number'];
//                }
//            }
//            $ordercount = Order::where(['status' => 2, 'oa_status' => 1, 'school_id' => $this->school['id'], 'class_id' => $this->data['id'], 'nature' => 0])->whereIn('pay_status',[3,4])->count();
//            if($ordercount >= $stocknum){
//                $data['is_pay'] =2;
//            }else{
                //是否已购买
                if($this->userid != 0){
                    $order = Order::where(['student_id' => $this->userid, 'class_id' =>$this->data['id'], 'status' => 2,'nature'=>0])->whereIn('pay_status',[3,4])->orderByDesc('id')->first();
                    //看订单里面的到期时间 进行判断
                    if (date('Y-m-d H:i:s') >= $order['validity_time']) {
                        //课程到期  只能观看
                        $data['is_pay'] = 0;
                    } else {
                        $data['is_pay'] = 1;
                    }
                 }else{
                     $data['is_pay'] = 0;
                }
//            }
            //判断用户是否收藏
            if($this->userid != 0){
                $collects = Collection::where(['lesson_id'=>$this->data['id'],'student_id'=>$this->userid,'is_del'=>0,'nature'=>0])->count();
                if($collects != 0){
                    $data['is_collect'] = 1;
                }else{
                    $data['is_collect'] = 0;
                }
            }else{
                $data['is_collect'] = 0;
            }
        }
        return response()->json(['code' => 200, 'msg' => '查询成功', 'data' => $data]);
    }
    //课程收藏
    public function collect(){
        if(!isset($this->data['id'])||empty($this->data['id'])){
            return response()->json(['code' => 201, 'msg' => '课程id为空']);
        }
        $list = Collection::where(['lesson_id'=>$this->data['id'],'student_id'=>$this->userid,'nature'=>$this->data['nature']])->first();
        if($list){
            $status = $list['is_del'] == 1?0:1;
            $add = Collection::where(['lesson_id'=>$this->data['id'],'student_id'=>$this->userid,'nature'=>$this->data['nature']])->update(['is_del'=>$status]);
        }else{
            $add = Collection::insert([
                'lesson_id' => $this->data['id'],
                'student_id' => $this->userid,
                'created_at' => date('Y-m-d H:i:s'),
                'nature' => $this->data['nature']
            ]);
        }
        if($add){
            $count = Collection::where(['lesson_id'=>$this->data['id'],'nature'=>$this->data['nature'],'is_del'=>0])->count();
            return response()->json(['code' => 200, 'msg' => '操作成功','data'=>$count]);
        }else{
            return response()->json(['code' => 203, 'msg' => '操作失败']);
        }
    }
    /*
         * @param  课程讲师
         * @param  author  苏振文
         * @param  ctime   2020/7/13 15:29
         * return  array
         */
    public function courseTeacher(){
        if(!isset($this->data['id'])||empty($this->data['id'])){
            return response()->json(['code' => 201, 'msg' => '课程id为空']);
        }
        $nature = isset($this->data['nature'])?$this->data['nature']:0;
        if($nature == 1){
            $course = CourseSchool::where(['id'=>$this->data['id'],'is_del'=>0,'status'=>1])->first();
            $this->data['id'] =  $course['course_id'];
        }
        $teacher = Couresteacher::where(['course_id'=>$this->data['id'],'is_del'=>0])->get();
        $teacherlist=[];
        if(!empty($teacher)){
            foreach ($teacher as $k=>$v){
                $teacherlist[] = Lecturer::where(['id'=>$v['teacher_id'],'is_del'=>0,'is_forbid'=>0,'type'=>2])->first();
            }
        }
        return response()->json(['code' => 200, 'msg' => '获取成功','data'=>$teacherlist]);
    }
    /*
         * @param  课程介绍
         * @param  author  苏振文
         * @param  ctime   2020/7/7 15:18
         * return  array
         */

    public function courseIntroduce(){
        $nature = $this->data['nature'];
        if($nature == 1){
            //课程基本信息
            $course = CourseSchool::select('introduce')->where(['id'=>$this->data['id'],'is_del'=>0])->first();
            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无查看权限']);
            }
        }else{
            //课程基本信息
            $course = Coures::select('introduce')->where(['id'=>$this->data['id'],'is_del'=>0])->first();
            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无查看权限']);
            }
        }
        return response()->json(['code' => 200 , 'msg' => '查询成功','data'=>$course]);
    }
    /*
         * @param  课程录播列表
         * @param  author  苏振文
         * @param  ctime   2020/7/7 14:39
         * return  array
         */
    public function recordedarr(){
        //课程基本信息
        if(!isset($this->data['id'])||empty($this->data['id'])){
            return response()->json(['code' => 201 , 'msg' => '课程id为空']);
        }
        $nature = isset($this->data['nature'])?$this->data['nature']:0;
        if($nature == 1){
            $course = CourseSchool::where(['to_school_id'=>$this->school['id'],'id'=>$this->data['id'],'is_del'=>0])->first();
            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无查看权限']);
            }
            $orderwhere=[
                'student_id'=>$this->userid,
                'class_id' => $course['id'],
                'status' => 2,
                'nature' =>1
            ];
            $this->data['id'] = $course['course_id'];
        }else{
            $course = Coures::where(['school_id'=>$this->school['id'],'id'=>$this->data['id'],'is_del'=>0])->first();
            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无查看权限']);
            }
            $orderwhere=[
                'student_id'=>$this->userid,
                'class_id' => $this->data['id'],
                'status' => 2,
                'nature' =>0
            ];
        }
        //判断用户与课程的关系
        //判断课程是否免费
            $order = Order::where($orderwhere)->orderByDesc('id')->first();
            //判断是否购买
            if (!empty($order)) {
                //看订单里面的到期时间 进行判断
                if (date('Y-m-d H:i:s') >= $order['validity_time']) {
                    //课程到期  只能观看
                    $is_show = 0;
                } else {
                    $is_show = 1;
                }
            } else {
                //未购买
                $is_show = 0;
            }
        //章总数
        $count = Coureschapters::where(['course_id'=>$this->data['id'],'is_del'=>0,'parent_id'=>0])->count();
        $recorde =[];
        if($count > 0) {
            //如果is_show是1  查询所有的课程   0查询能免费看的，试听的课程
            if($is_show == 1){
                $chapterswhere = [
                    'is_del' => 0,
                ];
            }else {
                //查询免费课程
                $chapterswhere = [
                    'is_del' => 0,
                    'is_free' => 0
                ];
            }
            //获取章
            $recorde = Coureschapters::where(['course_id' => $this->data['id'], 'is_del' => 0, 'parent_id' => 0])->orderBy('sort','asc')->get();
            if (!empty($recorde)) {
                //循环章  查询每个章下的节
                foreach ($recorde as $k => &$v) {
                    $recordes = Coureschapters::where(['course_id' => $this->data['id'], 'parent_id' => $v['id']])->where($chapterswhere)->orderBy('sort','asc')->get();
                    if (!empty($recordes)) {
                        $v['chapters'] = $recordes;
                    }
                }
            }
        }
        return response()->json(['code' => 200 , 'msg' => '获取成功','data'=>$recorde]);
    }
    //录播小节播放url
    public function recordeurl(){
        if($this->data['resource_id'] == 0){
            return response()->json(['code' => 201 , 'msg' => '暂无资源']);
        }else{
            // TODO:  这里替换欢托的sdk CC 直播的  这里是点播的业务
            $MTCloud = new MTCloud();
            $CCCloud = new CCCloud();
            //查询小节绑定的录播资源
            $ziyuan = Video::where(['id' => $this->data['resource_id'], 'is_del' => 0])->first();
//            $video_url = $MTCloud->videoGet($ziyuan['mt_video_id'],'720d');
            $nickname = !empty($this->data['user_info']['nickname'] )?$this->data['user_info']['nickname']:$this->data['user_info']['real_name'];
            $school_id = !empty($this->data['user_info']['school_id'] )?$this->data['user_info']['school_id']:0;
            if(empty($nickname)){
                $nickname = $this->data['user_info']['phone'];
            }
            if($ziyuan['service'] == "MT"){
                $res = $MTCloud->courseAccessPlayback($ziyuan['course_id'], $this->userid,$nickname, 'user');
            }else{
                // todo 这里修改成cc 点播播放地址
                //$res = $CCCloud->get_room_live_recode_code($ziyuan['course_id']);
                $res = $CCCloud ->get_video_code($school_id, $ziyuan['cc_video_id'],$nickname);
            }
            $res['data']['is_live'] = 0;
            if($res['code'] ==  0){
//                $video_url = $video_url['data']['videoUrl'];
                return response()->json(['code' => 200 , 'msg' => '获取成功','data'=>$res['data']]);
            }else{
                return response()->json(['code' => 201 , 'msg' => '暂无资源']);
            }
        }
    }
    //课程直播列表
    public function livearr(){

        //每页显示的条数
        $pagesize = (int)isset($this->data['pageSize']) && $this->data['pageSize'] > 0 ? $this->data['pageSize'] : 2;
        $page     = isset($this->data['page']) && $this->data['page'] > 0 ? $this->data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //课程基本信息
        if(!isset($this->data['id'])||empty($this->data['id'])){
            return response()->json(['code' => 201 , 'msg' => '课程id为空']);
        }
        $nature = isset($this->data['nature'])?$this->data['nature']:1;
        if($nature == 1){
            $course = CourseSchool ::where(['to_school_id'=>$this->school['id'],'id'=>$this->data['id'],'is_del'=>0])->first();
            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无查看权限1']);
            }
            $this->data['id'] = $course['course_id'];
            $orderwhere=[
                'student_id'=>$this->userid,
                'class_id' => $course['id'],
                'status' => 2,
                'nature' =>1
            ];
        }else{
            $course = Coures::where(['school_id'=>$this->school['id'],'id'=>$this->data['id'],'is_del'=>0])->first();
            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无查看权限2']);
            }
            $orderwhere=[
                'student_id'=>$this->userid,
                'class_id' => $course['id'],
                'status' => 2,
                'nature' =>0
            ];
        }
        $courseArr=[];
        //判断用户与课程的关系
        $order = Order::where($orderwhere)->whereIn('pay_status',[3,4])->first();
        //判断是否购买
        if (!empty($order)) {
            //看订单里面的到期时间 进行判断
            if (date('Y-m-d H:i:s') >= $order['validity_time']) {
                //课程到期  只能观看
                $is_show = 0;
            } else {
                $is_show = 1;
            }
        } else {
            //未购买
            $is_show = 0;
        }
        //章总数
        $count = CourseLiveResource::where(['course_id'=>$this->data['id'],'is_del'=>0])->count();
        $course_statistics_detail = new CourseStatisticsDetail();
        if($count > 0){
            //获取所有的班号
            $courseArr = CourseLiveResource::select('shift_id')->where(['course_id'=>$this->data['id'],'is_del'=>0])->get();
            if(!empty($courseArr)){
                foreach ($courseArr as $k=>&$v){
                    //获取班级信息
                    $class = LiveClass::where(['id'=>$v['shift_id'],'is_del'=>0])->first();
                    $v['class_name'] = $class['name'];
                    //获取所有的课次
                    $classci = LiveChild::join("ld_course_live_childs","ld_course_live_childs.class_id","=","ld_course_class_number.id")->where(['shift_no_id'=>$v['shift_id'],'ld_course_class_number.is_del'=>0,'ld_course_class_number.status'=>1])->get();
                    if(!empty($classci)){
                        //课次关联讲师  时间戳转换   查询所有资料
                        foreach ($classci as $ks=>&$vs){
                            //开课时间戳 start_at 结束时间戳转化 end_at
                            $ymd = date('Y-m-d',$vs['start_at']);//年月日
                            $start = date('H:i',$vs['start_at']);//开始时分
                            $end = date('H:i',$vs['end_at']);//结束时分
                            $weekarray = ["周日", "周一", "周二", "周三", "周四", "周五", "周六"];
                            $xingqi = date("w", $vs['start_at']);
                            $week = $weekarray[$xingqi];
                            $vs['times'] = $ymd.'&nbsp;&nbsp;'.$week.'&nbsp;&nbsp;'.$start.'-'.$end;
                            //判断课程直播状态  1未直播2直播中3回访
                            $vs['livestatus'] = $vs['status'];

                            //查询讲师
                            $teacher = LiveClassChildTeacher::leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_class_teacher.teacher_id')
                                ->where(['ld_course_class_teacher.is_del'=>0,'ld_lecturer_educationa.is_del'=>0,'ld_lecturer_educationa.type'=>2,'ld_lecturer_educationa.is_forbid'=>0])
                                ->where(['ld_course_class_teacher.class_id'=>$vs['id']])
                                ->first();
                            if(!empty($teacher)){
                                $vs['teacher_name'] = $teacher['real_name'];
                            }
                            //查询资料
                            $material = Couresmaterial::where(['mold'=>3,'is_del'=>0,'parent_id'=>$vs['id']])->get();
                            if(!empty($material)){
                                $vs['material'] = $material;
                            }else{
                                $vs['material'] = '';
                            }
                            // 获取 本次 课次的 完成进度
                            $vs['learn_rate'] = "".$course_statistics_detail ->getCourseRateByStudentIdAndRoomId($this->school['id'],$this->userid,$vs['course_id']);
                            $vs['learn_rate_args'] = array($this->school['id'],$this->userid,$vs['course_id']);
                            //class_id
                            $vs['id'] = $vs['class_id'];
                        }
                        $v['keci'] = $classci;
                    }
                }
            }
        }
        return response()->json(['code' => 200 , 'msg' => '查询成功','data'=>$courseArr]);
    }
    //直播播放url
    public function liveurl(){
        //根据课次id 查询关联欢拓表
        $livechilds = CourseLiveClassChild::where(['class_id'=>$this->data['id'],'is_del'=>0,'is_forbid'=>0])->first();
        $datas['course_id'] = $livechilds['course_id'];
        $datas['uid'] = $this->userid;
        $datas['nickname'] = $this->data['user_info']['nickname'] != ''?$this->data['user_info']['nickname']:$this->data['user_info']['real_name'];
        $datas['role'] = 'user';
        $datas['user_key'] = $livechilds['user_key'];


        $platform = verifyPlat() ? verifyPlat() : 'pc';
        $user_token = $platform.":".$this->data['user_token'];
        $datas['user_key'] = $user_token;


        $student_id = $this->data['user_info']['user_id'];
        $nickname = $this->data['user_info']['nickname'];
        $school_id = $this->data['user_info']['school_id'];
        $phone = $this->data['user_info']['phone'];

        // TODO:  这里替换欢托的sdk CC 直播的 ok

        $MTCloud = new MTCloud();
        $CCCloud = new CCCloud();
        if ($this->data[ 'livestatus' ] == 1 || $this->data[ 'livestatus' ] == 2) {
            if ($livechilds[ 'bid' ] > 0) {
                $res = $MTCloud->courseAccess($datas[ 'course_id' ], $datas[ 'uid' ], $datas[ 'nickname' ], $datas[ 'role' ]);

            } else {
                $viewercustominfo= array(
                    "school_id"=>$school_id,
                    "id" => $student_id,
                    "nickname" => $nickname,
                    "phone" => $phone
                );
                // $res = $CCCloud->get_room_live_code($datas[ 'course_id' ], $this->school->id, $datas[ 'nickname' ], $datas[ 'user_key' ],$viewercustominfo);
                $res = $CCCloud->get_room_live_code($datas[ 'course_id' ], $this->school->id, $datas[ 'nickname' ], $user_token,$viewercustominfo);

            }

            if (!array_key_exists('code', $res) && !$res[ "code" ] == 0) {
                return response()->json([ 'code' => 201, 'msg' => '暂无直播，请重试' ]);

            }

            return response()->json([ 'code' => 200, 'msg' => '获取成功', 'data' => $res[ 'data' ][ 'liveUrl' ] ]);
        }
        if ($this->data[ 'livestatus' ] == 3) {
            if ($livechilds[ 'bid' ] > 0) {
                $res = $MTCloud->courseAccessPlayback($datas[ 'course_id' ], $datas[ 'uid' ], $datas[ 'nickname' ], $datas[ 'role' ]);
            } else {
                $viewercustominfo= array(
                    "school_id"=>$school_id,
                    "id" => $student_id,
                    "nickname" => $nickname,
                    "phone" => $phone
                );
                // $res = $CCCloud->get_room_live_recode_code($datas[ 'course_id' ], $this->school->id, $datas[ 'nickname' ], $datas[ 'user_key' ],$viewercustominfo);
                $res = $CCCloud->get_room_live_recode_code($datas[ 'course_id' ], $this->school->id, $datas[ 'nickname' ], $user_token,$viewercustominfo);
            }

            if (!array_key_exists('code', $res) && !$res[ "code" ] == 0) {
                return response()->json([ 'code' => 201, 'msg' => '暂无回访，请重试' ]);

            }
            // 欢托去掉之后 cc的返回结果返回标准的结果

            //return response()->json([ 'code' => 200, 'msg' => '获取成功', 'data' => $res[ 'data' ][ 'playbackUrl' ] ]);
            return response()->json([ 'code' => 200, 'msg' => '获取成功', 'data' => $res[ 'data' ] ]);

        }
    }

    /*
         * @param  课程资料表   录播  直播班号 课程小节
         * @param  author  苏振文
         * @param  ctime   2020/7/7 14:40
         * return  array
         */
    public function material(){

        //每页显示的条数
//        $pagesize = (int)isset($this->data['pageSize']) && $this->data['pageSize'] > 0 ? $this->data['pageSize'] : 10;
//        $page     = isset($this->data['page']) && $this->data['page'] > 0 ? $this->data['page'] : 1;
//        $offset   = ($page - 1) * $pagesize;
        $nature = isset($this->data['nature'])?$this->data['nature']:0;
        //查询订单信息
        if($nature == 1){
            $course = CourseSchool ::where(['id'=>$this->data['id'],'is_del'=>0])->first();
            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无查看权限']);
            }
            $this->data['id'] = $course['course_id'];
            $orderwhere=[
                'student_id'=>$this->userid,
                'class_id' => $course['id'],
                'status' => 2,
                'nature' =>1
            ];
        }else{
            $course = Coures::where(['school_id'=>$this->school['id'],'id'=>$this->data['id'],'is_del'=>0])->first();
            if(!$course){
                return response()->json(['code' => 201 , 'msg' => '无查看权限']);
            }
            $orderwhere=[
                'student_id'=>$this->userid,
                'class_id' => $course['id'],
                'status' => 2,
                'nature' =>0
            ];
        }
        $type = isset($this->data['type'])?$this->data['type']:'';
        $ziyuan=[];
        //判断用户与课程的关系
        $order = Order::where($orderwhere)->whereIn('pay_status',[3,4])->first();
        //判断是否购买
        if (!empty($order)) {
            //看订单里面的到期时间 进行判断
            if (date('Y-m-d H:i:s') >= $order['validity_time']) {
                //课程到期  只能观看
                $is_show = 0;
            } else {
                $is_show = 1;
            }
        } else {
            //未购买
            $is_show = 0;
        }
        if($is_show > 0){
            //录播资料
            $jie = Coureschapters::where(['course_id'=>$this->data['id'],'is_del'=>0])->where('parent_id','>',0)->get();
            if(!empty($jie)){
                foreach ($jie as $k=>$v){
                    $ziliao = Couresmaterial::where(['parent_id'=>$v['id'],'is_del'=>0,'mold'=>1])
                        ->where(function ($query) use ($type) {
                            if (!empty($type) && $type != '' && $type != 0) {
                                $query->where('type', $type);
                            }
                        })->get();
                    if(!empty($ziliao)){
                        foreach ($ziliao as $kss=>$vss){
                            $ziliao[$kss]['method'] = 1;
                            $ziyuan[] = $vss;
                        }
                    }
                }
            }
            //直播资料  获取所有的班号
            $ban = CourseLiveResource::where(['course_id'=>$this->data['id'],'is_del'=>0])->get();
            if(!empty($ban)){
                foreach ($ban as $ks=>$vs){
                    //获取班号资料
                    $classzl = Couresmaterial::where(['mold'=>2,'is_del'=>0,'parent_id'=>$vs['shift_id']])->where(function ($query) use ($type) {
                        if (!empty($type) && $type != '' && $type != 0) {
                            $query->where('type', $type);
                        }
                    })->get();
                    if(!empty($classzl)){
                        foreach ($classzl as $classk => $classv){
                            $classzl[$classk]['method'] = 2;
                            $ziyuan[] = $classv;
                        }
                    }
                    //每个班号获取所有的课次
                    $shirt = LiveChild::where(['shift_no_id'=>$vs['shift_id'],'is_del'=>0,'status'=>1])->get();
                    if(!empty($shirt)){
                        foreach ($shirt as $shirtk => $shirtv){
                            $number = Couresmaterial::where(['mold'=>3,'is_del'=>0,'parent_id'=>$shirtv['id']])->where(function ($query) use ($type) {
                                if (!empty($type) && $type != '' && $type != 0) {
                                    $query->where('type', $type);
                                }
                            })->get();
                            if(!empty($number)){
                                foreach ($number as $numberk => $numberv){
                                    $number[$numberk]['method'] = 2;
                                    $ziyuan[] = $numberv;
                                }
                            }
                        }
                    }
                }
            }
        }
//        $res = array_slice($ziyuan, $offset, $pagesize);
//        if(empty($res)){
//            $res = array_slice($res, 1, $pagesize);
//        }
        return ['code' => 200 , 'msg' => '查询成功','data'=>$ziyuan];
    }

	/*
     * @param  comment    课程评论
     * @param  参数说明
     *      user_token   用户token
     *      school_dns   网校域名
     *      course_id    课程id
     *      nature       课程类型    0自增 1授权
     *      content      内容
     *      score        等级       1  2  3  4  5 星
     * @param  author          sxh
     * @param  ctime           2020-10-29
     * return  array
     */
    public function comment(){
        //验证参数
        if(!isset($this->data['course_id'])||empty($this->data['course_id'])){
            return response()->json(['code' => 201, 'msg' => '课程id为空']);
        }
        if(!isset($this->data['content'])||empty($this->data['content'])){
            return response()->json(['code' => 201, 'msg' => '课程评论内容为空']);
        }
        if(!isset($this->data['nature']) || (!in_array($this->data['nature'],[0,1]))){
            return response()->json(['code' => 201, 'msg' => '课程类型有误']);
        }
       //一分钟内只能提交两次
            $time = date ( "Y-m-d H:i:s" , strtotime ( "-1 minute" ));
            $data = date ( "Y-m-d H:i:s" , time());
            $list = Comment::where(['school_id'=>$this->school['id'],'course_id'=>$this->data['course_id'],'nature'=>$this->data['nature'],'uid'=>$this->userid])->whereBetween('create_at',[$time,$data])->orderByDesc('create_at')->count();
            if($list>=2){
                return response()->json(['code' => 202, 'msg' => '操作太频繁,1分钟以后再来吧']);
            }
        //获取课程名称
        if($this->data['nature']==0){
            $course = Coures::where(['id'=>$this->data['course_id'],'is_del'=>0])->select('title','id as course_id')->first();
        }else if($this->data['nature']==1){
            $course = CourseSchool::where(['id'=>$this->data['course_id'],'is_del'=>0])->select('title','course_id')->first();
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
                'school_id'    => $this->school['id'],
                'status'       => 0,
                'course_id'    => $course['course_id'],
                'course_name'  => $course['title'],
                'nature'       => $this->data['nature'],
                'create_at'    => date('Y-m-d H:i:s'),
                'content'      => addslashes($this->data['content']),
                'uid'          => $this->userid,
                'score'        => empty($this->data['score']) ? 1 : $this->data['score'],
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

	/*
     * @param  commentList    课程评论列表
     * @param  参数说明
     *      user_token   用户token
     *      school_dns   网校域名
     *      course_id    课程id
     *      nature       课程类型    0自增 1授权
     *      page
     *      pagesize
     * @param  author          sxh
     * @param  ctime           2020-10-30
     * return  array
     */
    public function commentList(){
        try {
            //验证参数
            if(!isset($this->data['course_id'])||empty($this->data['course_id'])){
                return response()->json(['code' => 201, 'msg' => '课程id为空']);
            }
            if(!isset($this->data['nature'])){
                return response()->json(['code' => 201, 'msg' => '课程类型为空']);
            }
            //授权
            if($this->data['nature'] == 1){
                $this->data['course_id'] = CourseSchool::select('course_id')->where(['id'=>$this->data['course_id']])->first()['course_id'];
            }
			//获取总数
            $count_list = Comment::leftJoin('ld_student','ld_student.id','=','ld_comment.uid')
                ->leftJoin('ld_school','ld_school.id','=','ld_comment.school_id')
                ->where(['ld_comment.school_id' => $this->school['id'], 'ld_comment.course_id'=>$this->data['course_id'], 'ld_comment.status'=>1])
                ->count();
            //每页显示的条数
            $pagesize = isset($this->data['pagesize']) && $this->data['pagesize'] > 0 ? $this->data['pagesize'] : 20;
            $page     = isset($this->data['page']) && $this->data['page'] > 0 ? $this->data['page'] : 1;
            $offset   = ($page - 1) * $pagesize;

            //获取列表
            $list = Comment::leftJoin('ld_student','ld_student.id','=','ld_comment.uid')
                ->leftJoin('ld_school','ld_school.id','=','ld_comment.school_id')
                ->where(['ld_comment.school_id' => $this->school['id'], 'ld_comment.course_id'=>$this->data['course_id'], 'ld_comment.status'=>1])
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
     * 获取学生课程协议内容
     * @return array
     */
    public function getCourseAgreement()
    {
        $studentId = $this->userid;
        $schoolId = $this->data['user_info']['school_id'];
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
        $studentId = $this->userid;
        $schoolId = $this->data['user_info']['school_id'];
        $courseId = array_get($this->data, 'course_id', 0);
        $stepType = array_get($this->data, 'step_type', 0);
        $nature = array_get($this->data, 'nature', 0);

        //判断基础数据
        if (empty($studentId) || empty($schoolId) || empty($courseId) || ! in_array($stepType, [1, 2]) || ! in_array($nature, [0, 1])) {
            return ['code' => 204, 'msg' => '参数错误，请核实'];
        }

        return CourseAgreement::setCourseAgreement($schoolId, $studentId, $courseId, $nature, $stepType);

    }

    //判断二维数组中是否包含此值
    function deep_in_array($value, $array) {
        foreach($array as $item) {
            if(!is_array($item)) {
                if ($item == $value) {
                    return true;
                } else {
                    continue;
                }
            }
            if(in_array($value, $item)) {
                return true;
            } else if(deep_in_array($value, $item)) {
                return true;
            }
        }
        return false;
    }
}
