<?php
namespace App\Models;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CourseAgreement extends Model {
    //指定别的表名
    public $table      = 'ld_course_agreement';
    //时间戳设置
    public $timestamps = false;

    /**
     * 获取学生课程协议内容
     * @param $schoolId
     * @param $studentId
     * @param $courseId
     * @param $nature
     * @param $stepType
     * @return array
     */
    public static function getCourseAgreement($schoolId, $studentId, $courseId, $nature, $stepType)
    {
        /**
         * 授权课程 取真正的课程id
         */
        if ($nature == 1) {
            $courseInfo = CourseSchool::query()
                ->where('id', $courseId)
                ->where('to_school_id', $schoolId)
                ->where('is_del', 0)
                ->select('*')
                ->first();
        } else {
            $courseInfo = Coures::query()
                ->where('id', $courseId)
                ->where('school_id', $schoolId)
                ->where('is_del', 0)
                ->select('*')
                ->first();
        }
        if (empty($courseInfo)) {
            return [
                'code' => 403,
                'msg' => '课程数据异常',
            ];
        } else {
            $courseInfo = $courseInfo->toArray();
            //授权课程取 课程id
            if ($nature == 1) {
                $masterCourseId = $courseInfo['course_id'];
            } else {
                $masterCourseId = $courseId;
            }
        }

        //学生有效的已签署协议内容
        $studentAgreementInfo = StudentAgreement::query()
            ->where('school_id', $schoolId)
            ->where('course_id', $masterCourseId)
            ->where('student_id', $studentId)
            ->where('expire_time', '>=', Carbon::now()->toDateTimeString())
            ->orderBy('expire_time', 'desc')
            ->first();

        //没有签署有效的协议
        if (empty($studentAgreementInfo)) {

            //查找课程关联协议数据
            $courseAgreementInfo = CourseAgreement::query()
                ->where('school_id', $schoolId)
                ->where('course_id', $masterCourseId)
                ->where('is_del', 0)
                ->select('agreement_id', 'update_time')
                ->first();

            /**
             * 获取协议相关数据
             */
            $agreementInfo = [];
            if (! empty($courseAgreementInfo)) {
                //课程协议关联数据
                $courseAgreementInfo = $courseAgreementInfo->toArray();

                //协议相关数据
                $agreementInfo = Agreement::query()
                    ->where('id', $courseAgreementInfo['agreement_id'])
                    ->where('school_id', $schoolId)
                    ->where('is_del', 0)
                    ->where('is_forbid', 1)
                    ->select('step_type', 'student_type', 'agreement_name', 'title', 'text', 'update_time')
                    ->first();
                //转化成数组
                if (empty($agreementInfo)) {
                    $agreementInfo = [];
                } else {
                    $agreementInfo = $agreementInfo->toArray();
                }
            }


            //协议 内容 分别处理
            if (empty($agreementInfo)) {
                $return = [
                    'is_sign' => 0,
                    'is_need_sign' => 0,
                    'is_exists_agreement' => 0,
                    'agreement_info' => []
                ];
            } else {

                //学生订单数据
                $studentOrderInfo = [];
                //验证 是否符合 操作步骤
                if ($agreementInfo['step_type'] != $stepType) {
                    $isNeedSign = 0;
                } else {
                    //新学员 特殊处理
                    if ($agreementInfo['student_type'] == 1) {

                        //查找 学员的报名订单时间
                        $studentOrderInfo = Order::query()
                            ->where('school_id', $schoolId)
                            ->where('student_id', $studentId)
                            ->where('class_id', $courseId)
                            ->where('nature', $nature)
                            ->whereIn('pay_status', [3, 4])
                            ->where('validity_time', '>=', Carbon::now()->toDateTimeString())
                            ->orderBy('id', 'desc')
                            ->select('*')
                            ->first();

                        if (empty($studentOrderInfo)) {
                            $isNeedSign = 1;
                        } else {
                            $studentOrderInfo = $studentOrderInfo->toArray();
                            //报名时间 早于 任何 关联协议变动时间 都 不算新用户
                            if ($studentOrderInfo['create_at'] < $agreementInfo['update_time'] || $studentOrderInfo['create_at'] < $courseAgreementInfo['update_time']) {
                                $isNeedSign = 0;
                            } else {
                                $isNeedSign = 1;
                            }
                        }

                    } else {
                        $isNeedSign = 1;
                    }
                }

                //需要签约 学生订单数据
                if ($isNeedSign == 1) {
                    if ($agreementInfo['student_type'] != 1) {
                        //查找 学员的报名订单时间
                        $studentOrderInfo = Order::query()
                            ->where('school_id', $schoolId)
                            ->where('student_id', $studentId)
                            ->where('class_id', $courseId)
                            ->where('nature', $nature)
                            ->whereIn('pay_status', [3, 4])
                            ->where('validity_time', '>=', Carbon::now()->toDateTimeString())
                            ->orderBy('id', 'desc')
                            ->select('*')
                            ->first();
                    }
                }

                //学生基础数据
                $studentInfo = Student::query()
                    ->where('id', $studentId)
                    ->first();
                if (! empty($studentInfo)) {
                    $studentInfo = $studentInfo->toArray();
                } else {
                    return [
                        'code' => 403,
                        'msg' => '用户信息异常',
                    ];
                }

                //学科列表
                $subjectList = Subject::query()
                    ->whereIn('id', [$courseInfo['parent_id'], $courseInfo['child_id']])
                    ->get()
                    ->toArray();
                $subjectList = array_column($subjectList, 'subject_name', 'id');

                //组装替换数据
                $textParams = [
                    '[userName]' => $studentInfo['real_name'],
                    '[courseName]' =>  $courseInfo['title'],
                    '[cardType]' => Controller::getPapersNameByType($studentInfo['papers_type']),
                    '[cardNum]' => $studentInfo['papers_num'],
                    '[phone]' => $studentInfo['phone'],
                    '[rawPrice]' => $courseInfo['pricing'],
                    '[price]' => $courseInfo['sale_price'],
                    '[curDate]' => date('Y-m-d'),
                    '[subjectName]' => array_get($subjectList, $courseInfo['parent_id'], '') . '-' . array_get($subjectList, $courseInfo['child_id'], ''),
                    '[payMoney]' => array_get($studentOrderInfo, 'student_price', '0.00'),
                    '[payMoneyCapitalize]' => self::convertAmountToCn(array_get($studentOrderInfo, 'student_price', 0), 0),
                    '[sex]' => $studentInfo['sex'] == 2 ? '女' : '男',
                    '[education]'=> Controller::getEducationalNameByType($studentInfo['educational']),
                    '[address]' => $studentInfo['address'],
                ];
                //返回数据
                $return = [
                    'is_sign' => 0,
                    'is_need_sign' => $isNeedSign,
                    'is_exists_agreement' => 1,
                    'agreement_info' => [
                        'step_type' => $agreementInfo['step_type'],
                        'agreement_name' =>  $agreementInfo['agreement_name'],
                        'title' => $agreementInfo['title'],
                        'text' => str_replace(array_keys($textParams), array_values($textParams), $agreementInfo['text'])
                    ]
                ];

            }


        } else {
            //获取签署时的协议内容
            $agreementInfo = json_decode($studentAgreementInfo->text, true);
            if (empty($agreementInfo)) {
                $agreementInfo = [];
            }

            //组装返回数据
            $return = [
                'is_sign' => 1,
                'is_need_sign' => 1,
                'is_exists_agreement' => 1,
                'agreement_info' => [
                    'step_type' => array_get($agreementInfo, 'step_type' ,0),
                    'agreement_name' => array_get($agreementInfo, 'agreement_name', ''),
                    'title' => array_get($agreementInfo, 'title', ''),
                    'text' => array_get($agreementInfo, 'text', '')
                ]
            ];

        }

        return [
            'code' => 200,
            'msg' => '获取成功',
            'data' => $return
        ];

    }

