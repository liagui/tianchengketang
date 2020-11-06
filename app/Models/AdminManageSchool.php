<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminManageSchool extends Model {
    //指定别的表名
    public $table      = 'ld_admin_manage_school';
    //时间戳设置
    public $timestamps = false;

    /**
     * 获取管理员可管理的分校
     */
    public static function manageSchools($adminid)
    {
        $lists = self::where('admin_id',$adminid)->where('is_del',0)->pluck('school_id')->toArray();
        return $lists;
    }

}
