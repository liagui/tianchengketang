<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialListing extends Model {
    //指定别的表名
    public $table = 'material_listing';
    //时间戳设置
    public $timestamps = false;
}
