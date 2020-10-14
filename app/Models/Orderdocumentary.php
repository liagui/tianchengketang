<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Orderdocumentary extends Model {
    //指定别的表名
    public $table = 'order_documentary';
    //时间戳设置
    public $timestamps = false;

    public static function getStudentStatus($data){
        dd($data);
    }
}
