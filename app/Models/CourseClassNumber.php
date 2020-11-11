<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Constraint\ExceptionMessage;

class CourseClassNumber extends Model {
    //指定别的表名
    public $table = 'ld_course_class_number';
    //时间戳设置
    public $timestamps = false;


}

