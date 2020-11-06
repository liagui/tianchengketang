<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use App\Models\Admin as Adminuser;
use App\Models\Roleauth;
use App\Models\Authrules;
use App\Models\School;
use Illuminate\Support\Facades\Redis;
use App\Tools\CurrentAdmin;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminLog;
use Illuminate\Support\Facades\DB;
use App\Models\CourseStocks;
class CourseStocksController extends Controller {


    /**
     * @param
     * @param  school_id
     * @param  author  李银生
     * @param  ctime   2020/6/29
     * @return  array
     */
    public function getList(){
    	$validator = Validator::make(self::$accept_data,
        [
        	'school_id' => 'required|integer',
        	'course_id' => 'required|integer', //授权课程id
       	],
        CourseStocks::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
     	$result = CourseStocks::getCourseStocksList(self::$accept_data);
        return response()->json($result);
    }
    public function doInsertStocks(){
        $validator = Validator::make(self::$accept_data,
        [
            'school_id' => 'required|integer',
            'course_id' => 'required|integer',
            'add_number'=> 'required|integer'
        ],
        CourseStocks::message());
        $result = CourseStocks::doInsertStocks(self::$accept_data);
        return response()->json($result);
    }

}
