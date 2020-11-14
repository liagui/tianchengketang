<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Admin;
use App\Models\AdminManageSchool;
use App\Models\School;
use App\Models\SchoolConnectionsCard;
use App\Models\SchoolConnectionsDistribution;
use App\Models\SchoolResource;
use App\Models\Subject;
use App\Tools\CurrentAdmin;
use Illuminate\Http\Request;
use App\Models\CourseStocks;
use App\Models\Coures;
use App\Models\Student;
use App\Models\CourseSchool;
use App\Models\ServiceRecord;
use App\Models\Order;
use Maatwebsite\Excel\Facades\Excel;
use mysql_xdevapi\Exception;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;
use Validator;
use Illuminate\Support\Facades\DB;

/**
 * 数据控制首页
 * @author laoxian
 */
class SchoolDataController extends Controller {

    //需要schoolid的方法
    protected $need_schoolid = [
        'courseDetailStocks'
    ];

    /**
     * 初始化
     */
    public function __construct()
    {
        list($path,$action) = explode('@',request()->route()[1]['uses']);
        //schoolid检查
        if(in_array($action,$this->need_schoolid)) {
            $schoolid = request()->input('schoolid');
            if (!$schoolid || !is_numeric($schoolid)) {
                //return response()->json(['code'=>'201','msg'=>'网校标识错误']);
                header('Content-type: application/json');
                echo json_encode(['code' => '201', 'msg' => '网校标识错误']);
                die();
            }
            $schools = School::find($schoolid);
            if(empty($schools)){
                header('Content-type: application/json');
                echo json_encode(['code' => '202', 'msg' => '找不到当前学校']);
                die();
            }
        }

        //删除
        //$data =  $request->except(['token']);
        //赋值 与 替换
        //$data =  $request->offsetSet('字段1',变量);
        //$request->merge(['字段1'=>1,'字段2'=>2]);
    }

