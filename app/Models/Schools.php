<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

// use App\Models\Teacher;
// use App\Models\Admin;
// use App\Models\CouresSubject;
// use App\Models\Coures;
// use App\Models\Couresmethod;

use App\Tools\CurrentAdmin;
class Schools extends Model {
    //指定别的表名
    public $table = 'ld_schools';
    //时间戳设置
    public $timestamps = false;




}
