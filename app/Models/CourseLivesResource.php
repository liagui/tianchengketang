<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

use App\Models\Teacher;
use App\Models\Admin;
use App\Models\CouresSubject;
use App\Tools\CurrentAdmin;
class CourseLivesResource extends Model {
	public $table = 'ld_course_live_resource';
    //时间戳设置
    public $timestamps = false;

}