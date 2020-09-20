<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lecturer extends Model {

    //指定别的表名   权限表
    public $table = 'ld_lecturer_educationa';
    //时间戳设置
    public $timestamps = false;


}

