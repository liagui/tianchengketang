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
use Illuminate\Console\Comand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Predis\Response\Iterator\MultiBulk;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;

class CCRoomLiveAnalysisCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'CCAnalysisCron';
    private $time_delay = 60 * 1;
    const ANALYSIS_CC_ROOM = "analysis_cc_room";
    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = 'CC 直播间统计分析功能';

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
        echo $this->description;
        /**
         *  计算步骤
         *    在直播结束后通过 cc 的回调把 room_id 和 当前的时间戳 压入 zset value room_id score 当前的时间戳
         *  1 通过redis 获取到 analysis_cc_room 中的房间号码
         *    使用ZRANGE 获取前10 个带统计分析的roomid
         *  2 一次便利
         *
         *
         */
        $this->consoleAndLog('开始统计CC点播昨日并发数使用情况' . PHP_EOL);


        $ret = Redis::ZRANGE(CCRoomLiveAnalysisCron::ANALYSIS_CC_ROOM, 0, -1, array( 'withscores' => true ));

        $now = time();
        $cc_cloud = new CCCloud();
        foreach ($ret as $room_id => $time_span) {

            $room_id = "80DB179F860F77CF9C33DC5901307461";
            $this->consoleAndLog("房间:id[$room_id]的直播统计信息" . PHP_EOL);

            $room_date = date("Y-m-d h:i:s", $time_span);
            $time_use = $now - $time_span;

            $log = "处理房间:id[$room_id]的直播统计信息" . PHP_EOL;
            $room_info = $cc_cloud->cc_live_info($room_id);

            if (!empty($room_info[ "data" ]) and !empty($room_info[ "data" ][ 'lives' ])) {
                $lives = ($room_info[ "data" ][ 'lives' ]);
                $this->consoleAndLog("获取到直播的列表,默认 处理第一个 直播共有: " . (count($lives)) . PHP_EOL);
                $live_info = $lives[ 0 ];

                // 获取本次直播中 用户的 进入 进出情况

                $ret = $cc_cloud->CC_statis_room_useraction($room_id, $live_info[ 'startTime' ],
                    $live_info[ 'endTime' ], 0);

                $this->consoleAndLog("开始获取用户进入列表" . PHP_EOL);
                print_r($ret);

                $this->consoleAndLog("开始获取用户离开列表" . PHP_EOL);
                $ret = $cc_cloud->CC_statis_room_useraction($room_id, $live_info[ 'startTime' ],
                    $live_info[ 'endTime' ], 1);

                print_r($ret);


                $this->consoleAndLog("开始获取直播用户列表" . PHP_EOL);
                $ret = $cc_cloud->CC_statis_live_useraction($live_info[ "id" ]);
                print_r($ret);

            }

            die();
        }

        $this->consoleAndLog('CC直播并发数统计完毕！');


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
        Redis::zadd(CCRoomLiveAnalysisCron::ANALYSIS_CC_ROOM,strtotime("now"),"78A4E25B3B12728A9C33DC5901307461");
//        sleep(1);
        Redis::zadd(CCRoomLiveAnalysisCron::ANALYSIS_CC_ROOM,strtotime("now"),"61DD535FDE8EBF829C33DC5901307461");


    }

    function clean()
    {
        // ZRANGE myzset 0 -1
        Redis::del(CCRoomLiveAnalysisCron::ANALYSIS_CC_ROOM);
    }


}
