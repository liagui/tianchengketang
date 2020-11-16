<?php
/**
 * ysh
 * 2020-11-16
 */
namespace App\Services\Admin\Course;

use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\Agreement;
use App\Models\Coures;
use App\Models\CourseAgreement;
use App\Models\CourseSchool;
use App\Models\SchoolCourseData;
use App\Models\StudentAgreement;
use App\Models\Subject;
use App\Tools\CurrentAdmin;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class AgreementService
{
    //内容参数列表
    public static $textParamList = [
        [
            'id' => '[userName]',
            'name' => '学员姓名',
        ],
        [
            'id' => '[courseName]',
            'name' => '课程名称',
        ],

        [
            'id' => '[cardType]',
            'name' => '证件类型',
        ],
        [
            'id' => '[cardNum]',
            'name' => '学员证件号码',
        ],

        [
            'id' => '[phone]',
            'name' => '学员电话',
        ],
        [
            'id' => '[rawPrice]',
            'name' => '课程原价',
        ],

        [
            'id' => '[price]',
            'name' => '课程优惠价',
        ],
        [
            'id' => '[curDate]',
            'name' => '签署日期',
        ],

        [
            'id' => '[subjectName]',
            'name' => '所属学科',
        ],
        [
            'id' => '[payMoney]',
            'name' => '实付款',
        ],

        [
            'id' => '[payMoneyCapitalize]',
            'name' => '实付款大写',
        ],
        [
            'id' => '[sex]',
            'name' => '性别',
        ],

        [
            'id' => '[education]',
            'name' => '学历',
        ],
        [
            'id' => '[address]',
            'name' => '邮寄地址',
        ]

    ];

    /**
     * 获取协议参数
     * @return array
     */
    public function getParams()
    {

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=>[
                'text_param_list' => self::$textParamList,
                'step_type_list' => [
                    [
                        'id' => 1,
                        'name' => '购买前',
                    ],
                    [
                        'id' => 2,
                        'name' => '学习前',
                    ],
                ],
                'student_type_list' => [
                    [
                        'id' => 1,
                        'name' => '新学员',
                    ],
                    [
                        'id' => 2,
                        'name' => '未确认学员',
                    ],
                ],
            ]
        ];

    }


    /**
     * 获取协议列表
     * @param $stepType 协议有效步骤
     * @param $searchKey 协议名关键词
     * @param $page
     * @param $pageSize
     * @return array
     */
    public function getList($stepType, $searchKey, $page, $pageSize)
    {
        //登录人信息
        $adminInfo = CurrentAdmin::user();

        //组装查询
        $agreementQuery = Agreement::query()
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0);
        //增加 有效步骤
        if (! empty($stepType)) {
            $agreementQuery->where('step_type', $stepType);
        }
        //增加协议名
        if (! empty($searchKey)) {
            $agreementQuery->where('agreement_name', 'like', '%' . $searchKey . '%');
        }

        //获取总数
        $total = $agreementQuery->count();
        //获取总页数
        $totalPage = ceil($total/$pageSize);

        //总数大于0
        if ($total > 0) {
            $agreemenList = $agreementQuery->select(
                'id', 'admin_id', 'step_type', 'student_type',
                'agreement_name', 'title', 'is_forbid', 'create_time'
                )
                ->orderBy('id', 'desc')
                ->limit($pageSize)
                ->offset(($page - 1) * $pageSize)
                ->get()
                ->toArray();

        } else {
            $customeList = [];
        }

        $dataList = [];
        if (! empty($agreemenList)) {

            /**
             * 增加操作人数据
             */
            $adminIdList = array_column($agreemenList, 'admin_id');
            $adminList = Admin::query()
                ->whereIn('id', $adminIdList)
                ->select('id', 'username')
                ->get()
                ->toArray();
            $adminList = array_column($adminList, 'username', 'id');
            foreach ($agreemenList as $item) {
                $item['admin_name'] = empty($adminList[$item['admin_id']]) ? '' : $adminList[$item['admin_id']];
                array_push($dataList, $item);
            }
        }

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=>[
                'total' => $total,
                'total_page' => $totalPage,
                'page' => $page,
                'pagesize' => $pageSize,
                'list' => $dataList
            ]
        ];

    }

    /**
     * 查看协议内容
     * @param $id 协议id
     * @return array
     */
    public function getInfo($id)
    {
        //获取登录者数据
        $adminInfo = CurrentAdmin::user();

        $agreementQuery = Agreement::query()
            ->where('id', $id)
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0)
            ->select(
                'id', 'admin_id', 'step_type', 'student_type',
                'agreement_name', 'title', 'is_forbid', 'text'
            )
            ->first();

        if (! empty($agreementQuery)) {
            $data = $agreementQuery->toArray();
        } else {
            return [
                'code' => 403,
                'msg' => '协议异常'
            ];
        }

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=> $data
        ];
    }


    /**
     * @param $data
     * [
        'step_type' => $stepType,
        'student_type' => $studentType,
        'agreement_name' => $agreementName,
        'title' => $title,
        'text' => $text
        ]
     * @return array
     */
    public function addInfo($data)
    {
        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        //插入用 默认数据
        $insertData = [
            'admin_id' => $adminInfo->cur_admin_id,
            'school_id' => $adminInfo->school_id,
            'text' => '',
        ];

        //插入用数据
        $insertData = array_merge($insertData, $data);
        Agreement::query()
            ->insert($insertData);

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'Agreement',
            'route_url'      =>  'admin/agreement/addInfo',
            'operate_method' =>  'insert',
            'content'        =>  json_encode($data),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
        ];

    }

    /**
     * 编辑协议
     * @param $data
     *      * [
     *  'id' =>
        'step_type' => $stepType,
        'student_type' => $studentType,
        'agreement_name' => $agreementName,
        'title' => $title,
        'text' => $text
        ]
     *
     * @return array
     */
    public function editInfo($data)
    {

        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        //获取数据是否存在
        $total = Agreement::query()
            ->where('id', $data['id'])
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0)
            ->count();
        if ($total == 0) {
            return [
                'code' => 403,
                'msg' => '协议异常'
            ];
        }

        //更新用数据
        $updateData = $data;
        unset($updateData['id']);

        Agreement::query()
            ->where('id', $data['id'])
            ->update($updateData);

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'Agreement',
            'route_url'      =>  'admin/agreement/editInfo',
            'operate_method' =>  'update',
            'content'        =>  json_encode($data),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
        ];

    }

    /**
     * 删除协议
     * @param $idList
     * @return array
     */
    public function delInfo($idList)
    {
        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        if (! is_array($idList)) {
            $idList = explode(',', $idList);
        }

        Agreement::query()
            ->whereIn('id', $idList)
            ->where('school_id', $adminInfo->school_id)
            ->update(['is_del' => 1]);

        CourseAgreement::query()
            ->whereIn('agreement_id', $idList)
            ->where('school_id', $adminInfo->school_id)
            ->update(['is_del' => 1]);

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'Agreement',
            'route_url'      =>  'admin/agreement/delInfo',
            'operate_method' =>  'delete',
            'content'        =>  json_encode(['id_list' => $idList]),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
        ];
    }

    /**
     * 开启关闭 协议
     * @param $idList
     * @param $isForbid
     * @return array
     */
    public function openInfo($idList, $isForbid)
    {

        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        if (! is_array($idList)) {
            $idList = empty($idList) ? [] : explode(',', $idList);
        }

        if (! empty($idList)) {
            Agreement::query()
                ->whereIn('id', $idList)
                ->where('school_id', $adminInfo->school_id)
                ->update(['is_forbid' => $isForbid]);
        }

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'Agreement',
            'route_url'      =>  'admin/agreement/openInfo',
            'operate_method' =>  'set',
            'content'        =>  json_encode(['id_list' => $idList, 'is_forbid' => $isForbid]),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
        ];
    }

    /**
     * 验证课程对此协议是否可用
     * @param $agreementId 协议id
     * @param $courseIdList 课程列表
     * @return array
     */
    public function checkCourseListRelation($agreementId, $courseIdList)
    {
        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        //获取数据是否存在
        $total = Agreement::query()
            ->where('id', $agreementId)
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0)
            ->count();
        if ($total == 0) {
            return [
                'code' => 403,
                'msg' => '协议异常'
            ];
        }

        //获取异常的课程列表 即关联了其他协议
        $errorCourseList = [];
        if (! empty($courseIdList)) {
            if (! is_array($courseIdList)) {
                $courseIdList = explode(',', $courseIdList);
            }

            //当前的课程绑定关系
            $courseAgreementList = CourseAgreement::query()
                ->where('school_id', $adminInfo->school_id)
                ->whereIn('course_id', $courseIdList)
                ->select('agreement_id', 'is_del', 'course_id')
                ->get()
                ->toArray();

            if (! empty($courseAgreementList)) {
                $errorCourseIdList = [];

                //获取异常的课程
                foreach ($courseAgreementList as $item) {
                    //既不是本协议 又没有删除关联
                    if ($item['is_del'] == 0 && $item['agreement_id'] > 0 && $item['agreement_id'] != $agreementId) {
                        array_push($errorCourseIdList, $item['course_id']);
                    }
                }

                //查询异常的课程列表
                if (! empty($errorCourseIdList)) {
                    $errorCourseList = Coures::query()
                        ->whereIn('id', $errorCourseIdList)
                        ->select('id', 'title')
                        ->get()
                        ->toArray();
                }

            }
        }

        return [
            'code'=>200,
            'msg'=>'Success',
            'data' => [
                'is_all_success' => empty($errorCourseList) ? 1 : 0,
                'error_course_list' => $errorCourseList

            ]
        ];
    }

    /**
     * 设置课程使用协议
     * @param $agreementId 协议id
     * @param $courseIdList 课程列表
     * @return array
     */
    public function setCourseListRelation($agreementId, $courseIdList)
    {
        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        //获取数据是否存在
        $total = Agreement::query()
            ->where('id', $agreementId)
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0)
            ->count();
        if ($total == 0) {
            return [
                'code' => 403,
                'msg' => '协议异常'
            ];
        }

        if (! empty($courseIdList)) {
            if (! is_array($courseIdList)) {
                $courseIdList = explode(',', $courseIdList);
            }
        } else {
            $courseIdList = [];
        }
        $courseIdList = array_diff($courseIdList ,['', 0]);
        //如果是空 则直接清空
        if (empty($courseIdList)) {
            CourseAgreement::query()
                ->where('agreement_id', $agreementId)
                ->where('school_id', $adminInfo->school_id)
                ->update([
                    'agreement_id' => 0,
                    'is_del' => 1
                ]);
        } else {
            //当前关联此协议的课程信息
            $existsRelationCourseIdList = CourseAgreement::query()
                ->where('agreement_id', $agreementId)
                ->where('school_id', $adminInfo->school_id)
                ->where('is_del', 0)
                ->select('course_id')
                ->get()
                ->pluck('course_id')
                ->toArray();
            //需要删除关联的课程列表
            $needDelCourseIdList = array_diff($existsRelationCourseIdList, $courseIdList);

            //当前的已有的课程列表
            $existsCourseIdList = CourseAgreement::query()
                ->where('school_id', $adminInfo->school_id)
                ->whereIn('course_id', $courseIdList)
                ->select('course_id')
                ->get()
                ->pluck('course_id')
                ->toArray();
            //需要插入的
            $needInsertCourseIdList = array_diff($courseIdList, $existsCourseIdList);

            $insertData = [];

            foreach ($needInsertCourseIdList as $val) {
                $row = [
                    'admin_id' => $adminInfo->cur_admin_id,
                    'school_id' => $adminInfo->school_id,
                    'course_id' => $val,
                    'agreement_id' => $agreementId
                ];
                array_push($insertData, $row);
            }


            DB::beginTransaction();
            try {
                //已关联 现无关联 删除
                if (! empty($needDelCourseIdList)) {
                    CourseAgreement::query()
                        ->where('school_id', $adminInfo->school_id)
                        ->whereIn('course_id', $needDelCourseIdList)
                        ->where('agreement_id', $agreementId)
                        ->update([
                            'agreement_id' => 0,
                            'is_del' => 1
                        ]);
                }

                //已存在的更新
                if (! empty($existsCourseIdList)) {
                    CourseAgreement::query()
                        ->where('school_id', $adminInfo->school_id)
                        ->whereIn('course_id', $existsCourseIdList)
                        ->update([
                            'agreement_id' => $agreementId,
                            'is_del' => 0
                        ]);
                }

                //插入新的记录
                if (! empty($insertData)) {
                    CourseAgreement::query()
                        ->insert($insertData);
                }


                DB::commit();
            } catch (\Exception $ex) {
                DB::rollBack();
                return [
                    'code' => $ex->getCode(),
                    'msg' => $ex->getMessage(),
                ];
            }
        }

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'Agreement',
            'route_url'      =>  'admin/agreement/setCourseListRelation',
            'operate_method' =>  'set',
            'content'        =>  json_encode(['course_id_list' => $courseIdList, 'agreement_id' => $agreementId]),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
        ];
    }

    /**
     * 使用协议的学生列表
     * @param $agreementId 协议id
     * @param $params
     *  'start_date' => $startDate,
        'end_date' => $endDate,
        'course_name' => $courseName,
        'real_name' => $realName,
        'phone' => $phone
     * @param $page
     * @param $pageSize
     * @return array
     */
    public function getStudentList($agreementId, $params, $page, $pageSize)
    {
        //登录人信息
        $adminInfo = CurrentAdmin::user();

        //组装查询
        $studentAgreementQuery = StudentAgreement::query()
            ->leftjoin('ld_student', 'ld_student.id', '=', 'ld_student_agreement.student_id')
            ->leftjoin('ld_course', 'ld_course.id', '=', 'ld_student_agreement.course_id')
            ->where('ld_student_agreement.school_id', $adminInfo->school_id)
            ->where('ld_student_agreement.agreement_id', $agreementId);
        //开始时间
        if (! empty($params['start_date'])) {
            $studentAgreementQuery->where('ld_student_agreement.create_time', '>=', $params['start_date']);
        }

        //结束时间
        if (! empty($params['end_date'])) {
            $studentAgreementQuery->where('ld_student_agreement.create_time', '<', Carbon::parse($params['end_date'])->addDay()->toDateString());
        }

        //课程名
        if (! empty($params['course_name'])) {
            $studentAgreementQuery->where('ld_course.title', 'like', '%' . $params['course_name'] . '%');
        }

        //真实姓名
        if (! empty($params['real_name'])) {
            $studentAgreementQuery->where('ld_student.real_name', 'like', '%' . $params['real_name'] . '%');
        }

        //电话
        if (! empty($params['phone'])) {
            $studentAgreementQuery->where('ld_student.phone', 'like', '%' . $params['phone'] . '%');
        }

        //获取总数
        $total = $studentAgreementQuery->count();
        //获取总页数
        $totalPage = ceil($total/$pageSize);

        //总数大于0
        if ($total > 0) {

            $studentAgreementList = $studentAgreementQuery->select(
                'ld_student_agreement.id', 'ld_student_agreement.create_time',
                'ld_student.nickname', 'ld_student.phone', 'ld_student.real_name',
                'ld_course.title as course_name'
                )
                ->orderBy('ld_student_agreement.id', 'desc')
                ->limit($pageSize)
                ->offset(($page - 1) * $pageSize)
                ->get()
                ->toArray();

        } else {
            $studentAgreementList = [];
        }

        return [
            'code' => 200,
            'msg' => 'Success',
            'data' => [
                'total' => $total,
                'total_page' => $totalPage,
                'page' => $page,
                'pagesize' => $pageSize,
                'list' => $studentAgreementList
            ]
        ];
    }


    /**
     * 学生签署协议的内容
     * @param AgreementService $agreementService
     * @return array|\Illuminate\Http\JsonResponse
     */

    public function getStudentInfo($id)
    {
        //获取登录者数据
        $adminInfo = CurrentAdmin::user();

        //获取学生签约协议
        $studentAgreementQuery = StudentAgreement::query()
            ->where('id', $id)
            ->where('school_id', $adminInfo->school_id)
            ->select('id','text')
            ->first();

        if (! empty($studentAgreementQuery)) {
            $studentAgreementInfo = $studentAgreementQuery->toArray();
        } else {
            return [
                'code' => 403,
                'msg' => '协议异常'
            ];
        }

        //日志格式化
        $agreementInfo = json_decode($studentAgreementInfo['text'], true);
        if (empty($agreementInfo)) {
            $agreementInfo = [];
        }
        //返回用数据
        $return = [
            'id' => $studentAgreementInfo['id'],
            'agreement_name' => empty($agreementInfo['agreement_name']) ? '' : $agreementInfo['agreement_name'],
            'title' => empty($agreementInfo['title']) ? '' : $agreementInfo['title'],
            'text' => empty($agreementInfo['text']) ? '' : $agreementInfo['text']
        ];

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=> $return
        ];
    }

    /**
     * 课程列表 - 某协议选择用
     * @param $agreementId 协议id
     * @param $isCurRelation 当前关联的数据
     * @param $params
        [
            'coursesubjectOne' => $coursesubjectOne,
            'coursesubjectTwo' => $coursesubjectTwo,
            'course_name' => $courseName,
        ]
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getCourseList($agreementId, $isCurRelation, $params)
    {
        //获取登录者数据
        $adminInfo = CurrentAdmin::user();


        $subjectIdList = [];
        //已关联的课程数据
        if ($isCurRelation == 1) {
            $courseAgreementQuery = CourseAgreement::query()
                ->leftJoin('ld_course', 'ld_course.id', '=', 'ld_course_agreement.course_id')
                ->where('ld_course_agreement.school_id', $adminInfo->school_id)
                ->where('ld_course_agreement.agreement_id', $agreementId)
                ->where('ld_course_agreement.is_del', 0)
                ->where('ld_course.is_del', 0);

            if (! empty($params['coursesubjectOne'])) {
                $courseAgreementQuery->where('ld_course.parent_id', $params['coursesubjectOne']);
            }

            if (! empty($params['coursesubjectTwo'])) {
                $courseAgreementQuery->where('ld_course.child_id', $params['coursesubjectTwo']);
            }

            if (! empty($params['course_name'])) {
                $courseAgreementQuery->where('ld_course.title', 'like', '%' . $params['course_name'] . '%');
            }

            $courseList = $courseAgreementQuery
                ->select('ld_course.id', 'ld_course.parent_id', 'ld_course.child_id', 'ld_course.title')
                ->get()
                ->toArray();
            foreach ($courseList as &$item) {
                $item['is_relation'] = 1;
                $subjectIdList[$item['parent_id']] = $item['parent_id'];
                $subjectIdList[$item['child_id']] = $item['child_id'];
            }

        } else {

            /**
             * 当前已关联的课程数据
             */
            $relationCourseIdList = CourseAgreement::query()
                ->where('school_id', $adminInfo->school_id)
                ->where('agreement_id', $agreementId)
                ->where('is_del', 0)
                ->select('course_id')
                ->pluck('course_id')
                ->toArray();
            $courseQuery = Coures::query()
                ->where('school_id', $adminInfo->school_id)
                ->where('is_del', 0);

            if (! empty($params['coursesubjectOne'])) {
                $courseQuery->where('parent_id', $params['coursesubjectOne']);
            }

            if (! empty($params['coursesubjectTwo'])) {
                $courseQuery->where('child_id', $params['coursesubjectTwo']);
            }

            if (! empty($params['course_name'])) {
                $courseQuery->where('title', 'like', '%' . $params['course_name'] . '%');
            }
            $courseList = $courseQuery
                ->select('id', 'parent_id', 'child_id', 'title')
                ->get()
                ->toArray();

            foreach ($courseList as &$item) {
                $item['is_relation'] = in_array($item['id'], $relationCourseIdList) ? 1 : 0;
                $subjectIdList[$item['parent_id']] = $item['parent_id'];
                $subjectIdList[$item['child_id']] = $item['child_id'];
            }

            $courseSchoolQuery = CourseSchool::query()
                ->where('to_school_id', $adminInfo->school_id)
                ->where('is_del', 0);

            if (! empty($params['coursesubjectOne'])) {
                $courseSchoolQuery->where('parent_id', $params['coursesubjectOne']);
            }

            if (! empty($params['coursesubjectTwo'])) {
                $courseSchoolQuery->where('child_id', $params['coursesubjectTwo']);
            }

            if (! empty($params['course_name'])) {
                $courseSchoolQuery->where('title', 'like', '%' . $params['course_name'] . '%');
            }
            $courseSchoolList = $courseSchoolQuery
                ->select('course_id as id', 'parent_id', 'child_id', 'title')
                ->get()
                ->toArray();

            foreach ($courseSchoolList as $row) {
                $row['is_relation'] = in_array($row['id'], $relationCourseIdList) ? 1 : 0;
                $subjectIdList[$row['parent_id']] = $row['parent_id'];
                $subjectIdList[$row['child_id']] = $row['child_id'];
                array_push($courseList, $row);
            }

        }

        //组装类别
        $subjectList = Subject::query()
            ->whereIn('id', $subjectIdList)
            ->where('is_del', 0)
            ->select('id', 'subject_name')
            ->get()
            ->toArray();
        $subjectList = array_column($subjectList, 'subject_name', 'id');

        foreach ($courseList as &$item) {
            $item['parent_name'] = empty($subjectList[$item['parent_id']]) ? :  $subjectList[$item['parent_id']];
            $item['child_name'] = empty($subjectList[$item['child_id']]) ? :  $subjectList[$item['child_id']];
        }

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=> $courseList
        ];
    }


}
