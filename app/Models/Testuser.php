<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testuser extends Model {
    //指定别的表名
    public $table = 'ce_testuser';
    //时间戳设置
    public $timestamps = false;


    public static function Find($id){
        $res = self::where(['id'=>$id,'is_valid'=>1])->get();
        return $res;
    }

    /*
     * @param  descriptsion 添加用户方法
     * @param  $data[
     *    mobile   =>     手机号
     *    username =>     用户名
     *    ....
     * ]
     * @param  author  duzhijian
     * @param  ctime   2020-04-13
     * return  int
     */
    public static function doAddUser($data) {
        return self::insertGetId($data);
    }


    /*
     * @param  descriptsion 根据用户ID获取用户信息
     * @param  $user_id     用户ID
     * @param  author  duzhijian
     * @param  ctime   2020-04-13
     * return  array
     */
    public static function getUserInfoById($user_id) {
        if(self::where('id',$user_id)->first()){
          return self::where('id',$user_id)->first()->toArray();
        }else{
          return [];
        }
    }

    /*
     * @param  descriptsion 根据用户ID修改用户信息
     * @param  $user_id     用户ID
     * @param  $data        修改用户数据信息
     * @param  author  duzhijian
     * @param  ctime   2020-04-13
     * return  boolean
     */
    public static function doUpdateUserById($data , $user_id) {
        if(self::find($user_id)){
            return self::where('id',$user_id)->update($data);
        }else{
            return false;
        }
    }

    /*
     * @param  descriptsion 获取用户列表
     * @param  $pagesize    每页显示条数
     * @param  author  duzhijian
     * @param  ctime   2020-04-13
     * return  array
     */
    public static function getUserList($pagesize=15) {
        //$user_count = self::count();
        return self::paginate($pagesize);
        /*if(self::find($user_id)){
            return self::paginate(15);
        }else{
            return false;
        }*/
    }

    /*
     * @param  descriptsion 获取用户所建立的课程列表
     * @param  author  duzhijian
     * @param  ctime   2020-04-13
     * return  array
     */
    public function getUserLessionList() {
        return $this->hasMany('App\Models\Lession' , 'user_id' , 'id');
    }


    public static function getMember(){
        //$user = User::where('id' , 2)->first();
        return $user;
    }
}
