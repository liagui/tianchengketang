<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model {
    //指定别的表名
    public $table      = 'ld_admin_operate_log';
    //时间戳设置
    public $timestamps = false;
    public static $admin_user;

    /*
     * @param  description   获取后端用户基本信息
     * @param  data          数组数据
     * @param  author        dzj
     * @param  ctime         2020-05-05
     * return  int
     */
    public static function getAdminInfo(){
        self::$admin_user['admin_user'] = \App\Tools\CurrentAdmin::user();
        return (object)self::$admin_user;
    }

    /*
     * @param  description   添加后台日志的方法
     * @param  data          数组数据
     * @param  author        dzj
     * @param  ctime         2020-04-27
     * return  int
     */
    public static function insertAdminLog($data) {

        if (empty($data['school_id'])) {
            $data['school_id'] = self::getAdminInfo()->admin_user->school_id;
        }
        return self::insertGetId($data);
    }
}
