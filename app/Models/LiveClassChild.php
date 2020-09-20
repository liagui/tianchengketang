<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveClassChild extends Model {

    //指定别的表名
    public $table = 'ld_live_class_childs';
    //时间戳设置
    public $timestamps = false;
}

