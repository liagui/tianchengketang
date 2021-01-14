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
use phpDocumentor\Reflection\Types\Self_;
use phpDocumentor\Reflection\Types\True_;
use Predis\Response\Iterator\MultiBulk;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;

class CCRoomLiveAnalysisRecodeCron extends Command
{
    const ACTION_IN = 0;
    const PAGE_NUM_COUNT = 1000;
    const ACTION_OUT = 1;
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

    #region  调试相关的数据
    /**
     *  调试 相关的 api
     * @var int[]
     */
    private $_debug_user_id = array( 11655, 23551, 24317, 24775, 52038, 52253, 52764 );

    public function addDebugFilterUserId(int $user_id)
    {
        array_push($this->_debug_user_id, $user_id);
    }

    /**
     *  判断当前是否 生成调试文件
     * @var bool
     */
    private $_isDebug = false;
    #endregion

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
     *  设定 调试 模式
     * @param bool $is_debug
     */
    public function setDebug($is_debug = true)
    {
        $this->_isDebug = $is_debug;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        // $this->setDebug(true);
        ini_set('max_execution_time', 30000);
        ini_set('memory_limit', '512M');

        /**
         *  计算步骤
         *     获取昨日的全部直播回放的数据列表
         *         获取两次 第一次登陆 第二次 登出 全部放入到redis 中
         *     计算reids 中的数据 得到用户 观看直播锝对应的房号和观看时长
         */


        if ($this->lockFile() == false) {
            die();
        }


        $yesterday = date("Y-m-d", strtotime("-1 day"));
        $today = date("Y-m-d", strtotime("now"));

        //$yesterday = "2021-01-01";
        $yesterday_start = date("Y-m-d 00:00:01", strtotime($yesterday));
        $yesterday_end = date("Y-m-d 23:59:59", strtotime($yesterday));

        $this->consoleAndLog('开始' . $this->description . PHP_EOL);
        $this->consoleAndLog('当前时间:' . $today . PHP_EOL);
        $this->consoleAndLog('统计时间:' . $yesterday_start . "---" . $yesterday_end . PHP_EOL);

        list($entry_level_info, $un_process_date) = $this->ProcessCCCLoudUserActionsByDate($yesterday_start, $yesterday_end);

        $this->consoleAndLog('结束' . $this->description . PHP_EOL);
        $this->unlockFile();
    }


    function addRecode($user_id, $school_id, $course_id, $room_id, $recode_id, $start_time, $end_time, $statistics_time)
    {
        $message = "add recode info school_id:[%s] course_id:[%s] room_id:[%s] recode_id:[%s] start_time:[%s] end_time[%s] watch_time:[%s]";

        //school_id:[2] course_id:[3622] room_id:[B24A3793DD31C7D09C33DC5901307461] recode_id:[D1B7407323B4A458] start_time:[2020-12-29 22:21:42.0] end_time[2020-12-29 21:21:31.0]
        $course_statics = new CourseStatisticsDetail();
        // list($watch_start_time, $sec) = explode(".", $start_time);
        // list($watch_end_time, $sec) = explode(".", $end_time);

        $watch_time = intval($end_time) - intval($start_time);
        // 判断 学习 进度  计算 用户停留时间 和 总的  直播时间 的 差 不小于 5% 即可

        $learn_rate = round($watch_time / $statistics_time * 100);
        ($learn_rate >= 100) ? $learn_rate = 100 : 0;
        print_r(sprintf(" watch_time:[%s] course_time:[%s] so time is :[%s] ", $watch_time, $statistics_time, ($learn_rate)) . PHP_EOL);
        $this->consoleAndLog(sprintf($message, $school_id, $course_id, $recode_id, $room_id, date("Y-m-d H:i:s", $start_time), date("Y-m-d H:i:s", $end_time), $watch_time) . PHP_EOL);
        $course_statics->addRecodeRecode($room_id, $school_id, $course_id, $recode_id, $user_id,
            '1', $watch_time, date("Y-m-d H:i:s", $start_time), $learn_rate, $learn_rate > 95);

    }


