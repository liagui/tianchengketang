<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\OfflinePay;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Tools\CurrentAdmin;
use App\Models\AdminLog;
use Illuminate\Support\Facades\DB;
use App\Models\CouresSubject;
use Log;
class OfflinePayController extends Controller {

	/*
     * @param  getUserAuth   获取线下支付列表
     * @param  return  array   
     * @param author    lys
     * @param ctime     2020-09-21
    */
	public function getList(){
	 	$data = OfflinePay::getList(self::$accept_data);
	 	return response()->json($data);
	}
	/*
     * @param  getUserAuth   添加线下支付列表
     * @param  return  array   
     * @param author    lys
     * @param ctime     2020-09-21
    */
	public function doInsertPay(){
	 	$data = OfflinePay::doInsertPay(self::$accept_data);
	 	return response()->json($data);
	}
	/*
     * @param  getUserAuth   编辑线下支付列表（获取）
     * @param  return  array   
     * @param author    lys
     * @param ctime     2020-09-21
    */
	public function getOfflinePayById(){
	 	$data = OfflinePay::getOfflinePayById(self::$accept_data);
	 	return response()->json($data);
	}
	/*
     * @param  getUserAuth   编辑线下支付列表
     * @param  return  array   
     * @param author    lys
     * @param ctime     2020-09-21
    */
	public function doUpdateOfflinePay(){
	 	$data = OfflinePay::doUpdateOfflinePay(self::$accept_data);
	 	return response()->json($data);
	}
	 	



}