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
    public  function  addLog($school_id,$used_num,$change_type,$log_date,$admin_id=""){
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
        return $this->newModelQuery()->insert($data);
    }


    public function getConnectionsLog(string $school_id, string $start_date = null, string $end_date = null)
    {
        $query = $this->newBaseQueryBuilder();

        $query->selectRaw("DATE_FORMAT(log_date, '%Y-%m') as date ")
            ->selectRaw("SUM(used_num) as count ")
            ->from($this->table)
            ->where("school_id", "=", $school_id)->where("change_type", "=", 'use')
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




}