    function consoleAndLog($str)
    {
        echo $str;
        Log::info($str);
    }

    #region 保证 定时任务单独执行 文件所
    private $_lock_file = __FILE__ . ".lock.file";

    /**
     * 加锁，独占锁
     */
    public function lockFile()
    {
        $this->handle = fopen($this->_lock_file, 'w+');
        if ($this->handle) {
            //如果文件被锁定则非阻塞操作
            if (flock($this->handle, LOCK_EX | LOCK_NB)) {
                return true;
            } else {

                $this->consoleAndLog("发现 lock file [" . $this->description . "] 退出");
            }
        }
        return false;
    }

    /**
     *解锁
     */
    public function unlockFile()
    {
        if ($this->handle) {//释放锁定
            flock($this->handle, LOCK_UN);
            clearstatcache();
            fclose($this->handle);
            unlink($this->_lock_file);
        }
    }
#endregion

    /**
     *  获取用户 登录的信息
     * @param CCCloud $CCCloud
     * @param string $yesterday_start
     * @param string $yesterday_end
     * @param array $all_user_actions_entry
     * @return array
     */
    private function getCCCloudUserActionIn(CCCloud $CCCloud, string $yesterday_start, string $yesterday_end, array $all_user_actions_entry): array
    {
        $pageIndex = 1;
        // 处理 用户 进入 的 时间 列表
        $ret = $CCCloud->CC_statis_user_record_useraction($yesterday_start, $yesterday_end, self::ACTION_IN, $pageIndex, self::PAGE_NUM_COUNT);
        if (isset($ret[ 'code' ]) and $ret[ 'code' ] == 0) {

            $userActions = $ret[ 'data' ][ 'userActions' ];
            $pageIndex = $ret[ 'data' ][ 'pageIndex' ];
            $count = $ret[ 'data' ][ 'count' ];

            while (!empty($userActions)) {

                //file_put_contents("aciont_0.txt", (print_r($ret,true)), FILE_APPEND);
                $this->consoleAndLog("process user entry " . ($yesterday_start . "--" . $yesterday_end) . " actionlist pageindex:" . $pageIndex . " rate " . ($pageIndex * self::PAGE_NUM_COUNT) . "/" . $count . PHP_EOL);

                // 循环 处理  每一条 用户 进入进出的 记录
                foreach ($userActions as $action) {

                    $data_tmp = array(
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

                        $all_user_actions_entry[ $userId ][ 'list' ][] = $data_tmp;
                    } else {
                        $all_user_actions_entry[ $userId ] = $data_tmp;
                    }
                }

                sleep(1);
                // 尾递归 优化一下获取数据
                $pageIndex += 1;
                $ret = $CCCloud->CC_statis_user_record_useraction($yesterday_start, $yesterday_end, self::ACTION_IN, $pageIndex, self::PAGE_NUM_COUNT);
                $userActions = $ret[ 'data' ][ 'userActions' ];
            }
        }
        return $all_user_actions_entry;
    }

