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
use Illuminate\Support\Facades\Log;

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
        'purStorageDate',//空间续费
        'purStorage',//空间容量升级
        'purFlow',//流量
        'stockRefund',//库存退费
        'stockRefundMoney',//根据退货数量查询可退费金额
        'dostockRefund',//执行退费
        'addShopCart',//加入购物车
        'shopCart',//购物车查看
        'shopCartManageOperate',//购物车数量管理
        'shopCartManageUpdate',//购物车数量直接操作
        'shopCartManageDel',//购物车删除
        'shopCartPay',//购物车去结算
        'preReplaceStock',//更换库存页面
        'replaceStockDetail',//更换库存详情
        'doReplaceStock',//执行更换库存
        'courseIndex',//总校课程
        'getLiveOrderInfo',//直播未支付订单去支付
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
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        $return = Service::getOrderlist($post);
        return response()->json($return);
    }
    //订单服务类型列表
    public function getTypeService(Request $request){
        $post = $request->all();
        $return['code'] = 200;
        $return['msg'] = "请求服务类型列表成功";
        $return['data'] = Service::getTypeService($post);
        return response()->json($return);
    }

    /**
     * 订单详情
     */
    public function orderDetail(Request $request)
    {
        $id = $request->input('id');
        $schoolid = $request->input('schoolid');

        if(!$id){
            return ['code'=>201,'msg'=>'订单id不能为空'];
        }
        $return = Service::getOrderDetail(['id'=>$id,'schoolid'=>$schoolid]);
        return $return;
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
        //参数整理
        $arr = ['paytype','schoolid','money'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }

        $validator = Validator::make($post, [
            'paytype' => 'required|integer',
            'money' => 'required|min:1|numeric',
        ],Service::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }
        //执行
        $return = Service::recharge($post);
        return response()->json($return);
    }

    /**
     * 网校充值 根据订单号重新发起支付
     * @param oid string 订单号
     * @author laoxian
     * @time 2020/11/10
     * @return array
     */
    public function againRecharge(Request $request)
    {
        //数据
        $oid = $request->input('oid');

        if(!$oid){
            return response()->json(['code'=>201,'msg'=>'订单号不能为空']);
        }
        //执行
        $return = Service::againRecharge($oid);
        return response()->json($return);
    }

    /**
     * 银行汇款重新支付->1,获取当前订单信息
     * @param oid string 订单号
     */
    public function bankPayInfo(Request $request)
    {
        $oid = $request->input('oid');

        if(!$oid){
            return response()->json(['code'=>201,'msg'=>'订单号不能为空']);
        }
        //执行
        $return = Service::bankPayInfo($oid);
        return response()->json($return);

    }

    /**
     * 银行汇款重新支付 [当前只可改变支付方式(支付宝,微信),不可改变已选金额]
     * @param oid string 订单号
     */
    public function bankAgainPay(Request $request)
    {
        $post['oid'] = $request->input('oid');
        $post['paytype'] = $request->input('paytype');


        if(!$post['oid']){
            return response()->json(['code'=>201,'msg'=>'订单号不能为空']);
        }
        //执行
        $return = Service::bankAgainPay($post);
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
        //参数整理
        $arr = ['num','start_time','end_time','schoolid','money','ispay'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'money' => 'required|min:0.01|numeric',
            'start_time' => 'required|date',
            'end_time' => 'required|date',
        ],Service::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }
        $post['start_time'] = substr($post['start_time'],0,10);//日期格式校正
        $post['end_time'] = substr($post['end_time'],0,10);//日期格式校正

        //1, 获取价格: 空间价格网校已设置时, 使用本网校设置的金额, 否则使用统一价格
        $live_price = School::where('id',$post['schoolid'])->value('live_price');
        $live_price = $live_price>0?$live_price:(ENV('LIVE_PRICE')?:0);

        //2, 购买时长
        $diff = diffDate($post['start_time'],$post['end_time']);

        //3,计算需要支付金额
        $money = 0;
        if($diff['year']){
            $money +=  $diff['year'] * $post['num'] * 12 * $live_price;
        }
        if($diff['month']){
            $money += $diff['month'] * $post['num'] * $live_price;
        }
        if($diff['day']){
            $money += round( $diff['day'] / 30 * $post['num'] * $live_price,2);
        }
        $post['money'] = $money;//计算出的金额

        //执行
        $post['type'] = 1;//代表直播并发
        $post['paytype'] = 5;//余额
        $post['status'] = 2;//支付状态:2=预定义已支付
        $return = Service::purService($post);
        return response()->json($return);

    }

    /**
     * 购买服务-空间:续费
     * @param [
     *      schoolid int 学校
     *      money int 金额
     *      month int 续费时长
     * ]
     */
    public function purStorageDate(Request $request)
    {
        //数据
        $post = $request->all();
        //参数整理
        $arr = ['month','schoolid','money'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }
        $validator = Validator::make($post, [
            //'num' => 'required|min:1|integer',
            'month' => 'required|min:1|integer',
            'money' => 'required|min:0.01|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        //学校身份执行查询当前空间订单状态
        $arr = SchoolOrder::school_querySchoolNowStorageOrderStatus($post['schoolid']);
        if($arr['code']!=200){
            return response()->json($arr);
        }

        $month = $post['month'];
        //根据month生成start_time end_time
        $post = ServiceRecord::storageRecord($post);

        //1, 获取价格: 空间价格网校已设置时, 使用本网校设置的金额, 否则使用统一价格
        $storage_price = School::where('id',$post['schoolid'])->value('storage_price');
        $storage_price = $storage_price>0?$storage_price:(ENV('STORAGE_PRICE')?:0);

        //2,计算需要支付金额
        $post['money'] = $month * $storage_price * $post['num'];// 月 * 价格 * 当前数量

        $post['type'] = 2;//代表空间
        $post['paytype'] = 5;//余额
        $post['status'] = 2;//支付状态:预定义已支付
        $sort = 1;//代表续费, 用于在model调用老马续费的接口
        $return = Service::purService($post,$sort);
        return response()->json($return);
    }

    /**
     * 购买服务-空间:扩容
     * @param [
     *      schoolid int 学校
     *      num int 容量
     *      money int 金额
     *      month int 续费时长
     * ]
     */
    public function purStorage(Request $request)
    {
        //数据
        $post = $request->all();
        //参数整理
        $arr = ['num','schoolid','money'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'money' => 'required|min:0.01|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        //学校身份执行查询当前空间订单状态
        $arr = SchoolOrder::school_querySchoolNowStorageOrderStatus($post['schoolid']);
        if($arr['code']!=200){
            return response()->json($arr);
        }

        //1, 获取价格: 空间价格网校已设置时, 使用本网校设置的金额, 否则使用统一价格
        $storage_price = School::where('id',$post['schoolid'])->value('storage_price');
        $storage_price = $storage_price>0?$storage_price:(ENV('STORAGE_PRICE')?:0);

        $num = $post['num'];//取出需扩容数量
        $money = 0;//定义代付金额

        //2, 根据month生成 本次服务购买的start_time end_time
        $post = ServiceRecord::storageRecord($post);
        if($post['end_time']=='0' || strtotime($post['end_time'])<time()){
            return ['code'=>205,'msg'=>'空间不在有效期内, 请先续费'];
        }

        //3.1, 计算剩余有效期
        $end_time = $post['end_time'];//当前有效期
        $diff = diffDate(date('Y-m-d'),mb_substr($end_time,0,10));

        //3.2,计算需补差价金额
        if($diff['year']){
            $money +=  $diff['year'] * $num * 12 * $storage_price;
        }
        if($diff['month']){
            $money +=  $diff['month'] * $num * $storage_price;
        }
        if($diff['day']){
            $money += round( $diff['day'] / 30 * $num * $storage_price,2);
        }
        $post['money'] = $money;//计算出的金额


        $post['type'] = 2;//代表空间
        $post['paytype'] = 5;//余额
        $post['status'] = 2;//支付状态
        $post['add_num'] = $num;//增加容量, 用于老马的接口
        $sort = 2;//代表扩容, 用于在model调用老马扩容的接口
        $return = Service::purService($post,$sort);
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
        //参数整理
        $arr = ['num','schoolid','money'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'money' => 'required|min:0.01|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        //1, 获取价格: 空间价格网校已设置时, 使用本网校设置的金额, 否则使用统一价格
        $flow_price = School::where('id',$post['schoolid'])->value('flow_price');
        $flow_price = $flow_price>0?$flow_price:(ENV('FLOW_PRICE')?:0);

        $post['money'] = $flow_price * $post['num'];

        //执行
        $post['type'] = 3;//代表流量
        $post['paytype'] = 5;//余额
        $post['status'] = 2;//在余额支付时,定义支付成功的状态
        $post['end_time'] = date('Y-m-d');//
        //end_time 不能为空, 原型图更改后无此字段, 暂定义一个默认字段
        $return = Service::purService($post);
        return response()->json($return);
    }

    /**
     * 分校查看总校的全部在售课程
     * +2020/11/10号, 用于总控 授权课程的时候展示总校的课程
     * @param schoolid int 学校
     * @param parentid int 一级学科
     * @param childid int 二级学科
     * @param type int 直播 or 点播
     * @param page int 页码
     * @param pagesize int 页大小
     */
    public function courseIndex(Request $request)
    {
        $post = $request->all();
        if(isset($post['nature']) && $post['nature'] != 1){
            $return = StockShopCart::onlyCourseSchool($post);
        }else {
            $return = StockShopCart::courseIndex($post);
        }
        return response()->json($return);

    }

    /**
     * 申请库存退费-返回可退费的库存数量
     * @param schoolid int 网校
     * @param courseid int 课程id
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
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        $return = Service::preStockRefund($post);
        return response()->json($return);
    }

    /**
     * 申请库存退费-返回可退费金额
     * @param schoolid int 网校
     * @param courseid int 课程id
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
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        $return = Service::getCourseRefundMoney($post);
        return response()->json($return);
    }

    /**
     * 申请库存退费-执行退费
     * @param schoolid int 网校
     * @param courseid int 课程id
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
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        $return = Service::doStockRefund($post);
        return response()->json($return);
    }

    /**
     * 加入库存购物车
     * @param schoolid int 学校
     * @param courseID int 课程id
     * @author 赵老仙
     */
    public function addShopCart(Request $request)
    {
        $post = $request->all();
        $validator = Validator::make($post, [
            'courseid' => 'required|integer',
        ],StockShopCart::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
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
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        $return = StockShopCart::ShopCartNumOperate($post);
        return response()->json($return);
    }

    /**
     * 购物车数量直接操作
     */
    public function shopCartManageUpdate(Request $request)
    {
        $post = $request->all();
        $validator = Validator::make($post, [
            'gid' => 'required|integer',
            'update_num' => 'required|integer|min:1',
        ],StockShopCart::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        $return = StockShopCart::shopCartManageUpdate($post);
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
            return response()->json(json_decode($validator->errors()->first(),true));
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
            return response()->json(json_decode($validator->errors()->first(),true));
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
            return response()->json(json_decode($validator->errors()->first(),true));
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
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        $return = StockShopCart::doReplaceStock($post);
        return response()->json($return);
    }

    /**
     * 库存订单
     * @param schoolid int 网校
     * @param status int 订单状态筛选
     */
    public function stockOrder(Request $request)
    {
        $return = StockShopCart::stockOrder($request->all());
        return response()->json($return);

    }



    /************余额不足生成的未支付订单--重新发起支付***********/

    /**
     * 获取直播并发订单信息
     */
    public function getLiveOrderInfo(Request $request)
    {
        //订单号
        $post = $request->all();
        if(!isset($post['oid']) || !$post['oid']){
            return response()->json(['code'=>201,'msg'=>'未找到订单号']);
        }
        //

        $post['type'] = 3;//直播
        $return = Service::getServiceOrderInfo($post);
        return $return;

    }

    /**
     * 直播并发订单去支付
     * @param $schoolid int 网校id
     * @param $oid string 订单号
     * @param //$money float 订单金额
     */
    public function liveOrderPay(Request $request)
    {
        //数据
        $post = $request->all();
        //参数整理
        $arr = ['schoolid','money','oid'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }
        $validator = Validator::make($post, [
            //'money' => 'required|min:1|numeric',
            'oid'   => 'required',
        ],Service::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        //获取订单信息
        $post['type'] = 3;//直播
        $record = Service::getServiceRecord($post);
        if($record['code']!=200){
            return response()->json($record);
        }
        $record = json_decode(json_encode($record),true);

        //补充信息,用于重新计算价格
        $post['num']        = $record['data']['content']['num'];
        $post['start_time'] = $record['data']['content']['start_time'];
        $post['end_time']   = $record['data']['content']['end_time'];

        //1, 获取价格: 空间价格网校已设置时, 使用本网校设置的金额, 否则使用统一价格
        $live_price = School::where('id',$post['schoolid'])->value('live_price');
        $live_price = $live_price>0?$live_price:(ENV('LIVE_PRICE')?:0);

        //2,计算需要支付金额:计算年月 不算日
        $post['money'] = $this->getMoney($post['start_time'],$post['end_time'],$live_price,$post['num'],2);


        //执行
        $return = Service::OrderAgainPay($post);
        return response()->json($return);

    }

    /**
     * 获取空间扩容订单的信息
     */
    public function getStorageOrderInfo(Request $request)
    {
        //订单号
        $post = $request->all();
        if(!isset($post['oid']) || !$post['oid']){
            return response()->json(['code'=>201,'msg'=>'未找到订单号']);
        }
        //

        $post['type'] = 4;//空间
        $return = Service::getServiceOrderInfo($post);
        return $return;

    }

    /**
     * 空间扩容去支付
     */
    public function storageOrderPay(Request $request)
    {
        //数据
        $post = $request->all();
        //参数整理
        $arr = ['oid','schoolid','money'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }
        $validator = Validator::make($post, [
            'oid'   => 'required',
            //'money' => 'required|min:1|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        //获取订单信息
        $post['type'] = 4;//空间扩容
        //获取扩容信息
        $record = Service::getOnlineStorageUpdateDetail($post['oid'],$post['schoolid']);
        //$record = Service::getServiceOrderInfo($post);
        /*if($record['code']!=200){
            return response()->json($record);
        }*/

        //补充信息
        $post['num']        = $record['num'];//最终的容量, 不是待扩容的数量
        $post['start_time'] = date('Y-m-d');
        $post['end_time']   = $record['end_time'];
        //待扩容的数量
        $post['add_num'] = $record['add_num'];

        //1, 获取价格: 空间价格网校已设置时, 使用本网校设置的金额, 否则使用统一价格
        $storage_price = School::where('id',$post['schoolid'])->value('storage_price');
        $storage_price = $storage_price>0?$storage_price:(ENV('STORAGE_PRICE')?:0);

        //获取需要 升级信息
        /*$record = SchoolOrder::getStorageUpdateDetail($post['schoolid']);
        if(!isset($record['num']) || $record['num']){
            return response()->json(['code'=>205,'msg'=>'网络异常, 请重试']);
        }*/



        //2, 根据month生成 本次服务购买的start_time end_time
        /*$post = ServiceRecord::storageRecord($post);
        if($post['end_time']=='0' || strtotime($post['end_time'])<time()){
            return ['code'=>205,'msg'=>'空间不在有效期内, 请先续费'];
        }*/

        //计算出的金额
        $post['money'] = $this->getMoney($post['start_time'],$post['end_time'],$storage_price,$post['add_num'],3);
        $post['sort'] = 1;//自定义一个参数, 代表扩容

        $return = Service::OrderAgainPay($post);

        return response()->json($return);

    }

    /**
     * 空间续费未支付订单的信息
     */
    public function getStorageDateOrderInfo(Request $request)
    {
        //订单号
        $post = $request->all();
        if(!isset($post['oid']) || !$post['oid']){
            return response()->json(['code'=>201,'msg'=>'未找到订单号']);
        }
        //

        $post['type'] = 4;//空间
        $return = Service::getServiceOrderInfo($post);
        return $return;
    }

    /**
     * 空间续费未支付订单去支付
     */
    public function storageDateOrderPay(Request $request)
    {
        //数据
        $post = $request->all();
        //参数整理
        $arr = ['oid','schoolid','money'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }
        $validator = Validator::make($post, [
            'oid'   => 'required',
            //'money' => 'required|min:1|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        //获取订单信息
        $post['type'] = 4;//空间
        $record = Service::getOnlineStorageUpdateDetail($post['oid'],$post['schoolid']);

        //补充信息
        $post['num']        = $record['num'];//当前容量
        $post['start_time'] = $record['start_time'];
        $post['end_time']   = $record['end_time'];//

        //1, 获取价格: 空间价格网校已设置时, 使用本网校设置的金额, 否则使用统一价格
        $storage_price = School::where('id',$post['schoolid'])->value('storage_price');
        $storage_price = $storage_price>0?$storage_price:(ENV('STORAGE_PRICE')?:0);

        //2,计算需要支付金额
        //计算出的金额
        $post['money'] = $record['month'] * $storage_price * $post['num'];
        $post['sort']  = 2;//自定义一个参数, 代表续费

        $return = Service::OrderAgainPay($post);
        return response()->json($return);
    }

    /**
     * 流量订单信息
     */
    public function getFlowOrderInfo(Request $request)
    {
        //订单号
        $post = $request->all();
        if(!isset($post['oid']) || !$post['oid']){
            return response()->json(['code'=>201,'msg'=>'未找到订单号']);
        }
        //

        $post['type'] = 5;//流量
        $return = Service::getServiceOrderInfo($post);
        return $return;

    }

    /**
     * 流量订单去支付
     */
    public function flowOrderPay(Request $request)
    {
        //数据
        $post = $request->all();
        //参数整理
        $arr = ['oid','schoolid','money'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }

        $validator = Validator::make($post, [
            'oid'   => 'required',
            //'money' => 'required|min:1|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        //获取订单信息
        $post['type'] = 5;//流量
        $record = Service::getServiceRecord($post);
        if($record['code']!=200){
            return response()->json($record);
        }

        $record = json_decode(json_encode($record),true);

        //补充信息
        $post['num']        = $record['data']['content']['num'];//购买流量
        //end_time 不能为空, 原型图更改后无此字段, 暂定义一个默认字段
        $post['end_time']   = $record['data']['content']['end_time'];

        //1, 获取价格: 空间价格网校已设置时, 使用本网校设置的金额, 否则使用统一价格
        $flow_price = School::where('id',$post['schoolid'])->value('flow_price');
        $flow_price = $flow_price>0?$flow_price:(ENV('FLOW_PRICE')?:0);

        $post['money'] = $flow_price * $post['num'];

        //执行
        $return = Service::OrderAgainPay($post);
        return response()->json($return);

    }

    /**
     * 库存购物车去结算-1, 获取订单信息
     */
    public function getStockShopCartOrderInfo(Request $request)
    {
        //订单号
        $post = $request->all();
        if(!isset($post['oid']) || !$post['oid']){
            return response()->json(['code'=>201,'msg'=>'未找到订单号']);
        }
        //

        $post['type'] = 7;//库存购物车
        $return = Service::getServiceOrderInfo($post);
        return $return;
    }

    /**
     * 库存购物车去支付
     */
    public function stockShopCartOrderPay(Request $request)
    {
        //数据
        $post = $request->all();
        //参数整理
        if(!$post['oid']){
            return response()->json(['code'=>201,'msg'=>'无效订单号']);
        }

        //执行
        $return = StockShopCart::stockShopCartOrderAgainPay($post);
        return response()->json($return);
    }

    /**
     * 库存更换订单去支付
     */
    public function getStockReplaceOrderInfo(Request $request)
    {
        //订单号
        $post = $request->all();
        if(!isset($post['oid']) || !$post['oid']){
            return response()->json(['code'=>201,'msg'=>'未找到订单号']);
        }
        //

        $post['type'] = 8;//库存更换
        $return = Service::getServiceOrderInfo($post);
        return $return;
    }

    /**
     * 库存更换订单去支付
     */
    public function stockReplaceOrderPay(Request $request)
    {
        //数据
        $post = $request->all();
        //参数整理
        if(!$post['oid']){
            return response()->json(['code'=>201,'msg'=>'无效订单号']);
        }

        //执行
        $return = StockShopCart::stockReplaceOrderAgainPay($post);
        return response()->json($return);
    }

    /**
     * 恢复失效库存更换订单的库存
     */
    public function recoveryRefundOrderStocks(Request $request)
    {
        $id = $request->input('id');
        if(!is_numeric($id)){
            return response()->json(['code'=>201,'msg'=>'获取订单失败']);
        }
        $return = StockShopCart::recoveryRefundOrderStocks($id,$request->input('schoolid'));
        return response()->json($return);
    }


    /**
     * 计算服务计算金额
     * @param $start_time date 开始时间
     * @param $end_time date 截止时间
     * @param $price float 价格
     * @param $num int 数量
     * @param $level int 计算级别,1=计算年,2=计算年月,3=计算年月日
     * @return $money float
     */
    public function getMoney($start_time,$end_time,$price,$num,$level = 3)
    {
        $diff = diffDate(mb_substr($start_time,0,10),mb_substr($end_time,0,10));

        //金额
        $money = 0;
        if($diff['year'] && $level >= 1){
            $money += $diff['year'] * $num * 12 * $price;
        }
        if($diff['month'] && $level >= 2){
            $money += $diff['month'] * $num * $price;
        }
        if($diff['day'] && $level >= 3){
            $money += round($diff['day'] / 30 * $num * $price,2);
        }

        return $money;

    }

    /**
     * 计算订单表数量入课程表salesnum字段, 更换新数据库时使用
     */
    public function CourseSales()
    {
        echo time().'<br>';
        $course_salesArr = DB::table('ld_order')
                            ->where('nature',0)
                            ->select(DB::raw('count(class_id) as total,class_id'))
                            ->groupBy('class_id')
                            ->get()->toArray();
        $course_salesArr = json_decode(json_encode($course_salesArr),true);
        echo count($course_salesArr).'<br>';

        $course_school_salesArr = DB::table('ld_order as order')
            ->join('ld_course_school as course','course.id','=','order.class_id')
            ->where('nature',1)
            ->select(DB::raw('count(order.class_id) as total,course.course_id'))
            ->groupBy('order.class_id')
            ->get()->toArray();
        $course_school_salesArr = json_decode(json_encode($course_school_salesArr),true);
        echo count($course_school_salesArr);
        echo '<br>';
        $saleArr = [];

        foreach($course_salesArr as $a){
            if( !isset($saleArr[$a['class_id']]) ){
                $saleArr[$a['class_id']] = $a['total'];
            }else{
                $saleArr[$a['class_id']] += $a['total'];
            }
        }

        foreach($course_school_salesArr as $a){
            if( !isset($saleArr[$a['course_id']]) ){
                $saleArr[$a['course_id']] = $a['total'];
            }else{
                $saleArr[$a['course_id']] += $a['total'];
            }
        }
        echo count($saleArr).'<br>';

        //print_r($saleArr);
        $i = 0;
        foreach($saleArr as $k=>$v){
            if($k%100==0){
                sleep(5);
            }
            DB::table('ld_course')->where('id',$k)->update(['salesnum'=>$v]);
            $i++;
        }
        echo time().'<br>';



    }

}
