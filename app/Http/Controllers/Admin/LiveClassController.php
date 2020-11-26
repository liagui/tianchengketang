<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use  App\Tools\CurrentAdmin;
use Validator;
use App\Models\LiveClass;
use Log;


class LiveClassController extends Controller {

    /**
     * @param  直播班号列表
     * @param  pagesize   page
     * @param  author  zzk
     * @param  ctime   2020/6/28
     * @return  array
     */
    public function index(Request $request){
        try{
            $list = LiveClass::getLiveClassList(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /**
     * 添加班号.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try{
            $list = LiveClass::AddLiveClass(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /* 修改班号
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request) {
        //获取提交的参数
        try{
            $data = LiveClass::updateLiveClass(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /**
     * 启用/禁用班号
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request) {
        try{
            $one = LiveClass::updateLiveClassStatus(self::$accept_data);
            return response()->json($one);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /**
     * 删除班号
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request) {
        try{
            $one = LiveClass::updateLiveClassDelete(self::$accept_data);
            return response()->json($one);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /**
     * 班号详情
     *
     *
     */
    public function oneList(){
        try{
            $list = LiveClass::getLiveClassOne(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /**
     * 添加班号课程资料
     */
    public function uploadLiveClass(){
        try{
            $list = LiveClass::uploadLiveClass(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //班号课程资料列表
    public function getListLiveClassMaterial(){
        try{
            $list = LiveClass::getLiveClassMaterial(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //班号课程资料删除
    public function deleteLiveClassMaterial(){
        try{
            $list = LiveClass::deleteLiveClassMaterial(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
}
