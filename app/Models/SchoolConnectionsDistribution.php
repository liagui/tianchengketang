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
    protected $fillable = [ 'school_id' ,"assigned_month","num"];

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
            $current_data = date("Y-m-t", strtotime("+$months_count months", strtotime($start_date)));

            //如果没有查询到记录,则将这条数据添加到数据表中 默认分配数目0
            $this->newQuery()->firstOrCreate(
                ["school_id"      => $school_id,
                "assigned_month" => $current_data,
                "num"            => 0]
            );


            $months_count++;
            if ($current_data == date("Y-m-t", strtotime($end_date))) {
                $_flag = false;
            }
        }

    }


    public  function  getDistributionByDate($school_id,$date){
        $date = date("Y-m-t",strtotime($date));

        $query = $this->newBaseQueryBuilder();
        $list = $query->select([ 'assigned_month', 'num' ])
            ->from($this->table)
            ->where("school_id", "=", $school_id)
            ->where("assigned_month", "=", $date)
            ->orderBy("assigned_month")
            ->first();
        if($list){
            return $list->num;
        }

        //  默认 返回 0
        return  0;
    }

    /**
     *  获取一个网校的并发分配情况
     * @param string $school_id 网校id
     * @return array
     */
    public function getDistribution(string $school_id)
    {
        $query = $this->newBaseQueryBuilder();
        $list = $query->select([ 'assigned_month', 'num' ])
            ->from($this->table)
            ->where("school_id", "=", $school_id)
            ->orderBy("assigned_month")
            ->get();
        if (!$list) {
            return array();
        }
        // 预先 处理一下 这里 需要 返回 两年的 数据 本年和下一年
        $ret_list_year = array();

        $start_date= date("Y-01-01", strtotime("now"));
        $end_date=date("Y-12-t", strtotime("+1 year", strtotime($start_date)));
        $_now_timespan = time();

        $_flag = true;
        $months_count = 0;
        while ($_flag) {
            // 当前的月份
            $_timespan = strtotime("+$months_count months", strtotime($start_date));
            $_data = date("Y-m-t", $_timespan );
            $_year = date("Y",strtotime($_data));
            $_months = date("m",strtotime($_data));


            //  如果 是未来的日期
            if($_timespan > $_now_timespan){
                // 默认 的 数据格式
                $ret_list_year[$_year][$_months]= array(
                    "month" => $_months,
                    "counts" => 0,
                    "assignment_status" => false, // 默认 -1
                    "assignment_enable" => true  //  可以编辑
                );
            }else{
                // 过去的状态
                $ret_list_year[$_year][$_months]= array(
                    "month" => $_months,
                    "counts" => 0,
                    "assignment_status" => false, // 默认 -1
                    "assignment_enable" => false  // 默认不可编辑
                );
            }


            $months_count++;
            if ($_data == date("Y-m-t", strtotime($end_date))) {
                $_flag = false;
            }
        }

        // 按照 年 月份 进行处理数据从 数据库中 查到的数据
        $ret_list = array();
        foreach ($list as $item) {
            $month = $item->assigned_month;
            $num = $item->num;
            $year = date("Y", strtotime($month));
            $month = date("m", strtotime($month));
            $ret_list_year[ $year ][ $month ]["month"] = $month;
            $ret_list_year[ $year ][ $month ]["counts"] = $num;
            $ret_list_year[ $year ][ $month ]["assignment_status"] = true;
            //echo "[ $year ][ $month ][counts]:$num ".PHP_EOL;
        }
        foreach ($ret_list_year as $key=>$item){
            $ret_list['connections'][] = array(
                "year" => $key,
                "list"=>array_values($item)
            );
        }

        return $ret_list;
    }

}


