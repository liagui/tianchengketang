<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAccountlog extends Model {
    //指定别的表名
    public $table      = 'ld_student_account_logs';
    //时间戳设置
    public $timestamps = false;


}
