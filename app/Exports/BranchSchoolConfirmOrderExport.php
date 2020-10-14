<?php
namespace App\Exports;

use App\Models\School;
use App\Models\Project;
use App\Models\Course;
use App\Models\Admin;
use App\Models\Pay_order_inside;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class BranchSchoolConfirmOrderExport implements FromCollection, WithHeadings {
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
        $count = Pay_order_inside::where(function($query) use ($body){
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
            $query->where('pay_status' , '=' , 1);
            $query->where('confirm_status' , '=' , 1);
        })->count();
        
        //支付方式
        $pay_type_array = [1=>'支付宝扫码',2=>'微信扫码',3=>'银联快捷支付',4=>'微信小程序',5=>'线下录入'];
        
        //回访状态
        $return_visit_array = [0=>'否',1=>'是'];
        
        //开课状态
        $classes_array  = [0=>'否',1=>'是'];
        
        //订单类型
        $order_type_array = [1=>'课程订单',2=>'报名订单',3=>'课程+报名订单'];
        
        if($count > 0){
            //新数组赋值
            $array = [];

            //获取收入详情列表
            $list = Pay_order_inside::select("order_no","create_time","name","mobile","have_user_id","school_id","project_id","subject_id","course_id","pay_type","course_Price","sign_Price","return_visit","classes","pay_time","confirm_order_type")->where(function($query) use ($body){
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
                $query->where('pay_status' , '=' , 1);
                $query->where('confirm_status' , '=' , 1);
            })->orderBy('create_time' , 'asc')->offset($offset)->limit($pagesize)->get()->toArray();

            //循环获取相关信息
            foreach($list as $k=>$v){
                //获取分校的名称
                $school_name = School::where('id' , $v['school_id'])->value('school_name');
                
                //项目名称
                $project_name= Project::where('id' , $v['project_id'])->value('name');
                
                //学科名称
                $subject_name = Project::where('parent_id' , $v['project_id'])->where('id' , $v['subject_id'])->value('name');
                
                //课程名称
                $course_name  = Course::where('id' , $v['course_id'])->value('course_name');
                
                //根据班主任id获取班主任名称
                $have_user_name = Admin::where('id' , $v['have_user_id'])->value('real_name');
                
                
                //数组赋值
                $array[] = [
                    'order_no'      =>  $v['order_no'] && !empty($v['order_no']) ? $v['order_no'] : '-' ,    //订单编号
                    'create_time'   =>  $v['create_time'] && !empty($v['create_time']) ? $v['create_time'] : '-'  ,   //订单创建时间                                        
                    'name'          =>  $v['name'] && !empty($v['name']) ? $v['name'] : '-'  , //姓名
                    'mobile'        =>  $v['mobile'] && !empty($v['mobile']) ? $v['mobile'] : '-' ,  //手机号
                    'have_user_name'=>  $have_user_name && !empty($have_user_name) ? $have_user_name : '-' , //班主任姓名
                    'school_name'   =>  $school_name && !empty($school_name) ? $school_name : '-' ,    //所属分校
                    'project_name'  =>  $project_name && !empty($project_name) ? $project_name : '-' , //项目名称
                    'subject_name'  =>  $subject_name && !empty($subject_name) ? $subject_name : '-' , //学科名称
                    'course_name'   =>  $course_name && !empty($course_name) ? $course_name : '-' ,  //课程名称
                    'pay_type'      =>  isset($pay_type_array[$v['pay_type']]) && !empty($pay_type_array[$v['pay_type']]) ? $pay_type_array[$v['pay_type']] : '-' , //支付方式
                    'course_price'  =>  $v['course_Price'] && $v['course_Price'] > 0 ? floatval($v['course_Price']) : '-' ,  //课程金额
                    'sign_price'    =>  $v['sign_Price'] && $v['sign_Price'] > 0 ? floatval($v['sign_Price']) : '-' ,        //报名金额
                    'sum_money'     =>  0 ,  //总金额
                    'return_visit'  =>  isset($return_visit_array[$v['return_visit']]) && !empty($return_visit_array[$v['return_visit']]) ? $return_visit_array[$v['return_visit']] : '-' , //是否回访
                    'classes'       =>  isset($classes_array[$v['classes']]) && !empty($classes_array[$v['classes']]) ? $classes_array[$v['classes']] : '-' , //是否开课
                    'pay_time'      =>  $v['pay_time'] && !empty($v['pay_time']) ? $v['pay_time'] : '-'  ,   //支付成功时间
                    'order_type'    =>  isset($order_type_array[$v['confirm_order_type']]) && !empty($order_type_array[$v['confirm_order_type']]) ? $order_type_array[$v['confirm_order_type']] : '-'   //订单类型
                ];
            }
        }
        return collect($array);
    }

    public function headings(): array {
        return ['订单编号', '订单创建时间', '姓名', '手机号', '班主任', '所属分校', '项目', '学科', '课程', '支付方式', '课程金额', '报名金额' , '总金额' , '是否回访' , '是否开课' , '支付成功时间' , '订单类型'];
    }

}
