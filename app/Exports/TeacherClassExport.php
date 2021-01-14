<?php
namespace App\Exports;

use App\Models\AdminLog;
use App\Models\Student;
use App\Models\School;
use App\Models\CourseClassTeacher;
use App\Models\Teacher;
use App\Models\CourseClassNumber;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromArray;
class TeacherClassExport implements FromArray{
//学员统计导出
    protected $data;
    public function __construct($post){
        $this->data = $post;
    }
    public function collection() {
        $data = $this->data;
        $school = School::where(['id'=>$data['school_id']])->first();
       //获取用户网校id
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
       //先查询课时   再查询讲师
       $keci = CourseClassTeacher::where(['is_del'=>0])->whereBetween('create_at', [$statetime, $endtime])->groupBy('teacher_id')->get();
       $dataArr=[];
       if(!empty($keci)){
           $keci = $keci->toArray();
           foreach ($keci as $k=>$v){

               //讲师信息    课时总数
               $teacher = Teacher::where(['id' => $v['teacher_id']])
                   ->where(function ($query) use ($data) {
                       if (!empty($data['real_name']) && $data['real_name'] != '') {
                           $query->where('real_name', 'like', '%' . $data['real_name'] . '%');
                       }
                       if (!empty($data['phone']) && $data['phone'] != '') {
                           $query->where('phone', 'like', '%' . $data['phone'] . '%');
                       }
                   })->first();
               if (!empty($teacher)) {
                   $keshicount = CourseClassTeacher::where(['is_del' => 0, 'teacher_id' => $v['teacher_id']])->whereBetween('create_at', [$statetime, $endtime])->get();
                   $keshicounts = 0;
                   if (!empty($keshicount)) {
                       $keshicount = $keshicount->toArray();
                       $ids = array_column($keshicount, 'class_id');
                       $keshicounts = CourseClassNumber::whereIn('id', $ids)->where(['is_del' => 0, 'status' => 1])->sum('class_hour');
                   }
                   if(empty($keshicounts)){
                        $keshicounts = "0";
                   }
                   $dataArr[] = [
                       //'id' => $v['teacher_id'],
                       'school_name' => $school['name'],
                       'phone' => $teacher['phone'],
                       'real_name' => $teacher['real_name'],
                       'times' => $keshicounts
                   ];
               }
           }

        $arr = ["school_name"=>"所属网校","phone"=>"手机号","real_name"=>"姓名","times"=>"课时"];
        array_unshift($dataArr,$arr);
        return $dataArr;
    }
}
    public function array(): array{
        // return [
        //     'id',
        //     '所属网校',
        //     '手机号',
        //     '姓名',
        //     '课时',
        // ];
        return $this->collection();
    }
}
