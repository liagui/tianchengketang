<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use App\Models\Exam;
use App\Models\Bank;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QuestionBank extends Model {
    //指定别的表名
    public $table      = 'ld_question_bank';
    //时间戳设置
    public $timestamps = false;
}