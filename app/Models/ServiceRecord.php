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
            $ordertype = [
                1=>['key'=>3,'field'=>'live_price'],
                2=>['key'=>4,'field'=>'storage_price'],
                3=>['key'=>5,'field'=>'flow_price'],
            ];
            $field = $ordertype[$params['type']]['field'];
            //价格
            $price = (int) School::where('id',$params['schoolid'])->value($field)?:env(strtoupper($field));

            //订单
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;//当前登录账号id
            $order = [
                'oid' => $oid,
                'school_id' => $params['schoolid'],
                'admin_id' => $admin_id,
                'type' => $ordertype[$params['type']]['key'],//充值
                'paytype' => 1,//内部支付
                'status' => 1,//待审核
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
            unset($params['schoolid']);
            unset($params['money']);
            if(isset($params['remark'])) unset($params['remark']);
            $params['price'] = $price;
            $lastid = self::insertGetId($params);
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
                    ->select('record.start_time','record.end_time')
                    ->where('order.school_id',$post['schoolid'])
                    ->where('order.status',2)//审核通过 or 购买成功
                    ->where('order.type',4)//空间
                    ->where('record.type',2)//空间
                    ->orderBy('order.id','desc')
                    ->first();
        $order = json_decode(json_encode($order),true);
        if($order){
            //在已购买基础上增加
            $time = strtotime($order['end_time']);
            $post['start_time'] = date('Y-m-d H:i:s',strtotime('+1 day',$time));
            $post['end_time'] = date('Y-m-d H:i:s',strtotime("+{$post['month']} month",$time));
        }else{
            //新增
            $time = time();
            $post['start_time'] = date('Y-m-d H:i:s',$time);
            $post['end_time'] = date('Y-m-d H:i:s',strtotime("+{$post['month']} month",$time));
        }
        unset($post['month']);

        return $post;
    }
}