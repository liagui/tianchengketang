<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolConfig extends Model {
    //指定别的表名
    public $table      = 'ld_school_config';
    //时间戳设置
    public $timestamps = false;
}
