<?php
namespace App\Models;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\School;
use App\Models\Refund_order;

class Pay_order_inside extends Model
{
    //指定别的表名
    public $table = 'pay_order_inside';
    //时间戳设置
    public $timestamps = false;
    /*
         * @param  订单总览
         * @param  pay_type    支付方式 1支付宝扫码2微信扫码3银联快捷支付4微信小程序5线下录入
         * @param  pay_status  支付状态 0未支付1已支付2支付失败
         * @param  confirm_order_type   1课程订单 2报名订单3课程+报名订单
         * @param  return_visit   0未回访 1 已回访
         * @param  classes   0不开课 1开课
         * @param  confirm_status   订单确认状态码 0未确认 1确认  2驳回
         * @param  project_id  数组
         * @param  school_id   学校id
         * @param  state_time   创建时间
         * @param  end_time    结束时间
         * @param  order_no    订单号/手机号/姓名
         * @param  pagesize    每页显示条数
         * @param  page    第几页
         * @param  苏振文
         * @param  2020/9/2 15:52
         * return  array
         */
    public static function orderList($data,$schoolarr){
        $where['del_flag']=0;
        //判断时间
        $begindata="2020-03-04";
        $enddate = date('Y-m-d');
        $statetime = !empty($data['state_time'])?$data['state_time']:$begindata;
        $endtime = !empty($data['end_time'])?$data['end_time']:$enddate;
        $state_time = $statetime." 00:00:00";
        $end_time = $endtime." 23:59:59";
        //支付方式
        if(!empty($data['pay_type'])){
            $where['pay_type'] = $data['pay_type'];
        }
        //支付状态
        if(isset($data['pay_status'])){
            $where['pay_status'] = $data['pay_status'];
        }
        //订单是否回访
        if(isset($data['return_visit'])){
            $where['return_visit'] = $data['return_visit'];
        }
        //订单状态
        if(isset($data['confirm_status'])){
            $where['confirm_status'] = $data['confirm_status'];
        }
        //学校id
        if(isset($data['school_id'])){
            $where['school_id'] = $data['school_id'];
        }
        //科目id&学科id
        if(!empty($data['project_id'])){
            $parent = json_decode($data['project_id'], true);
            if(!empty($parent[0])){
                $where['project_id'] = $parent[0];
                if(!empty($parent[1])){
                    $where['subject_id'] = $parent[1];
                }
            }
        }
        //每页显示的条数
        $pagesize = (int)isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //数据   流转订单 + 第三方支付订单
        $order = self::where(function($query) use ($data,$schoolarr) {
                if(isset($data['order_no']) && !empty($data['order_no'])){
                    $query->where('order_no',$data['order_no'])
                        ->orwhere('name',$data['order_no'])
                        ->orwhere('mobile',$data['order_no']);
                }
                if(isset($data['classes'])){
                    $query->where('classes',$data['classes']);
                }
                if(isset($data['confirm_order_type'])){
                    $query->where('confirm_order_type',$data['confirm_order_type']);
                }
                $query->whereIn('school_id',$schoolarr);
            })
            ->where($where)
//            ->where('pay_status','<',2)
            ->whereBetween('create_time', [$state_time, $end_time])
            ->orderByDesc('id')
            ->get()->toArray();
        $external = Pay_order_external::where(function($query) use ($data,$schoolarr) {
            if (isset($data['order_no']) && !empty($data['order_no'])) {
                $query->where('order_no', $data['order_no'])
                    ->orwhere('name', $data['order_no'])
                    ->orwhere('mobile', $data['order_no']);
            }
        })->where($where)
            ->where(['pay_status'=>1,'status'=>0])
        ->whereBetween('create_time', [$state_time, $end_time])
        ->orderByDesc('id')
        ->get()->toArray();
        //分校只显示流转
        if(!empty($data['isBranchSchool']) && $data['isBranchSchool'] == true){
            $orderprice = self::whereIn('school_id',$schoolarr)->sum('pay_price');
            $externalprice = 0;
            $all = $order;
            $count = count($order);
        }else{
            //两数组合并
            if (!empty($order) && !empty($external)) {
                $all = array_merge($order, $external);//合并两个二维数组
            } else {
                $all = !empty($order) ? $order : $external;
            }
            $orderprice = self::whereIn('school_id',$schoolarr)->sum('pay_price');
            $externalprice = Pay_order_external::where(['pay_status'=>1])->sum('pay_price');
            //循环查询分类
            $count = count($order) + count($external);
        }
        $date = array_column($all, 'create_time');
        array_multisort($date, SORT_DESC, $all);
        $res = array_slice($all, $offset, $pagesize);
        if(empty($res)){
            $res = array_slice($all, 1, $pagesize);
        }

        if(!empty($res)){
            foreach ($res as $k=>&$v){
                //查学校
                if(empty($v['school_id']) || $v['school_id'] == 0){
                        $v['school_name'] = '';
                }else{
                    $school = School::where(['id'=>$v['school_id']])->first();
                    if($school){
                        $v['school_name'] = $school['school_name'];
                    }
                }
                if($v['pay_type'] <= 4){
                    if(!empty($v['offline_id'])){
                        $chnnel = Channel::where(['id'=>$v['offline_id']])->first();
                        if($v['pay_type'] == 1){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-微信';
                        }else if ($v['pay_type'] == 2){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-支付宝';
                        }else if ($v['pay_type'] == 3){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-汇聚-微信';
                        }else if ($v['pay_type'] == 4){
                            $v['pay_type_text'] =$chnnel['channel_name'].'-汇聚-支付宝';
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }else{
                    if(!empty($v['offline_id'])){
                        $offline = OfflinePay::where(['id'=>$v['offline_id']])->first();
                        if ($v['pay_type'] == 5){
                            $v['pay_type_text'] = '银行卡支付-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 6){
                            $v['pay_type_text'] = '对公转账-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 7){
                            $v['pay_type_text'] = '支付宝账号对公-'.$offline['account_name'];
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }
                if($v['pay_status'] == 0){
                    $v['pay_status_text'] = '未支付';
                }else if($v['pay_status'] == 1){
                    $v['pay_status_text'] = '已支付';
                }else if($v['pay_status'] == 2){
                    $v['pay_status_text'] = '支付失败';
                }else if($v['pay_status'] == 3){
                    $v['pay_status_text'] = '待审核';
                }
                if(!isset($v['return_visit'])){
                    $v['return_visit_text'] = '';
                }else{
                    if($v['return_visit'] == 0){
                        $v['return_visit_text'] = '否';
                    }else{
                        $v['return_visit_text'] = '是';
                    }
                }
                if(!isset($v['classes'])){
                    $v['classes_text'] = '';
                }else{
                    if( $v['classes'] == 0){
                        $v['classes_text'] = '否';
                    }else{
                        $v['classes_text'] = '是';
                    }
                }
                if(empty($v['confirm_order_type'])){
                    $v['confirm_order_type_text'] = '';
                }else{
                    if($v['confirm_order_type'] == 1){
                        $v['confirm_order_type_text'] = '课程订单';
                    }else if($v['confirm_order_type'] == 2){
                        $v['confirm_order_type_text'] = '报名订单';
                    }else if($v['confirm_order_type'] == 3){
                        $v['confirm_order_type_text'] = '课程+报名订单';
                    }
                }

                if(empty($v['first_pay'])){
                    $v['first_pay_text'] = '';
                }else{
                    if($v['first_pay'] == 1){
                        $v['first_pay_text'] = '全款';
                    }else if($v['first_pay'] == 2){
                        $v['first_pay_text'] = '定金';
                    }else if($v['first_pay'] == 3){
                        $v['first_pay_text'] = '部分尾款';
                    }else if($v['first_pay'] == 4){
                        $v['first_pay_text'] = '最后一笔尾款';
                    }
                }
                if(empty($v['confirm_status'])){
                    $v['confirm_status_text'] = '';
                }else{
                    if($v['confirm_status'] == 0){
                        $v['confirm_status_text'] = '未确认';
                    }else if($v['confirm_status'] == 1){
                        $v['confirm_status_text'] = '确认';
                    }else if($v['confirm_status'] == 2){
                        $v['confirm_status_text'] = '驳回';
                    }
                }
                //course  课程
                $course = Course::select('course_name')->where(['id'=>$v['course_id']])->first();
                $v['course_name'] = $course['course_name'];
                //Project  项目
                $project = Project::select('name')->where(['id'=>$v['project_id']])->first();
                $v['project_name'] = $project['name'];
                //Subject  学科
                $subject = Project::select('name')->where(['id'=>$v['subject_id']])->first();
                $v['subject_name'] = $subject['name'];
                if(!empty($v['education_id']) && $v['education_id'] != 0){
                    //查院校
                    $education = Education::select('education_name')->where(['id'=>$v['education_id']])->first();
                    $v['education_name'] = $education['education_name'];
                    //查专业
                    $major = Major::where(['id'=>$v['major_id']])->first();
                    $v['major_name'] = $major['major_name'];
                }
                //根据上传凭证人id查询凭证名称
                if(empty($v['pay_voucher_user_id'])){
                    $v['pay_voucher_name'] = '';
                }else{
                    $adminname = Admin::where(['id'=>$v['pay_voucher_user_id']])->first();
                    $v['pay_voucher_name'] = $adminname['username'];
                }
                //驳回人查询
                if(empty($v['reject_admin_id'])){
                    $v['reject_admin_name'] = '';
                }else{
                    $adminreject = Admin::where(['id'=>$v['reject_admin_id']])->first();
                    $v['reject_admin_name'] = $adminreject['username'];
                }
                //备注人 admin_id
                if(empty($v['admin_id'])){
                    $v['remark_admin_name'] = '';
                }else{
                    $adminbeizhu = Admin::where(['id'=>$v['admin_id']])->first();
                    $v['remark_admin_name'] = $adminbeizhu['username'];
                }
            }
        }
        $page=[
            'pagesize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        //计算总数
        $countprice = $orderprice + $externalprice;
        //总金额
        return ['code' => 200 , 'msg' => '查询成功','data'=>$res,'countprice'=>number_format($countprice,2),'where'=>$data,'page'=>$page];
    }
    /*
         * @param  手动报单
         * @param   project_id  项目id
         * @param   subject_id  学科id
         * @param   course_id  课程id
         * @param   education_id  课程id
         * @param   major_id  院校id
         * @param   mobile  专业id
         * @param   pay_price  支付金额
         * @param   pay_type  支付方式（1微信扫码2支付宝扫码3汇聚微信4汇聚支付宝5银行卡支付6对公转账7支付宝账号对公）
         * @param   remark  备注
         * @param   name  姓名
         * @param   school_id  所属分校
         * @param   pay_voucher  支付凭证
         * @param  author  苏振文
         * @param  ctime   2020/9/3 10:46
         * return  array
         */
    public static function handOrder($data){
        $admin = isset(AdminLog::getAdminInfo()->admin_user) ? AdminLog::getAdminInfo()->admin_user : [];
        //科目id&学科id
        if(!isset($data['course_id']) || empty($data['course_id'])){
            return ['code' => 201 , 'msg' => '未选择课程'];
        }
        if(!isset($data['mobile']) || empty($data['mobile'])){
            return ['code' => 201 , 'msg' => '未输入手机号'];
        }
//        if(!in_array($data['pay_type'],[1,2,3,4,5,6,7])){
//            return ['code' => 201 , 'msg' => '未选择支付方式'];
//        }
        if(!isset($data['name']) || empty($data['name'])){
            return ['code' => 201 , 'msg' => '未填写姓名'];
        }
        if(!isset($data['school_id']) || empty($data['school_id'])){
            return ['code' => 201 , 'msg' => '未选择分校'];
        }
        if(!isset($data['pay_voucher']) || empty($data['pay_voucher'])){
            return ['code' => 201 , 'msg' => '未上传支付凭证'];
        }
        unset($data['/admin/order/handOrder']);
        if(empty($data['education_id'])){
            unset($data['education_id']);
            unset($data['major_id']);
        }
        //根据条件查询第三方订单，如果有，直接到流转，否则就进去审核订单
        $data['course_Price'] = isset($data['course_Price'])?$data['course_Price']:0;
        $data['sign_Price'] = isset($data['sign_Price'])?$data['sign_Price']:0;
        $data['add_time'] =date('Y-m-d H:i:s');
        $data['confirm_status'] = 0;
        $data['pay_voucher_user_id'] = $admin['id']; //上传凭证人
        $data['pay_voucher_time'] = date('Y-m-d H:i:s');//上传凭证时间
        $data['admin_id'] = $admin['id'];
        $data['is_handorder'] = 1;   //手动报单
//        if($data['pay_type'] <= 4){
//            $exorder = Pay_order_external::where(['name'=>$data['name'],'mobile'=>$data['mobile'],'course_id'=>$data['course_id'],'project_id'=>$data['project_id'],'subject_id'=>$data['subject_id'],'pay_status'=>1,'status'=>0])->first();
//            if(!empty($exorder)){
//                $data['order_no'] = $exorder['order_no'];
//                $data['create_time'] =$exorder['create_time'];
//                $data['pay_time'] = $exorder['pay_time'];
//                $data['pay_status'] = 1;
//                $data['pay_price'] = $exorder['pay_price'];
//                $data['realy_pay_type'] = $exorder['pay_type'];
//                Pay_order_external::where(['id'=>$exorder['id']])->update(['status'=>1]);
//            }else{
//                $data['order_no'] = date('YmdHis', time()) . rand(1111, 9999); //订单号  随机生成
//                $data['create_time'] =date('Y-m-d H:i:s');
//                $data['pay_time'] =date('Y-m-d H:i:s');
//                $data['pay_status'] = 3;  //3是待审核
//                $data['pay_price'] = $data['course_Price'] + $data['sign_Price'];
//            }
//        }else{
            $data['order_no'] = date('YmdHis', time()) . rand(1111, 9999); //订单号  随机生成
            $data['create_time'] =date('Y-m-d H:i:s');
            $data['pay_time'] =isset($data['pay_time'])?$data['pay_time']:date('Y-m-d H:i:s');;
            $data['pay_status'] = 3;  //3是待审核
            $data['pay_price'] = $data['course_Price'] + $data['sign_Price'];
//        }
        $add = self::insert($data);
        if($add){
            return ['code' => 200 , 'msg' => '报单成功'];
        }else{
            return ['code' => 201 , 'msg' => '报单失败'];
        }
    }
    /*
         * @param  查看订单凭证
         * @param  order_id
         * @param  author  苏振文
         * @param  ctime   2020/9/3 15:29
         * return  array
         */
    public static function orderVoucher($data){
        if(!isset($data['id'])|| empty($data['id'])){
            return ['code' => 201 , 'msg' => '订单有误'];
        }
        $order = self::where(['id'=>$data['id']])->first();
        $user = Admin::where(['id'=>$order['pay_voucher_user_id'],'is_del'=>0,'is_forbid'=>0])->first();
        $res=[
            'name' => $user['username'],
            'pay_voucher' => $order['pay_voucher'],
            'pay_voucher_time' => $order['pay_voucher_time']
        ];
        return ['code' => 200 , 'msg' => '查询成功','data'=>$res];
    }
    /*
         * @param  查询备注或驳回原因
         * @param  id 订单id
         * @param  author  苏振文
         * @param  ctime   2020/9/3 16:07
         * return  array
         */
    public static function orderDetail($data){
        if(!isset($data['id'])|| empty($data['id'])){
            return ['code' => 201 , 'msg' => '订单有误'];
        }
        $order = self::where(['id'=>$data['id']])->first();
        $remark=[];
        if(!empty($order['remark'])){
            $admin = Admin::where(['id'=>$order['admin_id']])->first();
            $remark = [
                'name' => $admin['username'],
                'create_time' =>$order['create_time'],
                'remark' => $order['remark']
            ];
        }
        if($order['confirm_status'] == 2){
            $reject=[];
            if(!empty($order['reject_des'])){
                $admin = Admin::where(['id'=>$order['reject_admin_id']])->first();
                $reject=[
                    'name' => $admin['username'],
                    'create_time' =>$order['reject_time'],
                    'reject' => $order['reject_des']
                ];
            }
            return ['code' => 200 , 'msg' => '查询成功','remark' => $remark,'reject' => $reject];
        }else{
            return ['code' => 200 , 'msg' => '查询成功','remark' => $remark];
        }

    }
    /*
         * @param  订单详情
         * @param  id  订单id
         * @param  author  苏振文
         * @param  ctime   2020/9/8 15:58
         * return  array
         */
    public static function sureOrder($data){
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数有误'];
        }
        $res =self::where(['id'=>$data['id'],'del_flag'=>0])->first();
        if($res){
            //查询分类
            //course  课程
            $course = Course::select('course_name')->where(['id'=>$res['course_id']])->first();
            $res['course_name'] = $course['course_name'];
            //Project  项目
            $project = Project::select('name')->where(['id'=>$res['project_id']])->first();
            $res['project_name'] = $project['name'];
            //Subject  学科
            $subject = Project::select('name')->where(['id'=>$res['subject_id']])->first();
            $res['subject_name'] = $subject['name'];
            if(!empty($res['education_id']) && $res['education_id'] != 0){
                //查院校
                $education = Education::select('education_name')->where(['id'=>$res['education_id']])->first();
                $res['education_name'] = $education['education_name'];
                //查专业
                $major = Major::where(['id'=>$res['major_id']])->first();
                $res['major_name'] = $major['major_name'];
            }
            return ['code' => 200 , 'msg' => '查询成功','data'=>$res];
        }else{
            return ['code' => 201 , 'msg' => '查无此订单'];
        }
    }
    /*
         * @param  总校待确认订单列表   分校已提交订单
         * @param  project_id   数组形式
         * @param  school_id  分校id
         * @param  pay_type  支付方式（1支付宝扫码2微信扫码3银联快捷支付4微信小程序5线下录入）
         * @param  confirm_order_type  确认的订单类型 1课程订单 2报名订单3课程+报名订单
         * @param  return_visit  回访状态 0未回访 1 已回访
         * @param  classes  是否开课 0不开课 1开课
         * @param  order_on  订单号/手机号/姓名
         * @param  author  苏振文
         * @param  ctime   2020/9/7 10:14
         * return  array
         */
    public static function awaitOrder($data,$schoolarr){
        $where['del_flag'] = 0;  //未删除
        //科目id&学科id
        if(!empty($data['project_id'])){
            $parent = json_decode($data['project_id'], true);
            if(!empty($parent)){
                $where['project_id'] = $parent[0];
                if(!empty($parent[1])){
                    $where['subject_id'] = $parent[1];
                }
            }
        }
       if(isset($data['school_id'])){
           $where['school_id'] = $data['school_id'];
       }
        if(isset($data['pay_type'])){
            $where['pay_type'] = $data['pay_type'];
        }
        if(isset($data['confirm_order_type'])){
            $where['confirm_order_type'] = $data['confirm_order_type'];
        }
        if(isset($data['return_visit'])){
            $where['return_visit'] = $data['return_visit'];
        }
        if(isset($data['classes'])){
            $where['classes'] = $data['classes'];
        }

        //每页显示的条数
        $pagesize = (int)isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //計算總數
        $count = self::where(function($query) use ($data,$schoolarr) {
            $query->whereIn('school_id',$schoolarr);
            if(!empty($data['isBranchSchool']) && $data['isBranchSchool'] == true){
                $query->where('pay_status','=',1);
//                $query->where('confirm_status',0)
//                    ->orwhere('confirm_status',1);
            }else{
                $query->where('confirm_status',0);
                $query->where('pay_status',1);
            }
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
        })
        ->where($where)
        ->count();

        $order = self::where(function($query) use ($data,$schoolarr) {
            $query->whereIn('school_id',$schoolarr);
            if(!empty($data['isBranchSchool']) &&$data['isBranchSchool'] == true){
                $query->where('pay_status','=',1);
//                $query->where('confirm_status',0)
//                $query->where('confirm_status',1);
            }else{
                $query->where('confirm_status',0);
                $query->where('pay_status',1);
            }
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
        })
        ->where($where)
        ->orderByDesc('id')
        ->offset($offset)->limit($pagesize)->get()->toArray();
         //循环查询分类
        if(!empty($order)){
            foreach ($order as $k=>&$v){
                //查学校
                $school = School::where(['id'=>$v['school_id']])->first();
                if($school){
                    $v['school_name'] = $school['school_name'];
                }
                if($v['pay_type'] <= 4){
                    if(!empty($v['offline_id'])){
                        $chnnel = Channel::where(['id'=>$v['offline_id']])->first();
                        if($v['pay_type'] == 1){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-微信';
                        }else if ($v['pay_type'] == 2){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-支付宝';
                        }else if ($v['pay_type'] == 3){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-汇聚-微信';
                        }else if ($v['pay_type'] == 4){
                            $v['pay_type_text'] =$chnnel['channel_name'].'-汇聚-支付宝';
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }else{
                    if(!empty($v['offline_id'])){
                        $offline = OfflinePay::where(['id'=>$v['offline_id']])->first();
                        if ($v['pay_type'] == 5){
                            $v['pay_type_text'] = '银行卡支付-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 6){
                            $v['pay_type_text'] = '对公转账-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 7){
                            $v['pay_type_text'] = '支付宝账号对公-'.$offline['account_name'];
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }
                if($v['pay_status'] == 0){
                    $v['pay_status_text'] = '未支付';
                }else if($v['pay_status'] == 1){
                    $v['pay_status_text'] = '已支付';
                }else if($v['pay_status'] == 2){
                    $v['pay_status_text'] = '支付失败';
                }else if($v['pay_status'] == 3){
                    $v['pay_status_text'] = '待审核';
                }
                if($v['return_visit'] == 0){
                    $v['return_visit_text'] = '否';
                }else{
                    $v['return_visit_text'] = '是';
                }
                if($v['classes'] == 0){
                    $v['classes_text'] = '否';
                }else{
                    $v['classes_text'] = '是';
                }
                if($v['confirm_order_type'] == 1){
                    $v['confirm_order_type_text'] = '课程订单';
                }else if($v['confirm_order_type'] == 2){
                    $v['confirm_order_type_text'] = '报名订单';
                }else if($v['confirm_order_type'] == 3){
                    $v['confirm_order_type_text'] = '课程+报名订单';
                }
                if($v['first_pay'] == 1){
                    $v['first_pay_text'] = '全款';
                }else if($v['first_pay'] == 2){
                    $v['first_pay_text'] = '定金';
                }else if($v['first_pay'] == 3){
                    $v['first_pay_text'] = '部分尾款';
                }else if($v['first_pay'] == 4){
                    $v['first_pay_text'] = '最后一笔尾款';
                }
                if($v['confirm_status'] == 0){
                    $v['confirm_status_text'] = '未确认';
                }else if($v['confirm_status'] == 1){
                    $v['confirm_status_text'] = '确认';
                }else if($v['confirm_status'] == 2){
                    $v['confirm_status_text'] = '驳回';
                }
                //course  课程
                $course = Course::select('course_name')->where(['id'=>$v['course_id']])->first();
                $v['course_name'] = $course['course_name'];
                //Project  项目
                $project = Project::select('name')->where(['id'=>$v['project_id']])->first();
                $v['project_name'] = $project['name'];
                //Subject  学科
                $subject = Project::select('name')->where(['id'=>$v['subject_id']])->first();
                $v['subject_name'] = $subject['name'];
                if(!empty($v['education_id']) && $v['education_id'] != 0){
                    //查院校
                    $education = Education::select('education_name')->where(['id'=>$v['education_id']])->first();
                    $v['education_name'] = $education['education_name'];
                    //查专业
                    $major = Major::where(['id'=>$v['major_id']])->first();
                    $v['major_name'] = $major['major_name'];
                }
                //根据上传凭证人id查询凭证名称
                $adminname = Admin::where(['id'=>$v['pay_voucher_user_id']])->first();
                $v['pay_voucher_name'] = $adminname['username'];
                //备注人
                $adminname = Admin::where(['id'=>$v['admin_id']])->first();
                $v['remark_name'] = $adminname['username'];
            }
        }
        $page=[
            'pagesize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        return ['code' => 200 , 'msg' => '查询成功','data'=>$order,'where'=>$data,'page'=>$page];
    }
    /*
         * @param  未确认订单进行确认
         * @param  id  订单id
         * @param  project_id    项目id
         * @param  subject_id   学科id
         * @param  course_id  课程id
         * @param  education_id  院校id
         * @param  major_id  专业id
         * @param  name   姓名
         * @param  mobile   手机号
         * @param  confirm_order_type   订单类型
         * @param  first_pay   缴费类型
         * @param  confirm_status    1确认2驳回
         * @param  reject_des  驳回原因
         * @param  remark   订单备注
         * @param  school_id  分校id
         * @param  course_Price   课程金额
         * @param  sign_Price   报名金额
         * @param  author  苏振文
         * @param  ctime   2020/9/7 15:09
         * return  array
         */
    public static function notarizeOrder($data){
        //获取操作人信息
        $admin = isset(AdminLog::getAdminInfo()->admin_user) ? AdminLog::getAdminInfo()->admin_user : [];
        $order = self::where(['id'=>$data['id']])->first();
        unset($data['/admin/order/notarizeOrder']);

        if(empty($data['education_id'])){
            unset($data['education_id']);
            unset($data['major_id']);
        }
        if(!isset($data['fee_id'])|| empty($data['fee_id'])){
            unset($data['fee_id']);
            unset($data['sign_Price']);
        }
        if(empty($data['course_Price'])){
            unset($data['course_Price']);
        }
        if(!isset($data['confirm_status'])){
            return ['code' => 201 , 'msg' => '请选择订单确认状态'];
        }
        if($data['confirm_status'] == 1){
            if($data['confirm_order_type'] == 2){
                if($data['sign_Price'] != $order['pay_price']){
                    return ['code' => 201 , 'msg' => '所填金额不等于支付金额'];
                }
            }
            if($data['confirm_order_type'] == 3){
                $ppppp = $data['course_Price'] + $data['sign_Price'];
                if($ppppp != $order['pay_price']){
                    return ['code' => 201 , 'msg' => '所填金额不等于支付金额'];
                }
            }
            $data['comfirm_time'] = date('Y-m-d H:i:s');
            //确认订单  排课
            //值班班主任 排课
            $classlead = Admin::where(['is_del'=>1,'is_forbid'=>1,'status'=>1,'is_use'=>1])->get()->toArray();
            if(!empty($classlead)){
                //上次值班的班主任id
                $leadid = Redis::get('classlead');
                if(empty($leadid)){
                    //如果没有 就从第一个开始
                    $data['have_user_id'] = $classlead[0]['id'];
                    $data['have_user_name'] = $classlead[0]['username'];
                    Redis::set('classlead' , $classlead[0]['id']);
                }else{
                    //如果有 判断班主任id是否等于或大于最后一个数，从第一个开始排 否者数组取下一个
                    $len = count($classlead);
                    if($classlead[$len-1]['id'] <= $leadid){
                        $data['have_user_id'] = $classlead[0]['id'];
                        $data['have_user_name'] = $classlead[0]['username'];
                        Redis::set('classlead' , $classlead[0]['id']);
                    }else{
                        foreach ($classlead as $k => $v){
                            if($v['id'] > $leadid){
                                $data['have_user_id'] = $v['id'];
                                $data['have_user_name'] = $v['username'];
                                Redis::set('classlead' , $v['id']);
                                break;
                            }
                        }
                    }
                }
            }else{
                $data['have_user_id'] = 0;
            }
            //计算成本
            //到款业绩=到款金额
            //扣税=到账金额*扣税比例
            //税后金额=到账金额-扣税
            //单数=报名订单数量+含有学历成本的订单数量
            //成本=学历成本+报名费用
            //实际到款=税后金额-成本
            //返佣比例=后台分校管理中佣金比例
            //返佣金额=实际到款*返佣比例
            //保证金=返佣金额*后台分校管理中押金比例
            $school = School::where(['id'=>$data['school_id']])->first();
            $daokuan = $order['pay_price'];
            if($school['tax_point'] == 0 && strlen($school['tax_point'])  > 0){
                $kousui = 0;
            }else{
                $kousui = $daokuan * (100/$school['tax_point']);
            }
            $suihou = $daokuan - $kousui; //税后金额
            if($school['commission'] == 0 && strlen($school['commission'])  > 0){
                $fanyong = 0;
            }else{
                $fanyong = $daokuan * (100/$school['commission']); //返佣金额
            }

            if($school['deposit'] == 0 && strlen($school['deposit'])  > 0){
                $baozhengjin = 0;
            }else{
                $baozhengjin = $daokuan * (100/$school['deposit']); //保证金
            }
            //一级没有保证金  二级给一级代理保证金  三级给二级代理保证金
            if($school['level'] == 1){
                $dailibaozhengjin = 0;
                $yijichoulijine = 0;
                $erjichoulijine = 0;
                //一级分校的实际返佣=返佣金额-一级分校的保证金+（二级分校的一级抽离金额+三级分校的一级抽离金额）*（1-押金比例）
            }else if($school['level'] == 2){
                //一级抽离金额
                $yijichoulijine = $daokuan * (100/$school['one_extraction_ratio']);
                $dailibaozhengjin = $yijichoulijine * $school['deposit'];
                $erjichoulijine = 0;
                //二级分校的实际返佣=二级分校的返佣金额-二级分校的保证金+三级分校的二级抽离金额*（1-押金比例）
            }else if($school['level'] == 3){
                //一级抽离金额
                $yijichoulijine = $daokuan * (100/$school['one_extraction_ratio']);
                //二级抽离金额
                $erjichoulijine = $daokuan * (100/$school['two_extraction_ratio']);
                $dailibaozhengjin = $erjichoulijine * (100/$school['deposit']);
                //三级分校的实际返佣=三级分校的返佣金额
            }
            //查成本
            $chengben=0;
            if(!empty($data['education_id'])){
                $majorprice = Major::where(['id'=>$data['major_id']])->first();
                $signprice = isset($data['sign_Price'])?$data['sign_Price']:0;
                $chengben = $majorprice['price'] + $signprice;
            }
            $data['after_tax_amount'] = $suihou;   //税后金额
            $data['return_commission_amount'] = $fanyong;  //返佣金额
            $data['earnest_money'] = $baozhengjin;    //保证金
            $data['agent_margin'] = $dailibaozhengjin;    //代理保证金
            $data['first_out_of_amount'] = $yijichoulijine;    //1级抽离金额
            $data['second_out_of_amount'] = $erjichoulijine;    //2级抽离金额
            $data['sum_Price'] = $chengben;    //成本价
        }
        if($data['confirm_status'] == 2){
            if(!isset($data['reject_des'])){
                return ['code' => 201 , 'msg' => '请填写驳回原因'];
            }
            $data['reject_time'] = date('Y-m-d H:i:s');
            $data['reject_admin_id'] = $admin['id'];
        }
        if($data['confirm_status'] == 0){
            return ['code' => 201 , 'msg' => '请选择状态'];
        }
        $data['update_time'] = date('Y-m-d H:i:s');
        $up = self::where(['id'=>$data['id']])->update($data);
        if($up){
            //确认或驳回订单之后 将用户信息加入学生表中    在学生与课程关联表中加数据   班主任排课
            $student = Student::where(['user_name'=>$data['name'],'mobile'=>$data['mobile']])->first();
            if(!$student){
                $add=[
                    'user_name' => $data['name'],
                    'mobile' => $data['mobile'],
                    'create_time' => date('Y-m-d H:i:s'),
                ];
                Student::insert($add);
                $student_course = [
                    'order_no' => $order['order_no'],
                    'student_name' => $data['name'],
                    'phone' => $data['mobile'],
                    'order_type' => $data['confirm_order_type'],
                    'school_id' => $data['school_id'],
                    'project_id' => $data['project_id'],
                    'subject_id' => $data['subject_id'],
                    'course_id' => $data['course_id'],
                    'status' => 0,
                    'create_time' => date('Y-m-d H:i:s')
                ];
                StudentCourse::insert($student_course);
            }
            return ['code' => 200 , 'msg' => '操作成功'];
        }else{
            return ['code' => 201 , 'msg' => '操作失败'];
        }
    }
    /*
         * @param  总校确认订单列表
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/9/14 9:47
         * return  array
         */
    public static function sureOrderList($data,$schoolarr){
        $where['del_flag'] = 0;  //未删除
        $where['confirm_status'] = 1;  //已确认
        //科目id&学科id
        if(!empty($data['project_id'])){
            $parent = json_decode($data['project_id'], true);
            if(!empty($parent[0])){
                $where['project_id'] = $parent[0];
                if(!empty($parent[1])){
                    $where['subject_id'] = $parent[1];
                }
            }
        }
        if(isset($data['school_id'])){
            $where['school_id'] = $data['school_id'];
        }
        if(isset($data['pay_type'])){
            $where['pay_type'] = $data['pay_type'];
        }
        if(isset($data['confirm_order_type']) ){
            $where['confirm_order_type'] = $data['confirm_order_type'];
        }
        if(isset($data['return_visit'])){
            $where['return_visit'] = $data['return_visit'];
        }
        if(isset($data['classes']) ){
            $where['classes'] = $data['classes'];
        }

        //每页显示的条数
        $pagesize = (int)isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //計算總數
        $count = self::where(function($query) use ($data,$schoolarr) {
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
            $query->whereIn('school_id',$schoolarr);
        })
            ->where('pay_status','<',2)
            ->where('confirm_status','=',1)
            ->where($where)
            ->count();

        $order = self::where(function($query) use ($data,$schoolarr) {
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
            $query->whereIn('school_id',$schoolarr);
        })
            ->where('pay_status','<',2)
            ->where('confirm_status','=',1)
            ->where($where)
            ->orderByDesc('id')
            ->offset($offset)->limit($pagesize)->get()->toArray();
        //循环查询分类
        if(!empty($order)){
            foreach ($order as $k=>&$v){
                if($v['pay_type'] <= 4){
                    if(!empty($v['offline_id'])){
                        $chnnel = Channel::where(['id'=>$v['offline_id']])->first();
                        if($v['pay_type'] == 1){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-微信';
                        }else if ($v['pay_type'] == 2){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-支付宝';
                        }else if ($v['pay_type'] == 3){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-汇聚-微信';
                        }else if ($v['pay_type'] == 4){
                            $v['pay_type_text'] =$chnnel['channel_name'].'-汇聚-支付宝';
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }else{
                    if(!empty($v['offline_id'])){
                        $offline = OfflinePay::where(['id'=>$v['offline_id']])->first();
                        if ($v['pay_type'] == 5){
                            $v['pay_type_text'] = '银行卡支付-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 6){
                            $v['pay_type_text'] = '对公转账-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 7){
                            $v['pay_type_text'] = '支付宝账号对公-'.$offline['account_name'];
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }
                if($v['pay_status'] == 0){
                    $v['pay_status_text'] = '待支付';
                }else if($v['pay_status'] == 1){
                    $v['pay_status_text'] = '已支付';
                }else if($v['pay_status'] == 2){
                    $v['pay_status_text'] = '支付失败';
                }else if($v['pay_status'] == 3){
                    $v['pay_status_text'] = '待审核';
                }
                if($v['return_visit'] == 0){
                    $v['return_visit_text'] = '否';
                }else{
                    $v['return_visit_text'] = '是';
                }
                if($v['classes'] == 0){
                    $v['classes_text'] = '否';
                }else{
                    $v['classes_text'] = '是';
                }
                if($v['confirm_order_type'] == 1){
                    $v['confirm_order_type_text'] = '课程订单';
                }else if($v['confirm_order_type'] == 2){
                    $v['confirm_order_type_text'] = '报名订单';
                }else if($v['confirm_order_type'] == 3){
                    $v['confirm_order_type_text'] = '课程+报名订单';
                }
                if($v['first_pay'] == 1){
                    $v['first_pay_text'] = '全款';
                }else if($v['first_pay'] == 2){
                    $v['first_pay_text'] = '定金';
                }else if($v['first_pay'] == 3){
                    $v['first_pay_text'] = '部分尾款';
                }else if($v['first_pay'] == 4){
                    $v['first_pay_text'] = '最后一笔尾款';
                }
                if($v['confirm_status'] == 0){
                    $v['confirm_status_text'] = '未确认';
                }else if($v['confirm_status'] == 1){
                    $v['confirm_status_text'] = '确认';
                }else if($v['confirm_status'] == 2){
                    $v['confirm_status_text'] = '驳回';
                }
                //查学校
                $school = School::where(['id'=>$v['school_id']])->first();
                if($school){
                    $v['school_name'] = $school['school_name'];
                }
                //course  课程
                $course = Course::select('course_name')->where(['id'=>$v['course_id']])->first();
                $v['course_name'] = $course['course_name'];
                //Project  项目
                $project = Project::select('name')->where(['id'=>$v['project_id']])->first();
                $v['project_name'] = $project['name'];
                //Subject  学科
                $subject = Project::select('name')->where(['id'=>$v['subject_id']])->first();
                $v['subject_name'] = $subject['name'];
                if(!empty($v['education_id']) && $v['education_id'] != 0){
                    //查院校
                    $education = Education::select('education_name')->where(['id'=>$v['education_id']])->first();
                    $v['education_name'] = $education['education_name'];
                    //查专业
                    $major = Major::where(['id'=>$v['major_id']])->first();
                    $v['major_name'] = $major['major_name'];
                }
                //根据上传凭证人id查询凭证名称
                $adminname = Admin::where(['id'=>$v['pay_voucher_user_id']])->first();
                $v['pay_voucher_name'] = $adminname['username'];
                //备注
                $beizhuname = Admin::where(['id'=>$v['admin_id']])->first();
                $v['remark_name'] = $beizhuname['username'];
            }
        }
        $page=[
            'pagesize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        return ['code' => 200 , 'msg' => '查询成功','data'=>$order,'where'=>$data,'page'=>$page];
    }
    /*
         * @param  分校未提交订单
         * @param  order_on   订单号
         * @param  author  苏振文
         * @param  ctime   2020/9/3 20:26
         * return  array
         */
    public static function unsubmittedOrder($data){
        //默认不传订单号   展示空页面
        $res=[];
        if(!isset($data['order_no'])){
            return ['code' => 200 , 'msg' => '获取成功','data'=>$res];
        }
        $resss = Pay_order_external::where(function($query) use ($data) {
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
        })->where(['status'=>0,'pay_status'=>1])->get()->toArray();
        $ress = Pay_order_inside::where(function($query) use ($data) {
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
        })->where(['pay_status'=>1,'del_flag'=>1])->get()->toArray();
        $res = array_merge($ress,$resss);
        if(!empty($res)){
            foreach ($res as $k=>&$v){
                if($v['pay_type'] <= 4){
                    if(!empty($v['offline_id'])){
                        $chnnel = Channel::where(['id'=>$v['offline_id']])->first();
                        if($v['pay_type'] == 1){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-微信';
                        }else if ($v['pay_type'] == 2){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-支付宝';
                        }else if ($v['pay_type'] == 3){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-汇聚-微信';
                        }else if ($v['pay_type'] == 4){
                            $v['pay_type_text'] =$chnnel['channel_name'].'-汇聚-支付宝';
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }else{
                    if(!empty($v['offline_id'])){
                        $offline = OfflinePay::where(['id'=>$v['offline_id']])->first();
                        if ($v['pay_type'] == 5){
                            $v['pay_type_text'] = '银行卡支付-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 6){
                            $v['pay_type_text'] = '对公转账-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 7){
                            $v['pay_type_text'] = '支付宝账号对公-'.$offline['account_name'];
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }

                //course  课程
                $course = Course::select('course_name')->where(['id'=>$v['course_id']])->first();
                $v['course_name'] = $course['course_name'];
                //Project  项目
                $project = Project::select('name')->where(['id'=>$v['project_id']])->first();
                $v['project_name'] = $project['name'];
                //Subject  学科
                $subject = Project::select('name')->where(['id'=>$v['subject_id']])->first();
                $v['subject_name'] = $subject['name'];
                if(!empty($res['education_id']) && $v['education_id'] != 0){
                    //查院校
                    $education = Education::select('education_name')->where(['id'=>$v['education_id']])->first();
                    $v['education_name'] = $education['education_name'];
                    //查专业
                    $major = Major::where(['id'=>$v['major_id']])->first();
                    $v['major_name'] = $major['major_name'];
                }
                if(!empty($v['pay_voucher_user_id'])){
                    $adminname = Admin::where(['id'=>$v['pay_voucher_user_id']])->first();
                    $v['pay_voucher_name'] = $adminname['username'];
                }
            }
            return ['code' => 200 , 'msg' => '获取成功','data'=>$res];
        }else{
            return ['code' => 201 , 'msg' => '无此订单'];
        }
    }
    /*
         * @param  （分校）未提交订单详情
         * @param  id   订单id
         * @param  author  苏振文
         * @param  ctime   2020/9/4 15:07
         * return  array
         */
    public static function unsubmittedOrderDetail($data){
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数有误'];
        }
        $res = Pay_order_external::where(['id'=>$data['id'],'del_flag'=>0])->first();
        if($res){
            $res['school_id'] = null;
            $res['first_pay'] = null;
            $res['pay_type'] = (int)$res['pay_type'];
            //查询分类
            //course  课程
            $course = Course::select('course_name')->where(['id'=>$res['course_id']])->first();
            $res['course_name'] = $course['course_name'];
            //Project  项目
            $project = Project::select('name')->where(['id'=>$res['project_id']])->first();
            $res['project_name'] = $project['name'];
            //Subject  学科
            $subject = Project::select('name')->where(['id'=>$res['subject_id']])->first();
            $res['subject_name'] = $subject['name'];
            if(!empty($res['education_id']) && $res['education_id'] != 0){
                //查院校
                $education = Education::select('education_name')->where(['id'=>$res['education_id']])->first();
                $res['education_name'] = $education['education_name'];
                //查专业
                $major = Major::where(['id'=>$res['major_id']])->first();
                $res['major_name'] = $major['major_name'];
            }
            return ['code' => 200 , 'msg' => '查询成功','data'=>$res];
        }else{
            return ['code' => 201, 'msg' => '查询失败'];
        }

    }
    /*
         * @param  分校未提交订单进行提交
         * @param  id   第三方订单id
         * @param  project_id    项目id
         * @param  subject_id   学科id
         * @param  course_id  课程id
         * @param  education_id  院校id
         * @param  major_id  专业id
         * @param  name   姓名
         * @param  mobile   手机号
         * @param  confirm_order_type   订单类型
         * @param  first_pay   缴费类型
         * @param  return_visit   回访状态 0未回访 1 已回访
         * @param  classes   是否开课 0不开课 1开课
         * @param  remark   订单备注
         * @param  pay_voucher   上传凭证
         * @param  course_Price   课程金额
         * @param  sign_Price   报名金额
         * @param  author  苏振文
         * @param  ctime   2020/9/4 15:06
         * return  array
         */
    public static function DoSubmitted($data){
        //将此信息加入到pay_order_inside，修改pay_order_external中订单的status
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数有误'];
        }
        if(!isset($data['project_id']) || empty($data['project_id'])){
            return ['code' => 201 , 'msg' => '未选择项目'];
        }
        if(!isset($data['subject_id']) || empty($data['subject_id'])){
            return ['code' => 201 , 'msg' => '未选择学科'];
        }
        if(!isset($data['course_id']) || empty($data['course_id'])){
            return ['code' => 201 , 'msg' => '未选择课程'];
        }
        if(!isset($data['name']) || empty($data['name'])){
            return ['code' => 201 , 'msg' => '未填写姓名'];
        }
        if(!isset($data['mobile']) || empty($data['mobile'])){
            return ['code' => 201 , 'msg' => '未填写手机号'];
        }
        if(!isset($data['confirm_order_type']) || empty($data['confirm_order_type'])){
            return ['code' => 201 , 'msg' => '未选择订单类型'];
        }
        if(!isset($data['first_pay']) || empty($data['first_pay'])){
            return ['code' => 201 , 'msg' => '未选择缴费类型'];
        }
        //获取操作员信息
        $admin = isset(AdminLog::getAdminInfo()->admin_user) ? AdminLog::getAdminInfo()->admin_user : [];
        //第三方订单数据
        $external = Pay_order_external::where(['id'=>$data['id']])->first();
        if($data['confirm_order_type'] == 2){
            if($data['sign_Price'] > $external['pay_price']){
                return ['code' => 201 , 'msg' => '所填金额大于支付金额'];
            }
        }
        if($data['confirm_order_type'] == 3){
            $ppppp = $data['course_Price'] + $data['sign_Price'];
            if($ppppp > $external['pay_price']){
                return ['code' => 201 , 'msg' => '所填金额大于支付金额'];
            }
        }
        //入库
        $insert=[
            'name' => $data['name'],//姓名
            'mobile' =>$data['mobile'],//手机号
            'order_no' => $external['order_no'],//订单编号
            'create_time' => date('Y-m-d H:i:s'),//订单创建时间
            'add_time' => $external['create_time'],//第三方生成订单时间
            'pay_time' => $external['pay_time'],//支付成功时间
            'pay_price' => $external['pay_price'],//支付金额
            'course_id' => $data['course_id'],//课程id
            'project_id' => $data['project_id'],//项目id
            'subject_id' => $data['subject_id'], //学科id
            'education_id' => isset($data['education_id'])?$data['education_id']:0, //院校id
            'major_id' => isset($data['major_id'])?$data['major_id']:0, //专业id
            'pay_status' => $external['pay_status'],//支付状态
            'pay_type' => $external['pay_type'], //支付方式（1支付宝扫码2微信扫码3银联快捷支付4微信小程序5线下录入）
            'confirm_status' => 0, //订单确认状态码
            'school_id' => $data['school_id'],  //所属分校
            'consignee_status' => 0,//0带收集 1收集中 2已收集 3重新收集
            'confirm_order_type' => $data['confirm_order_type'],//确认的订单类型 1课程订单 2报名订单3课程+报名订单
            'first_pay' => $data['first_pay'],//支付类型 1全款 2定金 3部分尾款 4最后一笔尾款
            'classes' => isset($data['classes'])?$data['classes']:0,//开课状态
            'return_visit' => isset($data['return_visit'])?$data['return_visit']:0,//回访状态
            'remark' => $data['remark'], //备注
            'pay_voucher_user_id' => $admin['id'], //上传凭证人
            'pay_voucher_time' => date('Y-m-d H:i:s'), //上传凭证时间
            'pay_voucher' => isset($data['pay_voucher'])?$data['pay_voucher']:'', //支付凭证
            'course_Price' => isset($data['course_Price'])?$data['course_Price']:0,
            'sum_Price' => $external['pay_price'],
            'sign_Price' => isset($data['sign_Price'])?$data['sign_Price']:0,
            'admin_id' => $admin['id'],
            'offline_id' => $external['offline_id']
        ];
        $add = Pay_order_inside::insert($insert);
        if($add){
            //修改第三方订单状态 修改为已提交
            Pay_order_external::where(['id'=>$data['id']])->update(['status'=>1]);
            return ['code' => 200 , 'msg' => '提交成功'];
        }else{
            return ['code' => 201 , 'msg' => '提交失败'];
        }
    }
    /*
         * @param  分校已提交订单进行取消
         * @param  id 订单id
         * @param  author  苏振文
         * @param  ctime   2020/9/4 17:50
         * return  array
         */
    public static function submittedOrderCancel($data){
        //流转订单假删   第三方订单修改状态
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数为空'];
        }
        $order = self::where(['id'=>$data['id']])->first();
        $updel = self::where(['id'=>$data['id']])->update(['del_flag'=>1]);
        if($updel){
            //修改第三方订单号
            Pay_order_external::where(['inside_no'=>$order['order_on']])->update(['status'=>0]);
            return ['code' => 200 , 'msg' => '取消成功'];
        }else{
            return ['code' => 201 , 'msg' => '取消失败'];
        }
    }
    /*
         * @param  驳回订单
         * @param  project_id  arr
         * @param  school_id  分校id
         * @param  pay_type  支付方式（1支付宝扫码2微信扫码3银联快捷支付4微信小程序5线下录入）
         * @param  confirm_order_type  确认的订单类型 1课程订单 2报名订单3课程+报名订单
         * @param  return_visit  回访状态 0未回访 1 已回访
         * @param  classes  是否开课 0不开课 1开课
         * @param  order_on  订单号/手机号/姓名
         * @param  author  苏振文
         * @param  ctime   2020/9/7 16:03
         * return  array
         */
    public static function rejectOrder($data,$schoolarr){
        $where['del_flag'] = 0;  //未删除
        $where['confirm_status'] = 2;  //已驳回
        //科目id&学科id
        if(!empty($data['project_id'])){
            $parent = json_decode($data['project_id'], true);
            if(!empty($parent[0])){
                $where['project_id'] = $parent[0];
                if(!empty($parent[1])){
                    $where['subject_id'] = $parent[1];
                }
            }
        }
        $begindata="2020-03-04";
        $enddate = date('Y-m-d');
        $statetime = !empty($data['state_time'])?$data['state_time']:$begindata;
        $endtime = !empty($data['end_time'])?$data['end_time']:$enddate;
        $state_time = $statetime." 00:00:00";
        $end_time = $endtime." 23:59:59";
        if(isset($data['pay_type']) ){
            $where['pay_type'] = $data['pay_type'];
        }
        if(isset($data['confirm_order_type']) ){
            $where['confirm_order_type'] = $data['confirm_order_type'];
        }
        if(isset($data['return_visit']) ){
            $where['return_visit'] = $data['return_visit'];
        }
        if(isset($data['classes'])){
            $where['classes'] = $data['classes'];
        }
        if(isset($data['school_id'])){
            $where['school_id'] = $data['school_id'];
        }

        //每页显示的条数
        $pagesize = (int)isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //計算總數
        $count = self::where(function($query) use ($data,$schoolarr) {
            if(isset($data['order_on']) && !empty($data['order_on'])){
                $query->where('order_no',$data['order_on'])
                    ->orwhere('name',$data['order_on'])
                    ->orwhere('mobile',$data['order_on']);
            }
            $query->whereIn('school_id',$schoolarr);
        })
        ->whereBetween('create_time', [$state_time, $end_time])
        ->where($where)
        ->where('pay_status','!=',2)
        ->count();

        $order = self::where(function($query) use ($data,$schoolarr) {
            if(isset($data['order_on']) && !empty($data['order_on'])){
                $query->where('order_no',$data['order_on'])
                    ->orwhere('name',$data['order_on'])
                    ->orwhere('mobile',$data['order_on']);
            }
            $query->whereIn('school_id',$schoolarr);
        })
        ->where($where)
        ->where('pay_status','!=',2)
        ->whereBetween('create_time', [$state_time, $end_time])
        ->orderByDesc('id')
        ->offset($offset)->limit($pagesize)->get()->toArray();
        //循环查询分类
        if(!empty($order)){
            foreach ($order as $k=>&$v){
                if($v['pay_type'] <= 4){
                    if(!empty($v['offline_id'])){
                        $chnnel = Channel::where(['id'=>$v['offline_id']])->first();
                        if($v['pay_type'] == 1){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-微信';
                        }else if ($v['pay_type'] == 2){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-支付宝';
                        }else if ($v['pay_type'] == 3){
                            $v['pay_type_text'] = $chnnel['channel_name'].'-汇聚-微信';
                        }else if ($v['pay_type'] == 4){
                            $v['pay_type_text'] =$chnnel['channel_name'].'-汇聚-支付宝';
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }else{
                    if(!empty($v['offline_id'])){
                        $offline = OfflinePay::where(['id'=>$v['offline_id']])->first();
                        if ($v['pay_type'] == 5){
                            $v['pay_type_text'] = '银行卡支付-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 6){
                            $v['pay_type_text'] = '对公转账-'.$offline['account_name'];
                        }else if ($v['pay_type'] == 7){
                            $v['pay_type_text'] = '支付宝账号对公-'.$offline['account_name'];
                        }
                    }else{
                        $v['pay_type_text']='';
                    }
                }
                if($v['pay_status'] == 1){
                    $v['pay_status_text'] = '已支付';
                }else if($v['pay_status'] == 2){
                    $v['pay_status_text'] = '支付失败';
                }else if($v['pay_status'] == 3){
                    $v['pay_status_text'] = '待审核';
                }
                if($v['return_visit'] == 0){
                    $v['return_visit_text'] = '否';
                }else{
                    $v['return_visit_text'] = '是';
                }
                if($v['classes'] == 0){
                    $v['classes_text'] = '否';
                }else{
                    $v['classes_text'] = '是';
                }
                if($v['confirm_order_type'] == 1){
                    $v['confirm_order_type_text'] = '课程订单';
                }else if($v['confirm_order_type'] == 2){
                    $v['confirm_order_type_text'] = '报名订单';
                }else if($v['confirm_order_type'] == 3){
                    $v['confirm_order_type_text'] = '课程+报名订单';
                }
                if($v['first_pay'] == 1){
                    $v['first_pay_text'] = '全款';
                }else if($v['first_pay'] == 2){
                    $v['first_pay_text'] = '定金';
                }else if($v['first_pay'] == 3){
                    $v['first_pay_text'] = '部分尾款';
                }else if($v['first_pay'] == 4){
                    $v['first_pay_text'] = '最后一笔尾款';
                }
                if($v['confirm_status'] == 0){
                    $v['confirm_status_text'] = '未确认';
                }else if($v['confirm_status'] == 1){
                    $v['confirm_status_text'] = '确认';
                }else if($v['confirm_status'] == 2){
                    $v['confirm_status_text'] = '驳回';
                }
                //查学校
                $school = School::where(['id'=>$v['school_id']])->first();
                if($school){
                    $v['school_name'] = $school['school_name'];
                }
                //course  课程
                $course = Course::select('course_name')->where(['id'=>$v['course_id']])->first();
                $v['course_name'] = $course['course_name'];
                //Project  项目
                $project = Project::select('name')->where(['id'=>$v['project_id']])->first();
                $v['project_name'] = $project['name'];
                //Subject  学科
                $subject = Project::select('name')->where(['id'=>$v['subject_id']])->first();
                $v['subject_name'] = $subject['name'];
                if(!empty($v['education_id']) && $v['education_id'] != 0){
                    //查院校
                    $education = Education::select('education_name')->where(['id'=>$v['education_id']])->first();
                    $v['education_name'] = $education['education_name'];
                    //查专业
                    $major = Major::where(['id'=>$v['major_id']])->first();
                    $v['major_name'] = $major['major_name'];
                }
                //根据上传凭证人id查询凭证名称
                $adminname = Admin::where(['id'=>$v['pay_voucher_user_id']])->first();
                $v['pay_voucher_name'] = $adminname['username'];
                //驳回人查询
                $adminreject = Admin::where(['id'=>$v['reject_admin_id']])->first();
                $v['reject_admin_name'] = $adminreject['username'];
            }
        }
        $page=[
            'pagesize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        return ['code' => 200 , 'msg' => '查询成功','data'=>$order,'where'=>$data,'page'=>$page];
    }
    /*
         * @param  被驳回订单  取消订单
         * @param  id 订单id
         * @param  author  苏振文
         * @param  ctime   2020/9/7 16:16
         * return  array
         */
    public static function anewOrder($data){
        //总校操作   status变成0 到待确认
        //分校操作   status变成0 到已提交
        if(empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数错误'];
        }
        $up = self::where(['id'=>$data['id']])->update(['confirm_status'=>0]);
        if($up){
            return ['code' => 200 , 'msg' => '操作成功'];
        }else{
            return ['code' => 201 , 'msg' => '操作失败'];
        }
    }
    /*
        * @param  驳回订单
        * @param  id 订单id
        * @param  auth 驳回原因
        * @param  author  苏振文
        * @param  ctime   2020/9/7 16:16
        * return  array
        */
    public static function DorejectOrder($data){
        if(empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数错误'];
        }
        $admin = isset(AdminLog::getAdminInfo()->admin_user) ? AdminLog::getAdminInfo()->admin_user : [];
        $redate=[
            'reject_admin_id' => $admin['id'],
            'reject_time' => date('Y-m-d H:i:s'),
            'reject_des' => $data['reject_des'],
            'confirm_status' => 2
        ];
        $up = self::where(['id'=>$data['id']])->update($redate);
        if($up){
            return ['code' => 200 , 'msg' => '操作成功'];
        }else{
            return ['code' => 201 , 'msg' => '操作失败'];
        }
    }
    /*
         * @param  分校被驳回订单重新提交
         * @param  id   流转订单id
         * @param  project_id    项目id
         * @param  subject_id   学科id
         * @param  course_id  课程id
         * @param  education_id  院校id
         * @param  major_id  专业id
         * @param  name   姓名
         * @param  mobile   手机号
         * @param  confirm_order_type   订单类型
         * @param  first_pay   缴费类型
         * @param  return_visit   回访状态 0未回访 1 已回访
         * @param  classes   是否开课 0不开课 1开课
         * @param  remark   订单备注
         * @param  pay_voucher   上传凭证
         * @param  course_Price   课程金额
         * @param  sign_Price   报名金额
         * @param  author  苏振文
         * @param  ctime   2020/9/4 15:06
         * return  array
         */
    public static function branchsubmittedOrderCancel($data){
        unset($data['/admin/order/branchsubmittedOrderCancel']);
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数有误'];
        }
        if(!isset($data['project_id']) || empty($data['project_id'])){
            return ['code' => 201 , 'msg' => '未选择项目'];
        }
        if(!isset($data['subject_id']) || empty($data['subject_id'])){
            return ['code' => 201 , 'msg' => '未选择学科'];
        }
        if(!isset($data['course_id']) || empty($data['course_id'])){
            return ['code' => 201 , 'msg' => '未选择课程'];
        }
        if(!isset($data['name']) || empty($data['name'])){
            return ['code' => 201 , 'msg' => '未填写姓名'];
        }
        if(!isset($data['mobile']) || empty($data['mobile'])){
            return ['code' => 201 , 'msg' => '未填写手机号'];
        }
        if(!isset($data['confirm_order_type']) || empty($data['confirm_order_type'])){
            return ['code' => 201 , 'msg' => '未选择订单类型'];
        }
        if(!isset($data['first_pay']) || empty($data['first_pay'])){
            return ['code' => 201 , 'msg' => '未选择缴费类型'];
        }
        $data['confirm_status'] = 0;
        $data['update_time'] = date('Y-m-d H:i:s');
        //获取操作员信息
        $up = Pay_order_inside::where(['id'=>$data['id']])->update($data);
        if($up){
            return ['code' => 200 , 'msg' => '提交成功'];
        }else{
            return ['code' => 201 , 'msg' => '提交失败'];
        }
    }
    /*
         * @param  核对订单
         * @param  school_id  学校id
         * @param  project_id 项目id arr
         * @param  pay_type 支付方式 1微信2支付宝3汇聚微信4汇聚支付宝5银行卡支付6对公转账7支付宝账号对公
         * @param  pay_status 核对状态   3未审核
         * @param  confirm_order_type 订单类型  1课程订单 2报名订单3课程+报名订单
         * @param  first_pay 缴费类型  1全款 2定金 3部分尾款 4最后一笔尾款
         * @param  state_time 开始时间
         * @param  end_time 结束时间
         * @param  order_no 订单号/姓名/手机号
         * @param  author  苏振文
         * @param  ctime   2020/9/22 11:15
         * return  array
         */
    public static function auditOrder($data){
        $where['del_flag']=0;
        $where['confirm_status']=0;
        //判断时间
        $begindata="2020-03-04";
        $enddate = date('Y-m-d');
        $statetime = !empty($data['state_time'])?$data['state_time']:$begindata;
        $endtime = !empty($data['end_time'])?$data['end_time']:$enddate;
        $state_time = $statetime." 00:00:00";
        $end_time = $endtime." 23:59:59";
        //学校id
        if(isset($data['school_id'])){
            $where['school_id'] = $data['school_id'];
        }
        //支付方式
        if(!empty($data['pay_type'])){
            $where['pay_type'] = $data['pay_type'];
        }
        //订单类型
        if(isset($data['confirm_order_type'])){
            $where['confirm_order_type'] = $data['confirm_order_type'];
        }
        //缴费
        if(isset($data['first_pay'])){
            $where['first_pay'] = $data['first_pay'];
        }
        //科目id&学科id
        if(!empty($data['project_id'])){
            $parent = json_decode($data['project_id'], true);
            if(!empty($parent[0])){
                $where['project_id'] = $parent[0];
                if(!empty($parent[1])){
                    $where['subject_id'] = $parent[1];
                }
            }
        }
        //每页显示的条数
        $pagesize = (int)isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        $count = self::where(function($query) use ($data) {
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
            if(isset($data['pay_status'])){
              $query->where('pay_status',$data['pay_status']);
            }
        })
        ->where($where)
        ->where(['is_handorder'=>1])
        ->whereBetween('create_time', [$state_time, $end_time])
        ->count();
        //数据   流转订单
        $order = self::where(function($query) use ($data) {
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
            if(isset($data['pay_status'])){
                $query->where('pay_status',$data['pay_status']);
            }
        })
        ->where($where)
        ->where(['is_handorder'=>1])
        ->whereBetween('create_time', [$state_time, $end_time])
        ->orderByDesc('id')
        ->offset($offset)->limit($pagesize)
        ->get()->toArray();
        foreach ($order as $k=>&$v){
                //查学校
                if(empty($v['school_id']) || $v['school_id'] == 0){
                    $v['school_name'] = '';
                }else{
                    $school = School::where(['id'=>$v['school_id']])->first();
                    if($school){
                        $v['school_name'] = $school['school_name'];
                    }
                }
            if($v['pay_type'] <= 4){
                if(!empty($v['offline_id'])){
                    $chnnel = Channel::where(['id'=>$v['offline_id']])->first();
                    if($v['pay_type'] == 1){
                        $v['pay_type_text'] = $chnnel['channel_name'].'-微信';
                    }else if ($v['pay_type'] == 2){
                        $v['pay_type_text'] = $chnnel['channel_name'].'-支付宝';
                    }else if ($v['pay_type'] == 3){
                        $v['pay_type_text'] = $chnnel['channel_name'].'-汇聚-微信';
                    }else if ($v['pay_type'] == 4){
                        $v['pay_type_text'] =$chnnel['channel_name'].'-汇聚-支付宝';
                    }
                }else{
                    $v['pay_type_text']='';
                }
            }else{
                if(!empty($v['offline_id'])){
                    $offline = OfflinePay::where(['id'=>$v['offline_id']])->first();
                    if ($v['pay_type'] == 5){
                        $v['pay_type_text'] = '银行卡支付-'.$offline['account_name'];
                    }else if ($v['pay_type'] == 6){
                        $v['pay_type_text'] = '对公转账-'.$offline['account_name'];
                    }else if ($v['pay_type'] == 7){
                        $v['pay_type_text'] = '支付宝账号对公-'.$offline['account_name'];
                    }
                }else{
                    $v['pay_type_text']='';
                }
            }
                if($v['pay_status'] == 0){
                    $v['pay_status_text'] = '未支付';
                }else if($v['pay_status'] == 1){
                    $v['pay_status_text'] = '已支付';
                }else if($v['pay_status'] == 2){
                    $v['pay_status_text'] = '支付失败';
                }else if($v['pay_status'] == 3){
                    $v['pay_status_text'] = '待审核';
                }
                if(!isset($v['return_visit'])){
                    $v['return_visit_text'] = '';
                }else{
                    if($v['return_visit'] == 0){
                        $v['return_visit_text'] = '否';
                    }else{
                        $v['return_visit_text'] = '是';
                    }
                }
                if(!isset($v['classes'])){
                    $v['classes_text'] = '';
                }else{
                    if( $v['classes'] == 0){
                        $v['classes_text'] = '否';
                    }else{
                        $v['classes_text'] = '是';
                    }
                }
                if(empty($v['confirm_order_type'])){
                    $v['confirm_order_type_text'] = '';
                }else{
                    if($v['confirm_order_type'] == 1){
                        $v['confirm_order_type_text'] = '课程订单';
                    }else if($v['confirm_order_type'] == 2){
                        $v['confirm_order_type_text'] = '报名订单';
                    }else if($v['confirm_order_type'] == 3){
                        $v['confirm_order_type_text'] = '课程+报名订单';
                    }
                }

                if(empty($v['first_pay'])){
                    $v['first_pay_text'] = '';
                }else{
                    if($v['first_pay'] == 1){
                        $v['first_pay_text'] = '全款';
                    }else if($v['first_pay'] == 2){
                        $v['first_pay_text'] = '定金';
                    }else if($v['first_pay'] == 3){
                        $v['first_pay_text'] = '部分尾款';
                    }else if($v['first_pay'] == 4){
                        $v['first_pay_text'] = '最后一笔尾款';
                    }
                }
                if(empty($v['confirm_status'])){
                    $v['confirm_status_text'] = '';
                }else{
                    if($v['confirm_status'] == 0){
                        $v['confirm_status_text'] = '未确认';
                    }else if($v['confirm_status'] == 1){
                        $v['confirm_status_text'] = '确认';
                    }else if($v['confirm_status'] == 2){
                        $v['confirm_status_text'] = '驳回';
                    }
                }
                //course  课程
                $course = Course::select('course_name')->where(['id'=>$v['course_id']])->first();
                $v['course_name'] = $course['course_name'];
                //Project  项目
                $project = Project::select('name')->where(['id'=>$v['project_id']])->first();
                $v['project_name'] = $project['name'];
                //Subject  学科
                $subject = Project::select('name')->where(['id'=>$v['subject_id']])->first();
                $v['subject_name'] = $subject['name'];
                if(!empty($v['education_id']) && $v['education_id'] != 0){
                    //查院校
                    $education = Education::select('education_name')->where(['id'=>$v['education_id']])->first();
                    $v['education_name'] = $education['education_name'];
                    //查专业
                    $major = Major::where(['id'=>$v['major_id']])->first();
                    $v['major_name'] = $major['major_name'];
                }
                $pay_voucher_name = Admin::where(['id'=>$v['pay_voucher_user_id']])->first();
                $v['pay_voucher_name'] = $pay_voucher_name['username'];
        }
        $page=[
            'pagesize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        return ['code' => 200 , 'msg' => '查询成功','data'=>$order,'where'=>$data,'page'=>$page];
    }
    /*
         * @param 根据类型查账户
         * @param  type  类型
         * @param  author  苏振文
         * @param  ctime   2020/9/22 14:47
         * return  array
         */
    public static function offlinepay($data){
        if($data['pay_type'] == 5){ //银行卡支付
            $type = 2;
        }
        if($data['pay_type'] == 6){ //对公转账
            $type = 1;
        }
        if($data['pay_type'] == 7){ //支付宝账号对公
            $type = 3;
        }
        $list = OfflinePay::where(['type'=>$type,'is_show'=>1,'is_del'=>1])->get()->toArray();
        return ['code' => 200 , 'msg' => '查询成功','data'=>$list];
    }
    /*
         * @param  进行审核
         * @param  id  订单id
         * @param  offline_id 收款账号id
         * @param  pay_status 支付类型
         * @param  author  苏振文
         * @param  ctime   2020/9/22 15:03
         * return  array
         */
    public static function offlineing($data){
        if(!isset($data['id']) || empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数错误'];
        }
        //财务进行驳回
        if($data['pay_status'] == 4){
            if(!isset($data['reject_des']) || empty($data['reject_des'])){
                return ['code' => 201 , 'msg' => '请填写驳回原因'];
            }
            $admin = isset(AdminLog::getAdminInfo()->admin_user) ? AdminLog::getAdminInfo()->admin_user : [];
            $ups=[
                'confirm_status'=>2,
                'offline_id'=>isset($data['offline_id'])?$data['offline_id']:'',
                'reject_time'=>date('Y-m-d H:i:s'),
                'reject_des'=>$data['reject_des'],
                'reject_admin_id'=>$admin['id'],
            ];
            $ups['update_time'] = date('Y-m-d H:i:s');
            $up = Pay_order_inside::where(['id'=>$data['id']])->update($ups);
            if($up){
                return ['code' => 200 , 'msg' => '成功'];
            }else{
                return ['code' => 201 , 'msg' => '失败'];
            }
        }else{
            if(!isset($data['offline_id']) || empty($data['offline_id'])){
                return ['code' => 201 , 'msg' => '请选择收款账号'];
            }
            if(!isset($data['pay_status'])){
                return ['code' => 201 , 'msg' => '请判断类型'];
            }
            unset($data['/admin/order/offlineing']);
            $data['update_time'] = date('Y-m-d H:i:s');
            $up = Pay_order_inside::where(['id'=>$data['id']])->update($data);
            if($up){
                return ['code' => 200 , 'msg' => '成功'];
            }else{
                return ['code' => 201 , 'msg' => '失败'];
            }
        }
    }

    /*------------------------------------------------------------------------------------------------------------------------*/
    /*
     * @param  description   开课管理列表接口
     * @param  参数说明       body包含以下参数[
     *     category_id       项目-学科大小类(例如:[1,2])
     *     school_id         分校id
     *     order_type        订单类型(1.课程订单2.报名订单3.课程+报名订单)
     *     status            开课状态(0不开课 1开课)
     *     keywords          订单号/手机号/姓名
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function getOpenCourseList($body=[]) {
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取开课管理的总数量
        $open_class_count = StudentCourse::where(function($query) use ($body){
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

            //判断分校id是否为空和合法
            if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                $query->where('school_id' , '=' , $body['school_id']);
            }

            //判断订单类型是否为空和合法
            if(isset($body['order_type']) && !empty($body['order_type']) && in_array($body['order_type'] , [1,2,3])){
                $query->where('order_type' , '=' , $body['order_type']);
            }

            //判断开课状态是否为空和合法
            if(isset($body['status']) && !empty($body['status']) && in_array($body['status'] , [0,1])){
                $query->where('status' , '=' , $body['status']);
            }
        })->where(function($query) use ($body){
            //判断订单号/手机号/姓名是否为空
            if(isset($body['keywords']) && !empty($body['keywords'])){
                $query->where('name','like','%'.$body['keywords'].'%')->orWhere('phone','like','%'.$body['keywords'].'%')->orWhere('order_no','like','%'.$body['keywords'].'%');
            }
        })->count();

        if($open_class_count > 0){
            //新数组赋值
            $order_array = [];

            //获取开课列表
            $open_class_list = StudentCourse::select('id as open_id' , 'create_time' , 'phone' , 'student_name' , 'course_id' , 'project_id' , 'subject_id' , 'school_id' , 'status','open_time')->where(function($query) use ($body){
                //判断项目-学科大小类是否为空
                if(isset($body['category_id']) && !empty($body['category_id'])){
                    $category_id= json_decode($body['category_id'] , true);
                    $project_id = $category_id[0];
                    $subject_id = $category_id[1];

                    //判断项目id是否传递
                    if($project_id && $project_id > 0){
                        $query->where('project_id' , '=' , $project_id);
                    }

                    //判断学科id是否传递
                    if($subject_id && $subject_id > 0){
                        $query->where('subject_id' , '=' , $subject_id);
                    }
                }

                //判断分校id是否为空和合法
                if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                    $query->where('school_id' , '=' , $body['school_id']);
                }

                //判断订单类型是否为空和合法
                if(isset($body['order_type']) && !empty($body['order_type']) && in_array($body['order_type'] , [1,2,3])){
                    $query->where('order_type' , '=' , $body['order_type']);
                }

                //判断开课状态是否为空和合法
                if(isset($body['status']) && !empty($body['status']) && in_array($body['status'] , [0,1])){
                    $query->where('status' , '=' , $body['status']);
                }
            })->where(function($query) use ($body){
                //判断订单号/手机号/姓名是否为空
                if(isset($body['keywords']) && !empty($body['keywords'])){
                    $query->where('name','like','%'.$body['keywords'].'%')->orWhere('mobile','like','%'.$body['keywords'].'%')->orWhere('order_no','like','%'.$body['keywords'].'%');
                }
            })->orderByDesc('create_time')->offset($offset)->limit($pagesize)->get()->toArray();

            //循环获取相关信息
            foreach($open_class_list as $k=>$v){
                //项目名称
                $project_name = Project::where('id' , $v['project_id'])->value('name');

                //学科名称
                $subject_name = Project::where('parent_id' , $v['project_id'])->where('id' , $v['subject_id'])->value('name');

                //课程名称
                $course_name  = Course::where('id' , $v['course_id'])->value('course_name');

                //分校的名称
                $school_name  = School::where('id' , $v['school_id'])->value('school_name');

                //开课数组管理赋值
                $order_array[] = [
                    'open_id'       =>  $v['open_id'] ,
                    'create_time'   =>  $v['create_time'] ,
                    'phone'         =>  $v['phone'] ,
                    'student_name'  =>  $v['student_name'] ,
                    'status'        =>  (int)$v['status'] ,
                    'open_time'       =>  $v['open_time'] ,
                    'status_name'   =>  $v['status'] > 0 ? '已开课' : '未开课' ,
                    'project_name'  =>  $project_name && !empty($project_name) ? $project_name : '' ,
                    'subject_name'  =>  $subject_name && !empty($subject_name) ? $subject_name : '' ,
                    'course_name'   =>  $course_name  && !empty($course_name)  ? $course_name  : '' ,
                    'school_name'   =>  $school_name  && !empty($school_name)  ? $school_name  : ''
                ];
            }
            return ['code' => 200 , 'msg' => '获取开课列表成功' , 'data' => ['open_class_list' => $order_array , 'total' => $open_class_count , 'pagesize' => $pagesize , 'page' => $page]];
        }
        return ['code' => 200 , 'msg' => '获取开课列表成功' , 'data' => ['open_class_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
    }

    /*
     * @param  description   开课管理订单详情接口
     * @param  参数说明       body包含以下参数[
     *       open_id         开课得管理id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-08
     * return string
     */
    public static function getOpenCourseInfo($body=[]) {
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //判断开课得管理id是否合法
        if(!isset($body['open_id']) || empty($body['open_id']) || $body['open_id'] <= 0){
            return ['code' => 202 , 'msg' => '开课得管理id不合法'];
        }

        //根据开课管理得id获取信息
        $info = StudentCourse::where('id' , $body['open_id'])->first();
        if(!$info || empty($info)){
            return ['code' => 203 , 'msg' => '此开课管理信息不存在'];
        }

        //学员名称
        $name   = $info['student_name'];
        //手机号
        $mobile = $info['phone'];
        //所属分校
        $school_id = $info['school_id'];
        //项目
        $project_id= $info['project_id'];
        //学科
        $subject_id= $info['subject_id'];
        //课程
        $course_id = $info['course_id'];

        //获取订单的总数量
        $order_count = self::where('name' , $name)->where('mobile' , $mobile)->where('school_id' , $school_id)->where('project_id' , $project_id)->where('subject_id' , $subject_id)->where('course_id' , $course_id)->where('del_flag' , 0)->count();

        //支付方式数组
        $pay_type_array = [1=>'微信扫码',2=>'支付宝扫码',3=>'微信扫码',4=>'支付宝扫码',5=>'银行卡支付',6=>'对公转账',7=>'支付宝账号对公'];

        //支付状态数组
        $pay_status_array = [0=>'未支付',1=>'已支付',2=>'支付失败',3=>'已退款'];

        //回访数组
        $return_visit_array = [0=>'否',1=>'是'];

        //开课数组
        $classes_array      = [0=>'否',1=>'是'];

        //订单类型数组
        $order_type_array   = [1=>'课程订单',2=>'报名订单',3=>'课程+报名订单'];

        //订单状态数组
        $order_status_array = [0=>'未确认',1=>'已确认',2=>'已驳回'];

        //缴费类型数组
        $first_pay_array    = [1=>'全款',2=>'定金',3=>'部分尾款',4=>'最后一笔尾款'];

        //判断订单总数量是否大于0
        if($order_count > 0){
            //新数组赋值
            $order_array = [];

            //获取订单列表
            $order_list = self::select('order_no' , 'create_time' , 'mobile' , 'name' , 'course_id' , 'project_id' , 'subject_id' , 'school_id' , 'pay_type' , 'course_Price' , 'sign_Price' , 'sum_Price' , 'pay_status' , 'classes' , 'return_visit' , 'pay_time' , 'confirm_order_type' , 'first_pay' , 'confirm_status' , 'pay_voucher')->where('name' , $name)->where('mobile' , $mobile)->where('school_id' , $school_id)->where('project_id' , $project_id)->where('subject_id' , $subject_id)->where('course_id' , $course_id)->where('del_flag' , 0)->orderByDesc('create_time')->offset($offset)->limit($pagesize)->get()->toArray();

            //循环获取相关信息
            foreach($order_list as $k=>$v){
                //分校的名称
                $school_name  = School::where('id' , $v['school_id'])->value('school_name');

                //项目名称
                $project_name = Project::where('id' , $v['project_id'])->value('name');

                //学科名称
                $subject_name = Project::where('parent_id' , $v['project_id'])->where('id' , $v['subject_id'])->value('name');

                //课程名称
                $course_name  = Course::where('id' , $v['course_id'])->value('course_name');

                //新数组信息赋值
                $order_array[] = [
                    'order_no'           =>  $v['order_no'] && !empty($v['order_no']) ? $v['order_no'] : '-' ,
                    'create_time'        =>  $v['create_time'] && !empty($v['create_time']) ? $v['create_time'] : '-' ,
                    'name'               =>  $v['name'] && !empty($v['name']) ? $v['name'] : '-' ,
                    'mobile'             =>  $v['mobile'] && !empty($v['mobile']) ? $v['mobile'] : '-' ,
                    'school_name'        =>  $school_name  && !empty($school_name)  ? $school_name  : '-' ,
                    'project_name'       =>  $project_name && !empty($project_name) ? $project_name : '-' ,
                    'subject_name'       =>  $subject_name && !empty($subject_name) ? $subject_name : '-' ,
                    'course_name'        =>  $course_name  && !empty($course_name)  ? $course_name  : '-' ,
                    'pay_type'           =>  $v['pay_type'] > 0 && isset($pay_type_array[$v['pay_type']]) ? $pay_type_array[$v['pay_type']] : '-' ,
                    'course_price'       =>  !empty($v['course_Price']) && $v['course_Price'] > 0 ? $v['course_Price'] : '-' ,
                    'sign_price'         =>  !empty($v['sign_Price']) && $v['sign_Price'] > 0 ? $v['sign_Price'] : '-' ,
                    'sum_price'          =>  !empty($v['sum_Price']) && $v['sum_Price'] > 0 ? $v['sum_Price'] : '-' ,
                    'pay_status'         =>  $v['pay_status'] > 0 && isset($pay_status_array[$v['pay_status']]) ? $pay_status_array[$v['pay_status']] : '-' ,
                    'return_visit'       =>  $v['return_visit'] ,
                    'return_visit_name'  =>  $v['return_visit'] > 0 && isset($return_visit_array[$v['return_visit']]) ? $return_visit_array[$v['return_visit']] : '-' ,
                    'classes'            =>  (int)$v['classes'] ,
                    'classes_name'       =>  $v['classes'] > 0 && isset($classes_array[$v['classes']]) ? $classes_array[$v['classes']] : '-' ,
                    'pay_time'           =>  $v['pay_time'] && !empty($v['pay_time']) ? $v['pay_time'] : '-' ,
                    'order_type'         =>  $v['confirm_order_type'] > 0 && isset($order_type_array[$v['confirm_order_type']]) ? $order_type_array[$v['confirm_order_type']] : '-' ,
                    'first_pay'          =>  $v['first_pay'] > 0 && isset($first_pay_array[$v['first_pay']]) ? $first_pay_array[$v['first_pay']] : '-' ,
                    'pay_voucher_name'   =>  $v['pay_voucher'] && !empty($v['pay_voucher']) ? '已上传' : '未上传' ,
                    'pay_voucher'        =>  $v['pay_voucher'] && !empty($v['pay_voucher']) ? 1 : 0 ,
                    'order_status_name'  =>  $v['confirm_status'] > 0 && isset($order_status_array[$v['confirm_status']]) ? $order_status_array[$v['confirm_status']] : '-' ,
                    'order_status'       =>  $v['confirm_status']
                ];
            }
            return ['code' => 200 , 'msg' => '获取订单详情成功' , 'data' => ['order_list' => $order_array , 'total' => $order_count , 'pagesize' => $pagesize , 'page' => $page]];
        }
        return ['code' => 200 , 'msg' => '获取订单详情成功' , 'data' => ['order_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
    }

    /*
     * @param  description   开课管理-确认开课方法
     * @param  参数说明       body包含以下参数[
     *     open_id           开课id
     *     project_id        项目id
     *     subject_id        学科id
     *     course_id         课程id
     *     student_name      学员名称
     *     phone             手机号
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function doMakeSureOpenCourse($body=[]) {
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断开课id是否合法
        if(!isset($body['open_id']) || empty($body['open_id']) || $body['open_id'] <= 0){
            return ['code' => 202 , 'msg' => '开课id不合法'];
        }

        //根据开课管理得id获取信息
        $info = StudentCourse::where('id' , $body['open_id'])->first();
        if(!$info || empty($info)){
            return ['code' => 203 , 'msg' => '此开课管理信息不存在'];
        }

        //判断项目id是否合法
        if(!isset($body['project_id']) || empty($body['project_id']) || $body['project_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }

        //判断学科id是否合法
        if(!isset($body['subject_id']) || empty($body['subject_id']) || $body['subject_id'] <= 0){
            return ['code' => 202 , 'msg' => '学科id不合法'];
        }

        //判断课程id是否合法
        if(!isset($body['course_id']) || empty($body['course_id']) || $body['course_id'] <= 0){
            return ['code' => 202 , 'msg' => '课程id不合法'];
        }

        //判断学员名称是否合法
        if(!isset($body['student_name']) || empty($body['student_name'])){
            return ['code' => 202 , 'msg' => '学员名称为空'];
        }

        //判断学员手机号是否合法
        if(!isset($body['phone']) || empty($body['phone'])){
            return ['code' => 202 , 'msg' => '手机号为空'];
        }

        //判断此开课记录是否存在
        $info = self::where('id' , $body['open_id'])->first();
        if(!$info || empty($info)){
            return ['code' => 203 , 'msg' => '此开课记录不存在'];
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //获取当前开课记录的状态
        $status = $info['status'] > 0 ? 0 : 1;

        //判断是否是取消状态
        if($status == 1){
            //封装成数组
            $array = [
                'student_name'   =>   $body['student_name'] ,
                'phone'          =>   $body['phone'] ,
                'project_id'     =>   $body['project_id'] ,
                'subject_id'     =>   $body['subject_id'] ,
                'course_id'      =>   $body['course_id'] ,
                'status'         =>   1 ,
                'create_id'      =>   $admin_id ,
                'open_time'      =>   date('Y-m-d H:i:s') ,
                'update_time'    =>   date('Y-m-d H:i:s')
            ];
        } else {
            //封装成数组
            $array = [
                'student_name'   =>   $body['student_name'] ,
                'phone'          =>   $body['phone'] ,
                'project_id'     =>   $body['project_id'] ,
                'subject_id'     =>   $body['subject_id'] ,
                'course_id'      =>   $body['course_id'] ,
                'status'         =>   0 ,
                'create_id'      =>   $admin_id ,
                'update_time'    =>   date('Y-m-d H:i:s')
            ];
        }

        //开启事务
        DB::beginTransaction();

        //根据开课id更新信息
        if(false !== StudentCourse::where('id',$body['open_id'])->update($array)){
            //更新学员开课状态
            //self::where('id',$info['order_id'])->update(['classes' => $status]);
            //事务提交
            DB::commit();
            return ['code' => 200 , 'msg' => '更新成功'];
        } else {
            //事务回滚
            DB::rollBack();
            return ['code' => 203 , 'msg' => '更新失败'];
        }
    }

    /*
     * @param  description   确认开课详情接口
     * @param  参数说明       body包含以下参数[
     *       open_id         开课得管理id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-08
     * return string
     */
    public static function getStudentCourseInfoById($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断开课id是否合法
        if(!isset($body['open_id']) || empty($body['open_id']) || $body['open_id'] <= 0){
            return ['code' => 202 , 'msg' => '开课id不合法'];
        }

        //根据开课管理得id获取信息
        $info = StudentCourse::select('student_name' , 'phone' , 'project_id' , 'subject_id' , 'course_id')->where('id' , $body['open_id'])->first();
        if(!$info || empty($info)){
            return ['code' => 203 , 'msg' => '此开课管理信息不存在'];
        } else {
            return ['code' => 200 , 'msg' => '获取详情成功' , 'data' => $info];
        }
    }


    /*
     * @param  description   财务管理-收入详情
     * @param  参数说明       body包含以下参数[
     *     education_id      院校id
     *     project_id        项目id
     *     subject_id        学科id
     *     course_id         课程id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-18
     * return string
     */
    public static function getIncomeeList($body=[]) {
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取收入详情的总数量
        $count = self::where(function($query) use ($body){
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
            if(isset($body['create_time']) && !empty($body['create_time'])){
                $create_time = json_decode($body['create_time'] , true);
                $state_time  = $create_time[0]." 00:00:00";
                $end_time    = $create_time[1]." 23:59:59";
                $query->whereBetween('create_time', [$state_time, $end_time]);
            }
        })->whereIn('education_id' , $body['schoolId'])->where('del_flag' , 0)->count();

        if($count > 0){
            //新数组赋值
            $array = [];

            //获取收入详情列表
            $list = self::where(function($query) use ($body){
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
                if(isset($body['create_time']) && !empty($body['create_time'])){
                    $create_time = json_decode($body['create_time'] , true);
                    $state_time  = $create_time[0]." 00:00:00";
                    $end_time    = $create_time[1]." 23:59:59";
                    $query->whereBetween('create_time', [$state_time, $end_time]);
                }
            })->whereIn('education_id' , $body['schoolId'])->where('del_flag' , 0)->orderByDesc('create_time')->offset($offset)->limit($pagesize)->get()->toArray();

            //循环获取相关信息
            foreach($list as $k=>$v){
                //项目名称
                $project_name = Project::where('id' , $v['project_id'])->value('name');

                //学科名称
                $subject_name = Project::where('parent_id' , $v['project_id'])->where('id' , $v['subject_id'])->value('name');

                //课程名称
                $course_name  = Course::where('id' , $v['course_id'])->value('course_name');

                //分校的名称
                $school_name  = School::where('id' , $v['school_id'])->value('school_name');

                //数组赋值
                $array[] = [
                    'create_time'   =>  date('Ymd' ,strtotime($v['create_time'])) ,
                    'school_name'   =>  $school_name  && !empty($school_name)  ? $school_name  : '' ,
                    'project_name'  =>  $project_name && !empty($project_name) ? $project_name : '' ,
                    'subject_name'  =>  $subject_name && !empty($subject_name) ? $subject_name : '' ,
                    'course_name'   =>  $course_name  && !empty($course_name)  ? $course_name  : '' ,
                    'received_order'=>  0 ,  //到账订单数量
                    'refund_order'  =>  0 ,  //退费订单数量
                    'received_money'=>  0 ,  //到账金额
                    'refund_money'  =>  0 ,  //退费金额
                    'enroll_price'  =>  $v['sign_Price'] ,  //报名费用
                    'prime_cost'    =>  $v['sum_Price']  ,  //成本
                    'return_commission_amount' => $v['return_commission_amount'] ,  //返佣金额(实际佣金)
                ];
            }
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => $array , 'total' => $count , 'pagesize' => $pagesize , 'page' => $page]];
        }
        return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
    }

    /*
     * @param  description   财务管理-分校收入详情
     * @param  参数说明       body包含以下参数[
     *     education_id      院校id
     *     project_id        项目id
     *     subject_id        学科id
     *     course_id         课程id
     *     search_time       搜索时间(例如:2020-09-01至2020-09-20)
     * ]
     * @param author    dzj
     * @param ctime     2020-09-21
     * return string
     */
    public static function getBranchSchoolIncomeeList($body=[]) {
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取收入详情的总数量
        $count = self::selectRaw("count(date_format(create_time , '%Y%m%d')) as t_count")->where(function($query) use ($body){
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
            $list = self::selectRaw("any_value(project_id) as project_id , any_value(subject_id) as subject_id , any_value(course_id) as course_id , any_value(school_id) as school_id , any_value(create_time) as create_time")->where(function($query) use ($body){
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
                $received_order = self::where(function($query) use ($body){
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
                })->where('pay_status'  ,1)->where('confirm_status' , 1)->count();

                //到账金额
                $received_money = self::where(function($query) use ($body){
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
                })->where('pay_status' , 1)->where('confirm_status' , 1)->sum('pay_price');

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
                $enroll_price   = self::where(function($query) use ($body){
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
                $prime_cost     = self::where(function($query) use ($body){
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
                $actual_commission = self::where(function($query) use ($body){
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

                //分校支出=退费金额+报名费用+成本
                $campus_expenditure = $refund_money+$enroll_price+$prime_cost;

                //实际收入=到账金额-退费金额
                $real_income  = $received_money-$refund_money > 0 ? $received_money-$refund_money : 0;

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
                    'campus_expenditure'=> (float)number_format($campus_expenditure ,2) , //分校支出
                    'real_income'       => (float)number_format($real_income ,2) ,  //实际收入
                    'actual_commission' => $actual_commission > 0 ? floatval($actual_commission) : 0 ,  //实际佣金
                ];
            }
            //总和数组
            $the_sum = [
                'create_time'   =>   '总和' ,
                'school_name'   =>   $array[0]['school_name'] ,
                'project_name'  =>   $array[0]['project_name'] ,
                'subject_name'  =>   $array[0]['subject_name'] ,
                'course_name'   =>   $array[0]['course_name'] ,
                'received_order'=>   array_sum(array_column($array , 'received_order')) ,
                'refund_order'  =>   array_sum(array_column($array , 'refund_order')) ,
                'received_money'=>  sprintf("%.2f" , array_sum(array_column($array , 'received_money'))) ,  //到账金额
                'refund_money'  =>  sprintf("%.2f" , array_sum(array_column($array , 'refund_money'))) ,      //退费金额
                'enroll_price'  =>  sprintf("%.2f" , array_sum(array_column($array , 'enroll_price'))) ,      //报名费用
                'prime_cost'    =>  sprintf("%.2f" , array_sum(array_column($array , 'prime_cost'))) ,          //成本
                'campus_expenditure'=> sprintf("%.2f" , array_sum(array_column($array , 'campus_expenditure'))) , //分校支出
                'real_income'       => sprintf("%.2f" , array_sum(array_column($array , 'real_income'))) ,  //实际收入
                'actual_commission' => sprintf("%.2f" , array_sum(array_column($array , 'actual_commission'))) ,  //实际佣金
            ];
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => $array , 'the_sum' => $the_sum , 'total' => $count , 'pagesize' => $pagesize , 'page' => $page]];
        }
        return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => [] , 'the_sum' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
    }

    /*
     * @param  description   财务管理-分校收入详情-已确认订单
     * @param  参数说明       body包含以下参数[
     *     school_id         分校id
     *     order_time        订单时间
     * ]
     * @param author    dzj
     * @param ctime     2020-09-21
     * return string
     */
    public static function getBranchSchoolConfirmOrderList($body=[]) {
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取日期
        if(!isset($body['create_time']) || empty($body['create_time'])){
            return ['code' => 201 , 'msg' => '明细日期不能为空'];
        }

        //获取收入详情的总数量
        $count = self::where(function($query) use ($body){
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
        $pay_type_array = [1=>'微信',2=>'支付宝',3=>'微信',4=>'支付宝',5=>'银行卡转账',6=>'对公转账',7=>'支付宝账号对公转账'];

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
            $list = self::select("order_no","create_time","name","mobile","have_user_id","school_id","project_id","subject_id","course_id","pay_type","course_Price","sign_Price","return_visit","classes","pay_time","confirm_order_type")->where(function($query) use ($body){
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
                    'course_price'  =>  $v['course_Price'] && $v['course_Price'] > 0 ? (float)$v['course_Price'] : 0 ,  //课程金额
                    'sign_price'    =>  $v['sign_Price'] && $v['sign_Price'] > 0 ? (float)$v['sign_Price'] : 0 ,        //报名金额
                    'sum_money'     =>  $v['course_Price'] + $v['sign_Price'] ,  //总金额
                    'return_visit'  =>  isset($return_visit_array[$v['return_visit']]) && !empty($return_visit_array[$v['return_visit']]) ? $return_visit_array[$v['return_visit']] : '-' , //是否回访
                    'classes'       =>  isset($classes_array[$v['classes']]) && !empty($classes_array[$v['classes']]) ? $classes_array[$v['classes']] : '-' , //是否开课
                    'pay_time'      =>  $v['pay_time'] && !empty($v['pay_time']) ? $v['pay_time'] : '-'  ,   //支付成功时间
                    'order_type'    =>  isset($order_type_array[$v['confirm_order_type']]) && !empty($order_type_array[$v['confirm_order_type']]) ? $order_type_array[$v['confirm_order_type']] : '-'   //订单类型
                ];
            }
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => $array , 'total' => $count , 'pagesize' => $pagesize , 'page' => $page]];
        }
        return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
    }

    /*
     * @param  description   财务管理-分校订单明细公共接口
     * @param  参数说明       body包含以下参数[
     *     school_id         分校id
     *     order_time        订单时间
     * ]
     * @param author    dzj
     * @param ctime     2020-09-21
     * return string
     */
    public static function getBranchSchoolOrderInfo($body=[]){
        //获取日期
        if(!isset($body['create_time']) || empty($body['create_time'])){
            return ['code' => 201 , 'msg' => '明细日期不能为空'];
        }

        //判断分校id是否为空和合法
        if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
            //获取分校的名称
            $schoolName = School::where('id' , $body['school_id'])->value('school_name');
        } else {
            $schoolName = '所有分校';
        }

        //判断项目-学科大小类是否为空
        if(isset($body['category_id']) && !empty($body['category_id'])){
            $category_id= json_decode($body['category_id'] , true);
            $project_id = isset($category_id[0]) && $category_id[0] ? $category_id[0] : 0;
            $subject_id = isset($category_id[1]) && $category_id[1] ? $category_id[1] : 0;

            //判断项目id是否传递
            if($project_id && $project_id > 0){
                //项目名称
                $projectName= Project::where('id' , $project_id)->value('name');
            } else {
                $projectName= '所有项目';
            }

            //判断学科id是否传递
            if($subject_id && $subject_id > 0){
                //学科名称
                $subjectName = Project::where('parent_id' , $project_id)->where('id' , $subject_id)->value('name');
            } else {
                $subjectName = '所有学科';
            }
        } else {
            $projectName = '所有项目';
            $subjectName = '所有学科';
        }

        //判断课程id是否为空和合法
        if(isset($body['course_id']) && !empty($body['course_id']) && $body['course_id'] > 0){
            //课程名称
            $courseName  = Course::where('id' , $body['course_id'])->value('course_name');
        } else {
            $courseName  = '所有课程';
        }

        //到账金额
        $amount_received = self::where(function($query) use ($body){
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
            $createTime = date('Y-m-d' , strtotime($body['create_time']));
            $startTime  = $createTime." 00:00:00";
            $endTime    = $createTime." 23:59:59";
            $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
        })->where('pay_status'  ,1)->where('confirm_status' , 1)->sum('pay_price');

        //退费金额
        $refund_amount   = Refund_order::where(function($query) use ($body){
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
            $createTime = date('Y-m-d' , strtotime($body['create_time']));
            $startTime  = $createTime." 00:00:00";
            $endTime    = $createTime." 23:59:59";
            $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
        })->sum('refund_Price');

        //分校支出
        $campus_expenditure = 0;

        //报名费用
        $registration_fee  = self::where(function($query) use ($body){
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
            $createTime = date('Y-m-d' , strtotime($body['create_time']));
            $startTime  = $createTime." 00:00:00";
            $endTime    = $createTime." 23:59:59";
            $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
        })->where('pay_status'  ,1)->where('confirm_status' , 1)->sum('sign_Price');

        //成本
        $cost  = self::where(function($query) use ($body){
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
            $createTime = date('Y-m-d' , strtotime($body['create_time']));
            $startTime  = $createTime." 00:00:00";
            $endTime    = $createTime." 23:59:59";
            $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
        })->where('pay_status'  ,1)->where('confirm_status' , 1)->sum('sum_Price');

        //实际收入
        $real_income = self::where(function($query) use ($body){
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
            $createTime = date('Y-m-d' , strtotime($body['create_time']));
            $startTime  = $createTime." 00:00:00";
            $endTime    = $createTime." 23:59:59";
            $query->where('create_time', '>=' , $startTime)->where('create_time', '<=' , $endTime);
        })->where('pay_status'  ,1)->where('confirm_status' , 1)->sum('actual_commission');

        //明细日期
        $detailed_date = isset($body['create_time']) && !empty($body['create_time']) ? $body['create_time'] : '-';

        $campus_expenditure = floatval($refund_amount)+floatval($registration_fee)+floatval($cost);

        //封装数组
        $info_array = [
            'school_name'     =>   $schoolName ,   //分校名称
            'project_name'    =>   $projectName ,  //项目名称
            'subject_name'    =>   $subjectName ,  //学科名称
            'course_name'     =>   $courseName ,   //课程名称
            'received_money'  =>   floatval($amount_received) , //到账金额
            'refund_money'    =>   floatval($refund_amount) ,  //退费金额
            'campus_expenditure' => floatval($campus_expenditure) , //分校支出=退费金额+报名费用+成本
            'enroll_price'       => floatval($registration_fee) ,   //报名费用
            'prime_cost'         => floatval($cost) , //成本
            'real_income'        => floatval($amount_received)-floatval($refund_amount) > 0 ? floatval($amount_received)-floatval($refund_amount) : 0 , //实际收入=到账金额-退费金额
            'detailed_date'      => $detailed_date //明细日期
        ];
        return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => $info_array];
    }

    /*
     * @param  description   财务管理-分校收入详情-已退费订单
     * @param  参数说明       body包含以下参数[
     *     school_id         分校id
     *     order_time        订单时间
     * ]
     * @param author    dzj
     * @param ctime     2020-09-21
     * return string
     */
    public static function getBranchSchoolRefundOrderList($body=[]) {
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
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => $array , 'total' => $count , 'pagesize' => $pagesize , 'page' => $page]];
        }
        return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
    }

    /*
     * @param  description   财务管理-分校业绩列表
     * @param  参数说明       body包含以下参数[
     *     school_id         分校id
     *     search_time       搜索时间
     * ]
     * @param author    dzj
     * @param ctime     2020-09-19
     * return string
     */
    public static function getAchievementSchoolList($body=[]) {
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;


        //获取数量
        $count = DB::table('school')->selectRaw("count(school.id) as t_count")->leftjoin("pay_order_inside" , function($join){
            $join->on('school.id', '=', 'pay_order_inside.school_id');
        })->where('school.is_del' , 0)->where(function($query) use ($body){
            //判断分校id是否为空和合法
            if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                $query->where('school.id' , '=' , $body['school_id']);
            }

            //获取日期
            if(isset($body['search_time']) && !empty($body['search_time'])){
                $create_time = json_decode($body['search_time'] , true);
                $state_time  = $create_time[0]." 00:00:00";
                $end_time    = $create_time[1]." 23:59:59";
                $query->whereBetween('pay_order_inside.comfirm_time', [$state_time, $end_time]);
            }
        })->groupBy(DB::raw('school.id'))->get()->count();

        //判断数量是否大于0
        if($count > 0){
            //新数组赋值
            $array = [];

            //获取分校业绩列表
            $list = DB::table('school')->selectRaw('any_value(school.id) as school_id , any_value(count(school.id)) as t_count , any_value(school.one_extraction_ratio) as one_extraction_ratio , any_value(school.two_extraction_ratio) as two_extraction_ratio , any_value(school.school_name) as school_name , any_value(school.level) as level , any_value(school.tax_point) as tax_point , any_value(school.commission) as commission , any_value(school.deposit) as deposit , any_value(sum(pay_order_inside.after_tax_amount)) as after_tax_amount,any_value(pay_order_inside.sum_Price) as sum_Price,any_value(sum(pay_order_inside.pay_price)) as pay_price,any_value(sum(pay_order_inside.agent_margin)) as agent_margin,any_value(pay_order_inside.first_out_of_amount) as first_out_of_amount,any_value(pay_order_inside.second_out_of_amount) as second_out_of_amount,any_value(pay_order_inside.education_id) as education_id,any_value(pay_order_inside.major_id) as major_id,any_value(sum(pay_order_inside.sign_Price)) as sign_Price')->leftjoin("pay_order_inside" , function($join){
                $join->on('school.id', '=', 'pay_order_inside.school_id');
            })->where('school.is_del' , 0)->where(function($query) use ($body){
                //判断分校id是否为空和合法
                if(isset($body['school_id']) && !empty($body['school_id']) && $body['school_id'] > 0){
                    $query->where('school.id' , '=' , $body['school_id']);
                }

                //获取日期
                if(isset($body['search_time']) && !empty($body['search_time'])){
                    $create_time = json_decode($body['search_time'] , true);
                    $state_time  = $create_time[0]." 00:00:00";
                    $end_time    = $create_time[1]." 23:59:59";
                    $query->whereBetween('pay_order_inside.comfirm_time', [$state_time, $end_time]);
                }
            })->orderByDesc('school.create_time')->groupBy(DB::raw('school.id'))->offset($offset)->limit($pagesize)->get()->toArray();

            //循环获取相关信息
            foreach($list as $k=>$v){
                $v = (array)$v;
                //获取是几级分校
                if($v['level'] == 1){
                    $first_school_name = $v['school_name'];
                    $two_school_name   = '';
                    $three_school_name = '';
                } elseif($v['level'] == 2){
                    $first_school_name = '';
                    $two_school_name   = $v['school_name'];
                    $three_school_name = '';
                } elseif($v['level'] == 3){
                    $first_school_name = '';
                    $two_school_name   = '';
                    $three_school_name = $v['school_name'];
                }

                //到款业绩=到款金额
                $payment_performance = sprintf("%.2f",$v['pay_price']);

                //扣税比例
                $tax_deduction_ratio = $v['tax_point'];

                //扣税=到账金额*扣税比例
                $tax_deduction    = sprintf("%.2f" , $v['pay_price'] * ($tax_deduction_ratio / 100));

                //税后金额=到账金额-扣税
                $after_tax_amount = $v['pay_price'] > $tax_deduction ? sprintf("%.2f" , $v['pay_price'] - $tax_deduction) : 0;

                $body['school_id'] = $v['school_id'];
                //单数=报名订单数量+含有学历成本的订单数量
                $enroll_number   = self::where(function($query) use ($body){
                    //分校查询
                    $query->where('school_id' , '=' , $body['school_id'])->whereIn('confirm_order_type' , [2,3]);

                    //获取日期
                    if(isset($body['search_time']) && !empty($body['search_time'])){
                        $create_time = json_decode($body['search_time'] , true);
                        $state_time  = $create_time[0]." 00:00:00";
                        $end_time    = $create_time[1]." 23:59:59";
                        $query->whereBetween('pay_order_inside.comfirm_time', [$state_time, $end_time]);
                    }
                })->count();
                $chengben_number = self::where(function($query) use ($body){
                    //分校查询
                    $query->where('school_id' , '=' , $body['school_id'])->where('education_id' , '>' , 0)->where('major_id' , '>' , 0);

                    //获取日期
                    if(isset($body['search_time']) && !empty($body['search_time'])){
                        $create_time = json_decode($body['search_time'] , true);
                        $state_time  = $create_time[0]." 00:00:00";
                        $end_time    = $create_time[1]." 23:59:59";
                        $query->whereBetween('pay_order_inside.comfirm_time', [$state_time, $end_time]);
                    }
                })->count();
                $order_number    = $enroll_number + $chengben_number;

                //成本=学历成本+报名费用
                $education_major_ids = self::select('major_id')->where(function($query) use ($body){
                    //分校查询
                    $query->where('school_id' , '=' , $body['school_id']);

                    //获取日期
                    if(isset($body['search_time']) && !empty($body['search_time'])){
                        $create_time = json_decode($body['search_time'] , true);
                        $state_time  = $create_time[0]." 00:00:00";
                        $end_time    = $create_time[1]." 23:59:59";
                        $query->whereBetween('pay_order_inside.comfirm_time', [$state_time, $end_time]);
                    }
                })->get()->toArray();
                $major_ids      = array_column($education_major_ids, 'major_id');
                $education_cost = Major::whereIn('id' , $major_ids)->sum('price');
                $sum_cost       = sprintf("%.2f" , $education_cost + $v['sign_Price']);

                //实际到款=税后金额-成本
                $actual_receipt = $after_tax_amount > $v['sum_Price']  ? sprintf("%.2f" , $after_tax_amount - $v['sum_Price']) : 0;

                //返佣比例=后台分校管理中佣金比例
                $commission_rebate = $v['commission'];

                //返佣金额=实际到款*返佣比例
                $commission_money  = sprintf("%.2f" , $actual_receipt * ($commission_rebate / 100));

                //保证金=返佣金额*后台分校管理中押金比例
                $bond  = sprintf("%.2f" , $commission_money * ($v['deposit'] / 100));

                $one_extraction_ratio = $v['one_extraction_ratio'] && !empty($v['one_extraction_ratio']) ? $v['one_extraction_ratio'] : 0;
                $two_extraction_ratio = $v['two_extraction_ratio'] && !empty($v['two_extraction_ratio']) ? $v['two_extraction_ratio'] : 0;

                //一级分校无一级抽离比例、押金和二级抽离比例、押金
                if($v['level'] == 1){
                    $first_out_of_amount = '';
                    $second_out_of_amount= '';
                    $first_out_of_money  = '';
                    $second_out_of_money = '';
                    //代理保证金
                    $agent_margin = $v['agent_margin'] && !empty($v['agent_margin']) ? $v['agent_margin'] : 0;

                    //一级分校下面的所有二级分校
                    $seond_school_id = School::select('id')->where('parent_id' , $v['school_id'])->where('level' , 2)->get()->toArray();
                    $seond_school_ids= array_column($seond_school_id, 'id');

                    //二级下面的所有三级分校
                    $three_school_id = School::select('id')->whereIn('parent_id' , $seond_school_ids)->where('level' , 3)->get()->toArray();
                    $three_school_ids= array_column($three_school_id, 'id');

                    //一级分校的实际返佣=返佣金额-一级分校的保证金+（二级分校的一级抽离金额+三级分校的一级抽离金额）*（1-押金比例）-（一级分校退费*返佣比例+二级分校退费*二级分校1级抽离比例+三级分校退费*二级分校1级抽离比例）
                    //二级分校的一级抽离金额
                    $first_out_of_amount1 = self::whereIn('school_id' , $seond_school_ids)->sum('first_out_of_amount');

                    //三级分校的一级抽离金额
                    $first_out_of_amount2 = self::whereIn('school_id' , $three_school_ids)->sum('first_out_of_amount');

                    //一级分校退费金额
                    $first_refund_Price = Refund_order::where('school_id' , $v['school_id'])->where('confirm_status' , 1)->sum('refund_Price');
                    //二级分校退费金额
                    $send_refund_Price  = Refund_order::whereIn('school_id' , $seond_school_ids)->where('confirm_status' , 1)->sum('refund_Price');
                    //三级分校退费金额
                    $three_refund_Price = Refund_order::whereIn('school_id' , $three_school_ids)->where('confirm_status' , 1)->sum('refund_Price');

                    //二级分校的一级抽离比例=后台分校管理中一级抽离比例  |  三级分校的一级抽离比例=后台分校管理中一级抽离比例
                    $actual_commission_refund = $commission_money - $bond + ($first_out_of_amount1 + $first_out_of_amount2) * (1 - $v['deposit']) - ($first_refund_Price*$v['commission']+$send_refund_Price*$one_extraction_ratio+$three_refund_Price*$one_extraction_ratio);
                } elseif($v['level'] == 2){
                    //二级分校的一级抽离比例=后台分校管理中一级抽离比例
                    //二级分校的一级抽离金额=二级分校的一级抽离比例*实际到款
                    //二级分校无二级抽离比例、押金

                    //二级分校的一级抽离比例一级抽离比例
                    $first_out_of_amount  = $v['one_extraction_ratio'] && !empty($v['one_extraction_ratio']) ? $v['one_extraction_ratio'] : '';
                    $first_out_of_money   = !empty($v['first_out_of_amount']) && $v['first_out_of_amount'] > 0 ? $v['first_out_of_amount'] : '';
                    $second_out_of_amount = '';
                    $second_out_of_money  = '';
                    //代理保证金
                    $agent_margin = $v['agent_margin'] && !empty($v['agent_margin']) ? $v['agent_margin'] : 0;

                    //二级下面的所有三级分校
                    $three_school_id = School::select('id')->where('parent_id' , $v['school_id'])->where('level' , 3)->get()->toArray();
                    $three_school_ids= array_column($three_school_id, 'id');

                    //三级分校的二级抽离金额
                    $second_out_of_amount2 = self::whereIn('school_id' , $three_school_ids)->sum('second_out_of_amount');

                    //二级分校退费金额
                    $send_refund_Price     = Refund_order::where('school_id' , $v['school_id'])->where('confirm_status' , 1)->sum('refund_Price');
                    //三级分校退费金额
                    $three_refund_Price    = Refund_order::whereIn('school_id' , $three_school_ids)->where('confirm_status' , 1)->sum('refund_Price');

                    //二级分校的实际返佣=二级分校的返佣金额-二级分校的保证金+三级分校的二级抽离金额*（1-押金比例）-（二级分校退费*返佣比例+三级分校退费*三级分校2级抽离比例）
                    $actual_commission_refund = $commission_money - $bond + $second_out_of_amount2 * (1 - $v['deposit']) - ($send_refund_Price * $v['commission'] + $three_refund_Price * $two_extraction_ratio);
                } elseif($v['level'] == 3){
                    //三级分校的一级抽离比例=后台分校管理中一级抽离比例
                    //三级分校的一级抽离金额=三级分校的一级抽离比例*实际到款
                    //三级分校的二级抽离比例=后台分校管理中二级抽离比例
                    //三级分校的二级抽离金额=三级分校的二级抽比例*实际到款
                    //三级分校的实际返佣=三级分校的返佣金额-三级分校的保证金-三级分校退费*三级分校返佣比例
                    $first_out_of_amount  = $v['one_extraction_ratio'] && !empty($v['one_extraction_ratio']) ? $v['one_extraction_ratio'] : '';
                    $first_out_of_money   = $v['first_out_of_amount'] && !empty($v['first_out_of_amount']) ? $v['first_out_of_amount'] : '';

                    //二级抽离比例
                    $second_out_of_amount= $v['two_extraction_ratio'] && !empty($v['two_extraction_ratio']) ? $v['two_extraction_ratio'] : '';
                    $second_out_of_money = !empty($v['second_out_of_amount']) && $v['second_out_of_amount'] > 0 ? $v['second_out_of_amount'] : '';
                    //三级分校无代理保证金
                    $agent_margin = '';

                    //三级分校退费金额
                    $three_refund_Price    = Refund_order::where('school_id' , $v['school_id'])->where('confirm_status' , 1)->sum('refund_Price');

                    //三级分校的实际返佣=三级分校的返佣金额-三级分校的保证金-三级分校退费*三级分校返佣比例
                    $actual_commission_refund = $commission_money - $bond - $three_refund_Price * $v['commission'];
                }


                //数组赋值
                $array[] = [
                    'first_school_name'   =>  $first_school_name ,
                    'two_school_name'     =>  $two_school_name ,
                    'three_school_name'   =>  $three_school_name ,
                    'payment_performance' =>  $payment_performance ,
                    'actual_receipt'      =>  $actual_receipt ,   //实际到款
                    'tax_deduction_ratio' =>  $tax_deduction_ratio ,
                    'after_tax_amount'    =>  $after_tax_amount ,
                    'order_number'        =>  $order_number ,
                    'sum_cost'            =>  $sum_cost ,
                    'commission_rebate'   =>  $commission_rebate ,
                    'commission_money'    =>  $commission_money ,
                    'bond'                =>  $bond ,
                    'agent_margin'        =>  $agent_margin ,
                    'first_out_of_amount' =>  $first_out_of_amount ,
                    'first_out_of_money'  =>  $first_out_of_money ,
                    'second_out_of_amount'=>  $second_out_of_amount ,
                    'second_out_of_money' =>  $second_out_of_money ,
                    'actual_commission_refund' => $actual_commission_refund
                ];
            }
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => $array , 'total' => $count , 'pagesize' => $pagesize , 'page' => $page]];
        }
        return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
    }

    public static function paylistarr(){
        $channel = Channel::where(['is_use'=>0,'is_del'=>0,'is_forbid'=>0])->first();
        $paylist = PaySet::where(['channel_id'=>$channel['id']])->first();
        $payarr=[];
        if($paylist['wx_pay_state'] == 1){
            $payarr[]=[
                'label'=>'微信扫码',
                'value'=> 1
            ];
        }
        if($paylist['zfb_pay_state'] == 1){
            $payarr[]=[
                'label'=>'支付宝扫码',
                'value'=> 2
            ];
        }
        if($paylist['hj_wx_pay_state'] == 1){
            $payarr[]=[
                'label'=>'汇聚微信扫码',
                'value'=> 3
            ];
        }
        if($paylist['hj_zfb_pay_state'] == 1){
            $payarr[]=[
                'label'=>'汇聚支付宝扫码',
                'value'=> 4

            ];
        }
        $bank = OfflinePay::where(['is_del'=>1,'type'=>2,'is_show'=>1])->count();
        if($bank > 0){
            $payarr[]=[
                'label'=>'银行卡支付',
                'value'=> 5
            ];
        }
        $gong = OfflinePay::where(['is_del'=>1,'type'=>1,'is_show'=>1])->count();
        if($gong > 0){
            $payarr[]=[
                'label'=>'对公打款',
                'value'=> 6
            ];
        }
        $zfb = OfflinePay::where(['is_del'=>1,'type'=>3,'is_show'=>1])->count();
        if($zfb > 0){
            $payarr[]=[
                'label'=>'支付宝对公支付',
                'value'=> 7
            ];
        }
        return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => $payarr];
    }
}
