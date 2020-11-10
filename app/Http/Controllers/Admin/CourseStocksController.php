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
	
	/**
     * @param getGiveCourse 获取授权课程信息
     * @param  school_id
     * @param  author  sxh
     * @param  ctime   2020/11/9
     * @return  array
     */
    public function getGiveCourse(){
        //获取提交的参数
        try{
            $data = CourseStocks::getGiveCourse(self::$accept_data);
            return response()->json($data);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

}
