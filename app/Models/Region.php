<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model {
    //指定别的表名
    public $table      = 'ld_region';
    //时间戳设置
    public $timestamps = false;
}
