<?php
namespace App\Exports;

use App\Models\Coures;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\Subject;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class FinanceExport implements FromCollection, WithHeadings {

    protected $where;
    public function __construct($invoices){
        $this->where = $invoices;
    }
    public function collection() {
        $data = $this->where;
        $total = Order::select('ld_school.name','ld_student.real_name','ld_student.phone','ld_order.price','ld_order.lession_price','ld_order.class_id','ld_order.nature','ld_order.create_at')
            ->leftJoin('ld_school','ld_school.id','=','ld_order.school_id')
            ->leftJoin('ld_student','ld_student.id','=','ld_order.student_id')
            ->where(function($query) use ($data) {
                if(isset($data['start_time'])&& !empty($data['start_time'])){
                    $query->where('ld_order.create_at','>',$data['start_time']);
                }
                if(isset($data['end_time']) && !empty($data['end_time'])){
                    $query->where('ld_order.create_at','<',$data['end_time']);
                }
                if(isset($data['school_id']) && !empty($data['school_id'])){
                    $query->where('ld_order.school_id','=',$data['school_id']);

                }
            })->whereIn('ld_order.status',[1,2])->groupBy('ld_order.id')
            ->get();
        foreach ($total as $k=>&$v){
            if($v['nature'] == 1){
                $lesson = CourseSchool::where(['id'=>$v['class_id']])->first();
            }else{
                $lesson = Coures::where(['id'=>$v['class_id']])->first();
            }
            $subject = Subject::where(['id'=>$lesson['parent_id']])->first();
            $v['class_name'] = $lesson['title'];
            $v['subject_name'] = $subject['subject_name'];
            unset($v['class_id']);
            unset($v['nature']);
        }
        return $total;
    }

    public function headings(): array{
        return [
            '分校名称',
            '姓名',
            '手机号',
            '课程价格',
            '购买价格',
            '时间',
            '课程名称',
            '所属学科'
        ];
    }
}
