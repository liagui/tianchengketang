<?php


namespace App\Console\Commands;
/**
 *
 *  用来分析 直播完成后 学生端使用直播的情况
 *  这里仅仅分析 直播中的数据
 *  从 验证接口中获取到 待计算的直播间 roomid
 *  按照 roomid 来获取live id 来获取 获取观看直播的访问记录
 *   http://api.csslcloud.net/api/statis/live/useraction
 *  来关联用户的学习进度
 *
 */

use App\Models\CouresSubject;
use App\Models\Course;
use App\Models\CourseStatistics;
use App\Models\CourseStatisticsDetail;
use App\Models\School;
use App\Models\SchoolConnectionsCard;
use App\Models\SchoolConnectionsDistribution;
use App\Models\SchoolResourceLimit;
use App\Models\SchoolResource;
use App\Models\SchoolSpaceLog;
use App\Models\SchoolTrafficLog;
use App\Models\Video;
use App\Services\Admin\Course\CourseService;
use App\Services\Admin\Course\OpenCourseService;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use phpDocumentor\Reflection\Types\True_;
use Predis\Response\Iterator\MultiBulk;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;

class CCRoomLiveAnalysisRecodeCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'CCAnalysisRecodeCron';
    private $time_delay = 60 * 1;
    const ANALYSIS_CC_ROOM = "analysis_cc_room";
    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = 'CC 直播回放统计分析功能(只统计直播回放的观看率)';

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
        ini_set('memory_limit', '512M');

        /**
         *  计算步骤
         *     获取昨日的全部直播回放的数据列表
         *         获取两次 第一次登陆 第二次 登出 全部放入到redis 中
         *     计算reids 中的数据 得到用户 观看直播锝对应的房号和观看时长
         *
         *
         *
         */


        if ($this->lockFile() == false) {
            die();
        }


        $yesterday = date("Y-m-d", strtotime("-1 day"));
        $today = date("Y-m-d", strtotime("now"));
        $yesterday = "2020-12-29";
        $yesterday_start = date("Y-m-d 00:00:01", strtotime($yesterday));
        $yesterday_end = date("Y-m-d 23:59:59", strtotime($yesterday));

        $this->consoleAndLog('开始' . $this->description . PHP_EOL);
        $this->consoleAndLog('当前时间:' . $today . PHP_EOL);
        $CCCloud = new CCCloud();
        $CCCloud->_useRawUrlEncode = true; // 不适用 urlEncode 使用 rawEncode

        $all_user_actions_entry = array();
        $all_user_actions_level = array();
        $un_process_date = array();

        //print_r(date("Y-m-d H:i:s") . PHP_EOL);

        // 处理 用户 进入 的 时间 列表
        $ret = $CCCloud->CC_statis_user_record_useraction($yesterday_start, $yesterday_end, 0);
        if (isset($ret[ 'code' ]) and $ret[ 'code' ] == 0) {

            $userActions = $ret[ 'data' ][ 'userActions' ];
            $pageIndex = $ret[ 'data' ][ 'pageIndex' ];
            $count = $ret[ 'data' ][ 'count' ];


            while (!empty($userActions)) {

                file_put_contents("aciont_0.txt", (print_r($ret,true)), FILE_APPEND);
                $this->consoleAndLog("process user level actionlist pageindex:" . $pageIndex . " rate " . ($pageIndex * 1000) . "/" . $count . PHP_EOL);

                // 循环 处理  每一条 用户 进入进出的 记录
                foreach ($userActions as $action) {
                    if($action['userId'] != 50612) continue;

                    $data = array(
                        'userId'   => $action[ 'userId' ],
                        'time'     => $action[ 'time' ],
                        'recordId' => $action[ 'recordId' ],
                        'roomId'   => $action[ 'roomId' ]
                    );
                    $userId = $action[ 'userId' ];
                    // 判断是否存在 处理用户多次进入进出的情况
                    if (isset ($all_user_actions_entry[ $userId ])) {
                        // 如果已经有了key 将key 变成 只含有 一个key（list） 的 的数组
                        if (!isset($all_user_actions_entry[ $userId ][ 'list' ])) {
                            $old = $all_user_actions_entry[ $userId ];
                            unset($all_user_actions_entry[ $userId ]);
                            $all_user_actions_entry[ $userId ][ 'list' ][] = $old;
                        }

                        $all_user_actions_entry[ $userId ][ 'list' ][] = $data;
                    } else {
                        $all_user_actions_entry[ $userId ] = $data;
                    }
                }

                sleep(1);
                // 尾递归 优化一下获取数据
                $pageIndex += 1;
                $ret = $CCCloud->CC_statis_user_record_useraction($yesterday_start, $yesterday_end, 0, $pageIndex);
                $userActions = $ret[ 'data' ][ 'userActions' ];
            }

        }

        print_r(date("Y-m-d H:i:s") . PHP_EOL);
        $ret = $CCCloud->CC_statis_user_record_useraction($yesterday_start, $yesterday_end, 1);
        if (isset($ret[ 'code' ]) and $ret[ 'code' ] == 0) {

            $userActions = $ret[ 'data' ][ 'userActions' ];
            $pageIndex = $ret[ 'data' ][ 'pageIndex' ];
            $count = $ret[ 'data' ][ 'count' ];

            while (!empty($userActions)) {
                file_put_contents("aciont_1.txt", (print_r($ret,true)), FILE_APPEND);
                $this->consoleAndLog("process user level actionlist pageindex:" . $pageIndex . " rate " . ($pageIndex * 1000) . "/" . $count . PHP_EOL);

                // 循环 处理  每一条 用户 进入进出的 记录
                foreach ($userActions as $action) {
                    if($action['userId'] != 50612) continue;
                    $data = array(
                        'userId'   => $action[ 'userId' ],
                        'time'     => $action[ 'time' ],
                        'recordId' => $action[ 'recordId' ],
                        'roomId'   => $action[ 'roomId' ]
                    );
                    $userId = $action[ 'userId' ];
                    if (isset ($all_user_actions_level[ $userId ])) {
                        // 如果已经有了key 将key 变成 只含有 一个key（list） 的 的数组
                    // 判断是否存在 处理用户多次进入进出的情况
                        if (!isset($all_user_actions_level[ $userId ][ 'list' ])) {
                            $old = $all_user_actions_level[ $userId ];
                            unset($all_user_actions_level[ $userId ]);
                            $all_user_actions_level[ $userId ][ 'list' ][] = $old;
                        }

                        $all_user_actions_level[ $userId ][ 'list' ][] = $data;
                    } else {
                        $all_user_actions_level[ $userId ] = $data;
                    }


                }

                sleep(1);
                // 尾递归 优化一下获取数据
                $pageIndex += 1;
                $ret = $CCCloud->CC_statis_user_record_useraction($yesterday_start, $yesterday_end, 1, $pageIndex);
                $userActions = $ret[ 'data' ][ 'userActions' ];
            }

        }

        file_put_contents("entry.txt",(print_r($all_user_actions_entry,true)));
        file_put_contents("level.txt",(print_r($all_user_actions_level,true)));
        print_r(date("Y-m-d H:i:s") . PHP_EOL);
        $entry_level_info = array();
        foreach ($all_user_actions_entry as $user_id => $user_entry_action) {
            if (key_exists($user_id, $all_user_actions_level)) {

                $user_level_action = $all_user_actions_level[ $user_id ];

                // 这里是 用户 的 进入 包含多个房间的 数据


                // 判断 用户是否多次 进入进出
                if (key_exists('list', $user_entry_action) and key_exists('list', $user_level_action)) {
                    //print_r("$user_id:" . "用户多次进入进出" . PHP_EOL);
                    // 便利所有的 进入记录
                    array_walk($user_entry_action[ 'list' ], function ($value, $key) use (&$entry_level_info, $user_id) {
                        $entry_level_info[ $value[ 'roomId' ] ][ $value[ 'recordId' ] ][ $user_id ][ 'entry_time' ][] = $value['time'];
                    });
                    // 遍历 所有 的 退出 资料
                    array_walk($user_level_action[ 'list' ], function ($value, $key) use (&$entry_level_info, $user_id) {
                        $entry_level_info[ $value[ 'roomId' ] ][ $value[ 'recordId' ] ][ $user_id ][ 'level_time' ][] = $value["time"];
                    });

                } else if (!key_exists('list', $user_entry_action) and !key_exists('list', $user_level_action)) {
                    //两边都是 一对一 的
                    //print_r("$user_id:" . "用户单次进入退出" . PHP_EOL);
                    $entry_level_info[ $user_entry_action[ 'roomId' ] ][ $user_entry_action[ 'recordId' ] ][ $user_id ][ 'entry_time' ][] = $user_entry_action['time'];
                    $entry_level_info[ $user_entry_action[ 'roomId' ] ][ $user_entry_action[ 'recordId' ] ][ $user_id ][ 'level_time' ][] = $user_level_action['time'];

                } else {
                    // 有 多对- 的情况  进入或者输出 一方有多个值
                    $this->consoleAndLog("skip  ".PHP_EOL);
                    $un_process_date[ $user_id ][] = $user_entry_action;
                    $un_process_date[ $user_id ][] = $user_level_action;
                }

            } else {
                $this->consoleAndLog("发现 没有离开的数据! user_id:" . $user_id . "======" . (print_r($user_entry_action, true)) . PHP_EOL);

            }
        }

        file_put_contents("entry_level.txt",(print_r($entry_level_info,true)));

        //  从整理的数据中开始 准备时间的数据
        array_walk($entry_level_info, function ($recorde_info, $room_id) {
            // 根据 roomid 来获取  课程 信息  然后 通过课程信息和 订单信息 来 查询 这个课程的 id 究竟是哪一个


            array_walk($recorde_info, function ($user_time, $recode_id) use ($room_id) {

                  $course_statistics = new CourseStatistics();
                $course_statistics ->getStatisticsTimeByRoomId($room_id);
                if(array_key_exists('statistics_time',$course_statistics)){
                    $statistics_time =$course_statistics['statistics_time'];
                }else{
                    $this->consoleAndLog("无法 统计 时长 ");
                    $statistics_time = 100;
                }


                //  便利这个 同一个 房间下面同一个  回放id 下面的 时间列表
                array_walk($user_time, function ($time_list, $user_id) use ($room_id, $recode_id,$statistics_time) {

                    $this->consoleAndLog('room_id:'.$room_id. " user_id:".$user_id.PHP_EOL);
                    $school_course_info = Course::getCourseInfoForRoomIdAndStudentId($room_id, $user_id);
                    if(empty($school_course_info)){
                        $this->consoleAndLog('room_id:'.$room_id." user_id:".$user_id."未发现 有效课程信息".PHP_EOL);

                        $school_course_info['school_id'] = 2;
                        $school_course_info['course_id'] = 3622;
                       // return;
                    }

                    if (!array_key_exists("entry_time",$time_list) or !array_key_exists('level_time',$time_list)){
                        $this->consoleAndLog('skip'.PHP_EOL);
                        return;
                    }
                    $entry_time_list = $time_list[ 'entry_time' ];
                    $level_time_list = $time_list[ 'level_time' ];

                    // 计算的时候 如果list 有多个值 按照进入时间和离开时间 一一 配对 计算
                    // 如果只有 进入时间 只有一个 那么 获取 离开的的第一个时间来计算
                    // 如果不匹配 那么 （首次）进入时间 -- 第一个离开时间 （以后）进入时间 --（当次或者最后的）离开时间

                    if (count($entry_time_list) == 1) {
                        $start_time = $entry_time_list[ 0 ];
                        $end_time = $level_time_list[ 0 ];
                        $this->processRecode($user_id,$school_course_info[ 'school_id' ], $school_course_info[ 'course_id' ], $recode_id, $room_id, $start_time, $end_time,$statistics_time);
                    } else {

                        for ($x = 0; $x < count($entry_time_list); $x++) {
                            if ($x == 0) {
                                $start_time = $entry_time_list[ 0 ];
                                $end_time = $level_time_list[ 0 ];
                                $this->processRecode($user_id,$school_course_info[ 'school_id' ], $school_course_info[ 'course_id' ], $recode_id, $room_id, $start_time, $end_time,$statistics_time);
                            } else {
                                $start_time = $entry_time_list[ $x ];
                                $end_time = (count($level_time_list)  <= ($x)) ? end($level_time_list) : $level_time_list[ $x ];
                                $this->processRecode($user_id,$school_course_info[ 'school_id' ], $school_course_info[ 'course_id' ], $recode_id, $room_id, $start_time, $end_time,$statistics_time);

                            }
                        }
                    }
                });
            });


        });


        print_r(date("Y-m-d H:i:s") . PHP_EOL);

        $this->consoleAndLog('结束' . $this->description . PHP_EOL);
        $this->unlockFile();
    }


    function processRecode($user_id,$school_id, $course_id, $room_id, $recode_id, $start_time, $end_time,$statistics_time)
    {
        $message = "add recode info school_id:[%s] course_id:[%s] room_id:[%s] recode_id:[%s] start_time:[%s] end_time[%s] watch_time:[%s]";

        //school_id:[2] course_id:[3622] room_id:[B24A3793DD31C7D09C33DC5901307461] recode_id:[D1B7407323B4A458] start_time:[2020-12-29 22:21:42.0] end_time[2020-12-29 21:21:31.0]
        $course_statics = new CourseStatisticsDetail();
        list($watch_start_time, $sec) = explode(".", $start_time);
        list($watch_end_time, $sec) = explode(".", $end_time);

        $watch_time = strtotime($watch_end_time) - strtotime($watch_start_time);
        // 判断 学习 进度  计算 用户停留时间 和 总的  直播时间 的 差 不小于 5% 即可
        $learn_rate = round($watch_time / $statistics_time * 100);
        $this->consoleAndLog(sprintf($message, $school_id, $course_id, $recode_id, $room_id, $start_time, $end_time,$watch_time) . PHP_EOL);
        //$course_statics->addRecodeRecode($room_id,$school_id,$course_id,$recode_id,$user_id,'app',$watch_time,$start_time,$learn_rate,$learn_rate >95);

    }

    /**
     *   处理用户数据
     *   将用户数据
     * @param $user_action_list
     * @param $type
     */
    function processUserAction($user_action_list, $type)
    {

    }

    function consoleAndLog($str)
    {
        echo $str;
        Log::info($str);
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
