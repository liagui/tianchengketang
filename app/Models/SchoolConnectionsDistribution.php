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

class SchoolConnectionsDistribution extends Model
{
    //指定表名
    public $table = 'ld_school_connections_distribution';

    /**
     *  增加一些待分配并发数的有效期
     *  并发数的有效期 ？ 当用户购买了并发数的时候 有附带一个有效期，
     *  这里把有效期 写在月分配表中 这样一来 用户待分配并发界面就 更加清楚明了
     * @param $school_id
     * @param $start_date
     * @param $end_date
     */
    public function addDistributionDate(string $school_id, string $start_date, string $end_date)
    {
        // 增加一组并发连接数 从开始的月份到结束日期的月份

        $_flag = true;
        $months_count = 0;
        while ($_flag) {
            // 当前的月份
            $current_data = date("Y-m", strtotime("+$months_count months", strtotime($start_date)));

            $where = [ 'school_id' => $school_id, "assigned_month" => $current_data ];

            //如果没有查询到记录,则将这条数据添加到数据表中 默认分配数目0
            $data = $where + [ 'num' => 0 ];

            $this->newQuery()->firstOrCreate($where, $data);

            $months_count++;
            if ($current_data == date("Y-m", strtotime($end_date))) {
                $_flag = false;
            }
        }

    }

    /**
     *  获取一个网校的并发分配情况
     * @param string $school_id 网校id
     * @return array
     */
    public function getDistribution(string $school_id)
    {
        $query = $this->newBaseQueryBuilder();
        $list = $query->select([ 'assigned_month', 'num' ])->from($this->table)->where("school_id", "=", $school_id)->get();
        if (!$list) {
            return array();
        }
        // 按照 年 月份 进行处理数据
        $ret_list = array();
        $ret_list_year = array();
        foreach ($list as $item) {
            $month = $item->assigned_month;
            $num = $item->num;
            $year = date("Y", strtotime($month));
            $month = date("m", strtotime($month));
            $ret_list_year[ $year ][  ] = array(
                "month" => $month,
                "counts" => $num
            );
        }
        foreach ($ret_list_year as $key=>$item){
            $ret_list['connections'][] = array(
                "year" => $key,
                "list"=>$item
            );
        }

        return $ret_list;
    }

}


