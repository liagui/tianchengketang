<?php


namespace App\Console\Commands\Message;


use App\Console\Commands\Message\CheckTodayRoomIdCron;
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
use mysql_xdevapi\Exception;
use Predis\Response\Iterator\MultiBulk;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;


/**
 *   这个 定时任务是用来辅助  CheckTodayRoomIdCron
 *   来发送消息的 设定运行时间 每个  10分钟  每隔之分钟 检查
 *   redis 中的队列  today_room_id_list 来获取 待开始 的直播
 *   并且发送 消息给 报名的 学生
 * Class SendMessageForClassIdCron
 * @package App\Console\Commands\Message
 */
class SendMessageForLiveChanageCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'sendMsg2Cron';
    const LIVE_INFO_CHANGE = "live_info_change";

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '开课或者开课时间修改的时候发送消息给客户';
    /**
     * @var false|resource
     */
    private $handle;

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
        ini_set('max_execution_time', 30000);
        ini_set('default_socket_timeout', -1);
        $this->consoleAndLog('开始:' . $this->description . PHP_EOL);

        if ($this->lockFile()== false){
            die();
        }

        while (true) {

            try {
                $ret_live_info = Redis::rpop(Self::LIVE_INFO_CHANGE);

                //var_dump($ret_live_info);

                if ($ret_live_info === false) {
                    print_r("sleep in 5 sec " . PHP_EOL);
                    sleep(5);
                    continue;
                }

                $msg_info = json_decode($ret_live_info, true);

                $type = $msg_info[ 'type' ];
                $live_info = $msg_info[ 'live_info' ];
                $room_id = $live_info[ 'room_id' ];
                $this->consoleAndLog("process RoomId:[" . $room_id . "] type:[" . $type . "]" . PHP_EOL);

                $this->processLiveInfo($type, $live_info);
                print_r("============process end===============" . PHP_EOL);


            } catch (\Exception $ex) {
                $this->consoleAndLog("发生错误，重新启动！");
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


    function processLiveInfo($type, $live_info)
    {

        $order = new Order();
        $message = new StudentMessage();

        $room_id = $live_info[ 'room_id' ];

        // 开始 处理这个 房间号
        $ret_live = Course::getCourseInfoForRoomId($room_id);

        if (isset($ret_live[ 'live_info' ])) {
            $live_info = $ret_live[ 'live_info' ];
            $new_start_time = $ret_live[ 'live_info' ][ 'start_time' ];

            unset($ret_live[ 'live_info' ]);
        }
        // 便利所有的学校和授权课程 并且进行通知
        foreach ($ret_live as $key => $value) {

            print_r("Query Schol_id:" . $value[ 'school_id' ] . " course_id:" . $value[ 'course_id' ] . PHP_EOL);
            $order_list = $order->getOrdersBySchoolIdAndClassId($value[ 'school_id' ], $value[ 'course_id' ]);
            if ($value[ 'school_id' ] == 1) {
                $order_course_info_ = Coures::query()->where("id", "=", $value[ 'course_id' ])->first();
                if (!empty($order_course_info_)) {
                    $order_course_info_ = $order_course_info_->toArray();
                }
            } else {
                $order_course_info_ = CourseSchool::query()->where("id", "=", $value[ 'course_id' ])
                    ->where("to_school_id", "=", $value[ 'school_id' ])
                    ->first();
                if (!empty($order_course_info_)) {
                    $order_course_info_ = $order_course_info_->toArray();
                }
            }

            $course_name = $order_course_info_[ 'title' ] . "---" . $live_info[ 'course_name' ];

            if (empty($order_list)) {
                echo "not found order " . PHP_EOL;
            } else {
                print_r("订阅课程人数:" . count($order_list) . PHP_EOL);
                foreach ($order_list as $order_info) {
                    // print_r($order_info);

                    $date[ 'student_id' ] = $order_info[ 'student_id' ];
                    $student_info = Student::getStudentInfoById($date);
                    if (isset($student_info[ 'data' ])) {
                        $student_info = $student_info[ 'data' ];
                    } else {
                        print_r($student_info[ 'msg' ]);
                        break;
                    }


                    //  学生姓名
                    $student_name = (empty($student_info[ 'real_name' ]) ? $student_info[ 'phone' ] : $student_info[ 'real_name' ]);

                    // 格式化 消息字符串
                    if ($type == "live_start") {
                        $msg_context = "%s同学，《%s》直播课已经开始上课了，点击课程开始学习吧～";
                        $ret_msg_context = sprintf($msg_context, $student_name, $course_name);
                    } else if ($type == "time_change") {

                        $msg_context = "%s同学，《%s》直播课上课时间调整为%s，记得要按时参加学习～";
                        $course_start_time = date("Y年m月d日H时i分s秒", $live_info[ 'start_time' ]);
                        $ret_msg_context = sprintf($msg_context, $student_name, $course_name, $course_start_time);
                    }
                    // 发送消息
                    $this->consoleAndLog($ret_msg_context . PHP_EOL);
                    $message->addMessage($value[ 'school_id' ], $order_info[ 'student_id' ], $value[ 'course_id' ],
                        $live_info['id'], $order_info[ 'id' ], $order_info[ 'nature' ], 1, $ret_msg_context);

                }
            }
        }

    }

    #region 保证 定时任务单独执行 文件所
    private $_lock_file = __FILE__.".lock.file";

    /**
     * 加锁，独占锁
     */
    public function lockFile()
    {
        $this->handle=fopen($this->_lock_file,'w+');
        if($this->handle){
            //如果文件被锁定则非阻塞操作
            if(flock($this->handle,LOCK_EX | LOCK_NB)){
                return true;
            }else{

                $this->consoleAndLog("发现 lock file [".$this->description ."] 退出");
            }
        }
        return false;
    }

    /**
     *解锁
     */
    public function unlockFile()
    {
        if($this->handle){//释放锁定
            flock($this->handle,LOCK_UN);
            clearstatcache();
            fclose($this->handle);
            unlink($this->_lock_file);
        }
    }
#endregion

}
