<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use Validator;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionSubject as Subject;
use Illuminate\Support\Facades\Redis;

class CustomPageConfig extends Model {
    //指定别的表名
    public $table      = 'ld_custom_page_config';
    //时间戳设置
    public $timestamps = false;
}
