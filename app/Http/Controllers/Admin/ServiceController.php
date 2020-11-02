<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolOrder;
use App\Models\ServiceRecord;
use Illuminate\Http\Request;
use App\Models\Service;
use Validator;

/**
 * 中控服务模块
 * @author laoxian
 */
class ServiceController extends Controller {

    //需要schoolid的方法
    protected $need_schoolid = [
        'orderIndex',//查看订单
        'orderDetail',//订单详情
        'recharge',//充值
        'purLive',//购买服务
        'purStorage',//空间
        'purFlow',//流量
        'stockRefund',//库存退费
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
            }else{
                $schools = School::find($schoolid);
                if(empty($schools)){
                    header('Content-type: application/json');
                    echo json_encode(['code' => '202', 'msg' => '找不到当前学校']);
                    die();
                }
            }
        }

        //删除
        //$data =  $request->except(['token']);
        //赋值 与 替换
        //$data =  $request->offsetSet('字段1',变量);
        //$request->merge(['字段1'=>1,'字段2'=>2]);
    }

    /**
     * 查看订单
     * @author laoxian
     */
    public function orderIndex(Request $request){
        $post = $request->all();
        $validator = Validator::make($post, [
            'status'   => 'integer',
            'type' => 'integer',
        ],Service::Message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $return = Service::getOrderlist($post);
        return response()->json($return);
    }

    /**
     * 订单详情
     */
    public function orderDetail(Request $request)
    {


    }

    /**
     * 网校充值线上
     * @param [
     *      schoolid int 网校
     *      howpay int 支付类型:2=银行汇款,3=支付宝,4=微信,5=其他
     *      money int 金额
     * ]
     * @author laoxian
     * @time 2020/10/30
     * @return array
     */
    public function recharge(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'paytype' => 'required|integer',
            'money' => 'required|min:1|numeric',
        ],Service::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }
        //执行
        $return = Service::recharge($post);
        return response()->json($return);
    }

    /**
     * 轮询充值结果
     * @param oid string 订单号
     * @author 赵老仙
     * @return array
     */
    public function recharge_res(Request $request)
    {
        $oid = $request->input('oid');
        if(!$oid){
            return response()->json(['code'=>201,'msg'=>'未获取查询条件']);
        }
        //查询订单
        $order = SchoolOrder::where('oid',$oid)->select('status')->first();
        if(empty($order)){
            return response()->json(['code'=>202,'msg'=>'找不到订单']);
        }
        //
        $status = $order->status==2?1:0;//1=支付成功,0=未支付或其他
        return response()->json(['code'=>200,'msg'=>'success','data'=>$status]);

    }

    /**
     * 购买服务-直播并发
     * 余额购买, 存入订单, 并更改订单支付状态, 改变账户余额, 不改动其他
     * @param [
     *      schoolid int 学校
     *      num int 数量
     *      money int 金额
     *      start_tiime date 时间
     *      end_time date 时间
     * ]
     */
    public function purLive(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'money' => 'required|min:1|numeric',
            'start_time' => 'required|date',
            'end_time' => 'required|date',
        ],Service::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }
        //执行
        $post['type'] = 1;//代表直播并发
        $return = Service::purService($post);
        return response()->json($return);

    }

    /**
     * 购买服务-空间
     * @param [
     *      schoolid int 学校
     *      num int 数量
     *      money int 金额
     *      month int 续费时长
     * ]
     */
    public function purStorage(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'month' => 'required|min:1|integer',
            'money' => 'required|min:1|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }

        //根据month生成start_time end_time
        $post = ServiceRecord::storageRecord($post);

        $post['type'] = 2;//代表空间
        $return = ServiceRecord::purService($post);
        return response()->json($return);
    }

    /**
     * 购买服务-流量
     * @param [
     *      schoolid int 学校
     *      num int 数量
     *      money int 金额说
     * ]
     */
    public function purFlow(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'money' => 'required|min:1|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }
        //执行
        $post['type'] = 3;//代表流量
        $post['end_time'] = date('Y-m-d H:i:s');//
        //end_time 不能为空, 原型图更改后无此字段, 暂定义一个默认字段
        $return = ServiceRecord::purService($post);
        return response()->json($return);
    }

    /**
     * 申请库存退费-返回可退费的库存数量
     * @param schoolid int 网校
     * @param courseid int 课程id(实为授权表id)
     * @author 赵老仙
     * @return array
     */
    public function stockRefund(Request $request)
    {
        $return = Service::preStockRefund($request->all());
        return response()->json($return);
    }

    /**
     * 申请库存退费-返回可退费金额
     * @param schoolid int 网校
     * @param courseid int 课程id(实为授权表id)
     * @param numleft  int 0-48退费数量
     * @param numright int 48-72退费数量
     * @author 赵老仙
     * @return array
     */
    public function stockRefundMoney(Request $request)
    {
        $return = Service::getCourseRefundMoney($request->all());
        return response()->json($return);
    }
}
