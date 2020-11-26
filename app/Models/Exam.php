<?php
namespace App\Models;

use App\Models\Chapters;
use App\Models\ExamOption;
use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use App\Models\QuestionSubject;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class Exam extends Model {
    //指定别的表名
    public $table      = 'ld_question_exam';
    //时间戳设置
    public $timestamps = false;


    /*
     * @param  description   增加试题的方法
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题3代表判断题4代表不定项5填空题6简答题7材料题)
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
    public static function doInsertExam($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断是否为材料题添加子类
        if(!isset($body['exam_id']) || empty($body['exam_id']) || $body['exam_id'] <= 0){
            //判断试题类型是否合法
            if(!isset($body['type']) || empty($body['type']) || !in_array($body['type'] , [1,2,3,4,5,6,7])){
                return ['code' => 202 , 'msg' => '试题类型不合法'];
            }

            //判断科目id是否合法
            if(!isset($body['subject_id']) || empty($body['subject_id']) || $body['subject_id'] <= 0){
                return ['code' => 202 , 'msg' => '科目id不合法'];
            }

            //判断题库id是否合法
            if(!isset($body['bank_id']) || empty($body['bank_id']) || $body['bank_id'] <= 0){
                return ['code' => 202 , 'msg' => '题库id不合法'];
            }
        } else {
            //判断试题类型是否合法
            if(!isset($body['type']) || empty($body['type']) || !in_array($body['type'] , [1,2,3,4,5,6])){
                return ['code' => 202 , 'msg' => '试题类型不合法'];
            }
        }

        //判断是否试题内容是否为空
        if(!isset($body['exam_content']) || empty($body['exam_content'])){
            return ['code' => 202 , 'msg' => '试题内容不合法'];
        }

        //判断添加的是否为材料题
        if($body['type'] < 7){
            //判断是否为(1单选题2多选题3判断4不定项5填空6简答)
            if(in_array($body['type'] , [1,2,3,4,5,6]) && (!isset($body['option_list']) || empty($body['option_list']))){
                return ['code' => 201 , 'msg' => '试题选项为空'];
            }

            //判断单选题和多选题和不定项和判断题
            if(in_array($body['type'] , [1,2,3,4]) && (!isset($body['answer']) || empty($body['answer']))){
                return ['code' => 201 , 'msg' => '答案不能为空'];
            }

            //判断文字解析是否为空
            /*if(!isset($body['text_analysis']) || empty($body['text_analysis'])){
                return ['code' => 201 , 'msg' => '文字解析为空'];
            }

            //判断是音频解析是否为空
            if(!isset($body['audio_analysis']) || empty($body['audio_analysis'])){
                return ['code' => 201 , 'msg' => '音频解析为空'];
            }

            //判断视频解析是否为空
            if(!isset($body['video_analysis']) || empty($body['video_analysis'])){
                return ['code' => 201 , 'msg' => '视频解析为空'];
            }

            //判断章节考点id是否合法
            if((!isset($body['chapter_id']) || empty($body['chapter_id']) || $body['chapter_id'] <= 0) || (!isset($body['joint_id']) || empty($body['joint_id']) || $body['joint_id'] <= 0) || (!isset($body['point_id']) || empty($body['point_id']) || $body['point_id'] <= 0)){
                return ['code' => 201 , 'msg' => '请选择章节考点'];
            }*/

            //判断试题难度是否合法
            if(!isset($body['item_diffculty']) || empty($body['item_diffculty']) || !in_array($body['item_diffculty'] , [1,2,3])){
                return ['code' => 202 , 'msg' => '试题难度不合法'];
            }
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        if($body['type'] == 5){
          $answer='';
          $answerarr = json_decode($body['option_list'],true);
          foreach ($answerarr as $k=>$v){
              $answer= $answer.','.$v['option_name'];
          }
          $answer = trim($answer,',');
        }else{
            $answer =  isset($body['answer']) && !empty($body['answer']) ? trim($body['answer']) : '';
        }
        //判断是否传递试题父级id
        if(isset($body['exam_id']) &&  $body['exam_id'] > 0){
            //试题数据组合
            $exam_arr = [
                'parent_id'     =>  $body['exam_id'] ,
                'exam_content'  =>  $body['exam_content'] ,
                'answer'        =>  $answer,
                'text_analysis' =>  isset($body['text_analysis'])  && !empty($body['text_analysis']) ? $body['text_analysis']   : '' ,
                'audio_analysis'=>  isset($body['audio_analysis']) && !empty($body['audio_analysis']) ? $body['audio_analysis'] : '' ,
                'video_analysis'=>  isset($body['video_analysis']) && !empty($body['video_analysis']) ? $body['video_analysis'] : '' ,
                'chapter_id'    =>  isset($body['chapter_id']) && $body['chapter_id'] > 0 ? $body['chapter_id'] : 0 ,
                'joint_id'      =>  isset($body['joint_id']) && $body['joint_id'] > 0 ? $body['joint_id'] : 0 ,
                'point_id'      =>  isset($body['point_id']) && $body['point_id'] > 0 ? $body['point_id'] : 0 ,
                'type'          =>  $body['type'] ,
                'item_diffculty'=>  $body['item_diffculty'],
                'is_publish'    =>  isset($body['is_publish']) && $body['is_publish'] > 0 ? 1 : 0,
                'create_at'     =>  date('Y-m-d H:i:s')
            ];
        } else {
            //试题数据组合
            $exam_arr = [
                'admin_id'      =>  $admin_id ,
                'subject_id'    =>  $body['subject_id'] ,
                'bank_id'       =>  $body['bank_id'] ,
                'exam_content'  =>  $body['exam_content'] ,
                'answer'        =>  $answer ,
                'text_analysis' =>  isset($body['text_analysis'])  && !empty($body['text_analysis']) ? $body['text_analysis']   : '' ,
                'audio_analysis'=>  isset($body['audio_analysis']) && !empty($body['audio_analysis']) ? $body['audio_analysis'] : '' ,
                'video_analysis'=>  isset($body['video_analysis']) && !empty($body['video_analysis']) ? $body['video_analysis'] : '' ,
                'chapter_id'    =>  isset($body['chapter_id']) && $body['chapter_id'] > 0 ? $body['chapter_id'] : 0 ,
                'joint_id'      =>  isset($body['joint_id']) && $body['joint_id'] > 0 ? $body['joint_id'] : 0 ,
                'point_id'      =>  isset($body['point_id']) && $body['point_id'] > 0 ? $body['point_id'] : 0 ,
                'type'          =>  $body['type'] ,
                'item_diffculty'=>  $body['item_diffculty'],
                'is_publish'    =>  isset($body['is_publish']) && $body['is_publish'] > 0 ? 1 : 0,
                'create_at'     =>  date('Y-m-d H:i:s')
            ];
        }

        //开启事务
        DB::beginTransaction();
        try {
            //将数据插入到表中
            $exam_id = self::insertGetId($exam_arr);
            if($exam_id && $exam_id > 0){
                //判断是否为(1单选题2多选题3判断4不定项5填空题)
                if(in_array($body['type'] , [1,2,3,4,5]) && !empty($body['option_list'])){
                    //添加试题选项
                    ExamOption::insertGetId([
                        'admin_id'       =>   $admin_id ,
                        'exam_id'        =>   $exam_id ,
                        'option_content' =>   $body['option_list'] ,
                        'create_at'      =>   date('Y-m-d H:i:s')
                    ]);
                }

                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Question' ,
                    'route_url'      =>  'admin/question/doInsertExam' ,
                    'operate_method' =>  'insert' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '添加成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '添加失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }

    }

    /*
     * @param  description   更改试题的方法
     * @param  参数说明       body包含以下参数[
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
    public static function doUpdateExam($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断试题的id是否合法
        if(!isset($body['exam_id']) || empty($body['exam_id']) || $body['exam_id'] <= 0){
            return ['code' => 202 , 'msg' => '试题id不合法'];
        }

        //key赋值
        $key = 'exam:update:'.$body['exam_id'];

        //判断此试题是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此试题不存在'];
        } else {
            //判断此试题在试题表中是否存在
            $exam_info = self::find($body['exam_id']);
            if(!$exam_info || empty($exam_info)){
                //存储试题的id值并且保存60s
                Redis::setex($key , 60 , $body['exam_id']);
                return ['code' => 204 , 'msg' => '此试题不存在'];
            }
        }

        //判断是否试题内容是否为空
        if(!isset($body['exam_content']) || empty($body['exam_content'])){
            return ['code' => 201 , 'msg' => '试题内容为空'];
        }

        //判断此试题是哪种类型的[试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)]
        if(in_array($exam_info['type'] , [1,2,3,4,5,6])){
            //判断是否为(1单选题2多选题4不定项)
            if(in_array($exam_info['type'] , [1,2,4,5]) && (!isset($body['option_list']) || empty($body['option_list']))){
                return ['code' => 201 , 'msg' => '试题选项为空'];
            }

            //判断单选题和多选题和不定项和判断题和简答题
            if(in_array($exam_info['type'] , [1,2,3,4,6]) && (!isset($body['answer']) || empty($body['answer']))){
                return ['code' => 201 , 'msg' => '答案为空'];
            }

            //判断文字解析是否为空
            /*if(!isset($body['text_analysis']) || empty($body['text_analysis'])){
                return ['code' => 201 , 'msg' => '文字解析为空'];
            }

            //判断是音频解析是否为空
            if(!isset($body['audio_analysis']) || empty($body['audio_analysis'])){
                return ['code' => 201 , 'msg' => '音频解析为空'];
            }

            //判断视频解析是否为空
            if(!isset($body['video_analysis']) || empty($body['video_analysis'])){
                return ['code' => 201 , 'msg' => '视频解析为空'];
            }

            //判断章节考点id是否合法
            if((!isset($body['chapter_id']) || empty($body['chapter_id']) || $body['chapter_id'] <= 0) || (!isset($body['joint_id']) || empty($body['joint_id']) || $body['joint_id'] <= 0) || (!isset($body['point_id']) || empty($body['point_id']) || $body['point_id'] <= 0)){
                return ['code' => 201 , 'msg' => '请选择章节考点'];
            }*/

            //判断试题难度是否合法
            if(!isset($body['item_diffculty']) || empty($body['item_diffculty']) || !in_array($body['item_diffculty'] , [1,2,3])){
                return ['code' => 202 , 'msg' => '试题难度不合法'];
            }
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        if($body['type'] == 5){
            $answer='';
            $answerarr = json_decode($body['option_list'],true);
            foreach ($answerarr as $k=>$v){
                $answer= $answer.','.$v['option_name'];
            }
            $answer = trim($answer,',');
        }else{
            $answer =  isset($body['answer']) && !empty($body['answer']) ? trim($body['answer']) : '';
        }
        //试题数据组合
        $exam_arr = [
            'exam_content'  =>  $body['exam_content'] ,
            'answer'        =>  $answer ,
            'text_analysis' =>  isset($body['text_analysis'])  && !empty($body['text_analysis']) ?  $body['text_analysis']   : '' ,
            'audio_analysis'=>  isset($body['audio_analysis']) && !empty($body['audio_analysis']) ? $body['audio_analysis']  : '' ,
            'video_analysis'=>  isset($body['video_analysis']) && !empty($body['video_analysis']) ? $body['video_analysis'] : '' ,
            'chapter_id'    =>  isset($body['chapter_id']) && $body['chapter_id'] > 0 ? $body['chapter_id'] : 0 ,
            'joint_id'      =>  isset($body['joint_id']) && $body['joint_id'] > 0 ? $body['joint_id'] : 0 ,
            'point_id'      =>  isset($body['point_id']) && $body['point_id'] > 0 ? $body['point_id'] : 0 ,
            'item_diffculty'=>  $exam_info['type'] < 7 ? $body['item_diffculty'] : 0,
            'is_publish'    =>  isset($body['is_publish']) && $body['is_publish'] > 0 ? 1 : 0,
            'update_at'     =>  date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();
        try {
            //根据试题的id更新试题内容
            $update_exam_info = self::where("id" , $body['exam_id'])->update($exam_arr);
            if($update_exam_info && !empty($update_exam_info)){
                //判断是否为(1单选题2多选题4不定项5填空题)
                if(in_array($exam_info['type'] , [1,2,4,5]) && !empty($body['option_list'])){
                    //更新试题的id更新试题选项
                    ExamOption::where("exam_id" , $body['exam_id'])->update(['option_content' => $body['option_list'] , 'update_at' => date('Y-m-d H:i:s')]);
                }

                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Question' ,
                    'route_url'      =>  'admin/question/doUpdateExam' ,
                    'operate_method' =>  'insert' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '更新成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '更新失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
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
    public static function doDeleteExam($body=[]) {
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断试题id是否合法
        if(!isset($body['exam_id']) || empty($body['exam_id'])){
            return ['code' => 202 , 'msg' => '试题id不合法'];
        }

        //key赋值
        /*$key = 'exam:delete:'.$body['exam_id'];

        //判断此试题是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此试题不存在'];
        } else {
            //试题id赋值(多个会以逗号分隔【例如:1,2,3】)
            $exam_id  = explode(',',$body['exam_id']);

            //判断此试题在试题表中是否存在
            $exam_count = self::whereIn('id',$exam_id)->count();
            if($exam_count <= 0){
                //存储试题的id值并且保存60s
                Redis::setex($key , 60 , $body['exam_id']);
                return ['code' => 204 , 'msg' => '此试题不存在'];
            }
        }*/
        //试题id参数
        $exam_id = json_decode($body['exam_id'] , true);

        //追加更新时间
        $data = [
            'is_del'     => 1 ,
            'update_at'  => date('Y-m-d H:i:s')
        ];

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //开启事务
        DB::beginTransaction();
        try {
            //根据试题id更新删除状态
            if(false !== self::whereIn('id',$exam_id)->update($data)){
                //将试卷中的题删除
                PapersExam::whereIn('exam_id',$exam_id)->update(['is_del'=>1]);
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Question' ,
                    'route_url'      =>  'admin/question/doDeleteExam' ,
                    'operate_method' =>  'delete' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '删除成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '删除失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
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
    public static function doPublishExam($body=[]) {
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断试题id是否合法
        if(!isset($body['exam_id']) || empty($body['exam_id'])){
            return ['code' => 202 , 'msg' => '试题id不合法'];
        }

        //key赋值
        /*$key = 'exam:publish:'.$body['exam_id'];

        //判断此试题是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此试题不存在'];
        } else {
            //试题id赋值(多个会以逗号分隔【例如:1,2,3】)
            $exam_id  = explode(',',$body['exam_id']);

            //判断此试题在试题表中是否存在
            $exam_count = self::whereIn('id',$exam_id)->count();
            if($exam_count <= 0){
                //存储试题的id值并且保存60s
                Redis::setex($key , 60 , $body['exam_id']);
                return ['code' => 204 , 'msg' => '此试题不存在'];
            }
        }*/
        //试题id参数
        $exam_id = json_decode($body['exam_id'] , true);

        //获取试题是否有审核通过了
        $exam_count = self::whereIn('id',$exam_id)->where("is_publish",1)->count();
        if($exam_count && $exam_count > 0){
            //追加更新时间
            $data = [
                'is_publish' => 0 ,
                'update_at'  => date('Y-m-d H:i:s')
            ];
        } else {
            //追加更新时间
            $data = [
                'is_publish' => 1 ,
                'update_at'  => date('Y-m-d H:i:s')
            ];
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        //$exam_id  = explode(',',$body['exam_id']);

        //开启事务
        DB::beginTransaction();
        try {
            //根据试题id更新删除状态
            if(false !== self::whereIn('id',$exam_id)->update($data)){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Question' ,
                    'route_url'      =>  'admin/question/doPublishExam' ,
                    'operate_method' =>  'update' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '操作成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '操作失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }

    }

    /*
     * @param  descriptsion    获取试题列表
     * @param  参数说明         body包含以下参数[
     *     bank_id         题库id(必传)
     *     subject_id      科目id(非必传)
     *     type            试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)(非必传)
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
    public static function getExamList($body=[]) {
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //判断题库id是否为空和合法
        if(!isset($body['bank_id']) || empty($body['bank_id']) || $body['bank_id'] <= 0){
            return ['code' => 202 , 'msg' => '题库id不合法'];
        }

        //key赋值
        /*$key = 'exam:list:'.$body['bank_id'];

        //判断此试题是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 200 , 'msg' => '获取试题列表成功' , 'data' => ['exam_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
        } else {
            $bank_count = self::where('bank_id',$body['bank_id'])->count();
            if($bank_count <= 0){
                //存储试题的id值并且保存60s
                Redis::setex($key , 60 , $body['bank_id']);
                return ['code' => 200 , 'msg' => '获取试题列表成功' , 'data' => ['exam_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
            }
        }*/

        //判断试题类型是否为空和合法
        if(!isset($body['type']) || empty($body['type']) || $body['type'] <= 0 || !in_array($body['type'] , [1,2,3,4,5,6,7])){
            $body['type']   =   1;
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //判断科目id是否为空和合法
        if(!isset($body['subject_id']) || empty($body['subject_id']) || $body['subject_id'] <= 0){
            //根据题库的Id获取最新科目信息
            $subject_info = QuestionSubject::select("id")->where("admin_id" , $admin_id)->where("bank_id" , $body['bank_id'])->where('subject_name' , '!=' , "")->where("is_del" , 0)->orderByDesc('create_at')->first();
            $body['subject_id']  = $subject_info['id'] ? $subject_info['id'] : 0;
        }

        //获取试题的总数量
        $exam_count = self::where(function($query) use ($body){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            $query->where('bank_id' , '=' , $body['bank_id'])->where("subject_id" , "=" , $body['subject_id'])->where("type" , $body['type'])->where("parent_id" , 0)->where('is_del' , '=' , 0);

            //判断审核状态是否为空和合法
            if(isset($body['is_publish']) && in_array($body['is_publish'] , [1,0])){
                $query->where('is_publish' , '=' , $body['is_publish']);
            }

            //判断章id是否为空和合法
            if(isset($body['chapter_id']) && !empty($body['chapter_id']) && $body['chapter_id'] > 0){
                $query->where('chapter_id' , '=' , $body['chapter_id']);
            }

            //判断节id是否为空和合法
            if(isset($body['joint_id']) && !empty($body['joint_id']) && $body['joint_id'] > 0){
                $query->where('joint_id' , '=' , $body['joint_id']);
            }

            //判断考点id是否为空和合法
            if(isset($body['point_id']) && !empty($body['point_id']) && $body['point_id'] > 0){
                $query->where('point_id' , '=' , $body['point_id']);
            }

            //判断试题难度是否为空和合法
            if(isset($body['item_diffculty']) && !empty($body['item_diffculty']) && in_array($body['item_diffculty'] , [1,2,3])){
                $query->where('item_diffculty' , '=' , $body['item_diffculty']);
            }

            //判断试题名称是否为空
            if(isset($body['exam_name']) && !empty(isset($body['exam_name']))){
                $query->where('exam_content','like','%'.$body['exam_name'].'%');
            }
        })->count();

        if($exam_count > 0){
            //获取试题列表
            $exam_list = self::select('id as exam_id','exam_content','is_publish','item_diffculty')->where(function($query) use ($body){
                //题库id
                $query->where('bank_id' , '=' , $body['bank_id'])->where("subject_id" , "=" , $body['subject_id'])->where("type" , $body['type'])->where("parent_id" , 0);

                //删除状态
                $query->where('is_del' , '=' , 0);

                //判断审核状态是否为空和合法
                if(isset($body['is_publish']) && in_array($body['is_publish'] , [1,0])){
                    $query->where('is_publish' , '=' , $body['is_publish']);
                }

                //判断章id是否为空和合法
                if(isset($body['chapter_id']) && !empty($body['chapter_id']) && $body['chapter_id'] > 0){
                    $query->where('chapter_id' , '=' , $body['chapter_id']);
                }

                //判断节id是否为空和合法
                if(isset($body['joint_id']) && !empty($body['joint_id']) && $body['joint_id'] > 0){
                    $query->where('joint_id' , '=' , $body['joint_id']);
                }

                //判断考点id是否为空和合法
                if(isset($body['point_id']) && !empty($body['point_id']) && $body['point_id'] > 0){
                    $query->where('point_id' , '=' , $body['point_id']);
                }

                //判断试题难度是否为空和合法
                if(isset($body['item_diffculty']) && !empty($body['item_diffculty']) && in_array($body['item_diffculty'] , [1,2,3])){
                    $query->where('item_diffculty' , '=' , $body['item_diffculty']);
                }

                //判断试题名称是否为空
                if(isset($body['exam_name']) && !empty(isset($body['exam_name']))){
                    $query->where('exam_content','like','%'.$body['exam_name'].'%');
                }
            })->orderByDesc('create_at')->offset($offset)->limit($pagesize)->get();
            return ['code' => 200 , 'msg' => '获取试题列表成功' , 'data' => ['exam_list' => $exam_list , 'total' => $exam_count , 'pagesize' => $pagesize , 'page' => $page]];
        }
        return ['code' => 200 , 'msg' => '获取试题列表成功' , 'data' => ['exam_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
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
    public static function getExamInfoById($body=[]) {
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断试题id是否合法
        if(!isset($body['exam_id']) || empty($body['exam_id']) || $body['exam_id'] <= 0){
            return ['code' => 202 , 'msg' => '试题id不合法'];
        }

        //key赋值
        $key = 'exam:examinfo:'.$body['exam_id'];

        //判断此试题是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此试题不存在'];
        } else {
            //判断此试题在试题表中是否存在
            $exam_count = self::where('id',$body['exam_id'])->count();
            if($exam_count <= 0){
                //存储试题的id值并且保存60s
                Redis::setex($key , 60 , $body['exam_id']);
                return ['code' => 204 , 'msg' => '此试题不存在'];
            }
        }

        //根据id获取试题详细信息
        $exam_info = self::select('type','exam_content','answer','text_analysis','audio_analysis','video_analysis','chapter_id','joint_id','point_id','item_diffculty','subject_id')->findOrFail($body['exam_id']);

        //根据科目id获取科目名称
        $subject_info  = QuestionSubject::find($exam_info->subject_id);
        $exam_info['subject_name']  = $subject_info['subject_name'];
        $exam_info['item_diffculty']= (string)$exam_info->item_diffculty;

        //选项赋值
        $exam_info['option_list'] = [];

        //根据试题的id获取选项的列表(只有单选题,多选题,不定项有选项,其他没有)
        if(in_array($exam_info['type'] , [1,2,4,5])){
            //根据试题的id获取选项列表
            $option_list = ExamOption::select("option_content")->where("exam_id",$body['exam_id'])->first()->toArray();
            $exam_info['option_list']   =   json_decode($option_list['option_content'] , true);
        }
        return ['code' => 200 , 'msg' => '获取试题信息成功' , 'data' => $exam_info];
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
    public static function getMaterialList($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //判断试题id是否合法
        if(!isset($body['exam_id']) || empty($body['exam_id']) || $body['exam_id'] <= 0 || !is_numeric($body['exam_id'])){
            return ['code' => 202 , 'msg' => '试题id不合法'];
        }

        //key赋值
        $key = 'exam:material:'.$body['exam_id'];

        //判断此试题是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此材料试题不存在'];
        } else {
            //判断此试题在试题表中是否存在
            $exam_info = self::where('id',$body['exam_id'])->where('is_del' , 0)->where("type" , 7)->first();
            if(!$exam_info || empty($exam_info)){
                //存储试题的id值并且保存60s
                Redis::setex($key , 60 , $body['exam_id']);
                return ['code' => 204 , 'msg' => '此材料试题不存在'];
            }

            //根据科目id获取科目名称
            $subject_info  = QuestionSubject::find($exam_info->subject_id);
        }

        //根据材料题获取材料题所属下面的试题列表(单选题，多选题，不定项，判断题，简答题，填空题)
        $material_count = self::where("parent_id" , $body['exam_id'])->where("is_del" , 0)->count();
        if($material_count > 0){
            //获取材料题下面的子类型试题列表
            $material_list = self::select("id","exam_content as content","type as status")->where("parent_id" , $body['exam_id'])->where("is_del" , 0)->orderByDesc('create_at')->offset($offset)->limit($pagesize)->get();
            //返回json数据结构
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['subject_name' => $subject_info['subject_name'] , 'material_info' => $exam_info->exam_content ,'item_diffculty' => $exam_info->item_diffculty ,'chapter_id' => $exam_info->chapter_id ,'joint_id' => $exam_info->joint_id ,'point_id' => $exam_info->point_id , 'child_list' => $material_list , 'total' => $material_count , 'pagesize' => $pagesize , 'page' => $page]];
        } else {
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['subject_name' => $subject_info['subject_name'] , 'material_info' => $exam_info->exam_content , 'item_diffculty' => $exam_info->item_diffculty ,'chapter_id' => $exam_info->chapter_id ,'joint_id' => $exam_info->joint_id ,'point_id' => $exam_info->point_id  ,'child_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
        }
    }


    /*
     * @param  description   随机生成试题的方法
     * @param  参数说明       body包含以下参数[
     *     chapter_id      章id
     *     joint_id        节id
     *     number          试题数量
     *     simple_ratio    简单
     *     kind_ratio      一般
     *     hard_ratio      困难
     * ]
     * @param author    dzj
     * @param ctime     2020-05-12
     * return array
     */
    public static function getRandExamList($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断章是否合法
        if(empty($body['chapter_id']) || !is_numeric($body['chapter_id']) || $body['chapter_id'] <= 0){
            return ['code' => 202 , 'msg' => '章id不合法'];
        }

        //判断节是否合法
        if(empty($body['joint_id']) || !is_numeric($body['joint_id']) || $body['joint_id'] <= 0){
            return ['code' => 202 , 'msg' => '节id不合法'];
        }

        //判断试题数量是否为空
        if(empty($body['number']) || $body['number'] <= 0 || !is_numeric($body['number'])){
            return ['code' => 202 , 'msg' => '试题数量不合法'];
        }

        //判断简单占比是否合法
        if(empty($body['simple_ratio']) || $body['simple_ratio'] <= 0 || !is_numeric($body['simple_ratio'])){
            return ['code' => 202 , 'msg' => '简单占比不合法'];
        }

        //判断一般占比是否合法
        if(empty($body['kind_ratio']) || $body['kind_ratio'] <= 0 || !is_numeric($body['kind_ratio'])){
            return ['code' => 202 , 'msg' => '一般占比不合法'];
        }

        //判断困难占比是否合法
        if(empty($body['hard_ratio']) || $body['hard_ratio'] <= 0 || !is_numeric($body['hard_ratio'])){
            return ['code' => 202 , 'msg' => '困难占比不合法'];
        }

        //简单试题数量
        $simple_number   =   $body['number'] * ($body['simple_ratio'] / 100);
        //一般试题数量
        $kind_number     =   $body['number'] * ($body['kind_ratio'] / 100);
        //困难试题数量
        $hard_number     =   $body['number'] * ($body['hard_ratio'] / 100);

        //简单试题随机
        $simple_exam_list = self::select("id" , "exam_content")->where("is_del" , 0)->where("item_diffculty" , 1)->orderByRaw("RAND()")->limit($simple_number)->get();

        //一般试题随机
        $kind_exam_list   = self::select("id" , "exam_content")->where("is_del" , 0)->where("item_diffculty" , 2)->orderByRaw("RAND()")->limit($kind_number)->get();

        //困难试题随机
        $hard_exam_list   = self::select("id" , "exam_content")->where("is_del" , 0)->where("item_diffculty" , 3)->orderByRaw("RAND()")->limit($hard_number)->get();

        return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => ['simple_exam_list' => $simple_exam_list , 'kind_exam_list' => $kind_exam_list , 'hard_exam_list' => $hard_exam_list]];
    }

    /*
     * @param  description   导入试题功能方法
     * @param  参数说明       $body导入数据参数[
     *     is_insert         是否执行插入操作(1表示是,0表示否)
     * ]
     * @param  author        dzj
     * @param  ctime         2020-05-13
    */
    public static function doImportExam($body=[] , $is_insert = 0){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 201 , 'msg' => '暂无导入的数据信息'];
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //判断导入试题信息是否为空
        if(!$body['data'] || empty($body['data'])){
            return ['code' => 201 , 'msg' => '导入数据为空'];
        }

        //设置试题长度
        $exam_length = [];

        //去掉试题模板中没有用的列和展示项
        $exam_list = array_slice($body['data'] , 3);

        //试题难度(1代表简单,2代表一般,3代表困难)
        $diffculty_array = ['简单' => 1 , '一般' => 2 , '困难' => 3];
        try{
            DB::beginTransaction();
            //空数组赋值
            $arr = [];
            foreach($exam_list as $k=>$v){
                $hanghao = $k +1;
                $exam_content  = $v[1] && !empty($v[1]) ? trim($v[1]) : '';
                if(empty($exam_content)){
                    $arr[] = '第'.$hanghao.'行请填写试题内容';
                    continue;
                }
                $text_analysis = $v[11] && !empty($v[11]) ? trim($v[11]) : '';
                //判断此题库此科目下面此试题是否被添加过
                $is_insert_exam = Exam::where('bank_id' , $body['bank_id'])->where('subject_id' , $body['subject_id'])->where('exam_content' , $exam_content)->where('text_analysis' , $text_analysis)->where('is_del' , 0)->count();
                if($is_insert_exam <= 0){
                    //试题类型赋值
                    $exam_type = $v[0];
                    if(empty($exam_type)){
                        $arr[] = '第'.$hanghao.'行请填写试题类型';
                        continue;
                    }
                    if(!in_array($exam_type , [1,2,3,4,5,6,7])){
                        $arr[] = '第'.$hanghao.'行请填写正确试题类型';
                        continue;
                    }
                    //试题选项空数组赋值
                    $option_list = [];

                    //判断试题类型是单选题或多选题或不定项或填空题
                    if(in_array($exam_type , [1,2,4,5])){
                        //选项对应索引值
                        $option_index = [3=>'A',4=>'B',5=>'C',6=>'D',7=>'E',8=>'F',9=>'G',10=>'H'];

                        //判断A选项的值是否为空
                        for($i=3;$i<11;$i++){
                            //对应的选项列是否为空
                            if($v[$i] && !empty($v[$i])){
                                $option_list[] = [
                                    'option_no'    => $option_index[$i] ,
                                    'option_name'  => $v[$i]  ,
                                    'correct_flag' => $exam_type == 5 ? 1 : strpos($v[2] , $option_index[$i]) !== false ? 1 : 0
                                ];
                            }
                        }
                    }

                    //判断excel表格中章的信息是否为空
                    if($v[13] && !empty($v[13])){
                        //根据章的名称获取章的信息
                        $chapter_info  = Chapters::where('bank_id' , $body['bank_id'])->where('subject_id' , $body['subject_id'])->where('parent_id' , 0)->where("name" , trim($v[13]))->where("type" , 0)->where('is_del' , 0)->first();

                        //如果章不存在则插入
                        if(!$chapter_info || empty($chapter_info)){
                            $chapter_id = Chapters::insertGetId([
                                'parent_id'      =>   0 ,
                                'subject_id'     =>   $body['subject_id'] ,
                                'admin_id'       =>   $admin_id ,
                                'bank_id'        =>   $body['bank_id'] ,
                                'name'           =>   trim($v[13]) ,
                                'type'           =>   0 ,
                                'create_at'      =>   date('Y-m-d H:i:s', time()+10)
                            ]);
                        } else {
                            $chapter_id = $chapter_info['id'];
                        }
                    }else{
                        $arr[] = '第'.$hanghao.'行请填写章';
                        continue;
                    }

                    //判断excel表格中节的信息是否为空
                    if($v[14] && !empty($v[14])){
                        //根据节的名称获取节的信息
                        $joint_info    = Chapters::where('bank_id' , $body['bank_id'])->where('subject_id' , $body['subject_id'])->where('parent_id' , $chapter_id)->where("name" , trim($v[14]))->where("type" , 1)->where('is_del' , 0)->first();

                        //如果节不存在则插入
                        if(!$joint_info || empty($joint_info)){
                            $joint_id = Chapters::insertGetId([
                                'parent_id'      =>   $chapter_id ,
                                'subject_id'     =>   $body['subject_id'] ,
                                'admin_id'       =>   $admin_id ,
                                'bank_id'        =>   $body['bank_id'] ,
                                'name'           =>   trim($v[14]) ,
                                'type'           =>   1 ,
                                'create_at'      =>   date('Y-m-d H:i:s', time()+10)
                            ]);
                        } else {
                            $joint_id = $joint_info['id'];
                        }
                    }else{
                        $arr[] = '第'.$hanghao.'行请填写小节';
                        continue;
                    }

                    //判断excel表格中考点的信息是否为空
                    if($v[15] && !empty($v[15])){
                        //根据考点的名称获取考点的信息
                        $point_info    = Chapters::where('bank_id' , $body['bank_id'])->where('subject_id' , $body['subject_id'])->where('parent_id' , $joint_id)->where("name" , trim($v[15]))->where("type" , 2)->where('is_del' , 0)->first();

                        //如果考点不存在则插入
                        if(!$point_info || empty($point_info)){
                            $point_id = Chapters::insertGetId([
                                'parent_id'      =>   $joint_id ,
                                'subject_id'     =>   $body['subject_id'] ,
                                'admin_id'       =>   $admin_id ,
                                'bank_id'        =>   $body['bank_id'] ,
                                'name'           =>   trim($v[15]) ,
                                'type'           =>   2 ,
                                'create_at'      =>   date('Y-m-d H:i:s', time()+10)
                            ]);
                        } else {
                            $point_id = $point_info['id'];
                        }
                    }else{
                        $arr[] = '第'.$hanghao.'行请填写考点';
                        continue;
                    }

                    //判断是否执行插入操作
                    if($is_insert > 0){
                        //试题插入操作
                        $exam_id = self::insertGetId([
                            'bank_id'        =>  $body['bank_id'] ,                                        //题库的id
                            'subject_id'     =>  $body['subject_id'] ,                                     //科目的id
                            'admin_id'       =>  $admin_id ,                                               //后端的操作员id
                            'exam_content'   =>  !empty($v[1]) ? trim($v[1]) : '' ,                        //试题内容
                            'answer'         =>  $exam_type == 3 ? $v[2] == '正确' ?  '正确'  : '错误'  : trim($v[2])  ,   //试题答案
                            'text_analysis'  =>  !empty($v[11]) ? trim($v[11]) : '' ,                      //文字解析
                            'item_diffculty' =>  !empty($v[12]) ? $diffculty_array[trim($v[12])] : 0 ,     //试题难度
                            'chapter_id'     =>  $v[13] && !empty($v[13]) ? $chapter_id > 0 ? $chapter_id : 0 : 0,         //章id
                            'joint_id'       =>  $v[14] && !empty($v[14]) ? $joint_id > 0 ? $joint_id : 0 : 0,             //节id
                            'point_id'       =>  $v[15] && !empty($v[15]) ? $point_id > 0 ? $point_id : 0 : 0,             //考点id
                            'type'           =>  $exam_type,                                               //试题类型
                            'create_at'      =>  date('Y-m-d H:i:s')                                       //创建时间
                        ]);

                        //判断是否插入成功试题
                        if($exam_id > 0 && in_array($exam_type , [1,2,4,5])){
                            //试题选项插入
                            ExamOption::insertGetId([
                                'admin_id'       =>  $admin_id ,                                           //后端的操作员id
                                'exam_id'        =>  $exam_id ,                                            //试题的id
                                'option_content' =>  $option_list ? json_encode($option_list) : [],        //试题选项
                                'create_at'      =>  date('Y-m-d H:i:s')                                   //创建时间
                            ]);
                        }
                    } else {
                        //数组信息赋值
                        $arr[$exam_type][] = [
                            'exam_content'  =>  $v[1]  ,                                                  //试题内容
                            'answer'        =>  $exam_type == 3 ? $v[2] == '正确' ?  '正确'  : '错误'  : trim($v[2])  ,   //试题答案
                            'text_analysis' =>  !empty($v[11]) ? $v[11] : '' ,                            //文字解析
                            'item_diffculty'=>  !empty($v[12]) ? $diffculty_array[trim($v[12])] : 0 ,     //试题难度
                            'chapter_id'     =>  $v[13] && !empty($v[13]) ? $chapter_id > 0 ? $chapter_id : 0 : 0,         //章id
                            'joint_id'       =>  $v[14] && !empty($v[14]) ? $joint_id > 0 ? $joint_id : 0 : 0,             //节id
                            'point_id'       =>  $v[15] && !empty($v[15]) ? $point_id > 0 ? $point_id : 0 : 0,             //考点id
                            'option_list'   =>  $option_list ? $option_list : []                          //试题选项
                        ];
                    }
                } else {
                    $exam_length[] = 1;
                }
            }
            //判断此excel试题是否导入过一遍了
            if(count($exam_list) == count($exam_length)){
                //返回信息数据
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '该文件试题已被导入'];
            } else {
                //事务提交
                DB::commit();
                //返回信息数据
                return ['code' => 200 , 'msg' => '成功' , 'data' => $arr];
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
}
