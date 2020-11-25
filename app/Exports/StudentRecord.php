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
		Array_unshift($cellData,['申请人','申请产品名称','申请理由','联系人','手机号','邮编','邮箱','单位名称','联系地址']);
Excel::create('记录表单',function($excel) use ($cellData){
$excel->sheet('score', function($sheet) use ($cellData){
　　    $sheet->rows($cellData);
    });
})->export('xls'); 

		//$res = (object)$studentList['data'];
        //var_dump($studentList);die();
        return $studentList['data'];
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
