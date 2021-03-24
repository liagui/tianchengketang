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

    public function CalculateCourseRateByVideoId($student_id, $cc_video_id,&$out=null)
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
            if(!is_null($out)){
                $out = $play_position;
            }

          return round($play_position/$play_duration *100);
        }


    }

     /**
         *  通过 一组id 来获取
         * @param string $student_id
         * @param array $cc_video_ids
         * @param null $out
         * @return float|int|array
         */
        public function CalculateCourseRateByVideoIdList( string $student_id,  array $cc_video_id_list,&$out=null)
        {

            // 计算 点播的 课程的学习仅需 使用video id 来计算多次观看录播课程
            $query = $this->newQuery();
            // 查询 用户观看 视频的长度
            $play_info = $query->where("user_id", "=", $student_id)
                //->wh("videoid", "=", $cc_video_id)
                ->whereIn('videoid',$cc_video_id_list)
                ->where("play_position", ">", 0)->where("play_duration", ">", 0)
                ->groupBy('videoid')
                ->select([ "videoid", "play_duration", "play_position" ])
                ->get();

            if (empty($play_info)) {
                // 默认返回 空
                return array();
            } else {

                $ret_list = [];
                foreach ($play_info as  $course_info){
                    // 计算 录播视频 学习进度
                    $play_duration = $course_info[ 'play_duration' ];
                    $play_position = $course_info[ 'play_position' ];

                    $rate = round($play_position/$play_duration *100);

                    // 返回 所有的数据
                    $ret_list [$course_info['videoid']]['rate'] = $rate ;
                    $ret_list [$course_info['videoid']]['play_position'] = $play_position;
                    $ret_list [$course_info['videoid']]['play_duration'] = $play_duration;
                }
                return  $ret_list;

            }


        }


    /**
     *  通过 一组id 来获取
     * @param string $student_id
     * @param array $cc_video_ids
     * @param null $out
     * @return float|int|array
     */
    // public function CalculateCourseRateByVideoIdList( string $student_id,  array $cc_video_id_list,&$out=null)
    // {

    //     // 计算 点播的 课程的学习仅需 使用video id 来计算多次观看录播课程
    //     $query = $this->newQuery();
    //     // 查询 用户观看 视频的长度
    //     $play_info = $query->where("user_id", "=", $student_id)
    //         //->wh("videoid", "=", $cc_video_id)
    //         ->whereIn('videoid',$cc_video_id_list)
    //         ->where("play_position", ">", 0)->where("play_duration", ">", 0)
    //         ->groupBy('videoid')
    //         ->select([ "videoid", "play_duration", "play_position" ])
    //         ->get();

    //     if (empty($play_info)) {
    //         // 默认返回 空
    //         return array();
    //     } else {

    //         $ret_list = [];
    //         foreach ($play_info as  $course_info){
    //             // 计算 录播视频 学习进度
    //             $play_duration = $course_info[ 'play_duration' ];
    //             $play_position = $course_info[ 'play_position' ];

    //             $rate = round($play_position/$play_duration *100);

    //             // 返回 所有的数据
    //             $ret_list [$course_info['videoid']]['rate'] = $rate ;
    //             $ret_list [$course_info['videoid']]['play_position'] = $play_position;
    //             $ret_list [$course_info['videoid']]['play_duration'] = $play_duration;
    //         }
    //         return  $ret_list;

    //     }


    // }




}
