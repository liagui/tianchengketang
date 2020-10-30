<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
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
        'recharge',//充值
        'purLive',//购买服务
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
     *购买服务-空间
     */
    public function purStorage(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'end_time' => 'required|date',
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }
        //执行
        $post['type'] = 2;//代表空间
        $return = ServiceRecord::purService($post);
        return response()->json($return);
    }

    /**
     *购买服务-流量
     */
    public function purFlow(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'end_time' => 'required|date',
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }
        //执行
        $post['type'] = 3;//代表流量
        $return = ServiceRecord::purService($post);
        return response()->json($return);
    }

}
