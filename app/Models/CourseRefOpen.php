<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;
use App\Models\OpenCourseTeacher;
use App\Models\Teacher;
use App\Models\Admin;
use App\Models\CouresSubject;
use App\Tools\CurrentAdmin;
class CourseRefOpen extends Model {
    //指定别的表名
    public $table = 'ld_course_ref_open';
    //时间戳设置
    public $timestamps = false;
}