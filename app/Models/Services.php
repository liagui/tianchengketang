<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Services extends Model {

    //指定别的表名
    public $table = 'ld_services';
    //时间戳设置
    public $timestamps = false;
}

