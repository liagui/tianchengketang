<?php
namespace App\Exports;

use App\Models\Student;
use App\Models\StudentDoTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\StudentPapers;
class BankListExport implements FromCollection, WithHeadings {

    protected $data;
    public function __construct($invoices){
        $this->data = $invoices;
    }

    public function collection() {
        $data = $this->data;
        //获取学员做题信息
        $studentList = StudentPapers::getStudentBankList($data);
		//var_dump($studentList);die();
        return (object)$studentList['data'];
    }

    public function headings(): array
    {
        return [
            '做题时间',
            '题库',
            '科目',
            '试卷名称',
            '得分',
            '类型',
            '做题数',
            '正确率',
            '错题数',
        ];
    }
}
