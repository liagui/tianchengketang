<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\FootConfig;
use Illuminate\Http\Request;
use App\Tools\CurrentAdmin;
use DB;
use Validator;

class PageSetController extends Controller {
	//列表
	public function getList(){
	    $res = FootConfig::getList(self::$accept_data);
		return response()->json($res);
	}
	//详情-修改
	public function details(){
	    $validator = Validator::make(self::$accept_data, 
            [
            	'id' => 'required|integer',
            	'type' => 'required|integer',
           	],
            FootConfig::message());
	    $res = FootConfig::details(self::$accept_data);
		return response()->json($res);
	}
	//修改logo
	public function doLogoUpdate(){
		 $validator = Validator::make(self::$accept_data, 
            [
            	'school_id' => 'required|integer',
            	'logo' => 'required',
           	],
            FootConfig::message());
	    $res = FootConfig::doLogoUpdate(self::$accept_data);
		return response()->json($res);
	}



}