<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
class AnswersReply extends Model {
    //指定别的表名
    public $table = 'ld_answers_reply';
    //时间戳设置
    public $timestamps = false;

    /*
         * @param  addAnswersReply 添加问答回复
         * @param  $answers_id     问答id
         * @param  $content        回复内容
         * @param  author  sxh
         * @param  ctime   2020/10/29
         * return  array
         */
    public static function addAnswersReply($data){

        //判断分类id
        if(empty($data['answers_id']) || !isset($data['answers_id'])){
            return ['code' => 201 , 'msg' => '问答id为空'];
        }
        //判断标题
        if(empty($data['content']) || !isset($data['content'])){
            return ['code' => 201 , 'msg' => '回复内容为空'];
        }
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        $add = self::insertGetId([
            'answers_id' => $data['answers_id'],
            'create_at' => date('Y-m-d H:i:s'),
            'content' => addslashes($data['content']),
            'status' => 1,
            'user_id' => $user_id,
			'user_type' => 2,
        ]);
        if($add){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'AnswersReply' ,
                'route_url'      =>  'admin/Article/addAnswersReply' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增回复数据'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '添加成功'];
        }else{
            return ['code' => 202 , 'msg' => '添加失败'];
        }
    }

	/*
         * @param 修改回复状态
         * @param  $id      回复id
         * @param  $status  问答id  0禁用 1启用 2删
         * @param  author  sxh
         * @param  ctime   2020/10/30
         * return  array
         */
    public static function editAnswersReplyStatus($data){
        //判断id是否合法
        if (!isset($data['id']) || empty($data['id'])) {
            return ['code' => 202, 'msg' => 'id不合法'];
        }
        if(!isset($data['status']) || (!in_array($data['status'],[0,1,2]))){
            return ['code' => 201 , 'msg' => '状态信息为空或错误'];
        }
        $answers_info = self::where(['id'=>$data['id']])->first();
        if(!$answers_info){
            return ['code' => 201 , 'msg' => '数据信息有误'];
        }
        $data['status'] = empty($data['status']) ? 0 : $data['status'];
        $update = self::where(['id'=>$data['id']])->update(['status'=>$data['status'],'update_at'=>date('Y-m-d H:i:s')]);
        if($update){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'AnswersReply' ,
                'route_url'      =>  'admin/Article/editAnswersReplyStatus' ,
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






}
