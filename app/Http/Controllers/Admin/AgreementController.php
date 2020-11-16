<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Course\AgreementService;
use Illuminate\Http\Request;
use Validator;

class AgreementController extends Controller {

    public $request;

    function __construct(Request $request)
    {
        $this->request = $request;
        parent::__construct();
    }

    /**
     * 协议参数
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getParams(AgreementService $agreementService)
    {
        $return  = $agreementService->getParams();
        return response()->json($return);
    }

    /**
     * 获取协议列表
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getList(AgreementService $agreementService)
    {
        //页码
        $page = $this->request->input('page', 1);
        //每页数量
        $pageSize = $this->request->input('pagesize', 15);
        //协议有效当前步骤
        $stepType = $this->request->input('step_type', 0);
        //协议名关键词
        $searchKey = $this->request->input('search_key', '');

        $return  = $agreementService->getList($stepType, $searchKey, $page, $pageSize);
        return response()->json($return);
    }

    /**
     * 查看协议内容
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getInfo(AgreementService $agreementService)
    {
        $id = $this->request->input('id', 0);

        //判断传过来的数组数据是否为空
        if(empty($id)){
            return ['code' => 202 , 'msg' => 'id传递数据不合法'];
        }

        $return  = $agreementService->getInfo($id);
        return response()->json($return);
    }

    /**
     * 添加协议
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function addInfo(AgreementService $agreementService)
    {
        $stepType = $this->request->input('step_type', 0);
        $studentType = $this->request->input('student_type', 0);
        $agreementName = $this->request->input('agreement_name', '');
        $title = $this->request->input('title', '');
        $text = $this->request->input('text', '');
        //判断参数合法性
        if (empty($stepType) || empty($studentType) || empty($agreementName) || empty($title) || empty($text)) {
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //组装参数
        $data = [
            'step_type' => $stepType,
            'student_type' => $studentType,
            'agreement_name' => $agreementName,
            'title' => $title,
            'text' => $text
        ];
        $return  = $agreementService->addInfo($data);
        return response()->json($return);
    }

    /**
     * 更改协议
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function editInfo(AgreementService $agreementService)
    {

        $id = $this->request->input('id', 0);
        $stepType = $this->request->input('step_type', 0);
        $studentType = $this->request->input('student_type', 0);
        $agreementName = $this->request->input('agreement_name', '');
        $title = $this->request->input('title', '');
        $text = $this->request->input('text', '');
        //判断参数合法性
        if (empty($id) || empty($stepType) || empty($studentType) || empty($agreementName) || empty($title) || empty($text)) {
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //组装参数
        $data = [
            'id' => $id,
            'step_type' => $stepType,
            'student_type' => $studentType,
            'agreement_name' => $agreementName,
            'title' => $title,
            'text' => $text
        ];
        $return  = $agreementService->editInfo($data);
        return response()->json($return);
    }

    /**
     * 删除协议
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function delInfo(AgreementService $agreementService)
    {
        $idList = $this->request->input('id_list', '');

        //判断传过来的数组数据是否为空
        if(empty($idList)){
            return ['code' => 202 , 'msg' => 'id_list传递数据不合法'];
        }

        $return  = $agreementService->delInfo($idList);
        return response()->json($return);
    }

    /**
     * 开启关闭 协议
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function openInfo(AgreementService $agreementService)
    {
        $idList = $this->request->input('id_list', '');
        $isForbid = $this->request->input('is_forbid', 0);

        if (empty($idList) || !in_array($isForbid, [0, 1])) {
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        $return  = $agreementService->openInfo($idList, $isForbid);
        return response()->json($return);
    }

    /**
     * 验证课程对此协议是否可用
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function checkCourseListRelation(AgreementService $agreementService)
    {
        $agreementId = $this->request->input('agreement_id', 0);
        $courseIdList = $this->request->input('course_id_list', '');

        if (empty($agreementId)) {
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        $return  = $agreementService->checkCourseListRelation($agreementId, $courseIdList);
        return response()->json($return);
    }

    /**
     * 设置课程使用协议
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function setCourseListRelation(AgreementService $agreementService)
    {
        $agreementId = $this->request->input('agreement_id', 0);
        $courseIdList = $this->request->input('course_id_list', '');

        if (empty($agreementId)) {
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        $return  = $agreementService->setCourseListRelation($agreementId, $courseIdList);
        return response()->json($return);
    }

    /**
     * 使用协议的学生列表
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getStudentList(AgreementService $agreementService)
    {
        $agreementId = $this->request->input('agreement_id', 0);
        if (empty($agreementId)) {
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }
        $page = (int)$this->request->input('page', 1);
        $pageSize = (int)$this->request->input('pagesize', 15);
        $startDate = $this->request->input('start_date', '');
        $endDate = $this->request->input('end_date', '');
        $courseName = $this->request->input('course_name', '');
        $realName = $this->request->input('real_name', 0);
        $phone = $this->request->input('phone', 0);

        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'course_name' => $courseName,
            'real_name' => $realName,
            'phone' => $phone
        ];

        $return  = $agreementService->getStudentList($agreementId, $params, $page, $pageSize);
        return response()->json($return);
    }


    /**
     * 学生签署协议的内容
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getStudentInfo(AgreementService $agreementService)
    {
        $id = $this->request->input('id', 0);

        //判断传过来的数组数据是否为空
        if(empty($id)){
            return ['code' => 202 , 'msg' => 'id传递数据不合法'];
        }

        $return  = $agreementService->getStudentInfo($id);
        return response()->json($return);
    }

    /**
     * 课程列表 - 某协议选择用
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getCourseList(AgreementService $agreementService)
    {

        $agreementId = $this->request->input('agreement_id', 0);
        //判断传过来的数组数据是否为空
        if(empty($agreementId)){
            return ['code' => 202 , 'msg' => 'agreement_id传递数据不合法'];
        }
        $isCurRelation = $this->request->input('is_cur_relation', 0);

        $coursesubjectOne = $this->request->input('coursesubjectOne', 0);
        $coursesubjectTwo = $this->request->input('coursesubjectTwo', 0);
        $courseName = $this->request->input('course_name', '');

        $params = [
            'coursesubjectOne' => $coursesubjectOne,
            'coursesubjectTwo' => $coursesubjectTwo,
            'course_name' => $courseName,
        ];

        $return  = $agreementService->getCourseList($agreementId, $isCurRelation, $params);
        return response()->json($return);
    }

}
