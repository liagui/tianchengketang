<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Redis;
use Log;

class SchoolResource extends Model
{
    //指定别的表名
    public $table = 'ld_school_resource';

    protected $fillable = [ 'school_id','log_date' ];

    // region 流量相关的函数

    /**
     *  更新 网校的流量情况
     *
     * @param string $school_id 网校的id
     * @param int $traffic_changed 更新了多少流量
     * @param string $day 更新日期
     * @param string $type 更新类型 use 使用陆良 add 增加流量
     * @param bool $useTransaction
     * @return false
     */
    function   updateTrafficUsage(string $school_id, int $traffic_changed, string $day, $type = "use", bool $useTransaction=true)
    {
        Log::info("开始更新流量使用情况".print_r(func_get_args(),true) );
        // 流量日志
        $traffic_log = new SchoolTrafficLog();

        // 1 首先查询 网校的情况
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        if (!$school_info) {
            // 如果没有记录那么新添加一条
            $school_info = $this->newModelQuery()->firstOrCreate(array( "school_id" => $school_id ))->save();
            $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        } else if ($type == "use") {
            //如果记录存在 并且 传递 过来的 是 use 那么如果今天更新了 不在更新 流量数据
            $last_update_date = $school_info[ 'log_date' ];

            //print_r($last_update_date);
            $last_update_date = date("Y-m-d", strtotime($last_update_date));
            if ($last_update_date == $day) {
                //print_r("school_id:" . $school_id . "流量已经更新".PHP_EOL);
                Log::error("school_id:" . $school_id . "流量已经更新");
                return false;
            }
        }

        // 设定处理过程 外部调用的时候不适用 事务
        $processTraffic  = function () use($type, $school_id, $traffic_changed, $day, $traffic_log, $school_info){
            // 2 更改流量使用情况
            if ($type == 'use') {

                // 流量使用 目前流量只有 cc在使用 使用类型（used_type）默认是视频 字段 traffic_used 和 traffic_used_video
                // 1 首先会会增加使用量 增加总的使用量
                $this->newQuery()->where("school_id", $school_id)->increment("traffic_used", $traffic_changed);
                // 2 增加视频（直播课）的使用总量
                $this->newQuery()->where("school_id", $school_id)->increment("traffic_used_video", $traffic_changed);

                if(!empty($day)){
                    // 这里 控制 更新时间  只对 使用 流量进行 更新
                    $this->newQuery()->where("school_id", $school_id)->update([ 'log_date' => $day ]);
                }else{
                    $day=date("Y-m-d H:i:s");
                }

                // 3 增加一个 流量使用log
                $traffic_log->addLog($school_id, $traffic_changed, SchoolTrafficLog::USED_TYPE_VIDEO,
                    SchoolTrafficLog::TRAFFIC_USE, $school_info[ 'traffic_used' ], $day);

            } else if ($type == "add") {
                 // 注意 这里增加流量的代码 前端传递来的单位是GB 这里需要转换一下
                $traffic_changed = GBtoBytes($traffic_changed);
                // 注意 空间增加 不更新 log_data 字段 自动更新 update_at 字段
                // 首先会会增加使用量 增加总的使用量 字段 space_total
                $this->newQuery()->where("school_id", $school_id)->increment("traffic_total", $traffic_changed);

                //增加一个 流量使用log 这里 的类型是 增加
                $traffic_log->addLog($school_id, $traffic_changed, '',
                    SchoolTrafficLog::TRAFFIC_ADD, $school_info[ 'traffic_total' ], $day);
            }
        };

        //  根据传递的结果 决定是否使用事务
        if($useTransaction){
            // 使用 事务
            UseDBTransaction(function() use ($processTraffic,$type, $school_id, $traffic_changed, $day, $traffic_log, $school_info){
                $processTraffic($type, $school_id, $traffic_changed, $day, $traffic_log, $school_info);

            },function ( \Exception $ex){
                Log::error("流量更新发生错误：" . $ex->getMessage());
            });
        }else{
            // 不使用 事务
            $processTraffic($school_id, $traffic_changed, $day, $traffic_log, $school_info);

        }

    }