    /**
     * 获取学生课程协议签约
     * @param $schoolId
     * @param $studentId
     * @param $courseId
     * @param $nature
     * @param $stepType
     * @return array
     */
    public static function setCourseAgreement($schoolId, $studentId, $courseId, $nature, $stepType)
    {
        /**
         * 授权课程 取真正的课程id
         */
        if ($nature == 1) {
            $courseInfo = CourseSchool::query()
                ->where('id', $courseId)
                ->where('to_school_id', $schoolId)
                ->where('is_del', 0)
                ->select('*')
                ->first();
        } else {
            $courseInfo = Coures::query()
                ->where('id', $courseId)
                ->where('school_id', $schoolId)
                ->where('is_del', 0)
                ->select('*')
                ->first();
        }
        if (empty($courseInfo)) {
            return [
                'code' => 403,
                'msg' => '课程数据异常',
            ];
        } else {
            $courseInfo = $courseInfo->toArray();
            //授权课程取 课程id
            if ($nature == 1) {
                $masterCourseId = $courseInfo['course_id'];
            } else {
                $masterCourseId = $courseId;
            }
        }

        //学生有效的已签署协议内容
        $studentAgreementInfo = StudentAgreement::query()
            ->where('school_id', $schoolId)
            ->where('course_id', $masterCourseId)
            ->where('student_id', $studentId)
            ->where('expire_time', '>=', Carbon::now()->toDateTimeString())
            ->orderBy('expire_time', 'desc')
            ->first();

        //没有签署有效的协议
        if (empty($studentAgreementInfo)) {

            //查找课程关联协议数据
            $courseAgreementInfo = CourseAgreement::query()
                ->where('school_id', $schoolId)
                ->where('course_id', $masterCourseId)
                ->where('is_del', 0)
                ->select('agreement_id', 'update_time')
                ->first();

            /**
             * 获取协议相关数据
             */
            $agreementInfo = [];
            if (! empty($courseAgreementInfo)) {
                //课程协议关联数据
                $courseAgreementInfo = $courseAgreementInfo->toArray();

                //协议相关数据
                $agreementInfo = Agreement::query()
                    ->where('id', $courseAgreementInfo['agreement_id'])
                    ->where('school_id', $schoolId)
                    ->where('is_del', 0)
                    ->where('is_forbid', 1)
                    ->select('id', 'step_type', 'student_type', 'agreement_name', 'title', 'text', 'update_time')
                    ->first();
                //转化成数组
                if (empty($agreementInfo)) {
                    $agreementInfo = [];
                } else {
                    $agreementInfo = $agreementInfo->toArray();
                }
            }


            //协议为空 直接返回异常
            if (empty($agreementInfo)) {
                return [
                    'code' => 403,
                    'msg' => '课程协议异常',
                ];
            } else {

                //验证 是否符合 操作步骤
                if ($agreementInfo['step_type'] != $stepType) {
                    return [
                        'code' => 403,
                        'msg' => '当前学员无需签约',
                    ];
                }

                //学生订单数据 查找 学员的报名订单时间
                $studentOrderInfo = Order::query()
                    ->where('school_id', $schoolId)
                    ->where('student_id', $studentId)
                    ->where('class_id', $courseId)
                    ->where('nature', $nature)
                    ->whereIn('pay_status', [3, 4])
                    ->where('validity_time', '>=', Carbon::now()->toDateTimeString())
                    ->orderBy('id', 'desc')
                    ->select('*')
                    ->first();
                if (empty($studentOrderInfo)) {
                    $studentOrderInfo = [];
                } else {
                    $studentOrderInfo = $studentOrderInfo->toArray();
                }

                //新学员 特殊处理
                if ($agreementInfo['student_type'] == 1) {
                    if (! empty($studentOrderInfo)) {
                        $studentOrderInfo = $studentOrderInfo->toArray();
                        //报名时间 早于 任何 关联协议变动时间 都 不算新用户
                        if ($studentOrderInfo['create_at'] < $agreementInfo['update_time'] || $studentOrderInfo['create_at'] < $courseAgreementInfo['update_time']) {
                            return [
                                'code' => 403,
                                'msg' => '当前学员无需签约',
                            ];
                        }
                    }
                }

                //学生基础数据
                $studentInfo = Student::query()
                    ->where('id', $studentId)
                    ->first();
                if (! empty($studentInfo)) {
                    $studentInfo = $studentInfo->toArray();
                } else {
                    return [
                        'code' => 403,
                        'msg' => '用户信息异常',
                    ];
                }

                //学科列表
                $subjectList = Subject::query()
                    ->whereIn('id', [$courseInfo['parent_id'], $courseInfo['child_id']])
                    ->get()
                    ->toArray();
                $subjectList = array_column($subjectList, 'subject_name', 'id');

                //组装替换数据
                $textParams = [
                    '[userName]' => $studentInfo['real_name'],
                    '[courseName]' =>  $courseInfo['title'],
                    '[cardType]' => Controller::getPapersNameByType($studentInfo['papers_type']),
                    '[cardNum]' => $studentInfo['papers_num'],
                    '[phone]' => $studentInfo['phone'],
                    '[rawPrice]' => $courseInfo['pricing'],
                    '[price]' => $courseInfo['sale_price'],
                    '[curDate]' => date('Y-m-d'),
                    '[subjectName]' => array_get($subjectList, $courseInfo['parent_id'], '') . '-' . array_get($subjectList, $courseInfo['child_id'], ''),
                    '[payMoney]' => array_get($studentOrderInfo, 'student_price', '0.00'),
                    '[payMoneyCapitalize]' => self::convertAmountToCn(array_get($studentOrderInfo, 'student_price', 0), 0),
                    '[sex]' => $studentInfo['sex'] == 2 ? '女' : '男',
                    '[education]'=> Controller::getEducationalNameByType($studentInfo['educational']),
                    '[address]' => $studentInfo['address'],
                ];
                if (! empty($studentOrderInfo)) {
                    $expireTime = $studentOrderInfo['validity_time'];
                } else {
                    if ($courseInfo['expiry'] == 0) {
                        $expireTime = '3000-01-02 12:12:12';
                    } else {
                        $expireTime = Carbon::now()->addDays($courseInfo['expiry'])->toDateTimeString();
                    }
                }

                StudentAgreement::query()
                    ->insert([
                        'school_id' => $schoolId,
                        'student_id' => $studentId,
                        'course_id' => $masterCourseId,
                        'agreement_id' => $agreementInfo['id'],
                        'text' => json_encode([
                            'agreement_name' => $agreementInfo['agreement_name'],
                            'title' => $agreementInfo['title'],
                            'step_type' => $agreementInfo['step_type'],
                            'student_type' => $agreementInfo['student_type'],
                            'text' => str_replace(array_keys($textParams), array_values($textParams), $agreementInfo['text'])
                        ]),
                        'expire_time' => $expireTime
                    ]);

            }


        } else {
            return [
                'code' => 403,
                'msg' => '已签约，无需重签',
            ];

        }


        return [
            'code' => 200,
            'msg' => '签约成功'
        ];


    }

