<?php
namespace App\Models;

use App\Models\AdminLog;
use App\Models\SchoolOrder;
use App\Models\SchoolAccount;
use App\Models\CourseSchool;
use App\Models\CourseStocks;
use App\Models\Coures;
use App\Models\StockShopCart;
use App\Tools\AlipayFactory;
use App\Tools\QRcode;
use App\Tools\WxpayFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
            $whereArr[] = ['type','=',$params['type']];//订单类型
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
            $list[$k]['status_text'] = isset($texts['online_status'][$v['status']])?$texts['online_status'][$v['status']]:'';
            //服务类型
            $list[$k]['service_text'] = isset($texts['service_text'][$v['type']])?$texts['service_text'][$v['type']]:'';
            //备注 and 管理员备注
            $list[$k]['remark'] = $v['remark']?:'';
            $list[$k]['admin_remark'] = $v['admin_remark']?:'';
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
            $params['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;//当前登录账号id
            $order = [
                'oid' => $oid,
                'school_id' => $params['schoolid'],
                'admin_id' => $params['admin_id'],
                'type' => 1,//充值
                'paytype' => $paytype,//3=支付宝,4=微信
                'status' => 1,//未支付
                'online' => $paytype==2?0:1,//线上订单
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
            $price = (int) $schools[$field]>0?$schools[$field]:env(strtoupper($field));
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
            unset($params['paytype']);
            unset($params['status']);
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
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

            $tmp['oid'] = $oid;
            $tmp['school_id'] = $tmp['school_pid'] = $tmp['admin_id'] = $admin_id;
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
                'oid' => $oid,
                'school_id' => $params['schoolid'],
                'admin_id' => $admin_id,
                'type' => 9,//库存退费
                'paytype' => 5,//余额
                'status' => 2,//已支付
                'online' => 1,//线上订单
                'money' => $money,
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }
            //账户余额
            $res = School::where('id',$params['schoolid'])->increment('balance',$money);
            if(!$res){
                DB::rollBack();
                return ['code'=>209,'msg'=>'网络错误'];
            }

            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'SchoolData' ,
                'route_url'      =>  'admin/service/doStockRefund' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增数据'.json_encode($params) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);

            DB::commit();
            return ['code'=>200,'msg'=>'SUCCESS'];
            //Log::info('库存退费记录_'.json_encode($params));
        }catch(\Exception $e){
            DB::rollBack();
            return ['code'=>208,'msg'=>$e->getMessage()];
        }
    }




}
