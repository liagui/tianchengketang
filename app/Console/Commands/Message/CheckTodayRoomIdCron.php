<?php


namespace App\Console\Commands\Message;


use App\Models\Coures;
use App\Models\CouresSubject;
use App\Models\Course;
use App\Models\CourseLiveClassChild;
use App\Models\Order;
use App\Models\School;
use App\Models\SchoolConnectionsCard;
use App\Models\SchoolConnectionsDistribution;
use App\Models\SchoolConnectionsLog;
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
use Predis\Response\Iterator\MultiBulk;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;

/**
 * 统计 今日的额要开启的直博的 id
 *  每天凌晨 定时 执行 将 今日带开课的 直播间的id
 *  放入到 reids 供后续消费定时任务消费执行
 * Class CheckTodayRoomIdCron
 * @package App\Console\Commands\Message
 */
class CheckTodayRoomIdCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'TodayRoomIdCron';

    const TODAY_ROOM_ID_LIST = "today_room_id_list";

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '统计当日要开始直播';

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
        $this->consoleAndLog("开始:" . $this->description . PHP_EOL);

        $now_time = strtotime("2020-12-11");
         $this->consoleAndLog("统计时间:".date("Y-m-d",$now_time));
        $CourseLive = new CourseLiveClassChild();
        $order = new Order();

        $room_id_list = $CourseLive->getRoomIdByDate($now_time);
        foreach ($room_id_list as $room_id) {

            $ret = Course::getCourseInfoForRoomId($room_id);
            if (isset($ret[ 'live_info' ])) {
                $live_info = $ret[ 'live_info' ];
                print_r("设定 房间号和 开课的时间".PHP_EOL);
                Redis::ZADD( self::TODAY_ROOM_ID_LIST ,$live_info['start_time'],$room_id);
                $this->consoleAndLog("RoomID:[".$room_id."]@".$live_info['start_time'].PHP_EOL);
            }
        }
        $this->consoleAndLog("结束:" . $this->description . PHP_EOL);

    }

    function consoleAndLog($str)
    {
        $str .= PHP_EOL;
        echo $str;
        Log::info($str);
    }


}
