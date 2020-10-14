<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use App\Models\Admin as Adminuser;
use App\Models\Roleauth;
use App\Models\Authrules;
use App\Models\School;
use App\Models\StudentDatum;
use App\Models\Region;
use Illuminate\Support\Facades\Redis;
use App\Tools\CurrentAdmin;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminLog;
use Illuminate\Support\Facades\DB;

class StudentDatumController extends Controller {
    //获取列表
	public function getList(){
		//获取提交的参数
        try{
            $data = self::$accept_data;
            $school_id =  isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            $data['school_ids'] = $this->underlingLook($school_id);
            $data = StudentDatum::getStudentDatumList($data);
            return response()->json($data);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
	}
    //资料添加
	public function doDatumInsert(){
        //获取提交的参数
		try{
            $data = StudentDatum::doStudentDatumInsert(self::$accept_data);
            return response()->json($data);
        }catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
	}
    //获取详情
    public function getDatumById(){
        //获取提交的参数
        try{
            $data = StudentDatum::getDatumById(self::$accept_data);
            return response()->json($data);
        }catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //资料审核
    public function doUpdateAudit(){
        //获取提交的参数
        try{
            $data = StudentDatum::doUpdateAudit(self::$accept_data);
            return response()->json($data);
        }catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    } 
    //获取资料的id
    public function getInitiatorById(){
        //获取提交的参数
        try{
            $data = StudentDatum::getInitiatorById(self::$accept_data);
            return response()->json($data);
        }catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //获取发起人的资料
    public function getRegionList(){
        $data = Region::getRegion();
        return response()->json($data);
    }
    //获取添加资料的数量
    public function getDatumCount(){
        //获取提交的参数
        try{
            $data = StudentDatum::getDatumCount(self::$accept_data);
            return response()->json($data);
        }catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
   //获取地区
    public function getRegionLists(){
        $arr = Region::getRegionList(self::$accept_data);
        return response()->json($arr);
    }



}