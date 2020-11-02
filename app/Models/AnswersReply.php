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
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        $add = self::insertGetId([
            'answers_id' => $data['answers_id'],
            'create_at' => date('Y-m-d H:i:s'),
            'content' => addslashes($data['content']),
            'status' => 1,
            'user_id' => $user_id,
        ]);
        if($add){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'Answers' ,
                'route_url'      =>  'admin/Article/addAnswersReply' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增回复数据'.json_encode($data) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '添加成功'];
        }else{
            return ['code' => 202 , 'msg' => '添加失败'];
        }
    }






}