    /**
     * 控制台首页
     * @author laoxian
     * @todo 并发, 空间,流量已使用, 负责人
     * @time 2020/10/20
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 15;

        //是否管理所有分校
        $admin_user = isset(AdminLog::getAdminInfo()->admin_user) ? AdminLog::getAdminInfo()->admin_user : [];
        //$admin_user = Admin::find(1);

        //
        $whereArr = [['is_del','=',1],['id','>',1]];//>1 是为了 列表排除总校显示
        //like
        if(isset($data['school_name']) && $data['school_name']){
            $whereArr[] = ['name','like','%'.$data['school_name'].'%'];
        }
        if(isset($data['school_dns']) && $data['school_dns']){
            $whereArr[] = ['dns','like','%'.$data['school_dns'].'%'];
        }
        //page
        $offset   = ($page - 1) * $pagesize;

        //result
        $field = ['id','name','logo_url','dns','balance','is_forbid','end_time','livetype','ifinto'];
        $query = School::where($whereArr)->where(function($query) use ($admin_user) {
            if(!$admin_user->is_manage_all_school){
                //获取本管理员可管理的网校
                $school_ids = AdminManageSchool::manageSchools($admin_user->id);
                if($school_ids){
                    $query->whereIn('id',$school_ids);
                }else{
                    $query->where('id','=',0);//当此管理员没有可管理的网校时, 定义一个结果为空的查询条件
                }
            }
        })->select($field);

        //total
        $count = $query->count();
        //row
        $list = $query->offset($offset)->limit($pagesize)->get();
        //$count = School::where($whereArr)->count();
        $sum_page = ceil($count/$pagesize);
        $return = [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'list' => [] ,
                'total' => $count ,
                'total_page'=>$sum_page
            ],
        ];
        if(!$count){
            return response()->json($return);
        }

        $list = json_decode($list,true);
        $lists = $this->queryTable($list);
        $return['data']['list'] = $lists;
        return response()->json($return);
    }

    /**
     * 查询网校数据
     * @author laoxian
     * @return array
     */
    public function queryTable($list)
    {
        $schoolidArr = array_column($list,'id');

        //查找当前网校的 总控方管理员姓名, 并重新整理数组
        $school_admins = DB::table('ld_school as school')
            ->join('ld_admin_manage_school as manage','school.id','=','manage.school_id')
            ->join('ld_admin as admin','admin.id','=','manage.admin_id')
            ->whereIn('school.id',$schoolidArr)
            ->select('manage.school_id','admin.realname')
            ->get()->toArray();
        $school_adminArr = [];
        foreach($school_admins as $k=>$v){
            $school_adminArr[$v->school_id][] = $v->realname;
        }


        foreach($list as $k=>$v){
            $list[$k]['service'] = '';
            if(isset($school_adminArr[$v['id']])){
                $list[$k]['service'] = implode(',',$school_adminArr[$v['id']]);
            }

            $data = [];
            //课程
            $data['course'] = $this->giveCourseData($v['id']);

            /*
             * $time = date('Y-m-d H:i:s');
            $listArr = DB::table('ld_service_record as service')//服务购买表
            ->join('ld_school_order as order','service.oid','=','order.oid')
                ->where('order.school_id',$v['id'])//学校
                ->where('order.status',2)//审核成功
                ->whereIn('service.type',[1,2])//直播,空间,流量
                ->where('service.start_time','<=',$time)//生效时间
                ->where('service.end_time','>',$time)//截止使用时间
                ->select('service.num','service.start_time','service.end_time','service.type')
                ->get()->toArray();

            $flownum = DB::table('ld_service_record as service')//服务购买表
            ->join('ld_school_order as order','service.oid','=','order.oid')
                ->where('order.school_id',$v['id'])//学校
                ->where('order.status',2)//审核成功
                ->where('service.type',3)//直播,空间,流量
                ->sum('service.num');
            $listArrs = [];
            foreach($listArr as $a){
                $listArrs[$a->type][] = $a;
            }*/

            // * 获取后台 统计 用户 并发 流量 使用数据
            $school_resource  = new SchoolResource();
            $school_card = new SchoolConnectionsCard();
            $resource = $school_resource ->getInfoBySchoolID($v['id']);
            // 当月可用的并发数
            $month_num = $school_card->getNumByDate($v['id'],date("Y-m-d"));
            // 当月 已经 分配的 并发数
            $school_conn_dis = new SchoolConnectionsDistribution();
            $month_num_used = $school_conn_dis->getDistributionByDate($v['id'],date("Y-m-d"));


            //2直播并发
            //$data['live'] = $this->getLiveData($v['id'],isset($listArrs[1])?$listArrs[1]:[]);
            $data['live'] =  [
                'num'=> !is_null($resource)? $resource->connections_total:0,
                'month_num'=>$month_num,
                'month_usednum'=>intval($month_num_used),
                //'end_time'=>substr($end_time,0,10), // 并发数没有截止日期的说
            ];


            //3空间
            //$data['storage'] = $this->getStorageData($v['id'],isset($listArrs[2])?$listArrs[2]:[]);
            $data['storage'] = [
                'total'=> conversionBytes(!is_null($resource)? $resource->space_total:0),
                'used'=> conversionBytes(!is_null($resource)? $resource->space_used:0),
                'end_time'=>date("Y-m-d",strtotime(!is_null($resource)?$resource->space_expiry_date:0)),
            ];

            //4流量
            //$data['flow'] = $this->getFlowData($v['id'],isset($listArrs[3])?$listArrs[3]:[]);
            $data['flow']['total'] = conversionBytes(!is_null($resource)?$resource->traffic_total:0);
            $data['flow']['used'] = conversionBytes(!is_null($resource)?$resource->traffic_used:0);
            $data['flow']['end_time'] = !is_null($resource)?date("Y-m-d",strtotime($resource->space_expiry_date)):date("Y-m-d");

            //5学员
            $data['user'] = $this->getUserData($v['id']);

            $list[$k]['content'] = $data;
            $list[$k]['end_time'] = $v['end_time']?substr($v['end_time'],0,10):'';
        }
        return $list;
    }

