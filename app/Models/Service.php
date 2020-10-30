<?php
namespace App\Models;

use App\Models\SchoolOrder;
use App\Models\SchoolAccount;
use App\Tools\AlipayFactory;
use App\Tools\QRcode;
use App\Tools\WxpayFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use APP\Models\SchoolAccountlog;
use App\Models\AdminLog;
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
            DB::commit();

            if(!in_array($paytype,[3,4])) {
                return ['code' => 200, 'msg' => 'success'];//成功
            }

            if($paytype==3333){
                //$wxpay = new WxpayFactory();
                //$number = date('YmdHis', time()) . rand(1111, 9999);
                //$price = 0.01;
                //$return = $wxpay->getPcPayOrder($number,$price);
                return ['code' => 202 , 'msg' => '生成二维码失败'];
            }

            if($paytype==3){
                //获取总校支付信息id=2
                /*$payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>2])->first();
                if(empty($payinfo) || empty($payinfo['zfb_app_id']) || empty($payinfo['zfb_app_public_key'])){
                    return response()->json(['code' => 202, 'msg' => '商户号为空']);
                }*/

                $alipay = new AlipayFactory(2);
                $order['title'] = '充值';
                $order['notify'] = '/admin/service/notify';
                $return = $alipay->createSchoolPay($order);

                if($return['alipay_trade_precreate_response']['code'] == 10000){
                    echo '<img src='.$return['alipay_trade_precreate_response']['qr_code'].' >';die();
                    return ['code' => 200 , 'msg' => '支付','data'=>$return['alipay_trade_precreate_response']['qr_code']];
                }else{
                    return ['code' => 202 , 'msg' => '生成二维码失败'];
                }
            }



        }catch(\Exception $e){
            DB::rollback();
            Log::error('网校线上充值记录error_'.json_encode($params) . $e->getMessage());
            echo  $e->getMessage();
            return ['code'=>205,'msg'=>'未知错误'];
        }
    }

}
