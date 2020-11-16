<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agreement extends Model {
    //指定别的表名
    public $table      = 'ld_agreement';
    //时间戳设置
    public $timestamps = false;
}
