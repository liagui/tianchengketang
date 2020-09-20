<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use App\Models\Exam;
use App\Models\Bank;
use App\Models\QuestionSubject;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class AuthMap extends Model {
    //指定别的表名
    public $table      = 'ld_auth_map';
    //时间戳设置
    public $timestamps = false;

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