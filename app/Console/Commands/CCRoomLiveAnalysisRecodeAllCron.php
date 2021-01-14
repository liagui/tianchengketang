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

class CCRoomLiveAnalysisRecodeAllCron extends Command
{
    const ACTION_IN = 0;
    const PAGE_NUM_COUNT = 1000;
    const ACTION_OUT = 1;
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'CCAnalysisRecodeAllCron';
    private $time_delay = 60 * 1;
    const ANALYSIS_CC_ROOM = "analysis_cc_room";
    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = 'CC 直播回放统计分析功能(统计从2020年12月01号开始的所有直播回看)';

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

        $this->consoleAndLog('开始' . $this->description . PHP_EOL);
        $start_day = '2020-12-01';

        $first_day = strtotime(date('Y-m-d', strtotime($start_day)));
        $last_day =  strtotime(date('Y-m-d', strtotime("now")));

        $LiveAnalysisRecode = new CCRoomLiveAnalysisRecodeCron();

        $current_time = $first_day;
        while( $current_time <= $last_day ){

            $yesterday_start = date("Y-m-d 00:00:01", ($current_time));
            $yesterday_end = date("Y-m-d 23:59:59", ($current_time));

            $this->consoleAndLog('统计时间:' . $yesterday_start . "---" . $yesterday_end . PHP_EOL);

            list($entry_level_info, $un_process_date) = $LiveAnalysisRecode->ProcessCCCLoudUserActionsByDate($yesterday_start, $yesterday_end);
            $current_time = strtotime("+1 day",$current_time);

            sleep(1);
        }

        $this->consoleAndLog('结束' . $this->description . PHP_EOL);

    }

    function consoleAndLog($str)
    {
        echo $str;
        Log::info($str);
    }

}
