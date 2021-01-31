<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;

class WxRouting extends Model {
    //指定别的表名
    public $table      = 'ld_order_wx_routing';
    //时间戳设置
    public $timestamps = false;
}
