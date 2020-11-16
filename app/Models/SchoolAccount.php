<?php
namespace App\Models;

use App\Models\SchoolOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\SchoolAccountlog;
use App\Models\School;
use Log;

/**
 * 充值
 * @author laoxian
 */
class SchoolAccount extends Model {
    //指定别的表名   权限表
    public $table = 'ld_school_account';
    //时间戳设置
    public $timestamps = false;

    protected $fillable = [
        'id','schoolid','type','money','time','remark'
    ];

    protected $hidden = [
        'created_at'
    ];

    //错误信息
    public static function message()
    {
        return [
            'schoolid.required'  => json_encode(['code'=>'201','msg'=>'网校标识不能为空']),
            'schoolid.integer'   => json_encode(['code'=>'202','msg'=>'网校标识不合法']),
            'schoolid.min'   => json_encode(['code'=>'202','msg'=>'网校标识不合法']),
            'type.required'  => json_encode(['code'=>'201','msg'=>'类型不能为空']),
            'type.integer'   => json_encode(['code'=>'202','msg'=>'类型参数不合法']),
            'type.min'   => json_encode(['code'=>'202','msg'=>'类型参数不合法']),
            //'money.required' => json_encode(['code'=>'201','msg'=>'金额不能为空']),
            'money.numeric' => json_encode(['code'=>'202','msg'=>'金额必须是正确数值']),
            'money.min'  => json_encode(['code'=>'202','msg'=>'金额不合法']),
            'give_money.numeric' => json_encode(['code'=>'202','msg'=>'增偶是那个金额必须是正确数值']),
            'give_money.min'  => json_encode(['code'=>'202','msg'=>'赠送金额不合法']),
            'paytype.required'  => json_encode(['code'=>'201','msg'=>'请选择规定的支付方式']),
            'paytype.integer'  => json_encode(['code'=>'202','msg'=>'支付方式不合法']),
        ];
    }

