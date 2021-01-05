<?php
namespace App\Exports;

use App\Http\Controllers\Admin\StatisticsController;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class StudentExport implements FromCollection, WithHeadings {

    protected $where;
    public function __construct($post){
        $this->where = $post;
    }
    public function collection() {
        $return = (new StatisticsController())->StudentList($this->where);
        return $return;
    }

    public function headings(): array{
        return [
            '所属网校',
            '手机号',
            '姓名',
            '注册时间',
            '用户类型',
            '来源',
        ];
    }
}
