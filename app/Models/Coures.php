<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class Coures extends Model {
    //指定别的表名
    public $table = 'ld_course';
    //时间戳设置
    public $timestamps = false;
    //列表
    public static function courseList($data){
		
        //获取用户网校id
        $data['school_status'] = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        if(!isset($data['nature']) || empty($data['nature'])){
            //自增
            $count1 = self::where(['is_del'=>0])->where(function($query) use ($data,$school_id) {
                //判断总校 查询所有或一个分校
//                if($data['school_status'] == 1){
//                    if(!empty($data['school_id']) && $data['school_id'] != ''){
//                        $query->where('school_id',$data['school_id']);
//                    }
//                }else{
                    //分校查询当前学校
                    $query->where('school_id',$school_id);
//                }
                //学科大类
                if(!empty($data['coursesubjectOne']) && $data['coursesubjectOne'] != ''){
                    $query->where('parent_id',$data['coursesubjectOne']);
                }

                //学科小类
                if(!empty($data['coursesubjectTwo']) && $data['coursesubjectTwo'] != ''){
                    $query->where('child_id',$data['coursesubjectTwo']);
                }
                //状态
                if(!empty($data['status']) && $data['status'] != ''){
                    $query->where('status',$data['status']-1);
                }
            })->count();
            //授权
            $count2 = CourseSchool::where(['is_del'=>0])->where(function($query) use ($data,$school_id) {
                //判断总校 查询所有或一个分校
//                if($data['school_status'] == 1){
//                    if(!empty($data['school_id']) && $data['school_id'] != ''){
//                        $query->where('to_school_id',$data['school_id']);
//                    }
//                }else{
                    //分校查询当前学校
                    $query->where('to_school_id',$school_id);
//                }
                //学科大类
                if(!empty($data['coursesubjectOne']) && $data['coursesubjectOne'] != ''){
                    $query->where('parent_id',$data['coursesubjectOne']);
                }
                //学科小类
                if(!empty($data['coursesubjectTwo']) && $data['coursesubjectTwo'] != ''){
                    $query->where('child_id',$data['coursesubjectTwo']);
                }
                //状态
                if(!empty($data['status']) && $data['status'] != ''){
                    $query->where('status',$data['status']-1);
                }
            })->count();
            $count = $count1 + $count2;
        }else if($data['nature']-1 == 1){
            //授权
            $count = CourseSchool::where(['is_del'=>0])->where(function($query) use ($data,$school_id) {
                //判断总校 查询或一个分校
//                if($data['school_status'] == 1){
//                    if(!empty($data['school_id']) && $data['school_id'] != ''){
//                        $query->where('to_school_id',$data['school_id']);
//                    }
//                }else{
                    //分校查询当前学校
                    $query->where('to_school_id',$school_id);
//                }
                //学科大类
                if(!empty($data['coursesubjectOne']) && $data['coursesubjectOne'] != ''){
                    $query->where('parent_id',$data['coursesubjectOne']);
                }
                //学科小类
                if(!empty($data['coursesubjectTwo']) && $data['coursesubjectTwo'] != ''){
                    $query->where('child_id',$data['coursesubjectTwo']);
                }
                //状态
                if(!empty($data['status']) && $data['status'] != ''){
                    $query->where('status',$data['status']-1);
                }
            })->count();
        }else{
            //自增
            $count = self::where(['is_del'=>0])->where(function($query) use ($data,$school_id) {
                //判断总校 查询所有或一个分校
//                if($data['school_status'] == 1){
//                    if(!empty($data['school_id']) && $data['school_id'] != ''){
//                        $query->where('school_id',$data['school_id']);
//                    }
//                }else{
                    //分校查询当前学校
                    $query->where('school_id',$school_id);
//                }
                //学科大类
                if(!empty($data['coursesubjectOne']) && $data['coursesubjectOne'] != ''){
                    $query->where('parent_id',$data['coursesubjectOne']);
                }

                //学科小类
                if(!empty($data['coursesubjectTwo']) && $data['coursesubjectTwo'] != ''){
                    $query->where('child_id',$data['coursesubjectTwo']);
                }
                //状态
                if(!empty($data['status']) && $data['status'] != ''){
                    $query->where('status',$data['status']-1);
                }
            })->count();
        }
        $list=[];
        if($count > 0){
            if(!isset($data['nature']) || empty($data['nature'])){
                //全部
                $list1 = self::where(['is_del'=>0])->where(function($query) use ($data,$school_id) {
                    //判断总校 查询所有或一个分校
//                    if($data['school_status'] == 1){
//                        if(!empty($data['school_id']) && $data['school_id'] != ''){
//                            $query->where('school_id',$data['school_id']);
//                        }
////                    }else{
                        //分校查询当前学校
                        $query->where('school_id',$school_id);
//                    }
                    //学科大类
                    if(!empty($data['coursesubjectOne']) && $data['coursesubjectOne'] != ''){
                        $query->where('parent_id',$data['coursesubjectOne']);
                    }
                    //学科小类
                    if(!empty($data['coursesubjectTwo']) && $data['coursesubjectTwo'] != ''){
                        $query->where('child_id',$data['coursesubjectTwo']);
                    }
                    //状态
                    if(!empty($data['status']) && $data['status'] != ''){
                        $query->where('status',$data['status']-1);
                    }
                })
                    ->orderBy('id','desc')->get()->toArray();
                foreach($list1  as $k=>&$v){
					$list1[$k]['buy_num'] = Order::where(['nature'=>0,'status'=>2,'class_id'=>$v['id']])->count();
                    $where=[
                        'course_id'=>$v['id'],
                        'is_del'=>0
                    ];
                    if(!empty($data['method'])) {
                        $where['method_id'] = $data['method'];
                    }
                    $method = Couresmethod::select('method_id')->where($where)->get()->toArray();
                    if(empty($method)){
                        unset($list1[$k]);
                    }else{
                        foreach ($method as $key=>&$val){
                            if($val['method_id'] == 1){
                                $val['method_name'] = '直播';
                            }
                            if($val['method_id'] == 2){
                                $val['method_name'] = '录播';
                            }
                            if($val['method_id'] == 3){
                                $val['method_name'] = '其他';
                            }
                        }
                        $v['method'] = $method;
                    }
                }
                $list2 = CourseSchool::where(['is_del'=>0])->where(function($query) use ($data,$school_id) {
                    //判断总校 查询所有或一个分校
//                    if($data['school_status'] == 1){
//                        if(!empty($data['school_id']) && $data['school_id'] != ''){
//                            $query->where('to_school_id',$data['school_id']);
//                        }
//                    }else{
                        //分校查询当前学校
                        $query->where('to_school_id',$school_id);
//                    }
                    //学科大类
                    if(!empty($data['coursesubjectOne']) && $data['coursesubjectOne'] != ''){
                        $query->where('parent_id',$data['coursesubjectOne']);
                    }
                    //学科小类
                    if(!empty($data['coursesubjectTwo']) && $data['coursesubjectTwo'] != ''){
                        $query->where('child_id',$data['coursesubjectTwo']);
                    }
                    //状态
                    if(!empty($data['status']) && $data['status'] != ''){
                        $query->where('status',$data['status']-1);
                    }
                })
                    ->orderBy('id','desc')->get()->toArray();
                foreach($list2  as $ks=>&$vs){
					$list2[$ks]['buy_num'] = Order::where(['nature'=>1,'status'=>2,'class_id'=>$vs['id']])->count();
                    $vs['nature'] = 1;
                    $where=[
                        'course_id'=>$vs['course_id'],
                        'is_del'=>0
                    ];
                    if(!empty($data['method'])) {
                        $where['method_id'] = $data['method'];
                    }
                    $method = Couresmethod::select('method_id')->where($where)->get()->toArray();
                    if(!$method){
                        unset($list2[$ks]);
                    }else{
                        foreach ($method as $key=>&$val){
                            if($val['method_id'] == 1){
                                $val['method_name'] = '直播';
                            }
                            if($val['method_id'] == 2){
                                $val['method_name'] = '录播';
                            }
                            if($val['method_id'] == 3){
                                $val['method_name'] = '其他';
                            }
                        }
                        $vs['method'] = $method;
                    }
					$buy_nember = Order::whereIn('pay_status',[3,4])->where('nature',1)->where(['school_id'=>$school_id,'class_id'=>$vs['id'],'status'=>2,'oa_status'=>1])->count();
                        $sum_nember = CourseStocks::where(['school_pid'=>1,'school_id'=>$school_id,'course_id'=>$vs['course_id'],'is_del'=>0])->sum('add_number');
                        $list2[$k]['surplus'] = $sum_nember-$buy_nember <=0 ? 0 : $sum_nember-$buy_nember; 
						$list2[$k]['sum_nember'] = $sum_nember; 
                }
                $list =array_slice(array_merge($list1,$list2),($page - 1) * $pagesize, $pagesize);
            }else if($data['nature']-1 == 1){
                //授权
                $list = CourseSchool::where(['is_del'=>0])->where(function($query) use ($data,$school_id) {
                    //判断总校 查询所有或一个分校
//                    if($data['school_status'] == 1){
//                        if(!empty($data['school_id']) && $data['school_id'] != ''){
//                            $query->where('to_school_id',$data['school_id']);
//                        }
//                    }else{
                        //分校查询当前学校
                        $query->where('to_school_id',$school_id);
//                    }
                    //学科大类
                    if(!empty($data['coursesubjectOne']) && $data['coursesubjectOne'] != ''){
                        $query->where('parent_id',$data['coursesubjectOne']);
                    }
                    //学科小类
                    if(!empty($data['coursesubjectTwo']) && $data['coursesubjectTwo'] != ''){
                        $query->where('child_id',$data['coursesubjectTwo']);
                    }
                    //状态
                    if(!empty($data['status']) && $data['status'] != ''){
                        $query->where('status',$data['status']-1);
                    }
                })->orderBy('id','desc')
                    ->offset($offset)->limit($pagesize)->get()->toArray();
                    foreach($list  as $k=>&$v){
						$list[$k]['buy_num'] = Order::where(['nature'=>1,'status'=>2,'class_id'=>$v['id']])->count();
                        $v['nature'] = 1;
                        $where=[
                            'course_id'=>$v['course_id'],
                            'is_del'=>0
                        ];
                        if(!empty($data['method'])) {
                            $where['method_id'] = $data['method'];
                        }
                        $method = Couresmethod::select('method_id')->where($where)->get()->toArray();
                        if(!$method){
                            unset($list[$k]);
                        }else{
                            foreach ($method as $key=>&$val){
                                if($val['method_id'] == 1){
                                    $val['method_name'] = '直播-h';
                                }
                                if($val['method_id'] == 2){
                                    $val['method_name'] = '录播-h';
                                }
                                if($val['method_id'] == 3){
                                    $val['method_name'] = '其他-h';
                                }
                            }
                            $v['method'] = $method;
                        }
						//kucun
						$buy_nember = Order::whereIn('pay_status',[3,4])->where('nature',1)->where(['school_id'=>$school_id,'class_id'=>$v['id'],'status'=>2,'oa_status'=>1])->count();
                        $sum_nember = CourseStocks::where(['school_pid'=>1,'school_id'=>$school_id,'course_id'=>$v['course_id'],'is_del'=>0])->sum('add_number');
                        $list[$k]['surplus'] = $sum_nember-$buy_nember <=0 ? 0 : $sum_nember-$buy_nember; //剩余库存量
						$list[$k]['sum_nember'] = $sum_nember; //剩余库存量
						
                    }
            }else{
                //自增
                $list = self::where(['is_del'=>0])->where(function($query) use ($data,$school_id) {
                    //判断总校 查询所有或一个分校
//                    if($data['school_status'] == 1){
//                        if(!empty($data['school_id']) && $data['school_id'] != ''){
//                            $query->where('school_id',$data['school_id']);
//                        }
//                    }else{
                        //分校查询当前学校
                        $query->where('school_id',$school_id);
//                    }
                 
                    if(!empty($data['coursesubjectOne']) && $data['coursesubjectOne'] != ''){
                        $query->where('parent_id',$data['coursesubjectOne']);
                    }
                  
                    if(!empty($data['coursesubjectTwo']) && $data['coursesubjectTwo'] != ''){
                        $query->where('child_id',$data['coursesubjectTwo']);
                    }
                    //状态
                    if(!empty($data['status']) && $data['status'] != ''){
                        $query->where('status',$data['status']-1);
                    }
                })
                    ->orderBy('id','desc')
                    ->offset($offset)->limit($pagesize)->get()->toArray();
                foreach($list  as $k=>&$v){
					$list[$k]['buy_num'] = Order::where(['nature'=>0,'status'=>2,'class_id'=>$v['id']])->count();
                    $where=[
                        'course_id'=>$v['id'],
                        'is_del'=>0
                    ];
                    if(!empty($data['method'])) {
                        $where['method_id'] = $data['method'];
                    }
                    $method = Couresmethod::select('method_id')->where($where)->get()->toArray();
                    if(!$method){
                        unset($list[$k]);
                    }else{
                        foreach ($method as $key=>&$val){
                            if($val['method_id'] == 1){
                                $val['method_name'] = '直播';
                            }
                            if($val['method_id'] == 2){
                                $val['method_name'] = '录播';
                            }
                            if($val['method_id'] == 3){
                                $val['method_name'] = '其他';
                            }
                        }
                        $v['method'] = $method;
                    }
                }
            }
        }
        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        return ['code' => 200 , 'msg' => '查询成功','data'=>$list,'where'=>$data,'page'=>$page];
    }


    /**
     * @param  课程列表 为首页配置准备
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    public static function courseListByIndexSet($data, $schoolId){
        $school_id = $schoolId;
        //获取总数据
        $topNum = empty($data['top_num']) ? 1 : $data['top_num'];
        $isRecommend = empty($data['is_recommend']) ? 0 : $data['is_recommend'];

        //学科大类小类条件
        $courseSubjectOne = empty($data['coursesubjectOne']) ? 0 : $data['coursesubjectOne'];
        $courseSubjectTwo = empty($data['coursesubjectTwo']) ? 0 : $data['coursesubjectTwo'];
        //授课类型条件
        $methodWhere = isset($data['method']) ? $data['method']:0;

        $count = 0;
        //自增课程
        $course = Coures::select('id', 'title', 'cover' ,'describe', 'pricing','sale_price', 'buy_num', 'nature', 'watch_num', 'is_recommend','create_at')
            ->where(function ($query) use ($courseSubjectOne, $courseSubjectTwo) {
                if (! empty($courseSubjectOne)) {
                    $query->where('parent_id', $courseSubjectOne);
                }
                if (! empty($courseSubjectTwo)) {
                    $query->where('child_id', $courseSubjectTwo);
                }
            })
            ->where(['school_id' => $school_id, 'is_del' => 0, 'status' => 1])
            ->get()
            ->toArray();
        //自增课程
        if(! empty($course)) {

            //获取课的订单总数
            $classIdList = array_column($course, 'id');
            $orderCountList = Order::query()
                ->whereIn('class_id', $classIdList)
                ->where([
                    'nature' => 0,
                    'status' => 2
                ])
                ->whereIn('pay_status',[3,4])
                ->select(DB::raw("class_id, count(*) as total"))
                ->groupBy('class_id')
                ->get()
                ->toArray();
            $orderCountList = array_column($orderCountList, 'total', 'class_id');

            //课程的全部类型
            $methodList = [];
            $methodBaseList = Couresmethod::query()
                ->select('course_id','method_id')
                ->whereIn('course_id', $classIdList)
                ->where(['is_del' => 0])
                ->where(function ($query) use ($methodWhere) {
                    if (! empty($methodWhere)) {
                        $query->where('method_id', $methodWhere);
                    }
                })
                ->get()
                ->toArray();
            if (! empty($methodBaseList)) {
                foreach ($methodBaseList as $val) {
                    if ($val['method_id'] == 1) {
                        $val['method_name'] = '直播';
                    }
                    if ($val['method_id'] == 2) {
                        $val['method_name'] = '录播';
                    }
                    if ($val['method_id'] == 3) {
                        $val['method_name'] = '其他';
                    }
                    $methodList[$val['course_id']][] = $val;
                }
            }

            if (! empty($methodBaseList)) {

                foreach ($course as $k => &$v) {

                    if (empty($methodList[$v['id']])) {
                        unset($course[$k]);
                    } else {
                        if (! empty($orderCountList[$v['id']])) {
                            $v['buy_num'] = $v['buy_num'] + $orderCountList[$v['id']];
                        }
                        $v['course_id'] = $v['id'];
                        $v['method'] = $methodList[$v['id']];
                    }
                }
            } else {
                $course = [];
            }
        }

        //授权课程
        $ref_course = CourseSchool::query()
            ->select('id', 'title', 'cover' ,'describe', 'pricing','sale_price', 'buy_num', 'watch_num', 'is_recommend', 'create_at', 'course_id')
            ->where(function ($query) use ($courseSubjectOne, $courseSubjectTwo) {
                if (! empty($courseSubjectOne)) {
                    $query->where('parent_id', $courseSubjectOne);
                }
                if (! empty($courseSubjectTwo)) {
                    $query->where('child_id', $courseSubjectTwo);
                }
            })
            ->where(['to_school_id' => $school_id, 'is_del' => 0, 'status' => 1])
            ->get()
            ->toArray();

        if (! empty($ref_course)) {

            //获取课的订单总数
            $classIdList = array_column($ref_course, 'id');
            $orderCountList = Order::query()
                ->whereIn('class_id', $classIdList)
                ->where(['status' => 2, 'oa_status' => 1, 'school_id' => $school_id, 'nature' => 1])
                ->whereIn('pay_status',[3,4])
                ->select(DB::raw("class_id, count(*) as total"))
                ->groupBy('class_id')
                ->get()
                ->toArray();
            $orderCountList = array_column($orderCountList, 'total', 'class_id');

            //课程的全部类型
            $methodList = [];
            $methodBaseList = Couresmethod::query()
                ->select('course_id','method_id')
                ->whereIn('course_id', array_column($ref_course, 'course_id'))
                ->where(['is_del' => 0])
                ->where(function ($query) use ($methodWhere) {
                    if (! empty($methodWhere)) {
                        $query->where('method_id', $methodWhere);
                    }
                })
                ->get()
                ->toArray();
            if (! empty($methodBaseList)) {
                foreach ($methodBaseList as $val) {
                    if ($val['method_id'] == 1) {
                        $val['method_name'] = '直播';
                    }
                    if ($val['method_id'] == 2) {
                        $val['method_name'] = '录播';
                    }
                    if ($val['method_id'] == 3) {
                        $val['method_name'] = '其他';
                    }
                    $methodList[$val['course_id']][] = $val;
                }
            }


            if (! empty($methodBaseList)) {

                foreach ($ref_course as $k => &$v) {
                    if (empty($methodList[$v['course_id']])) {
                        unset($course[$k]);
                    } else {
                        if (! empty($orderCountList[$v['id']])) {
                            $v['buy_num'] = $v['buy_num'] + $orderCountList[$v['id']];
                        }
                        $v['nature'] = 1;
                        $v['method'] = $methodList[$v['course_id']];
                    }
                }
            } else {
                $ref_course = [];
            }
        }

        //两数组合并 排序
        if (!empty($course) && !empty($ref_course)) {
            $all = array_merge($course, $ref_course);//合并两个二维数组
        } else {
            $all = !empty($course) ? $course : $ref_course;
        }
        //sort 1最新2最热  默认最新
        $sort = isset($data['sort']) ? $data['sort'] : 1;
        if ($sort == 1) {
            $date = array_column($all, 'create_at');
            array_multisort($date, SORT_DESC, $all);
        } else if ($sort == 2) {
            $date = array_column($all, 'buy_num');
            array_multisort($date, SORT_DESC, $all);
        }
        if ($isRecommend == 1) {
            $isRecommendList = array_column($all, 'is_recommend');
            array_multisort($isRecommendList, SORT_DESC, $all);
        }

        $res = array_slice($all, 0, $topNum);
        if(empty($res)){
            $res = [];
        } else {
            foreach ($res as $key => &$item) {

                $curStudentList = [];
//                if ($item['nature'] == 1) {
//                     /*
//                     *  当前购买课程的学生列表
//                     */
//                    //获取前四个
//                    $buyList = Order::query()
//                        ->where([
//                            'class_id' => $item['id'],
//                            'status' => 2,
//                            'oa_status' => 1,
//                            'school_id' => $school_id,
//                            'nature' => 1
//                        ])
//                        ->whereIn('pay_status',[3,4])
//                        ->select('student_id')
//                        ->limit(4)
//                        ->get()
//                        ->toArray();
//
//                } else {
//                    /*
//                     *  当前购买课程的学生列表
//                     */
//                    //获取前四个
//                    $buyList = Order::query()
//                        ->where([
//                            'class_id' => $item['id'],
//                            'nature' => 0,
//                            'status' => 2
//                        ])
//                        ->whereIn('pay_status',[3,4])
//                        ->select('student_id')
//                        ->limit(4)
//                        ->get()
//                        ->toArray();
//
//
//                }
//                if (! empty($buyList)) {
//
//                    $studentIdList = array_column($buyList, 'student_id');
//
//                    //获取学生信息
//                    $studentList = Student::query()
//                        ->whereIn('id', $studentIdList)
//                        ->select('id', 'nickname', 'head_icon')
//                        ->get()
//                        ->toArray();
//                    //学生信息不为空
//                    if (! empty($studentList)) {
//                        $studentList = array_column($studentList, null, 'id');
//                        foreach ($studentIdList as $val) {
//                            if (! empty($studentList[$val])) {
//                                $curStudentList[] = $studentList[$val];
//                            }
//                        }
//                    }
//                }

                $item['student_list'] = $curStudentList;

            }

        }
        return ['code' => 200, 'msg' => '获取成功', 'data' => $res];
    }

    //添加
    public static function courseAdd($data){
		$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id)?AdminLog::getAdminInfo()->admin_user->school_id:0;
        if(empty($data) || !isset($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        if(!isset($data['parent']) || empty($data['parent'])){
            return ['code' => 201 , 'msg' => '请选择学科'];
        }
        if(!isset($data['title']) || empty($data['title'])){
            return ['code' => 201 , 'msg' => '学科名称不能为空'];
        }
        if(!isset($data['cover']) || empty($data['cover'])){
            return ['code' => 201 , 'msg' => '学科封面不能为空'];
        }
//        if(!isset($data['pricing']) || empty($data['pricing'])){
//            return ['code' => 201 , 'msg' => '请填写课程原价'];
//        }
//        if(!isset($data['sale_price']) || empty($data['sale_price'])){
//            return ['code' => 201 , 'msg' => '请填写课程优惠价'];
//        }
        $data['pricing'] = isset($data['pricing'])?$data['pricing']:0;
        $data['sale_price'] = isset($data['sale_price'])?$data['sale_price']:0;
        if(!isset($data['method']) || empty($data['method'])){
            return ['code' => 201 , 'msg' => '请选择授课方式'];
        }
        if(!isset($data['teacher']) || empty($data['teacher'])){
            return ['code' => 201 , 'msg' => '请选择讲师'];
        }
        if(!isset($data['describe']) || empty($data['describe'])){
            return ['code' => 201 , 'msg' => '课程描述不能为空'];
        }
        if(!isset($data['introduce']) || empty($data['introduce'])){
            return ['code' => 201 , 'msg' => '课程简介不能为空'];
        }
		//课程标题是否重复
        $title = self::where(['title'=>$data['title'],'is_del'=>0,'school_id'=>$school_id])->first();
        if($title){
            return ['code' => 201 , 'msg' => '课程名称已存在'];
        }
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id)?AdminLog::getAdminInfo()->admin_user->cur_admin_id:0;
        //入课程表
        DB::beginTransaction();
        try {
            $couser = self::addCouserGetId($data,$user_id);
            if($couser){
                //添加 课程授课表 课程讲师表
                self::addMethodAndTeacherInfo($data,$couser);
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $user_id  ,
                    'module_name'    =>  'courseAdd' ,
                    'route_url'      =>  'admin/Course/courseAdd' ,
                    'operate_method' =>  'add' ,
                    'content'        =>  '添加操作'.json_encode($data) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return ['code' => 200 , 'msg' => '添加成功'];
            }else{
                DB::rollback();
                return ['code' => 202 , 'msg' => '添加失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }
    }
	//入课程表
	private static function addCouserGetId($data,$user_id){
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id)?AdminLog::getAdminInfo()->admin_user->school_id:0;
        //入课程表
        $parent = json_decode($data['parent'],true);
        $couser = self::insertGetId([
            'admin_id' => $user_id,
            'school_id' => $school_id,
            'parent_id' => isset($parent[0])?$parent[0]:0,
            'child_id' => isset($parent[1])?$parent[1]:0,
            'title' => $data['title'],
            'keywords' => isset($data['keywords'])?$data['keywords']:'',
            'cover' => $data['cover'],
            'pricing' => isset($data['pricing'])?$data['pricing']:0,
            'sale_price' => isset($data['sale_price'])?$data['sale_price']:0,
            'buy_num' => isset($data['buy_num'])?$data['buy_num']:0,
            'expiry' => isset($data['expiry'])?$data['expiry']:24,
            'describe' => $data['describe'],
            'introduce' => $data['introduce'],
			'impower_price' => isset($data['impower_price'])?$data['impower_price']:0,
        ]);
        return $couser;
    }
	//添加 课程授课表 课程讲师表
    private static function addMethodAndTeacherInfo($data,$couser){
        $method = json_decode($data['method'],true);
        foreach ($method as $k=>$v){
            Couresmethod::insert([
                'course_id' => $couser,
                'method_id' => $v
            ]);
        }
        $teacher = json_decode($data['teacher'],true);
        foreach ($teacher as $k=>$v){
            Couresteacher::insert([
                'course_id' => $couser,
                'teacher_id' => $v
            ]);
        }
    }

    //删除
    public static function courseDel($data){
        if(empty($data) || !isset($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '请选择课程'];
        }
        if($data['nature'] == 1){
            return ['code' => 203, 'msg' => '授权课程，无法删除'];
        }
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        if($school_status == 1){
            // 总校删除 先查询是否有授权的，有再查询授权分校库存，没有进行删除
            $coursecount = CourseSchool::where(['course_id'=>$data['id'],'is_del'=>0])->count();
            if($coursecount > 0){
                return ['code' => 203, 'msg' => '此课程授权给分校，无法删除'];
            }
//            $courseSchool = CourseStocks::where('course_id',$data['id'])->where('add_number','>',0)->get()->toArray();
//            if(!empty($courseSchool)) {
//                return ['code' => 203, 'msg' => '此课程授权给分校，无法删除'];
//            }
        }
        $del = self::where(['id'=>$data['id']])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
        if($del){
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'courseDel' ,
                'route_url'      =>  'admin/Course/courseDel' ,
                'operate_method' =>  'courseDel' ,
                'content'        =>  '删除操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '删除成功'];
        }else{
            return ['code' => 201 , 'msg' => '删除失败'];
        }
    }
    //单条查询
    public static function courseFirst($data){
        if(empty($data) || !isset($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '请选择课程'];
        }
        $nature = isset($data['nature'])?$data['nature']:0;
        if($nature == 1){
            $find = CourseSchool::where(['id'=>$data['id'],'is_del'=>0])->first();
            if(!$find){
                return ['code' => 201 , 'msg' => '此数据不存在'];
            }
            $find['nature'] = 1;
            //查询授课方式
            $method= Couresmethod::select('method_id')->where(['course_id'=>$find['course_id'],'is_del'=>0])->get()->toArray();
            $find['method'] = array_column($method, 'method_id');
            $where = [];
            if($find['parent_id'] > 0){
                $where[0] = $find['parent_id'];
            }
            if($find['parent_id'] > 0 && $find['child_id'] > 0){
                $where[1] = $find['child_id'];
            }

            $find['parent'] = $where;
            unset($find['parent_id'],$find['child_id']);
            //查询讲师
            $teachers = $teacher = Couresteacher::select('teacher_id')->where(['course_id'=>$find['course_id'],'is_del'=>0])->get()->toArray();
            if(!empty($teachers)){
                foreach ($teachers as $k=>&$v){
                    $name = Lecturer::select('real_name')->where(['id'=>$v['teacher_id'],'is_del'=>0,'type'=>2])->first();
                    if(!empty($name)){
                        $v['real_name'] = $name['real_name'];
                    }else{
                        unset($teachers[$k]);
                    }
                }
            }
            $find['teacher'] = array_column($teacher, 'teacher_id');
            $find['teachers'] = $teachers;
        }else{
            $find = self::where(['id'=>$data['id'],'is_del'=>0])->first();
            if(!$find){
                return ['code' => 201 , 'msg' => '此数据不存在'];
            }
            //查询授课方式
            $method= Couresmethod::select('method_id')->where(['course_id'=>$find['id'],'is_del'=>0])->get()->toArray();
            $find['method'] = array_column($method, 'method_id');
            $where = [];
            if($find['parent_id'] > 0){
                $where[0] = $find['parent_id'];
            }
            if($find['parent_id'] > 0 && $find['child_id'] > 0){
                $where[1] = $find['child_id'];
            }
            $find['parent'] = $where;
            unset($find['parent_id'],$find['child_id']);
            //查询讲师
            $teachers = $teacher = Couresteacher::select('teacher_id')->where(['course_id'=>$data['id'],'is_del'=>0])->get()->toArray();
            if(!empty($teachers)){
                foreach ($teachers as $k=>&$v){
                    $name = Lecturer::select('real_name')->where(['id'=>$v['teacher_id'],'is_del'=>0,'type'=>2])->first();
                    $v['real_name'] = $name['real_name'];
                }
            }
            $find['teacher'] = array_column($teacher, 'teacher_id');
            $find['teachers'] = $teachers;
        }
        return ['code' => 200 , 'msg' => '查询成功','data'=>$find];
    }
    //修改
    public static function courseUpdate($data){
		$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id)?AdminLog::getAdminInfo()->admin_user->school_id:0;
        if(empty($data) || !isset($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
		//课程标题是否重复
        $title = self::where(['title'=>$data['title'],'is_del'=>0,'school_id'=>$school_id])->whereNotIn('id',[$data['id']])->first();
        if($title){
            return ['code' => 201 , 'msg' => '课程名称已存在'];
        }
        DB::beginTransaction();
        try{
                //修改 课程表 课程授课表 课程讲师表
                $cousermethod = isset($data['method'])?$data['method']:'';
                $couserteacher = isset($data['teacher'])?$data['teacher']:'';
                unset($data['/admin/course/courseUpdate']);
                unset($data['method']);
                unset($data['teacher']);
                unset($data['teachers']);
				//unset($data['impower_price']);
                $parent = json_decode($data['parent'],true);
                if(isset($parent[0]) && !empty($parent[0])){
                    $data['parent_id'] = $parent[0];
                }
                if(isset($parent[1]) && !empty($parent[1])){
                    $data['child_id'] = $parent[1];
                }
                unset($data['parent']);

                //判断自增还是授权
                $nature = isset($data['nature'])?$data['nature']:0;
                if($nature == 1){
                    //只修改基本信息
                    unset($data['nature']);


                    $data['update_at'] = date('Y-m-d H:i:s');
                    $id = $data['id'];
                    unset($data['id']);
                    unset($data['parent_id']);
                    unset($data['child_id']);
                    CourseSchool::where(['id'=>$id])->update($data);
                }else {
                    $data['update_at'] = date('Y-m-d H:i:s');
                    self::where(['id' => $data['id']])->update($data);
                    if (!empty($cousermethod)) {
                        Couresmethod::where(['course_id' => $data['id']])->update(['is_del' => 1, 'update_at' => date('Y-m-d H:i:s')]);
                        $method = json_decode($cousermethod, true);
                        foreach ($method as $k => $v) {
                            $infor = Couresmethod::where(['course_id' => $data['id'], 'method_id' => $v])->first();
                            if ($infor) {
                                Couresmethod::where(['id' => $infor['id']])->update(['is_del' => 0, 'update_at' => date('Y-m-d H:i:s')]);
                            } else {
                                Couresmethod::insert([
                                    'course_id' => $data['id'],
                                    'method_id' => $v
                                ]);
                            }
                        }
                    }
                    if (!empty($couserteacher)) {
                        Couresteacher::where(['course_id' => $data['id']])->update(['is_del' => 1, 'update_at' => date('Y-m-d H:i:s')]);
                        $teacher = json_decode($couserteacher, true);
                        foreach ($teacher as $k => $v) {
                            $infor = Couresteacher::where(['course_id' => $data['id'], 'teacher_id' => $v])->first();
                            if ($infor) {
                                Couresteacher::where(['id' => $infor['id']])->update(['is_del' => 0, 'update_at' => date('Y-m-d H:i:s')]);
                            } else {
                                Couresteacher::insert([
                                    'course_id' => $data['id'],
                                    'teacher_id' => $v
                                ]);
                            }
                        }
                    }
                }
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'courseUpdate' ,
                'route_url'      =>  'admin/Course/courseUpdate' ,
                'operate_method' =>  'Update' ,
                'content'        =>  '修改操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
        DB::commit();
        return ['code' => 200 , 'msg' => '修改成功'];
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //修改推荐状态
    public static function courseComment($data){
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => 'id为空'];
        }
        $nature = isset($data['nature'])?$data['nature']:0;
        if($nature == 1){
            $find = CourseSchool::where(['id'=>$data['id'],'is_del'=>0])->first();
            if($find){
                $recommend = $find['is_recommend'] == 1 ? 0:1;
                $up = CourseSchool::where(['id'=>$find['id']])->update(['is_recommend'=>$recommend,'update_at'=>date('Y-m-d H:i:s')]);
            }else{
                return ['code' => 201 , 'msg' => '课程未找到'];
            }
        }else{
            $find = self::where(['id'=>$data['id']])->first();
            $recommend = $find['is_recommend'] == 1 ? 0:1;
            $up = self::where(['id'=>$data['id']])->update(['is_recommend'=>$recommend,'update_at'=>date('Y-m-d H:i:s')]);
        }
        if($up){
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'courseComment' ,
                'route_url'      =>  'admin/Course/courseComment' ,
                'operate_method' =>  'update' ,
                'content'        =>  '修改推荐状态操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 201 , 'msg' => '修改失败'];
        }
    }
    //修改课程状态
    public static function courseUpStatus($data){
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '课程id不能为空'];
        }
        if(!isset($data['status']) || empty($data['status'])){
            return ['code' => 201 , 'msg' => '课程状态不能为空'];
        }
        $nature = isset($data['nature'])?$data['nature']:0;
        if($nature == 1){
            $up = CourseSchool::where('id',$data['id'])->update(['status'=>$data['status'],'update_at'=>date('Y-m-d H:i:s')]);
        }else{
            $up = self::where('id',$data['id'])->update(['status'=>$data['status'],'update_at'=>date('Y-m-d H:i:s')]);
        }
        if($up){
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'courseUpStatus' ,
                'route_url'      =>  'admin/Course/courseUpStatus' ,
                'operate_method' =>  'update' ,
                'content'        =>  '修改课程状态操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200, 'msg' => '操作成功'];
        }else{
            return ['code' => 202 , 'msg' => '操作失败'];
        }
    }
    //课程关联直播的列表
    public static function liveToCourseList($data){
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '课程id不能为空'];
        }
        $list = [];
        $first = [];
        $checked = [];
        $count = CourseLiveResource::where(['course_id'=>$data['id'],'is_del'=>0])->count();
        if($count > 0){
            $list = CourseLiveResource::where(['course_id'=>$data['id'],'is_del'=>0])->get()->toArray();
            foreach ($list as $k=>&$v){
                //if($v['shift_id'] == '' || $v['shift_id'] == null){
                //    continue;
                //}
				$shift_no = LiveClass::where(['resource_id'=>$v['resource_id'],'is_del'=>0,'is_forbid'=>0])->get()->toArray();
                if(count($shift_no)==0){
                    unset($list[$k]);
                }else{
                    array_push($first,$v['id']);
                }
                //array_push($first,$v['id']);
                $names = Live::select('name')->where(['id'=>$v['resource_id']])->first();
                $v['name'] = $names['name'];
                //$shift_no = LiveClass::where(['resource_id'=>$v['resource_id'],'is_del'=>0,'is_forbid'=>0])->get()->toArray();
                foreach ($shift_no as $ks=>&$vs){
                    if($ks == 0){
                        if($v['shift_id'] != ''){
                            array_push($checked,$v['shift_id']);
                        }else{
                            array_push($checked,$vs['id']);
                        }
                    }
                    //查询课次
                    $class_num = LiveChild::where(['shift_no_id'=>$vs['id'],'is_del'=>0,'status'=>1])->count();
                    //课时
                    $class_time = LiveChild::where(['shift_no_id'=>$vs['id'],'is_del'=>0,'status'=>1])->sum('class_hour');
                    $vs['class_num'] = $class_num;
                    $vs['class_time'] = $class_time;
                }
                $v['shift_no'] = $shift_no;
            }
        }
        return ['code' => 200 , 'msg' => '获取成功','data'=>$list,'first'=>$first,'checked'=>$checked];
    }
    //课程进行排课
    public static function liveToCourseshift($data){
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        if(!isset($data['first']) || empty($data['first'])){
            return ['code' => 201 , 'msg' => 'first参数为空'];
        }
        if(!isset($data['checked']) || empty($data['checked'])){
            return ['code' => 201 , 'msg' => 'checked参数为空'];
        }
        $first = json_decode($data['first'],true);
        $checked = json_decode($data['checked'],true);
        foreach ($first as $k=>$v){
            CourseLiveResource::where('id',$v)->update(['shift_id'=>$checked[$k],'update_at'=>date('Y-m-d H:i:s')]);
        }
        $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
        //添加日志操作
        AdminLog::insertAdminLog([
            'admin_id'       =>   $user_id  ,
            'module_name'    =>  'liveToCourseshift' ,
            'route_url'      =>  'admin/Course/liveToCourseshift' ,
            'operate_method' =>  'update' ,
            'content'        =>  '排课操作'.json_encode($data) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return ['code' => 200 , 'msg' => '修改成功'];
    }
    /*==============================转班================================================*/
    //单条订单购买的课程
    public static function consumerUser($data){
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        if(!isset($data['order_number']) || empty($data['order_number'])){
            return ['code' => 201 , 'msg' => 'order_number参数为空'];
        }
        $order = Order::where(['order_number'=>$data['order_number']])->first();
        if($order['nature'] == 1){
            $course = CourseSchool::where(['id'=>$order['class_id']])->first();
        }else{
            $course = Coures::where(['id'=>$order['class_id']])->first();
        }
        $order['course_cover'] = $course['cover'];
        $order['course_title'] = $course['title'];
        $student = Student::where(['id'=>$order['student_id']])->first();
        $order['real_name'] = $student['real_name'];
        $order['nickname'] = $student['nickname'];
        $order['reg_source'] = $student['reg_source'];
        $order['phone'] = $student['phone'];
        if($student['reg_source'] == 0){
            $order['reg_name'] = '官网注册';
        }
        if($student['reg_source'] == 1){
            $order['reg_name'] = '手机端';
        }
        if($student['reg_source'] == 2){
            $order['reg_name'] = '线下录入';
        }
        if($order['status'] == 0){
            $order['learning'] = "未支付";
            $order['bgcolor'] = '#26A4FD';
        }
        if($order['status'] == 1){
            $order['learning'] = "待审核";
            $order['bgcolor'] = '#FDA426';
        }
        if($order['status'] == 2){
            if($order['pay_status'] == 3 || $order['pay_status'] == 4){
                $order['learning'] = "已开课";
                $order['bgcolor'] = '#FF4545';
            }else{
                $order['learning'] = "尾款未结清";
                $order['bgcolor'] = '#FDA426';
            }
        }
        if($order['status'] == 3){
            $order['learning'] = "审核失败";
            $order['bgcolor'] = '#67C23A';
        }
        if($order['status'] == 4){
            $order['learning'] = "已退款";
            $order['bgcolor'] = '#f2f6fc';
        }
        if($order['status'] == 5){
            $order['learning'] = "以失效";
            $order['bgcolor'] = '#FF4545';
        }
        return ['code' => 200 , 'msg' => '获取成功','data'=>$order];
    }
    //课程详情
    public static function courseDetail($data){
        //传课程id  根据id 查询直播录播其他
        //nature  0自增1授权
        $nature = isset($data['nature'])?$data['nature']:0;
        if($nature == 1){
            $course = CourseSchool::where(['id'=>$data['id'],'is_del'=>0,'status'=>1])->first();
            $data['id'] = $course['course_id'];
        }else{
            $course = Coures::where(['id'=>$data['id'],'is_del'=>0,'status'=>1])->first();
        }
        $return = [];
        $methodstr ='';
        $method = Couresmethod::where(['course_id'=>$data['id'],'is_del'=>0])->get()->toArray();
        if(!empty($method)){
            foreach ($method as $methodk=>$methodv){
                if($methodv['method_id'] == 1){
                    $methodstr = $methodstr.'直播';
                    //课程关联的班号
                    $livearr = CourseLiveResource::where(['course_id'=>$data['id'],'is_del'=>0])->get();
                    if(!empty($livearr)){
                        foreach ($livearr as $livek=>$livev){
                            //查询直播单元表
                            $livename = Live::select('name as livename')->where(['id'=>$livev['resource_id'],'is_del'=>0])->where('is_forbid','<',2)->first();
                            $livename['type'] = '直播';
                            //查询课次表
                            if($livev['shift_id'] != '' && $livev['shift_id'] != null){
                                $shiftno = LiveClass::select('name')->where(['id'=>$livev['shift_id'],'is_del'=>0,'is_forbid'=>0])->first();
                                //查询课次
                                $class_num = LiveChild::where(['shift_no_id'=>$livev['shift_id'],'is_del'=>0,'status'=>1])->count();
                                //课时
                                $class_time = LiveChild::where(['shift_no_id'=>$livev['shift_id'],'is_del'=>0,'status'=>1])->sum('class_hour');
                                $shiftno['class_num'] = $class_num;
                                $shiftno['class_time'] = $class_time;
                                $livename['livearr'] = $shiftno;
                            }
                            $return['live'][] = $livename;
                        }
                    }
                }
                if($methodv['method_id'] == 2){
                    $lubo['recordedname'] = $course['title'];
                    $lubo['type'] = '录播';
                    $return['lubo'] = $lubo;
                    $methodstr = $methodstr.',录播';
                }
                if($methodv['method_id'] == 3){
                    $lubo['recordedname'] = $course['title'];
                    $lubo['type'] = '其他';
                    $return['rest'] = $lubo;
                    $methodstr = $methodstr.',其他';
                }
            }
            $return['methodtype'] = ltrim($methodstr,',');
        }
        return ['code' => 200 , 'msg' => '获取成功','data'=>$return];
    }
    /*
         * @param  订单
         * @param  order_number     原订单号
         * @param  pay_status     付款类型
         * @param  pay_type     付款方式
         * @param  price     付款金额
         * @param  pay_time     付款时间
         * @param  id     购买的课程
         * @param  nature     课程类型
         * @param  author  苏振文
         * @param  ctime   2020/7/31 16:16
         * return  array
         */
    public static function classTransfer($arr){
        //课程信息
        if($arr['nature'] == 1){
            $course = CourseSchool::where(['id'=>$arr['id'],'is_del'=>0,'status'=>1])->first()->toArray();
        }else{
            $course = Coures::where(['id'=>$arr['id'],'is_del'=>0,'status'=>1])->first()->toArray();
        }
        //原订单 状态变成5已失效  再新增订单
        $formerorder = Order::where(['order_number'=>$arr['order_number']])->first()->toArray();
        if($formerorder['status'] == 5){
            return ['code' => 201 , 'msg' => '订单失效'];
        }
        $bmcourse1 =  Order::select('class_id')->where(['student_id'=>$formerorder['student_id'],'status'=>2,'pay_status'=>3])->groupBy('class_id')->get()->toArray();
        $bmcourse2 = Order::select('class_id')->Where(['student_id'=>$formerorder['student_id'],'status'=>2,'pay_status'=>4])->groupBy('class_id')->get()->toArray();
        if(!empty($bmcourse1) && !empty($bmcourse2)){
            $bmcourse = array_merge($bmcourse1,$bmcourse2);
        }else{
            $bmcourse = !empty($bmcourse1)? $bmcourse1 : $bmcourse2;
        }
        if(!empty($bmcourse)){
            foreach ($bmcourse as $ks=>$vs){
                if($vs['class_id'] == $arr['id']){
                    return ['code' => 202 , 'msg' => '此课程已报名'];
                }
            }
        }
        Order::where(['order_number'=>$arr['order_number']])->update(['status'=>5]);
        //获取后端的操作员id
        $data['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;  //操作员id
        //根据用户id获得分校id
        $school = Student::select('school_id')->where('id',$formerorder['student_id'])->first();
        $data['order_number'] = date('YmdHis', time()) . rand(1111, 9999); //订单号  随机生成
        $data['order_type'] = 1;        //1线下支付 2 线上支付
        $data['student_id'] = $formerorder['student_id'];
        $data['price'] = $arr['price']; //实际付价格
        $data['student_price'] = $course['sale_price'];//学员价格
        $data['lession_price'] = $course['sale_price']; //课程价格
        $data['pay_status'] = $arr['pay_status']; //支付类型
        $data['pay_type'] = $arr['pay_type'];   //支付方式
        $data['status'] = 2;                  //支付状态
        $data['pay_time'] = $arr['pay_time']; //支付时间
        $data['oa_status'] = 1;             //OA状态
        $data['class_id'] = $arr['id'];  //课程id
        $data['school_id'] = $school['school_id'];
        $data['nature'] = $arr['nature'];  //课程类型
//        $data['validity_time'] = '';  //课程到期时间
        $data['parent_order_number'] = $arr['order_number'];  //转班订单号
        $add = Order::insert($data);
        if($add){
            if($arr['pay_status'] == 3 || $arr['pay_status'] == 4){
                //课程到期时间
                if($course['expiry'] == 0){
                    $validity_time = "3002-01-01 12:12:12";
                }else{
                    $validity_time = date("Y-m-d H:i:s",strtotime("+".$course['expiry']." day",strtotime($formerorder['create_at'])));
                }
                Order::where(['order_number'=>$data['order_number']])->update(['validity_time'=>$validity_time,'update_at' => date('Y-m-d H:i:s')]);
                $overorder = Order::where(['student_id'=>$formerorder['student_id'],'status'=>2])->count(); //用户已完成订单
                $userorder = Order::where(['student_id'=>$formerorder['student_id']])->count(); //用户所有订单
                if($overorder == $userorder){
                    $state_status = 2;
                }else{
                    if($overorder > 0 ){
                        $state_status = 1;
                    }else{
                        $state_status = 0;
                    }
                }
                Student::where(['id' => $formerorder['student_id']])->update(['enroll_status' => 1, 'state_status' => $state_status]);
//                Order::where(['order_number'=>$arr['order_number']])->update(['status'=>5]);
            }
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $data['admin_id']  ,
                'module_name'    =>  'Order' ,
                'route_url'      =>  'admin/Course/classTransfer' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '转班：'.$arr['order_number'].'转到'.$data['order_number'].',========传参：'.json_encode($arr),
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '转班成功'];
        }else{
            return ['code' => 201 , 'msg' => '转班失败'];
        }
    }
    //转班费用
    public static function coursePay($data){
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        if(!isset($data['order_number']) || empty($data['order_number'])){
            return ['code' => 201 , 'msg' => 'order_number参数为空'];
        }
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '课程id参数为空'];
        }
        $order = Order::where(['order_number'=>$data['order_number']])->first();
        $price1 = Order::where(['student_id'=>$order['student_id'],'class_id'=>$order['class_id'],'nature'=>$order['nature'],'status'=>1])->sum('price');
        $price2 = Order::where(['student_id'=>$order['student_id'],'class_id'=>$order['class_id'],'nature'=>$order['nature'],'status'=>2])->sum('price');
        $price = $price1 + $price2;
        if($data['nature'] == 1){
            $course = CourseSchool::where(['id'=>$data['id']])->first();
        }else {
            $course = Coures::where(['id' => $data['id']])->first();
        }
        $difference = $course['sale_price'] - $price;
        if($difference < 0){
            $difference = 0;
        }
        $arr=[
            'order_price' => $course['sale_price'],
            'price' => $difference,
            'original_course_price' => $order['lession_price'],
            'original_order_price' => $price
        ];
        return ['code' => 200 , 'msg' => '获取成功','data'=>$arr];

    }

	/*
        * @param  获取复制课程学科信息
        * @param  author  sxh
        * @param  ctime   2020/11/5
        * return  array
        */
    public static function getCopyCourseSubjectInfo($data){
        //获取分校id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //获取分类
        $course_subject = CouresSubject::where(['parent_id'=>0,'is_open'=>0,'is_del'=>0,'school_id'=>$school_id])->select('id','subject_name')->get();
        if($course_subject){
            $course_subject = $course_subject->toArray();
            foreach ($course_subject as $k => $v){
                $two = CouresSubject::where(['parent_id'=>$v['id'],'is_open'=>0,'is_del'=>0,'school_id'=>$school_id])->select('id','subject_name')->get();
                if($two){
                    $course_subject[$k]['two'] = $two->toArray();
                }else{
                    $course_subject[$k]['two'] = '';
                }
            }
            return ['code' => 200 , 'msg' => '获取课程学科成功','data'=>$course_subject];
        }else{
            return ['code' => 202 , 'msg' => '该分校没有课程分类,请先添加分类'];
        }
    }

    /*
        * @param  获取复制课程信息
        * @param  author  sxh
        * @param  ctime   2020/11/5
        * return  array
        */
    public static function getCopyCourseInfo($data){
		//获取网校id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
		//每页显示的条数
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //拆分学科分类
		$parent = '';

        if(isset($data['parent_id']) && !empty($data['parent_id'])){
            $parent = json_decode($data['parent_id'],true);
        }
        $list = self::where(['is_del'=>0,'school_id'=>$school_id])
			->where(function ($query) use ($data,$parent){
                //学科大类
                if(!empty($parent[0]) && $parent[0] != ''){
                    $query->where('parent_id',$parent[0]);
                }
                //学科小类
                if(!empty($parent[1]) && $parent[1] != ''){
                    $query->where('child_id',$parent[1]);
                }
                //课程标题
                if(!empty($data['course_title']) && $data['course_title'] != ''){
                    $query->where('title','like','%'.$data['course_title'].'%');
                }
        })->offset($offset)->limit($pagesize)->select('id','title','parent_id','child_id')->get();
        if(!empty($list)){
                $list = $list->toArray();
				foreach($list as $k => $v){
                    $list[$k]['parent_name'] = '';
                    $list[$k]['child_name'] = '';
                    $list[$k]['method_name'] = '';
                    $parent_id = CouresSubject::where(['is_del'=>0,'is_open'=>0,'id'=>$v['parent_id']])->select('subject_name')->first();
                    $child_id = CouresSubject::where(['is_del'=>0,'is_open'=>0,'id'=>$v['child_id']])->select('subject_name')->first();
                    if($parent_id){
                        $parent_id = $parent_id->toArray();
                        $list[$k]['parent_name'] = $parent_id['subject_name'];
                    }
                    if($child_id){
                        $child_id = $child_id->toArray();
                        $list[$k]['child_name'] = $child_id['subject_name'];
                    }
                    $course_method = Couresmethod::where(['course_id'=>$v['id'],'is_del'=>0])->select('method_id')->get();
                    if($course_method){
                        $course_method = $course_method->toArray();
                        foreach ($course_method as $key=>&$val){
                            if($val['method_id'] == 1){
                                $val['method_name'] = '直播';
                            }
                            if($val['method_id'] == 2){
                                $val['method_name'] = '录播';
                            }
                            if($val['method_id'] == 3){
                                $val['method_name'] = '其他';
                            }
                        }
                         $list[$k]['method_name'] = $course_method;
                    }
                }
                return ['code' => 200 , 'msg' => '获取课程学科成功' , 'data' => ['list' => $list , 'total' => count($list) , 'pagesize' => $pagesize , 'page' => $page]];
        }else{
                return ['code' => 200 , 'msg' => '获取课程学科成功','data'=>''];
        }

         return ['code' => 200 , 'msg' => '获取课程学科成功' , 'data' => ['list' => $list , 'total' => count($list) , 'pagesize' => $pagesize , 'page' => $page]];
    }

    /*
        * @param  复制课程
        * @param  $course_id  课程id
        * @param  author  sxh
        * @param  ctime   2020/11/4
        * return  array
        */
    public static function copyCourseInfo($data){
        //判断课程id
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '课程id为空'];
        }
        //获取课程列表  0 1 发布 未发布
        $course_list = self::where(['is_del'=>0,'id'=>$data['id']])->whereIn('status',[0,1])->first();
        if(!$course_list){
            return ['code' => 202 , 'msg' => '课程不存在或已删除'];
        }
		//判断课程分类
        if(!isset($data['parent']) || empty($data['parent'])){
            return ['code' => 201 , 'msg' => '课程分类为空'];
        }
        //判断课程标题
        if(!isset($data['title']) || empty($data['title'])){
            return ['code' => 201 , 'msg' => '课程标题为空'];
        }
        //判断课程封面
        if(!isset($data['cover']) || empty($data['cover'])){
            return ['code' => 201 , 'msg' => '课程封面为空'];
        }
        //判断授课方式
        if(!isset($data['method']) || empty($data['method'])){
            return ['code' => 201 , 'msg' => '授课方式为空'];
        }
        //判断课程讲师
        if(!isset($data['teacher']) || empty($data['teacher'])){
            return ['code' => 201 , 'msg' => '课程讲师为空'];
        }
        //判断课程藐视
        if(!isset($data['describe']) || empty($data['describe'])){
            return ['code' => 201 , 'msg' => '课程描述为空'];
        }
        //判断课程介绍
        if(!isset($data['introduce']) || empty($data['introduce'])){
            return ['code' => 201 , 'msg' => '课程介绍为空'];
        }
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id)?AdminLog::getAdminInfo()->admin_user->cur_admin_id:0;
		$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //插入课程数据
        DB::beginTransaction();
        try {
            $couser = self::addCouserGetId($data,$user_id);
            if($couser){
                //添加 课程授课表 课程讲师表
                self::addMethodAndTeacherInfo($data,$couser);
                //获取之前课程的类型
                $course_method = Couresmethod::where(['is_del'=>0,'course_id'=>$data['id']])->select('id','method_id')->get();
				
                if($course_method){
                    foreach($course_method as $k => $v){
                        if($v['method_id']==1){
                            $live = CourseLiveResource::where(['is_del'=>0,'course_id'=>$data['id']])
                                ->get();
                            if($live){
                                $live = $live->toArray();
                                foreach ($live as $k => $v){
                                    $resource[$k] = CourseLivecastResource::where(['is_del'=>0,'id'=>$v['resource_id']])->first()->toArray();
                                }
                            }
                            self::batchAddLiveResourceInfo($couser,$user_id,$live,$school_id);
                        }else if($v['method_id']==2){
                            $chapters = Coureschapters::where(['is_del'=>0,'course_id'=>$data['id']])->get();
                            if($chapters){
                                $chapters = $chapters->toArray();
								foreach ($chapters as $k => $v){
									$chapters[$k]['arr'] = Coureschapters::where(['is_del'=>0,'course_id'=>$course_list['id'],'parent_id'=>$v['id']])->get();
									foreach($chapters[$k]['arr'] as $ck=>$cs){
										$chapters[$k]['arr'][$ck]['material'] = Couresmaterial::where(['parent_id'=>$cs['id']])->get();
									}
								}
                                self::batchAddCourseSchaptersInfo($couser,$user_id,$chapters,$school_id);
                            }
                        }
                    }
                }
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $user_id  ,
                    'module_name'    =>  'copyCourseInfo' ,
                    'route_url'      =>  'admin/Course/copyCourseInfo' ,
                    'operate_method' =>  'add' ,
                    'content'        =>  '复制课程操作'.json_encode($data) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return ['code' => 200 , 'msg' => '添加成功'];
            }else{
                DB::rollback();
                return ['code' => 202 , 'msg' => '添加失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }
    }


    /*
        * @param  复制直播课程相关信息
        * @param  $couser          新课程id
        * @param  $user_id         admin用户id
        * @param  $live           直播资源关联课程表   ld_course_live_resource
        * @param  $resource       直播资源表          ld_course_livecast_resource
        * @param  author  sxh
        * @param  ctime   2020/11/4
        * return  array
        */
    private static function batchAddLiveResourceInfo($couser,$user_id,$live,$school_id){
        foreach ($live as $k=>$v){
            CourseLiveResource::insert([
                'resource_id' => $v['resource_id'],
                'course_id' => $couser,
                'shift_id' => $v['shift_id'],
                'is_del' => $v['is_del'],
                'create_at' => date('Y-m-d H:i:s'),
            ]);
        }
		/*foreach ($resource as $key=>$value){
            CourseLivecastResource::insert([
                'admin_id' => $user_id,
                'school_id' => $school_id,
                'parent_id' => $value['parent_id'],
                'child_id' => $value['child_id'],
                'name' => $value['name'],
                'introduce' => $value['introduce'],
                'is_del' => $value['is_del'],
                'nature' => $value['nature'],
                'is_forbid' => $value['is_forbid'],
                'create_at' => date('Y-m-d H:i:s'),
            ]);
        }*/
    }

    /*
        * @param  复制录播课程相关信息
        * @param  $couser          新课程id
        * @param  $user_id         admin用户id
        * @param  $chapters        录播资源表   ld_course_chapters
        * @param  author  sxh
        * @param  ctime   2020/11/4
        * return  array
        */
    private static function batchAddCourseSchaptersInfo($couser,$user_id,$chapters,$school_id){
        foreach ($chapters as $k=>$v){
            $id = Coureschapters::insertGetId([
                'admin_id' => $user_id,
                'school_id' => $school_id,
                'parent_id' => $v['parent_id'],
                'course_id' => $couser,
                'resource_id' => $v['resource_id'],
                'name' => $v['name'],
                'type' => $v['type'],
                'is_free' => $v['is_free'],
                'is_del' => $v['is_del'],
                'create_at' => date('Y-m-d H:i:s'),
            ]);
			 foreach ($v['arr'] as $ks => $vs){
                $cid = Coureschapters::insertGetId([
                    'admin_id' => $user_id,
                    'school_id' => $school_id,
                    'parent_id' => $id,
                    'course_id' => $couser,
                    'resource_id' => $vs['resource_id'],
                    'name' => $vs['name'],
                    'type' => $vs['type'],
                    'is_free' => $vs['is_free'],
                    'is_del' => $vs['is_del'],
                    'create_at' => date('Y-m-d H:i:s'),
                ]);
				foreach ($vs['material'] as $mk=>$mv){
                    Couresmaterial::insert([
                        'admin_id' => $user_id,
                        'school_id' => $school_id,
                        'parent_id' => $cid,
                        'course_id' => 0,
                        'type' => $mv['type'],
                        'material_name' => $mv['material_name'],
                        'material_size' => $mv['material_size'],
                        'material_url' => $mv['material_url'],
                        'is_del' => $mv['is_del'],
                        'mold' => 1,
                        'create_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

	/*
       * @param  复制录播课程相关信息
       * @param  $couser          课程id
       * @param  author  sxh
       * @param  ctime   2020/11/11
       * return  array
       */
    public static function courseScore($data){
        //获取网校id
        $school_id = AdminLog::getAdminInfo()->admin_user->school_id;
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '课程id不能为空'];
        }
        if(!isset($data['score']) || empty($data['score'])){
            return ['code' => 201 , 'msg' => '课程评分不能为空'];
        }
        if($school_id != 1){
            return ['code' => 201 , 'msg' => '中控课程不能评分'];
        }
        $up = self::where('id',$data['id'])->update(['score'=>$data['score'],'update_at'=>date('Y-m-d H:i:s')]);
        if($up){
            $user_id = AdminLog::getAdminInfo()->admin_user->id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'courseScore' ,
                'route_url'      =>  'admin/Course/courseScore' ,
                'operate_method' =>  'update' ,
                'content'        =>  '修改课程评分操作'.json_encode($data) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200, 'msg' => '操作成功'];
        }else{
            return ['code' => 202 , 'msg' => '操作失败'];
        }
    }
}
