<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDoTitle extends Model {
    //指定别的表名
    public $table      = 'ld_student_do_title';
    //时间戳设置
    public $timestamps = false;
	
	/*
     * @param  getStudentBankDetails    获取学员做题记录详情
     * @param  $student_id    学员id
     * @param  $bank_id       题库id
     * @param  $subject_id    科目id
     * @param  $papers_id     试卷id
     * @param  author          sxh
     * @param  ctime           2020-10-27
     * return  array
     */
    public static function getStudentBankDetails($data) {

        //判断学员信息是否为空
        if(empty($data['student_id']) || !is_numeric($data['student_id']) || $data['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不能为空' , 'data' => ['']];
        }
        //判断题库信息是否为空
        if(empty($data['bank_id']) || !is_numeric($data['bank_id']) || $data['bank_id'] <= 0){
            return ['code' => 202 , 'msg' => '题库id不能为空' , 'data' => ['']];
        }
        //判断科目信息是否为空
        if(empty($data['subject_id']) || !is_numeric($data['subject_id']) || $data['subject_id'] <= 0){
            return ['code' => 202, 'msg' => '科目id不能为空', 'data' => ['']];
        }
        //判断试卷信息是否为空
        if(empty($data['papers_id']) || !is_numeric($data['papers_id']) || $data['papers_id'] <= 0){
            return ['code' => 202, 'msg' => '试卷id不能为空', 'data' => ['']];
        }

        $papers_list = [];
        $diffculty_name = [1=>'正确',2=>'错误',3=>'未答'];
        $type_name = [1=>'单选题',2=>'多选题',3=>'判断题',4=>'不定项',5=>'填空题',6=>'简答题',7=>'材料题'];

        //单选题
        $data['type'] = 1;
        $signle = self::getStudentListSql($data);
        foreach($signle as $k => $v){
            $signle[$k]['number'] = $k+1;
            $signle[$k]['is_right']    = isset($diffculty_name[$v['is_right']]) && !empty($diffculty_name[$v['is_right']]) ? $diffculty_name[$v['is_right']] : '未答';
            $signle[$k]['type_name']    = isset($type_name[$v['type']]) && !empty($type_name[$v['type']]) ? $type_name[$v['type']] : '';
        }
        $signle_count = count($signle);
        $papers_list['单选题'] = ['count' => $signle_count , 'list' => $signle];

        //多选题
        $data['type'] = 2;
        $more = self::getStudentListSql($data);
        foreach($more as $k => $v){
            $more[$k]['number'] = $signle_count+$k+1;
            $more[$k]['is_right']    = isset($diffculty_name[$v['is_right']]) && !empty($diffculty_name[$v['is_right']]) ? $diffculty_name[$v['is_right']] : '未答';
            $more[$k]['type_name']    = isset($type_name[$v['type']]) && !empty($type_name[$v['type']]) ? $type_name[$v['type']] : '';
        }
        $more_count = count($more);
        $papers_list['多选题'] = ['count' => $more_count , 'list' => $more];

        //判断题
        $data['type'] = 3;
        $judge = self::getStudentListSql($data);
        foreach($judge as $k => $v){
            $judge[$k]['number'] = $k+1+$signle_count+$more_count;
            $judge[$k]['is_right']    = isset($diffculty_name[$v['is_right']]) && !empty($diffculty_name[$v['is_right']]) ? $diffculty_name[$v['is_right']] : '未答';
            $judge[$k]['type_name']    = isset($type_name[$v['type']]) && !empty($type_name[$v['type']]) ? $type_name[$v['type']] : '';
        }
        $judge_count = count($judge);
        $papers_list['判断题'] = ['count' => $judge_count , 'list' => $judge];

        //不定项
        $data['type'] = 4;
        $options = self::getStudentListSql($data);
        foreach($options as $k => $v){
            $options[$k]['number'] = $k+1+$signle_count+$more_count+$judge_count;
            $options[$k]['is_right']    = isset($diffculty_name[$v['is_right']]) && !empty($diffculty_name[$v['is_right']]) ? $diffculty_name[$v['is_right']] : '未答';
            $options[$k]['type_name']    = isset($type_name[$v['type']]) && !empty($type_name[$v['type']]) ? $type_name[$v['type']] : '';
        }
        $options_count = count($options);
        $papers_list['不定项'] = ['count' => $options_count , 'list' => $options];

        //填空题
        $data['type'] = 5;
        $pack = self::getStudentListSql($data);
        foreach($pack as $k => $v){
            $pack[$k]['number'] = $k+1+$signle_count+$more_count+$judge_count+$options_count;
            $pack[$k]['is_right']    = isset($diffculty_name[$v['is_right']]) && !empty($diffculty_name[$v['is_right']]) ? $diffculty_name[$v['is_right']] : '未答';
            $pack[$k]['type_name']    = isset($type_name[$v['type']]) && !empty($type_name[$v['type']]) ? $type_name[$v['type']] : '';
        }
        $pack_count = count($pack);
        $papers_list['填空题'] = ['count' => $pack_count , 'list' => $pack];

        //简答题
        $data['type'] = 6;
        $short = self::getStudentListSql($data);
        foreach($short as $k => $v){
            $short[$k]['number'] = $k+1+$signle_count+$more_count+$judge_count+$options_count+$pack_count;
            $short[$k]['is_right']    = isset($diffculty_name[$v['is_right']]) && !empty($diffculty_name[$v['is_right']]) ? $diffculty_name[$v['is_right']] : '未答';
            $short[$k]['type_name']    = isset($type_name[$v['type']]) && !empty($type_name[$v['type']]) ? $type_name[$v['type']] : '';
        }
        $short_count = count($short);
        $papers_list['简答题'] = ['count' => $short_count , 'list' => $short];

        //材料题
        $data['type'] = 7;
        $material = self::getStudentListSql($data);
        foreach($material as $k => $v){
            $material[$k]['number'] = $k+1+$signle_count+$more_count+$judge_count+$options_count+$pack_count+$short_count;
            $material[$k]['is_right']    = isset($diffculty_name[$v['is_right']]) && !empty($diffculty_name[$v['is_right']]) ? $diffculty_name[$v['is_right']] : '未答';
            $material[$k]['type_name']    = isset($type_name[$v['type']]) && !empty($type_name[$v['type']]) ? $type_name[$v['type']] : '';
        }
        $material_count = count($material);
        $papers_list['材料题'] = ['count' => $material_count , 'list' => $material];

        //总题数
        $public_info['count'] =$signle_count+$more_count+$judge_count+$options_count+$pack_count+$short_count+$material_count;
        //答错题数
        $public_info['error_count'] = self::where(['student_id'=>$data['student_id'],'bank_id'=>$data['bank_id'],'subject_id'=>$data['subject_id'],'papers_id'=>$data['papers_id']])->where('is_right' , 2)->count();
        //试卷解析和名称
        $public_info['exam_info'] = self::getExamInfo($data);

        return ['code' => 200 , 'msg' => '获取试卷列表成功' , 'data' => $papers_list , 'public_info' => $public_info];
    }

    private static function getStudentListSql($data){
        $list = self::leftJoin('ld_question_exam','ld_question_exam.id','=','ld_student_do_title.exam_id')
            ->leftJoin('ld_question_papers','ld_question_papers.id','=','ld_student_do_title.papers_id')
            ->leftJoin('ld_question_exam_option','ld_question_exam_option.exam_id','=','ld_question_exam.id')
            ->where(['ld_student_do_title.student_id'=>$data['student_id'],'ld_student_do_title.bank_id'=>$data['bank_id'],'ld_student_do_title.subject_id'=>$data['subject_id'],'ld_student_do_title.papers_id'=>$data['papers_id']])
            ->where(function($query) use ($data){
                //判断题库id是否为空
                if(!empty($data['type']) && $data['type'] > 0){
                    $query->where('ld_question_exam.type' , '=' , $data['type']);
                }
            })
            ->select('ld_question_papers.content','ld_question_papers.papers_name','ld_student_do_title.is_right','ld_question_exam.item_diffculty','ld_question_exam.exam_content','ld_student_do_title.answer as student_answer','ld_question_exam.answer','ld_question_exam.text_analysis','ld_question_exam.type','ld_question_exam_option.option_content')
            ->get()->toArray();
        return $list;
    }

    //获取公共部分试卷信息
    private static function getExamInfo($data){
        return self::leftJoin('ld_question_exam','ld_question_exam.id','=','ld_student_do_title.exam_id')
            ->leftJoin('ld_question_papers','ld_question_papers.id','=','ld_student_do_title.papers_id')
            ->leftJoin('ld_question_exam_option','ld_question_exam_option.exam_id','=','ld_question_exam.id')
            ->where(['ld_student_do_title.student_id'=>$data['student_id'],'ld_student_do_title.bank_id'=>$data['bank_id'],'ld_student_do_title.subject_id'=>$data['subject_id'],'ld_student_do_title.papers_id'=>$data['papers_id']])
            ->select('ld_question_papers.content','ld_question_papers.papers_name')
            ->first();
    }
	

}
