<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionSubject as Subject;
use App\Models\Chapters;

class QuestionController extends Controller {
    /*
     * @param  description   添加题库科目的方法
     * @param  参数说明       body包含以下参数[
     *     bank_id         题库id
     *     subject_name    科目名称
     * ]
     * @param author    dzj
     * @param ctime     2020-04-29
     */
    public function doInsertSubject() {
        //获取提交的参数
        try{
            $data = Subject::doInsertSubject(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '添加成功' , 'data' => ['subject_id' => $data['data']]]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   更改题库科目的方法
     * @param  参数说明       body包含以下参数[
     *     subject_id   科目id
     *     subject_name 题库科目名称
     * ]
     * @param author    dzj
     * @param ctime     2020-04-29
     */
    public function doUpdateSubject() {
        //获取提交的参数
        try{
            $data = Subject::doUpdateSubject(self::$accept_data);
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
     * @param  descriptsion    删除题库科目的方法
     * @param  参数说明         body包含以下参数[
     *      subject_id   科目id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-29
     * return  array
     */
    public function doDeleteSubject(){
        //获取提交的参数
        try{
            $data = Subject::doDeleteSubject(self::$accept_data);
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
     * @param  descriptsion    获取题库科目列表
     * @param  参数说明         body包含以下参数[
     *     bank_id   题库id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-25
     * return  array
     */
    public function getSubjectList(){
        //获取提交的参数
        try{
            $data = Subject::getSubjectList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取题库科目列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   增加章节考点的方法
     * @param  参数说明       body包含以下参数[
     *     parent_id         父级id[章id或节id]
     *     subject_id        科目id
     *     bank_id           题库id
     *     name              章节考点名称
     *     type              添加类型(0代表章1代表节2代表考点)
     * ]
     * @param author    dzj
     * @param ctime     2020-04-29
     * return string
     */
    public function doInsertChapters() {
        //获取提交的参数
        try{
            $data = Chapters::doInsertChapters(self::$accept_data);
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
     * @param  description   更改章节考点的方法
     * @param  参数说明       body包含以下参数[
     *     chapters_id       章节考点id
     *     name              章节考点名称
     * ]
     * @param author    dzj
     * @param ctime     2020-04-29
     * return string
     */
    public function doUpdateChapters() {
        //获取提交的参数
        try{
            $data = Chapters::doUpdateChapters(self::$accept_data);
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
     * @param  descriptsion    删除章节考点的方法
     * @param  参数说明         body包含以下参数[
     *      chapters_id   章节考点id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-29
     * return  array
     */
    public function doDeleteChapters(){
        //获取提交的参数
        try{
            $data = Chapters::doDeleteChapters(self::$accept_data);
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
     * @param  descriptsion    获取章节考点列表
     * @param  参数说明         body包含以下参数[
     *     bank_id     题库id
     *     subject_id  科目id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-29
     * return  array
     */
    public function getChaptersList(){
        //获取提交的参数
        try{
            $data = Chapters::getChaptersList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取章节考点列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    获取章节考点选择列表
     * @param  参数说明         body包含以下参数[
     *     bank_id         题库id
     *     subject_id      科目id
     *     chapters_id     章节id
     *     type            查询类型(0代表章1代表节2代表考点)
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-09
     * return  array
     */
    public function getChaptersSelectList(){
        //获取提交的参数
        try{
            $data = Chapters::getChaptersSelectList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取章节考点选择列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

	/*
     * @param  doUpdateListSort   更改章节考点排序
     * @param  参数说明       body包含以下参数[
     *     chapters_id       章节考点id,[1,2,3,4 ...  ....]
     * ]
     * @param author    sxh
     * @param ctime     2020-10-23
     * return string
     */
    public function doUpdateListSort() {
        //获取提交的参数
        try{
            $data = Chapters::doUpdateListSort(self::$accept_data);
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
    * @param  doUpdateListSort   更改科目排序
    * @param  id        科目id,[1,2,3,4 ...  ....]
    * @param author    sxh
    * @param ctime     2020-10-23
    * return string
    */
    public function doUpdateSubjectListSort(){

        try{
            $data = Subject::doUpdateSubjectListSort(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
}