    /**
     * 添加
     * @param [
     *  schoolid int 学校id
     *  type int 入账方式
     *  money int 金额
     * ]
     * @author laoxian
     * @time 2020/10/16
     * @return array
     */
    public static function insertAccount($params)
    {
        //开启事务
        DB::beginTransaction();
        try{
            $oid = SchoolOrder::generateOid();
            $params['oid'] = $oid;

            $data = $params;
            if(isset($data['remark'])) unset($data['remark']);

            //充值金额入库
            $money = 0;//订单表金额
            if(isset($data['money'])){
                $money += $data['money'];
                $data['type'] = 1;//充值金额
                unset($data['give_money']);
                $lastid = self::insertGetId($data);
                if(!$lastid){
                    DB::rollBack();
                    return ['code'=>203,'msg'=>'入账失败, 请重试'];
                }
            }
            //赠送金额
            if(isset($params['give_money'])){
                $data['type'] = 2;//赠送金额
                $data['money'] = $params['give_money'];
                $money += $data['money'];
                $lastid2 = self::insertGetId($data);
                if(!$lastid2){
                    DB::rollBack();
                    return ['code'=>204,'msg'=>'入账失败, 请重试'];
                }
            }

            //订单
            //遍历添加库存表完成(is_del=1,未生效的库存), 执行订单入库
            $params['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;//当前登录账号id
            $order = [
                'oid' => $oid,
                'school_id' => $params['schoolid'],
                'admin_id' => $params['admin_id'],
                'type' => 1,//手动打款
                'paytype' => 1,//内部支付
                'status' => 1,//待审核
                'online' => 0,//线下订单
                'money' => $money,
                'remark' => isset($params['remark'])?$params['remark']:'',
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }

            //变动日志
            /*SchoolAccountlog::insert([
                'schoolid'=>$data['schoolid'],
                'type'=>$data['type'],
                'money'=>$data['money'],
                'balance'=>$balance,
                'create_at'=>date('Y-m-d H:i:s'),
            ]);*/

            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>  $params['admin_id'] ,
                'module_name'    =>  'SchoolData' ,
                'route_url'      =>  'admin/SchoolData/insert' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增数据'.json_encode($params) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);

            Log::info('网校充值记录_'.json_encode($params));
            DB::commit();
            return ['code'=>200,'msg'=>'success'];//成功

        }catch(\Exception $e){
            DB::rollback();
            Log::error('网校充值记录error_'.json_encode($params) . $e->getMessage());
            return ['code'=>205,'msg'=>'未知错误'];
        }
    }

    /**
     * 查看记录
     * @param schoolid,page,pagesize int 网校,页码,页大小
     * @author laoxian
     * @time 2020/10/16
     * @return array
     */
    public static function getlist($params)
    {
        $schoolid = $params['schoolid'];
        $page = (int) (isset($params['page']) && $params['page'])?$params['page']:1;
        $pagesize = (int) (isset($params['pagesize']) && $params['pagesize'])?$params['pagesize']:15;

        //固定条件
        $whereArr = [
            'schoolid'=>$schoolid
        ];

        //搜索条件
        if(isset($params['title']) && $params['title']){
            $whereArr['title'] = ['like','%'.$title.'%'];
        }

        //总数
        $total = self::where($whereArr)->count();
        //结果集
        $list = self::where($whereArr)->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();
        $data = [
            'total'=>$total,
            'total_page'=> ceil($total/$pagesize),
            'list'=>$list
        ];
        return ['code'=>200,'msg'=>'success','data'=>$data];
    }

    /**
     * 获取单条
     * @author laoxian
     *
     */
    public static function detail($params){
        $schoolid = $params['schoolid'];
        $id = (int) isset($params['id'])?$params['id']:1;

        $row = self::where('schoolid',$schoolid)->where('id',$id)->first();
        return ['code'=>200,'msg'=>'success','data'=>$row];
    }

    /**
     * 获取当前余额详情
     * 充值余额 and 赠送余额(退款金额属于赠送金额:库存退货, 库存更换)
     */
    public static function getbalancesDetail($schoolid)
    {
        //1, 获取SchoolAccount充值金额
        $wheres = [
            'schoolid'=>$schoolid,//学校
            'type'=>1,//充值
            'status'=>2,//成功状态
        ];
        $total_rechange_money = self::where($wheres)->sum('money');

        //2, 获取SchoolAccount赠送金额
        $wheres['type'] = 2;//type改为2,赠送金额
        $total_give_money = self::where($wheres)->sum('money');


        //3, 获取SchoolOrder 退费金额 : 在网站中归属赠送金额
        $wheres = [
            'school_id'=>$schoolid,
            'status'=>2,//退费成功
            'type'=>9,//代表退费订单
        ];
        $total_refund_money = SchoolOrder::where($wheres)->sum('money');

        //4, 获取SchoolOrder 消费金额 (消费的充值金额 + 消费的赠送金额)
        $wheres = [
            ['school_id','=',$schoolid],//学校
            ['status','=',2],//订单成功
        ];
        $types = [3,4,5,6,7,8];//消费类型的订单
        $wheres[] = [function($query) use ($types){
            $query->whereIn('type', $types);
        }];

        //4.1消费充值金额
        $use_money = SchoolOrder::where($where)->sum('money');
        //4.2消费赠送金额
        $use_givemoney = SchoolOrder::where($where)->sum('use_givemoney');

        //当前剩余充值金额
        $money = $total_rechange_money - $use_money;
        //当前剩余赠送金额
        $give_money = ($total_give_money + $total_refund_money) - $use_givemoney;

        return [
            'money'=>$money,
            'give_money'=>$give_money,
        ];

    }

    /**
     * 执行订单消费, 充值余额 与 赠送余额的扣除
     * @param $school array [key 账户现有充值金额balance] [key 现有赠送余额give_balance]
     * @param $schoolid int 学校id
     * @param $money float 订单金额
     * @return array [code 状态] [use_givemoney 要从赠送金额中扣除的数目]
     */
    public static function doBalanceUpdate($schools, float $money, int $schoolid)
    {
        if(gettype($schools)=='object'){
            $schools = json_decode($schools,true);
        }
        $school_account = [];
        $use_givemoney = 0;//要从赠送金额中扣除的数目

        if(!$money){
            return ['code'=>1,'tmp_money'=>$use_givemoney];
        }

        if($schools['balance']>=$money){
            //充值余额充足, 只扣充值余额
            $school_account['balance'] = $schools['balance'] - $money;
            $school_account['give_balance'] = $schools['give_balance'];
        }else{
            //充值余额不足, 先扣除充值余额, 再从赠送金额中扣除(订单金额大于充值金额的部分)
            $school_account['balance'] = 0;
            $use_givemoney = $money - $schools['balance'];
            $school_account['give_balance'] = $schools['give_balance'] - $use_givemoney;
        }

        $res = School::where('id',$schoolid)->update($school_account);

        return ['code'=>$res,'use_givemoney'=>$use_givemoney];

    }

    public static function getbalancesDetail_two()
    {

        //第一种方法, 只有一个余额字段
        //每次消费余额时查询 当前余额中 充值金额与赠送金额的组成部分
        //适合余额支付不频繁的结构
        //需要执行余额详情的地方: 余额订单消费,
        //------------------------------------------------
        //第二种方法, 分两个字段充值余额 + 赠送余额

        //充值金额入 money
        //赠送金额入 give_money
        //退费金额入 give_money
        //消费金额 money > give_money

        //每次消费, 根据两个字段综合判断

        //现有结构中
        //1, 返回余额详情需要更改
        //2, 余额是否充足需要更改
        //3, 订单完成关联余额需要更改

    }

}
