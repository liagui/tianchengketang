<?php
namespace App\Exports;

use App\Models\Order;

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
        //$studentList = Order::getStudentStudyList($data);
		//$res = (object)$studentList['data'];
        //var_dump($res);die();
		$list =Order::where(['student_id'=>$data['student_id'],'status'=>2])
            ->whereIn('pay_status',[3,4])
            ->where(function ($query) use ($data) {
                if (isset($data['id']) && !empty($data['id'])) {
                    $query->where('class_id', $data['id']);
                }
            })
            ->select('id','pay_time','class_id','nature','class_id')
            ->orderByDesc('id')
            ->get();
        return $list;
    }

    public function headings(): array
    {
        return [
            '课程名称',
            '课次名称',
            '教学形式',
            '最后上课时间',
            '完成情况',
            '最长上课时间',
        ];
    }
}
