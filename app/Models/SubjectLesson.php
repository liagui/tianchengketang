<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectLesson extends Model {

	    //指定别的表名
    public $table = 'ld_subject_lessons';

    protected $fillable = [
    	'subject_id',
        'lesson_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}