    /**
     * 授权课程数据
     */
    public function giveCourseData($id)
    {
        //授权库存
        $give_total = DB::table('ld_course_school as course')//授权课程记录表关联库存记录表
            ->join('ld_course_stocks as stocks','course.course_id','=','stocks.course_id')
            ->where('course.to_school_id',$id)//学校
            ->where('stocks.school_id',$id)//学校
            ->where('course.is_del',0)
            ->where('stocks.is_del',0)
            ->sum('stocks.add_number');

        //授权课程销售量
        $give_ordernum = DB::table('ld_course_school as course')//授权课程记录表, 关联订单表
            ->join('ld_order as order','course.course_id','=','order.class_id')
            ->where('course.to_school_id',$id)//学校
            ->where('course.is_del',0)//未删除
            ->where('order.oa_status',1)//订单成功
            ->where('order.status',2)//订单成功
            ->where('order.nature',1)//授权课程
            ->whereIn('order.pay_status',[3,4])//付费完成订单
            ->count();
        /*$wheres = ['school_id'=>$id,'oa_status'=>1,'nature'=>1,'status'=>2];
        $give_ordernum = Order::whereIn('pay_status',[3,4])->where($wheres)->count();*/
        //自增课程数量
        $total = Coures::where('school_id',$id)->where('is_del',0)->count();
        //自增课程销售
        $ordernum = DB::table('ld_course as course')//授权课程记录表, 关联订单表
        ->join('ld_order as order','course.id','=','order.class_id')
            ->where('course.school_id',$id)//学校
            ->where('course.is_del',0)//未删除
            ->where('order.oa_status',1)//订单成功
            ->where('order.status',2)//订单成功
            ->where('order.nature',0)//授权课程
            ->whereIn('order.pay_status',[3,4])//付费完成订单
            ->count();
        /*$wheres['nature'] = 0;
        $ordernum = Order::whereIn('pay_status',[3,4])->where($wheres)->count();*/

        return [
            'give_stocks'=>$give_total,
            'give_sales'=>$give_ordernum,
            'total'=>$total,
            'sales'=>$ordernum
            ];
    }

    /**
     * 直播数据统计
     */
    public function getLiveData($id,$listArr)
    {
        //并发数量
        $num = 0;

        //当月可用
        $month_num = 0;

        //当月已用
        $month_usednum = 0;

        //有效期
        $end_time = '1970-00-00';
        foreach($listArr as $a){
            $num += $a->num;
            if(strtotime($a->end_time)>strtotime($end_time))
            {
                $end_time = $a->end_time;
            }
        }

        return [
            'num'=>$num,
            'month_num'=>$month_num,
            'month_usednum'=>$month_usednum,
            'end_time'=>substr($end_time,0,10),
        ];

    }

    /**
     * 储存空间
     */
    public function getStorageData($id,$listArr)
    {
        //总量
        $total = 0;
        //使用量
        $used = 0;
        //有效期
        $end_time = '1970-00-00';
        foreach($listArr as $a){
            $total += $a->num;
            if(strtotime($a->end_time)>strtotime($end_time))
            {
                $end_time = $a->end_time;
            }
        }

        return [
            'total'=>$total,
            'used'=>$used,
            'end_time'=>substr($end_time,0,10),
        ];
    }

    /**
     * 流量数据
     */
    public function getFlowData($id,$listArr)
    {
        //总量
        $total = 0;
        //使用量
        $used = 0;
        //有效期
        $end_time = '1970-00-00';
        foreach($listArr as $a){
            $total += $a->num;
            if(strtotime($a->end_time)>strtotime($end_time))
            {
                $end_time = $a->end_time;
            }
        }

        return [
            'total'=>$total,
            'used'=>$used,
            'end_time'=>substr($end_time,0,10),
        ];
    }

    /**
     * 学员数据
     */
    public function getUserData($id)
    {
        //总注册
        $total = Student::where('school_id',$id)->count();
        //总付费
        $pay_student = Student::where('school_id',$id)->where('enroll_status',1)->count();

        return ['total'=>$total,'pay_student'=>$pay_student];
    }

    /**
     * 对账数据
     * @param page int 页码
     * @param pagesize int 页大小
     * @param school_id int 网校
     * @param subject_id int 学科
     * @param course_id int 课程
     * @param start_time date 开始时间
     * @param end_time date 结束时间
     * @param name string 关键字:姓名/手机号
     * @author zhaolaoxian
     * @time 2020/10/28
     */
    public function orderList(Request $request)
    {
        /*$arr = [
            '5'=>[
                29
            ]
        ];
        return response()->json($arr);*/
        $return = $this->getOrderlist($request->all());
        return response()->json($return);
    }

