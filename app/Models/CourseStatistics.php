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
    public function CalculateCourseRate($school_id,$course_id,$student_id){
        // 获取 这个 课程 的 时长
        $course_time = $this ->getTotalTimeByCourseIdAndSchoolId($school_id,$course_id);

        // 获取该学生 的 观看 直博和 回访的 时间
        $Course_statistics_detail = new CourseStatisticsDetail();
        $user_time = $Course_statistics_detail ->getAllTimeByCourseIdAndSchoolId($school_id,$course_id,$student_id);

        $live_time = $user_time['live_time'];
        $recode_time = $user_time['recode_time'];

        if($course_time == 0){
            return  0;
        }

        return round(($live_time + $recode_time) / $course_time * 100);

    }


}
