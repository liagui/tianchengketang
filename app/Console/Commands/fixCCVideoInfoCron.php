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
use App\Models\CourseLivesResource;
use App\Models\CourseStatistics;
use App\Models\CourseStatisticsDetail;
use App\Models\CourseVideoResource;
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

class fixCCVideoInfoCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'fixCCVideoInfoCron';
    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '补充 CC 点播业务中上传的视频没有补充视频时长的问题 ';

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

        $page = 1;

        $video_list = $this->getVideos();
        print_r("处理 第 " . $page . "页 data count " . count($video_list) . PHP_EOL);

        $cc = count($video_list);

        while ($cc != 0) {


            $this->ProcessVideoList($video_list);

            $page += 1;
            $video_list = $this->getVideos($page);
            $cc = count($video_list);
            print_r("获取 第 " . $page . "页 data count " . count($video_list) . PHP_EOL);
            sleep(2);
        }


        $this->consoleAndLog('结束:' . $this->description . PHP_EOL);

    }


    function getVideos($page = 1, $pageNum = 100)
    {
        print_r("page : $page @pageNum: $pageNum" . PHP_EOL);
        $course_video = new CourseVideoResource();
        //  查询 条件
        $query = $course_video->newQuery()
            ->whereRaw(" service = 'CC' ")
            ->orderBy("id");
        $offset = ($page - 1) * $pageNum;

        $query->offset($offset)->limit($pageNum);
        $query->select([ 'cc_video_id', 'mt_duration' ]);

        $ret = $query->get();
        if (empty($ret)) {
            return array();
        } else {
            return $ret->toArray();
        }


    }

    function ProcessVideoList(array $video_list)
    {
        $cc_video_list = array_column($video_list, "cc_video_id");

        $orgin_count = count($cc_video_list);

        $cc_cloud = new CCCloud();
        $ret = $cc_cloud->CC_Videos_V7($cc_video_list);
        if ($ret[ 'code' ] == "0") {
            $videos = $ret[ 'data' ][ 'videos' ];
            $total = $videos[ 'total' ];
            $video = $videos[ 'video' ];
            // 处理 返回 的 结果

            print_r("query count: $orgin_count  get count: $total " . PHP_EOL);
            foreach ($video as $info) {
                $id = $info[ 'id' ];
                $duration = $info[ 'duration' ];
                //print_r(" update info: video_Id: $id duration: $duration " . PHP_EOL);
                $this->update($id, $duration);

            }


        }


    }


    function update($video_id, $duration)
    {

        $query = CourseVideoResource::query();
        $ret = $query->where("cc_video_id", '=', $video_id)->update([ 'mt_duration' => $duration ]);
        //print_r("update return: $ret" . PHP_EOL);
    }

    function consoleAndLog($str)
    {
        echo $str;
        Log::info($str);
    }


}
