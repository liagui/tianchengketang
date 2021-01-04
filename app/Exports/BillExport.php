<?php
namespace App\Exports;

use App\Http\Controllers\Admin\SchoolDataController;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class BillExport implements FromCollection, WithHeadings {

    protected $where;
    public function __construct($post){
        $this->where = $post;
    }
    public function collection() {

        $return = (new SchoolDataController())->getOrderlist($this->where)['data']['list'];
        return $return;
    }

    public function headings(): array{
        return [
            '分校名称',
            '姓名',
            '手机号',
            '购买价格',
            '课程价格',
            '时间',
            '课程名称',
            '所属学科'
        ];
    }
}
