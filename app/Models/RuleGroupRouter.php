<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleGroupRouter extends Model {
    //指定别的表名
    public $table = 'ld_rule_group_router';
    //时间戳设置
    public $timestamps = false;

    // protected $fillable = [
    //     'role_id', 'group_id'
    // ];
}