    /**
     *  更新 空间 的 哟小气
     * @param string $school_id
     * @param string $expiry_date
     * @return bool
     */
    public function updateSpaceExpiry(string $school_id, string $expiry_date)
    {
        // 更新 网校的 空间 使用
        $space_log = new SchoolSpaceLog();

        // 1 首先查询 网校的情况
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();

        if (!$school_info) {
            // 如果没有网校记录 那么新添加一条
            $school_info = $this->newModelQuery()->firstOrCreate(array( "school_id" => $school_id ))->save();
            $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        }

        DB::beginTransaction();
        try {

            // 1 首先会会增加 网校的已使用的总数
            $this->newQuery()->where("school_id", $school_id)->update(['space_expiry_date' =>$expiry_date]);

            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error("更新网校空间过期时间发生错误：" . $ex->getMessage());
            return false;
        }

    }

    /**
     *  修改 用户 空间 并且 添加 空间 日志
     *
     * @param string $school_id
     * @param int $space_changed
     * @param string $day
     * @param string $type
     * @param string $use_type
     * @param bool $useTransaction
     * @return bool
     */
    public function updateSpaceUsage(string $school_id, int $space_changed, string $day, $type = "use",
                                     $use_type = "video",  bool $useTransaction=true)
    {
        // 更新 网校的 空间 使用
        $space_log = new SchoolSpaceLog();

        // 1 首先查询 网校的情况
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();

        if (!$school_info) {
            // 如果没有网校记录 那么新添加一条
            $school_info = $this->newModelQuery()->firstOrCreate(array( "school_id" => $school_id ))->save();
            $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        }

        $processSpace = function () use ($type, $school_id, $space_changed, $use_type, $space_log, $school_info, $day) {
            // 2 更改流量使用情况
            if ($type == 'use') {

                // 1 首先会会增加空间使用量 增加总的空间使用量
                $this->newQuery()->where("school_id", $school_id)->increment("space_used", $space_changed);

                if ($use_type == SchoolSpaceLog::USE_TYPE_VIDEO) {
                    // 2 增加视频（直播课）的使用总量 这里增加的是 视频空间使用量
                    $this->newQuery()->where("school_id", $school_id)->increment("space_used_video", $space_changed);

                    // 3 增加一个空间使用log
                    $space_log->addLog($school_id, $space_changed, SchoolSpaceLog::USE_TYPE_VIDEO,
                        SchoolSpaceLog::SPACE_USE, $school_info[ 'traffic_used' ], $day);
                } else if ($use_type == SchoolSpaceLog::USE_TYPE_DOC) {

                    // 2 增加视频（直播课）的使用总量 这里增加的是 文档空间的使用量
                    $this->newQuery()->where("school_id", $school_id)->increment("space_used_doc", $space_changed);

                    // 3 增加一个空间使用log
                    $space_log->addLog($school_id, $space_changed, SchoolSpaceLog::USE_TYPE_DOC,
                        SchoolSpaceLog::SPACE_USE, $school_info[ 'traffic_used' ], $day);
                }

            } else if ($type == "add") {

                // 注意 这里增加空间的代码 前端传递来的单位是GB 这里需要转换一下
                $space_changed = GBtoBytes($space_changed);

                // 注意 空间增加 不更新 log_data 字段 自动更新 update_at 字段
                // 首先会会增加空间使用量 增加总的使用量 字段 space_total
                $this->newQuery()->where("school_id", $school_id)->increment("space_total", $space_changed);

                //增加一个 空间使用log 这里 的类型是 增加
                $space_log->addLog($school_id, $space_changed, '',
                    SchoolSpaceLog::SPACE_ADD, $school_info[ 'space_total' ], $day);
            }


        };

        //  根据传递的结果 决定是否使用事务
        if($useTransaction){
            // 使用 事务
            UseDBTransaction( function () use($processSpace,$type, $school_id, $space_changed, $use_type, $space_log, $school_info, $day){
                $processSpace($type, $school_id, $space_changed, $use_type, $space_log, $school_info, $day);
            } ,function ( \Exception $ex){
                Log::error("空间更新发生错误：" . LogDBExceiption($ex));
            });
        }else{
            // 不使用 事务
            $processSpace($type, $school_id, $space_changed, $use_type, $space_log, $school_info, $day);

        }

    }


