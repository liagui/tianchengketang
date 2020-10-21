<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleRuleGroup extends Model {
    //指定别的表名
    public $table = 'ld_role_rule_group';
    //时间戳设置
    public $timestamps = false;

    protected $fillable = [
        'role_id', 'group_id'
    ];
}
