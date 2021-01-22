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
        $this->school = School::where(['dns'=>$this->data['school_dns']])->first(); //改前
        //$this->school = $this->getWebSchoolInfo($this->data['school_dns']); //改后
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
        //获取总条数
        $count = Answers::leftJoin('ld_student','ld_student.id','=','ld_answers.uid')
        ->where(['ld_answers.is_check'=>1,'ld_answers.school_id'=> $this->school['id']])
        ->where(function($query) use ($data){
            if(isset($data['name']) && !empty($data['name'])){
                $query->where('ld_answers.title','like','%'.$data['name'].'%')->orWhere('ld_answers.content','like','%'.$data['name'].'%');
            }
        })
        ->select('ld_answers.id','ld_answers.create_at','ld_answers.title','ld_answers.content','ld_answers.is_top','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon')
        ->orderByDesc('ld_answers.is_top')
        ->orderByDesc('ld_answers.create_at')
        ->count();
        //问答列表
        $list = Answers::leftJoin('ld_student','ld_student.id','=','ld_answers.uid')
            ->where(['ld_answers.is_check'=>1,'ld_answers.school_id'=> $this->school['id']])
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
                    $list[$k]['reply'][$key]['head_icon']  = 'http://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-11-20/1605873635715fb7afe356303.png';
                }

            }
            $list[$k]['count'] = count($list[$k]['reply']);
        }
        //添加日志操作
        WebLog::insertWebLog([
            'admin_id'       =>  $this->userid  ,
            'module_name'    =>  'Answers' ,
            'route_url'      =>  'web/answers/list' ,
            'operate_method' =>  'select' ,
            'content'        =>  '查询问答列表'.json_encode($list) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);


        return ['code' => 200 , 'msg' => '获取评论列表成功' , 'data' => ['list' => $list , 'total' => $count , 'pagesize' => $pagesize , 'page' => $page]];
    }

	/*
        * @param  问答详情
        * @param  $id     问答id
        * @param  author  sxh
        * @param  ctime   2020/11/3
        * return  array
        */
    public function details(){
        $data = $this->data;
        //问答idwen是否为空
        if(empty($data['id']) || !isset($data['id'])){
            return ['code' => 201 , 'msg' => 'id为空或格式错误'];
        }
        $list = Answers::where(['is_check'=>1,'id'=>$data['id']])->first();
        if(empty($list)){
            return ['code' => 202 , 'msg' => '问答信息有误'];
        }
        //问答详情
        $details = Answers::leftJoin('ld_student','ld_student.id','=','ld_answers.uid')
            ->where(['ld_answers.is_check'=>1,'ld_answers.id'=>$data['id']])
            ->where(function($query) use ($data){
                if(isset($data['name']) && !empty($data['name'])){
                    $query->where('ld_answers.title','like','%'.$data['name'].'%')->orWhere('ld_answers.content','like','%'.$data['name'].'%');
                }
            })
            ->select('ld_answers.id','ld_answers.create_at','ld_answers.title','ld_answers.content','ld_answers.is_top','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon')
            ->first()->toArray();
        $details['user_name'] = empty($details['real_name']) ? $details['nickname'] : $details['real_name'];
        $details['reply'] = AnswersReply::where(['answers_id'=>$details['id'],'status'=>1])
            ->select('create_at','content','user_id','user_type')
            ->get()->toArray();
        $details['count'] = count($details['reply']);
        foreach($details['reply'] as $key => $value){
            if($value['user_type']==1){
                $student = Student::where(['id'=>$value['user_id']])->select('real_name','head_icon')->first();
                $details['reply'][$key]['user_name'] = $student['real_name'];
                $details['reply'][$key]['head_icon'] = $student['head_icon'];
            }else{
                $admin = Admin::where(['id'=>$value['user_id']])->select('realname')->first();
                $details['reply'][$key]['user_name']  = $admin['realname'];
                $details['reply'][$key]['head_icon']  = '';
            }
        }
        //添加日志操作
        WebLog::insertWebLog([
            'admin_id'       =>  $this->userid  ,
            'module_name'    =>  'Answers' ,
            'route_url'      =>  'web/answers/details' ,
            'operate_method' =>  'select' ,
            'content'        =>  '查询问答详情'.json_encode($details) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return ['code' => 200 , 'msg' => '获取评论详情成功' , 'data' => $details];
    }

	/*
         * @param  reply 问答回复
         * @param  $user_token     用户token
         * @param  $school_dns     网校域名
         * @param  $id             问答id
         * @param  $content        回复内容
         * @param  $user_type      回复用户类型   1前台
         * @param  author  sxh
         * @param  ctime   2020/11/3
         * return  array
         */
    public function reply(){
        $data = $this->data;
        $uid = $this->userid;
        //判断分类id
        if(empty($data['id']) || !isset($data['id'])){
            return ['code' => 201 , 'msg' => '问答id为空'];
        }
        //判断内容
        if(empty($data['content']) || !isset($data['content'])){
            return ['code' => 201 , 'msg' => '回复内容为空'];
        }
        //判断回复的用户类型
        if(empty($data['user_type']) || !isset($data['user_type'])){
            return ['code' => 201 , 'msg' => '回复的用户类型为空'];
        }
		//一分钟内不得频繁提交内容
            $time = date ( "Y-m-d H:i:s" , strtotime ( "-1 minute" ));
            $date = date ( "Y-m-d H:i:s" , time());
            $list = AnswersReply::where(['answers_id'=>$data['id'],'user_id'=>$this->userid])->whereBetween('create_at',[$time,$date])->select('id','create_at')->orderByDesc('create_at')->count();
            if($list>=2){
                return response()->json(['code' => 202, 'msg' => '操作太频繁,1分钟以后再来吧']);
            }
        DB::beginTransaction();
        try{
            //插入数据
            $add = AnswersReply::insertGetId([
                'answers_id' => $data['id'],
                'create_at'  => date('Y-m-d H:i:s'),
                'content'    => addslashes($data['content']),
                'status'     => 0,
                'user_id'    => $uid,
                'user_type'  =>$data['user_type'],
            ]);
            if($add){
                //添加日志操作
                WebLog::insertWebLog([
                    'admin_id'       =>  $this->userid  ,
                    'module_name'    =>  'Answers' ,
                    'route_url'      =>  'web/answers/reply' ,
                    'operate_method' =>  'insert' ,
                    'content'        =>  '问答回复'.json_encode($add) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return ['code' => 200 , 'msg' => '回复成功'];
            }else{
                DB::rollback();
                return ['code' => 202 , 'msg' => '回复失败'];
            }
        } catch (\Exception $ex) {
            DB::rollback();
            return ['code' => 202 , 'msg' => $ex->getMessage()];
        }
    }

	/*
     * @param  addAnswers    提问
     * @param  参数说明
     *      user_token   用户token
     *      school_dns   网校域名
     *      title        标题
     *      content      内容
     * @param  author          sxh
     * @param  ctime           2020-11-4
     * return  array
     */
    public function addAnswers(){
        //验证参数
        if(!isset($this->data['title'])||empty($this->data['title'])){
            return response()->json(['code' => 201, 'msg' => '标题为空']);
        }
        if(!isset($this->data['content'])||empty($this->data['content'])){
            return response()->json(['code' => 201, 'msg' => '内容为空']);
        }
        //一分钟内不得频繁提交内容
            $time = date ( "Y-m-d H:i:s" , strtotime ( "-1 minute" ));
            $data = date ( "Y-m-d H:i:s" , time());
            $list = Answers::where(['uid'=>$this->userid])->whereBetween('create_at',[$time,$data])->select('id','create_at')->orderByDesc('create_at')->count();
            if($list>=2){
                return response()->json(['code' => 202, 'msg' => '操作太频繁,1分钟以后再来吧']);
            }
        //开启事务
        DB::beginTransaction();
        try {
            //拼接数据
            $add = Answers::insert([
                'uid'          => $this->userid,
                'create_at'    => date('Y-m-d H:i:s'),
                'title'        => addslashes($this->data['title']),
                'content'      => addslashes($this->data['content']),
                'is_top'       => 0,
                'is_check'     => 2,
				'school_id'    => $this->school['id'],
            ]);
            if($add){
                //添加日志操作
                WebLog::insertWebLog([
                    'admin_id'       =>  $this->userid  ,
                    'module_name'    =>  'Answers' ,
                    'route_url'      =>  'web/answers/addAnswers' ,
                    'operate_method' =>  'insert' ,
                    'content'        =>  '问答提问'.json_encode($add) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return response()->json(['code' => 200, 'msg' => '发表问答成功,等待后台的审核']);
            }else{
                DB::rollBack();
                return response()->json(['code' => 203, 'msg' => '发表问答失败']);
            }
        } catch (\Exception $ex) {
            //事务回滚
            DB::rollBack();
            return ['code' => 204, 'msg' => $ex->getMessage()];
        }
    }


}