    /**
     *  设定 某一个月的并发数
     * @param $school_id
     * @param $connections_num
     * @param $date
     * @param $admin_id
     * @return bool
     */
    public function setConnectionNumByDate($school_id, $connections_num, $date, $admin_id)
    {
        // 购买的并发数 并发数 是有有效期的
        // 1 首先跟新school_resource  connections_total

        // 更新 并发日志
        $connection_log = new SchoolConnectionsLog();

        // 并发的月度分布系统
        $connection_distribution = new SchoolConnectionsDistribution();
        // 并发 点卡系统
        $connection_card = new SchoolConnectionsCard();

        // 1 首先查询 网校的情况
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        if (!$school_info) {
            // 如果没有网校记录 那么新添加一条
            $school_info = $this->newModelQuery()->firstOrCreate(array( "school_id" => $school_id ))->save();
            $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        }

        DB::beginTransaction();
        try {

            // 1 首先会会增加 网校的已使用的总数
            $this->newQuery()->where("school_id", $school_id)->increment("connections_used", $connections_num);

            $will_use_num = $connection_card->getNumByDate($school_id, $date);

            if ($will_use_num < $connections_num) {
                // 并发数 不够的情况下
                DB::rollBack();
                return false;
            }
            // 扣除并发数
            $connection_card->useNumByDay($school_id, $connections_num, $date);

            $num = $connection_distribution->getDistributionByDate($school_id,$date);
            // 设定这个月实用的并发数
            $connection_distribution->setDistributionByDate($school_id,$date,$connections_num);

            $connection_log->addLog($school_id, $connections_num, SchoolConnectionsLog::CONN_CHANGE_USE,
                date("Y-m-d H:i:s"),$date, $admin_id,$num);
            //  设定 redis 中的 num 数目
            $total_num = intval($connections_num) +  intval($num);
            $key = $school_id."_"."num_".date("Y_m");
            // 暂时不设定 过期的时间 后期设定过期时间为 1 个月
            Redis::set($key,$total_num);


            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error("网校并发数更新发生错误：" . $ex->getMessage());
            return false;
        }


    }

    public function addConnectionNum(string $school_id, $start_date, $end_date, $connections_num, bool $useTransaction=true)
    {

        // 购买的并发数 并发数 是有有效期的 并且 购买的开始时间不能低于当前时间

        // 1 首先跟新school_resource  connections_total

        // 更新 并发日志
        $connection_log = new SchoolConnectionsLog();

        // 并发的月度分布系统
        $connection_distribution = new SchoolConnectionsDistribution();
        // 并发 点卡系统
        $connection_card = new SchoolConnectionsCard();


        // 1 首先查询 网校的情况
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();

        if (!$school_info) {
            // 如果没有网校记录 那么新添加一条
            $school_info = $this->newModelQuery()->firstOrCreate(array( "school_id" => $school_id ))->save();
            $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        }

        $processConnections = function ()use ($school_id, $connections_num, $connection_card, $start_date, $end_date, $connection_distribution, $connection_log){
            // 1 首先会会增加 网校的已经购买总数
            $this->newQuery()->where("school_id", $school_id)->increment("connections_total", $connections_num);

            // 增加一张 并发数的虚拟卡
            $connection_card->addCard($school_id, $connections_num, $start_date, $end_date);

            // 按照有效期 增加并发数 分布数据
            $connection_distribution->addDistributionDate($school_id, $start_date, $end_date);
            $connection_log->addLog($school_id, $connections_num, SchoolConnectionsLog::CONN_CHANGE_ADD,
                date("Y-m-d"),date("Y-m-d"));
        };

        //  根据传递的结果 决定是否使用事务
        if($useTransaction){
            // 使用 事务
            UseDBTransaction(function () use($processConnections,$school_id, $connections_num, $connection_card,
                $start_date, $end_date, $connection_distribution, $connection_log) {
                $processConnections($school_id, $connections_num, $connection_card, $start_date, $end_date,
                    $connection_distribution, $connection_log);
            },function ( \Exception $ex){
                Log::error("并发连接更新发生错误：" . LogDBExceiption($ex));
            });
        }else{
            // 不使用 事务
            $processConnections($school_id, $connections_num, $connection_card, $start_date, $end_date,
                $connection_distribution, $connection_log);;

        }


    }

