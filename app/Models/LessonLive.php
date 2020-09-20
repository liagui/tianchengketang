<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonLive extends Model {

	//指定别的表名
    public $table = 'ld_lesson_lives';


    protected $fillable = [
        //'admin_id',
    	'live_id',
        'lesson_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'is_del',
        'is_forbid'
    ];

    public function lives() {
        return $this->belongsToMany('App\Models\Live', 'ld_lesson_lives');
    }
}

