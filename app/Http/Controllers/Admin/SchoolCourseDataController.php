<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\liveService;
use App\Models\School;
use Illuminate\Http\Request;
use App\Models\CourseStocks;
use App\Models\Coures;
use App\Models\Student;
use App\Models\CourseSchool;
use App\Models\Order;
use Validator;
use Illuminate\Support\Facades\DB;

/**
 * 数据控制 课程相关
 * @author laoxian
 */
class SchoolCourseDataController extends Controller {

    //需要schoolid的方法
    protected $need_schoolid = [
        'stocks',//课程详情
        'addMultiStocks'//批量添加库存
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
     * 课程详情
     */
    public function stocks(Request $request)
    {
        $params = $request->all();
        $id = $params['schoolid'];
        $data['give'] = $this->giveDetail($id);
        $data['school'] = $this->schoolDetail($id);
        //var_dump($data);die();
        return response()->json(['code'=>200,'msg'=>'success','data'=>$data]);
    }

    /**
     * 授权课程详情
     */
    public function giveDetail($id)
    {
        $normal = [];//在售
        $hidden = [];//停售
        //授权课程 在售 and 停售
        $normal['total'] = CourseSchool::where(['to_school_id'=>$id,'is_del'=>0,'status'=>1])->count();
        $hidden['total'] = CourseSchool::where(['to_school_id'=>$id,'is_del'=>0,'status'=>2])->count();
        //库存总数
        $query = DB::table('ld_course_school as course')//授权课程记录表关联库存记录表
        ->join('ld_course_stocks as stocks','course.course_id','=','stocks.course_id')
            ->where('course.to_school_id',$id)//学校
            ->where('course.is_del',0);//未删除

        //status= 1,在售课程
        $normal['stocks'] = $query->where('course.status',1)->sum('stocks.add_number');
        //status= 2, 停售课程
        $hidden['stocks'] = $query->where('course.status',2)->sum('stocks.add_number');

        //购买量
        $query = DB::table('ld_course_school as course')//授权课程记录表, 关联订单表
        ->join('ld_order as order','course.course_id','=','order.class_id')
            ->where('course.to_school_id',$id)//学校
            ->where('course.is_del',0)//未删除
            ->where('order.oa_status',1)//订单成功
            ->where('order.status',2)//订单成功
            ->where('order.nature',1)//授权课程
            ->whereIn('order.pay_status',[3,4]);//付费完成订单
        $normal['used_stocks'] = $query->where('course.status',1)->count();
        //print_r($normal['used_stocks']);die();
        $hidden['used_stocks'] = $query->where('course.status',2)->count();

        //现有库存  =  总库存-已出售
        $normal['surplus_stocks'] = $normal['stocks']-$normal['used_stocks'];
        $hidden['surplus_stocks'] = $hidden['stocks']-$hidden['used_stocks'];

        return ['normal'=>$normal,'hidden'=>$hidden];
    }

    /**
     * 网校自增课程详情
     */
    public function schoolDetail($id)
    {
        $normal = [];//在售
        $hidden = [];//停售
        //自增课程数量 在售 and 停售
        $normal['total'] = Coures::where(['school_id'=>$id,'is_del'=>0,'status'=>1])->count();
        $hidden['total'] = Coures::where(['school_id'=>$id,'is_del'=>0,'status'=>2])->count();

        //购买量 在售 and 停售
        $query = DB::table('ld_course as course')//授权课程记录表, 关联订单表
        ->join('ld_order as order','course.id','=','order.class_id')
            ->where('course.school_id',$id)//学校
            ->where('course.is_del',0)//未删除
            ->where('order.oa_status',1)//oa审核订单成功
            ->where('order.status',2)//订单成功
            ->where('order.nature',0)//自增课程
            ->whereIn('order.pay_status',[3,4]);//付费完成订单
        $normal['used_stocks'] = $query->where('course.status',1)->count();
        $hidden['used_stocks'] = $query->where('course.status',2)->count();

        return ['normal'=>$normal,'hidden'=>$hidden];
    }

    /**
     * 批量添加库存
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMultiStocks(Request $request){
        $post = $request->all();
        $validator = Validator::make($post, [
            'course_id'   => 'required',
            'add_number' => 'required',
        ],liveService::stocksMessage());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }

        $return = liveService::doaddStocks($post);
        return response()->json($return);
    }
}
