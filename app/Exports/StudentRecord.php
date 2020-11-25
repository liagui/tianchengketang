<?php
namespace App\Exports;

use App\Models\Order;
use Exce;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\StudentPapers;
class StudentRecord implements FromCollection, WithHeadings {

    protected $data;
    public function __construct($invoices){
        $this->data = $invoices;
    }

    public function collection() {
        $data = $this->data;
        //获取学员做题信息..
        $studentList = Order::exportStudentStudyList($data);
		$cellData = $studentList['data'];
		Excel::create('导出表', function ($excel) use ($cellData, 'ceshi') {
            $excel->sheet('ceshi', function ($sheet) use ($data) {
                $sheet->fromModel($data)
                    ->freezeFirstRow(); #冻结第一行
            });
        })
            ->export('xlsx');

		//$res = (object)$studentList['data'];
        //var_dump($studentList);die();
        //return $studentList['data'];
    }

    /*public function headings(): array
    {
        return [
            '课程名称',
            '课次名称',
            '教学形式',
            '最后上课时间',
            '完成情况',
            '最长上课时间',
        ];
    }*/
}
