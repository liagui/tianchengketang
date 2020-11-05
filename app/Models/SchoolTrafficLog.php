<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

// use App\Models\Teacher;
// use App\Models\Admin;
// use App\Models\CouresSubject;
// use App\Models\Coures;
// use App\Models\Couresmethod;

use App\Tools\CurrentAdmin;

class SchoolTrafficLog extends Model
{
    //指定别的表名
    public $table = 'ld_school_traffic_log';

    // 'video','doc' 流量的使用类型 video 视频 点播课
    // doc 文档课件
    const USED_TYPE_DOC = "doc";
    const USED_TYPE_VIDEO = "video";

    // 'add','use'
    const TRAFFIC_USE = "use";
    const TRAFFIC_ADD = "add";


    // region 流量使用的相关函数

    /**
     *   添加一条 流量的变更记录
     * @param string $school_id 网校的 id
     * @param int $traffic_used 使用或者改变了多少流量
     * @param string $used_type 流量的使用类型 video ,doc
     * @param string $type 流量的改变类型 add 增加流量 use 使用流量
     * @param int $before_traffic 改变之前的流向
     * @param string $log_date
     * @return bool
     */
    public function addLog(string $school_id, int $traffic_used, string $used_type, string $type, int $before_traffic, string $log_date)
    {

        $data = array(
            "school_id"      => $school_id,
            "traffic_used"   => $traffic_used,
            "type"           => $type,
            "before_traffic" => $before_traffic,
            "log_date"       => $log_date
        );
        // 如果空间使用类型空 那么 有可能是 空间扩容
        !empty($used_type) ? $data[ "used_type" ] = $used_type : "";

        return $this->newModelQuery()->insert($data);

    }

    /**
     *  获取一个 网校的 流量日志
     *
     * @param string $school_id 分校的id
     * @param string|null $start_date 查询的日期
     * @param string|null $end_date 查询的结束日期
     * @return array
     */
    public function getTrafficLog(string $school_id, string $start_date = null, string $end_date = null)
    {
        $query = $this->newBaseQueryBuilder();

        $query->selectRaw("DATE_FORMAT(log_date, '%Y-%m') as date ")
            ->selectRaw("SUM(traffic_used) as count ")
            ->from($this->table)
            ->where("school_id", "=", $school_id)->where("type", "=", 'use')
            ->groupBy("date");
        // 如果 有日期限制 那么限制日期范围
        if (!empty($start_data) and !empty($end_data)) {
            $query->whereBetween("log_date", [ $start_date, $end_date ]);
        }

        $list = $query->get();
        $ret_list = array();
        // 遍历后 按照格式返回
        foreach ($list as $item) {
            $ret_list[] = array(
                "date"  => $item->date,
                'count' => $item->count
            );
        }

        return $ret_list;
    }


    // endregion

}


