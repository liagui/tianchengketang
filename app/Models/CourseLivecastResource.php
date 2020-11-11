<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Constraint\ExceptionMessage;

class CourseLivecastResource extends Model {
    //指定别的表名
    public $table = 'ld_course_livecast_resource';
    //时间戳设置
    public $timestamps = false;


}

