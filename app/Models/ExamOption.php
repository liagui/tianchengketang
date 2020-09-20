<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;

class ExamOption extends Model {
    //指定别的表名
    public $table      = 'ld_question_exam_option';
    //时间戳设置
    public $timestamps = false;
}
