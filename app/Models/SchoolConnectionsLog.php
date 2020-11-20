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
class SchoolConnectionsLog extends Model {

    //指定别的表名
    public $table = 'ld_school_connections_log';

    // 'add','use' 空间变化情况
    const CONN_CHANGE_USE = "use";
    const CONN_CHANGE_ADD = "add";
    const CONN_CHANGE_LOG = "log";
    public  function  addLog($school_id,$used_num,$change_type,$log_date,$admin_id="",$before_num=0){
        $data = array(
            "school_id" => $school_id,
            "used_num" => $used_num,
            "change_type" => $change_type,
            "log_date" => $log_date
        );
        // 如果有admin_id 那么更新本次操作的id
        if(!empty($admin_id)){
            $data['admin_id'] = $admin_id;
        }
        if(!empty($before_num)){
            $data['befor_num'] = $before_num;
        }

        return $this->newModelQuery()->insert($data);
    }


    /**
     *  获取每个月的分配日志
     * @param string $school_id
     * @param string|null $log_date
     * @return array
     */
    public function getConnectionsLogByDate(string $school_id, string $log_date = null){

        $query = $this->newBaseQueryBuilder();

        $query->from($this->table) ->leftJoin("ld_admin",function($join){
            $join->on( $this->table.'.admin_id', '=', 'ld_admin.id');
        })
            ->selectRaw("ld_admin.username,log_date,used_num,( befor_num + used_num) as after_num ")
            ->where($this->table.".school_id", "=", $school_id)
            ->where($this->table.".change_type", "=", SchoolConnectionsLog::CONN_CHANGE_USE);

        // 如果 有日期限制 那么限制日期范围
        if (!empty($log_date)) {

            $start_date =date('Y-m-01',strtotime($log_date));
            $end_date =date('Y-m-t',strtotime($log_date));
            $query->whereBetween("log_date",  array( $start_date,$end_date));
        }

        $list = $query->get();

        $ret_list = array();
        // 遍历后 按照格式返回
        foreach ($list as $item) {
            $ret_list[] = array(
                "username"  => $item->username,
                'log_date' => date("Y-m-d H:i:s",strtotime($item->log_date)),
                'num' => ( !is_null($item->used_num) )? intval($item->used_num):0,
                'after_num' => ( !is_null($item->after_num) )? intval($item->after_num):0
            );
        }

        return $ret_list;
    }

    public function getConnectionsLog(string $school_id, string $start_date = null, string $end_date = null)
    {
        $query = $this->newBaseQueryBuilder();

        // 获取使用日志中分配类型是 log 表示 统计的连接使用日志
        $query->selectRaw("DATE_FORMAT(log_date, '%Y-%m-%d') as date ")
            ->selectRaw("SUM(used_num) as count ")
            ->from($this->table)
            ->where("school_id", "=", $school_id)
            ->where("change_type", "=", SchoolConnectionsLog::CONN_CHANGE_LOG)
            ->whereRaw("`admin_id` IS NULL")
            ->groupBy("date");

        // 如果 有日期限制 那么限制日期范围
        if (!empty($start_date) and !empty($end_date)) {
            $query->whereBetween("log_date", [ $start_date, $end_date ]);
        }
        $list = $query->get()->toArray();




        $ret_list = array();
        $_flag = true;
        $day_count = 0;
        while ($_flag) {

            // 计算到那个月份了
            $_timespan = strtotime("+$day_count day", strtotime($start_date));
            $_data = date("Y-m-d", $_timespan );


            $ret_list["xAxi"][] =$_data;
            $ret_list["yAxi"][] =0;


            $day_count++;
            if ($_data == date("Y-m-d", strtotime($end_date))) {
                $_flag = false;
            }
        }





//        // 遍历后 按照格式返回
//        foreach ($list as $item) {
//
//            $ret_list["xAxi"][] =$item->date;
//            $ret_list["yAxi"][] =$item->count;
//
//        }

        return $ret_list;
    }




}


