<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\AdminLog;

class Datum extends Model {
    //指定别的表名
    public $table      = 'information';
    //时间戳设置
    public $timestamps = false;


}