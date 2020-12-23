<?php


namespace App\Console\Commands\Message;


use App\Models\Coures;
use App\Models\CouresSubject;
use App\Models\Course;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\School;
use App\Models\SchoolConnectionsCard;
use App\Models\SchoolConnectionsDistribution;
use App\Models\SchoolConnectionsLog;
use App\Models\SchoolResource;
use App\Models\SchoolSpaceLog;
use App\Models\SchoolTrafficLog;
use App\Models\Student;
use App\Models\StudentMessage;
use App\Models\Video;
use App\Services\Admin\Course\CourseService;
use App\Services\Admin\Course\OpenCourseService;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Predis\Response\Iterator\MultiBulk;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;
use App\Console\Commands\Message\CheckTodayRoomIdCron;


/**
 *   这个 定时任务是用来辅助  CheckTodayRoomIdCron
 *   来发送消息的 设定运行时间 每个  10分钟  每隔之分钟 检查
 *   redis 中的队列  today_room_id_list 来获取 待开始 的直播
 *   并且发送 消息给 报名的 学生
 * Class SendMessageForClassIdCron
 * @package App\Console\Commands\Message
 */
class SendMessageForClassIdCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'sendMsg1Cron';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '开课前两个小时内发送课次开始信息';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->consoleAndLog('开始:' . $this->description . PHP_EOL);
        $order = new Order();
        $message = new StudentMessage();
        $student_mod = new Student();
        // 1 从redis 中获取到 即将要直报的 直报间 这里默认获取 100个直播间抄
        $ret_live = Redis::ZRANGE(CheckTodayRoomIdCron::TODAY_ROOM_ID_LIST, 0, 100, array( 'withscores' => true ));

        foreach ($ret_live as $room_id => $start_time) {
            // 计算当日的剩余时间
            $last_time = $start_time - time();
            if ($last_time < 10 * 60 * 60) {
                $this->consoleAndLog("find:room_id:[" . $room_id . "]@" . date("Y-m-d H:i:s", $start_time));

                // 开始 处理这个 房间号
                $ret_live = Course::getCourseInfoForRoomId($room_id);

                if (isset($ret_live[ 'live_info' ])) {
                    $live_info = $ret_live[ 'live_info' ];
                    $new_start_time = $ret_live['live_info']['start_time'];
                    if($new_start_time != $start_time){
                        // 如果发现 课次的开始时间 和redis中的不一致 那么 肯定是今天修改了 课次的时间信息
                        // 提前两个小时的通知 不再起作用

                        $this->consoleAndLog("RoomID:".$room_id."已修改 不在进行连个小时的提前通知！".PHP_EOL);
                        break;
                    }

                    unset($ret_live[ 'live_info' ]);
                }
                 // 便利所有的学校和授权课程 并且进行通知
                foreach ($ret_live as $key => $value) {
                    print_r("===========================" . PHP_EOL);
                    print_r("Query Schol_id:" . $value[ 'school_id' ] . " course_id:" . $value[ 'course_id' ] . PHP_EOL);
                    $order_list = $order->getOrdersBySchoolIdAndClassId($value[ 'school_id' ], $value[ 'course_id' ]);
                    if ($value['school_id'] == 1){
                        $order_course_info_ = Coures::query()->where("id","=",$value['course_id'])->first();
                        if(!empty($order_course_info_)){
                            $order_course_info_ = $order_course_info_ ->toArray();
                        }
                    }else{
                        $order_course_info_ = CourseSchool::query()->where("id","=",$value['course_id'])
                            ->where("to_school_id","=",$value[ 'school_id' ])
                            ->first();
                        if(!empty($order_course_info_)){
                            $order_course_info_ = $order_course_info_ ->toArray();
                        }
                    }

                    $course_name = $order_course_info_['title']."---".$live_info['course_name'];

                    if (empty($order_list)) {
                        echo "not found order " . PHP_EOL;
                    } else {
                        print_r("订阅课程人数:" . count($order_list) . PHP_EOL);
                        foreach ($order_list as $order_info) {
                           // print_r($order_info);

                            $date['student_id'] = $order_info['student_id'];
                            $student_info = Student::getStudentInfoById($date);
                            if(!empty($student_info['data'])){
                                $student_info = $student_info['data'];
                            }
                            //  学生姓名
                            $student_name = (empty($student_info['real_name'])?$student_info['phone']:$student_info['real_name']);

                           // print_r($student_info);
                            $msg_context = "%s同学，《%s》直播课还有2小时就要开课了，点击课程开始学习吧～";
                            $ret_msg_context = sprintf($msg_context,$student_name,$course_name);

                            print_r($ret_msg_context);


                            $message ->addMessage($value[ 'school_id' ],$order_info['student_id'],$value[ 'course_id' ],
                                $live_info['id'],$order_info['id'], $order_info['nature'],1,$ret_msg_context);

                        }
                    }
                }


                // 处理完毕后 清理 redis中的东西
                Redis::ZREM(CheckTodayRoomIdCron::TODAY_ROOM_ID_LIST, $room_id);
            }

        }
        $this->consoleAndLog('结束:' . $this->description . PHP_EOL);

    }

    function consoleAndLog($str)
    {
        $str .= PHP_EOL;
        echo $str;
        Log::info($str);
    }


}
