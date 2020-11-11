<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleRouter extends Model {
    //指定别的表名   权限路由表
    public $table = 'ld_rule_router';
    //时间戳设置
    public $timestamps = false;

}
