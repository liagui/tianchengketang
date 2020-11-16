<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAgreement extends Model {
    //指定别的表名
    public $table      = 'ld_student_agreement';
    //时间戳设置
    public $timestamps = false;
}
