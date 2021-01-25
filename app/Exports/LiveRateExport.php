<?php

namespace App\Exports;

use App\Models\AdminLog;
use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LiveRateExport implements FromArray, WithHeadings
{

    protected $where;

    public function __construct($post)
    {
        $this->where = $post;
    }



    public function headings(): array
    {
        return [
            "学科",
            "学科小类",
            "课程",
            "单元",
            "课次",
            "课程时间",
            "到课率",
            "完成率"
        ];
    }

    public function array(): array
    {
        if (!isset($this->where[ 'school_id' ])) {
            //当前学校id
            $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        } else {
            $school_id = $this->where[ 'school_id' ];
        }

        // $return = (new SchoolDataController())->getOrderlist($this->where);
        $return = Order::getStudentLiveStatistics($this->where);
        list($unit_list, $class_list, $ret_class_list_count, $res) = Order::queryLiveRate($this->where, $school_id, PHP_INT_MAX, 0);
        return $res;
    }
}
