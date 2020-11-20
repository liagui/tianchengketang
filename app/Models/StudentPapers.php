<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StudentPapers extends Model {
    //指定别的表名
    public $table      = 'ld_student_papers';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  getStudentBankList    获取学员做题记录
     * @param  参数说明         student_id   学员id
     * @param  author          sxh
     * @param  ctime           2020-10-26
     * return  array
     */
    public static function getStudentBankList($data) {
        //判断数据信息是否为空
        if(empty($data['student_id']) || !is_numeric($data['student_id']) || $data['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不能为空' , 'data' => ['']];
        }
        //分页部分
        if(isset($data['pagesize']) && isset($data['page'])){
            $studentList = self::getStudentBankInfoPage($data);
            return ['code' => 200 , 'msg' => '获取做题记录列表成功' , 'data' => ['list' => $studentList , 'total' => count($studentList) , 'pagesize' => $data['pagesize'] , 'page' => $data['page']]];
        }
        //没有分页
        $studentList = self::getStudentBankInfo($data);
		
        return ['code' => 200 , 'msg' => '获取做题记录列表成功','data'=>$studentList];
    }



    /*
     * @param  getStudentBankInfoPage    获取学员做题记录信息-分页
     * @param  参数说明         $data   参数信息
     *                             student_id  学员id
     *                             page  pagesize  分页
     * @param  author          sxh
     * @param  ctime           2020-10-27
     * return  array
     */
    private static function getStudentBankInfoPage($data){
        //获取分页
        $offset   = ($data['page'] - 1) * $data['pagesize'];
        //获取学员做题信息
        $studentList = self::leftJoin('ld_student_do_title','ld_student_do_title.id','=','ld_student_papers.papers_id')
            ->leftJoin('ld_question_bank','ld_question_bank.id','=','ld_student_papers.bank_id')
            ->leftJoin('ld_question_subject','ld_question_subject.id','=','ld_student_papers.subject_id')
            ->leftJoin('ld_question_papers','ld_question_papers.id','=','ld_student_papers.papers_id')
            ->where(['ld_student_papers.student_id'=>$data['student_id']])
            ->where(function($query) use ($data){
                //判断题库id是否为空
                if(isset($data['bank_id']) && $data['bank_id'] > 0){
                    $query->where('ld_student_papers.bank_id' , '=' , $data['bank_id']);
                }
                //判断科目id是否为空
                if(isset($data['subject_id']) && $data['subject_id'] > 0){
                    $query->where('ld_student_papers.subject_id' , '=' , $data['subject_id']);
                }
                //判断类型id是否为空
                if(isset($data['type_id']) && $data['type_id'] > 0){
                    $query->where('ld_question_papers.diffculty' , '=' , $data['type_id']);
                }
                //判断开始时间是否为空
                if(isset($data['start_time'])){
                    $query->where('ld_student_papers.create_at' , '<' , $data['start_time']);
                }
                //判断结束时间是否为空
                if(isset($data['end_time'])){
                    $query->where('ld_student_papers.create_at' , '>' , $data['start_time']);
                }
            })
            ->select('ld_student_papers.id as new_papers_id','ld_student_papers.create_at','ld_student_papers.bank_id','ld_question_bank.topic_name as bank_name','ld_student_papers.subject_id','ld_question_subject.subject_name','ld_student_papers.papers_id as moni_papers_id','ld_question_papers.papers_name','ld_question_papers.diffculty','ld_student_papers.student_id','ld_student_papers.answer_score','ld_student_papers.type as ttype')
            ->offset($offset)->limit($data['pagesize'])
            ->get()->toArray();

        return self::getStudentListInfo($studentList,$data['page'],$data['pagesize']);
    }

    /*
     * @param  getStudentBankInfo    获取学员做题记录信息-分页
     * @param  参数说明         $data   参数信息
     *                             student_id  学员id
     *                             page  pagesize  分页
     * @param  author          sxh
     * @param  ctime           2020-10-27
     * return  array
     */
    private static function getStudentBankInfo($data){
        //获取学员做题信息
        $studentList = self::leftJoin('ld_student_do_title','ld_student_do_title.id','=','ld_student_papers.papers_id')
            ->leftJoin('ld_question_bank','ld_question_bank.id','=','ld_student_papers.bank_id')
            ->leftJoin('ld_question_subject','ld_question_subject.id','=','ld_student_papers.subject_id')
            ->leftJoin('ld_question_papers','ld_question_papers.id','=','ld_student_papers.papers_id')
            ->where(['ld_student_papers.student_id'=>$data['student_id'],'ld_student_papers.type'=>3])
            ->where(function($query) use ($data){
                //判断题库id是否为空
                if(isset($data['bank_id']) && $data['bank_id'] > 0){
                    $query->where('ld_student_papers.bank_id' , '=' , $data['bank_id']);
                }
                //判断科目id是否为空
                if(isset($data['subject_id']) && $data['subject_id'] > 0){
                    $query->where('ld_student_papers.subject_id' , '=' , $data['subject_id']);
                }
                //判断类型id是否为空
                if(isset($data['type_id']) && $data['type_id'] > 0){
                    $query->where('ld_question_papers.diffculty' , '=' , $data['type_id']);
                }
                //判断开始时间是否为空
                if(isset($data['start_time'])){
                    $query->where('ld_student_papers.create_at' , '<' , $data['start_time']);
                }
                //判断结束时间是否为空
                if(isset($data['end_time'])){
                    $query->where('ld_student_papers.create_at' , '>' , $data['start_time']);
                }
            })
            ->select('ld_question_papers.id as new_papers_id','ld_student_papers.bank_id','ld_question_bank.topic_name as bank_name','ld_student_papers.subject_id','ld_question_subject.subject_name','ld_student_papers.papers_id as moni_papers_id','ld_question_papers.papers_name','ld_question_papers.diffculty','ld_student_papers.student_id','ld_student_papers.answer_score')
            ->get();

        return self::getStudentListInfo($studentList);
    }

    /*
     * @param  getStudentListInfo    获取前端所需要的值
     * @param  参数说明         $studentList   学员做题列表
     * @param  author          sxh
     * @param  ctime           2020-10-27
     * return  array
     */
    private static function getStudentListInfo($studentList,$page='',$pagesize=''){
        //获取列表总数
        $student_list_count = count($studentList);
        //题类型
        $exam_diffculty = [1=>'真题',2=>'模拟题',3=>'其他'];
        foreach ($studentList as $k => $v){
	
			
            //获取题库试卷类型
            $studentList[$k]['type_name']    = isset($exam_diffculty[$v['diffculty']]) && !empty($exam_diffculty[$v['diffculty']]) ? $exam_diffculty[$v['diffculty']] : '';

            //获取做题数(做错题数+做对题数)  
            $sum_exam_count = StudentDoTitle::where(['student_id'=>$v['student_id'],'bank_id'=>$v['bank_id'],'subject_id'=>$v['subject_id'],'papers_id'=>$v['new_papers_id']])->count();
            //$do_exam_count  = StudentDoTitle::where(['student_id'=>$v['student_id'],'bank_id'=>$v['bank_id'],'subject_id'=>$v['subject_id'],'papers_id'=>$v['new_papers_id']])->where('is_right' , '>' , 0)->count();
            $do_exam_count  = StudentDoTitle::where(['student_id'=>$v['student_id'],'bank_id'=>$v['bank_id'],'subject_id'=>$v['subject_id'],'papers_id'=>$v['new_papers_id']])->where('answer','!=','')->count();
			$studentList[$k]['doTitleCount'] = $do_exam_count.'/'.$sum_exam_count.'题';

            //总分
            $studentList[$k]['answer_score'] = !empty($v['answer_score']) ? $v['answer_score'] : 0;

            //正确题数
            $correct_count = StudentDoTitle::where(['student_id'=>$v['student_id'],'bank_id'=>$v['bank_id'],'subject_id'=>$v['subject_id'],'papers_id'=>$v['new_papers_id']])->where('is_right' , 1)->count();
            //错误题数
            $error_count   = StudentDoTitle::where(['student_id'=>$v['student_id'],'bank_id'=>$v['bank_id'],'subject_id'=>$v['subject_id'],'papers_id'=>$v['new_papers_id']])->where('is_right' , 2)->count();
            //正确率(已做题目正确数/已做题目总数)
            if($do_exam_count == 0){
                $studentList[$k]['score_avg'] = 0.00.'%';
            }else{
                $studentList[$k]['score_avg'] = round($correct_count / $do_exam_count*100,2).'%';
            }
            //正确数    错误数
            if(!empty($page) && !empty($pagesize)){
                $studentList[$k]['correct_count'] = $correct_count;
                $studentList[$k]['error_count']   = $error_count;
            }else{
                $studentList[$k]['do_bank_count'] = '√'.$correct_count.' - x'.$error_count;
                unset($studentList[$k]['bank_id']);
                unset($studentList[$k]['subject_id']);
                unset($studentList[$k]['papers_id']);
                unset($studentList[$k]['diffculty']);
                unset($studentList[$k]['student_id']);
                unset($studentList[$k]['new_papers_id']);
            }


        }
        return $studentList;
    }


}