    /**
     * 将数值金额转换为中文大写金额
     * @param $amount float 金额(支持到分)
     * @param $type   int   补整类型,0:到角补整;1:到元补整
     * @return mixed 中文大写金额
     */
    public static function convertAmountToCn($amount, $type = 1) {
        // 判断输出的金额是否为数字或数字字符串
        if(!is_numeric($amount)){
            return "要转换的金额只能为数字!";
        }

        // 金额为0,则直接输出"零元整"
        if($amount == 0) {
            return "人民币零元整";
        }

        // 金额不能为负数
        if($amount < 0) {
            return "要转换的金额不能为负数!";
        }

        // 金额不能超过万亿,即12位
        if(strlen($amount) > 12) {
            return "要转换的金额不能为万亿及更高金额!";
        }

        // 预定义中文转换的数组
        $digital = array('零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖');
        // 预定义单位转换的数组
        $position = array('仟', '佰', '拾', '亿', '仟', '佰', '拾', '万', '仟', '佰', '拾', '元');

        // 将金额的数值字符串拆分成数组
        $amountArr = explode('.', $amount);

        // 将整数位的数值字符串拆分成数组
        $integerArr = str_split($amountArr[0], 1);

        // 将整数部分替换成大写汉字
        $result = '人民币';
        $integerArrLength = count($integerArr);     // 整数位数组的长度
        $positionLength = count($position);         // 单位数组的长度
        $zeroCount = 0;                             // 连续为0数量
        for($i = 0; $i < $integerArrLength; $i++) {
            // 如果数值不为0,则正常转换
            if($integerArr[$i] != 0){
                // 如果前面数字为0需要增加一个零
                if($zeroCount >= 1){
                    $result .= $digital[0];
                }
                $result .= $digital[$integerArr[$i]] . $position[$positionLength - $integerArrLength + $i];
                $zeroCount = 0;
            }else{
                $zeroCount += 1;
                // 如果数值为0, 且单位是亿,万,元这三个的时候,则直接显示单位
                if(($positionLength - $integerArrLength + $i + 1)%4 == 0){
                    $result = $result . $position[$positionLength - $integerArrLength + $i];
                }
            }
        }

        // 如果小数位也要转换
        if($type == 0) {
            // 将小数位的数值字符串拆分成数组
            $decimalArr = str_split($amountArr[1], 1);
            // 将角替换成大写汉字. 如果为0,则不替换
            if($decimalArr[0] != 0){
                $result = $result . $digital[$decimalArr[0]] . '角';
            }
            // 将分替换成大写汉字. 如果为0,则不替换
            if($decimalArr[1] != 0){
                $result = $result . $digital[$decimalArr[1]] . '分';
            }
        }else{
            $result = $result . '整';
        }
        return $result;
    }
}
