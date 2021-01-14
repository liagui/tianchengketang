<?php
namespace App\Exports;

use App\Models\AdminLog;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class StudentExport implements FromCollection, WithHeadings {
//学员统计导出
    protected $data;
    public function __construct($post){
        $this->data = $post;
    }
    public function collection(){
        //导出数据
        $data = $this->data;
        if(!empty($data['time'])){
            if($data['time'] == 1){
                $stime = date('Y-m-d');
                $etime = date('Y-m-d');
            }
            if($data['time'] == 2){
                $stime = date("Y-m-d",strtotime("-1 day"));
                $etime = date("Y-m-d",strtotime("-1 day"));
            }
            if($data['time'] == 3){
                $stime = date("Y-m-d",strtotime("-7 day"));
                $etime = date('Y-m-d');
            }
            if($data['time'] == 4){
                $statetimestamp = mktime(0, 0, 0, date('m'), 1, date('Y'));
                $stime =date('Y-m-d', $statetimestamp);
                $endtimestamp = mktime(23, 59, 59, date('m'), date('t'), date('Y'));
                $etime = date('Y-m-d', $endtimestamp);
            }
            if($data['time'] == 5){
                $stime = date("Y-m-d", strtotime("-3 month"));
                $etime = date('Y-m-d');
            }
        }else{
            if(!empty($data['timeRange'])){
                $datetime = json_decode($data['timeRange'],true);
                $statr = $datetime[0] * 0.001;
                $ends = $datetime[1] * 0.001;
                $stime = date("Y-m-d",$statr);
                $etime = date("Y-m-d",$ends);
            }else{
                $stime = date('Y-m-d');
                $etime = date('Y-m-d');
            }
        }
        $statetime = $stime . " 00:00:00";
        $endtime = $etime . " 23:59:59";

        //总条数
        $count = Student::leftJoin('ld_school','ld_school.id','=','ld_student.school_id')
            ->where(['ld_student.is_forbid'=>1,'ld_school.is_del'=>1,'ld_school.is_forbid'=>1])
            ->where(function($query) use ($data) {
                //分校
                if(!empty($data['school_id'])&&$data['school_id'] != ''){
                    $query->where('ld_student.school_id',$data['school_id']);
                }
                //来源
                if(isset($data['source'])){
                    $query->where('ld_student.reg_source',$data['source']);
                }
                //用户类型
                if(isset($data['enroll_status'])){
                    $query->where('ld_student.enroll_status',$data['enroll_status']);
                }
                //用户姓名
                if(!empty($data['real_name'])&&$data['real_name'] != ''){
                    $query->where('ld_student.real_name','like','%'.$data['real_name'].'%');
                }
                //用户手机号
                if(!empty($data['phone'])&&$data['phone'] != ''){
                    $query->where('ld_student.phone','like','%'.$data['phone'].'%');
                }
            })->whereBetween('ld_student.create_at', [$statetime, $endtime])->count();

        $studentList = Student::select('ld_student.phone','ld_student.real_name','ld_student.create_at','ld_student.reg_source','ld_student.enroll_status','ld_school.name')
            ->leftJoin('ld_school','ld_school.id','=','ld_student.school_id')
            ->where(['ld_student.is_forbid'=>1,'ld_school.is_del'=>1,'ld_school.is_forbid'=>1])
            ->where(function($query) use ($data) {
                //分校
                if(!empty($data['school_id'])&&$data['school_id'] != ''){
                    $query->where('ld_student.school_id',$data['school_id']);
                }
                //来源
                if(isset($data['source'])){
                    $query->where('ld_student.reg_source',$data['source']);
                }
                //用户类型
                if(isset($data['enroll_status'])){
                    $query->where('ld_student.enroll_status',$data['enroll_status']);
                }
                //用户姓名
                if(!empty($data['real_name'])&&$data['real_name'] != ''){
                    $query->where('ld_student.real_name','like','%'.$data['real_name'].'%');
                }
                //用户手机号
                if(!empty($data['phone'])&&$data['phone'] != ''){
                    $query->where('ld_student.phone','like','%'.$data['phone'].'%');
                }
            })
            ->whereBetween('ld_student.create_at', [$statetime, $endtime])
            ->orderByDesc('ld_student.id')
            ->get();
        //根据时间将用户分类查询总数
        $website = 0; //官网
        $offline = 0; //线下
        $mobile = 0; //手机端
        if(!empty($studentList)){
            foreach ($studentList as $k=>$v){
                if($v['reg_source'] == 0){
                    $studentList[$k]['reg_source_name'] = "官网";
                }
                if($v['reg_source'] == 1){
                    $studentList[$k]['reg_source_name'] = "线下";
                }
                if($v['reg_source'] == 2){
                    $studentList[$k]['reg_source_name'] = "手机端";
                }
                if($v['enroll_status'] == 0){
                    $studentList[$k]['enroll_status_name'] = "普通用户";
                }
                if($v['enroll_status'] == 1){
                    $studentList[$k]['enroll_status_name'] = "收费用户";
                }
                unset($studentList[$k]['reg_source']);
                unset($studentList[$k]['user_type']);
            }
        }

        return $studentList;
    }

    public function headings(): array{
        return [
            '手机号',
            '姓名',
            '注册时间',
            '所属网校',
            '来源说明',
            '用户类型说明',

        ];
    }
}
