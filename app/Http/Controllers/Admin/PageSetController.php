<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\School\CustomPageService;
use Illuminate\Http\Request;
use App\Tools\CurrentAdmin;
use Validator;

class PageSetController extends Controller {
    /**
     * 自定义页面 - 列表
     * @param CustomPageService $customPageService
     * @return \Illuminate\Http\JsonResponse
     */
	public function getList(CustomPageService $customPageService){

	    $data = self::$accept_data;

        $validator = Validator::make(
            $data,
            [
                'page_type' => 'required|regex:/^[12]$/',
            ]);

        if($validator->fails()) {
            return response()->json(['code' => 201, 'msg' => $validator->errors()->first()]);
        }

        $return = $customPageService->getList(
            $data['page_type'],
            empty($data['page']) ? 1 : $data['page'],
            empty($data['pagesize']) ? 15 : $data['pagesize']);
		return response()->json($return);
	}
	//详情-修改

    /**
     * 自定义页面 - 详情
     * @param CustomPageService $customPageService
     * @return \Illuminate\Http\JsonResponse
     */
	public function details(CustomPageService $customPageService){
        $data = self::$accept_data;
	    $validator = Validator::make($data,
            [
            	'id' => 'required|integer|min:1',
           	]);
        if($validator->fails()) {
            return response()->json(['code' => 201, 'msg' => $validator->errors()->first()]);
        }

        $res = $customPageService->details($data['id']);

		return response()->json($res);
	}

    /**
     * 自定义页面 - 新增
     * @param CustomPageService $customPageService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function addInfo(CustomPageService $customPageService){
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'page_type' => 'required|regex:/^[12]$/',
                'name' => 'required',
                'link_type' => 'required|regex:/^[123]$/',
            ]);
        if($validator->fails()) {
            return response()->json(['code' => 201, 'msg' => $validator->errors()->first()]);
        }

        //自定义单页 标识不为空
        if ($data['page_type'] == 1 && empty($data['sign'])) {
            //@todo 失败
            return ['code' => 201, 'msg' => 'sign不合法'];
        }

        //自定义链接时 url不为空
        if ($data['link_type'] == 1 && empty($data['url'])) {
            //@todo 失败
            return ['code' => 201, 'msg' => 'url不合法'];
        }

        //自定义单页 标识不为空
        if (($data['link_type'] == 2 || $data['page_type'] == 1) && empty($data['text'])) {
            //@todo 失败
            return ['code' => 201, 'msg' => 'text不合法'];
        }

        $res = $customPageService->addInfo($data);

        return response()->json($res);
    }

    /**
     * 自定义页面 - 修改
     * @param CustomPageService $customPageService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function editInfo(CustomPageService $customPageService){
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'id' => 'required',
                'page_type' => 'required|regex:/^[12]$/',
                'name' => 'required',
                'link_type' => 'required|regex:/^[123]$/',
            ]);
        if($validator->fails()) {
            return response()->json(['code' => 201, 'msg' => $validator->errors()->first()]);
        }

        //自定义单页 标识不为空
        if ($data['page_type'] == 1 && empty($data['sign'])) {
            //@todo 失败
            return ['code' => 201, 'msg' => 'sign不合法'];
        }

        //自定义链接时 url不为空
        if ($data['link_type'] == 1 && empty($data['url'])) {
            //@todo 失败
            return ['code' => 201, 'msg' => 'url不合法'];
        }

        //自定义单页 标识不为空
        if (($data['link_type'] == 2 || $data['page_type'] == 1) && empty($data['text'])) {
            //@todo 失败
            return ['code' => 201, 'msg' => 'text不合法'];
        }

        $res = $customPageService->editInfo($data);

        return response()->json($res);
    }


    /**
     * 自定义页面 - 删除
     * @param CustomPageService $customPageService
     * @return \Illuminate\Http\JsonResponse
     */
    public function delInfo(CustomPageService $customPageService){
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'id_list' => 'required',
            ]);
        if($validator->fails()) {
            return response()->json(['code' => 201, 'msg' => $validator->errors()->first()]);
        }

        $res = $customPageService->delInfo($data['id_list']);

        return response()->json($res);
    }

    /**
     * 自定义页面 - 开启关闭
     * @param CustomPageService $customPageService
     * @return \Illuminate\Http\JsonResponse
     */
    public function openInfo(CustomPageService $customPageService){
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'id_list' => 'required',
                'is_forbid' => 'regex:/^[10]$/',
            ]);
        if($validator->fails()) {
            return response()->json(['code' => 201, 'msg' => $validator->errors()->first()]);
        }

        $res = $customPageService->openInfo($data['id_list'], $data['is_forbid']);

        return response()->json($res);
    }

    /**
     * 自定义页面 - 排序
     * @param CustomPageService $customPageService
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortInfo(CustomPageService $customPageService){
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'info_list' => 'required',
            ]);
        if($validator->fails()) {
            return response()->json(['code' => 201, 'msg' => $validator->errors()->first()]);
        }

        $res = $customPageService->sortInfo($data['info_list']);

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
