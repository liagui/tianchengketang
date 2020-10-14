<?php
namespace App\Http\Controllers\Admin;

use App\Http\Middleware\JWTRoleAuth;
use App\Models\AdminLog;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Admin;

class OrderExcelController extends Controller {
    /*
     * @param  description   导出分校收入详情
     * @param  参数说明       body包含以下参数[
     *      open_id        开课得管理id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-08
     * return string
     */
    public function doExportBranchSchoolExcel() {
        $body = self::$accept_data;
        //判断admin_id
        if(!isset($body['admin_id']) || $body['admin_id'] <= 0){
            return response()->json(['code' => 202 , 'msg' => 'id不合法']);
        }
        //通过admin_id获取后台信息
        $school_id = Admin::where('id' , $body['admin_id'])->value('school_id');
        //获取院校id(1,2,3)
        $school_arr = parent::underlingLook($school_id);

        //分校的id传递
        $body['schoolId'] = $school_arr['data'];
        if($school_id == 0){
            return Excel::download(new \App\Exports\ZongBranchSchoolExport($body), '总校收入详情.xlsx');
        } else {
            return Excel::download(new \App\Exports\BranchSchoolExport($body), '分校收入详情.xlsx');
        }
    }

    /*
     * @param  description   导出分校已确认订单
     * @param  参数说明       body包含以下参数[
     *      open_id        开课得管理id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-08
     * return string
     */
    public function doExportBranchSchoolConfirmOrderExcel() {
        $body = self::$accept_data;
        //判断admin_id
        if(!isset($body['admin_id']) || $body['admin_id'] <= 0){
            return response()->json(['code' => 202 , 'msg' => 'id不合法']);
        }
        //通过admin_id获取后台信息
        $school_id = Admin::where('id' , $body['admin_id'])->value('school_id');
        //获取院校id(1,2,3)
        $school_arr = parent::underlingLook($school_id);

        //分校的id传递
        $body['schoolId'] = $school_arr['data'];
        return Excel::download(new \App\Exports\BranchSchoolConfirmOrderExport($body), '分校收入详情-已确认订单.xlsx');
    }
    
    /*
     * @param  description   导出分校已退费订单
     * @param  参数说明       body包含以下参数[
     *      open_id        开课得管理id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-08
     * return string
     */
    public function doExportBranchSchoolRefundOrderExcel() {
        $body = self::$accept_data;
        //判断admin_id
        if(!isset($body['admin_id']) || $body['admin_id'] <= 0){
            return response()->json(['code' => 202 , 'msg' => 'id不合法']);
        }
        //通过admin_id获取后台信息
        $school_id = Admin::where('id' , $body['admin_id'])->value('school_id');
        //获取院校id(1,2,3)
        $school_arr = parent::underlingLook($school_id);

        //分校的id传递
        $body['schoolId'] = $school_arr['data'];
        return Excel::download(new \App\Exports\BranchSchoolRefundOrderExport($body), '分校收入详情-已退费订单.xlsx');
    }
}
