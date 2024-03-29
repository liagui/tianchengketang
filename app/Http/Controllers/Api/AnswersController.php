<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Answers;
use App\Models\AnswersReply;
use App\Models\Student;
use App\Models\Admin;
use App\Models\AppLog;
use App\Tools\MTCloud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AnswersController extends Controller {
    protected $school;
    protected $data;
    protected $userid;
        /*
         * @param  问答列表
         */
    public function list(Request $request){
        $pagesize = $request->input('pagesize') ?: 15;
        $page     = $request->input('page') ?: 1;
        $offset   = ($page - 1) * $pagesize;
        $data['name']  = $request->input('name');
        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'pc';
        //获取用户token值
        $token = $request->input('user_token');
        //hash中token赋值
        $token_key   = "user:regtoken:".$platform.":".$token;
        //判断token值是否合法
        $redis_token = Redis::hLen($token_key);
        if($redis_token && $redis_token > 0) {
            //解析json获取用户详情信息
            $json_info = Redis::hGetAll($token_key);
            //登录显示属于分的课程
            $schoolId = $json_info['school_id'];
        }else{
            //未登录默认观看学校37 2021-01-06 17：23 lys 改成30
            $schoolId = 30;
        }
        //每页显示的条数
        $pagesize = isset($pagesize) && $pagesize > 0 ? $pagesize : 20;
        $page     = isset($page) && $page > 0 ? $page : 1;
        $offset   = ($page - 1) * $pagesize;

        //http://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-12-29/160923550573095feafc31b81ec.png  女教务
        //http://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-12-29/160923553192365feafc4b7f6ca.png  男教务

        //问答列表
        $list = Answers::leftJoin('ld_student','ld_student.id','=','ld_answers.uid')
            ->where(['ld_answers.is_check'=>1,'ld_answers.school_id'=> $schoolId])
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
                    $list[$k]['reply'][$key]['head_icon']  = 'http://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-12-29/160923553192365feafc4b7f6ca.png';
                }

            }
            $list[$k]['count'] = count($list[$k]['reply']);
        }


        return ['code' => 200 , 'msg' => '获取问答列表成功' , 'data' => ['list' => $list , 'total' => count($list) , 'pagesize' => $pagesize , 'page' => $page]];
    }

	/*
        * @param  问答详情
        * @param  $id     问答id
        * @param  author  sxh
        * @param  ctime   2020/11/3
        * return  array
        */
    public function details(Request $request){
        $data['id']  = $request->input('id');
        $student_id = self::$accept_data['user_info']['user_id'];
        $schoolId = self::$accept_data['user_info']['school_id'];
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
                $details['reply'][$key]['head_icon']  = 'http://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-12-29/160923553192365feafc4b7f6ca.png';
            }
        }
        //添加日志操作
        AppLog::insertAppLog([
            'school_id'      =>  !isset(self::$accept_data['user_info']['school_id'])?0:self::$accept_data['user_info']['school_id'],
            'admin_id'       =>  !isset(self::$accept_data['user_info']['user_id'])?0:self::$accept_data['user_info']['user_id'],
            'module_name'    =>  'Answers' ,
            'route_url'      =>  'api/answers/details' ,
            'operate_method' =>  'select' ,
            'content'        =>  '评论详情成功'.json_encode(['data'=>['id'=>$data['id'],'schoolId'=>$schoolId]]) ,
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
    public function reply(Request $request){
        $data['id']  = $request->input('id');
        $data['content']  = $request->input('content');
        $data['user_type']  = $request->input('user_type');
        $uid = self::$accept_data['user_info']['user_id'];
        $data['school_id'] = self::$accept_data['user_info']['school_id'];
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
            $endtime = date ( "Y-m-d H:i:s" , time());
            $list = AnswersReply::where(['answers_id'=>$data['id'],'user_id'=>$uid])->whereBetween('create_at',[$time,$endtime])->select('id','create_at')->orderByDesc('create_at')->count();
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
                AppLog::insertAppLog([
                   'school_id'      =>  !isset(self::$accept_data['user_info']['school_id'])?0:self::$accept_data['user_info']['school_id'],
                   'admin_id'       =>  !isset(self::$accept_data['user_info']['user_id'])?0:self::$accept_data['user_info']['user_id'],
                    'module_name'    =>  'Answers' ,
                    'route_url'      =>  'api/answers/reply' ,
                    'operate_method' =>  'isnert' ,
                    'content'        =>  '评论回复成功'.json_encode(['data'=>$add]) ,
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
    public function addAnswers(Request $request){
        $data['content']  = $request->input('content');
        $data['title']  = $request->input('title');
        $uid = self::$accept_data['user_info']['user_id'];
        $school_id = self::$accept_data['user_info']['school_id'];
        //验证参数
        if(!isset($data['title'])||empty($data['title'])){
            return response()->json(['code' => 201, 'msg' => '标题为空']);
        }
        if(!isset($data['content'])||empty($data['content'])){
            return response()->json(['code' => 201, 'msg' => '内容为空']);
        }
        //一分钟内不得频繁提交内容
            $time = date ( "Y-m-d H:i:s" , strtotime ( "-1 minute" ));
            $endtime = date ( "Y-m-d H:i:s" , time());
            $list = Answers::where(['uid'=>$uid])->whereBetween('create_at',[$time,$endtime])->select('id','create_at')->orderByDesc('create_at')->count();
            if($list>=2){
                return response()->json(['code' => 202, 'msg' => '操作太频繁,1分钟以后再来吧']);
            }
        //开启事务
        DB::beginTransaction();
        try {
            //拼接数据
            $add = Answers::insert([
                'uid'          => $uid,
                'create_at'    => date('Y-m-d H:i:s'),
                'title'        => addslashes($data['title']),
                'content'      => addslashes($data['content']),
                'is_top'       => 0,
                'is_check'     => 2,
				'school_id'    => $school_id,
            ]);
            if($add){

                //添加日志操作
                AppLog::insertAppLog([
                    'school_id'      =>  !isset(self::$accept_data['user_info']['school_id'])?0:self::$accept_data['user_info']['school_id'],
                    'admin_id'       =>  !isset(self::$accept_data['user_info']['user_id'])?0:self::$accept_data['user_info']['user_id'],
                    'module_name'    =>  'Answers' ,
                    'route_url'      =>  'api/answers/addAnswers' ,
                    'operate_method' =>  'isnert' ,
                    'content'        =>  '发表问答成功'.json_encode(['data'=>$add]) ,
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

        /*
         * @param  我的问答列表
         */
        public function Mylist(Request $request){
            $pagesize = $request->input('pagesize') ?: 15;
            $page     = $request->input('page') ?: 1;
            $offset   = ($page - 1) * $pagesize;
            $data['type']  = $request->input('type');//1我的问答2我的回复
            $student_id = self::$accept_data['user_info']['user_id'];
            $schoolId = self::$accept_data['user_info']['school_id'];
            //每页显示的条数
            $pagesize = isset($pagesize) && $pagesize > 0 ? $pagesize : 20;
            $page     = isset($page) && $page > 0 ? $page : 1;
            $offset   = ($page - 1) * $pagesize;
            //问答列表
            if($data['type'] == 1){
                //我的提问
                $list = Answers::leftJoin('ld_student','ld_student.id','=','ld_answers.uid')
                ->where(['ld_answers.is_check'=>1,'ld_answers.school_id'=> $schoolId,'ld_answers.uid' => $student_id])
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
                return ['code' => 200 , 'msg' => '获取问答列表成功' , 'data' => ['list' => $list , 'total' => count($list) , 'pagesize' => $pagesize , 'page' => $page]];
            }else{
                //我的回答
                $list1 = Answers::leftJoin('ld_student','ld_student.id','=','ld_answers.uid')
                ->where(['ld_answers.is_check'=>1,'ld_answers.school_id'=> $schoolId])
                ->select('ld_answers.id','ld_answers.create_at','ld_answers.title','ld_answers.content','ld_answers.is_top','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon')
                ->orderByDesc('ld_answers.is_top')
                ->orderByDesc('ld_answers.create_at')
                ->offset($offset)->limit($pagesize)
                ->get()->toArray();
                foreach($list1 as $k=>$v){
                    $list1[$k]['user_name'] = empty($v['real_name']) ? $v['nickname'] : $v['real_name'];
                    $list1[$k]['reply'] = AnswersReply::where(['answers_id'=>$v['id'],'status'=>1,'user_id'=>$student_id])
                            ->select('create_at','content','user_id','user_type')
                            ->get()->toArray();
                    $list1[$k]['count'] = count($list1[$k]['reply']);
                }
                foreach($list1 as $k=>$v){
                    if(empty($list1[$k]['reply'])){
                        unset($list1[$k]);
                    }
                }
                $list1 = array_values($list1);
                AppLog::insertAppLog([
                   'school_id'      =>  !isset(self::$accept_data['user_info']['school_id'])?0:self::$accept_data['user_info']['school_id'],
                   'admin_id'       =>  !isset(self::$accept_data['user_info']['user_id'])?0:self::$accept_data['user_info']['user_id'],
                    'module_name'    =>  'Answers' ,
                    'route_url'      =>  'api/answers/Mylist' ,
                    'operate_method' =>  'select' ,
                    'content'        =>  '我的问答列表'.json_encode(['data'=>$list1]) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                return ['code' => 200 , 'msg' => '获取问答列表成功' , 'data' => ['list' => $list1 , 'total' => count($list1) , 'pagesize' => $pagesize , 'page' => $page]];
            }

        }


}
