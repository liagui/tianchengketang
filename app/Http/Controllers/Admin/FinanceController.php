<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller {
    /*
     * @param  description   财务管理-班主任业绩列表接口
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public function getHeadMasterList(){
        //获取提交的参数
        try{
            //每页显示的条数
            $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
            $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
            $offset   = ($page - 1) * $pagesize;

            //获取班主任的总数量
            $head_mater_count = DB::table('admin')->join("role_auth" , function($join){
                $join->on('admin.role_id', '=', 'role_auth.id');
            })->where('admin.is_del' , 1)->where('admin.is_forbid' , 1)->where('role_auth.is_del' , 0)->where('admin.role_id' , 3)->count();

            if($head_mater_count > 0){
                //新数组赋值
                $school_array = [];

                //获取班主任的列表
                $head_mater_list = DB::table('admin')->join("role_auth" , function($join){
                    $join->on('admin.role_id', '=', 'role_auth.id');
                })->where('admin.is_del' , 1)->where('admin.is_forbid' , 1)->where('role_auth.is_del' , 0)->where('admin.role_id' , 3)->orderByDesc('create_time')->offset($offset)->limit($pagesize)->get()->toArray();
                return ['code' => 200 , 'msg' => '获取班主任业绩列表成功' , 'data' => ['head_master_list' => $school_array , 'total' => $head_mater_count , 'pagesize' => $pagesize , 'page' => $page]];
            }
            return ['code' => 200 , 'msg' => '获取班主任业绩列表成功' , 'data' => ['head_master_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
}
