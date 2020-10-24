<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Papers;

class PapersController extends Controller {
    /*
     * @param  description   增加试卷的方法
     * @param  参数说明       body包含以下参数[
     *     subject_id      科目id
     *     bank_id         题库id
     *     papers_name     试卷名称
     *     diffculty       试题类型(1代表真题,2代表模拟题,3代表其他)
     *     papers_time     答题时间
     *     area            所属区域
     *     cover_img       封面图片
     *     content         试卷描述
     *     type            选择题型(1代表单选题2代表多选题3代表不定项4代表判断题5填空题6简答题7材料题)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-07
     * return string
     */
    public function doInsertPapers() {
        //获取提交的参数
        try{
            $data = Papers::doInsertPapers(self::$accept_data);
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
     * @param  description   更改试卷的方法
     * @param  参数说明       body包含以下参数[
     *     papers_id       试卷id
     *     papers_name     试卷名称
     *     diffculty       试题类型(1代表真题,2代表模拟题,3代表其他)
     *     papers_time     答题时间
     *     area            所属区域
     *     cover_img       封面图片
     *     content         试卷描述
     *     type            选择题型(1代表单选题2代表多选题3代表不定项4代表判断题5填空题6简答题7材料题)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-07
     * return string
     */
    public function doUpdatePapers() {
        //获取提交的参数
        try{
            $data = Papers::doUpdatePapers(self::$accept_data);
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
     * @param  descriptsion    删除试卷的方法
     * @param  参数说明         body包含以下参数[
     *      papers_id   试卷id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-07
     * return  array
     */
    public function doDeletePapers() {
        //获取提交的参数
        try{
            $data = Papers::doDeletePapers(self::$accept_data);
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
     * @param  descriptsion    试卷发布/取消发布的方法
     * @param  参数说明         body包含以下参数[
     *      papers_id   试卷id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-07
     * return  array
     */
    public function doPublishPapers() {
        //获取提交的参数
        try{
            $data = Papers::doPublishPapers(self::$accept_data);
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
     * @param  descriptsion    根据试卷id获取试卷详情信息
     * @param  参数说明         body包含以下参数[
     *     papers_id   试卷id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-06
     * return  array
     */
    public function getPapersInfoById(){
        //获取提交的参数
        try{
            $data = Papers::getPapersInfoById(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取试卷信息成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    获取试卷列表
     * @param  author          dzj
     * @param  ctime           2020-05-07
     * return  array
     */
    public function getPapersList(){
        //获取提交的参数
        try{
            //获取科目对应的试卷列表
            $data = Papers::getPapersList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取试卷列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

   /* @param  decerption     省市县三级联动
    * @param  $body[
    *     region_id   地区id(默认为0)
    * ]
    * @return array
    */
    public function getRegionList(){
        //获取提交的参数
        try{
            //获取地区所属列表
            $data = self::getRegionDataList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取地区列表数据成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
}
