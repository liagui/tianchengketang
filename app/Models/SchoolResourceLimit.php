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

class SchoolResourceLimit extends Model
{

    //指定别的表名
    public $table = 'ld_school_limit';

    /**
     *  添加 一条 某一个学校 流量 限制
     * @param $school_id
     * @param $traffic_limit
     * @return int
     */
    public function addOrUpdateTrafficLimit($school_id, $traffic_limit)
    {
        // 把界面的 gb换成 字节
        $limit = GBtoBytes($traffic_limit);

        $query = $this->newBaseQueryBuilder();
        $ret = $query->from($this->table)->updateOrInsert(
            [ "school_id" => $school_id ],
            [ "limit_traffic" => $limit ]);

        return $ret;

    }

    // 获取一个网校的流量提醒
    public function getTrafficLimit($school_id)
    {
        $query = $this->newBaseQueryBuilder();
        $ret = $query->select([ 'limit_traffic' ])
            ->from($this->table)
            ->where("school_id", "=", $school_id)
            ->first();
        return (empty($ret))?0:$ret->limit_traffic;
    }


}