    /**
     *
     *  获取用户 退出的信息
     * @param CCCloud $CCCloud
     * @param string $yesterday_start
     * @param string $yesterday_end
     * @param array $all_user_actions_level
     * @return array
     */
    private function getCCCloudUserActionOut(CCCloud $CCCloud, string $yesterday_start, string $yesterday_end, array $all_user_actions_level): array
    {
        $pageIndex = 1;
        // print_r(date("Y-m-d H:i:s") . PHP_EOL);
        $ret = $CCCloud->CC_statis_user_record_useraction($yesterday_start, $yesterday_end, self::ACTION_OUT, $pageIndex, self::PAGE_NUM_COUNT);
        if (isset($ret[ 'code' ]) and $ret[ 'code' ] == 0) {

            $userActions = $ret[ 'data' ][ 'userActions' ];
            $pageIndex = $ret[ 'data' ][ 'pageIndex' ];
            $count = $ret[ 'data' ][ 'count' ];

            while (!empty($userActions)) {
                $this->consoleAndLog("process user level " . ($yesterday_start . "--" . $yesterday_end) . " actionlist pageindex:" . $pageIndex . " rate " . ($pageIndex * self::PAGE_NUM_COUNT) . "/" . $count . PHP_EOL);

                // 循环 处理  每一条 用户 进入进出的 记录
                foreach ($userActions as $action) {

                    $data_tmp = array(
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

                        $all_user_actions_level[ $userId ][ 'list' ][] = $data_tmp;
                    } else {
                        $all_user_actions_level[ $userId ] = $data_tmp;
                    }
                }

                sleep(1);
                // 尾递归 优化一下获取数据
                $pageIndex += 1;
                $ret = $CCCloud->CC_statis_user_record_useraction($yesterday_start, $yesterday_end, 1, $pageIndex, self::PAGE_NUM_COUNT);
                $userActions = $ret[ 'data' ][ 'userActions' ];
            }

        }
        return $all_user_actions_level;
    }

    /**
     *
     *  修正一下数据 规整数据
     * @param array $all_user_actions_entry
     * @param array $all_user_actions_level
     * @param array $entry_level_info
     * @param array $un_process_date
     * @return array
     */
    private function fixData(array $all_user_actions_entry, array $all_user_actions_level, array &$entry_level_info, array &$un_process_date): array
    {
        foreach ($all_user_actions_entry as $user_id => $user_entry_action) {
            if (key_exists($user_id, $all_user_actions_level)) {

                $user_level_action = $all_user_actions_level[ $user_id ];

                // 处理用户 进入的数据
                if (key_exists('list', $user_entry_action)) {
                    //print_r("$user_id:" . "用户多次进入进出" . PHP_EOL);
                    // 便利所有的 进入记录
                    array_walk($user_entry_action[ 'list' ], function ($value, $key) use (&$entry_level_info, $user_id) {
                        $entry_level_info[ $value[ 'roomId' ] ][ $value[ 'recordId' ] ][ $user_id ][ 'entry_time' ][] = $value[ 'time' ];
                    });

                } else {
                    //  如果 只有 一样 的处理 变成数据
                    $entry_level_info[ $user_entry_action[ 'roomId' ] ][ $user_entry_action[ 'recordId' ] ][ $user_id ][ 'entry_time' ][] = $user_entry_action[ 'time' ];
                }

                // 用户退出的数据
                if (key_exists('list', $user_level_action)) {

                    // 遍历 所有 的 退出 资料
                    array_walk($user_level_action[ 'list' ], function ($value, $key) use (&$entry_level_info, $user_id) {
                        $entry_level_info[ $value[ 'roomId' ] ][ $value[ 'recordId' ] ][ $user_id ][ 'level_time' ][] = $value[ "time" ];
                    });

                } else {
                    //两边都是 一对一 的
                    $entry_level_info[ $user_level_action[ 'roomId' ] ][ $user_level_action[ 'recordId' ] ][ $user_id ][ 'level_time' ][] = $user_level_action[ 'time' ];

                }

            } else {
                $this->consoleAndLog("发现 没有离开的数据! user_id:" . $user_id . "======" . (print_r($user_entry_action, true)) . PHP_EOL);

            }
        }
        return $entry_level_info;
    }

