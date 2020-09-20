<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SubjectLesson;

class LessonStock extends Model {

    //指定别的表名
    public $table = 'ld_lesson_stocks';

    protected $fillable = [
        'admin_id',
    	'lesson_id',
        'school_pid',
        'school_id',
        'add_number',
        'current_number'
    ];

    protected $hidden = [
        'updated_at',
        'is_del',
        'is_forbid'
    ];

    public function lesson() {
        return $this->belongsTo('App\Models\Lesson');
    }

}
