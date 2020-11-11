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
        //  这里是测试代码 上线前 移除
        //$this->test();
        //die(PHP_EOL . "end");

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
        Log::info('统计时间段：start_day:' . $yesterday . "end_day" . $today);
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
                    Log::info("网校[$school_name:$school_id]流量统计结果："
                        . "pc:[" . $pc_traffic . "]mobile:[" . $mobile_traffic . "] total：" . ($pc_traffic + $mobile_traffic));
                    // 添加力量消费日志
                    $school_resource->updateTrafficUsage($school_id, intval($pc_traffic) + intval($mobile_traffic), $yesterday);
                }
            } else {
                Log::info("网校[$school_name:$school_id]流量审查失败!");
            }

        }

        Log::info('CC点播统计完毕！');

    }

    public function test()
    {
//        $room_id = "71E9773C2D991F2D9C33DC5901307461";
//        $cc_cloud = new CCCloud();
//        $ret = $cc_cloud ->cc_rooms_publishing($room_id);
//        print_r($ret);
//
//        $GB = "1";
//        print_r($GB*1024*1024*1024);
//
//        $month_first=date("Y-m-01", time());
//        $month_end=date("Y-m-t", time());
//        print_r($month_first);
//        print_r($month_end);
//
//        $School  = new SchoolConnectionsLog();
//        $ret =  $School -> getConnectionsLogByAdmin(3,date("Y-m-d"));
//        print_r($ret);

        /** 老仙 这里是代码   */
        $resource = new SchoolResource();

        // 增加一个网校的流量 参数：学校id 增加的流量（单位B，helper中有参数 可以转化） 购买的日期  固定参数add 是否使用事务固定false
        // 注意 流量没时间 限制 随买随用
        $resource->updateTrafficUsage(3,10, date("y-m-d"),"add",false);

        // 增加一个网校的空间 参数: 学校id 增加的空间 时间 固定参数add 固定参数video 固定参数是否使用事务 false
        // 注意 购买空间 空间这里没有时间
        $resource ->updateSpaceUsage(3,300, date("Y-m-d"),'add','video',false );

        // 空间续费 参数:学校的id 延期时间（延期到哪年那月）
        $resource ->updateSpaceExpiry(3,date("Y-m-d"));

        // 网校个并发数 参数： 网校id 开始时间 结束时间 增加的并发数
        $resource ->addConnectionNum(3,date("y-M-D"),date("Y-m-d"),300);

        /** 老仙 代码结束了   */

//        $school_distribution = new SchoolConnectionsDistribution();
//        $ret = $school_distribution ->getDistribution(1);
//        print_r($ret);
//
//         $start_date = date("Y-m-d", strtotime("-15 Day"));
//        $end_date = date("Y-m-d", strtotime("now"));
//        print_r("start_date：[".$start_date."] end_date:[".$end_date."]");


//        $category_id = "1E45E7766CFE4DCF";
//        $video_id = "8B76F28390735F859C33DC5901307461";
//        $CCCloud = new CCCloud();
//        $ret =  $CCCloud ->move_video_category($video_id,$category_id);
//        print_r($ret);
//       echo "ddd";
//
//        $video_id = "898989898989898989898989898";
//        $video = new Video();
//        $ret = $video->auditVideo($video_id);
//
//        if(!isset($ret['video_info'])){
//
//            $school_id = $ret['video_info']['school_id'];
//            $parent_id = $ret['video_info']['parent_id'];
//            $child_id = $ret['video_info']['child_id'];
//        }
//
//        $path_info = CouresSubject::GetSubjectNameById(1,1,18);
//
//        $cccloude = new CCCloud();
//        $ret = $cccloude -> cc_spark_video_category_v2();
//
//        if(!empty($ret)){
//           $cc_category =$ret['data'];
//           $first_category =array();
//           foreach ($cc_category as $first_item){
//                 echo "1".$path_info['school_name'],"===".$first_item['name'].PHP_EOL;
//                // 如果找到了 一级分类 学校
//                if($path_info['school_name'] == $first_item['name']){
//                    echo "find：first_category".$first_item['name'].PHP_EOL;
//                    $first_category  = $first_item;
//                }
//           }
//
//           if(empty($first_category)){
//                 // 如果没有找到一级分类
//                return $cccloude->makeCategory('',
//                     [$path_info['school_name'],$path_info['parent_name'],$path_info['children_name']]);
//           }
//
//            $sub_category = array();
//           // 处理二级 目录
//            foreach ($first_category['sub-category'] as $sub_item){
//                echo "2".$path_info['parent_name'],"===".$sub_item['name'].PHP_EOL;
//                // 如果找到了 一级分类 学校
//                if($path_info['parent_name'] == $sub_item['name']){
//                    echo "find：sub_category".$sub_item['name'].PHP_EOL;
//                    $sub_category  = $sub_item;
//                }
//            }
//            if(empty($sub_category)){
//                // 如果没有找到一级分类
//                return $cccloude->makeCategory($first_category['id'],
//                    [$path_info['parent_name'],$path_info['children_name']]);
//            }
//
//            //  处理三级目录
//
//            $child_category = array();
//            // 处理二级 目录
//            foreach ($sub_category['sub-category'] as $child){
//                echo "2".$path_info['parent_name'],"===".$child['name'].PHP_EOL;
//                // 如果找到了 一级分类 学校
//                if($path_info['children_name'] == $child['name']){
//                    echo "find：child_category".$child['name'].PHP_EOL    ;
//                    $child_category  = $child;
//                }
//            }
//            if(empty($child_category)){
//                // 如果没有找到一级分类
//                return $cccloude->makeCategory($sub_category['id'], [$path_info['children_name']]);
//            }
//            echo "child_category".$child_category['id'];
//            return $child_category['id'];
//
//        }


//        $start_date = "2020-11-02";
//        $end_date = "2021-8-02";
//
//        $_flag = true;$months_count = 0;
//        while ($_flag) {
//            $current_data =  date("Y-m", strtotime("+$months_count months", strtotime($start_date)));
//
//            print_r($current_data.PHP_EOL);
//
//
//            $months_count ++;
//           if( $current_data == date("Y-m", strtotime($end_date)) ){
//               $_flag = false;
//           }
//        }

//        $school_traffic = new SchoolTrafficLog();
//        $ret_list =$school_traffic->getTrafficLog(1,'2020-01-01','2020-1230');
//        print_r($ret_list);


          $school_resource = new SchoolResource();
          //$school_resource ->addConnectionNum()；

          $School_conn_card = new SchoolConnectionsCard();
//        $num= $School_conn_card -> getNumInfoByDate(1,'2021-03-01');
//        print_r($num);
//        echo "========20==========".PHP_EOL;
//        $num= $School_conn_card -> useNumByDay(1,20,'2021-12-01');
//        var_dump($num);
//
//        echo "========200==========".PHP_EOL;
//        $num= $School_conn_card -> useNumByDay(1,310,'2021-03-01');
//        var_dump($num);
//
//        echo "========100==========".PHP_EOL;
//        $num= $School_conn_card -> useNumByDay(1,100,'2021-04-01');
//        var_dump($num);
//        echo "========500==========".PHP_EOL;
//
//        $num= $School_conn_card -> useNumByDay(1,200,'2021-05-01');
//        var_dump($num);
//        echo "==================".PHP_EOL;



//        $school_distribution = new SchoolConnectionsDistribution();
//        $ret = $school_distribution ->getDistribution(1);
//        print_r(json_encode($ret));





    }


}
