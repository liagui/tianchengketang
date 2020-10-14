<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model {
    //指定别的表名
    public $table      = 'category';
    //时间戳设置
    public $timestamps = false;
  
}