<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoLog extends Model {
    //指定别的表名
    public $table      = 'ld_video_log';
    //时间戳设置
    public $timestamps = false;

    protected $fillable = ['play_position'];
}
