<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\PapersExam;
use App\Models\QuestionSubject;

class ExamController extends Controller {
    /*
     * @param  description   增加试题的方法
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)
     *     subject_id      科目id
     *     bank_id         题库id
     *     exam_id         试题id
     *     exam_content    题目内容
     *     option_list     [
     *         option_no     选项字母
     *         option_name   选项内容
     *         correct_flag  是否为正确选项(1代表是,0代表否)
     *     ]
     *     answer          题目答案
     *     text_analysis   文字解析
     *     audio_analysis  音频解析
     *     video_analysis  视频解析
     *     chapter_id      章id
     *     joint_id        节id
     *     point_id        考点id
     *     item_diffculty  试题难度(1代表简单,2代表一般,3代表困难)
     *     is_publish      是否发布(1代表发布,0代表未发布)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-08
     * return string
     */
    public function doInsertExam() {
        //获取提交的参数
        try{
            $data = Exam::doInsertExam(self::$accept_data);
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
     * @param  description   更改试题的方法
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)
     *     exam_id         试题id
     *     exam_content    题目内容
     *     option_list     [
     *         option_no     选项字母
     *         option_name   选项内容
     *         correct_flag  是否为正确选项(1代表是,0代表否)
     *     ]
     *     answer          题目答案
     *     text_analysis   文字解析
     *     audio_analysis  音频解析
     *     video_analysis  视频解析
     *     chapter_id      章id
     *     joint_id        节id
     *     point_id        考点id
     *     item_diffculty  试题难度(1代表简单,2代表一般,3代表困难)
     *     is_publish      是否发布(1代表发布,0代表未发布)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-12
     * return string
     */
    public function doUpdateExam() {
        //获取提交的参数
        try{
            $data = Exam::doUpdateExam(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '更新成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    删除试题的方法
     * @param  参数说明         body包含以下参数[
     *      exam_id    试题id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-11
     * return  array
     */
    public function doDeleteExam(){
        //获取提交的参数
        try{
            $data = Exam::doDeleteExam(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '删除成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    发布试题的方法
     * @param  参数说明         body包含以下参数[
     *      exam_id    试题id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-11
     * return  array
     */
    public function doPublishExam(){
        //获取提交的参数
        try{
            $data = Exam::doPublishExam(self::$accept_data);
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
     * @param  descriptsion    获取试题列表
     * @param  参数说明         body包含以下参数[
     *     bank_id         题库id(必传)
     *     subject_id      科目id(必传)
     *     type            试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)(必传)
     *     is_publish      审核状态(非必传)
     *     chapter_id      章id(非必传)
     *     joint_id        节id(非必传)
     *     point_id        考点id(非必传)
     *     item_diffculty  试题难度(1代表简单,2代表一般,3代表困难)(非必传)
     *     exam_name       试题名称(非必传)
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-09
     * return  array
     */
    public function getExamList(){
        //获取提交的参数
        try{
            $data = Exam::getExamList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取试题列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    根据试题id获取试题详情信息
     * @param  参数说明         body包含以下参数[
     *     exam_id   试题id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-07
     * return  array
     */
    public function getExamInfoById(){
        //获取提交的参数
        try{
            $data = Exam::getExamInfoById(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取试题信息成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    查看材料题方法
     * @param  参数说明         body包含以下参数[
     *     exam_id         试题id(必传)
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-12
     * return  array
     */
    public function getMaterialList(){
        //获取提交的参数
        try{
            $data = Exam::getMaterialList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取材料题信息成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
     * @param  description   保存所选试题生成试卷
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)
     *     papers_id       试卷id
     * ]
     * @param  author        zzk
     * @param  ctime         2020-05-13
     */
    public function InsertTestPaperSelection(){
        //获取提交的参数
        try{
            $data = PapersExam::InsertTestPaperSelection(self::$accept_data);
            if($data['code'] == 200){
                return response()->json($data);
            } else {
                return response()->json($data);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }

    }
    /*
     * @param  description   试题检测重复
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)
     *     papers_id       试卷id
     * ]
     * @param  author        zzk
     * @param  ctime         2020-05-11
     */
    public function RepetitionTestPaperSelection(){
        try{
            $data = PapersExam::GetRepetitionExam(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取试题列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
     * @param  description   手动添加接口
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)
     *     papers_id       试卷id
     *     chapter_id      章id
     *     chapter_id      节id
     *     exam_name       题目名称
     *     page            页码
     * ]
     * @param  author        zzk
     * @param  ctime         2020-05-11
     */
    public function ListTestPaperSelection(){
        try{
            $data = PapersExam::GetExam(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取试题列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
     * @param  description   选择试题接口
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)
     *     papers_id       试卷id
     * ]
     * @param  author        zzk
     * @param  ctime         2020-05-11
     */
    public function doTestPaperSelection(){
        try{
            $data = PapersExam::GetTestPaperSelection(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取试题列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   根据试卷的id获取每种题型的分数
     * @param  参数说明       body包含以下参数[
     *     papers_id       试卷id
     * ]
     * @param author    duzhijian
     * @param ctime     2020-07-03
     * return string
     */
    public function getExamSignleScore(){
        try{
            $data = PapersExam::getExamSignleScore(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '返回数据信息成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /*
     * @param  description   试卷删除试题
     * @param  author        zzk
     * @param  ctime         2020-05-011
     */
    public function deleteTestPaperSelection(){
        try{
            $data = PapersExam::DeleteTestPaperSelection(self::$accept_data);
            if($data['code'] == 200){
                return response()->json($data);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
     * @param  description   试卷试题详情
     * @param  author        zzk
     * @param  ctime         2020-05-011
     */
    public function oneTestPaperSelection(){
        try{
            $data = PapersExam::oneTestPaperSelection(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取试题详情成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
     * @param  description   试题公共参数列表
     * @param  author        dzj
     * @param  ctime         2020-05-09
     */
    public function getExamCommonList(){
        //试题类型
        $exam_array = [
            [
                'id'  =>  1 ,
                'name'=> '单选题'
            ] ,
            [
                'id'  =>  2 ,
                'name'=> '多选题'
            ] ,
            [
                'id'  =>  4 ,
                'name'=> '不定项'
            ],
            [
                'id'  =>  3 ,
                'name'=> '判断题'
            ] ,
            [
                'id'  =>  5 ,
                'name'=> '填空题'
            ] ,
            [
                'id'  =>  6 ,
                'name'=> '简答题'
            ],
            [
                'id'  =>  7 ,
                'name'=> '材料题'
            ]
        ];

        //试题难度
        $diffculty_array = [
            [
                'id'  =>  1 ,
                'name'=> '简单'
            ] ,
            [
                'id'  =>  2 ,
                'name'=> '一般'
            ] ,
            [
                'id'  =>  3 ,
                'name'=> '困难'
            ]
        ];
        return response()->json(['code' => 200 , 'msg' => '返回数据成功' , 'data' => ['diffculty_array' => $diffculty_array , 'exam_array' => $exam_array]]);
    }
    /*
      * @param  试卷试题排序
      * @param  id    array
      * @param  author  苏振文
      * @param  ctime   2020/11/2 10:44
      * return  array
      */
     public function questionsSort(){
         try{
             $data = self::$accept_data;
             if(empty($data['arrid'])){
                 return response()->json(['code' => 202 , 'msg' => '题库id不合法']);
             }
             $dataid = json_decode($data['arrid'],true);
             $i = 0;
             foreach ($dataid as $k=>$v){
                 $i++;
                 PapersExam::where(['id'=>$v])->update(['sort'=>$i]);
             }
             return response()->json(['code' => 200 , 'msg' => '排序成功']);
         } catch (\Exception $ex) {
             return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
         }
     }
    /*
     * @param  description   导入试题功能方法
     * @param  author        dzj
     * @param  ctime         2020-05-14
    */
    public function doImportExam(){
             //获取提交的参数
            //判断题库id是否为空
            if(empty(self::$accept_data['bank_id']) || !is_numeric(self::$accept_data['bank_id']) || self::$accept_data['bank_id'] <= 0){
                return response()->json(['code' => 202 , 'msg' => '题库id不合法']);
            }

            //判断科目id是否为空
            if(empty(self::$accept_data['subject_id']) || !is_numeric(self::$accept_data['subject_id']) || self::$accept_data['subject_id'] <= 0){
                return response()->json(['code' => 202 , 'msg' => '科目id不合法']);
            }
           print_r(self::$accept_data);die;
            //判断此科目是否属于此题库下面
            $is_bank_subject = QuestionSubject::where('id' , self::$accept_data['subject_id'])->where('bank_id' , self::$accept_data['bank_id'])->where('is_del' , 0)->count();
            if($is_bank_subject <= 0){
                return response()->json(['code' => 202 , 'msg' => '此科目不属于此题库下面']);
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
            $exam_list = Exam::doImportExam(self::$accept_data,$is_insert);

            //判断是否导入成功
            if($exam_list['code'] == 200){
                //删除excel表格文件
                //self::delDir($response_data['data']['path']);
                unlink($response_data['data']['path']);
                return response()->json(['code' => 200 , 'msg' => '导入试题列表成功' , 'data' => $exam_list['data']]);
            } else {
                unlink($response_data['data']['path']);
                return response()->json(['code' => $exam_list['code'] , 'msg' => $exam_list['msg']]);
            }
    }

    /*
     * @param  description   校验excel数据接口
     * @param  author        dzj
     * @param  ctime         2020-05-15
    */
    public static function doExamineExcelData() {
        //获取提交的参数
        try{
            //校验excel数据是否合法
            $check_excel = self::checkExamineExcelData();
            if($check_excel['code'] != 200){
                return response()->json(['code' => $check_excel['code'] , 'msg' => $check_excel['msg']]);
            } else {
                //删除excel原始文件
                unlink($check_excel['data']['path']);
                return response()->json(['code' => 200 , 'msg' => '校验成功']);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
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

            //判断excel上传大小是否大于3M
            $excel_size = filesize($_FILES['file']['tmp_name']);
            if($excel_size > 3145728){
                return ['code' => 202 , 'msg' => '上传excel不能大于3M'];
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
            $exam_list = self::doImportExcel(new \App\Imports\UsersImport , $path , 1 , 1000);

            //判断是否超过最大导入量
            if($exam_list['code'] != 200){
                return ['code' => $exam_list['code'] , 'msg' => $exam_list['msg']];
            }

            //去掉试题模板中没有用的列和展示项
            $is_empty_exam = array_slice($exam_list['data'] , 3);

            //判断excel数据传输是否合法
            if(!$is_empty_exam || empty($is_empty_exam)){
                //删除excel原始文件
                unlink($path);
                return ['code' => 202 , 'msg' => '请按照模板上格式导入'];
            }

            //获取excel表格的后缀类型
            /*$excel_key = "excel_true_".$is_correct_extensiton;

            //空数组赋值
            $arr = [];
            foreach($exam_list as $v){
                $arr[] = $v[1];
            }

            //判断redis中是否存在excel表格中数据信息
            $getExcelInfo = Redis::get($excel_key);
            if($getExcelInfo && !empty($getExcelInfo)){
                //获取redis中excel表格数据信息
                $getExcelInfo = json_decode($getExcelInfo , true);
                foreach($getExcelInfo as $k=>$v){
                    if(in_array($v,$arr)){
                        return ['code' => 204 , 'msg' => '请勿重复上传相同试题'];
                        break;
                    }
                }
            } else {
                //存储试题信息
                Redis::setex($excel_key , 60 , json_encode($arr));
            }*/

            //返回正确合法的数据信息
            return ['code' => 200 , 'msg' => '检验数据成功' , 'data' => ['exam_list' => $exam_list , 'path' => $path]];
        } catch (\Exception $ex) {
            return ['code' => 500 , 'msg' => $ex->getMessage()];
        }
    }

    /*
     * @param  description   导出做题记录功能方法
     * @param  参数说明[
     *     student_id     学员id(必传)
     *     bank_id        题库id(非必传)
     *     subject_id     科目id(非必传)
     *     type           类型(非必传)
     *     exam_date      做题日期(非必传)
     * ]
     * @param  author        dzj
     * @param  ctime         2020-04-30
    */
    /*public function doExportExamLog(){
        //获取提交的参数
        return Excel::download(new \App\Exports\ExamExport, 'examlog.xlsx');
    }*/
}