    function getSpaceTrafficDetail($school_id)
    {

        //  首先查询 网校的情况
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        if (!$school_info) {
            $school_info = $this->newModelQuery()->firstOrCreate(array( "school_id" => $school_id ))->save();
            $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        }

        $limit = new SchoolResourceLimit();
        $traffic_limit = $limit ->getTrafficLimit($school_id);

        $ret_info = array(
            "space_info"    => array(
                "space_total"  => conversionBytes($school_info->space_total),
                "space_used"   => conversionBytes($school_info->space_used),
                "expires_time" => date('Y-m-d', strtotime($school_info->space_expiry_date))
            ),
            "traffic_info"    => array(
                "traffic_total"  => conversionBytes(!is_null($school_info)?$school_info->traffic_total:0)."G",
                "traffic_used"   => conversionBytes(!is_null($school_info)?$school_info->traffic_used:0)."G",
                'traffic_limit' => intval(conversionBytes(!is_null($traffic_limit)?$traffic_limit:0)),

            ),
            "space_chart"   => array(
               // ['value'=>conversionBytes(intval($school_info->space_total) - intval($school_info->space_totalspace_used)), "name" => "剩余空间" ],
                ['value'=>conversionBytes( intval($school_info->space_totalspace_used)), "name" => "剩余空间" ],
                ['value'=>conversionBytes($school_info->space_used_video), "name" => "录播视频" ],
                ['value'=>conversionBytes($school_info->space_used_doc), "name" => "文档资源" ],
            ),
            "traffic_chart" => array(
                ['value'=>conversionBytes(intval($school_info->traffic_used) ), "name" => "剩余流量" ],
                ['value'=>conversionBytes($school_info->traffic_used_video), "name" => "录播视频" ],
                ['value'=>conversionBytes($school_info->traffic_used_doc), "name" => "文档资源" ],
            )
        );

        return $ret_info;

    }


    public function  getInfoBySchoolID($school_id){
        // 首先查询 网校的情况
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();

        if (!$school_info) {
            // 如果没有网校记录 那么新添加一条
            $school_info = $this->newModelQuery()->firstOrCreate(array( "school_id" => $school_id ))->save();
            $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        }

        return $school_info;

    }


    /**
     *   检查网校的剩余空间
     * @param $school_id
     * @param int $space_size
     * @return false
     */
    public function checkSpaceCanBeUpload($school_id, int $space_size)
    {
        // 如果是总校那么永远有流量
        if($school_id == 1){
              return true;
        }
        // 获取网校的数据
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        if (!$school_info) {
            // 如果没有网校记录 那么新添加一条
            $school_info = $this->newModelQuery()->firstOrCreate(array( "school_id" => $school_id ))->save();
            $school_info = $this->newQuery()->where("school_id", $school_id)->first();
            // 默认数据没有 剩余空间
            return false;
        }else{
            // 检查 剩余的空间
            $space_free_size = $school_info->space_total - intval($school_info->space_used);
            if($space_free_size <= 0) {
                // 如果没有剩余空间
                return false;
            }else if(($space_free_size - intval($space_size)) <0 ){
                // 剩余空间不够扣除的
                return  false;
            }else{
                // 检查 剩余空间 大于 0
                return  true;
            }
        }
    }


