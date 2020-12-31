<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseLiveClassChild extends Model {
    //指定别的表名
    public $table      = 'ld_course_live_childs';
    //时间戳设置
    public $timestamps = false;

    /**
     *  按照日期  进行  筛选 将要 开始 的课次信息 返回相应的 roomid
     * @param int $day_time
     * @return array
     */
    public function  getRoomIdByDate( int $day_time ){
        $year = date("Y", $day_time);
        $month = date("m", $day_time);
        $day = date("d", $day_time);
        $day_begin = mktime(0,0,0,$month,$day,$year);//当天开始时间戳
        $day_end = mktime(23,59,59,$month,$day,$year);//当天结束时间戳


        $query = $this->newQuery()->select("course_id as room_id")
            ->whereBetween("start_time",[$day_begin,$day_end]);

        $ret =$query->get();
        if ($ret ->count()){
            return  array_column ($ret ->toArray(),"room_id");
        }

       return array();
    }

}
