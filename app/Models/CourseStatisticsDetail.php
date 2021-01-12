<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CourseStatisticsDetail extends Model
{
    //指定别的表名
    public $table = 'ld_course_statistics_detail';
    //时间戳设置
    public $timestamps = false;

    /**
     *  添加一条 学员的学习进度
     *  对于一个 学生来说  这个 直播的 观看记录 要求 唯一性
     * @param $room_id
     * @param $school_id
     * @param $courese_id
     * @param $live_id
     * @param $student_id
     * @param $learning_styles
     * @param $watch_time
     * @param $learning_time
     * @param $learning_rate
     * @param $learning_finish
     * @return bool
     */
    public function addLiveRecode($room_id, $school_id, $courese_id, $live_id, $student_id, $learning_styles, $watch_time, $learning_time, $learning_rate, $learning_finish)
    {

        // 这里 确定唯一性的 字段
        $data_only = array(
            'school_id'  => $school_id,
            'course_id'  => $courese_id,
            'live_id'    => $live_id,
            'student_id' => $student_id,
            'room_id'    => $room_id
        );
        $date_update = array(
            'learning_styles' => $learning_styles,
            'learning_time'   => $learning_time,
            'learning_finish' => $learning_finish,
            'watch_time'      => $watch_time,
            'learning_rate'   => $learning_rate
        );

        $query = $this->newQuery();
        return $query->updateOrInsert($data_only, $date_update);
    }


    public function addRecodeRecode($room_id, $school_id, $course_id, $recode_id, $student_id, $learning_styles, $watch_time, $learning_time, $learning_rate, $learning_finish)
    {
        /**
         *  回访记录 的学习进度
         *  这里 的 唯一性字段 是  不同的 是  recode_id 和  learning_time
         *  无论 回看多少次  注意 开始时间  是 唯一的
         *   定时任务 在统计的 时候 多次的 进入 而无推出 去 第一次的 进入
         */
        // 这里 确定唯一性的 字段
        $data_only = array(
            'school_id'     => $school_id,
            'course_id'     => $course_id,
            'recode_id'     => $recode_id,
            'student_id'    => $student_id,
            'room_id'       => $room_id,
            'learning_time' => $learning_time,
        );
        $date_update = array(
            'learning_styles' => $learning_styles,
            'learning_finish' => $learning_finish,
            'watch_time'      => $watch_time,
            'learning_rate'   => $learning_rate
        );

        $query = $this->newQuery();
        return $query->updateOrInsert($data_only, $date_update);
    }


    public function CalculateLiveRate($room_id, $student_id, $school_id, $course_id)
    {
        // 统计 直播的数据
        $live_query = $this->newQuery();
        $live_all_time = $live_query->where("school_id", "=", $school_id)
            ->where("room_id", "=", $room_id)
            ->where("student_id", "=", $student_id)
            ->whereRaw("live_id is not null")
            ->sum("watch_time");

        // 统计  直播回访的 数据
        $recode_query = $this->newQuery();
        $recode_all_time = $recode_query->where("school_id", "=", $school_id)
            ->where("room_id", "=", $room_id)
            ->where("student_id", "=", $student_id)
            ->whereRaw("live_id is  null")
            ->sum("watch_time");

        // 统计 最长上课 时间
        $first_query = $this->newQuery();
        $first_query_all_time = $first_query->where("school_id", "=", $school_id)
            ->where("room_id", "=", $room_id)
            ->where("student_id", "=", $student_id)
            ->orderBy("watch_time", "desc")
            ->first();

        $max_time = 0;
        if (!empty($first_query_all_time)) {
            $max_time = time2string($first_query_all_time[ 'watch_time' ]);
        }

        //  最后上课时间
        $last_query = $this->newQuery();
        $last_query_all_time = $last_query->where("school_id", "=", $school_id)
            ->where("room_id", "=", $room_id)
            ->where("student_id", "=", $student_id)
            ->orderBy("learning_time", "desc")
            ->select('learning_time')
            ->first();
        $last_time = "-";
        if (!empty($last_query_all_time)) {
            $last_time = $last_query_all_time[ 'learning_time' ];
        }


        // 查询总的课程时长
        $CourseStatistics = new CourseStatistics();
        $course_item = $CourseStatistics->getTotalTimeByCourseIdAndSchoolId($school_id, $course_id);

        if ($course_item <= 0) {
            $rate = 0;
        } else {
            // 计算 课程 完成率
            $rate = round(($live_all_time + $recode_all_time) / $course_item * 100);

        }

        return [ 'rate' => $rate, "max_time" => $max_time, 'last_time' => $last_time ];
    }


    /**
     *
     *  查询  某一个 学校 某一个 学生 在 某一个课次(某一个 直播间)的 完成率
     * @param $room_id
     * @param $student_id
     * @param $school_id
     * @return array
     */
    public function getLiveStatisticsByRoomidAndStudentIdWithSchoolId($room_id, $student_id, $school_id)
    {
        $query = $this->newQuery();
        // room_id 限定 了 课次 信息
        $ret_data = $query->where("room_id", '=', $room_id)
            ->where('student_id', '=', $student_id)
            ->whereRaw(" live_id is not null ")  // 这里 限定 是 直播
            ->where('school_id', "=", $school_id)
            ->first();

        if (empty($ret_data)) {
            return [];
        }
        return $ret_data->toArray();
    }

    //获取某一个 学生某一个课程的 所有的观看直报和所有的观看直播回放的数据
    public function getAllTimeByCourseIdAndSchoolId($school_id, $course_id, $student_id)
    {
        // 统计 直播的数据
        $live_query = $this->newQuery();
        $live_all_time = $live_query->where("school_id", "=", $school_id)
            ->where("course_id", "=", $course_id)
            ->where("student_id", "=", $student_id)
            ->whereRaw("live_id is not null")
            ->sum("watch_time");

        // 统计  直播回访的 数据
        $recode_query = $this->newQuery();
        $recode_all_time = $recode_query->where("school_id", "=", $school_id)
            ->where("course_id", "=", $course_id)
            ->where("student_id", "=", $student_id)
            ->whereRaw("live_id is  null")
            ->sum("watch_time");

        return [ 'live_time' => $live_all_time, "recode_time" => $recode_all_time ];
    }

    /**
     *  计算 某一个 roomid 所有 学校 观看 时间
     *  返回结果 school_id  total_student 学生总个数  total_time 学习的总共时间
     * @param $room_id
     */
    public function CalculateLiveTimeWhitRoomid($room_id)
    {
        $query = $this->newQuery();
        $school_time = $query->select([ 'school_id', 'course_id' ])
            ->selectRaw('	count( DISTINCT ld_course_statistics_detail.student_id) as total_student')
            ->selectRaw("	sum(  ld_course_statistics_detail.watch_time) as total_time ")
            ->where("room_id", "=", $room_id)
            ->whereRaw(" recode_id IS NULL ")
            ->groupBy("school_id")
            ->get();

        //整理 数据
        if ($school_time->count() == 0) {

            return [];
        }

        // 如果 有数据 直接返回
        return $school_time->toArray();
    }

    /**
     *   获取 某一个 课次的总共的学生学习次数 和 总共的学习时间
     * @param $room_id
     * @return array
     */
    public function CalculateLiveRecodeRateWithRoomId($room_id)
    {
        // 统计某一课次的全部（直报/回放） 全部学生个数，全部的学习时间
        $query = $this->newQuery();
        $school_time = $query->select([ 'school_id', 'course_id' ])
            ->selectRaw('	count( DISTINCT ld_course_statistics_detail.student_id) as total_student')
            ->selectRaw("	sum( ld_course_statistics_detail.watch_time) as total_time ")
            ->where("room_id", "=", $room_id)
            ->groupBy("school_id")
            ->get();

        //整理 数据
        if ($school_time->count() == 0) {

            return [];
        }

        // 如果 有数据 直接返回
        return $school_time->toArray();
    }

    /**
     *  计算一个
     * @param $school_id
     * @param $student_id
     * @param $room_id
     * @return int
     */
    public function getCourseRateByStudentIdAndRoomId($school_id,$student_id,$room_id)
    {

        // 查看课时的 总长度
        $course_statistics = new CourseStatistics();
        $course_time =$course_statistics ->getStatisticsTimeByRoomId($room_id);

        if ( !empty($course_time) and $course_time->count() > 0) {
            // 无论是 那个学校 的 课程 同一个 room_id 下的时间 是一样的
            $statistics_time = $course_time[ 'statistics_time' ];
        } else {
            return 0;
        }

        // 统计 直播的数据
        $live_query = $this->newQuery();
        $live_all_time = $live_query->where("school_id", "=", $school_id)
            ->where("room_id", "=", $room_id)
            ->where("student_id", "=", $student_id)
            ->whereRaw("live_id is not null")
            ->sum("watch_time");

        // 统计  直播回访的 数据
        $recode_query = $this->newQuery();
        $recode_all_time = $recode_query->where("school_id", "=", $school_id)
            ->where("room_id", "=", $room_id)
            ->where("student_id", "=", $student_id)
            ->whereRaw("live_id is  null")
            ->sum("watch_time");


       // print_r( [ 'live_time' => $live_all_time, "recode_time" => $recode_all_time ,'statistics_time' => $statistics_time]);

        // 计算课程完成率 （观看直播的时间 + 观看回放的时间）/ 课程的直播时间
        if($statistics_time == 0){
            return  0;
        }else{
            $rate = round(($live_all_time + $recode_all_time)/$statistics_time*100,2);
            ($rate > 100)?$rate = 100:0;  // 如果100 表示已经完成
            return $rate;
        }

    }

}
