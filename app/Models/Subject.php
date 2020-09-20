<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Subject extends Model {

    //指定别的表名
    public $table = 'ld_course_subject';
    //时间戳设置
    public $timestamps = false;
}

