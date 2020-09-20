<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
#use App\Models\SubjectLesson;
use App\Tools\CurrentAdmin;

class Lesson extends Model {

    //指定别的表名
    public $table = 'ld_course';
    public $timestamps = false;
    // protected $fillable = [
    //     'id',
    //     'admin_id',
    // 	'title',
    //     'keyword',
    //     'cover',
    //     'price',
    //     'favorable_price',
    //     'method',
    //     'teacher_id',
    //     'description',
    //     'introduction',
    //     'buy_num',
    //     'ttl',
    //     'status',
    //     'subject_id'
    // ];

    // protected $hidden = [
    //     'created_at',
    //     'updated_at',
    //     'is_del',
    //     'is_forbid',
    //     'pivot'
    // ];

    // protected $appends = [
    //     'is_collection',
    //     'teacher_id',
    //     'subject_id',
    //     'sold_num',
    //     'stock_num',
    //     'is_buy',
    //     'class_num',
    // ];

    // protected $casts = [
    //     'price' => 'string',
    //     'favorable_price' => 'string'
    // ];

    // public function getClassNumAttribute($value)
    // {
    //     return 0;
    // }

    // public function getIsBuyAttribute($value)
    // {
    //     $token = isset($_REQUEST['user_token']) ? $_REQUEST['user_token'] : 0;
    //     $student = Student::where('token', $token)->first();
    //     if(!empty($student)){
    //         $num = $this->hasMany('App\Models\Order', 'class_id')->where(['status' => 2, 'student_id' => $student->id])->count();
    //         if($num > 0){
    //             return 1;
    //         }
    //     }
    //     return 0;
    // }

    // public function getStockNumAttribute($value)
    // {
    //     return (int)LessonStock::where(['lesson_id' => $this->id])->sum('add_number');
    // }

    // public function getSoldNumAttribute($value)
    // {
    //     return $this->hasMany('App\Models\Order', 'class_id')->where('status', 1)->count();
    // }

    // public function getMethodIdAttribute($value)
    // {
    //     return $this->belongsToMany('App\Models\Method', 'ld_lesson_methods')->pluck('id');
    // }

    // public function getTeacherIdAttribute($value)
    // {
    //     return $this->belongsToMany('App\Models\Teacher', 'ld_lesson_teachers')->pluck('id');
    // }

    // public function getSubjectIdAttribute($value)
    // {
    //     return $this->belongsToMany('App\Models\Subject', 'ld_subject_lessons')->pluck('id');
    // }

    // public function getIsAuthAttribute($value) {
    //     $user = CurrentAdmin::user();
    //     $token = isset($_REQUEST['user_token']) ? $_REQUEST['user_token'] : 0;
    //     $student = Student::where('token', $token)->first();
    //     if(!empty($student) ){
    //         $school = LessonSchool::where(['school_id' => $student->school_id, 'lesson_id' => $this->id])->count();
    //         $adminIds = Admin::where('school_id', $student->school_id)->pluck('id')->toArray();
    //         if($school > 0){
    //             //授权
    //             return 2;
    //         }
    //         if(!empty($adminIds)){
    //             $flipped_haystack = array_flip($adminIds);

    //             if ( isset($flipped_haystack[$this->admin_id]) )
    //             {
    //                 //自增
    //                 return  1;
    //             }
    //         }
    //     }
    //     if(!empty($user)){
    //         $school = LessonSchool::where(['school_id' => $user->school_id, 'lesson_id' => $this->id])->count();
    //         if($school > 0){
    //             //授权
    //             return 2;
    //         }
    //         if($user->id == $this->admin_id){
    //             //自增
    //             return  1;
    //         }
    //     }

    //     return  0;
    // }

    // /**
    //  * @param  description   是否收藏
    //  * @param  data          数组数据
    //  * @param  author        sxl
    //  * @param  ctime         2020-05-29
    //  * @return  int          0未收藏1已收藏
    //  */
    // public function getIsCollectionAttribute($value)
    // {
    //     $token = isset($_REQUEST['user_token']) ? $_REQUEST['user_token'] : '';
    //     if($token && !empty($token)){

    //         $student = Student::where('token', $token)->first();
    //         if(!empty($student)){
    //             $studentIds = $student->collectionLessons->pluck('id')->toArray();
    //             $flipped_haystack = array_flip($studentIds);
    //             if(isset($flipped_haystack[$this->id]))
    //             {
    //                 return 1;
    //             }
    //                 return 0;
    //         }
    //     }
    //     return 0;
    // }

    // public function getUrlAttribute($value) {
    //     if ($value) {
    //         $photos = json_decode($value, true);
    //         return $photos;
    //     }
    //     return [];
    // }

    // public function teachers() {
    //     return $this->belongsToMany('App\Models\Teacher', 'ld_course_teacher')->withTimestamps();
    // }

    // public function subjects() {
    //     return $this->belongsToMany('App\Models\Subject', 'ld_subject_lessons')->withTimestamps();
    // }

    // public function methods() {
    //     return $this->belongsToMany('App\Models\Method', 'ld_lesson_methods')->withTimestamps();
    // }

    // public function schools() {
    //     return $this->belongsTo('App\Models\LessonSchool');
    // }

    // public function lessonChilds() {
    //     return $this->hasMany('App\Models\LessonChild', 'lesson_id', 'id')->where('pid', 0);
    // }

    // public function lives() {
    //     return $this->belongsToMany('App\Models\Live', 'ld_lesson_lives')->withTimestamps();
    // }

    // public function order() {
    //     return $this->hasMany('App\Models\Order' , 'class_id' , 'id');
    // }
}
