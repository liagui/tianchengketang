<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
class Articleaccessory extends Model {
    //指定别的表名
    public $table = 'ld_article_accessory';
    //时间戳设置
    public $timestamps = false;
}
