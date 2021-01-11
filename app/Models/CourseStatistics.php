<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CourseStatistics extends Model
{
    //指定别的表名
    public $table = 'ld_course_statistics';
    //时间戳设置
    public $timestamps = false;


    /**
     *  插入一条数据
     *  直播的 观看数据 要求 唯一性
     * @param $school_id
     * @param $course_id string
     * @param $room_id
     * @param $start_time
     * @param $end_time
     * @param $statistics_time
     * @return bool
     */
    public function addStatisticsBySchoolAndCourseId($school_id, $course_id, $room_id, $start_time, $end_time, $statistics_time)
    {
        $query = $this->newQuery();
        // 这些 是 唯一 性 字段  同一个学校 同一个 课程 id 同一个 直播间(同一个课次)
        $wheres = array(
            "school_id" => $school_id,
            "course_id" => $course_id,
            'room_id'   => $room_id

        );
        // 跟新一下的 资料
        $data = array(
            "live_start_time" => $start_time,
            "live_end_time"   => $end_time,
            "statistics_time" => $statistics_time
        );


        return $query->updateOrInsert($wheres, $data);
    }

    public function update_course_attendance($course_id, $attendance)
    {
        return $this->newQuery()->where("course_id", $course_id)
            ->update([ 'course_attendance' => $attendance ]);
    }

    /**
     *  查询时长
     * @param $room_id
     * @return Builder|Model|object|null
     */
    public function getStatisticsTimeByRoomId($room_id)
    {
        return $this->newQuery()->where("room_id", '=', $room_id)
            ->select('statistics_time')->first();
    }

    /**
     *  通过 课程id (自增课 或者 授权课程) 查询 讲师所有的 授课时间
     *
     * @param $school_id
     * @param $course_id  string 课程id 或者 自增课程id
     * @return int|mixed
     */
    public function getTotalTimeByCourseIdAndSchoolId($school_id, string $course_id)
    {
        $query = $this->newQuery();
        return $query->where('course_id', "=", $course_id)
            ->where("school_id", "=", $school_id)
            ->sum('statistics_time');
    }

    /**
     *  计算 某一个 学生 在 某一个 课程 的 课程 完成率
     *
     * @param $school_id
     * @param $course_id
     * @param $student_id
     * @return float
     */
    public function CalculateCourseRateBySchoolIdAndStudentId($school_id, $course_id, $student_id)
    {

        // 获取 这个 课程 的 时长
        $course_time = $this->getTotalTimeByCourseIdAndSchoolId($school_id, $course_id);

        // 获取该学生 的 观看 直博和 回访的 时间
        $Course_statistics_detail = new CourseStatisticsDetail();
        $user_time = $Course_statistics_detail->getAllTimeByCourseIdAndSchoolId($school_id, $course_id, $student_id);

        $live_time = $user_time[ 'live_time' ];
        $recode_time = $user_time[ 'recode_time' ];

        if ($course_time == 0) {
            return 0;
        }

        return round(($live_time + $recode_time) / $course_time * 100);

    }


    public  function  getAllRoomIds(){
        $query = $this->newQuery();
        return $query  ->select("room_id") ->groupBy("room_id") ->get();
    }


    /**
     *  统计某一次直报 后的直报到课率
     * @param $room_id
     */
    public function updateAllSchoolIdCourseLiveRateWithRoomId($room_id)
    {

        // 获取 该 room_id 对应课次信息 对应的 学校和 课程西信息
        $school_course_info = Course::getCourseInfoForRoomId($room_id);
        $order_mod = new Order();

        if (isset($school_course_info[ 'live_info' ])) {
            $live_info = $school_course_info[ 'live_info' ];
            unset($school_course_info[ 'live_info' ]);
        }
        if (is_string($school_course_info)) {
            // 发生 错误
            return;
        }
        $school_course_info = array_column($school_course_info, 'course_id', 'school_id');

//
//        print_r( " room_id: $room_id " . count($school_course_info).PHP_EOL);
//        foreach ($school_course_info as $school_id=>$course_id){
//            //$school_id = $item[ 'school_id' ];
//            //$course_id = $item[ 'course_id' ];
//
//            // 查询该学校和该课程的订单信息
//            $order_count = $order_mod->getOrdersCountBySchoolIdAndClassId($school_id, $course_id);
//            print_r(" soso find school id: $school_id :: course_id:$course_id order count: $order_count  " . PHP_EOL);
//
//        }
        /**
         *  获取 学校和 对应 课程的 id
         *  获取总的已经报名的订单个数作为报名人数
         *  从上课的人数和报名的潤你数 得知直播到课率
         *
         */
        // 根据room_id  来获取 听课的 学校 和 学校的
        $course_statistics_mod = new CourseStatisticsDetail();
        $live_time_school_list = $course_statistics_mod->CalculateLiveTimeWhitRoomid($room_id);

        // 所有的 学校听课时间

        foreach ($live_time_school_list as $live_time) {
            $school_id = $live_time[ 'school_id' ];
            $course_id = $live_time[ 'course_id' ];
            $total_student = $live_time[ 'total_student' ];
            $total_time = $live_time[ 'total_time' ];
            // 查询该学校和该课程的订单信息
            $order_count = $order_mod->getOrdersCountBySchoolIdAndClassId($school_id, $course_id);
           // print_r("find school id: $school_id :: course_id:$course_id order count: $order_count  learn_student_count $total_student " . PHP_EOL);
            $date_where = array(
                "course_id" => $course_id,
                "school_id" => $school_id,
                "room_id"   => $room_id
            );

            // 计算 质保 到课率 这里 的 学生的 总的学习学生 数目 不可能是 0
            $update_date = array(
                "course_attendance" =>  ( $order_count/$total_student * 100)
            );
            $this->newQuery()->updateOrInsert($date_where,$update_date);
        }



    }


}
