<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Answers;
use App\Models\AnswersReply;
use App\Models\Student;
use App\Models\Admin;
use App\Tools\MTCloud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AnswersController extends Controller {
    protected $school;
    protected $data;
    protected $userid;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['school_dns']])->first();
        $this->userid = isset($this->data['user_info']['user_id'])?$this->data['user_info']['user_id']:0;
    }
    /*
         * @param  问答列表
         * @param  author  sxh
         * @param  ctime   2020/11/3
         * return  array
         */
    public function list(){
        $data = $this->data;
        //每页显示的条数
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //问答列表
        $list = Answers::leftJoin('ld_student','ld_student.id','=','ld_answers.uid')
            ->where(['ld_answers.is_check'=>1])
            ->where(function($query) use ($data){
                if(isset($data['name']) && !empty($data['name'])){
                    $query->where('ld_answers.title','like','%'.$data['name'].'%')->orWhere('ld_answers.content','like','%'.$data['name'].'%');
                }
            })
            ->select('ld_answers.id','ld_answers.create_at','ld_answers.title','ld_answers.content','ld_answers.is_top','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon')
            ->orderByDesc('ld_answers.is_top')
            ->orderByDesc('ld_answers.create_at')
            ->offset($offset)->limit($pagesize)
            ->get()->toArray();
        foreach($list as $k=>$v){
            $list[$k]['user_name'] = empty($v['real_name']) ? $v['nickname'] : $v['real_name'];
            $list[$k]['reply'] = AnswersReply::where(['answers_id'=>$v['id'],'status'=>1])
                    ->select('create_at','content','user_id','user_type')
                    ->get()->toArray();
            foreach($list[$k]['reply'] as $key => $value){
                if($value['user_type']==1){
                    $student = Student::where(['id'=>$value['user_id']])->select('real_name','head_icon')->first();
                    $list[$k]['reply'][$key]['user_name'] = $student['real_name'];
                    $list[$k]['reply'][$key]['head_icon'] = $student['head_icon'];
                }else{
                    $admin = Admin::where(['id'=>$value['user_id']])->select('realname')->first();
                    $list[$k]['reply'][$key]['user_name']  = $admin['realname'];
                    $list[$k]['reply'][$key]['head_icon']  = '';
                }

            }
            $list[$k]['count'] = count($list[$k]['reply']);
        }

        return ['code' => 200 , 'msg' => '获取评论列表成功' , 'data' => ['list' => $list , 'total' => count($list) , 'pagesize' => $pagesize , 'page' => $page]];
    }


}

