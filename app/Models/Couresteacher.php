<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Couresteacher extends Model {
    //指定别的表名
    public $table = 'ld_course_teacher';
    //时间戳设置
    public $timestamps = false;
}
