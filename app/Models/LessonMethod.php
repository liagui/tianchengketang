<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonMethod extends Model {

    //指定别的表名
    public $table = 'ld_lesson_methods';

    protected $fillable = [
        'lesson_id',
        'method_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}

