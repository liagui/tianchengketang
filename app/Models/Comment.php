<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
class Comment extends Model {
    //指定别的表名
    public $table = 'ld_comment';
    //时间戳设置
    public $timestamps = false;

    /*
         * @param  getCommentList 获取評論列表
         * @param  $school_id     网校id
         * @param  $status        0禁用 1启用
         * @param  $name          教师/课程
         * @param  author  sxh
         * @param  ctime   2020/10/29
         * return  array
         */
    public static function getCommentList($data){
        //每页显示的条数
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取列表
        $list = self::leftJoin('ld_student','ld_student.id','=','ld_comment.uid')
            ->leftJoin('ld_school','ld_school.id','=','ld_comment.school_id')
            ->where(function($query) use ($data){
                //网校是否为空
                if(isset($data['school_id']) && $data['school_id'] > 0){
                    $query->where('ld_comment.school_id' , '=' , $data['school_id']);
                }
                //判断评论状态
                if(isset($data['status']) && (in_array($data['status'],[0,1]))){
                    $query->where('ld_comment.status' , '=' , $data['status']);
                }
                //模糊搜索
                if(isset($data['search_name']) && !empty($data['search_name'])){
                    $query->where('ld_comment.course_name','like','%'.$data['search_name'].'%');
                }
            })
            ->select('ld_comment.id','ld_comment.create_at','ld_comment.content','ld_comment.course_name','ld_comment.teacher_name','ld_comment.status','ld_comment.anonymity','ld_student.real_name','ld_student.nickname','ld_student.head_icon as user_icon','ld_school.name as school_name')
            ->orderByDesc('ld_comment.create_at')->offset($offset)->limit($pagesize)
            ->get()->toArray();
        foreach($list as $k=>$v){
            if($v['anonymity']==1){
                $list[$k]['user_name'] = empty($v['real_name']) ? $v['nickname'] : $v['real_name'];
            }else{
                $list[$k]['user_name'] = '匿名';
            }
        }
        return ['code' => 200 , 'msg' => '获取评论列表成功' , 'data' => ['list' => $list , 'total' => count($list) , 'pagesize' => $pagesize , 'page' => $page]];
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
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Comment' ,
                'route_url'      =>  'admin/Article/editCommentToId' ,
                'operate_method' =>  'update' ,
                'content'        =>  '操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }
	
	/*
         * @param 评论一键审核状态
         * @param comment_id    评论id，数组，格式 [1,2,3]
         * @param  author  sxh
         * @param  ctime   2020/11/2
         * return  array
         */
    public static function editAllCommentIsStatus($data){
        if(empty($data) || !isset($data)){
            return ['code' => 201 , 'msg' => '传参数组为空'];
        }
        //判断id是否合法
        $comment_id = json_decode($data['comment_id']);
        if (empty($comment_id) || !isset($comment_id)) {
            return ['code' => 202, 'msg' => '请选择要操作的数据'];
        }
        //批量修改评论状态
        if(is_array($comment_id) && count($comment_id) > 0){
            $comment = self::whereIn('id', $comment_id)->update(['status'=>1,'update_at'=>date('Y-m-d H:i:s')]);
        }

        if($comment){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Comment' ,
                'route_url'      =>  'admin/Comment/editAllCommentIsStatus' ,
                'operate_method' =>  'update' ,
                'content'        =>  '操作评论一键审核状态'.json_encode($data) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 201 , 'msg' => '没有可修改的数据'];
        }
    }



}
