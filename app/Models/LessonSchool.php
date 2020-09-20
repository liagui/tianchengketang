<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SubjectLesson;

class LessonSchool extends Model {

    //指定别的表名
    public $table = 'ld_lesson_schools';

    protected $fillable = [
        'admin_id',
        'school_id',
        'lesson_id',
    	// 'title',
     //    'keyword',
     //    'cover',
     //    'price',
     //    'favorable_price',
     //    'method', 
     //    'description',
     //    'introduction',
     //    'buy_num',
     //    'ttl',
     //    'status',
     //    'url'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'is_del',
        'is_forbid'
    ];


    public function lesson() {
        return $this->belongsTo('App\Models\Lesson');
    }

    public function getUrlAttribute($value) {
        if ($value) {
            $photos = json_decode($value, true);
            foreach ($photos as $k => $v) {
                if (!empty($v) && strpos($v, 'http://') === false && strpos($v, 'https://') === false) {
                    $photos[$k] = $v;
                }
            }
            return $photos;
        }
        return $value;
    }
    
}
