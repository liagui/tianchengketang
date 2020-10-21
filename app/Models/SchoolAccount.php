<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use APP\Models\SchoolAccountlog;
use Log;

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
            'money.required' => json_encode(['code'=>'201','msg'=>'金额不能为空']),
            'money.integer' => json_encode(['code'=>'202','msg'=>'金额必须是整数']),
            'money.min'  => json_encode(['code'=>'202','msg'=>'金额不合法'])
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
     * @ctime 2020/10/16
     * @return array
     */
    public static function insertAccount($params)
    {
        //开启事务
        DB::beginTransaction();
        try{
            //网校数据
            $schools = School::find($params['schoolid']);

            //查看当前账户
            $balance_res = $params['type']==3?$schools->balance-$params['money']:$schools->balance+$params['money'];
            if($balance_res<0){
                return ['code'=>202,'msg'=>'操作失败, 当前可用余额'.$schools->balance];
            }

            //补充参数并添加
            $lastid = self::insertGetId($params);
            if(!$lastid){
                return ['code'=>203,'msg'=>'入账失败, 请重试'];
            }

            //更改余额
            $res = School::where('id',$params['schoolid'])->update(['balance'=>$balance_res]);
            if(!$res){
                DB::rollback();
                return ['code'=>204,'msg'=>'入账失败, 请重试'];
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
            $params['before_balance'] = $schools->balance;
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

            Log::info('网校入账记录'.json_encode($params));
            DB::commit();
            return ['code'=>200,'msg'=>'success'];//成功

        }catch(Exception $e){
            DB::rollback();
            Log::error('网校入账记录error'.json_encode($params) . $e->getMessage());
            return ['code'=>205,'msg'=>'未知错误'];
        }
    }

    /**
     * 查看记录
     * @param schoolid,page,pagesize int 网校,页码,页大小
     * @author laoxian
     * @ctime 2020/10/16
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
            'list'=>$list
        ];
        return ['code'=>200,'msg'=>'success','data'=>$data];
    }

    /**
     * 获取单条
     */
    public static function detail($params){
        $schoolid = $params['schoolid'];
        $id = (int) isset($params['id'])?$params['id']:1;

        $row = self::where('schoolid',$schoolid)->where('id',$id)->first();
        return ['code'=>200,'msg'=>'success','data'=>$row];
    }


}
