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
use Illuminate\Support\Facades\Redis;
use Predis\Response\Iterator\MultiBulk;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;

class CCConnectionsCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'CCConnCron';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = 'CC 并发量统计';

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
         *  1 获取昨天的日期
         *  2 获取学校列表
         *  3 便利学校列表
         *  4     从redis 中获取每一个 学校的当前日期的并发数使用情况
         *  5     更新每一个学校的本月的可使用并发数据
         *  6     更新完毕
         *
         */
        $this->consoleAndLog('开始统计CC点播昨日并发数使用情况'.PHP_EOL);
        $school_list = School::getSchoolAlls();
        $CCCloud = new CCCloud();
        $CCCloud->setDebug(true);

        $yesterday = date("Y-m-d", strtotime("-1 day"));
        $today = date("Y-m-d", strtotime("now"));
        Log::info('统计时间段：start_day:' . $yesterday . "end_day" . $today);

        $school_resource = new SchoolResource();
        $school_conn_dis = new SchoolConnectionsDistribution();
        $connection_log = new SchoolConnectionsLog();

        // 遍历所有的学校
        foreach ($school_list as $school) {

            $school_id = $school[ 'id' ];
            $school_name = $school[ 'name' ];

            // 网校 当前 的 并发数目 注意这里处理的是昨天的 对于夸月的情况也是如此
            $key = $school_id."_"."num_".date("Y_m",strtotime($yesterday));
            $num = Redis::get($key);

            $log =  "school:[$school_name][$school_id]:本月的可用并发数:[$key]=>[$num]".PHP_EOL;
            $this->consoleAndLog($log);

            //当前已经使用的并发数 注意 这里计算的时间是昨天的
            $key_now_num = $school_id."_"."num_now_".date("Y_m_d", strtotime($yesterday));
            $now_num = Redis::get($key_now_num);

            $log =  "school:[$school_name][$school_id]:当日已经使用的并发数:[$key_now_num]=>[$now_num]".PHP_EOL;
            $this->consoleAndLog($log);

            //var_dump($now_num);

            // 写入日志写入到并发日志中 表示当前已经使用的日志
            $connection_log->addUsedLog($school_id, $now_num, SchoolConnectionsLog::CONN_CHANGE_LOG, $yesterday);

            //todo 当并发数统计中发现最大的超过的并发数 是否禁止网校登录？？

            // 更新 redis 中 学校当月的可用并发数 和 今日已使用的并发数
            $month_num_used = $school_conn_dis->getDistributionByDate($school_id, date("Y-m-d"));
            Redis::set($key,$month_num_used);
            Redis::set($key_now_num,0);
            $log =  "school:[$school_name][$school_id]::[$key]=>[$month_num_used]".PHP_EOL;
            $this->consoleAndLog($log);
            
        }

        Log::info('CC直播并发数统计完毕！');


    }

    function  consoleAndLog($str){
        echo $str;
        Log::info($str);
    }




}
