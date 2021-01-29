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

use App\Console\Commands\Message\CheckTodayRoomIdCron;
use App\Models\CouresSubject;
use App\Models\Course;
use App\Models\CourseStatistics;
use App\Models\CourseStatisticsDetail;
use App\Models\Order;
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

class CCRoomLiveAnalysisLiveCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'CCAnalysisLiveCron';
    private $time_delay = 60 * 1;
    const ANALYSIS_CC_ROOM = "analysis_cc_room";
    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = 'CC 直播间统计分析功能(只统计直播功能的到课率)';

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

        if ($this->lockFile() == false) {
            die();
        }

        while (true) {

            try {

                $ret = Redis::ZRANGE(CCRoomLiveAnalysisLiveCron::ANALYSIS_CC_ROOM, 0, -1, array( 'withscores' => true ));

                $now = time();
                $cc_cloud = new CCCloud();

                if ($ret === false) {
                    print_r("sleep in 5 sec " . PHP_EOL);
                    sleep(5);
                    continue;
                }

                $this->processRoomIdList($ret, $now, $cc_cloud);


            } catch (\Exception $ex) {
                $this->consoleAndLog("发生错误，重新启动！");
            }
            sleep(5); // 默认 暂停 5秒钟
        }


        $this->consoleAndLog('结束:' . $this->description . PHP_EOL);

    }



    function consoleAndLog($str)
    {
        echo $str;
        Log::info($str);
    }


    // 准备一下test 的数据
    function test()
    {


//
        Redis::zadd(CCRoomLiveAnalysisLiveCron::ANALYSIS_CC_ROOM, strtotime("now"), "AA32E96D8DFFC3379C33DC5901307461");
//        sleep(1);
//        Redis::zadd(CCRoomLiveAnalysisCron::ANALYSIS_CC_ROOM,strtotime("now"),"61DD535FDE8EBF829C33DC5901307461");


    }

    function clean()
    {
        // ZRANGE myzset 0 -1
        Redis::del(CCRoomLiveAnalysisLiveCron::ANALYSIS_CC_ROOM);
    }

    /**
     * @param CCCloud $cc_cloud
     * @param $live_id
     * @param $room_id
     * @param $live_time
     */
    private function processLiveActionsList(CCCloud $cc_cloud, $live_id, $room_id, $live_time): void
    {
        $this->consoleAndLog("开始获取直播用户列表" . PHP_EOL);
        $live_useraction_List = $cc_cloud->CC_statis_live_useraction($live_id);
        sleep(1);

        if ($live_useraction_List[ 'code' ] == '0') {
            $userEnterLeaveActions = $live_useraction_List[ 'data' ][ 'userEnterLeaveActions' ];

            //$count = $live_useraction_List[ 'data' ][ 'count' ];
            $count = count($userEnterLeaveActions);

            $page_index = $live_useraction_List[ 'data' ][ 'pageIndex' ];
            $this->consoleAndLog("total count:[" . $count . "]" . " pageIndex:" . $page_index . PHP_EOL);

            // 首先获取 这个直报间对应的 课次信息 不必每一次查询
            $school_course_list = Course::getCourseInfoForRoomId($room_id);

            //  处理 这一次的 数据
            //  使用 尾递归  优化获取
            while ($count != '0') {

                $this->consoleAndLog(" process total count:[" . $count . "]" . " pageIndex:" . $page_index . PHP_EOL);

                // 处理 用户的列表数据
                $course_mod = new Course();
                $Course_statistics_mod = new CourseStatisticsDetail();

                $share_course_ids = [];
                if (isset($school_course_list[ 'live_info' ])) {
                    $live_info = $school_course_list[ 'live_info' ];
                    $share_course_ids = $live_info['ret_course_ids_share'];
                    print_r($share_course_ids);
                    unset($school_course_list[ 'live_info' ]);
                }
                if (is_string($school_course_list)){
                    $this->consoleAndLog("发生错误：".$school_course_list.PHP_EOL);
                    break;
                }
                //$school_course_list = array_column($school_course_list, 'course_id', 'school_id');

                // 添加 学生的学习进度
                $this->addLearnProcess($userEnterLeaveActions, $school_course_list, $Course_statistics_mod, $live_id, $live_time, $room_id,$share_course_ids);

                // 再次 获取 一起 列表数据
                $page_index += 1;

                $live_useraction_List = $cc_cloud->CC_statis_live_useraction($live_id, 100, $page_index);
                $userEnterLeaveActions = $live_useraction_List[ 'data' ][ 'userEnterLeaveActions' ];
                $count = count($userEnterLeaveActions);
                $this->consoleAndLog(" next page  count:[" . $count . "]" . " pageIndex:" . $page_index . PHP_EOL);
            }
        }
    }

    /**
     * @param $userEnterLeaveActions
     * @param array $school_course_list
     * @param CourseStatisticsDetail $Course_statistics_mod
     * @param $live_id
     * @param $live_time
     * @param $room_id
     */
    private function addLearnProcess($userEnterLeaveActions, array $school_course_list, CourseStatisticsDetail $Course_statistics_mod, $live_id, $live_time, $room_id,$share_course_ids = null): void
    {
        $order_mod = new Order();

        foreach ($userEnterLeaveActions as $action) {

            $userRole = $action[ 'userRole' ];
            $student_id = $action[ 'viewerId' ]; //换出 学生id
            $viewerName = $action[ 'viewerName' ];
            $enterTime = $action[ 'enterTime' ];
            $leaveTime = $action[ 'leaveTime' ];
            $watchTime = $action[ 'watchTime' ];
            $terminal = $action[ 'terminal' ]; // 终端类型 0 pc 1 app
            $customInfo = $action[ 'customInfo' ]; // 附带的
            if (!empty($customInfo)) {
                // 解密 custominfo
                $customInfo = json_decode($customInfo, true);
                $school_id_from_cc = $customInfo[ 'school_id' ];

                // 判断 一下 用户的角色
                // userRole 用户角色，1:主讲、推流端角色， 2:助教端角色，3:主持人角色，4:学生、观看端角色
                if ($userRole == '4') {

                    if(empty($school_course_list)){
                        print_r("该课程已经下架或者不存在so skip it ！！".PHP_EOL);
                        break;
                    }

                    $list = $order_mod ->CheckOrderSchoolIdWithStudent($school_course_list,$student_id,$share_course_ids);

                    if(empty($list)){

                       print_r("没有找到订单 so skip it ！！".PHP_EOL);
                       break;
                    }
                    print_r("已找到 订单信息 ！！ count :" .count($list).PHP_EOL);
                    print_r($list);
                    //这里 无论找到了 多少个 订单  依次处理
                    foreach ($list as $key => $order_info){

                        if($school_id_from_cc == $order_info['school_id']){

                            // 无论是不是授权课这里都能换成正常的 课程id （不同的课程下面的课程id）
                            $course_id = $order_info[ "class_id" ];

                            // 判断 学习 进度  计算 用户停留时间 和 总的  直播时间 的 差 不小于 5% 即可
                            $learn_rate = round($watchTime / $live_time * 100);

                            // 写入数据中 增加学生的学习进度 如果学习进度大于 95 就认为学习已经完成
                            // addLiveRecode($school_id,$courese_id,$live_id,$student_id,$learning_styles,$learning_time,$learning_finish)
                            $msg = " addLiveRecode :: school_id:[%s] course_id:[%s] live_id:[%s] student_id:[%s] enterTime:[%s] levelTime:[%s] learn_rate:[%s]";
                            print_r(sprintf($msg,$school_id_from_cc,$course_id,$live_id,$student_id,$enterTime,$leaveTime,$learn_rate).PHP_EOL);

                            // 修正  着了  只要学习的时间大于 95 就认为 学习完成了
                            ($learn_rate > 95) ? $learn_rate = 100 : 0;

                            $Course_statistics_mod->addLiveRecode($room_id, $school_id_from_cc, $course_id, $live_id, $student_id, $terminal,
                                $watchTime, $enterTime, $learn_rate, $learn_rate == 100);


                        }else{
                            print_r("进入 school_id 和 订单的 school_id  不一致: [" .$school_id_from_cc ."<=>". $order_info['school_id']."]"   .PHP_EOL);
                        }
                    }


                }
            }

            // 这里 处理 主讲的 信息 这里写入 课次的直播信息
            if ($userRole == '1') {
                // 这里 写入  这个 直播间下  的 授课 时长
//                $school_course_list = Course::getCourseInfoForRoomId($room_id);
//                if (isset($school_course_list[ 'live_info' ])) {
//                    $live_info = $school_course_list[ 'live_info' ];
//                    unset($school_course_list[ 'live_info' ]);
//                }
                //$school_course_list = array_column($school_course_list, 'course_id', 'school_id');
                if (empty($school_course_list)){
                    print_r("该课程已经下架或者不存在so skip it ！！".PHP_EOL);
                    break;
                }

                $list = $order_mod ->CheckOrderSchoolIdWithStudent($school_course_list);

                $course_room_statics = new CourseStatistics();

                //这里 无论找到了 多少个 订单  依次处理
                foreach ($list as $key => $order_info){

                    $school_id = $order_info['school_id'];
                    $course_id = $order_info['class_id'];

                    $msg =" addStatisticsBySchoolAndCourseId :: school_id:[%s] courser_id:[%s] room_id: [%s] enterTime:[%s] leaveTime:[%s] watchTime:[%s] ".PHP_EOL;
                    $this->consoleAndLog(sprintf($msg,$school_id, $course_id, $room_id, $enterTime, $leaveTime, $watchTime));
                    $course_room_statics->addStatisticsBySchoolAndCourseId($school_id, $course_id, $room_id, $enterTime, $leaveTime, $watchTime);

                }


//                // 这里 写入 房间 的 直播的 直播时长
//                foreach ($school_course_list as  $school_id =>  $course_id) {
//                    $msg =" addStatisticsBySchoolAndCourseId :: school_id:[%s] courser_id:[%s] room_id: [%s] enterTime:[%s] leaveTime:[%s] watchTime:[%s] ".PHP_EOL;
//                    $this->consoleAndLog(sprintf($msg,$school_id, $course_id, $room_id, $enterTime, $leaveTime, $watchTime));
//                    $course_room_statics->addStatisticsBySchoolAndCourseId($school_id, $course_id, $room_id, $enterTime, $leaveTime, $watchTime);
//
//                }
            }


        }
    }

    /**
     * @param $ret
     * @param int $now
     * @param CCCloud $cc_cloud
     */
    private function processRoomIdList($ret, int $now, CCCloud $cc_cloud): void
    {
        foreach ($ret as $room_id => $time_span) {

            //  $room_id = "AA32E96D8DFFC3379C33DC5901307461"; //这个 是 测试 账号
            $this->consoleAndLog("房间:id[$room_id]的直播统计信息" . PHP_EOL);

            $room_date = date("Y-m-d h:i:s", $time_span);
            $time_use = $now - $time_span;

            //  这里实在 直播完成后 30分钟内 进行执行处理
            if($time_use < 30*60 ){
                $this->consoleAndLog( "跳过次处理: time_use:".$time_use);
                continue;
            }

            $log = "处理房间:id[$room_id]的直播统计信息" . PHP_EOL;

            $this->ProcessCCLiveUserWatch($cc_cloud, $room_id);

            // 处理 清理 这个room id 无论 是否获取到 观看直播的信息 都讲这个 room 从 redis中 删除
            Redis::ZREM(CCRoomLiveAnalysisLiveCron::ANALYSIS_CC_ROOM, $room_id);
            $this->consoleAndLog("clear room ".$room_id.PHP_EOL);

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

    /**
     * @param CCCloud $cc_cloud
     * @param string $room_id
     */
    public function ProcessCCLiveUserWatch(CCCloud $cc_cloud, string $room_id): void
    {
        $room_info = $cc_cloud->cc_live_info($room_id);
        $course_statistics = new CourseStatistics();

        if (!empty($room_info[ "data" ]) and !empty($room_info[ "data" ][ 'lives' ])) {
            $lives = ($room_info[ "data" ][ 'lives' ]);
            $this->consoleAndLog("获取到直播的列表,默认 处理第一个 直播共有: " . (count($lives)) . PHP_EOL);
            $live_info = $lives[ 0 ];

            print_r("本次直播信息" . PHP_EOL);
            print_r($live_info);
            $live_start_time_span = strtotime($live_info[ 'startTime' ]);
            $live_end_time_span = strtotime($live_info[ 'endTime' ]);
            // 质保时间
            $live_time = $live_end_time_span - $live_start_time_span;
            print_r("直播时间:" . ($live_end_time_span - $live_start_time_span) . "Q" . PHP_EOL);
            // 获取本次直播中 用户的 进入 进出情况

            $ret = $cc_cloud->CC_statis_room_useraction($room_id, $live_info[ 'startTime' ],
                $live_info[ 'endTime' ], 0);


            // 这里 处理完 所有 的 学生 停留时间
            $this->processLiveActionsList($cc_cloud, $live_info[ 'id' ], $room_id, $live_time);

            // 这里应该处理 课程的直播完成率
            $course_statistics ->updateAllSchoolIdCourseLiveRateWithRoomId($room_id);


        }else{
            $this->consoleAndLog("直播信息为空！ so skip it！".$room_id.PHP_EOL);
        }
    }


}
