<?php
namespace App\Models;

use App\Models\SchoolOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Log;

/**
 * 服务购买
 * @author laoxian
 */
class  ServiceRecord extends Model {
    //指定别的表名   权限表
    public $table = 'ld_service_record';
    //时间戳设置
    public $timestamps = false;

    protected $fillable = [
        'id','oid','price','num','start_time','end_time'
    ];

    protected $hidden = [
    ];

    //错误信息
    public static function message()
    {
        return [
            'num.min'  => json_encode(['code'=>'202','msg'=>'请输入正确的数量']),
            'num.required'  => json_encode(['code'=>'202','msg'=>'数量不能为空']),
            'num.integer'  => json_encode(['code'=>'202','msg'=>'请输入正确的数量']),
            'month.min'  => json_encode(['code'=>'202','msg'=>'请输入正确的购买时长']),
            'month.required'  => json_encode(['code'=>'202','msg'=>'购买时长不能为空']),
            'month.integer'  => json_encode(['code'=>'202','msg'=>'请输入正确的购买时长']),
            'money.numeric' => json_encode(['code'=>'202','msg'=>'金额必须是正确数值']),
            'money.min'  => json_encode(['code'=>'202','msg'=>'金额不合法']),
            'money.required'  => json_encode(['code'=>'202','msg'=>'金额不能为空']),
            'start_time.required'  => json_encode(['code'=>'202','msg'=>'日期不能为空']),
            'start_time.date'  => json_encode(['code'=>'202','msg'=>'日期格式不正确']),
            'end_time.required'  => json_encode(['code'=>'202','msg'=>'截止使用日期不能为空']),
            'end_time.date'  => json_encode(['code'=>'202','msg'=>'截止使用日期格式不正确']),
        ];
    }

    /**
     * 添加服务记录
     */
    public static function purService($params)
    {
        //开启事务
        DB::beginTransaction();
        try{
            $oid = SchoolOrder::generateOid();
            $params['oid'] = $oid;
            // 键值(1,2,3)与value中的[key,field]
            // 分别代表:[直播,空间,流量] 在服务记录表(service_record)的type值, school_order的type值, 与代表本服务价格的字段
            $ordertype = [
                1=>['key'=>3,'field'=>'live_price'],
                2=>['key'=>4,'field'=>'storage_price'],
                3=>['key'=>5,'field'=>'flow_price'],
            ];
            $field = $ordertype[$params['type']]['field'];
            //价格
            $price = (int) School::where('id',$params['schoolid'])->value($field);
            $price = $price>0?$price:(env(strtoupper($field))?:0);

            //订单
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;//当前登录账号id
            $order = [
                'oid' => $oid,
                'school_id' => $params['schoolid'],
                'admin_id' => $admin_id,
                'type' => $ordertype[$params['type']]['key'],//充值
                'paytype' => isset($params['paytype'])?$params['paytype']:1,//内部支付
                'status' => isset($params['status'])?$params['status']:1,//待审核
                'money' => $params['money'],
                'remark' => isset($params['remark'])?$params['remark']:'',
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>201,'msg'=>'网络错误, 请重试'];
            }

            //服务记录
            $params['price'] = $price;
            $record_info = $params;//赋值一个数组, 保证params数据完整性, 用于记录日志
            unset($record_info['schoolid']);
            unset($record_info['money']);
            /*unset($record_info['paytype']);
            unset($record_info['status']);*/
            if(isset($record_info['remark'])) unset($record_info['remark']);
            //入库
            $lastid = self::insertGetId($record_info);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>202,'msg'=>'网络错误, 请重试'];
            }

            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'PurService' ,
                'route_url'      =>  'admin/purservice/service' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增数据'.json_encode($params) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);

            Log::info('网校购买服务记录'.json_encode($params));
            DB::commit();
            return ['code'=>200,'msg'=>'success'];//成功

        }catch(\Exception $e){
            DB::rollback();
            Log::error('网校购买服务记录error'.json_encode($params) . $e->getMessage());
            return ['code'=>205,'msg'=>'未知错误'];
        }
    }

    /**
     * 根据month 得到空间的start_time end_time
     */
    public static function storageRecord($post)
    {
        $order = DB::table('ld_school_order as order')
                    ->join('ld_service_record as record','order.oid','=','record.oid')
                    ->select('record.start_time','record.end_time','record.num')
                    ->where('order.school_id',$post['schoolid'])
                    ->where('order.status',2)//审核通过 or 购买成功
                    ->where('order.type',4)//订单表4代表空间
                    ->where('record.type',2)//服务记录表2代表空间
                    ->orderBy('order.id','desc')//获取最后一条购买成功的记录
                    ->first();
        $order = json_decode(json_encode($order),true);
        if($order){
            //步骤不可改变, 先判断是否续费, 在判断是否扩容
            $num = (isset($post['num']) && $post['num']>0)?$post['num']:0;

            //在已购买基础上增加
            if(isset($post['month']) && $post['month']>0){
                //续费
                $time = strtotime($order['end_time']);
                $time = $time>time()?$time:time();//当前时间未过期, 本次续费记录从到期时间开始, 已过期则从time()开始
                $post['start_time'] = date('Y-m-d H:i:s',strtotime('+1 day',$time));
                $post['end_time'] = date('Y-m-d H:i:s',strtotime("+{$post['month']} month",$time));
                $post['num'] = $order['num'];
            }

            //判断是否需要扩容
            if($num){
                if(!isset($post['start_time'])){
                    $post['start_time'] = $order['start_time'];
                }
                if(!isset($post['end_time'])){
                    $post['end_time'] = $order['end_time'];
                }
                $post['num'] = $order['num']+$num;//原容量+本次增加容量
            }
        }else{
            //新增
            if(isset($post['month'])){
                //续费
                $time = time();
                $post['start_time'] = date('Y-m-d H:i:s',$time);
                $post['end_time'] = date('Y-m-d H:i:s',strtotime("+{$post['month']} month",$time));
                $post['num'] = (isset($post['num']) && $post['num']>0)?$post['num']:0;
            }else{
                //扩容, 定义为0:当前无有效期状态下不可扩容,提示用户请先续费
                $time = time();
                $post['start_time'] = 0;
                $post['end_time'] = 0;
                $post['num'] = 0;
            }

        }
        unset($post['month']);

        return $post;
    }
}
