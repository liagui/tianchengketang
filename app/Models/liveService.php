<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Log;

class liveService extends Model {
    //指定别的表名   权限表
    public $table = 'ld_live_service';
    //时间戳设置
    public $timestamps = false;

    protected $fillable = [
        'id','name','isshow','short','create_at'
    ];

    protected $hidden = [
        'delete_at'
    ];

    //可批量修改字段
    protected static $multiFields = [
        'delete_at','isshow'
    ];

    //错误信息
    public static function message()
    {
        return [
            'name.required'  => json_encode(['code'=>'201','msg'=>'直播服务商名称不能为空']),
            'isshow.integer'   => json_encode(['code'=>'202','msg'=>'状态参数不合法'])
        ];
    }

    /**
     * 添加
     * @param [
     *  name string 直播商家名称
     *  isshow int 1=可用,2=不可用
     *  short string 描述
     * ]
     * @author laoxian
     * @ctime 2020/10/19
     * @return array
     */
    public static function add($params)
    {
        //补充参数并添加
        $lastid = self::insertGetId($params);
        if(!$lastid){
            return ['code'=>203,'msg'=>'添加失败, 请重试'];
        }

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/insert' ,
            'operate_method' =>  'insert' ,
            'content'        =>  '新增数据'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return ['code'=>200,'msg'=>'success'];//成功
    }

    /**
     * 查看记录
     * @param [
     *  page int 页码
     *  pagesize int 页大小
     * ]
     * @author laoxian
     * @ctime 2020/10/19
     * @return array
     */
    public static function getlist($params)
    {
        $page = (int) (isset($params['page']) && $params['page'])?$params['page']:1;
        $pagesize = (int) (isset($params['pagesize']) && $params['pagesize'])?$params['pagesize']:15;

        //固定条件
        $whereArr = [
            'delete_at'=>null
        ];

        //搜索条件
        if(isset($params['name']) && $params['name']){
            $whereArr[] = ['name','like','%'.$params['name'].'%'];
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
     * @param id int id标识
     * @author laoxian
     * @ctime 2020/10/19
     * @return array
     */
    public static function detail($params){
        $id = isset($params['id'])?$params['id']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }

        $row = self::where('id',$id)->first();
        return ['code'=>200,'msg'=>'success','data'=>$row];
    }

    /**
     * 修改
     * @param [
     *  name string 直播商家名称
     *  isshow int 1=可用,2=不可用
     *  short string 描述
     * ]
     * @author laoxian
     * @ctime 2020/10/19
     * @return array
     */
    public static function doedit($params){
        $id = isset($params['id'])?$params['id']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }
        $row = self::where('id',$id)->update($params);

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/doedit' ,
            'operate_method' =>  'doedit' ,
            'content'        =>  '修改'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return ['code'=>200,'msg'=>'success,影响了'.$row.'行'];
    }

    /**
     * 删除
     * @param id int
     * @author laoxian
     * @ctime 2020/10/19
     * @return array
     */
    public static function dodelete($params){
        $id = isset($params['id'])?$params['id']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }
        $row = self::where('id',$id)->update(['delete_at'=>time()]);

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/delete' ,
            'operate_method' =>  'delete' ,
            'content'        =>  '删除数据'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return ['code'=>200,'msg'=>'success,影响了'.$row.'行'];
    }

    /**
     * 批量更新
     * @param ids string 逗号连接的id字符串
     * @param param string 字段
     * @param value string 值
     * @author laoxian
     * @ctime 2020/10/19
     * @return array
     */
    public static function domulti($params){
        $ids = isset($params['ids'])?$params['ids']:die();
        $key = isset($params['params'])?$params['params']:die();
        $value = isset($params['value'])?$params['value']:die();

        if(!in_array($key,self::$multiFields)){
            return ['code'=>201,'msg'=>'不合法的key'];
        }

        //整理
        $idarr = explode(',',$ids);
        $value = $key=='delete_at'?time():$value;

        $i = self::whereIn('id',$idarr)->update([$key=>$value]);
        /*foreach($idarr as $a){
            if(is_numeric($a)){
                $res = self::where('id',$id)->update([$key=>$value]);
                $i+=$res;
            }
        }*/
        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/domulti' ,
            'operate_method' =>  'update' ,
            'content'        =>  '修改数据'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return ['code'=>200,'msg'=>'success,影响了'.$i.'行'];
    }

    /**
     * 修改网校直播商
     * @param [
     *  schoolid int 网校
     *  liveid int 直播类别
     * ]
     * @author laoxian
     * @ctime 2020/10/19
     * @return array
     */
    public static function updateLivetype($params){
        $id = isset($params['liveid'])?$params['liveid']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }
        $row = self::where('id',$id)->value('id');
        if(!$row){
            return ['code'=>202,'msg'=>'找不到当前直播商信息'];
        }

        //执行修改
        $res = School::where('id',$params['schoolid'])->update(['livetype'=>$id]);

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/updateLivetype' ,
            'operate_method' =>  'update' ,
            'content'        =>  '修改数据'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return ['code'=>200,'msg'=>'success,影响了'.$res.'行'];
    }

}