    /**
     * @param array $entry_level_info
     * @return array
     */
    private function processUserActionList(array $entry_level_info): array
    {
        //  从整理的数据中开始 准备时间的数据
        array_walk($entry_level_info, function ($recorde_info, $room_id) {
            // 根据 roomid 来获取  课程 信息  然后 通过课程信息和 订单信息 来 查询 这个课程的 id 究竟是哪一个

            array_walk($recorde_info, function ($user_time, $recode_id) use ($room_id) {

                $course_statistics = new CourseStatistics();
                $course_time = $course_statistics->getStatisticsTimeByRoomId($room_id);
                if ( !empty($course_time) and $course_time->count() > 0) {

                    // 无论是 那个学校 的 课程 同一个 room_id 下的时间 是一样的
                    $statistics_time = $course_time[ 'statistics_time' ];
                } else {

                    $this->consoleAndLog("课程信息:room_id[$room_id]的统计时长不存在! so skip it!" . PHP_EOL);
                    return;
                }


                //  便利这个 同一个 房间下面同一个  回放id 下面的 时间列表
                array_walk($user_time, function ($time_list, $user_id) use ($room_id, $recode_id, $statistics_time) {

                    $this->consoleAndLog('room_id:' . $room_id . " user_id:" . $user_id . PHP_EOL);
                    $school_course_info = Course::getCourseInfoForRoomIdAndStudentId($room_id, $user_id);
                    if (empty($school_course_info)) {
                        $this->consoleAndLog('room_id:' . $room_id . " user_id:" . $user_id . "未发现 有效课程信息 跳过处理 " . PHP_EOL);

//                        $school_course_info[ 'school_id' ] = 2;
//                        $school_course_info[ 'course_id' ] = 3622;

                        return;
                    }

                    if (!array_key_exists("entry_time", $time_list) or !array_key_exists('level_time', $time_list)) {
                        print_r($time_list);
                        $this->consoleAndLog('skip it! 数据不完整' . PHP_EOL);
                        return;
                    }
                    $entry_time_list = $time_list[ 'entry_time' ];
                    $level_time_list = $time_list[ 'level_time' ];

                    // 计算 用户据 的进入进出时间序列从中获取到比较大的时间区间
                    $time_slot = self::CalculateTimeline($entry_time_list, $level_time_list);

                    // 循环 添加 数据
                    foreach ($time_slot as $start_time => $end_time) {
                        $this->addRecode($user_id, $school_course_info[ 'school_id' ], $school_course_info[ 'course_id' ],
                            $room_id, $recode_id,  $start_time, $end_time, $statistics_time);
                    }

                    // 更新 直播的回看的到课率

                    // 这里应该处理 课程的直播完成率
                    $course_statistics =  new CourseStatistics();
                    $course_statistics ->updateAllSchoolIdCourseLiveRateWithRoomId($room_id);


                });
            });
        });
        return $entry_level_info;
    }

