<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Couresmaterial extends Model {
    //指定别的表名
    public $table = 'ld_course_material';
    //时间戳设置
    public $timestamps = false;
}