    /**
     * 对账数据
     * TODO 目前采用一次性导出的方法, 预计不增加列的情况下, 数据在 <2w条 期间不会出现内容溢出
     */
    public function orderExport(Request $request)
    {
        //定义一个用于判断导出的参数
        $request->offsetSet('export','1');
        $date = date("Y-m-d");
        return Excel::download(new \App\Exports\BillExport($request->all()), "对账数据-{$date}.xlsx");
    }

    /**
     * 获取订单内容
     * TODO 待验证是否可同一字段关联两张表
     */
    public function getOrderlist($post)
    {
        $page     = isset($post['page']) && $post['page'] > 0 ? $post['page'] : 1;
        $pagesize = isset($post['pagesize']) && $post['pagesize'] > 0 ? $post['pagesize'] : 15;
        //page
        $offset   = ($page - 1) * $pagesize;
        //需要展示 分校名, 姓名, 手机号, 姓名, 学科, 价格, 购买价格
        $field = [
            'ld_school.name as school_name',
            'ld_student.real_name','ld_student.phone',
            'ld_order.price','ld_order.lession_price','ld_order.class_id',
            'ld_order.nature','ld_order.create_at',//'ld_order.school_id'
        ];
        //
        $bill = Order::select($field)
            ->leftJoin('ld_school','ld_school.id','=','ld_order.school_id')
            ->leftJoin('ld_student','ld_student.id','=','ld_order.student_id')
            ->leftJoin('ld_course','ld_course.id','=','ld_order.class_id')
            ->leftJoin('ld_course_school','ld_course_school.id','=','ld_order.class_id')
            ->where(function($query) use ($post) {
                //网校
                if(isset($post['school_id']) && $post['school_id']){
                    $query->where('ld_order.school_id','=',$post['school_id']);
                }
                //学科
                if(isset($post['subject_id']) && $post['subject_id']){
                    $subjectidArr = json_decode($post['subject_id'],true);
                    $parentid = 0;//用于存储一级学科
                    $subjectid = 0;//用于存储二级学科
                    if(isset($subjectidArr[0])) $parentid = $subjectidArr[0];
                    if(isset($subjectidArr[1])) $subjectid = $subjectidArr[1];
                    /*foreach($subjectid as $k=>$v){
                        //注释二级学科多选的情况
                        $parentid = $k;
                        $subjectidarr = $v;
                    }*/
                    if($subjectid){
                        //单个学科用=
                        $sql = "(ld_course_school.child_id  = ? or ld_course.child_id = ? )";
                        $query->whereRaw($sql,[$subjectid,$subjectid]);
                        //$subjectidarr && is_array($subjectidarr)
                        /*$subjectidarr = implode(',',$subjectidarr);
                        if(strpos($subjectidarr,',')){
                            //多个学科采用 in
                            $sql = "(ld_course_school.child_id in (?) or ld_course.child_id in (?) )";
                            $query->whereRaw($sql,[$subjectidarr,$subjectidarr]);
                        }else{
                            //单个学科用=
                            $sql = "(ld_course_school.child_id  = ? or ld_course.child_id = ? )";
                            $query->whereRaw($sql,[$subjectidarr,$subjectidarr]);
                        }*/
                    }else{
                        //当二级学科不存在时, 判断是否执行搜索大类
                        if($parentid){
                            $sql = "(ld_course_school.parent_id = ? or ld_course.parent_id = ?)";
                            $query->whereRaw($sql,[$parentid,$parentid]);
                        }
                    }
                }

                //课程
                if(isset($post['course_id']) && $post['course_id']){
                    if(strpos($post['course_id'],',')){
                        $post['course_id'] = trim($post['course_id'],',');
                        $sql = "(ld_course_school.course_id in (?) or ld_course.id in (?) )";
                        $query->whereRaw($sql,[$post['course_id'],$post['course_id']]);
                    }else{
                        $sql = "(ld_course_school.course_id = ? or ld_course.id = ?)";
                        $query->whereRaw($sql,[$post['course_id'],$post['course_id']]);
                    }
                }

                //开始时间
                if(isset($post['start_time']) && $post['start_time']){
                    $query->where('ld_order.create_at','>=',$post['start_time']);
                }
                //结束时间
                if(isset($post['end_time']) && $post['end_time']){
                    $query->where('ld_order.create_at','<=',$post['end_time']);
                }
                //关键字 真实姓名/昵称/手机号 TODO 关键字原生模糊查询待改为参数过滤
                if(isset($post['name']) && $post['name']){
                    $sql = "(ld_student.real_name like '%{$post['name']}%' or ld_student.phone like '%{$post['name']}%' or ld_student.nickname like '%{$post['name']}%' )";
                    //$sql = "(ld_student.real_name like '%?%' or ld_student.phone like '%?%' or ld_student.nickname like '%?%' )";
                    $query->whereRaw($sql);//,[$post['name'],$post['name'],$post['name']]);
                }

            })
            ->whereIn('ld_order.status',[1,2]);//代表订单已支付
        if(isset($post['export']) && $post['export']){
            //导出 - 取全部数据
            $list = $bill->get();
        }else{
            //row
            $total = $bill->count();

            //查看 - 取15条数据
            $list = $bill->offset($offset)->limit($pagesize)->get();

            $total_page = ceil($total/$pagesize);
            $return = [
                'code'=>200,
                'msg'=>'success',
                'data'=>[
                    'list' => [] ,
                    'total' => $total ,
                    'total_page'=>$total_page
                ],
            ];
            if(!$total){
                return $return;
            }

            $list = json_decode($list,true);
        }


        //填充完整信息
        $courseids = [];
        foreach($list as $k=>$v){
            $courseids[$v['nature']][] = $v['class_id'];//nature==1授权课程,0自增课程
        }
        //自增课程
        $parentid_selfs = [];
        if(isset($courseids[0])){
            $course_selfs = Coures::whereIn('id',$courseids[0])->select('id','title','parent_id')->get()->toArray();
            $parentid_selfs = array_column($course_selfs,'parent_id');
            //整合为id=>标题,科目id
            $course_selfArr = [];
            foreach($course_selfs as $k=>$v){
                $course_selfArr[$v['id']]['title'] = $v['title'];
                $course_selfArr[$v['id']]['parent_id'] = $v['parent_id'];
            }
        }
        //授权课程
        $parentid_gives = [];
        if(isset($courseids[1])){
            $course_gives = CourseSchool::whereIn('id',$courseids[1])->select('id','title','parent_id')->get()->toArray();
            $parentid_gives = array_column($course_gives,'parent_id');
            //整合为id=>标题,科目id
            $course_giveArr = [];
            foreach($course_gives as $k=>$v){
                $course_giveArr[$v['id']]['title'] = $v['title'];
                $course_giveArr[$v['id']]['parent_id'] = $v['parent_id'];
            }
        }
        //学科id集合
        $parentids = array_merge($parentid_gives,$parentid_selfs);
        //学科id=>学科名称
        $parentArr = Subject::whereIn('id',$parentids)->pluck('subject_name','id');

        foreach($list as $k=>$v)
        {
            if($v['nature']){
                //课程标题
                $list[$k]['course_title'] = isset($course_giveArr[$v['class_id']]['title'])?$course_giveArr[$v['class_id']]['title']:'';
                //当前课程学科id
                $subjectid = isset($course_giveArr[$v['class_id']]['parent_id'])?$course_giveArr[$v['class_id']]['parent_id']:'X';
                //学科名称
                $list[$k]['subjectid'] = $subjectid;
                $list[$k]['course_subject_name'] = isset($parentArr[$subjectid])?$parentArr[$subjectid]:'';
            }else{
                //课程标题
                $list[$k]['course_title'] = isset($course_selfArr[$v['class_id']]['title'])?$course_selfArr[$v['class_id']]['title']:'';
                //档期间课程学科id
                $subjectid = isset($course_selfArr[$v['class_id']]['parent_id'])?$course_selfArr[$v['class_id']]['parent_id']:'X';
                //学科名称
                $list[$k]['subjectid'] = $subjectid;
                $list[$k]['course_subject_name'] = isset($parentArr[$subjectid])?$parentArr[$subjectid]:'';
            }
            if(!isset($total)){
                //查看接口时保留这两个字段, 用于查看测试
                unset($list[$k]['nature']);//授权字段
                unset($list[$k]['class_id']);//课程id字段
            }

        }

        $return['data']['list'] = $list;
        return $return;
    }

}
