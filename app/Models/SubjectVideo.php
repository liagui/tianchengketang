<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectVideo extends Model {

    //指定别的表名
    public $table = 'ld_subject_videos';

    protected $fillable = [
        'subject_id',
        'video_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}

