<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Enrolment;
use App\Models\StudentPapers;
use App\Models\QuestionBank;
use App\Models\QuestionSubject;
use App\Models\StudentDoTitle;
use App\Models\Order;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller {
    /*
     * @param  description   添加学员的方法
     * @param  参数说明       body包含以下参数[
     *     phone        手机号
     *     real_name    学员姓名
     *     sex          性别(1男,2女)
     *     papers_type  证件类型(1代表身份证,2代表护照,3代表港澳通行证,4代表台胞证,5代表军官证,6代表士官证,7代表其他)
     *     papers_num   证件号码
     *     birthday     出生日期
     *     address_locus户口所在地
     *     age          年龄
     *     educational  学历(1代表小学,2代表初中,3代表高中,4代表大专,5代表大本,6代表研究生,7代表博士生,8代表博士后及以上)
     *     family_phone 家庭电话号
     *     office_phone 办公电话
     *     contact_people  紧急联系人
     *     contact_phone   紧急联系电话
     *     email           邮箱
     *     qq              QQ号码
     *     wechat          微信
     *     address         地址
     *     remark          备注
     * ]
     * @param author    dzj
     * @param ctime     2020-04-28
     * return string
     */
    public function doInsertStudent() {
        //获取提交的参数
        try{
            $data = Student::doInsertStudent(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '添加成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   更新学员的方法
     * @param  参数说明       body包含以下参数[
     *     student_id   学员id
     *     phone        手机号
     *     real_name    学员姓名
     *     sex          性别(1男,2女)
     *     papers_type  证件类型(1代表身份证,2代表护照,3代表港澳通行证,4代表台胞证,5代表军官证,6代表士官证,7代表其他)
     *     papers_num   证件号码
     *     birthday     出生日期
     *     address_locus户口所在地
     *     age          年龄
     *     educational  学历(1代表小学,2代表初中,3代表高中,4代表大专,5代表大本,6代表研究生,7代表博士生,8代表博士后及以上)
     *     family_phone 家庭电话号
     *     office_phone 办公电话
     *     contact_people  紧急联系人
     *     contact_phone   紧急联系电话
     *     email           邮箱
     *     qq              QQ号码
     *     wechat          微信
     *     address         地址
     *     remark          备注
     * ]
     * @param author    dzj
     * @param ctime     2020-04-28
     * return string
     */
    public function doUpdateStudent() {
        //获取提交的参数
        try{
            $data = Student::doUpdateStudent(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '更改成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    根据学员id获取详细信息
     * @param  参数说明         body包含以下参数[
     *     student_id   学员id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-27
     * return  array
     */
    public function getStudentInfoById(){
        //获取提交的参数
        try{
            $data = Student::getStudentInfoById(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取学员信息成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    账号启用/禁用方法
     * @param  参数说明         body包含以下参数[
     *      student_id   学员id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-28
     */
    public function doForbidStudent(){
        //获取提交的参数
        try{
            $data = Student::doForbidStudent(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '操作成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   学员报名的方法
     * @param  参数说明       body包含以下参数[
     *     student_id     学员id
     *     parent_id      学科分类id
     *     lession_id     课程id
     *     lession_price  课程原价
     *     student_price  学员价格
     *     payment_type   付款类型
     *     payment_method 付款方式
     *     payment_fee    付款金额
     * ]
     * @param author    dzj
     * @param ctime     2020-04-28
     * return string
     */
    public function doStudentEnrolment(){
        //获取提交的参数
        try{
            $data = Enrolment::doStudentEnrolment(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => ' 报名成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

        /*
     * @param  descriptsion    获取学员列表
     * @param  参数说明         body包含以下参数[
     *     student_id   学员id
     *     is_forbid    账号状态
     *     state_status 开课状态
     *     real_name    姓名
     *     paginate     每页显示条数
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-27
     * return  array
     */
    public function getStudentList(){
        //获取提交的参数
        try{
            //判断token或者body是否为空
            /*if(!empty($request->input('token')) && !empty($request->input('body'))){
                $rsa_data = app('rsa')->servicersadecrypt($request);
            } else {
                $rsa_data = [];
            }*/

            //获取全部学员列表
            $data = Student::getStudentList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取学员列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    获取学员转校列表
     * @param  参数说明         body包含以下参数[
     *     search    姓名/手机号
     *     page      当前页数
     *     pagesize  每页显示条数
     * ]
     * @param  author          dzj
     * @param  ctime           2020-07-29
     * return  array
     */
    public function getStudentTransferSchoolList(){
        //获取提交的参数
        try{
            //获取全部学员列表
            $data = Student::getStudentTransferSchoolList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    学员转校功能
     * @param  参数说明         body包含以下参数[
     *     student_id   学员id
     *     school_id    分校id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-07-29
     * return  array
     */
    public function doTransferSchool(){
        //获取提交的参数
        try{
            //学员转校功能
            $data = Student::doTransferSchool(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '转校成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /*
     * @param  description   学员公共参数列表
     * @param  author        dzj
     * @param  ctime         2020-04-30
     */
    public function getStudentCommonList(){
        //证件类型
        $papers_type_array = [[
                'id'  =>  1 ,
                'name'=> '身份证'
            ] ,
            [
                'id'  =>  2 ,
                'name'=> '护照'
            ] ,
            [
                'id'  =>  3 ,
                'name'=> '港澳通行证'
            ],
            [
                'id'  =>  4 ,
                'name'=> '台胞证'
            ],
            [
                'id'  =>  5 ,
                'name'=> '军官证'
            ],
            [
                'id'  =>  6 ,
                'name'=> '士官证'
            ],
            [
                'id'  =>  7 ,
                'name'=> '其他'
            ]
        ];

        //学历
        $educational_array = [
            [
                'id'  =>  1 ,
                'name'=> '小学'
            ] ,
            [
                'id'  =>  2 ,
                'name'=> '初中'
            ] ,
            [
                'id'  =>  3 ,
                'name'=> '高中'
            ],
            [
                'id'  =>  4 ,
                'name'=> '大专'
            ],
            [
                'id'  =>  5 ,
                'name'=> '本科'
            ],
            [
                'id'  =>  6 ,
                'name'=> '研究生'
            ],
            [
                'id'  =>  7 ,
                'name'=> '博士生'
            ],
            [
                'id'  =>  8 ,
                'name'=>  '博士后及以上'
            ]
        ];

        //付款方式
        $payment_method = [
            [
                'id'  =>  1 ,
                'name'=> '微信'
            ] ,
            [
                'id'  =>  2 ,
                'name'=> '支付宝'
            ] ,
            [
                'id'  =>  3 ,
                'name'=> '银行转账'
            ]
        ];

        //付款类型
        $payment_type = [
            [
                'id'  =>  1 ,
                'name'=> '定金'
            ] ,
            [
                'id'  =>  2 ,
                'name'=> '尾款'
            ] ,
            [
                'id'  =>  3 ,
                'name'=> '最后一次尾款'
            ],
            [
                'id'  =>  4 ,
                'name'=> '全款'
            ]
        ];
        return response()->json(['code' => 200 , 'msg' => '返回数据成功' , 'data' => ['papers_type_list' => $papers_type_array , 'educational_list' => $educational_array , 'payment_method' => $payment_method , 'payment_type' => $payment_type]]);
    }

    /*
     * @param  description   导入学员功能方法
     * @param  author        dzj
     * @param  ctime         2020-07-21
    */
    public function doImportUser(){
        //获取提交的参数
        try{
            //判断分校id是否为空
            if(empty(self::$accept_data['school_id']) || !is_numeric(self::$accept_data['school_id']) || self::$accept_data['school_id'] <= 0){
                return response()->json(['code' => 202 , 'msg' => '分校id不合法']);
            }

            //判断课程分类是否传递
            if(empty(self::$accept_data['course_type'])){
                return response()->json(['code' => 201 , 'msg' => '请选择课程分类']);
            }

            //判断课程id是否为空或是否合法
            if(empty(self::$accept_data['course_id']) || self::$accept_data['course_id'] <= 0){
                return response()->json(['code' => 201 , 'msg' => '课程id是否合法']);
            }

            //返回校验的数据结果
            $response_data = self::checkExamineExcelData();
            if($response_data['code'] != 200){
                return response()->json(['code' => $response_data['code'] , 'msg' => $response_data['msg']]);
            }

            //excel表格数据赋值
            self::$accept_data['data'] = isset($response_data['data']['exam_list']['data']) && !empty($response_data['data']['exam_list']['data']) ? $response_data['data']['exam_list']['data'] : '';

            //是否执行插入操作(1代表是,0代表否)主要用于查看打印的数据格式是否正确
            $is_insert = isset(self::$accept_data['is_insert']) ? 0 : 1;

            //执行导入excel表格操作
            $exam_list = Student::doImportUser(self::$accept_data,$is_insert);

            //判断是否导入成功
            if($exam_list['code'] == 200){
                unlink($response_data['data']['path']);
                return response()->json(['code' => 200 , 'msg' => '导入学员列表成功' , 'data' => $exam_list['data']]);
            } else {
                return response()->json(['code' => $exam_list['code'] , 'msg' => $exam_list['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //学员导出
    public function doExportUser(){
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        $school_id     = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        return Excel::download(new \App\Exports\ExportUser(self::$accept_data,$school_status,$school_id), '学员列表.xlsx');
    }


    /*
     * @param  description   校验excel数据公共部分
     * @param  author        dzj
     * @param  ctime         2020-05-15
    */
    public static function checkExamineExcelData() {
        //获取提交的参数
        try{
            //获取上传文件
            $file = isset($_FILES['file']) && !empty($_FILES['file']) ? $_FILES['file'] : '';

            //判断是否有文件上传
            if(!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])){
                return ['code' => 201 , 'msg' => '请上传excel文件'];
            }

            //获取上传文件的文件后缀
            $is_correct_extensiton = self::detectUploadFileMIME($file);
            $excel_extension       = substr($_FILES['file']['name'], strrpos($_FILES['file']['name'], '.')+1);   //获取excel后缀名
            if($is_correct_extensiton <= 0 || !in_array($excel_extension , ['xlsx' , 'xls'])){
                return ['code' => 202 , 'msg' => '上传文件格式非法'];
            }

            //存放文件路径
            $file_path= app()->basePath() . "/public/upload/excel/";
            //判断上传的文件夹是否建立
            if(!file_exists($file_path)){
                mkdir($file_path , 0777 , true);
            }

            //重置文件名
            $filename = time() . rand(1,10000) . uniqid() . substr($file['name'], stripos($file['name'], '.'));
            $path     = $file_path.$filename;

            //判断文件是否是通过 HTTP POST 上传的
            if(is_uploaded_file($_FILES['file']['tmp_name'])){
                //上传文件方法
                move_uploaded_file($_FILES['file']['tmp_name'], $path);
            }

            //获取excel表格中试题列表
            $exam_list = self::doImportExcel2(new \App\Imports\UsersImport , $path , 1 , 500);

            //判断是否超过最大导入量
            if($exam_list['code'] != 200){
                return ['code' => $exam_list['code'] , 'msg' => $exam_list['msg']];
            }

            //返回正确合法的数据信息
            return ['code' => 200 , 'msg' => '检验数据成功' , 'data' => ['exam_list' => $exam_list , 'path' => $path]];
        } catch (\Exception $ex) {
            return ['code' => 500 , 'msg' => $ex->getMessage()];
        }
    }
    //获取学员学校进度列表
    public function getStudentStudyList(){
            //获取提交的参数
            try{
                $data_list = Student::getStudentStudyList(self::$accept_data);
                //返回正确合法的数据信息
                return ['code' => 200 , 'msg' => '获取成功' , 'data' => $data_list];
            } catch (\Exception $ex) {
                return ['code' => 500 , 'msg' => $ex->getMessage()];
            }
    }

	 /*
     * @param  getStudentBankList    获取学员做题记录
     * @param  参数说明         student_id   学员id
     * @param  author          sxh
     * @param  ctime           2020-10-26
     * return  array
     */
    public function getStudentBankList(){
        //获取提交的参数
        try{
            $data = StudentPapers::getStudentBankList(self::$accept_data);
            return response()->json(['code' => $data['code'] , 'msg' => $data['msg'], 'data' => $data['data']]);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  getStudentBankList    获取学员做题搜索列表
     * @param  author          sxh
     * @param  ctime           2020-10-26
     * return  array
     */
    public function getStudentBankSearchInfo(){
        try{
            //题库名称
            $data['bank_name'] = QuestionBank::where(['is_del'=>0,'is_open'=>0])->select('id as bank_id','topic_name')->get()->toArray();
            //类型名称
            $data['type_name'] = [
                [
                    'type_id'  =>  1 ,
                    'name'=> '真题'
                ] ,
                [
                    'type_id'  =>  2 ,
                    'name'=> '模拟题'
                ] ,
                [
                    'type_id'  =>  3 ,
                    'name'=> '其他'
                ],
            ];
            return response()->json(['code' => 200 , 'msg' => '成功', 'data' => $data]);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
        * @param  导出学员做题记录
        * @param  $student_id     参数
        * @param  author  sxh
        * @param  ctime   2020/10-26
        * return  array
        */
    public function exportExcelStudentBankList(){
        //return self::$accept_data;
		$time = date('Y-m-d',time());
        return Excel::download(new \App\Exports\BankListExport(self::$accept_data), 'BankList'.$time.'.xlsx');
    }

	public function exportExcelStudentRecord(){
        //return self::$accept_data;
        return Excel::download(new \App\Exports\StudentRecord(self::$accept_data), 'StudentRecord.xlsx');
    }

    /*
        * @param  获取学员做题记录详情
        * @param  $student_id    学员id
        * @param  $bank_id       题库id
        * @param  $subject_id    科目id
        * @param  $papers_id     试卷id
        * @param  author  sxh
        * @param  ctime   2020/10-27
        * return  array
        */
    public function getStudentBankDetails(){
        try{
            $data = StudentDoTitle::getStudentBankDetails(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

	/*
        * @param  学员学习记录
        * @param  $student_id     参数
        *         $type           1 直播 2 录播
        * @param  ctime   2020/10-28
        * return  array
        */
    public function getStudentStudyLists(){

        try{
            $data = Order::getStudentStudyList(self::$accept_data);
            return response()->json($data);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

	/*
       * @param  学员直播记录
       * @param  author  sxh
       * @param  ctime   2020/11/26
       * return  array
       */
    public function getStudentLiveStatistics(){
        try{

           $data = Order::getStudentLiveStatistics(self::$accept_data);
            return response()->json(['code' => 200 , 'data' => $data]);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
       * @param  export 直播到课率
       * @param  author  sxh
       * @param  ctime   2020/11/26
       * return  array
       */
    public function exportStudentLiveStatistics(){
        try{

            return Excel::download(new \App\Exports\LiveRateExport(self::$accept_data), '直播到课率.xlsx');
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }




}
