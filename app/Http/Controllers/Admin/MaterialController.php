<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\AdminLog;
class MaterialController extends Controller
{
    //获取物料列表
    public function getMaterialList(){
        //获取提交的参数
        try{
            $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id: 0;
            $school_id = $this->underlingLook($school_id);
            $data = Material::getMaterialList(self::$accept_data,$school_id);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //创建物料需求
    public function Materialadd(){
        //获取提交的参数
        try{
            $data = Material::Materialadd(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //更新物料需求
    public function updateMaterialOne(){
        //获取提交的参数
        try{
            $data = Material::updateMaterialOne(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //获取单条物料
    public function getMaterialOne(){
        //获取提交的参数
        try{
            $data = Material::getMaterialOne(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    //确认物料信息
    public function Materialupdate(){
        //获取提交的参数
        try{
            $data = Material::Materialupdate(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //获取确认物料信息
    public function getMaterial(){
        //获取提交的参数
        try{
            $data = Material::getMaterial(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //获取提交人信息
    public function getsubmit(){
        //获取提交的参数
        try{
            $data = Material::getsubmit(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
}