    /**
     *   检查网校的剩余空间
     * @param $school_id
     * @param int $space_size
     * @return false
     */
    public function checkTrafficCanBeUse($school_id)
    {
        // 如果是总校那么永远有流量
        if($school_id == 1){
            return true;
        }
        // 获取网校的数据
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        if (!$school_info) {
            // 如果没有网校记录 那么新添加一条
            $school_info = $this->newModelQuery()->firstOrCreate(array( "school_id" => $school_id ))->save();
            $school_info = $this->newQuery()->where("school_id", $school_id)->first();
            // 默认数据没有 剩余空间
            return false;
        }else{
            // 检查 剩余的空间
            $space_free_size = $school_info->traffic_total - intval($school_info->traffic_used);
            if($space_free_size <= 0) {
                // 如果没有剩余空间
                return false;
            }else{
                // 检查 剩余空间 大于 0
                return  true;
            }
        }
    }



    public function getResourceInfo($school_id): array
    {

        //$school_resource = new SchoolResource();
        $school_card = new SchoolConnectionsCard();
        $resource = $this->getInfoBySchoolID($school_id);
        // 当月可用的并发数
        $month_num = $school_card->getNumByDate($school_id, date("Y-m-d"));
        $next_month_num = $school_card->getNumByDate($school_id, date("Y-m-d",strtotime("+1 month")));
        // 当月 已经 分配的 并发数
        $school_conn_dis = new SchoolConnectionsDistribution();
        $month_num_used = $school_conn_dis->getDistributionByDate($school_id, date("Y-m-d"));

//2直播并发
        $key = $school_id."_"."num_".date("Y_m");
        // 暂时不设定 过期的时间 后期设定过期时间为 1 个月
        $redis_shool_num = Redis::get($key);

        //$data['live'] = $this->getLiveData($v['id'],isset($listArrs[1])?$listArrs[1]:[]);
        $data['live'] =  [
            'num'=> !is_null($resource)? $resource->connections_total:0,
            'month_num'=>$month_num,
            'month_usednum'=>intval($month_num_used),
            'num_used' => !is_null($resource)? $resource->connections_used:0,
            'redis_num' => $redis_shool_num,
            'redis_num_date' => $key,
            'next_month_num' =>$next_month_num
            //'end_time'=>substr($end_time,0,10), // 并发数没有截止日期的说
        ];
        // 就算剩余的 可用并发数据总的
        $data['live']['free_num'] = intval($data['live']['num']) - intval($data['live']['num_used']);


        //3空间
        //$data['storage'] = $this->getStorageData($v['id'],isset($listArrs[2])?$listArrs[2]:[]);
        $data['storage'] = [
            'total'=> conversionBytes(!is_null($resource)? $resource->space_total:0),
            'used'=> conversionBytes(!is_null($resource)? $resource->space_used:0),
            'end_time'=>!is_null($resource->space_expiry_date)?date("Y-m-d",strtotime(!is_null($resource)?$resource->space_expiry_date:0)):date("Y-m-d")
        ];

        //4流量
        //$data['flow'] = $this->getFlowData($v['id'],isset($listArrs[3])?$listArrs[3]:[]);
        $data['flow']['total'] = conversionBytes(!is_null($resource)?$resource->traffic_total:0);
        $data['flow']['used'] = conversionBytes(!is_null($resource)?$resource->traffic_used:0);
        $data['flow']['end_time'] = !is_null($resource->space_expiry_date)?date("Y-m-d",strtotime($resource->space_expiry_date)):date("Y-m-d");


        //5库存
        $data['stocks'] = $this->getCourseSchoolDetail($school_id);

        return $data;
    }

    /**
     * 获取网校的授权课程 与 库存量
     * 用于网校服务页面的首页展示
     */
    public function getCourseSchoolDetail($id)
    {
        //授权课程数量
        $arr['total'] = CourseSchool::where(['to_school_id'=>$id,'is_del'=>0])->count();
        //库存总数
        $arr['stocks'] = DB::table('ld_course_school as course')//授权课程记录表关联库存记录表
        ->join('ld_course_stocks as stocks','course.course_id','=','stocks.course_id')
            ->where('course.to_school_id',$id)//学校
            ->where('stocks.school_id',$id)//学校
            ->where('course.is_del',0)//未删除
            ->where('stocks.is_del',0)//库用库存
            ->sum('stocks.add_number');
        return $arr;
    }

    // endregion


}
