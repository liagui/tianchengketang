<?php
namespace App\Exports;

use App\Models\School;
use App\Models\Project;
use App\Models\Course;
use App\Models\Pay_order_inside;
use App\Models\Refund_order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class BranchSchoolExport implements FromCollection, WithHeadings {
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

        //获取收入详情的总数量
        $count = Pay_order_inside::selectRaw("count(date_format(create_time , '%Y%m%d')) as t_count")->where(function($query) use ($body){
            //判断分校id是否为空和合法
            if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                $query->where('school_id' , $body['school_id']);
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
                    $query->where('project_id' , $project_id);
                }

                //判断学科id是否传递
                if($subject_id && $subject_id > 0){
                    $query->where('subject_id' , $subject_id);
                }
            }

            //判断课程id是否为空和合法
            if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                $query->where('course_id' , $body['course_id']);
            }

            //获取日期
            if(isset($body['create_time']) && !empty($body['create_time'])){
                $create_time = json_decode($body['create_time'] , true);
                $state_time  = $create_time[0]." 00:00:00";
                $end_time    = $create_time[1]." 23:59:59";
                $query->where('create_time', '>=' , $state_time)->where('create_time', '<=' , $end_time);
            }
        })->groupBy(DB::raw("date_format(create_time , '%Y%m%d')"))->get()->count();
        
        //判断数量是否大于0
        if($count > 0){
            //新数组赋值
            $array = [];

            //获取收入详情列表
            $list = Pay_order_inside::selectRaw("any_value(project_id) as project_id , any_value(subject_id) as subject_id , any_value(course_id) as course_id , any_value(school_id) as school_id , any_value(create_time) as create_time")->where(function($query) use ($body){
                //判断分校id是否为空和合法
                if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                    $query->where('school_id' , $body['school_id']);
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
                        $query->where('project_id' , $project_id);
                    }

                    //判断学科id是否传递
                    if($subject_id && $subject_id > 0){
                        $query->where('subject_id' , $subject_id);
                    }
                }

                //判断课程id是否为空和合法
                if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                    $query->where('course_id' , $body['course_id']);
                }

                //获取日期
                if(isset($body['create_time']) && !empty($body['create_time'])){
                    $create_time = json_decode($body['create_time'] , true);
                    $state_time  = $create_time[0]." 00:00:00";
                    $end_time    = $create_time[1]." 23:59:59";
                    $query->where('create_time', '>=' , $state_time)->where('create_time', '<=' , $end_time);
                }
            })->orderBy('create_time' , 'asc')->groupBy(DB::raw("date_format(create_time , '%Y%m%d')"))->offset($offset)->limit($pagesize)->get()->toArray();
            
            //条件赋值
            $where = [];

            //循环获取相关信息
            foreach($list as $k=>$v){
                //判断项目-学科大小类是否为空
                if(isset($body['category_id']) && !empty($body['category_id'])){
                    $category_id= json_decode($body['category_id'] , true);
                    $project_id = isset($category_id[0]) && $category_id[0] ? $category_id[0] : 0;
                    $subject_id = isset($category_id[1]) && $category_id[1] ? $category_id[1] : 0;

                    //判断项目id是否传递
                    if($project_id && $project_id > 0){
                        //项目名称
                        $project_name = Project::where('id' , $project_id)->value('name');
                    } else {
                        $project_name = "所有项目";
                    }

                    //判断学科id是否传递
                    if($subject_id && $subject_id > 0){
                        //学科名称
                        $subject_name = Project::where('parent_id' , $project_id)->where('id' , $subject_id)->value('name');
                    } else {
                        $subject_name = "所有学科";
                    }
                } else {
                    $project_name = "所有项目";
                    $subject_name = "所有学科";
                }
                
                //判断课程id是否为空和合法
                if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                    //课程名称
                    $course_name  = Course::where('id' , $body['course_id'])->value('course_name');
                } else {
                    $course_name  = "所有课程";
                }
                
                //判断分校id是否为空和合法
                if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                    //分校的名称
                    $school_name  = School::where('id' , $body['school_id'])->value('school_name');
                } else {
                    $school_name  = "所有分校";
                }

                $body['createTime'] = $v['create_time'];
                //到账订单数
                $received_order = Pay_order_inside::where(function($query) use ($body){
                    //判断分校id是否为空和合法
                    if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                        $query->where('school_id' , $body['school_id']);
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
                            $query->where('project_id' , $project_id);
                        }

                        //判断学科id是否传递
                        if($subject_id && $subject_id > 0){
                            $query->where('subject_id' , $subject_id);
                        }
                    }

                    //判断课程id是否为空和合法
                    if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                        $query->where('course_id' , $body['course_id']);
                    }

                    //获取日期
                    $createTime = date('Y-m-d' , strtotime($body['createTime']));
                    $startTime  = $createTime." 00:00:00";
                    $endTime    = $createTime." 23:59:59";
                    $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
                })->where('pay_status'  ,1)->where('confirm_status'  ,1)->count();

                //到账金额
                $received_money = Pay_order_inside::where(function($query) use ($body){
                    //判断分校id是否为空和合法
                    if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                        $query->where('school_id' , $body['school_id']);
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
                            $query->where('project_id' , $project_id);
                        }

                        //判断学科id是否传递
                        if($subject_id && $subject_id > 0){
                            $query->where('subject_id' , $subject_id);
                        }
                    }

                    //判断课程id是否为空和合法
                    if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                        $query->where('course_id' , $body['course_id']);
                    }

                    //获取日期
                    $createTime = date('Y-m-d' , strtotime($body['createTime']));
                    $startTime  = $createTime." 00:00:00";
                    $endTime    = $createTime." 23:59:59";
                    $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
                })->where('pay_status' , 1)->where('confirm_status'  ,1)->sum('pay_price');

                //退费订单数量
                $refund_order   = Refund_order::where(function($query) use ($body){
                    //判断分校id是否为空和合法
                    if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                        $query->where('school_id' , $body['school_id']);
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
                            $query->where('project_id' , $project_id);
                        }

                        //判断学科id是否传递
                        if($subject_id && $subject_id > 0){
                            $query->where('subject_id' , $subject_id);
                        }
                    }

                    //判断课程id是否为空和合法
                    if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                        $query->where('course_id' , $body['course_id']);
                    }

                    //获取日期
                    $createTime = date('Y-m-d' , strtotime($body['createTime']));
                    $startTime  = $createTime." 00:00:00";
                    $endTime    = $createTime." 23:59:59";
                    $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
                })->count();

                //退费金额
                $refund_money   = Refund_order::where(function($query) use ($body){
                    //判断分校id是否为空和合法
                    if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                        $query->where('school_id' , $body['school_id']);
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
                            $query->where('project_id' , $project_id);
                        }

                        //判断学科id是否传递
                        if($subject_id && $subject_id > 0){
                            $query->where('subject_id' , $subject_id);
                        }
                    }

                    //判断课程id是否为空和合法
                    if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                        $query->where('course_id' , $body['course_id']);
                    }

                    //获取日期
                    $createTime = date('Y-m-d' , strtotime($body['createTime']));
                    $startTime  = $createTime." 00:00:00";
                    $endTime    = $createTime." 23:59:59";
                    $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
                })->sum('refund_Price');

                //报名总费用
                $enroll_price   = Pay_order_inside::where(function($query) use ($body){
                    //判断分校id是否为空和合法
                    if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                        $query->where('school_id' , $body['school_id']);
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
                            $query->where('project_id' , $project_id);
                        }

                        //判断学科id是否传递
                        if($subject_id && $subject_id > 0){
                            $query->where('subject_id' , $subject_id);
                        }
                    }

                    //判断课程id是否为空和合法
                    if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                        $query->where('course_id' , $body['course_id']);
                    }

                    //获取日期
                    $createTime = date('Y-m-d' , strtotime($body['createTime']));
                    $startTime  = $createTime." 00:00:00";
                    $endTime    = $createTime." 23:59:59";
                    $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
                })->where('pay_status' , 1)->where('confirm_status' , 1)->sum('sign_Price');

                //成本总费用 
                $prime_cost     = Pay_order_inside::where(function($query) use ($body){
                    //判断分校id是否为空和合法
                    if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                        $query->where('school_id' , $body['school_id']);
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
                            $query->where('project_id' , $project_id);
                        }

                        //判断学科id是否传递
                        if($subject_id && $subject_id > 0){
                            $query->where('subject_id' , $subject_id);
                        }
                    }

                    //判断课程id是否为空和合法
                    if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                        $query->where('course_id' , $body['course_id']);
                    }

                    //获取日期
                    $createTime = date('Y-m-d' , strtotime($body['createTime']));
                    $startTime  = $createTime." 00:00:00";
                    $endTime    = $createTime." 23:59:59";
                    $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
                })->where('pay_status' , 1)->where('confirm_status' , 1)->sum('sum_Price');

                //实际佣金总费用
                $actual_commission = Pay_order_inside::where(function($query) use ($body){
                    //判断分校id是否为空和合法
                    if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                        $query->where('school_id' , $body['school_id']);
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
                            $query->where('project_id' , $project_id);
                        }

                        //判断学科id是否传递
                        if($subject_id && $subject_id > 0){
                            $query->where('subject_id' , $subject_id);
                        }
                    }

                    //判断课程id是否为空和合法
                    if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
                        $query->where('course_id' , $body['course_id']);
                    }

                    //获取日期
                    $createTime = date('Y-m-d' , strtotime($body['createTime']));
                    $startTime  = $createTime." 00:00:00";
                    $endTime    = $createTime." 23:59:59";
                    $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
                })->where('pay_status' , 1)->where('confirm_status' , 1)->sum('actual_commission');

                //数组赋值
                $array[] = [
                    'create_time'   =>  date('Y-m-d' ,strtotime($v['create_time'])) ,
                    'school_name'   =>  $school_name  && !empty($school_name)  ? $school_name  : '' ,
                    'project_name'  =>  $project_name && !empty($project_name) ? $project_name : '' ,
                    'subject_name'  =>  $subject_name && !empty($subject_name) ? $subject_name : '' ,
                    'course_name'   =>  $course_name  && !empty($course_name)  ? $course_name  : '' ,
                    'received_order'=>  $received_order > 0 ? $received_order : 0 ,  //到账订单数量
                    'refund_order'  =>  $refund_order > 0 ? $refund_order : 0 ,      //退费订单数量
                    'received_money'=>  $received_money > 0 ? floatval($received_money) : 0 ,  //到账金额
                    'refund_money'  =>  $refund_money > 0 ? floatval($refund_money) : 0 ,      //退费金额
                    'enroll_price'  =>  $enroll_price > 0 ? floatval($enroll_price) : 0 ,      //报名费用
                    'prime_cost'    =>  $prime_cost > 0 ? floatval($prime_cost) : 0 ,          //成本
                    'actual_commission' => $actual_commission > 0 ? floatval($actual_commission) : 0 ,  //实际佣金
                ];
            }
        }
        return collect($array);
    }

    public function headings(): array {
        return ['日期', '分校', '项目', '学科', '课程', '到账订单数', '退费订单数', '到账金额', '退费金额', '报名费用', '成本', '实际佣金'];
    }

}
