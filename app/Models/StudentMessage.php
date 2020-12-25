<?php

namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class StudentMessage extends Model
{

    // 0删除 1未读 2已读
    const MESSAGE_STATUS_DEL = 0;
    const MESSAGE_STATUS_UNREAD = 1;
    const MESSAGE_STATUS_READ = 2;

    //指定别的表名
    public $table = 'ld_student_message';
    //时间戳设置
    public $timestamps = false;

    /**
     * @param $school_id
     * @param $student_id
     * @param $course_id
     * @param $course_live_id
     * @param $order_id
     * @param $nature
     * @param $msg_type
     * @param $msg_context
     * @param $msg_status
     * @param $msg_time
     * @param int $course_type 默认未读消息
     * @return int
     */
    public function addMessage($school_id, $student_id, $course_id, $course_live_id, $order_id, $nature, $msg_type, $msg_context, $course_type = 1)
    {
        // 添加消息
        $date = array(
            "school_id"   => $school_id,
            "student_id"  => $student_id,
            "course_id"   => $course_id,
            "live_id"    => $course_live_id,
            "order_id"    => $order_id,
            "nature"      => $nature,
            "msg_type"    => !isset($msg_type) ? $msg_type : 1,
            "msg_context" => $msg_context,
            "msg_status"  => 1,
            "msg_time"    => date("Y-m-d H:i:s"),
            "course_type" => $course_type
        );

        // 添加 数据
        return $this->newQuery()->insertGetId($date);
    }

    /**
     *  获取 消息 通过 学生 id 和  消息 状态
     * @param int $student_id
     * @param $school_id
     * @param $msg_status
     * @param $offset
     * @param $pagesize
     * @return array
     */
    public function getMessageByStudentAndSchoolId(int $student_id, $school_id, $msg_status = 0, $offset = 0, $pagesize = 10)
    {

        // 计算 消息的条目
         $wheres = $this->newQuery()
            ->where("student_id", "=", $student_id)
            ->where("school_id", "=", $school_id)

            ->orderBy("msg_time",'desc');
         // 判断 消息 的 状态 已读 / 未读
         if(!empty($msg_status)){
             $wheres ->where("msg_status", "=", $msg_status);
         }


        // 获取 具体的消息
        $msg_list = $wheres->offset($offset)->limit($pagesize);

        $msg_list = $msg_list->get();
        //  判断是否为空
        if ($msg_list->count() == 0) {
            // 如果 没有 数据
            return array();
        }
         $ret_array = array();
        // 格式化 输出 结果 按照  timetable 的 返回 结果 返回 数据
        foreach ($msg_list as $msg_info){

            if($msg_info['nature'] == 1){
                //  授权课程
                $course_info = CourseSchool::where(['id'=>$msg_info['course_id'],'is_del'=>0,'status'=>1])->first();
            }else {
                // 如果是自增课程那么从自增课程中查询信息
                $course_info = Coures::where(['id' => $msg_info['course_id'], 'is_del' => 0, 'status' => 1])->first();
            }
            // 获取 直播 信息
            //$live_info = LiveChild::query()-> where([ 'id' => $msg_info[ 'live_id' ], 'is_del' => 0, 'status' => 1 ])->first();
            $live_info = CourseLiveClassChild::where( ['id' => $msg_info[ 'live_id' ]])->first();

            $item = array();
            list($item, $day_span) = Course::formatMessageItem($msg_info, $live_info, $item, $course_info, $msg_info['course_id'], $msg_info['nature']);

           $ret_array[] = $item;

        }

        // 返回数组
        return  $ret_array;
    }


    /**
     *  更新 消息的 状态 为已读
     * @param $msg_id
     * @return int
     */
    public  function  setMessageRead($msg_id){

        return $this->newQuery()->where("id" ,'=',$msg_id)->update(["msg_status" => 2]);
    }

    /**
     *  获取 消息的 统计状态  (全部/未读/已读)
     * @param int $student_id 当前登录的用户id
     * @return array|false
     */
    public function getMessageStatistics(int $student_id, int $school_id)
    {
        // 统计三个数字 全部 已读 未读
        $query = $this->newQuery();
        $msg_list = $query->select("msg_status")
            ->selectRaw("count(id) as cc")
            ->where("student_id", "=", $student_id)
            ->where("school_id", "=", $school_id)
            ->groupBy("msg_status")
            ->get();

        //  统计 一下
        $cc_all = 0;      // 全部的 消息 个数
        $cc_unread = 0;   // 未读的 消息个数
        $cc_read = 0;     // 已读的 消息个数


        if ($msg_list->count() == 0) {
            // 返回 统计 个数
            return  [ 'all' => $cc_all, "un_read"=> $cc_unread, "read" => $cc_read ];
        }

        // 这里 统计 一下 各自 的 个数
        $count_array =  $msg_list->toArray();

        foreach ($count_array as $item){
            //  计算 全部的 消息中枢
            $cc_all += $item['cc'];
            // 统计 未读 和 已读的 消息
            switch ($item['msg_status']){
                case 0:
                    break;
                case 1:
                    $cc_unread += $item['cc'];
                    break;
                case 2 :
                    $cc_read += $item['cc'];
                    break;
            }
        }
        // 返回 统计 个数
        return  [ 'all' => $cc_all, "un_read"=> $cc_unread, "read" => $cc_read ];

    }


}
