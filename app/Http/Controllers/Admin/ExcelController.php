<?php
namespace App\Http\Controllers\Admin;

use App\Http\Middleware\JWTRoleAuth;
use App\Models\AdminLog;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Admin;

class ExcelController extends Controller {
	public function doExcelDatum(){
        $body = self::$accept_data;
        if(!isset($body['id']) || $body['id']<=0){
            return ['code' => 202 , 'msg' =>'id 不合法'];
        }
        $adminArr = Admin::where(['is_del'=>1,'is_forbid'=>1])->select('school_id')->first();
        $school_id = isset($body['school_id'])&& $body['school_id'] >0?$body['school_id']:'';
        if(isset($body['subject']) && !empty($body['subject'])){
            $subject = json_decode($body['subject'],1);
            if(!isset($subject[0]) || empty($subject[0])){
                return ['code'=>201,'msg'=>'请选择项目'];
            }else{
                $body['subject'] = $subject;
            }
        }
		$body['schoolids'] = $this->underlingLook(isset($adminArr['school_id']) && !empty($adminArr['school_id']) ? $adminArr['school_id'] : 0);
        return Excel::download(new \App\Exports\StudentDatumExport($body), '学员报名资料.xlsx');
	}
}
