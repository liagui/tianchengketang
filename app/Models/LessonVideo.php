<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonVideo extends Model {

	//指定别的表名
    public $table = 'ld_lesson_videos';


    protected $fillable = [
    	'video_id',
        'child_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'is_del',
        'is_forbid'
    ];
}

