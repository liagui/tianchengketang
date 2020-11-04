<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseSchool;
use App\Models\School;
use App\Models\SchoolOrder;
use App\Models\ServiceRecord;
use App\Models\StockShopCart;
use Illuminate\Http\Request;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
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
        'stockRefundMoney',//根据退货数量查询可退费金额
        'dostockRefund',//执行退费
        'addShopCart',//加入购物车
        'shopCart',//购物车查看
        'shopCartManageOperate',//购物车数量管理
        'shopCartManageDel',//购物车删除
        'shopCartPay',//购物车去结算
        'preReplaceStock',//更换库存页面
        'replaceStockDetail',//更换库存详情
        'doReplaceStock',//执行更换库存
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
     *      paytype int 支付类型:2=银行汇款,3=支付宝,4=微信,5=其他
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
     * 支付宝回调
     */
    public function aliNotify()
    {
        $arr = $_POST;
        $oid = $arr['out_trade_no'];
        Log::info('支付宝异步回调-'.date('Y-m-d H:i:s').':'.json_encode($arr));
        if($arr['trade_status'] == 'TRADE_SUCCESS'){
            $orders = Schoolorder::where(['oid'=>$oid])->first();
            if(empty($orders)){
                return 'fail';
            }
            if ($orders['status'] == 2) {
                return 'success';
            }else {
                try{
                    DB::beginTransaction();
                    //修改订单状态
                    $res = SchoolOrder::where('oid',$oid)->update(['status'=>2]);//修改为已支付状态
                    if(!$res){
                        DB::rollBack();
                        return 'fail';
                    }
                    if($res){
                        $res = School::where('id',$orders['school_id'])->increment('balance',$orders['money']);
                        if(!$res){
                            DB::rollBack();
                            return 'fail';
                        }
                    }

                    DB::commit();
                    return 'success';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return 'fail';
                }
            }
        }else{
            return 'fail';
        }
    }

    /**
     * 微信回调
     */
    public function wxNotify(Request $request)
    {

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
        $post = $request->all();
        $validator = Validator::make($post, [
            'courseid' => 'required|integer',
        ],Service::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $return = Service::preStockRefund($post);
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
        $post = $request->all();
        $validator = Validator::make($post, [
            'courseid' => 'required|integer',
            'numleft' => 'required|integer',
            'numright' => 'required|integer',
        ],Service::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $return = Service::getCourseRefundMoney($post);
        return response()->json($return);
    }

    /**
     * 申请库存退费-执行退费
     * @param schoolid int 网校
     * @param courseid int 课程id(实为授权表id)
     * @param numleft  int 0-48退费数量
     * @param numright int 48-72退费数量
     * @author 赵老仙
     * @return array
     */
    public function doStockRefund(Request $request)
    {
        $post = $request->all();
        $validator = Validator::make($post, [
            'courseid' => 'required|integer',
            'numleft' => 'required|integer',
            'numright' => 'required|integer',
        ],Service::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $return = Service::doStockRefund($post);
        return response()->json($return);
    }

    /**
     * 加入库存购物车
     * @param schoolid int 学校
     * @param courseID int 课程id(实为授权表id)
     * @author 赵老仙
     */
    public function addShopCart(Request $request)
    {
        $post = $request->all();
        $validator = Validator::make($post, [
            'courseid' => 'required|integer',
        ],StockShopCart::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $return = StockShopCart::addShopCart($post);
        return response()->json($return);
    }

    /**
     * 购物车查看
     * @param schoolid int 学校
     */
    public function shopCart(Request $request)
    {
        $schoolid = $request->schoolid;
        $return = StockShopCart::shopCart($schoolid);

        return response()->json($return);
    }

    /**
     * 购物车数量管理
     * @param schoolid int 学校
     * @param gid int 购物车id
     * @param operate string['in','de'] 增加 or 减少
     */
    public function shopCartManageOperate(Request $request)
    {
        $post = $request->all();
        $validator = Validator::make($post, [
            'gid' => 'required|integer',
            'operate' => 'required',
        ],StockShopCart::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $return = StockShopCart::ShopCartNumOperate($post);
        return response()->json($return);
    }

    /**
     * 购物车删除
     * @param schoolid int 学校
     * @param gid int 购物车id
     */
    public function shopCartManageDel(Request $request)
    {
        $post = $request->all();
        $validator = Validator::make($post, [
            'gid' => 'required|integer',
        ],StockShopCart::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $res = StockShopCart::where('id',$post['gid'])->where('school_id',$post['schoolid'])->delete();
        if($res){
            $arr = ['code'=>200,'msg'=>'success'];
        }else{
            $arr = ['code'=>201,'msg'=>'删除失败'];
        }
        return response()->json($arr);
    }

    /**
     * 购物车去结算
     * @param schoolid int 网校id
     */
    public function shopCartPay(Request $request)
    {
        $schoolid= $request->input('schoolid');
        $return = StockShopCart::shopCartPay($schoolid);
        return response()->json($return);
    }

    /**
     * 更换库存页面
     * @param schoolid int 网校
     * @param courseid int 课程
     */
    public function preReplaceStock(Request $request)
    {
        $post = $request->all();
        $validator = Validator::make($post, [
            'courseid' => 'required|integer',
        ],StockShopCart::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $return = StockShopCart::preReplaceStock($post);
        return response()->json($return);
    }

    /**
     * 根据退还数量获取更换库存详情
     * 返回更换库存详情
     * @param schoolid int 网校
     * @param courseid int 课程
     * @param ncourseid int 增加库存课程
     * @param stocks int 增加库存数
     */
    public function replaceStockDetail(Request $request)
    {
        $post = $request->all();
        $validator = Validator::make($post, [
            'courseid' => 'required|integer',
            'ncourseid' => 'required|integer',
            'stocks' => 'required|integer|min:1',
        ],StockShopCart::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $return = StockShopCart::replaceStockDetail($post);
        return response()->json($return);
    }

    /**
     * 执行更换库存
     * @param schoolid int 网校
     * @param courseid int 课程
     * @param ncourseid int 增加库存课程
     * @param stocks int 增加库存数
     */
    public function doReplaceStock(Request $request)
    {
        $post = $request->all();
        $validator = Validator::make($post, [
            'courseid' => 'required|integer',
            'ncourseid' => 'required|integer',
            'stocks' => 'required|integer|min:1',
        ],StockShopCart::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first()));
        }

        $return = StockShopCart::doReplaceStock($post);
        return response()->json($return);
    }
}
