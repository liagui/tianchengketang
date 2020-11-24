<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\School\NoticeService;
use Illuminate\Http\Request;
use Validator;

class NoticeController extends Controller {

    public $request;

    function __construct(Request $request)
    {
        $this->request = $request;
        parent::__construct();
    }

    /**
     * 获取未读通知总数
     * @param NoticeService $noticeService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnreadTotal(NoticeService $noticeService)
    {
        $return  = $noticeService->getUnreadTotal();
        return response()->json($return);
    }

    /**
     * 通知列表
     * @param NoticeService $noticeService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getList(NoticeService $noticeService)
    {
        //页码
        $page = $this->request->input('page', 1);
        //每页数量
        $pageSize = $this->request->input('pagesize', 15);

        $return  = $noticeService->getList($page, $pageSize);
        return response()->json($return);
    }


    /**
     * 查看通知内容
     * @param NoticeService $noticeService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getInfo(NoticeService $noticeService)
    {
        $id = $this->request->input('id', 0);

        //判断传过来的数组数据是否为空
        if(empty($id)){
            return ['code' => 202 , 'msg' => 'id传递数据不合法'];
        }

        $return  = $noticeService->getInfo($id);
        return response()->json($return);
    }


    /**
     * 设置读取通知
     * @param NoticeService $noticeService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function readInfo(NoticeService $noticeService)
    {
        $idList = $this->request->input('id_list', '');

        //判断传过来的数组数据是否为空
        if(empty($idList)){
            return ['code' => 202 , 'msg' => 'id_list传递数据不合法'];
        }

        $return  = $noticeService->readInfo($idList);
        return response()->json($return);
    }

}
