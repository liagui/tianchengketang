<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomPageConfig extends Model {
    //指定别的表名
    public $table      = 'ld_custom_page_config';
    //时间戳设置
    public $timestamps = false;
}
