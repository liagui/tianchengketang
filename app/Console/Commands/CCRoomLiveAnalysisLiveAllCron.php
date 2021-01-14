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
use App\Models\CourseLiveClassChild;
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

class CCRoomLiveAnalysisLiveAllCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'CCAnalysisLiveAllCron';
    private $time_delay = 60 * 1;
    const ANALYSIS_CC_ROOM = "analysis_cc_room";
    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = 'CC 直播间统计分析功能(统计从2020-12-01到今天所有的数据)';

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
        ini_set('memory_limit', '512M');

        $this->consoleAndLog('开始' . $this->description . PHP_EOL);
        $start_day = '2020-12-01';

        $first_day = strtotime(date('Y-m-d', strtotime($start_day)));
        $last_day = strtotime(date('Y-m-d', strtotime("now")));

        $LiveAnalysisRecode = new CCRoomLiveAnalysisRecodeCron();

        $CourseLive = new CourseLiveClassChild();

        $CCRoomLiveAnalysisLiveCron = new CCRoomLiveAnalysisLiveCron();
        $cccloud = new CCCloud();

        $current_time = $first_day;
        while ($current_time <= $last_day) {

            $yesterday_start = date("Y-m-d 00:00:01", ($current_time));
            $yesterday_end = date("Y-m-d 23:59:59", ($current_time));

            $this->consoleAndLog('统计时间:' . $yesterday_start . "---" . $yesterday_end . PHP_EOL);

            // 查询这一天所有的 roomids
            $room_id_list = $CourseLive->getRoomIdByDate($current_time);

            foreach ($room_id_list as $room_id){
                $CCRoomLiveAnalysisLiveCron->ProcessCCLiveUserWatch($cccloud,$room_id);
                sleep(3);
            }


            $current_time = strtotime("+1 day", $current_time);
        }


    }


    function consoleAndLog($str)
    {
        echo $str;
        Log::info($str);
    }


}