    /**
     *  计算 一个 一个时间序列的 的时间段
     * @param $start_time_list
     * @param $end_time_list
     * @return array
     */
    public static function CalculateTimeline($start_time_list, $end_time_list)
    {
        $time_list = array();

        // 计算 进入的时间
        foreach ($start_time_list as $start_time) {

            list($start_time, $sec) = explode(".", $start_time);
            $time_list[] = array(
                'date' => $start_time,
                'time' => strtotime($start_time),
                'type' => 0 //0 标志 用户进入
            );
        }

        foreach ($end_time_list as $end_time) {
            list($end_time, $sec) = explode(".", $end_time);
            $time_list[] = array(
                'date' => $end_time,
                'time' => strtotime($end_time),
                'type' => 1 //0 标志 用户进入
            );
        }

        // 排序 按照时间戳 进行排序
//       // ksort($time_list);
//        $array_sortt_time = array_column($time_list,'time');
//        sort($array_sortt_time);
//        array_multisort($array_sortt_time,$time_list);

        $time_list = array_orderby($time_list, 'time', SORT_ASC);

        $time_line_slot = array();

        $last_type = null;  //最后一次的状态
        $last_in_time = null;
        $last_out_time = null;

        // 便利时间序列
        foreach ($time_list as $time_point) {

            $current_type = $time_point[ 'type' ];
            $current_time = $time_point[ 'time' ];
            if (is_null($last_type)) {
                // 第一次 直接写入值
                if ($time_point[ 'type' ] == 0) {
                    $last_in_time = $current_time;
                    // 写入 最后一次的状态
                    $last_type = $current_type;
                } else {
                    echo "不能从离开开始计算" . PHP_EOL;
                }

            } else {

                // 进入后再次进入的情况  直接下一次循环  相当于最后  只记录 第一次的进入时间
                if ($current_type == 0 and $last_type == 0) {
                    continue;
                }
                // 退出后再次退出的情况  跟新一下最新的退出时间 状态不变 相当于 只记录最后 一次退出的时间
                if ($current_type == 1 and $last_type == 1) {
                    $last_out_time = $current_time;
                    // 冲掉上一次的 离开时间
                    $time_line_slot[ $last_in_time ] = $current_time;
                    continue;
                }

                // 当前 是离开 并且上一次是 进入的情况 状态更改  暂时计入时间段 相当于临时凑成一个时间段
                if ($current_type == 1 and $last_type == 0) {
                    $last_out_time = $current_time; // 更新 最后 一次的时间
                    $last_type = $current_type;
                    // 临时凑成一对 如果后续没有 退出那么 这一对转正了
                    $time_line_slot[ $last_in_time ] = $last_out_time;
                    continue;
                }

                // ok 这里凑够了 一个时间段 清楚状态
                if ($current_type == 0 and $last_type == 1) {
                    // 在临时期内 这个必须能配成的一对
                    if (isset($time_line_slot[ $last_out_time ])) {

                        // 跟新这一对
                        $time_line_slot[ $last_in_time ] = $current_time;
                    }
                    // 这里 无法配成一对那么从新开始 设定开始日期
                    $last_type = $current_type;
                    $last_in_time = $current_time;
                    $last_out_time = null;

                    continue;
                }


            }
        }
        // 返回 计算好的时间对
        return $time_line_slot;

    }

    /**
     * @param string $yesterday_start
     * @param string $yesterday_end
     * @return array
     */
    public function ProcessCCCLoudUserActionsByDate(string $yesterday_start, string $yesterday_end): array
    {
        $CCCloud = new CCCloud();
        $CCCloud->_useRawUrlEncode = true; // 不适用 urlEncode 使用 rawEncode

        // 处理数据
        $all_user_actions_entry = array();
        $all_user_actions_level = array();
        $un_process_date = array();

        // 获取 所有的数据
        $all_user_actions_entry = $this->getCCCloudUserActionIn($CCCloud, $yesterday_start, $yesterday_end, $all_user_actions_entry);
        sleep(1);
        $all_user_actions_level = $this->getCCCloudUserActionOut($CCCloud, $yesterday_start, $yesterday_end, $all_user_actions_level);
        sleep(1);

        // 这里 增加以下昨天的昨天的 23:00:00 -- 23:59:59  的时间段
        $day_before_yesterday = date("Y-m-d", strtotime("-1 day", strtotime($yesterday_start)));
        $day_befor_yesterday_start = date("Y-m-d 23:00:00", strtotime($day_before_yesterday));
        $day_befor_yesterday_end = date("Y-m-d 23:59:59", strtotime($day_before_yesterday));

        // 获取 前天的 23点之后的数据
        $this->getCCCloudUserActionIn($CCCloud, $day_befor_yesterday_start, $day_befor_yesterday_end, $all_user_actions_entry);
        sleep(1);
        $this->getCCCloudUserActionOut($CCCloud, $day_befor_yesterday_start, $day_befor_yesterday_end, $all_user_actions_level);
        sleep(1);

        $entry_level_info = array();

        // 整理数据 以方便处理
        $entry_level_info = $this->fixData($all_user_actions_entry, $all_user_actions_level, $entry_level_info, $un_process_date);

        //file_put_contents("entry_level.txt",(print_r($entry_level_info,true)));
        $entry_level_info = $this->processUserActionList($entry_level_info);
        return array( $entry_level_info, $un_process_date );
    }

}
