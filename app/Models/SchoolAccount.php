<?php
namespace App\Models;

use App\Models\SchoolOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use APP\Models\SchoolAccountlog;
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
            unset($data['remark']);

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
                'remark' => $params['remark'],
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
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'SchoolData' ,
                'route_url'      =>  'admin/SchoolData/insert' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增数据'.json_encode($params) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
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

}
