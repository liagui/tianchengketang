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
		
		$bank_id      = isset($data['bank_id']) && $data['bank_id'] > 0 ? $data['bank_id'] : 0;               //获取题库id
        $subject_id   = isset($data['subject_id']) && $data['subject_id'] > 0 ? $data['subject_id'] : 0;      //获取科目id
        $papers_id    = isset($data['papers_id']) && $data['papers_id'] > 0 ? $data['papers_id'] : 0;         //获取试卷的id
        $type         = isset($data['type']) && $data['type'] > 0 ? $data['type'] : 0;  //1代表章节练习2代表快速做题3代表模拟真题

        //判断题库的id是否传递合法
        if(!$bank_id || $bank_id <= 0){
            return response()->json(['code' => 202 , 'msg' => '题库id不合法']);
        }
        //判断科目的id是否传递合法
        if(!$subject_id || $subject_id <= 0){
            return response()->json(['code' => 202 , 'msg' => '科目id不合法']);
        }
        //判断学员信息是否为空
        if(empty($data['student_id']) || !is_numeric($data['student_id']) || $data['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不能为空' , 'data' => ['']];
        }
        $exam_array = [];
        //题型数组
        $exam_type_arr = [1=>'单选题',2=>'多选题',3=>'判断题',4=>'不定项',5=>'填空题',6=>'简答题',7=>'材料题'];
        //试题难度数组
        $exam_diffculty= [1=>'简单',2=>'一般',3=>'困难'];
        //判断是否为章节练习
        if($type == 1){
            //新数组赋值
            $exam_array = [];
            //查询还未做完的题列表
            $exam_list = StudentDoTitle::where(['student_id'=>$data['student_id'],'bank_id'=>$bank_id,'subject_id'=>$subject_id])
                ->where('type' , 1)->where('is_right' , '>' , 0)
                ->where(function($query) use($data){
                    if(isset($papers_id) && $papers_id > 0){
                        $query->where('papers_id' , '=' , $papers_id);
                    }
                })
                ->get();
            foreach($exam_list as $k=>$v) {
                //判断是否是材料题 ，材料题获取下面的子题
                if ($v['quert_type'] == 7) {
                    //先获取材料子题
                    $cailiaoziti = Exam::where('id', $v['exam_id'])->first();
                    $cailiao = Exam::where(['id'=>$cailiaoziti['parent_id']])->first();
                    //单选题,多选题,不定项
                    if(in_array($cailiaoziti['type'] , [1,2,4,5])){
                        //根据试题的id获取选项
                        $option_info = ExamOption::where("exam_id" , $v['exam_id'])->first();
                        //选项转化
                        $option_content = json_decode($option_info['option_content'] , true);
                        //获取试题类型
                        $exam_type_name = $exam_type_arr[$cailiaoziti['type']];
                    } else if($cailiaoziti['type'] == 3){
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$cailiaoziti['type']];
                    }else if($cailiaoziti['type'] == 6){
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$cailiaoziti['type']];
                    }
                    //判断学员是否收藏此题
                    //$is_collect =  StudentCollectQuestion::where('student_id' , self::$accept_data['user_info']['user_id'])->where("papers_id" , $v['papers_id'])->where('exam_id' , $v['exam_id'])->where('type' , 1)->where('status' , 1)->count();
                    $is_collect =  StudentCollectQuestion::where("student_id" , $data['student_id'])->where("bank_id" , $bank_id)->where("subject_id" , $subject_id)->where('exam_id' , $v['exam_id'])->where('status' , 1)->count();
                    //判断学员是否标记此题
                    $is_tab     =  StudentTabQuestion::where("student_id" , $data['student_id'])->where("bank_id" , $bank_id)->where("subject_id" , $subject_id)->where('papers_id' , $v['papers_id'])->where('type' , 1)->where('exam_id' , $v['exam_id'])->where('status' , 1)->count();
                    //试题随机展示
                    $exam_array[7][] = [
                        'cailiao' => $cailiao['exam_content'],
                        'tihao' => $v['tihao'],
                        'papers_id'           =>  $v['papers_id'] ,
                        'exam_id'             =>  $v['exam_id'] ,
                        'exam_name'           =>  $cailiaoziti['exam_content'] ,
                        'exam_type_name'      =>  $exam_type_name ,
                        'exam_diffculty'      =>  isset($exam_diffculty[$cailiaoziti['item_diffculty']]) ? $exam_diffculty[$cailiaoziti['item_diffculty']] : '' ,
                        'text_analysis'       =>  $cailiaoziti['text_analysis'] ,
                        'correct_answer'      =>  trim($cailiaoziti['answer']) ,
                        'option_list'         =>  $option_content ,
                        'my_answer'           =>  !empty($v['answer']) ? $v['answer'] : '' ,
                        'is_right'            =>  $v['is_right'] ,
                        'is_collect'          =>  $is_collect ? 1 : 0 ,
                        'is_tab'              =>  $is_tab ? 1 : 0 ,
                        'type'                =>  1,
                        'real_question_type' => $cailiaoziti['type']
                    ];
                } else {
                    //根据试题的id获取试题详情
                    $exam_info = Exam::where('id', $v['exam_id'])->first();
                    if(in_array($exam_info['type'] , [1,2,4,5])){
                        //根据试题的id获取选项
                        $option_info = ExamOption::where("exam_id" , $v['exam_id'])->first();
                        //选项转化
                        $option_content = json_decode($option_info['option_content'] , true);
                        //获取试题类型
                        $exam_type_name = $exam_type_arr[$exam_info['type']];
                    } else if($exam_info['type'] == 3){
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$exam_info['type']];
                    }else if($exam_info['type'] == 6){
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$exam_info['type']];
                    }

                    //判断学员是否收藏此题
                    //$is_collect =  StudentCollectQuestion::where('student_id' , $data['user_info']['user_id'])->where("bank_id" , $bank_id)->where("subject_id" , $subject_id)->where('papers_id' , $papers_id)->where('exam_id' , $v['exam_id'])->where('type' , 1)->where('status' , 1)->count();
                    $is_collect = StudentCollectQuestion::where("student_id", $data['student_id'])->where("bank_id", $bank_id)->where("subject_id", $subject_id)->where('exam_id', $v['exam_id'])->where('status', 1)->count();

                    //试题随机展示
                    $exam_array[$exam_info['type']][] = [
                        'exam_id' => $v['exam_id'],
                        'exam_name' => $exam_info['exam_content'],
                        'exam_type_name' => $exam_type_name,
                        'exam_diffculty' => isset($exam_diffculty[$exam_info['item_diffculty']]) ? $exam_diffculty[$exam_info['item_diffculty']] : '',
                        'text_analysis' => $exam_info['text_analysis'],
                        'correct_answer' => trim($exam_info['answer']),
                        'option_list' => $option_content,
                        'my_answer' => !empty($v['answer']) ? $v['answer'] : '',
                        'is_right' => $v['is_right'],
                        'is_collect' => $is_collect ? 1 : 0,
                        'type' => 1,
                        'real_question_type' => $exam_info['type']
                    ];
                }
            }
        } else if($type == 2){  //快速做题
            //新数组赋值
            $exam_array = [];

            //查询还未做完的题列表
            $exam_list = StudentDoTitle::where("student_id" , $data['student_id'])->where("bank_id" , $bank_id)->where("subject_id" , $subject_id)->where('type' , 2)->where('is_right' , '>' , 0)
                ->where(function($query) use($data){
                    if(isset($papers_id) && $papers_id > 0){
                        $query->where('papers_id' , '=' , $papers_id);
                    }
                })->get();
            foreach($exam_list as $k=>$v) {
                if ($v['quert_type'] == 7) { //材料题
                    //先获取材料子题
                    $cailiaoziti = Exam::where('id', $v['exam_id'])->first();
                    $cailiao = Exam::where(['id'=>$cailiaoziti['parent_id']])->first();
                    //根据试题的id获取试题详情
                    if (in_array($cailiaoziti['type'], [1, 2, 4, 5])) {
                        //根据试题的id获取选项
                        $option_info = ExamOption::where("exam_id", $v['exam_id'])->first();
                        //选项转化
                        $option_content = json_decode($option_info['option_content'], true);
                        //获取试题类型
                        $exam_type_name = $exam_type_arr[$cailiaoziti['type']];
                    } else if ($cailiaoziti['type'] == 3) {
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$cailiaoziti['type']];
                    } else if ($cailiaoziti['type'] == 6) {
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$cailiaoziti['type']];
                    }
                    $is_collect = StudentCollectQuestion::where("student_id", $data['student_id'])->where("bank_id", $bank_id)->where("subject_id", $subject_id)->where('exam_id', $v['exam_id'])->where('status', 1)->count();
                    //试题随机展示
                    //试题随机展示
                    $exam_array[7][] = [
                        'cailiao' => $cailiao['exam_content'],
                        'tihao' => $v['tihao'],
                        'papers_id'           =>  $v['papers_id'] ,
                        'exam_id'             =>  $v['exam_id'] ,
                        'exam_name'           =>  $cailiaoziti['exam_content'] ,
                        'exam_type_name'      =>  $exam_type_name ,
                        'exam_diffculty'      =>  isset($exam_diffculty[$cailiaoziti['item_diffculty']]) ? $exam_diffculty[$cailiaoziti['item_diffculty']] : '' ,
                        'text_analysis'       =>  $cailiaoziti['text_analysis'] ,
                        'correct_answer'      =>  trim($cailiaoziti['answer']) ,
                        'option_list'         =>  $option_content ,
                        'my_answer'           =>  !empty($v['answer']) ? $v['answer'] : '' ,
                        'is_right'            =>  $v['is_right'] ,
                        'is_collect'          =>  $is_collect ? 1 : 0 ,
                        'type'                =>  2,
                        'real_question_type'  => $cailiaoziti['type']
                    ];
                } else {
                    //根据试题的id获取试题详情
                    $exam_info = Exam::where('id', $v['exam_id'])->first();
                    if (in_array($exam_info['type'], [1, 2, 4, 5])) {
                        //根据试题的id获取选项
                        $option_info = ExamOption::where("exam_id", $v['exam_id'])->first();
                        //选项转化
                        $option_content = json_decode($option_info['option_content'], true);
                        //获取试题类型
                        $exam_type_name = $exam_type_arr[$exam_info['type']];
                    } else if ($exam_info['type'] == 3) {
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$exam_info['type']];
                    } else if ($exam_info['type'] == 6) {
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$exam_info['type']];
                    }
                    //判断学员是否收藏此题
                    //$is_collect =  StudentCollectQuestion::where('student_id' , $data['user_info']['user_id'])->where("bank_id" , $bank_id)->where("subject_id" , $subject_id)->where('papers_id' , $papers_id)->where('exam_id' , $v['exam_id'])->where('type' , 2)->where('status' , 1)->count();
                    $is_collect = StudentCollectQuestion::where("student_id", $data['student_id'])->where("bank_id", $bank_id)->where("subject_id", $subject_id)->where('exam_id', $v['exam_id'])->where('status', 1)->count();
                    //试题随机展示
                    $exam_array[$exam_info['type']][] = [
                        'exam_id' => $v['exam_id'],
                        'exam_name' => $exam_info['exam_content'],
                        'exam_type_name' => $exam_type_name,
                        'exam_diffculty' => isset($exam_diffculty[$exam_info['item_diffculty']]) ? $exam_diffculty[$exam_info['item_diffculty']] : '',
                        'text_analysis' => $exam_info['text_analysis'],
                        'correct_answer' => trim($exam_info['answer']),
                        'option_list' => $option_content,
                        'my_answer' => !empty($v['answer']) ? $v['answer'] : '',
                        'is_right' => $v['is_right'],
                        'is_collect' => $is_collect ? 1 : 0,
                        'type' => 2,
                        'real_question_type'  => $exam_info['type']
                    ];
                }
            }
        } else if($type == 3){  //模拟真题
            //新数组赋值
            $exam_array = [];
           
            $exam_list = StudentDoTitle::where(['student_id'=>$data['student_id'],'bank_id'=>$bank_id,'subject_id'=>$subject_id,'type'=>3])
                ->where(function($query) use($data){
                    if(isset($papers_id) && $papers_id > 0){
                        $query->where('papers_id' , '=' , $papers_id);
                    }
                })->get()->toArray();
            foreach($exam_list as $k=>$v) {
                //判断是否是材料题 ，材料题获取下面的子题
                if ($v['quert_type'] == 7) {
                    //先获取材料子题
                    $cailiaoziti = Exam::where('id', $v['exam_id'])->first();
                    $cailiao = Exam::where(['id' => $cailiaoziti['parent_id']])->first();
                    //单选题,多选题,不定项
                    if (in_array($cailiaoziti['type'], [1, 2, 4, 5])) {
                        //根据试题的id获取选项
                        $option_info = ExamOption::where("exam_id", $v['exam_id'])->first();
                        //选项转化
                        $option_content = json_decode($option_info['option_content'], true);
                        //获取试题类型
                        $exam_type_name = $exam_type_arr[$cailiaoziti['type']];
                    } else if ($cailiaoziti['type'] == 3) {
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$cailiaoziti['type']];
                    } else if ($cailiaoziti['type'] == 6) {
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$cailiaoziti['type']];
                    }
                    $is_collect = StudentCollectQuestion::where("student_id", $data['student_id'])->where("bank_id", $bank_id)->where("subject_id", $subject_id)->where('exam_id', $v['exam_id'])->where('status', 1)->count();
                    //判断学员是否标记此题
                    $is_tab = StudentTabQuestion::where("student_id", $data['student_id'])->where("bank_id", $bank_id)->where("subject_id", $subject_id)->where('papers_id', $v['papers_id'])->where('type', 1)->where('exam_id', $v['exam_id'])->where('status', 1)->count();
                    //试题随机展示
                    $exam_array[7][] = [
                        'cailiao' => $cailiao['exam_content'],
                        'tihao' => $v['tihao'],
                        'papers_id' => $v['papers_id'],
                        'exam_id' => $v['exam_id'],
                        'exam_name' => $cailiaoziti['exam_content'],
                        'exam_type_name' => $exam_type_name,
                        'exam_diffculty' => isset($exam_diffculty[$cailiaoziti['item_diffculty']]) ? $exam_diffculty[$cailiaoziti['item_diffculty']] : '',
                        'text_analysis' => $cailiaoziti['text_analysis'],
                        'correct_answer' => trim($cailiaoziti['answer']),
                        'option_list' => $option_content,
                        'my_answer' => !empty($v['answer']) ? $v['answer'] : '',
                        'is_right' => $v['is_right'],
                        'is_collect' => $is_collect ? 1 : 0,
                        'is_tab' => $is_tab ? 1 : 0,
                        'type' => 3,
                        'real_question_type' => $cailiaoziti['type']
                    ];
                } else {
                    //根据试题的id获取试题详情
                    $exam_info = Exam::where('id', $v['exam_id'])->first();
                    if(in_array($exam_info['type'] , [1,2,4,5])){
                        //根据试题的id获取选项
                        $option_info = ExamOption::where("exam_id" , $v['exam_id'])->first();
                        //选项转化
                        $option_content = json_decode($option_info['option_content'] , true);
                        //获取试题类型
                        $exam_type_name = $exam_type_arr[$exam_info['type']];
                    } else if($exam_info['type'] == 3){
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$exam_info['type']];
                    }else if($exam_info['type'] == 6){
                        $option_content = [];
                        $exam_type_name = $exam_type_arr[$exam_info['type']];
                    }
                    $is_collect = StudentCollectQuestion::where("student_id", $data['student_id'])->where("bank_id", $bank_id)->where("subject_id", $subject_id)->where('exam_id', $v['exam_id'])->where('status', 1)->count();

                    //根据条件获取此学生此题是否答了
                    $info = StudentDoTitle::where("student_id", $data['student_id'])->where("bank_id", $bank_id)->where("papers_id", $papers_id)->where("subject_id", $subject_id)->where('exam_id', $v['exam_id'])->where('type', 3)->first();
                    //试题随机展示
                    $exam_array[$exam_info['type']][] = [
                        'exam_id' => $v['exam_id'],
                        'exam_name' => $exam_info['exam_content'],
                        'exam_type_name' => $exam_type_name,
                        'exam_diffculty' => isset($exam_diffculty[$exam_info['item_diffculty']]) ? $exam_diffculty[$exam_info['item_diffculty']] : '',
                        'text_analysis' => $exam_info['text_analysis'],
                        'correct_answer' => trim($exam_info['answer']),
                        'option_list' => $option_content,
                        'my_answer' => $info && !empty($info) && !empty($info['answer']) ? $info['answer'] : '',
                        'is_right' => $info && !empty($info) ? $info['is_right'] : 0,
                        'is_collect' => $is_collect ? 1 : 0,
                        'type' => 3,
                        'real_question_type' => $exam_info['type']
                    ];
                }
            }
        }
        return ['code' => 200 , 'msg' => '成功' , 'data' => $exam_array ];
		
        //判断学员信息是否为空
        /*if(empty($data['student_id']) || !is_numeric($data['student_id']) || $data['student_id'] <= 0){
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

        return ['code' => 200 , 'msg' => '获取试卷列表成功' , 'data' => $papers_list , 'public_info' => $public_info];*/
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
