<?php
namespace App\Models;

use Illuminate\Database\Concerns\BuildsQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\AdminLog;

class Course extends Model {
    //指定别的表名
    public $table      = 'ld_course';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   项目管理-添加课程方法
     * @param  参数说明       body包含以下参数[
     *     parent_id         项目id
     *     child_id          学科id
     *     course_name       课程名称
     *     course_price      课程价格
     *     is_hide           是否显示/隐藏
     * ]
     * @param author    dzj
     * @param ctime     2020-09-02
     * return string
     */
    public static function doInsertCourse($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断分类父级id是否合法
        if(!isset($body['parent_id']) || empty($body['parent_id']) || $body['parent_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }

        //判断分类子级id是否合法
        if(!isset($body['child_id']) || empty($body['child_id']) || $body['child_id'] <= 0){
            return ['code' => 202 , 'msg' => '学科id不合法'];
        }

        //判断课程名称是否为空
        if(!isset($body['course_name']) || empty($body['course_name'])){
            return ['code' => 201 , 'msg' => '请输入课程名称'];
        }

        //判断课程价格是否为空
        if(!isset($body['course_price'])){
            return ['code' => 201 , 'msg' => '请输入课程价格'];
        }

        //判断是否展示是否选择
        if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
            return ['code' => 202 , 'msg' => '展示方式不合法'];
        }

        //判断父级id是否在表中是否存在
        $is_exists_parentId = Project::where('id' , $body['parent_id'])->where('parent_id' , 0)->where('is_del' , 0)->count();
        if(!$is_exists_parentId || $is_exists_parentId <= 0){
            return ['code' => 203 , 'msg' => '此项目名称不存在'];
        }

        //判断子级id是否在表中是否存在
        $is_exists_childId = Project::where('id' , $body['child_id'])->where('parent_id' , $body['parent_id'])->where('is_del' , 0)->count();
        if(!$is_exists_childId || $is_exists_childId <= 0){
            return ['code' => 203 , 'msg' => '此学科名称不存在'];
        }

        //判断课程名称是否存在
        $is_exists = self::where('category_one_id' , $body['parent_id'])->where('category_tow_id' , $body['child_id'])->where('course_name' , $body['course_name'])->where('is_del' , 0)->count();
        if($is_exists && $is_exists > 0){
            return ['code' => 203 , 'msg' => '此课程名称已存在'];
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //组装课程数组信息
        $course_array = [
            'category_one_id'     =>   isset($body['parent_id']) && $body['parent_id'] > 0 ? $body['parent_id'] : 0 ,
            'category_tow_id'     =>   isset($body['child_id']) && $body['child_id'] > 0 ? $body['child_id'] : 0 ,
            'course_name'         =>   $body['course_name'] ,
            'price'               =>   $body['course_price'] ,
            'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
            'admin_id'            =>   $admin_id ,
            'create_time'         =>   date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();
        try {
            //将数据插入到表中
            if(false !== self::insertGetId($course_array)){
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '添加成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '添加失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }

    }

    /*
     * @param  description   项目管理-修改课程方法
     * @param  参数说明       body包含以下参数[
     *     course_id         课程id
     *     course_name       课程名称
     *     course_price      课程价格
     *     is_hide           是否显示/隐藏
     *     is_del            是否删除(是否删除1已删除)
     * ]
     * @param author    dzj
     * @param ctime     2020-09-02
     * return string
     */
    public static function doUpdateCourse($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断课程id是否合法
        if(!isset($body['course_id']) || empty($body['course_id']) || $body['course_id'] <= 0){
            return ['code' => 202 , 'msg' => '课程id不合法'];
        }

        //判断课程名称是否为空
        if(!isset($body['course_name']) || empty($body['course_name'])){
            return ['code' => 201 , 'msg' => '请输入课程名称'];
        }

        //判断课程价格是否为空
        if(!isset($body['course_price'])){
            return ['code' => 201 , 'msg' => '请输入课程价格'];
        }

        //判断是否展示是否选择
        if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
            return ['code' => 202 , 'msg' => '展示方式不合法'];
        }

        //判断此课程得id是否存在此课程
        $is_exists_course = self::where('id' , $body['course_id'])->first();
        if(!$is_exists_course || empty($is_exists_course)){
            return ['code' => 203 , 'msg' => '此课程不存在'];
        }

        //判断课程名称是否存在
        $is_exists = self::where('category_one_id' , $is_exists_course['category_one_id'])->where('category_tow_id' , $is_exists_course['category_tow_id'])->where('course_name' , $body['course_name'])->where('is_del' , 0)->count();
        if($is_exists && $is_exists > 0){
            //组装课程数组信息
            $course_array = [
                'price'               =>   $body['course_price'] ,
                'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                'is_del'              =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                'update_time'         =>   date('Y-m-d H:i:s')
            ];
        } else {
            //组装课程数组信息
            $course_array = [
                'course_name'         =>   $body['course_name'] ,
                'price'               =>   $body['course_price'] ,
                'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                'is_del'              =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                'update_time'         =>   date('Y-m-d H:i:s')
            ];
        }

        //开启事务
        DB::beginTransaction();
        try {
            //根据课程id更新信息
            if(false !== self::where('id',$body['course_id'])->update($course_array)){
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '修改成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '修改失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }

    }

    /*
     * @param  description   项目管理-项目/学科详情方法
     * @param  参数说明       body包含以下参数[
     *     course_id         课程id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function getCourseInfoById($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断课程id是否合法
        if(!isset($body['course_id']) || empty($body['course_id']) || $body['course_id'] <= 0){
            return ['code' => 202 , 'msg' => '课程id不合法'];
        }

        //根据id获取课程的详情
        $info = self::select('course_name','price','is_hide','is_del')->where('id' , $body['course_id'])->where('is_del' , 0)->first();
        if($info && !empty($info)){
            return ['code' => 200 , 'msg' => '获取详情成功' , 'data' => $info];
        } else {
            return ['code' => 203 , 'msg' => '此课程不存在或已删除'];
        }
    }

    /*
     * @param  description   项目管理-课程列表接口
     * @param  参数说明       body包含以下参数[
     *     parent_id        项目id
     *     child_id         学科id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-03
     * return string
     */
    public static function getCourseList($body=[]){
        //判断项目的id是否为空
        if(!isset($body['parent_id']) || $body['parent_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }

        //判断学科id是否传递
        if(!isset($body['child_id']) || $body['child_id'] <= 0){
            //通过项目的id获取课程列表
            $course_list = self::select('id as course_id' , 'course_name' , 'course_name as label' , 'id as value')->where('category_one_id' , $body['parent_id'])->where('is_del' , 0)->get();
        } else {
            //通过项目的id获取课程列表
            $course_list = self::select('id as course_id' , 'course_name' , 'course_name as label' , 'id as value')->where('category_one_id' , $body['parent_id'])->where('category_tow_id' , $body['child_id'])->where('is_del' , 0)->get();
        }
        return ['code' => 200 , 'msg' => '获取课程列表成功' , 'data' => $course_list];
    }

    /*
     * @param  description   项目管理-课程列表接口
     * @param author    dzj
     * @param ctime     2020-09-15
     * return string
     */
    public static function getCourseAllList($body=[]){
        //通过项目的id获取课程列表
        $course_list = self::select('course_name as label' , 'id as value')->where('is_del' , 0)->get();
        return ['code' => 200 , 'msg' => '获取课程列表成功' , 'data' => $course_list];
    }


    public  static  function  getClassTimetableByDate($student_id,$school_id, string $start_date=null,$day_limit=7,$platform = "app"){
        $date = date('Y-m-d H:i:s');

        // 默认传递的是年月日的格式 这里把他变成 时间戳 计算该时间戳所在的 周的每一天的时间戳
        $day_time_span = strtotime($start_date);
        if($day_limit == 7){
            $date_day_list = GetWeekDayTimeSpanList($day_time_span);
        }else {
            // 默认
            $date_day_list = GetMonthDayTimeSpanList($day_time_span, true);
        }


        // 首先查新 订单信息 date 必须 大于今天 表示 课程到期时间
        $order = Order::where(['student_id'=>$student_id,'status'=>2,'school_id'=>$school_id])
            ->where('validity_time','>',$date)
            ->whereIn('pay_status',[3,4])
            ->get();

        $courses = [];
        //  根据查到的订单信息 中 的 calss_id 来查询 课程信息
        if(!empty($order)){

            $time_table = array();
            foreach ($order as $k=>$v){
                // 判断 是否是书券课程 如果是授权课程那么 从授权课程表中获取到课程信息
                if($v['nature'] == 1){
                    $course = CourseSchool::where(['id'=>$v['class_id'],'is_del'=>0,'status'=>1])->first();
                    if(!empty($course)){

                        $course['nature'] = 1;
                        $clsss_timetable_info = self::getCourseTimeTable($course[ 'course_id'],$course,head($date_day_list),
                            end($date_day_list),1,$v['class_id']);

                        //$time_table = array_merge($time_table,$clsss_timetable_info);
                        foreach ($clsss_timetable_info as $key=>$value){
                            if(!isset($time_table[$key]))$time_table[$key]=array();
                            $time_table[$key] = array_merge($time_table[$key],$value);
                        }
                    }
                }else {
                    // 如果是自增课程那么从自增课程中查询信息
                    $course = Coures::where(['id' => $v['class_id'], 'is_del' => 0, 'status' => 1])->first();
                    if (!empty($course)) {
                        $course = $course->toArray();
                        $course['nature'] = 0;

                        // 授权课程 从 授权课程信息中 的
                        $clsss_timetable_info = self::getCourseTimeTable($course[ 'id'],$course,head($date_day_list),
                            end($date_day_list),0);
                        foreach ($clsss_timetable_info as $key=>$value){
                            if(!isset($time_table[$key]))$time_table[$key]=array();
                            $time_table[$key] = array_merge($time_table[$key],$value);
                        }

                    }
                }
            }

        }

        // 按照数据格式组织格式信息
        $ret_data = array();
        foreach ($date_day_list as $day){
            $item ['time'] = $day;
            // 格式化  星期 的 字符串
            $weekarray = array("日","一","二","三","四","五","六"); //先定义一个数组
            $week_str=  "周".$weekarray[date("w",$day)];
            $item ['time_format']  = date("Y年m月d号", $day)." ". $week_str;
            $item ['time_format_for_web']  = date("Y-m-d", $day);

            // 将查询到的 课次顺序 添加 进去
            if (isset($time_table[$day])){
                $item['course_list'] = $time_table[$day];
            }else{
                $item['course_list'] = array();
            }

            $ret_data[] = $item;
        }

        return $ret_data;
    }


    static function getCourseTimeTable($course_id, $course_info, int $start_at=0, int $end_at=0,int $nature = 0,$nature_course_id= 0)
    {

        //print_r(" query course id:" . $course_id . PHP_EOL);
        $count = CourseLiveResource::where([ 'course_id' => $course_id, 'is_del' => 0 ])->count();
        if ($count <= 0) {
            return array();
        }
        //获取所有的班号
        $courseArr = CourseLiveResource::select('shift_id')->where([ 'course_id' => $course_id, 'is_del' => 0 ])->get()->toArray();
        $timeTable = [];

        foreach ($courseArr as $k => &$v) {

            //获取所有的课次 这里把时间加入进入 这样无论课次中的符合这个时间的课次就会被查询到
            $classci = LiveChild::query()-> where([ 'shift_no_id' => $v[ 'shift_id' ], 'is_del' => 0, 'status' => 1 ]);

            //  设定 课次的查询时间
            if(!empty($start_at) and !empty($end_at)){
                $classci ->whereBetween("start_at",[$start_at,$end_at]);
            }
            $classci = $classci->orderBy("start_at") ->get()->toArray();
            if (!empty($classci)) {

                //课次关联讲师  时间戳转换   查询所有资料
                foreach ($classci as $ks => $vs) {
                    $item = array();
                    $CourseLiveClassChild = CourseLiveClassChild::where(["class_id"=>$vs['id']])->first();
                    ;
                    list($item, $day_span) = self::formatTimeTableItem($vs,$CourseLiveClassChild, $item, $course_info, $course_id, $nature,$nature_course_id);

                    $timeTable[ $day_span ][] = $item;
                }
            }
        }

        //  按照 周的格式

        // 返回课程的课次信息
        return $timeTable;

    }



    /**
     *  通过 roomid 获取到对应学校信息
     *   room id 到对应 学校 到对应的 班号
     *   判断课程信息是否是自增课程或者授权课程
     *   这里值判断 直播间的
     * @param $room_id
     */
    public static function getSchoolInfoForRoomId( $room_id ){
        // 查询 直播间对应的课程信息
        $live_info = CourseLiveClassChild::where(['course_id' => $room_id])->first();

        // 查找不到任何一门课程的信息那么返回 false
        if(empty($live_info)){
            return false;
        }
        // 根据课次信息  找到班次信息 其中包含了班次关联的课程信息 shift_no_id 和 school_id
        $shift_no_info = CourseClassNumber::query()->where("id","=",$live_info->class_id)->first();
        if(empty($shift_no_info)){
            return  false;
        }

        // 通过school_id 获取到school_info
        $school_info = School::query()->where("id","=",$shift_no_info->school_id)->first();
        if(!empty($school_info)){
            return  $school_info->toArray();
        }
        return false;

//        // 查询出班次的等信息 通过 上面的 shift_no_id 查询 school_id 和 resource_id
//        $course_shift_no_info = CourseShiftNo::query()
//            ->where("id","=",$shift_no_info->shift_no_id)
//            ->where("school_id","=",$shift_no_info->school_id)
//            ->first();
//        if(empty($course_shift_no_info)){
//            return  false;
//        }
//
//        // 查询对应的课程和资源对应情况 通过 上面的 resource_id 获取到 school_id 和 course_id
//        $course_live_resource_info = CourseLivesResource::query()->where("resource_id","=",$course_shift_no_info-> resource_id)->first();
//        if(empty($course_live_resource_info)){
//            return  false;
//        }
//
//        // 最终我们去授权课程信息尝试获取到 课程信息 判断是否是 授权课程
//
//        $course_school_info = CourseSchool::query()
//            ->where("to_school_id","=",$shift_no_info->school_id)
//            ->where("course_id","=",$course_live_resource_info->course_id)->first();
//        if(!empty($course_school_info)){
//            return  false;
//        }
//
//        return  $course_school_info;










    }


    /**
     *  通过 roomid 获取到对应学校信息
     *   room id 到对应 学校 到对应的 班号
     *   判断课程信息是否是自增课程或者授权课程
     * @param $room_id
     * @param $student_id
     * @return Model|object|static|null
     */
    public static function getCourseInfoForRoomIdAndStudentId($room_id,$student_id,$with_del_live=false)
    {
       $course_info = Self::getCourseInfoForRoomId($room_id,$with_del_live);

       if (is_string($course_info)){
          //print_r($course_info.PHP_EOL);
           return  array();
       }
       if(isset($course_info['live_info'])){
           $live_info = $course_info['live_info'];
           $share_course_ids = $live_info['ret_course_ids_share'];
       }

        $order_mod = new Order();
        $list = $order_mod ->CheckOrderSchoolIdWithStudent($course_info,$student_id,$share_course_ids);

        if(empty($list)){

            print_r(" 无法通过没有找到订单 so skip it ！！".PHP_EOL);
           return  array();
        }

        $ret = [];
        //$school_course_info[ 'school_id' ], $school_course_info[ 'course_id' ],
        foreach ($list  as $key=>$value){
            $ret[]=array(
                'school_id' => $value['school_id'],
                "course_id" => $value['class_id']
            );

        }


        return $ret;
    }


    /**
     *  通过 roomid 获取到对应学校信息
     *   room id 到对应 学校 到对应的 班号
     *   判断课程信息是否是自增课程或者授权课程
     * @param $room_id
     * @return false|string|array
     */
    public static function getCourseInfoForRoomId($room_id ,$with_del=false)
    {
        // 查询 直播间对应的课程信息
        $live_info = CourseLiveClassChild::where([ 'course_id' => $room_id ])->first();

        // 查找不到任何一门课程的信息那么返回 false
        if (empty($live_info)) {
            // return "没有找到课次信息";
            return array();
        }
//        echo "直播资源信息:信息 " . PHP_EOL;
//        print_r($live_info->toArray());

        // 根据课次信息  找到班次信息 其中包含了班次关联的课程信息 shift_no_id 和 school_id
        $shift_no_info = CourseClassNumber::query()->where("id", "=", $live_info->class_id)->first();
        if (empty($shift_no_info)) {
            print "没有找到班次信息";
            return array();
        }
////
//        echo "班号资源信息:信息 " . PHP_EOL;
//        print_r($shift_no_info->toArray());

        // 查询出班次的等信息 通过 上面的 shift_no_id 查询 school_id 和 resource_id
        $course_shift_no_info = CourseShiftNo::query()
            ->where("id", "=", $shift_no_info->shift_no_id)
            ->where("school_id", "=", $shift_no_info->school_id)
            ->first();
        if (empty($course_shift_no_info)) {
            //print  "没有找到班号信息";
            return  array();
        };
//        echo "班次资源信息:信息 " . PHP_EOL;
//        print_r($course_shift_no_info->toArray());

        //bugfix 这里需要注意一下 直播资源有可能删除  添加过滤的条件
        $course_live_resource_info = CourseLiveResource::query();
        if($with_del == false){
            $course_live_resource_info ->where("is_del","=", 0);
        }
        $course_live_resource_info = $course_live_resource_info ->where("resource_id", "=", $course_shift_no_info->resource_id)->get();

        if (empty($course_live_resource_info  ->count())) {
            // return "找不到班次关联课程信息";
            return  array();
        }
//
//        echo "班次 关联课程 信息:信息::" . PHP_EOL;
//        print_r($course_live_resource_info->toArray());


        $course_live_cast_info_query =  CourseLivecastResource::query();
        $course_live_cast_info_query->where("id","=",$course_shift_no_info->resource_id) ->where("is_forbid","=",1);
        $course_live_cast_info = $course_live_cast_info_query->get();

        if (empty($course_live_cast_info)){
            return  "找不到班号对应的直播单元 或者给直播单元已经被禁用";
        }

//        echo "班次 关联 直播单元（资源）信息:信息::" . PHP_EOL;
//        print_r($course_live_cast_info->toArray());



        $ret_course_ids=array();

        //  兼容老的 数据  有些 course 在早期的 时候  既是自增课程 又是授权课程
        $ret_course_ids_share=array();

        // 这里需要 进行 两次查询 这一次 查询
        foreach ($course_live_resource_info as $course_live_info) {

            $ret_course_ids_share[] =$course_live_info["course_id"];

//            print_r("try to get course_id ".$course_live_info["course_id"]."".PHP_EOL);

            $course_info = Course::query()
                ->where("id", "=", $course_live_info[ 'course_id' ])
                ->where("is_del","=",0)
                ->where("school_id","=",1)->get();
            if(!empty($course_info)){
//                // 如果是 授权的课程 用这个id 去 courseSchool 表中查询 数据
//                print_r("id:".$course_live_info[ 'course_id' ]."是授权的课程".PHP_EOL);

                $course_school_info = CourseSchool::query()
                    ->where("course_id", "=", $course_live_info[ 'course_id' ])
                    ->where("is_del","=",0)
                    ->orderBy("to_school_id")->get();
                foreach ($course_school_info as $item){
//                    print_r("try to get course_id  ".$course_live_info["course_id"]." for course_school  id school_id: ".$item['to_school_id']."  course_school_course: ".$item["id"] .PHP_EOL);
//                    print_r($item->title.PHP_EOL);
                      $ret_course_ids[] =  array(
                        "school_id" =>$item['to_school_id'],
                        "course_id" => $item["id"]
                    );
                }


            }else{
                // 如果是分校的自增课程
                print_r("id:".$course_live_info[ 'course_id' ]."是分校".$course_info['school_id']."的自增课程".PHP_EOL);
                print_r($course_info->title.PHP_EOL);
                $ret_course_ids[] =  array(
                    "school_id" =>$course_info['school_id'],
                    "course_id" => $course_info["id"]
                );

            }

        }

//        print_r("最终得到全部的 学校和课次信息 ".PHP_EOL);
//        print_r($ret_course_ids);

        //这里 绑定 一下 课次信息
        $ret_course_ids["live_info"] = $live_info->toArray();
        $ret_course_ids["live_info"] ["ret_course_ids_share"]= array_unique($ret_course_ids_share);

        return  $ret_course_ids;

    }

    /**
     * @param $course_class_info
     * @param $live_info
     * @param $item
     * @param $course_info
     * @param $course_id
     * @param int $nature
     * @param int $nature_course_id
     * @return array
     */
    public static function formatTimeTableItem($course_class_info, $live_info,$item, $course_info, $course_id, int $nature,$nature_course_id = 0): array
    {

        // tearche_name: "小马讲师" //主讲老师姓名
        //teacher_img:"url"//教师头像

        //查询讲师 // 这里 去掉 讲师的 状态 是 禁止 的 状态的 情况
        $teacher = LiveClassChildTeacher::leftJoin('ld_lecturer_educationa', 'ld_lecturer_educationa.id', '=', 'ld_course_class_teacher.teacher_id')
            ->where([ 'ld_course_class_teacher.is_del' => 0, 'ld_lecturer_educationa.is_del' => 0, 'ld_lecturer_educationa.type' => 2,  ])
            ->where([ 'ld_course_class_teacher.class_id' => $course_class_info[ 'id' ] ])
            ->first();

        if (!empty($teacher)) {
            $item[ 'teacher_name' ] = $teacher[ 'real_name' ];
            $item[ 'teacher_img' ] = isset($teacher[ 'teacher_icon' ]) ? $teacher[ 'teacher_icon' ] : "";
        };

        //course_name:"课程名称-课次名称" //课程名称（课程名称-课次）
        $item[ 'course_name' ] = $course_info['title'] . "--" . $course_class_info[ 'name' ];


        //"course_time_start": "开始时间戳",
        //"course_time_end": "结束时间戳",
        //"course_time_format": "2020年11月12日 19:00 —— 2020年11月12日 21:00",
        $ymd = date('Y-m-d', $course_class_info[ 'start_at' ]);//年月日
        $start = date('H:i', $course_class_info[ 'start_at' ]);//开始时分
        $end = date('H:i', $course_class_info[ 'end_at' ]);//结束时分
        $weekarray = [ "周日", "周一", "周二", "周三", "周四", "周五", "周六" ];
        $start_at_date = date("w", $course_class_info[ 'start_at' ]);
        $week = $weekarray[ $start_at_date ];
        $item[ 'course_time_format' ] = $ymd . ' ' . $week . ' ' . $start . '-' . $end;   //开课时间戳 start_at 结束时间戳转化 end_at
        $item[ 'course_time_format_h5' ] = $start . '-' . $end;   //开课时间戳 start_at 结束时间戳转化 end_at
        $item[ 'course_time_start' ] = $course_class_info[ 'start_at' ];
        $item[ 'course_time_end' ] = $course_class_info[ 'end_at' ];


        // "course_status": "未开始/已经结束",
        //判断课程直播状态  1未直播2直播中3回访
        $item[ 'course_status' ] = $live_info[ 'status' ];
        // "course_class_count": 1,
        // "course_id": 1

        $item[ 'course_id' ] = $course_id;
        if ($nature == 1){
            $item[ 'course_id' ] = $nature_course_id;
        }
        $item[ 'nature' ] = $nature;
        $day_span = strtotime(date("Y-m-d", $course_class_info[ 'start_at' ]));
        return array( $item, $day_span );
    }


    /**
     * @param $msg_info
     * @param $Live_info
     * @param $item
     * @param $course_info
     * @param $course_id
     * @param int $nature
     * @return array
     */
    public static function formatMessageItem($msg_info, $Live_info, $item, $course_info, $course_id, int $nature): array
    {
        // tearche_name: "小马讲师" //主讲老师姓名
        //teacher_img:"url"//教师头像

        //查询讲师 // 这里 去掉 讲师的 状态 是 禁止 的 状态的 情况
        $teacher = LiveClassChildTeacher::leftJoin('ld_lecturer_educationa', 'ld_lecturer_educationa.id', '=', 'ld_course_class_teacher.teacher_id')
            ->where([ 'ld_course_class_teacher.is_del' => 0, 'ld_lecturer_educationa.is_del' => 0, 'ld_lecturer_educationa.type' => 2, ])
            ->where([ 'ld_course_class_teacher.class_id' => $Live_info[ 'class_id' ] ])
            ->first();

        if (!empty($teacher)) {
            $item[ 'teacher_name' ] = $teacher[ 'real_name' ];
            $item[ 'teacher_img' ] = isset($teacher[ 'teacher_icon' ]) ? $teacher[ 'teacher_icon' ] : "";
        };

        //course_name:"课程名称-课次名称" //课程名称（课程名称-课次）
        $item[ 'course_name' ] = $course_info['title']."--". $Live_info[ 'course_name' ];

        // "id":123
        // "msg_type": "1",
        // "msg_context": "X同学 ,<<xxxx>>直播课已经开课了 ,点击课程开始学习吧",
        // "msg_time": "2020-11-11 19:00:00",
        // "course_type": "1",
        $item['id'] = $msg_info['id'];
        $item['msg_type'] = $msg_info['msg_type'];
        $item['msg_context'] = $msg_info['msg_context'];
        $item['msg_status'] = $msg_info['msg_status'];
        $item['msg_time'] = $msg_info['msg_time'];
        $item['course_type'] = 1;

        $item['course_cover'] = $course_info['cover'];
        $item['watch_num']    = $course_info['watch_num'];

        $date1 = time();
        $date2 = strtotime($course_info['validity_time']);
        if ($date1 >= $date2) {
            $item['day'] = '已过期';
            } else {
                $interval = $date2 - $date1;
                $d = floor($interval/3600/24);
                $h = floor(($interval%(3600*24))/3600);
                $m = floor(($interval%(3600*24))%3600/60);
                $s = floor(($interval%(3600*24))%60);
                if ($course_info['course_expiry'] == 0) {
                    $item['day'] = '无期限';
                } else {
                    if ($s > 0) {
                        $item['day'] = $d.'天'.$h.'小时'.$m.'分'.$s.'秒';
                    } else {
                        $item['day'] = '已过期';
                    }
                }
            }



        //"course_time_start": "开始时间戳",
        //"course_time_end": "结束时间戳",
        //"course_time_format": "2020年11月12日 19:00 —— 2020年11月12日 21:00",
        $ymd = date('Y-m-d', $Live_info[ 'start_time' ]);//年月日
        $start = date('H:i:s', $Live_info[ 'start_time' ]);//开始时分
        $end = date('H:i:s', $Live_info[ 'end_time' ]);//结束时分
        $weekarray = [ "周日", "周一", "周二", "周三", "周四", "周五", "周六" ];
        $start_at_date = date("w", $Live_info[ 'start_at' ]);
        $week = $weekarray[ $start_at_date ];
        //$item[ 'course_time' ] = $ymd . ' ' . $week . ' ' . $start . '-' . $end;   //开课时间戳 start_at 结束时间戳转化 end_at
        $item[ 'course_time' ] =  $ymd." ".$start . '  -  '.$ymd." " . $end;   //开课时间戳 start_at 结束时间戳转化 end_at
//        $item[ 'course_time_format_h5' ] = $start . '-' . $end;   //开课时间戳 start_at 结束时间戳转化 end_at
//        $item[ 'course_time_start' ] = $Live_info[ 'start_time' ];
//        $item[ 'course_time_end' ] = $Live_info[ 'end_time' ];
//

        // "course_status": "未开始/已经结束",
        //判断课程直播状态  1未直播2直播中3回访
        $item[ 'course_status' ] = $Live_info[ 'status' ];
        // "course_class_count": 1,
        // "course_id": 1
        $item[ 'course_id' ] = $course_id;
        $item[ 'nature' ] = $nature;
        $day_span = strtotime(date("Y-m-d", $Live_info[ 'start_at' ]));
        return array( $item, $day_span );
    }

}
