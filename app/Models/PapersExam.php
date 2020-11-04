<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use App\Models\Papers;
use App\Models\Exam;
use Validator;
use Illuminate\Support\Facades\Redis;
class PapersExam extends Model {
    //指定别的表名
    public $table      = 'ld_question_papers_exam';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   试卷选择试题添加
     * @param  参数说明       body包含以下参数[
     * 操作员id
     *  subject_id  科目id
     *  papers_id   试卷id
     *  exam_id 试题id
     *  type    试题类型
     *  grade   每题得分
     * ]
     * @param  author        zzk
     * @param  ctime         2020-05-11
     */
    public static function InsertTestPaperSelection($body=[]){
        //规则结构
        $rule = [
            'subject_id'   =>   'required|numeric' ,
            'papers_id'    =>   'required|numeric' ,
            'exam_array'   =>   'required'
        ];

        //信息提示
        $message = [
            'subject_id.required'   =>  json_encode(['code'=>201,'msg'=>'科目id为空']) ,
            'papers_id.required'   =>  json_encode(['code'=>201,'msg'=>'试卷id为空']) ,
            'exam_array.required'   =>  json_encode(['code'=>201,'msg'=>'试题列表为空'])
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        $where = [];

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //获取选择得题得列表
        $exam_array = json_decode($body['exam_array'] , true);
        if(count($exam_array) <= 0){
            return ['code' => 201 , 'msg' => '请选择试题'];
        }

        //新数组赋值
        $exam_arr = [];

        //去掉删除的试题信息
        foreach($exam_array as $k=>$v){
            if($v['is_del'] <= 0){
                $exam_arr[] = $v;
            }
        }

        //判断是否有试题提交过来
        if(count($exam_arr) <= 0){
            return ['code' => 201 , 'msg' => '请选择试题'];
        }

        //根据试卷的id更新试题类型的每题分数
        $papers_info = Papers::where("id" , $body['papers_id'])->first();
        foreach($exam_arr as $k=>$v){
            //判断此试题在试卷中是否存在
            $exam_count = self::where("subject_id" , $body['subject_id'])->where("papers_id" , $body['papers_id'])->where("exam_id" , $v['exam_id'])->count();

            //数据数组组装
            $data = [
                "subject_id" => $body['subject_id'],
                "papers_id"  => $body['papers_id'],
                "exam_id"    => $v['exam_id'],
                "type"       => $v['type'],
                "grade"      => $v['grade'],
                "admin_id"   => $admin_id,
                "create_at"  => date('Y-m-d H:i:s')
            ];

            //试题类型
            $type = explode(',' , $papers_info['type']);
            if(in_array($v['type'] , $type)){
                if($v['type'] == 1){
                    $where['signle_score'] = $v['grade'];
                } else if($v['type'] == 2){
                    $where['more_score']   = $v['grade'];
                } else if($v['type'] == 3){
                    $where['judge_score']  = $v['grade'];
                } else if($v['type'] == 4){
                    $where['options_score']= $v['grade'];
                } else if($v['type'] == 5){
                    $where['pack_score']   = $v['grade'];
                } else if($v['type'] == 6){
                    $where['short_score']  = $v['grade'];
                } else if($v['type'] == 7){
                    $where['material_score'] = $v['grade'];
                }

                //更新分数的操作
                Papers::where("id" , $body['papers_id'])->update($where);
            }

            //判断试题的id是否大于0
            if($v['exam_id'] && $v['exam_id'] > 0){
                //判断试卷中试题是否存在
                if($exam_count <= 0){
                    //查询上一条的的sort数值
                    $sort = PapersExam::where(['papers_id'=>$body['papers_id'],'type'=>$v['type']])->orderBy('sort','desc')->first();
                    if(!empty($sort)){
                        $data['sort'] = $sort['sort'] + 1;
                    }else{
                        $data['sort'] = 1;
                    }
                    //将数据插入到表中
                    $papersexam_id = self::insertGetId($data);
                } else {
                    //将数据更新到表中
                    $papersexam_id = self::where("exam_id",$v['exam_id'])->update(['is_del'=>$v['is_del'] , 'update_at' => date('Y-m-d H:i:s')]);
                }
            } else {
                $papersexam_id = 1;
            }
        }

        //插入日志数据
        if($papersexam_id && $papersexam_id > 0){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Question' ,
                'route_url'      =>  'admin/question/InsertTestPaperSelection' ,
                'operate_method' =>  'insert' ,
                'content'        =>  json_encode($body) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '添加试题到试卷成功'];
        } else {
            return ['code' => 203 , 'msg' => '添加试题到试卷失败'];
        }
    }
    /*
     * @param  description   获取试题数据
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题3代表不定项4代表判断题5填空题6简答题7材料题)
     *     papers_id       试卷id
     *     chapter_id      章id
     *     chapter_id      节id
     *     exam_name       题目名称
     *     page            页码
     * ]
     * @param author    zzk
     * @param ctime     2020-05-12
     * return string
     */
    public static function GetExam($body=[],$page =1,$limit = 10){
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->id) ? AdminLog::getAdminInfo()->id : 0;

        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //获取总页码

