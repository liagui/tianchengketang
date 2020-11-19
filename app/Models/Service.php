<?php
namespace App\Models;

use App\Models\AdminLog;
use App\Models\SchoolOrder;
use App\Models\SchoolAccount;
use App\Models\CourseSchool;
use App\Models\CourseStocks;
use App\Models\Coures;
use App\Models\StockShopCart;
use App\Models\SchoolResource;
use App\Tools\AlipayFactory;
use App\Tools\QRcode;
use App\Tools\WxpayFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'month.min'  => json_encode(['code'=>'202','msg'=>'请输入正确的购买时长']),
            'month.required'  => json_encode(['code'=>'202','msg'=>'购买时长不能为空']),
            'month.integer'  => json_encode(['code'=>'202','msg'=>'请输入正确的购买时长']),
            'paytype.required'  => json_encode(['code'=>'201','msg'=>'请选择规定的支付方式']),
            'paytype.integer'  => json_encode(['code'=>'202','msg'=>'支付方式不合法']),
            'num.required'  => json_encode(['code'=>'202','msg'=>'购买数量不能为空']),
            'num.min'  => json_encode(['code'=>'202','msg'=>'购买数量最少为1']),
            'num.integer'  => json_encode(['code'=>'202','msg'=>'购买数量不合法']),
            'start_time.required'  => json_encode(['code'=>'202','msg'=>'开始日期不能为空']),
            'start_time.date'  => json_encode(['code'=>'202','msg'=>'开始日期格式不正确']),
            'end_time.required'  => json_encode(['code'=>'202','msg'=>'截止使用日期不能为空']),
            'end_time.date'  => json_encode(['code'=>'202','msg'=>'截止使用日期格式不正确']),
            'status.integer'  => json_encode(['code'=>'201','msg'=>'状态参数不合法']),
            'type.integer'   => json_encode(['code'=>'202','msg'=>'类型参数不合法']),
            'courseid.required'   => json_encode(['code'=>'202','msg'=>'课程参数不能为空']),
            'courseid.integer'   => json_encode(['code'=>'202','msg'=>'课程参数不合法']),
            'numleft.required'   => json_encode(['code'=>'202','msg'=>'0-48选择数量不能为空']),
            'numleft.integer'   => json_encode(['code'=>'202','msg'=>'0-48选择数量不合法']),
            'numright.required'   => json_encode(['code'=>'202','msg'=>'48-72选择数量不能为空']),
            'numright.integer'   => json_encode(['code'=>'202','msg'=>'48-72选择数量不合法']),
            'oid.required'   => json_encode(['code'=>'202','msg'=>'订单号不能为空']),
        ];
    }

    /**
     * 订单列表
     * @author laoxian
     * @return array
     */
    public static function getOrderlist($params)
    {
        $schoolid = $params['schoolid'];
        $page = (int) (isset($params['page']) && $params['page'])?$params['page']:1;
        $pagesize = (int) (isset($params['pagesize']) && $params['pagesize'])?$params['pagesize']:15;

        //预定义固定条件
        $whereArr = [
            ['school_id','=',$schoolid]//学校
        ];

        //搜索条件
        if(isset($params['status']) && $params['status']){
            $whereArr[] = ['status','=',$params['status']];//订单状态
        }
        if(isset($params['type']) && $params['type']){
            $types = ['a','b'];//预定义一个搜索结果一定为空的条件
            if($params['type']==1){
                $types = [1,2];
            }elseif($params['type']==2){
                $types = [3,4,5,6,7];
            }
            $whereArr[] = [function($query) use ($types){
                $query->whereIn('type', $types);
            }];

        }

        //总数
        //$total = self::where($whereArr)->count();
        //结果集
        $field = ['id','oid','school_id','type','paytype','status','money','remark','admin_remark','apply_time','operate_time'];
        //原生sql为, 查询线上订单与线下订单(online=0)的银行汇款订单汇总
        $query = SchoolOrder::where($whereArr)->whereRaw("(online = 1 or (online = 0 and type = 1 and paytype = 2) )");
        //总数
        $total = $query->count();
        $list = $query->select($field)->orderBy('id','desc')
            ->offset(($page-1)*$pagesize)
            ->limit($pagesize)->get()->toArray();
        $texts = SchoolOrder::tagsText(['pay','online_status','service','type']);
        foreach($list as $k=>$v){
            //订单类型
            $list[$k]['type_text'] = isset($texts['type_text'][$v['type']])?$texts['type_text'][$v['type']]:'';
            //支付类型
            $list[$k]['paytype_text'] = isset($texts['pay_text'][$v['paytype']])?$texts['pay_text'][$v['paytype']]:'';
            //订单状态
            $list[$k]['status_text'] = isset($texts['online_status_text'][$v['status']])?$texts['online_status_text'][$v['status']]:'';
            if($v['status']==1 && $v['type']==1 && $v['paytype']==2){
                $list[$k]['status_text'] = '汇款中';
            }
            //库存退费只有已退费一种状态
            if($v['type']==9){
                $list[$k]['status_text'] = '已退费';
            }
            //服务类型
            $list[$k]['service_text'] = isset($texts['service_text'][$v['type']])?$texts['service_text'][$v['type']]:'';
            //备注 and 管理员备注
            $list[$k]['remark'] = $v['remark']?:'';
            $list[$k]['admin_remark'] = $v['admin_remark']?:'';

            //当某订单 为[空间订单[并且[未支付], 判断此订单是扩容或续费
            if($v['type']==4 && $v['status']==1){
                $record = self::getOnlineStorageUpdateDetail($v['oid'],$v['school_id']);
                if($record['add_num']){
                    $list[$k]['type'] = 41;//判断为扩容
                }else{
                    $list[$k]['type'] = 42;//判断为续费
                }
            }
            //定义余额未支付状态下, 需要去支付的 获取订单信息 与 确认去支付的路由
            if($v['status']==1){
                switch($v['type']){
                    case 3:
                        //直播
                        $list[$k]['route_info'] = '/admin/service/orderpay/getLiveInfo';
                        $list[$k]['route_pay'] = '/admin/service/orderpay/live';
                        break;
                    case 41:
                        //扩容
                        $list[$k]['route_info'] = '/admin/service/orderpay/getStorageInfo';
                        $list[$k]['route_pay'] = '/admin/service/orderpay/storage';
                        break;
                    case 42:
                        //续费
                        $list[$k]['route_info'] = '/admin/service/orderpay/getStorageDateInfo';
                        $list[$k]['route_pay'] = '/admin/service/orderpay/storageDate';
                        break;
                    case 5:
                        //流量
                        $list[$k]['route_info'] = '/admin/service/orderpay/getFlowInfo';
                        $list[$k]['route_pay'] = '/admin/service/orderpay/flow';
                        break;
                }
            }

        }

        $data = [
            'total'=>$total,
            'total_page'=> ceil($total/$pagesize),
            'list'=>$list,
            //'texts'=>self::tagsText(['pay','status','service','type']),
        ];
        return ['code'=>200,'msg'=>'success','data'=>$data];
    }

    /**
     * 订单详情
     */
    public static function getOrderDetail($params)
    {
        $data = SchoolOrder::detail($params['id']);
        return $data;
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

        if(isset($params['/admin/service/recharge'])) unset($params['/admin/service/recharge']);
        try{
            $oid = SchoolOrder::generateOid();
            $params['oid'] = $oid;

            //充值金额入库
            $params['type'] = 1;//充值
            $paytype = $params['paytype'];
            unset($params['paytype']);
            $res = SchoolAccount::insert($params);
            if(!$res){
                DB::rollBack();
                return ['code'=>203,'msg'=>'入账失败, 请重试'];
            }

            //订单
            $params['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;//当前登录账号id
            $order = [
                'oid'        => $oid,
                'school_id'  => $params['schoolid'],
                'admin_id'   => $params['admin_id'],
                'type'       => 1,//充值
                'paytype'    => $paytype,//3=支付宝,4=微信
                'status'     => 1,//未支付
                'online'     => $paytype==2?0:1,//线上订单:paytype==2(银行汇款)
                'money'      => $params['money'],
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }

            //添加日志操作
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'SchoolData' ,
                'route_url'      =>  'admin/SchoolData/insert' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增数据'.json_encode($params) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            Log::info('网校线上充值记录_'.json_encode($params));
            if($paytype==2) {//银行汇款
                DB::commit();
                return ['code' => 200, 'msg' => 'success'];//成功
            }

            if($paytype==4){//微信
                $wxpay = new WxpayFactory();
                $return = $wxpay->getPcPayOrder($order);
                //DB::rollBack();
                DB::commit();

                return ['code' => 201 , 'msg' => '生成二维码失败'];
            }

            if($paytype==3){//支付宝
                //获取总校支付信息id=2
                $payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>1])->first();
                if(empty($payinfo) || empty($payinfo['zfb_app_id']) || empty($payinfo['zfb_app_public_key'])){
                    DB::rollBack();
                    return response()->json(['code' => 202, 'msg' => '商户号为空']);
                }
                $alipay = new AlipayFactory(1);
                $order['title'] = '充值';
                $order['notify'] = '/admin/service/aliNotify';
                $return = $alipay->createSchoolPay($order);

                if($return['alipay_trade_precreate_response']['code'] == 10000){
                    require_once realpath(dirname(__FILE__).'/../Tools/phpqrcode/QRcode.php');
                    $code = new QRcode();
                    ob_start();//开启缓冲区
                    $returnData  = $code->pngString($return['alipay_trade_precreate_response']['qr_code'], false, 'L', 10, 1);//生成二维码
                    $imageString = base64_encode(ob_get_contents());
                    ob_end_clean();
                    $str = "data:image/png;base64," . $imageString;

                    $arr['qrcode']=$str;
                    $arr['oid'] = $oid;
                    DB::commit();
                    return ['code' => 200 , 'msg' => '支付','data'=>$arr];
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
     * 根据订单号重新发起支付
     */
    public static function againRecharge($oid)
    {
        //status支付状态,paytype支付方式
        $orders = SchoolOrder::where('oid',$oid)->where('status',1)->whereIn('paytype',[3,4])->first();
        if(empty($orders)){
            return ['code'=>202,'msg'=>'找不到这个订单'];
        }
        $orders = json_decode(json_encode($orders),true);

        if($orders['paytype']==4){//微信
            /*$wxpay = new WxpayFactory();
            $return = $wxpay->getPcPayOrder($order);
            */
            return ['code' => 201 , 'msg' => '生成二维码失败'];
        }

        if($orders['paytype']==3){//支付宝
            //获取总校支付信息id=2
            $payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>1])->first();
            if(empty($payinfo) || empty($payinfo['zfb_app_id']) || empty($payinfo['zfb_app_public_key'])){
                return ['code' => 202, 'msg' => '商户号为空'];
            }
            $alipay = new AlipayFactory(1);
            $orders['title'] = '充值';
            $orders['notify'] = '/admin/service/aliNotify';
            $return = $alipay->createSchoolPay($orders);

            if($return['alipay_trade_precreate_response']['code'] == 10000){
                require_once realpath(dirname(__FILE__).'/../Tools/phpqrcode/QRcode.php');
                $code = new QRcode();
                ob_start();//开启缓冲区
                $returnData  = $code->pngString($return['alipay_trade_precreate_response']['qr_code'], false, 'L', 10, 1);//生成二维码
                $imageString = base64_encode(ob_get_contents());
                ob_end_clean();
                $str = "data:image/png;base64," . $imageString;

                $arr['qrcode']=$str;
                $arr['oid'] = $oid;

                return ['code' => 200 , 'msg' => '支付','data'=>$arr];
            }else{
                return ['code' => 202 , 'msg' => '生成二维码失败'];
            }
        }

    }

    /**
     * 获取当前(银行汇款)订单中的金额
     * @param oid string 订单那好
     */
    public static function bankPayInfo($oid)
    {
        //status支付状态,paytype支付方式
        $orders = SchoolOrder::where('oid',$oid)->where('status',1)->where('paytype',2)->first();
        if(empty($orders)){
            return ['code'=>202,'msg'=>'找不到这个订单'];
        }

        return [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'money'=>$orders->money
            ]
        ];
    }

    /**
     * 银行汇款重新支付
     */
    public static function bankAgainPay($params)
    {
        //status支付状态,paytype支付方式
        $orders = SchoolOrder::where('oid',$params['oid'])->where('status',1)->where('paytype',2)->first();
        if(empty($orders)){
            return ['code'=>202,'msg'=>'找不到这个订单'];
        }
        $orders = json_decode(json_encode($orders),true);

        //改变订单状态
        if($params['paytype']==2){
            //依然是选择了银行汇款,不做改变
            return ['code'=>200,'msg'=>'success'];
        }elseif($params['paytype']==3 || $params['paytype']==4){
            //online=1代表是线上订单,paytype代表是支付方式
            $res = SchoolOrder::where('oid',$params['oid'])->update(['online'=>1,'paytype'=>$params['paytype']]);
            if(!$res){
                return ['code'=>201,'msg'=>'请重新发起支付'];
            }
        }else{
            return ['code'=>205,'paytype'=>'支付方式不合法'];
        }

        if($params['paytype']==4){//微信
            /*$wxpay = new WxpayFactory();
            $return = $wxpay->getPcPayOrder($order);
            */
            return ['code' => 201 , 'msg' => '生成二维码失败'];
        }

        if($params['paytype']==3){//支付宝
            //获取总校支付信息id=2
            $payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>1])->first();
            if(empty($payinfo) || empty($payinfo['zfb_app_id']) || empty($payinfo['zfb_app_public_key'])){
                return ['code' => 202, 'msg' => '商户号为空'];
            }
            $alipay = new AlipayFactory(1);
            $orders['title'] = '充值';
            $orders['notify'] = '/admin/service/aliNotify';
            $return = $alipay->createSchoolPay($orders);

            if($return['alipay_trade_precreate_response']['code'] == 10000){
                require_once realpath(dirname(__FILE__).'/../Tools/phpqrcode/QRcode.php');
                $code = new QRcode();
                ob_start();//开启缓冲区
                $returnData  = $code->pngString($return['alipay_trade_precreate_response']['qr_code'], false, 'L', 10, 1);//生成二维码
                $imageString = base64_encode(ob_get_contents());
                ob_end_clean();
                $str = "data:image/png;base64," . $imageString;

                $arr['qrcode']=$str;
                $arr['oid'] = $orders['oid'];

                return ['code' => 200 , 'msg' => '支付','data'=>$arr];
            }else{
                return ['code' => 202 , 'msg' => '生成二维码失败'];
            }
        }

    }

    /**
     * 添加服务记录
     */
    public static function purService($params,$sort=0)
    {
        $oid = SchoolOrder::generateOid();
        $params['oid'] = $oid;
        $datetime = date('Y-m-d H:i:s');
        //新增add_num, 用于老马的接口
        $add_num = 0;
        if(isset($params['add_num'])){
            $add_num = $params['add_num'];
            unset($params['add_num']);
        }

        //根据type获取本次购买服务的配置信息
        $ordertype = [
            1=>['key'=>3,'field'=>'live_price'],
            2=>['key'=>4,'field'=>'storage_price'],
            3=>['key'=>5,'field'=>'flow_price'],
        ];
        //本服务在网站与数据库中的字段
        $field = $ordertype[$params['type']]['field'];

        //价格
        $schools = School::where('id',$params['schoolid'])->select($field,'balance','give_balance')->first();
        $price = (int) $schools[$field]>0?$schools[$field]:env(strtoupper($field));
        if($price<=0){
            return ['code'=>208,'msg'=>'价格无效'];
        }

        //整理杂项入数组
        $payinfo['oid']      = $oid;
        $payinfo['datetime'] = $datetime;
        $payinfo['price']    = $price;
        $payinfo['add_num']  = $add_num;
        $payinfo['sort']     = $sort;

        //订单金额 对比 账户余额,余额不足固定返回2090,用于前段判断是否去充值弹框
        $balance = $schools['balance'] + $schools['give_balance'];
        if($params['money']>$balance){
            //生成一个未支付订单
            $return = self::createNoPayOrder($params,$payinfo,$ordertype);
            if($return['code']!=200){
                return $return;
            }

            return [
                'code'=>2090,
                'msg'=>'账户余额不足,请充值',
                'data'=>[
                    'money'=>$params['money'],
                ]
            ];
        }

        //此时余额充足, 可判断是否是确认付费,
        //ispay=0代表是初次点击确认支付按钮, 需要返回一个询问确认付费状态, ispay=1时直接执行扣费
        /*if(!$params['ispay']){
            return [
                'code'=>2091,
                'msg'=>'本次需从您的账户余额扣费'.$params['money'].'元, 是否继续?',
                'data'=>[
                    'money'=>$params['money'],
                ]
            ];
        }*/
        //ispay已经用不到了, unset不入库参数
        //unset($params['ispay']);

        //创建一个支付状态为成功的订单
        $return = self::CreatePaySuccessOrder($params,$payinfo,$ordertype,$schools);
        //
        return $return;
    }

    /**
     * 购买服务
     * 当余额不足的时候, 生成一个待支付订单
     */
    public static function CreateNoPayOrder($params,$payinfo,$ordertype)
    {
        DB::beginTransaction();
        //开启事务
        try{
            //订单
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;//当前登录账号id
            $order = [
                'oid'        => $payinfo['oid'],
                'school_id'  => $params['schoolid'],
                'admin_id'   => $admin_id,
                'type'       => $ordertype[$params['type']]['key'],//直播 or 空间 or 流量
                'paytype'    => 5,// 余额支付
                'status'     => 1,//未支付状态
                'online'     => 1,//线上订单
                'money'      => $params['money'],
                'apply_time' => $payinfo['datetime'],
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>201,'msg'=>'网络错误, 请重试'];
            }

            //服务记录
            unset($params['schoolid']);
            unset($params['money']);
            unset($params['paytype']);
            unset($params['status']);
            $params['price'] = $payinfo['price'];
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
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  $payinfo['datetime'],
            ]);

            Log::info('网校线上购买服务记录_生成未支付订单'.json_encode($params));
            DB::commit();
            return ['code'=>200,'msg'=>'success'];//成功

        }catch(\Exception $e){
            DB::rollback();
            Log::error('网校线上购买服务记录_生成未支付订单error'.json_encode($params) . $e->getMessage());
            return ['code'=>205,'msg'=>$e->getMessage()];
        }
    }

    /**
     * 购买服务
     * 生成一个支付状态是成功的订单
     */
    public static function CreatePaySuccessOrder($params,$payinfo,$ordertype,$schools)
    {
        //开启事务
        DB::beginTransaction();
        //开启事务
        try{
            //余额扣除
            if($params['money']){
                $return_account = SchoolAccount::doBalanceUpdate($schools,$params['money'],$params['schoolid']);
                if(!$return_account['code']){
                    DB::rollBack();
                    return ['code'=>201,'msg'=>'请检查余额是否充足'];
                }
            }

            //订单
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;//当前登录账号id
            $order = [
                'oid'           => $payinfo['oid'],
                'school_id'     => $params['schoolid'],
                'admin_id'      => $admin_id,
                'type'          => $ordertype[$params['type']]['key'],//直播 or 空间 or 流量
                'paytype'       => 5,// 余额支付
                'status'        => 2,//直接已支付状态
                'online'        => 1,//线上订单
                'money'         => $params['money'],
                'use_givemoney' => isset($return_account['use_givemoney'])?$return_account['use_givemoney']:0,//用掉了多少赠送金额
                'apply_time'    => $payinfo['datetime'],
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>201,'msg'=>'网络错误, 请重试'];
            }

            //服务记录
            $schoolid = $params['schoolid'];
            unset($params['schoolid']);
            unset($params['money']);
            unset($params['paytype']);
            unset($params['status']);
            $params['price'] = $payinfo['price'];
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
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  $payinfo['datetime'],
            ]);

            $resource = new SchoolResource();
            //服务端调用->老马
            if($params['type']==1){
                // 网校个并发数 参数： 网校id 开始时间 结束时间 增加的并发数
                $resource ->addConnectionNum($schoolid,$params['start_time'],$params['end_time'],$params['num']);

            }elseif($params['type']==2){
                if($payinfo['sort']==1){
                    // 空间续费 参数:学校的id 延期时间（延期到哪年那月）
                    $resource ->updateSpaceExpiry($schoolid,$params['end_time']);
                }elseif($payinfo['sort']==2){
                    // 增加一个网校的空间 参数: 学校id 增加的空间 时间 固定参数add 固定参数video 固定参数是否使用事务 false
                    // 注意 购买空间 空间这里没有时间
                    $resource ->updateSpaceUsage($schoolid,$payinfo['add_num'], date("Y-m-d"),'add','video',false );
                }


            }elseif($params['type']==3){
                // 增加一个网校的流量 参数：学校id 增加的流量（单位B，helper中有参数 可以转化） 购买的日期  固定参数add 是否使用事务固定false
                // 注意 流量没时间 限制 随买随用
                $resource->updateTrafficUsage($schoolid,$params['num'], substr($payinfo['datetime'],0,10),"add",false);
            }

            Log::info('网校线上购买服务记录'.json_encode($params));
            DB::commit();
            return ['code'=>200,'msg'=>'success'];//成功

        }catch(\Exception $e){
            DB::rollback();
            Log::error('网校线上购买服务记录error'.json_encode($params) . $e->getMessage());
            return ['code'=>205,'msg'=>'遇到异常'];
        }
    }


    /**
     * 库存退费前需展示信息整合
     */
    public static function preStockRefund($params)
    {
        //授权表课程信息
        $course = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('course_id',$params['courseid'])
            ->select('id','course_id','parent_id','child_id','title')
            ->first();
        if(empty($course)){
            return ['code'=>202,'msg'=>'课程查找失败'];
        }
        $course = json_decode(json_encode($course),true);

        //科目名称
        $subjectArr = DB::table('ld_course_subject')
                ->whereIn('id',[$course['parent_id'],$course['child_id']])
                ->pluck('subject_name','id');
        $course['parent'] = isset($subjectArr[$course['parent_id']])?$subjectArr[$course['parent_id']]:'';
        $course['child'] = isset($subjectArr[$course['child_id']])?$subjectArr[$course['child_id']]:'';
        //课程id
        $params['course_id'] = $course['course_id'];
        //授权id
        $params['course_school_id'] = $course['id'];

        //当前课程目前库存详情
        $data = self::getCourseNowStockDetail($params);
        $data = $data + $course;
        return ['code'=>200,'msg'=>'success','data'=>$data];

    }

    /**
     * 获取单课程当前库存详情, 用于库存退费查看 or 对比
     */
    public static function getCourseNowStockDetail($params)
    {
        $whereArr = [
            ['school_id','=',$params['schoolid']],
            ['course_id','=',$params['course_id']],
            ['is_forbid','=','0'],
            ['is_del','=','0']
        ];
        $nowtime = time();
        $timeleft = date('Y-m-d H:i:s',strtotime('-48 hours',$nowtime));//48小时前
        $timeright = date('Y-m-d H:i:s',strtotime('-72 hours',$nowtime));//72小时前



        $total = (int) CourseStocks::where($whereArr)->selectRaw('sum(add_number) as total')->first()->total;
        $lists = CourseStocks::where($whereArr)->select('id','add_number','create_at')
                ->where('create_at','>',$timeright)->get()->toArray();
        //0< now <48hours  or 48<= now <72hours
        $num_left = 0;
        $num_right = 0;
        foreach($lists as $k=>$v){
            if($v['create_at']>$timeleft){
                //0-48小时内添加的库存
                $num_left+=$v['add_number'];
            }else{
                //48-72小时内添加的库存
                $num_right+=$v['add_number'];
            }
        }
        $wheres = ['school_id'=>$params['schoolid'],'oa_status'=>1,'nature'=>1,'status'=>2,'class_id'=>$params['course_school_id']];
        $use_stocks = Order::whereIn('pay_status',[3,4])->where($wheres)->count();
        //计算0-48 与 48-72小时这段时间添加的 可申请退费的库存 已经售卖的部分

        $real_stocks = $total-$use_stocks;//剩余真实库存
        $sure_refund = $num_left+$num_right;//72小时内上传的库存

        if( $real_stocks < $sure_refund){//真实剩余的库存 < 若72小时内(可申请退货的库存)
            $tmp = $sure_refund-$real_stocks;//
            if($num_right>=$tmp){//直接从48-72小时这一部分扣除
                $num_right = $num_right - $tmp;//
            }else{//48-72扣除后, 继续在0-48小时这一部分扣除
                $num_left = $num_left-($tmp-$num_right);
                $num_right = 0;
            }
        }
        return ['total'=>$total,'num_left'=>$num_left,'num_right'=>$num_right];
    }

    /**
     * 根据已选择的退货数量 返回可退费金额
     */
    public static function getCourseRefundMoney($params)
    {
        if(!$params['numleft'] && !$params['numright']){
            return ['code'=>203,'msg'=>'数量不能为空'];
        }
        //拿到授权表id
        $id = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('course_id',$params['courseid'])->value('id');
        $price = (int) Coures::where('id',$params['courseid'])->value('impower_price');//获取授权价格
        $params['course_id'] = $params['courseid'];
        $params['course_school_id'] = $id;//授权id
        //
        $stocks = self::getCourseNowStockDetail($params);
        if($params['numleft']>$stocks['num_left']){
            return ['code'=>205,'msg'=>'0-48超过可退费数量, 请重新提交退费'];
        }
        if($params['numright']>$stocks['num_right']){
            return ['code'=>206,'msg'=>'48-72超过可退费数量, 请重新提交退费'];
        }

        //可退费金额计算 0-48小时内全额退款,48-72退50%
        $left_money = $price * (int) $params['numleft'];
        $right_money = $price * (int) $params['numright'] * 0.5;
        $money = $left_money + $right_money;

        return [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'left_money'=>$left_money,
                'right_money'=>$right_money,
                'money'=>$money,
            ]
        ];
    }

    /**
     * 执行退费
     */
    public static function doStockRefund($params)
    {
        if(!$params['numleft'] && !$params['numright']){
            return ['code'=>203,'msg'=>'数量不能为空'];
        }
        //拿到授权表id
        $id = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('course_id',$params['courseid'])->value('id');
        $price = (int) Coures::where('id',$params['courseid'])->value('impower_price');//获取授权价格
        $params['course_id'] = $params['courseid'];
        $params['course_school_id'] = $id;
        //
        $stocks = self::getCourseNowStockDetail($params);
        if($params['numleft']>$stocks['num_left']){
            return ['code'=>205,'msg'=>'0-48超过可退费数量, 请重新提交退费'];
        }
        if($params['numright']>$stocks['num_right']){
            return ['code'=>206,'msg'=>'48-72超过可退费数量, 请重新提交退费'];
        }

        //可退费金额计算 0-48小时内全额退款,48-72退50%
        $money = $price * (int) $params['numleft'] + $price * (int) $params['numright'] * 0.5;

        //1, course_stocks课程库存表扣减库存, 2,并生成对应school_order订单表, 3, 并将余额加入学校总余额school->balance

        DB::beginTransaction();
        try{

            $oid = SchoolOrder::generateOid();
            $arr = [];
            $tmp = [];
            //
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

            $tmp['oid'] = $oid;
            $tmp['school_pid'] = $tmp['admin_id'] = $admin_id;
            $tmp['school_id'] = $params['schoolid'];
            $tmp['course_id'] = $params['courseid'];
            $tmp['price'] = $price;
            $tmp['create_at'] = date('Y-m-d H:i:s');

            //数量以
            if($params['numleft']){
                $tmp['add_number'] = 0-$params['numleft'];//退货:入库形式为负数
                $arr[] = $tmp;
            }
            if($params['numright']>0){
                $tmp['add_number'] = 0-$params['numright'];
                $arr[] = $tmp;
            }
            $res = CourseStocks::insert($arr);
            if(!$res){
                DB::rollBack();
                return ['code'=>207,'msg'=>'库存扣除失败, 请重试'];
            }

            //订单
            $order = [
                'oid'        => $oid,
                'school_id'  => $params['schoolid'],
                'admin_id'   => $admin_id,
                'type'       => 9,//库存退费
                'paytype'    => 5,//余额
                'status'     => 2,//已支付
                'online'     => 1,//线上订单
                'money'      => $money,
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }
            //账户余额
            if($money){
                $res = School::where('id',$params['schoolid'])->increment('give_balance',$money);
                if(!$res){
                    DB::rollBack();
                    return ['code'=>209,'msg'=>'网络错误'];
                }
            }

            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'SchoolData' ,
                'route_url'      =>  'admin/service/doStockRefund' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增数据'.json_encode($params) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);

            DB::commit();
            return ['code'=>200,'msg'=>'SUCCESS'];
        }catch(\Exception $e){
            DB::rollBack();
            Log::error('库存退费记录error_'.$e->getMessage() . json_encode($params));
            return ['code'=>208,'msg'=>$e->getMessage()];
        }
    }



    /************余额不足生成的未支付订单--重新发起支付***********/

    /**
     * 支付前的订单详情
     */
    public static function getServiceOrderInfo($params)
    {
        //find
        $wheres = [
            'school_id' => $params['schoolid'],
            'oid'       => $params['oid'],
            'online'    => 1,//线上订单,不可去掉
            'type'      => $params['type'],//订单类型:3=直播,4=空间......
        ];
        $order_id = SchoolOrder::where($wheres)->value('id');
        if(!$order_id){
            return ['code'=>203,'msg'=>'找不到当前订单'];
        }

        $data = SchoolOrder::detail($order_id);
        return $data;
    }

    /**
     * 获取服务订单信息, 的购买信息
     */
    public static function getServiceRecord($params)
    {
        //find
        $wheres = [
            'school_id' => $params['schoolid'],
            'oid'       => $params['oid'],
            'online'    => 1,//线上订单,不可去掉
            'type'      => $params['type'],//订单类型:3=直播,4=空间......
        ];
        $field = ['id','oid','type','paytype','status','money','apply_time'];
        $orders = SchoolOrder::where($wheres)->select($field)->first();
        if(empty($orders)){
            return ['code'=>203,'msg'=>'找不到当前订单'];
        }

        //service_record
        $field = ['num','start_time','end_time'];//price价格重新获取
        $recordArr = ServiceRecord::where('oid',$params['oid'])->select($field)->first();
        if(empty($recordArr)){
            return ['code'=>204,'msg'=>'订单信息错误'];
        }

        $orders['content'] = $recordArr;
        //
        return [
            'code'=>200,
            'msg'=>'success',
            'data'=>$orders,
        ];

    }

    /**
     * 去支付
     */
    public static function OrderAgainPay($params)
    {
        //根据type获取本次购买服务的配置信息
        $ordertype = [
            3=>'live_price',
            4=>'storage_price',
            5=>'flow_price',
        ];
        //本服务在网站与数据库中的字段
        $field = $ordertype[$params['type']];

        //价格
        $schools = School::where('id',$params['schoolid'])->select($field,'balance','give_balance')->first();
        $price = (int) $schools[$field]>0?$schools[$field]:env(strtoupper($field));
        if($price<=0){
            return ['code'=>208,'msg'=>'价格无效'];
        }

        //订单金额 对比 账户余额,余额不足固定返回2090,用于前段判断是否去充值弹框
        $balance = $schools['balance'] + $schools['give_balance'];
        if($params['money']>$balance){
            return [
                'code'=>209,
                'msg'=>'账户余额不足,请充值',
                'data'=>[
                    'money'=>$params['money'],
                ]
            ];
        }

        //修改订单表 与 服务记录表信息
        $params['price'] = $price;
        $return = self::UpdateServiceNoPayOrder($schools,$params);
        //
        return $return;
    }

    /**
     * 修改订单表 与 服务记录表信息
     */
    public static function UpdateServiceNoPayOrder($schools,$params)
    {
        $datetime = date('Y-m-d H:i:s');
        DB::beginTransaction();
        try{
            //账户扣款
            if($params['money']>0){
                $return_account = SchoolAccount::doBalanceUpdate($schools,$params['money'],$params['schoolid']);
                if(!$return_account['code']){
                    DB::rollBack();
                    return ['code'=>201,'msg'=>'请检查余额是否充足'];
                }
            }
            //修改订单表状态为成功,订单金额, 修改支付时间
            $update = [
                'status'        => 2,//success
                'money'         => $params['money'],
                'use_givemoney' => isset($return_account['use_givemoney'])?$return_account['use_givemoney']:0,//用掉了多少赠送金额
                'operate_time'  => $datetime,
            ];
            $res = SchoolOrder::where('oid',$params['oid'])->where('schoolid',$params['schoolid'])->update($update);
            if(!$res){
                DB::rollBack();
                return ['code'=>201,'msg'=>'支付失败, 请重试'];
            }

            //修改服务记录表的单价,价格一般没有变化, 只是执行, 不判断返回结果
            $record_update = [
                'price'=>$params['price'],
            ];
            if($params['type']==4){
                $record_update['start_time'] = $params['start_time'];
                $record_update['end_time'] = $params['end_time'];
            }
            ServiceRecord::where('oid',$params['oid'])->update($record_update);
            //提交
            DB::commit();

            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;//当前登录账号id
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'Service' ,
                'route_url'      =>  $_SERVER['REQUEST_URI'] ,
                'operate_method' =>  'update' ,
                'content'        =>  '新增数据'.json_encode($params) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  $datetime,
            ]);

            $resource = new SchoolResource();
            if($params['type']==3){
                //3=直播 服务端调用->老马 网校个并发数 参数： 网校id 开始时间 结束时间 增加的并发数
                $resource ->addConnectionNum($params['schoolid'],$params['start_time'],$params['end_time'],$params['num']);
            }elseif($params['type']==4){
                //4=空间
                if($params['sort']==1){
                    //扩容
                    $resource ->updateSpaceUsage($params['schoolid'],$params['add_num'], date("Y-m-d"),'add','video',false );
                }else{
                    //续费
                    $resource ->updateSpaceExpiry($params['schoolid'],substr($params['end_time'],0,1));
                }
            }elseif($params['type']==5){
                //5=流量
                $resource->updateTrafficUsage($params['schoolid'],$params['num'], date("Y-m-d"),"add",false);
            }

            return ['code'=>200,'msg'=>'success'];

        }catch(\Exception $e){
            DB::rollBack();
            Log::error('直播订单重新支付_error'. $e->getFile() . $e->getLine() . $e->getMessage() );
            return ['code'=>500,'msg'=>'遇到异常'];
        }

    }


    /**
     * 服务订单的未支付,进行重新支付时, 根据订单号查询本次空间订单的服务记录是[扩容]还是[续费]
     * 中控服务处不存在扩容与续费共同操作的方法, 只判断其中一种就可以
     */
    public static function getOnlineStorageUpdateDetail($oid,$schoolid)
    {
        //本条未支付的订单信息
        $record = ServiceRecord::where('oid',$oid)->first();
        //上一条未支付的订单信息
        $last_oid = SchoolOrder::where('school_id',$schoolid)->where('status',2)->orderByDesc('id')->value('oid');

        //当前订单之前不存在订单, 判断只有此一条有效订单
        if(!$last_oid){
            return $record;
        }
        //查询上一条订单的详情信息
        $last_record = ServiceRecord::where('oid',$last_oid)->first();

        $add_num = $last_record['num']-$record['num'];
        if( $add_num > 0 ){
            //扩容
            $record['add_num'] = $add_num;
        }else{
            //num无变化, 判断为续费, 取到本次应该设置的到期时间
            $record['add_num'] = 0;
            $diff = diffDate(mb_substr($record['start_time'],0,10),mb_substr($record['end_time'],0,10));

            //计算续费时间
            $month = 0;
            if($diff['year']){
                $month += (int) $diff['year'] * 12;
            }
            if($diff['month']){
                $month += (int) $diff['month'];
            }
            $record['month'] = $month;
            $record['start_time'] = date('Y-m-d');
            $record['end_time'] = date('Y-m-d',strtotime("+{$month} month",time() ));
        }

        //
        return $record;

    }

}
