<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoLog extends Model
{
    //指定别的表名
    public $table = 'ld_video_log';
    //时间戳设置
    public $timestamps = false;

    protected $fillable = [ 'play_position' ];

    public function CalculateCourseRateByVideoId($student_id, $cc_video_id)
    {

        // 计算 电报课程的学习仅需 使用video id 来计算多次观看录播课程
        $query = $this->newQuery();
        // 查询 用户观看 视频的长度
        $play_info = $query->where("user_id", "=", $student_id)
            ->where("videoid", "=", $cc_video_id)
            ->where("play_position", ">", 0)->where("play_duration", ">", 0)
            ->select([ "play_duration", "play_position" ])
            ->first();

        if (empty($play_info)) {
            return 0;
        } else {
            // 计算 录播视频 学习进度
            $play_duration = $play_info[ 'play_duration' ];
            $play_position = $play_info[ 'play_position' ];

          return round($play_position/$play_duration *100);
        }


    }

}
