<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
class MyMessage extends Model {
    //指定别的表名
    public $table = 'ld_my_message';
    //时间戳设置
    public $timestamps = false;





}
