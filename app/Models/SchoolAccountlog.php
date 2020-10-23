<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolAccountlog extends Model {
    //指定别的表名
    public $table      = 'ld_schoole_account_logs';
    //时间戳设置
    public $timestamps = false;

    /**
     * @param $data
     * @return mixed
     */
    public static function insert($data)
    {
        $lastid = self::insertGetId($data);
        return $lastid;
    }


}
