<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roleauth extends Model {
    //指定别的表名
    public $table = 'ld_role_auth';
    //时间戳设置
    public $timestamps = false;

    protected $fillable = [
        'role_name', 'auth_id', 'auth_desc', 'admin_id', 'school_id'
    ];

    /*
     * @param  descriptsion 角色查询
     * @param  $id     参数
     * @param  author  苏振文
     * @param  ctime   2020/4/25 15:52
     * return  array
     */
    public static function getRoleOne($where=[],$field = ['*']){

        $return = self::where(function($query) use ($where){
                    if( isset($where['id'] ) && $where['id'] != ''){
                        $query->where('id','=',$where['id']);
                    }
                    if( isset($where['school_id'] ) && $where['school_id'] != ''){
                        $query->where('school_id','=',$where['school_id']);
                    }
                    if( isset($where['role_name'] ) && $where['role_name'] != ''){
                        $query->where('role_name','=',$where['role_name']);
                    }
                    if( isset($where['is_del'] ) && $where['is_del'] != ''){
                        $query->where('is_del','=',$where['is_del']);
                    }
                    if( isset($where['is_super'] ) && $where['is_super'] != ''){
                        $query->where('is_super','=',$where['is_super']);
                    }
        })->select($field)->first();
        //print_r($return);
         if($return){
            return ['code'=>200,'msg'=>'获取角色信息成功','data'=>$return];
        }else{
            return ['code'=>204,'msg'=>'角色信息不存在'];
        }
    }
    /*
     * @param  descriptsion  获取角色列表
     * @param  $where  查询条件
     * @param  $page   页码
     * @param  $limit  显示条件
     * @param  author  lys
     * @param  ctime   2020/4/29
     * return  array
     */
    public static function getRoleAuthAll($where=[],$page =1,$limit = 10){
        $return = self::where(function($query) use ($where){
                if($where['search'] != ''){
                    $query->where('role_name','like','%'.$where['search'].'%');
                    $query->where('school_id','=',$where['school_id']);
                }
            })->forPage($page,$limit)->get()->toArray();
        return $return;
    }
    /*
     * @param  descriptsion  获取角色列表
     * @param  $where  查询条件
     * @param  $filed  字段 {['id','name'])
     * @param  author  lys
     * @param  ctime   2020/4/29
     * return  int
     */
    public static function getRoleAuthAlls($where = [],$field =['*']){
        return self::where($where)->select($field)->get()->toArray();
    }
     /*
     * @param  descriptsion 更改状态方法
     * @param  $where  更改条件
     * @param  $update  更改数据
     * @param  author  lys
     * @param  ctime   2020/4/29
     * return  int
     */
    public static function upRoleStatus($where,$update){
          return self::where($where)->update($update);
    }

    /*
     * @param  descriptsion 添加角色方法
     * @param  $insert  添加数组
     * @param  author  lys
     * @param  ctime   2020/4/29
     * return  int
     */
    public static function doRoleInsert($insert){
          return self::insertGetId($insert);
    }





}
