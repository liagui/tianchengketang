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

        $date_update = array(
            'school_id'       => $school_id,
            'course_id'       => $course_id,
            'recode_id'       => $recode_id,
            'student_id'      => $student_id,
            'room_id'         => $room_id,
            'learning_styles' => $learning_styles,
            'learning_time'   => $learning_time,
            'learning_finish' => $learning_finish,
            'watch_time'      => $watch_time,
            'learning_rate'   => $learning_rate
        );

        $query = $this->newQuery();
        return $query->insert($date_update);
    }


    public function CalculateLiveRate($room_id, $student_id, $school_id,$course_id)
    {
        // 统计 直播的数据
        $live_query = $this->newQuery();
        $live_all_time = $live_query->where("school_id", "=", $school_id)
            ->where("room_id","=",$room_id)
            ->where("student_id", "=", $student_id)
            ->whereRaw("live_id is not null")
            ->sum("watch_time");

        // 统计  直播回访的 数据
        $recode_query = $this->newQuery();
        $recode_all_time = $recode_query->where("school_id", "=", $school_id)
            ->where("room_id","=",$room_id)
            ->where("student_id", "=", $student_id)
            ->whereRaw("live_id is  null")
            ->sum("watch_time");

        // 统计 最长上课 时间
        $first_query = $this->newQuery();
        $first_query_all_time = $recode_query->where("school_id", "=", $school_id)
            ->where("room_id","=",$room_id)
            ->where("student_id", "=", $student_id)
            ->orderBy("watch_time","desc")
            ->first();

        $max_time = 0;
        if(!empty($first_query_all_time)){
            $max_time = time2string( $first_query_all_time['watch_time']);
        }

        //  最后上课时间
        $last_query = $this->newQuery();
        $last_query_all_time = $recode_query->where("school_id", "=", $school_id)
            ->where("room_id","=",$room_id)
            ->where("student_id", "=", $student_id)
            ->orderBy("learning_time","desc")
            ->select('learning_time')
            ->first();
        $last_time = "-";
        if(!empty($last_query_all_time)){
            $last_time = $last_query_all_time['learning_time'];
        }




        // 查询总的课程时长
        $CourseStatistics = new CourseStatistics();
        $course_item = $CourseStatistics ->getTotalTimeByCourseIdAndSchoolId($school_id,$course_id);

        if($course_item <= 0){
            $rate = 0;
        }else{
            // 计算 课程 完成率
            $rate =  round(($live_all_time + $recode_all_time) / $course_item * 100);

        }

        return [ 'rate' => $rate, "max_time" => $max_time,'last_time' => $last_time];
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


}
