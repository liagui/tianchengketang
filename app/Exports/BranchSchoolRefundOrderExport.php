<?php
namespace App\Exports;

use App\Models\School;
use App\Models\Refund_order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class BranchSchoolRefundOrderExport implements FromCollection, WithHeadings {
    protected $where;
    protected $resultSetType = 'collection';

    public function __construct($invoices) {
        $this->where = $invoices;
    }

    public function collection() {
        $body = $this->where;
        
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        
        //获取日期
        if(!isset($body['create_time']) || empty($body['create_time'])){
            return ['code' => 201 , 'msg' => '明细日期不能为空'];
        }

        //获取收入详情的总数量
        $count = Refund_order::where(function($query) use ($body){
            //判断分校id是否为空和合法
            if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                $query->where('school_id' , '=' , $body['school_id']);
            } else {
                $query->whereIn('school_id' , $body['schoolId']);
            }
                
            //判断项目-学科大小类是否为空
            if(isset($body['category_id']) && !empty($body['category_id'])){
                $category_id= json_decode($body['category_id'] , true);
                $project_id = isset($category_id[0]) && $category_id[0] ? $category_id[0] : 0;
                $subject_id = isset($category_id[1]) && $category_id[1] ? $category_id[1] : 0;

                //判断项目id是否传递
                if($project_id && $project_id > 0){
                    $query->where('project_id' , '=' , $project_id);
                }

                //判断学科id是否传递
                if($subject_id && $subject_id > 0){
                    $query->where('subject_id' , '=' , $subject_id);
                }
            }

            //判断课程id是否为空和合法
            if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                $query->where('course_id' , '=' , $body['course_id']);
            }

            //获取日期
            $state_time  = $body['create_time']." 00:00:00";
            $end_time    = $body['create_time']." 23:59:59";
            $query->where('create_time', '>=' , $state_time)->where('create_time', '<=' , $end_time);
        })->count();
        
        if($count > 0){
            //新数组赋值
            $array = [];

            //获取收入详情列表
            $list = Refund_order::select("refund_no","create_time","student_name","phone","school_id","refund_Price","refund_reason")->where(function($query) use ($body){
                //判断分校id是否为空和合法
                if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                    $query->where('school_id' , '=' , $body['school_id']);
                }
            
                //判断项目-学科大小类是否为空
                if(isset($body['category_id']) && !empty($body['category_id'])){
                    $category_id= json_decode($body['category_id'] , true);
                    $project_id = isset($category_id[0]) && $category_id[0] ? $category_id[0] : 0;
                    $subject_id = isset($category_id[1]) && $category_id[1] ? $category_id[1] : 0;

                    //判断项目id是否传递
                    if($project_id && $project_id > 0){
                        $query->where('project_id' , '=' , $project_id);
                    }

                    //判断学科id是否传递
                    if($subject_id && $subject_id > 0){
                        $query->where('subject_id' , '=' , $subject_id);
                    }
                }

                //判断课程id是否为空和合法
                if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                    $query->where('course_id' , '=' , $body['course_id']);
                }

                //获取日期
                $state_time  = $body['create_time']." 00:00:00";
                $end_time    = $body['create_time']." 23:59:59";
                $query->where('create_time', '>=' , $state_time)->where('create_time', '<=' , $end_time);
            })->orderBy('create_time' , 'asc')->offset($offset)->limit($pagesize)->get()->toArray();

            //循环获取相关信息
            foreach($list as $k=>$v){
                //获取分校的名称
                $school_name = School::where('id' , $v['school_id'])->value('school_name');
                
                //数组赋值
                $array[] = [
                    'order_no'      =>  $v['refund_no'] && !empty($v['refund_no']) ? $v['refund_no'] : '-' ,    //退费单号
                    'create_time'   =>  $v['create_time'] && !empty($v['create_time']) ? $v['create_time'] : '-'  ,   //退费发起时间                                        
                    'name'          =>  $v['student_name'] && !empty($v['student_name']) ? $v['student_name'] : '-'  , //姓名
                    'mobile'        =>  $v['phone'] && !empty($v['phone']) ? $v['phone'] : '-' ,  //手机号
                    'school_name'   =>  $school_name && !empty($school_name) ? $school_name : '-' ,    //所属分校
                    'refund_price'  =>  $v['refund_Price'] && $v['refund_Price'] > 0 ? floatval($v['refund_Price']) : '-' ,  //退费金额
                    'refund_reason' =>  $v['refund_reason'] && !empty($v['refund_reason']) ? $v['refund_reason'] : '-'       //退费原因
                ];
            }
        }
        return collect($array);
    }

    public function headings(): array {
        return ['退费单号', '退费发起时间', '姓名', '手机号' , '分校', '退费金额', '退费原因'];
    }

}
