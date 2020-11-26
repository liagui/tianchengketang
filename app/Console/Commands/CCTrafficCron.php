<?php


namespace App\Console\Commands;


use App\Models\CouresSubject;
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
use Predis\Response\Iterator\MultiBulk;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;

class CCTrafficCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'CCTrafficCron';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = 'CC 点播流量统计';

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

        /**
         *  计算步骤
         *  1 获取昨天的日期
         *  2 获取学校列表
         *  3 便利学校列表
         *  4     获取每一个学校的流量使用情况
         *  5     更新学校的流量统计信息
         *  6     更新完毕
         *
         */
        Log::info('开始统计CC点播昨日流量使用情况');
        $school_list = School::getSchoolAlls();
        $CCCloud = new CCCloud();
        $CCCloud->setDebug(true);

        $yesterday = date("Y-m-d", strtotime("-1 day"));
        $today = date("Y-m-d", strtotime("now"));
        $this->consoleAndLog('统计时间段：start_day:' . $yesterday . "end_day" . $today);
        $school_resource = new SchoolResource();

        // 遍历所有的学校
        foreach ($school_list as $school) {

            $school_id = $school[ 'id' ];
            $school_name = $school[ 'name' ];
            $ret_data = $CCCloud->cc_spark_api_traffic_user_custom_daily($school_id, $yesterday, $yesterday);

            // 判断 调用 是否 成功
            if ($ret_data[ 'code' ] == CCCloud::RET_IS_OK) {
                $traffic_info = $ret_data[ 'data' ][ 'traffics' ][ 'traffic' ];

                //  如果有返回值
                if (count($traffic_info) > 0) {
                    //cc 流量部分是按照 天来统计 同时区分 pc 和 mobile
                    $pc_traffic = intval($traffic_info[ 0 ][ 'pc' ]);
                    $mobile_traffic = intval($traffic_info[ 0 ][ 'mobile' ]);
                    $this->consoleAndLog("网校[$school_name:$school_id]流量统计结果："
                        . "pc:[" . $pc_traffic . "]mobile:[" . $mobile_traffic . "] total：" . ($pc_traffic + $mobile_traffic));
                    // 添加流量消费日志
                    $school_resource->updateTrafficUsage($school_id, intval($pc_traffic) + intval($mobile_traffic), $yesterday);
                }
            } else {
                Log::info("网校[$school_name:$school_id]流量审查失败!");
            }

        }

        Log::info('CC点播统计完毕！');

    }

    function  consoleAndLog($str){
        $str .= PHP_EOL;
        echo $str;
        Log::info($str);
    }



}