        //获取试卷id
        $papers_id = $body['papers_id'];
        //获取章id
        $chapter_id = $body['chapter_id'];
        //获取节id
        $joint_id = $body['joint_id'];
        //获取题目名称
        $exam_name = $body['exam_name'];
        if(isset($exam_name) && isset($chapter_id) && isset($joint_id)){
            //通过条件获取所有试题
            $exam_count = Exam::where(['is_del'=>1])->orWhere('exam_content', 'like', '%'.$exam_name.'%')->orWhere('joint_id',$joint_id)->orWhere('chapter_id',$chapter_id)->count();
            $exam_list = Exam::where(['is_del'=>1])->orWhere('exam_content', 'like', '%'.$exam_name.'%')->orWhere('joint_id',$joint_id)->orWhere('chapter_id',$chapter_id)->select('id','exam_content','item_diffculty','chapter_id','joint_id')->forPage($page,$limit)->get()->toArray();
        }else{
            $exam_count = Exam::where('is_del' , '=' , 0)->count();
            $exam_list = Exam::where(['is_del'=>0])->select('id','exam_content','item_diffculty')->forPage($page,$limit)->get()->toArray();
        }

        return ['code' => 200 , 'msg' => '获取成功','data'=>['exam_list' => $exam_list , 'total' => $exam_count , 'pagesize' => $pagesize , 'page' => $page]];
    }
    /*
     * @param  description   检测试卷试题
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题3代表不定项4代表判断题5填空题6简答题7材料题)
     *     papers_id       试卷id
     * ]
     * @param author    zzk
     * @param ctime     2020-05-11
     * return string
     */
    public static function GetRepetitionExam($body=[]){
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->id) ? AdminLog::getAdminInfo()->id : 0;
        //获取试卷id
        $papers_id = $body['papers_id'];
        //获取分类
        $type = $body['type'];
        if(!empty($type)){
            //通过试卷id获取该试卷下的所有试题按照分类进行搜索
            $exam = self::where(['ld_question_papers_exam.papers_id'=>$papers_id,'ld_question_papers_exam.type'=>$type,'ld_question_papers_exam.is_del'=>0])
            ->join('ld_question_exam', 'ld_question_papers_exam.exam_id', '=', 'ld_question_exam.id')
            ->select('ld_question_papers_exam.id','ld_question_papers_exam.exam_id','ld_question_exam.exam_content')
            ->get()
            ->toArray();
        }else{
            $exam = self::where(['ld_question_papers_exam.papers_id'=>$papers_id,'ld_question_papers_exam.is_del'=>0])
            ->join('ld_question_exam', 'ld_question_papers_exam.exam_id', '=', 'ld_question_exam.id')
            ->select('ld_question_papers_exam.id','ld_question_papers_exam.exam_id','ld_question_exam.exam_content')
            ->get()
            ->toArray();
        }
        $last_ages = array_column($exam,'exam_id');
        array_multisort($last_ages ,SORT_ASC,$exam);
        return ['code' => 200 , 'msg' => '获取成功','data'=>$exam];
    }
    /*
     * @param  description   选择试题
     * @param  参数说明       body包含以下参数[
     *     type            试题类型(1代表单选题2代表多选题3代表不定项4代表判断题5填空题6简答题7材料题)
     *     papers_id       试卷id
     * ]
     * @param author    zzk
     * @param ctime     2020-05-11
     * return string
     */
    public static function GetTestPaperSelection($body=[]){
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->id) ? AdminLog::getAdminInfo()->id : 0;
        //获取试卷id
        $papers_id = $body['papers_id'];
        //获取分类
        $type = $body['type'];
        if(!empty($type)){
            //通过试卷id获取该试卷下的所有试题按照分类进行搜索
            $exam = self::where(['papers_id'=>$papers_id,'type'=>$type,'is_del'=>0])->select('id','exam_id' , 'type')->orderBy('sort','asc')->get()->toArray();
        }else{
            $exam = self::where(['papers_id'=>$papers_id,'is_del'=>0])->select('id','exam_id' , 'type')->get()->toArray();
        }
        print_r($exam);die;

        foreach($exam as $k => $exams){
            if(empty(Exam::where(['id'=>$exams['exam_id'],'is_del'=>0])->select('exam_content')->first()['exam_content'])){
                unset($exam[$k]);
            }else{
                $exam[$k]['exam_content'] = Exam::where(['id'=>$exams['exam_id'],'is_del'=>0])->select('exam_content')->first()['exam_content'];
            }

            //根据试卷的id获取试卷详情
            $parpers_info =  Papers::where("id" , $papers_id)->first();

            //单选题
            if($exams['type'] == 1) {
                $score = $parpers_info['signle_score'];
            } else if($exams['type'] == 2){
                $score = $parpers_info['more_score'];
            } else if($exams['type'] == 3){
                $score = $parpers_info['judge_score'];
            } else if($exams['type'] == 4){
                $score = $parpers_info['options_score'];
            } else if($exams['type'] == 5){
                $score = $parpers_info['pack_score'];
            } else if($exams['type'] == 6){
                $score = $parpers_info['short_score'];
            } else if($exams['type'] == 7){
                $score = $parpers_info['material_score'];
            }
            $exam[$k]['score']  = $score;
        }
        return ['code' => 200 , 'msg' => '获取成功','data'=>$exam];
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
    public static function getExamSignleScore($body=[]){
        //判断试卷的id是否为空
        if(!isset($body['papers_id']) || $body['papers_id'] <= 0){
            return ['code' => 202 , 'msg' => '试卷的id不合法'];
        }

        //根据试卷的id获取试卷详情
        $parpers_info =  Papers::where("id" , $body['papers_id'])->first();

        //分数数组组装
        $score_array = [
            1 => $parpers_info['signle_score'] ,
            2 => $parpers_info['more_score'] ,
            3 => $parpers_info['judge_score'] ,
            4 => $parpers_info['options_score'] ,
            5 => $parpers_info['pack_score'] ,
            6 => $parpers_info['short_score'] ,
            7 => $parpers_info['material_score']
        ];

        return ['code' => 200 , 'msg' => '返回数据信息成功','data' => $score_array];
    }

    /*
     * @param  description   软删试卷试题
     * @param  参数说明       body包含以下参数
     * [
     *     papersexam_id       试卷内试题id
     * ]
     * @param author    zzk
     * @param ctime     2020-05-11
     * return string
     */
    public static function DeleteTestPaperSelection($body=[]){
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->id) ? AdminLog::getAdminInfo()->id : 0;
        //获取试题id
        $papersexam_id = $body['papersexam_id'];

        $examOne = self::where(['id'=>$papersexam_id])->first();
        if(!$examOne){
            return ['code' => 204 , 'msg' => '参数不对'];
        }

        //追加更新时间
        $data = [
            'is_del'     => 1 ,
            'update_at'  => date('Y-m-d H:i:s')
        ];

        //根据题库id更新删除状态
        if(false !== self::where('id',$body['papersexam_id'])->update($data)){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Question' ,
                'route_url'      =>  'admin/question/DeleteTestPaperSelection' ,
                'operate_method' =>  'delete' ,
                'content'        =>  json_encode($body) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '删除成功'];
        } else {
            return ['code' => 203 , 'msg' => '删除失败'];
        }
    }
    /*
     * @param  description   获取试卷试题详细
     * @param  参数说明       body包含以下参数
     * [
     *     exam_id       试题id
     * ]
     * @param author    zzk
     * @param ctime     2020-05-11
     * return string
     */
    public static function oneTestPaperSelection($body=[]){
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->id) ? AdminLog::getAdminInfo()->id : 0;
        //获取试题id
        $exam_id = $body['exam_id'];
        $examOne = Exam::where(['id'=>$exam_id])->first();
        if(empty($examOne)){
            $examOne = array();
        }
        return ['code' => 200 , 'msg' => '获取成功','data'=>$examOne];

    }
}
