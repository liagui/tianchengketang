<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentError extends Model {
    //指定别的表名
    public $table      = 'ld_student_error_exam';
    //时间戳设置
    public $timestamps = false;


}
