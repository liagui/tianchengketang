<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SubjectLesson;
use App\Tools\CurrentAdmin;

class Collection extends Model {

    //指定别的表名
    public $table = 'ld_collections';

    protected $fillable = [
        'lesson_id',
        'student_id'
    ];

    protected $hidden = [
        'updated_at',
        'is_del'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function lessons() {
        return $this->belongsTo('App\Models\Lesson', 'lesson_id');
    }
}
