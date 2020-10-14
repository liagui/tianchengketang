<?php
namespace App\Exports;

use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Models\Pay_order_inside;
use App\Models\Orderdocumentary;
class TeacherExport implements FromCollection, WithHeadings {
    protected $data;
    public function __construct($invoices){
        $this->data = $invoices;
    }
    public function collection(){
        $data = $this->data;
        $teacher = Teacher::select("real_name","mobile")->where('role_id',3)->get();
        foreach($teacher as $k =>$v){
            //已回访单数
            $teacher[$k]['yet_singular'] = Pay_order_inside::where(['return_visit'=>1,'have_user_id'=>$v['id']])->where(function($query) use ($data){
                if(isset($data['start_time']) && !empty(isset($data['start_time']))  && isset($data['end_time']) && !empty(isset($data['end_time']))){
                    $query->whereBetween('comfirm_time',[date("Y-m-d H:i:s",strtotime($data['start_time'])),date("Y-m-d H:i:s",strtotime($data['end_time']))]);
                }
            })->count();
            if($teacher[$k]['yet_singular'] == 0){
                $teacher[$k]['yet_singular'] = "0";
            }
            //未回放单数
            $teacher[$k]['not_singular'] = Pay_order_inside::where(['return_visit'=>0,'have_user_id'=>$v['id']])->where(function($query) use ($data){
                if(isset($data['start_time']) && !empty(isset($data['start_time']))  && isset($data['end_time']) && !empty(isset($data['end_time']))){
                    $query->whereBetween('comfirm_time',[date("Y-m-d H:i:s",strtotime($data['start_time'])),date("Y-m-d H:i:s",strtotime($data['end_time']))]);
                }
            })->count();
            if($teacher[$k]['not_singular'] == 0){
                $teacher[$k]['not_singular'] = "0";
            }
            //总回放单数
            $teacher[$k]['sum_singular'] = $teacher[$k]['yet_singular'] + $teacher[$k]['not_singular'];
            if($teacher[$k]['sum_singular'] == 0){
                $teacher[$k]['sum_singular'] = "0";
            }
            //已完成业绩
            $teacher[$k]['completed_performance'] = Pay_order_inside::select("course_Price")->where(['have_user_id'=>$v['id']])
            ->where(function($query) use ($data){
                if(isset($data['start_time']) && !empty(isset($data['start_time']))  && isset($data['end_time']) && !empty(isset($data['end_time']))){
                    $query->whereBetween('comfirm_time',[date("Y-m-d H:i:s",strtotime($data['start_time'])),date("Y-m-d H:i:s",strtotime($data['end_time']))]);
                }
            })->sum("course_Price");
            if($teacher[$k]['completed_performance'] == 0){
                $teacher[$k]['completed_performance'] = "0";
            }

            //退费业绩
            $teacher[$k]['return_premium'] = DB::table("refund_order")->where(["teacher_id"=>$v['id'],"refund_plan"=>2,"confirm_status"=>1])->where(function($query) use ($data){
                if(isset($data['start_time']) && !empty(isset($data['start_time']))  && isset($data['end_time']) && !empty(isset($data['end_time']))){
                    $query->whereBetween('refund_time',[date("Y-m-d H:i:s",strtotime($data['start_time'])),date("Y-m-d H:i:s",strtotime($data['end_time']))]);
                }
            })->sum("refund_Price");
            if($teacher[$k]['return_premium'] == 0){
                $teacher[$k]['return_premium'] = "0";
            }
        }
        return $teacher;
    }

    public function headings(): array
    {
        return [
            '班主任姓名',
            '手机号',
            '已回访单数',
            '未回放单数',
            '总回放单数',
            '已完成业绩',
            '退费业绩',
        ];
    }
}
