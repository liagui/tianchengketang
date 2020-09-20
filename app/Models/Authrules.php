<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Authrules extends Model {
    //指定别的表名   权限表
    public $table = 'ld_auth_rules';
    //时间戳设置
    public $timestamps = false;
        /*
         * @param  descriptsion 权限查询
         * @param  $name    url名
         * @param  author  苏振文
         * @param  ctime   2020/4/25 15:51
         * return  array
         */
    public static function getAuthOne($name){

        $return = self::where(['name'=>$name])->first();
        return $return;
    }
    /*
     * @param  descriptsion 权限查询(全部)
     * @param  $auth_id     权限id组
     * @param  author      lys
     * @param  ctime   2020/4/27 15:00
     * return  array
     */
    public static function getAdminAuthAll($auth_id){
        //判断权限id是否为空
        if(empty($auth_id)){
            return ['code'=>202,'msg'=>'参数类型有误'];
        }
        $auth_id_arr = explode(',',$auth_id);

        if(!$auth_id_arr){
             $auth_id_arr = [$auth_id];
        }
        $authData = self::where(['is_show'=>1,'is_del'=>1,'is_forbid'=>1])->select('id','name')->get()->toArray(); //全部路由数据
        $authData = array_column($authData,'name','id');

        $authArr = AuthMap::whereIn('id',$auth_id_arr)->where(['is_del'=>0,'is_show'=>0,'is_forbid'=>0])->select('id','title','parent_id','auth_id')->get()->toArray();
        $arr = [];
        foreach($authArr as $k=>$v){
            if($v['parent_id'] == 0){
                $arr[] = $v;    
            }else{
                foreach ($arr as $key => $value) {
                    if($v['parent_id'] == $value['id']){
                        unset($v['id']);
                        $arr[$key]['child_array'][] = $v;
                    }
                }
            }
        }

        if(!empty($arr)){
            foreach($arr as $kk=>&$vv){
                $vv['name']  = !isset($authData[$vv['auth_id']]) ?'':substr(substr($authData[$vv['auth_id']],5),0,-8);
                if(isset($vv['child_array'])){
                    foreach ($vv['child_array'] as $kkk => &$vvv) {
                        $vvv['name']  = !isset($authData[$vvv['auth_id']]) ?'':substr($authData[$vvv['auth_id']],5);
                    }
                }else{
                    $vv['child_array'] = [];
                }
            }
        }
       
        if($arr){
            return ['code'=>200,'msg'=>'获取权限信息成功','data'=>$arr];
        }else{
            return ['code'=>204,'msg'=>'权限信息不存在,请联系管理员'];
        }
    }
        /*
         * @param  descriptsion 获取权限列表
         * @param  $where[
                    'id' => 权限id串
                    ...
                ]  查询条件
         * @param  $field  字段
         * @param  author  lys
         * @param  ctime   2020/4/30
         * return  array
         */
    public static function getAuthAlls($where=[],$field=['*']){
        return  self::where(function($query) use ($where){ 
            if(isset($where['id']) && $where['id'] != ''  ){
                $query->whereIn('id',$where['id']);
            }
        })->select($field)->get()->toArray();        
    }

   
}
