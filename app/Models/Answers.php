<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
class Answers extends Model {
    //指定别的表名
    public $table = 'ld_answers';
    //时间戳设置
    public $timestamps = false;

    /*
         * @param  getAnswersList 获取问答列表
         * @param  $is_top        1置顶
         * @param  $status        1显示 2不显示
         * @param  $page
         * @param  $pagesize
         * @param  author  sxh
         * @param  ctime   2020/10/29
         * return  array
         */
    public static function getAnswersList($data){
        //每页显示的条数
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取列表
        $list = self::leftJoin('ld_student','ld_student.id','=','ld_answers.uid')
            ->where(['ld_answers.is_check'=>1])
            ->where(function($query) use ($data){
                //网校是否为空
                if(isset($data['is_top']) && $data['is_top'] > 0){
                    $query->where('ld_answers.is_top' , '=' , 1);
                }
                //判断评论状态
                if(isset($data['status']) && (in_array($data['status'],[1,2]))){
                    $query->where('ld_answers.status' , '=' , $data['status']);
                }
            })
            ->select('ld_answers.id','ld_answers.create_at','ld_answers.content','ld_answers.status','ld_answers.is_check','ld_answers.is_top','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon')
            ->orderByDesc('ld_answers.is_top')
            ->orderByDesc('ld_answers.create_at')
            ->offset($offset)->limit($pagesize)
            ->get()->toArray();
        foreach($list as $k=>$v){
           $list[$k]['user_name'] = empty($v['real_name']) ? $v['nickname'] : $v['real_name'];
        }
        return ['code' => 200 , 'msg' => '获取问答列表成功' , 'data' => ['list' => $list , 'total' => count($list) , 'pagesize' => $pagesize , 'page' => $page]];
    }

    /*
         * @param 修改评论状态
         * @param  $id 评论id
         * @param  author  sxh
         * @param  ctime   2020/10/29
         * return  array
         */
    public static function editCommentStatus($data){
        if(empty($data['id']) || !isset($data['id'])){
            return ['code' => 201 , 'msg' => '参数为空或格式错误'];
        }
        if(!isset($data['status'])){
            return ['code' => 201 , 'msg' => '状态信息为空或错误'];
        }
        $comment_info = self::where(['id'=>$data['id']])->first();
        //var_dump($comment_info['status']);die();
        if((!$comment_info) || ($comment_info['status']==2)){
            return ['code' => 201 , 'msg' => '数据信息有误或处于删除状态'];
        }
        $update = self::where(['id'=>$data['id']])->update(['status'=>$data['status'],'update_at'=>date('Y-m-d H:i:s')]);
        if($update){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Comment' ,
                'route_url'      =>  'admin/Article/editCommentToId' ,
                'operate_method' =>  'update' ,
                'content'        =>  '操作'.json_encode($data) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }
	
	/*
         * @param 置顶
         * @param  $id 问答id
         * @param  author  sxh
         * @param  ctime   2020/10/30
         * return  array
         */
    public static function editAnswersTopStatus($data){
        if(empty($data['id']) || !isset($data['id'])){
            return ['code' => 201 , 'msg' => '参数为空或格式错误'];
        }
        $is_top = !empty($data['is_top']) ? 1 : 0;
        $answers_info = self::where(['id'=>$data['id']])->first();
        if((!$answers_info) || ($answers_info['is_check']==2)){
            return ['code' => 201 , 'msg' => '数据信息有误或处于未审核状态'];
        }
        $update = self::where(['id'=>$data['id']])->update(['is_top'=>$is_top,'update_at'=>date('Y-m-d H:i:s')]);
        if($update){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Comment' ,
                'route_url'      =>  'admin/Article/editAnswersTopStatus' ,
                'operate_method' =>  'update' ,
                'content'        =>  '操作'.json_encode($data) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }

    /*
         * @param  getAnswersIsCheckList 获取问答审核列表
         * @param  $is_check   1通过 2未通过 3全部
         * @param  $page
         * @param  $pagesize
         * @param  author  sxh
         * @param  ctime   2020/11/3
         * return  array
         */
    public static function getAnswersIsCheckList($data){
        //每页显示的条数
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取列表
        $list = self::leftJoin('ld_student','ld_student.id','=','ld_answers.uid')
            ->where(function($query) use ($data){
                //审核状态
                if(isset($data['is_check']) && (in_array($data['is_check'],[1,2]))){
                    $query->where('ld_answers.is_check' , '=' , $data['is_check']);
                }
            })
            ->select('ld_answers.id','ld_answers.create_at','ld_answers.content','ld_answers.status','ld_answers.is_check','ld_answers.is_top','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon')
            ->orderByDesc('ld_answers.create_at')
            ->offset($offset)->limit($pagesize)
            ->get()->toArray();
        foreach($list as $k=>$v){
            $list[$k]['user_name'] = empty($v['real_name']) ? $v['nickname'] : $v['real_name'];
        }
        return ['code' => 200 , 'msg' => '获取问答审核列表成功' , 'data' => ['list' => $list , 'total' => count($list) , 'pagesize' => $pagesize , 'page' => $page]];
    }

    /*
         * @param 修改问答审核状态
         * @param  $id 问答id
         * @param  $is_check   问答审核状态  1审核通过 2未审核
         * @param  author  sxh
         * @param  ctime   2020/10/30
         * return  array
         */
    public static function editAnswersIsCheckStatus($data){
        if(empty($data['id']) || !isset($data['id'])){
            return ['code' => 201 , 'msg' => '参数为空或格式错误'];
        }
        if(isset($data['is_check']) && (!in_array($data['is_check'],[1,2]))){
            return ['code' => 201 , 'msg' => '状态信息为空或错误'];
        }
        $answers_info = self::where(['id'=>$data['id']])->first();
        if(!$answers_info){
            return ['code' => 201 , 'msg' => '数据信息有误'];
        }
        $update = self::where(['id'=>$data['id']])->update(['is_check'=>$data['is_check'],'update_at'=>date('Y-m-d H:i:s')]);
        if($update){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Answers' ,
                'route_url'      =>  'admin/Article/editAnswersIsCheckStatus' ,
                'operate_method' =>  'update' ,
                'content'        =>  '操作问答审核状态'.json_encode($data) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }

    /*
         * @param 问答一键审核状态
         * @param  author  sxh
         * @param  ctime   2020/11/2
         * return  array
         */
    public static function editAllAnswersIsCheckStatus(){
        //查询所有未审核状态的数据
        $answers_info = self::where(['is_check'=>2])->select('id')->get()->toArray();
        if(!$answers_info){
            return ['code' => 201 , 'msg' => '没有可修改的状态'];
        }
        //批量更改数据
        foreach($answers_info as $k => $v){
            $update = self::where(['id'=>$v['id']])->update(['is_check'=>1,'update_at'=>date('Y-m-d H:i:s')]);
        }
        if($update){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Answers' ,
                'route_url'      =>  'admin/Article/editAllAnswersIsCheckStatus' ,
                'operate_method' =>  'update' ,
                'content'        =>  '操作问答一键审核状态'.json_encode($answers_info) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }



}