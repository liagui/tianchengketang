<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use App\Models\CourseStocks;
use App\Models\Coures;
use App\Models\Student;
use App\Models\CourseSchool;
use App\Models\Order;
use Validator;
use Illuminate\Support\Facades\DB;

class SchoolDataController extends Controller {

    //需要schoolid的方法
    protected $need_schoolid = [
        'courseDetailStocks'
    ];

    /**
     * 初始化
     */
    public function __construct(Request $request)
    {
        list($path,$action) = explode('@',$request->route()[1]['uses']);
        //schoolid检查
        if(in_array($action,$this->need_schoolid)) {
            $schoolid = $request->input('schoolid');
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
     * @ctime 2020/10/20
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 15;

        //
        $whereArr = [['is_del','=',1]];
        //like
        if(isset($data['school_name']) && $data['school_name']){
            $whereArr[] = ['name','like','%'.$data['school_name'].'%'];
        }
        if(isset($data['school_dns']) && $data['school_dns']){
            $whereArr[] = ['dns','like','%'.$data['school_dns'].'%'];
        }
        //page
        $offset   = ($page - 1) * $pagesize;
        $count = School::where($whereArr)->count();
        $sum_page = ceil($count/$pagesize);

        //
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
        //result
        $field = ['id','name','logo_url','dns','balance','is_forbid','create_time as end_time','account_name as service'];
        $list = School::where($whereArr)->select($field)
                        ->offset($offset)->limit($pagesize)->get()->toArray();
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
        foreach($list as $k=>$v){
            $data = [];
            //课程
            $data['course'] = $this->giveCourseData($v['id']);

            //2直播并发
            $data['live'] = $this->getLiveData($v['id']);

            //3空间
            $data['storage'] = $this->getStorageData($v['id']);

            //4流量
            $data['flow'] = $this->getFlowData($v['id']);

            //5学员
            $data['user'] = $this->getUserData($v['id']);

            $list[$k]['content'] = $data;
        }
        return $list;
    }

    /**
     * 授权课程数据
     */
    public function giveCourseData($id)
    {
        //授权库存
        $give_total = CourseStocks::where('school_id',$id)->where('is_del',0)->sum('add_number');
        //授权课程销售量
        $wheres = ['school_id'=>$id,'oa_status'=>1,'nature'=>1,'status'=>2];
        $give_ordernum = Order::whereIn('pay_status',[3,4])->where($wheres)->count();
        //自增课程数量
        $total = Coures::where('school_id',$id)->where('is_del',0)->count();
        //自增课程销售
        $wheres['nature'] = 0;
        $ordernum = Order::whereIn('pay_status',[3,4])->where($wheres)->count();

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
    public function getLiveData($id)
    {
        //并发数量
        $num = 1;

        //当月可用
        $month_num = 1;

        //当月已用
        $month_usednum = 1;

        //有效期
        $end_time = '2020-10-29 11:59:59';

        return [
            'num'=>$num,
            'month_num'=>$month_num,
            'month_usednum'=>$month_usednum,
            'end_time'=>$end_time,
        ];

    }

    /**
     * 储存空间
     */
    public function getStorageData($id)
    {
        //总量
        $total = 1;
        //使用量
        $used = 1;
        //有效期
        $end_time = '2020-10-29 11:59:59';

        return [
            'total'=>$total,
            'used'=>$used,
            'end_time'=>$end_time
        ];
    }

    /**
     * 流量数据
     */
    public function getFlowData($id)
    {
        //总量
        $total = 1;
        //使用量
        $used = 1;
        //有效期
        $end_time = '2020-10-29 11:59:59';

        return [
            'total'=>$total,
            'used'=>$used,
            'end_time'=>$end_time
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

}
