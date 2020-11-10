<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

use Log;

class SchoolResource extends Model
{
    //指定别的表名
    public $table = 'ld_school_resource';

    protected $fillable = [ 'school_id' ];

    // region 流量相关的函数

    /**
     *  更新 网校的流量情况
     *
     * @param string $school_id 网校的id
     * @param int $traffic_changed 更新了多少流量
     * @param string $day 更新日期
     * @param string $type 更新类型 use 使用陆良 add 增加流量
     * @param null|string $limit_data
     * @return false
     */
    function updateTrafficUsage(string $school_id, int $traffic_changed, string $day, $type = "use", $limit_data = null)
    {
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
            print_r($last_update_date);
            $last_update_date = date("Y-m-d", strtotime($last_update_date));
            if ($last_update_date == $day) {
                Log::error("school_id:" . $school_id . "流量已经更新");
                return false;
            }
        }

        DB::beginTransaction();
        try {
            // 2 更改流量使用情况
            if ($type == 'use') {

                // 流量使用 目前流量只有 cc在使用 使用类型（used_type）默认是视频 字段 traffic_used 和 traffic_used_video
                // 1 首先会会增加使用量 增加总的使用量
                $this->newQuery()->where("school_id", $school_id)->increment("traffic_used", $traffic_changed);
                // 2 增加视频（直播课）的使用总量
                $this->newQuery()->where("school_id", $school_id)->increment("traffic_used_video", $traffic_changed);
                // 这里 控制 更新时间  只对 使用 流量进行 更新
                $this->newQuery()->where("school_id", $school_id)->update([ 'log_date' => $day ]);
                // 3 增加一个 流量使用log
                $traffic_log->addLog($school_id, $traffic_changed, SchoolTrafficLog::USED_TYPE_VIDEO,
                    SchoolTrafficLog::TRAFFIC_USE, $school_info[ 'traffic_used' ], $day);

            } else if ($type == "add") {
                // 注意 空间增加 不更新 log_data 字段 自动更新 update_at 字段
                // 首先会会增加使用量 增加总的使用量 字段 space_total
                $this->newQuery()->where("space_total", $school_id)->increment("traffic_total", $traffic_changed);

                //增加一个 流量使用log 这里 的类型是 增加
                $traffic_log->addLog($school_id, $traffic_changed, '',
                    SchoolTrafficLog::TRAFFIC_ADD, $school_info[ 'traffic_total' ], $day);
            }
            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error("流量更新发生错误：" . $ex->getMessage());
            return false;
        }

    }


    public function updateSpaceUsage(string $school_id, int $space_changed, string $day, $type = "use",
                                     $use_type = "video", $limit_data = null)
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
                // 注意 空间增加 不更新 log_data 字段 自动更新 update_at 字段
                // 首先会会增加空间使用量 增加总的使用量 字段 space_total
                $this->newQuery()->where("space_total", $school_id)->increment("space_total", $space_changed);

                //增加一个 空间使用log 这里 的类型是 增加
                $space_log->addLog($school_id, $space_changed, '',
                    SchoolSpaceLog::SPACE_ADD, $school_info[ 'space_total' ], $day);
            }
            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error("网校空间更新发生错误：" . $ex->getMessage());
            return false;
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
                // 并发数价 不够的情况下
                DB::rollBack();
                return false;
            }
            // 扣除并发数
            $connection_card->useNumByDay($school_id, $connections_num, $date);

            $connection_log->addLog($school_id, $connections_num, SchoolConnectionsLog::CONN_CHANGE_USE, $date, $admin_id);

            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error("网校并发数更新发生错误：" . $ex->getMessage());
            return false;
        }


    }

    public function addConnectionNum(string $school_id, $start_data, $end_data, $connections_num)
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

        DB::beginTransaction();
        try {

            // 1 首先会会增加 网校的已经购买总数
            $this->newQuery()->where("school_id", $school_id)->increment("connections_total", $connections_num);

            // 增加一张 并发数的虚拟卡
            $connection_card->addCard($school_id, $connections_num, $start_data, $end_data);

            // 按照有效期 增加并发数 分布数据
            $connection_distribution->addDistributionDate($school_id, $start_data, $end_data);
            $connection_log->addLog($school_id, $connections_num, SchoolConnectionsLog::CONN_CHANGE_ADD, date("Y-m-d"));

            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error("网校并发数更新发生错误：" . $ex->getMessage());
            return false;
        }


    }

    function getSpaceTrafficDetail($school_id)
    {

        //  首先查询 网校的情况
        $school_info = $this->newQuery()->where("school_id", $school_id)->first();
        if (!$school_info) {
            return false;
        }

        $ret_info = array(
            "space_info"    => array(
                "space_total"  => conversionBytes($school_info->space_total),
                "space_used"   => conversionBytes($school_info->space_used),
                "expires_time" => date('Y-m-d', strtotime($school_info->space_expiry_date))
            ),
            "space_chart"   => array(
                "video"    => array(
                    "used"    => conversionBytes($school_info->space_used_video),
                    "percent" => round(intval($school_info->space_used_video) / intval($school_info->space_total) * 100, 2)
                ),
                "document" => array(
                    "used"    => conversionBytes($school_info->space_used_doc),
                    "percent" => round(($school_info->space_used_doc) / ($school_info->space_total) * 100, 2)
                ),
                "free"     => array(
                    "used"    => conversionBytes(intval($school_info->space_total) - intval($school_info->space_totalspace_used)),
                    "percent" => round(($school_info->space_used_doc - $school_info->space_used) / ($school_info->space_total) * 100, 2)
                ),
            ),
            "traffic_chart" => array(
                "video"    => array(
                    "used"    => conversionBytes($school_info->traffic_used_video),
                    "percent" => round(($school_info->traffic_used_video) / ($school_info->traffic_total) * 100, 2)
                ),
                "document" => array(
                    "used"    => conversionBytes($school_info->traffic_used_doc),
                    "percent" => round(($school_info->traffic_used_doc) / ($school_info->traffic_total) * 100, 2)
                ),
                "free"     => array(
                    "used"    => conversionBytes($school_info->traffic_used_doc),
                    "percent" => round(($school_info->traffic_used_doc + $school_info->traffic_used_video) / ($school_info->traffic_total) * 100, 2)
                )
            )
        );

    }

    // endregion

}


