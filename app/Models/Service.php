<?php
namespace App\Models;

use App\Models\AdminLog;
use App\Models\SchoolOrder;
use App\Models\SchoolAccount;
use App\Tools\AlipayFactory;
use App\Tools\QRcode;
use App\Tools\WxpayFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use APP\Models\SchoolAccountlog;
use Log;

/**
 * 服务model
 * @author laoxian
 */
class Service extends Model {
    //指定别的表名   权限表
    public $table = '';
    //时间戳设置
    public $timestamps = false;

    //错误信息
    public static function message()
    {
        return [
            'schoolid.required'  => json_encode(['code'=>'201','msg'=>'网校标识不能为空']),
            'schoolid.integer'   => json_encode(['code'=>'202','msg'=>'网校标识不合法']),
            'schoolid.min'   => json_encode(['code'=>'202','msg'=>'网校标识不合法']),
            'money.required' => json_encode(['code'=>'202','msg'=>'请输入正确的金额']),
            'money.numeric' => json_encode(['code'=>'202','msg'=>'金额必须是正确数值']),
            'money.min'  => json_encode(['code'=>'202','msg'=>'金额不合法']),
            'paytype.required'  => json_encode(['code'=>'201','msg'=>'请选择规定的支付方式']),
            'paytype.integer'  => json_encode(['code'=>'202','msg'=>'支付方式不合法']),
            'num.required'  => json_encode(['code'=>'202','msg'=>'购买数量不能为空']),
            'num.min'  => json_encode(['code'=>'202','msg'=>'购买数量最少为1']),
            'num.integer'  => json_encode(['code'=>'202','msg'=>'购买数量不合法']),
            'start_time.required'  => json_encode(['code'=>'202','msg'=>'开始日期不能为空']),
            'start_time.date'  => json_encode(['code'=>'202','msg'=>'开始日期格式不正确']),
            'end_time.required'  => json_encode(['code'=>'202','msg'=>'截止使用日期不能为空']),
            'end_time.date'  => json_encode(['code'=>'202','msg'=>'截止使用日期格式不正确']),
        ];
    }

    /**
     * 线上充值
     * @param [
     *  schoolid int 学校id
     *  paytype int 支付方式
     *  money int 金额
     * ]
     * @author laoxian
     * @time 2020/10/30
     * @return array
     */
    public static function recharge($params)
    {
        //开启事务
        DB::beginTransaction();
        try{
            $oid = SchoolOrder::generateOid();
            $params['oid'] = $oid;

            //充值金额入库
            $params['type'] = 1;//充值
            $paytype = $params['paytype'];
            unset($params['paytype']);
            $lastid = SchoolAccount::insertGetId($params);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>203,'msg'=>'入账失败, 请重试'];
            }

            //订单
            $params['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;//当前登录账号id
            $order = [
                'oid' => $oid,
                'school_id' => $params['schoolid'],
                'admin_id' => $params['admin_id'],
                'type' => 1,//充值
                'paytype' => $paytype,//3=支付宝,4=微信
                'status' => 1,//未支付
                'online' => 1,//线上订单
                'money' => $params['money'],
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }

            //添加日志操作
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'SchoolData' ,
                'route_url'      =>  'admin/SchoolData/insert' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增数据'.json_encode($params) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            Log::info('网校线上充值记录_'.json_encode($params));
            if(!$paytype==2) {//银行汇款
                DB::commit();
                return ['code' => 200, 'msg' => 'success'];//成功
            }

            if($paytype==4){//微信
                //$wxpay = new WxpayFactory();
                //$number = date('YmdHis', time()) . rand(1111, 9999);
                //$price = 0.01;
                //$return = $wxpay->getPcPayOrder($number,$price);
                DB::rollBack();
                return ['code' => 201 , 'msg' => '生成二维码失败'];
            }

            if($paytype==3){//支付宝
                //获取总校支付信息id=2
                $payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>2])->first();
                if(empty($payinfo) || empty($payinfo['zfb_app_id']) || empty($payinfo['zfb_app_public_key'])){
                    DB::rollBack();
                    return response()->json(['code' => 202, 'msg' => '商户号为空']);
                }
                $alipay = new AlipayFactory(20);
                $order['title'] = '充值';
                $order['notify'] = '/admin/service/notify';
                $return = $alipay->createSchoolPay($order);

                if($return['alipay_trade_precreate_response']['code'] == 10000){
                    DB::commit();
                    return ['code' => 200 , 'msg' => '支付','data'=>$return['alipay_trade_precreate_response']['qr_code']];
                }else{
                    DB::rollBack();
                    return ['code' => 202 , 'msg' => '生成二维码失败'];
                }
            }
            DB::rollBack();
            return ['code' => 202 , 'msg' => '支付方式无效'];
        }catch(\Exception $e){
            DB::rollBack();
            Log::error('网校线上充值记录error_'.json_encode($params) . $e->getMessage());
            return ['code'=>205,'msg'=>'未知错误'];
        }
    }


    /**
     * 添加服务记录
     */
    public static function purService($params)
    {
        //开启事务
        try{
            $oid = SchoolOrder::generateOid();
            $params['oid'] = $oid;
            $ordertype = [
                1=>['key'=>3,'field'=>'live_price'],
                2=>['key'=>4,'field'=>'storage_price'],
                3=>['key'=>5,'field'=>'flow_price'],
            ];
            $field = $ordertype[$params['type']]['field'];
            //价格
            $schools = School::where('id',$params['schoolid'])->select($field,'balance')->first();
            $price = (int) $schools['price']>0?:env(strtoupper($field));
            if($price<=0){
                return ['code'=>208,'msg'=>'价格无效'];
            }
            //订单金额 对比 账户余额
            if($params['money']>$schools['balance']){
                return ['code'=>209,'msg'=>'账户余额不足'];
            }

            DB::beginTransaction();
            //订单
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;//当前登录账号id
            $order = [
                'oid' => $oid,
                'school_id' => $params['schoolid'],
                'admin_id' => $admin_id,
                'type' => $ordertype[$params['type']]['key'],//直播 or 空间 or 流量
                'paytype' => 5,// 余额支付
                'status' => 2,//直接已支付状态
                'money' => $params['money'],
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>201,'msg'=>'网络错误, 请重试'];
            }

            //余额扣除
            $res = School::where('id',$params['schoolid'])->decrement('balance',$params['money']);
            if(!$res){
                DB::rollBack();
                return ['code'=>201,'msg'=>'请检查余额是否充足'];
            }

            //服务记录
            unset($params['schoolid']);
            unset($params['money']);
            $params['price'] = $price;
            $lastid = ServiceRecord::insertGetId($params);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>202,'msg'=>'网络错误, 请重试'];
            }

            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'Service' ,
                'route_url'      =>  'admin/service/purservice' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增数据'.json_encode($params) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);

            Log::info('网校线上购买服务记录'.json_encode($params));
            DB::commit();
            return ['code'=>200,'msg'=>'success'];//成功

        }catch(\Exception $e){
            DB::rollback();
            Log::error('网校线上购买服务记录error'.json_encode($params) . $e->getMessage());
            return ['code'=>205,'msg'=>$e->getMessage()];
        }
    }

}
