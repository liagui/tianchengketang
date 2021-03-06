<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Admin extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use Authenticatable, Authorizable;

    public $table = 'ld_admin';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username', 'password', 'email', 'mobile', 'realname', 'sex', 'admin_id','teacher_id','school_status','school_id','is_forbid','is_del','role_id'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'created_at',
        'updated_at'
    ];


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return ['role' => 'admin'];
    }


    public static function message()
    {
        return [
            'id.required'  => json_encode(['code'=>'201','msg'=>'账号id不能为空']),
            'id.integer'   => json_encode(['code'=>'202','msg'=>'账号id不合法']),
            'school_id.required'  =>  json_encode(['code'=>'201','msg'=>'学校id不能为空']),
            'school_id.integer'   => json_encode(['code'=>'202','msg'=>'学校id类型不合法']),
            'username.required' => json_encode(['code'=>'201','msg'=>'账号不能为空']),
            'username.unique'  => json_encode(['code'=>'205','msg'=>'账号已存在']),
            'realname.required' => json_encode(['code'=>'201','msg'=>'真实姓名不能为空']),
            'mobile.required'  =>  json_encode(['code'=>'201','msg'=>'手机号不能为空']),
            'mobile.regex' => json_encode(['code'=>'202','msg'=>'手机号不合法']) ,
            'sex.integer'  => json_encode(['code'=>'202','msg'=>'性别标识不合法']),
            'sex.required' => json_encode(['code'=>'201','msg'=>'性别标识不能为空']),
            'password.required'  => json_encode(['code'=>'201','msg'=>'密码不能为空']),
            'pwd.required' => json_encode(['code'=>'201','msg'=>'确认密码不能为空']),
            'role_id.required' => json_encode(['code'=>'201','msg'=>'角色id不能为空']),
            'role_id.integer' => json_encode(['code'=>'202','msg'=>'角色id不合法']),
        ];

    }

    /*
         * @param  descriptsion 后台账号信息
         * @param  $user_id     用户id
         * @param  author  苏振文
         * @param  ctime   2020/4/25 15:44
         * return  array
         */
    // public static function GetUserOne($id){
    //     $return = self::where(['id'=>$id])->first();
    //     return $return;
    // }


    /*
         * @param  descriptsion 后台账号信息
         * @param  $where[
         *    id   =>       用户id
         *    ....
         * ]
         * @param  author  苏振文
         * @param  ctime   2020/4/25 15:44
         * return  array
         */
    public static function getUserOne($where){

        $userInfo = self::where($where)->first();
        if($userInfo){
            return ['code'=>200,'msg'=>'获取后台用户信息成功','data'=>$userInfo->toArray()];
        }else{
            return ['code'=>204,'msg'=>'后台用户信息不存在'];
        }
    }

    /*
     * @param  descriptsion 更新状态方法
     * @param  $where[
     *    id   =>       用户id
     *    ....
     * ]
     * @param  $update[
     *    is_del   =>      删除状态码
     *    is_forbid =>     启禁状态码
     * ]
     * @param  author  lys
     * @param  ctime   2020-04-13
     * return  int
     */
    public static function upUserStatus($where,$update){

        $result = self::where($where)->update($update);
        return $result;
    }
    /*
     * @param  descriptsion 添加用户方法
     * @param  $insertArr[
     *    phone   =>     手机号
     *    account =>     登录账号
     *    ....
     * ]
     * @param  author  duzhijian
     * @param  ctime   2020-04-13
     * return  int
     */
    public static function insertAdminUser($insertArr){
        return  self::insertGetId($insertArr);

    }

    /*
     * @param  description   获取用户列表
     * @param  参数说明       body包含以下参数[
     *     search       搜索条件 （非必填项）
     *     page         当前页码 （不是必填项）
     *     limit        每页显示条件 （不是必填项）
     *     school_id    学校id  （非必填项）
     * ]
     * @param author    lys
     * @param ctime     2020-04-29
     */
    public static function getAdminUserList($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }
        $adminUserInfo  = CurrentAdmin::user();  //当前登录用户所有信息
        $school_id = $adminUserInfo['school_id'];//学校
        if($adminUserInfo['school_status'] == 1){ //总校
            //判断学校id是否合法
            $school_id = !isset($body['school_id']) && empty($body['school_id']) ?$school_id:$body['school_id'];
        }
        //判断搜索条件是否合法、
        $body['search'] = !isset($body['search']) && empty($body['search']) ?'':$body['search'];

        if(!empty($body['school_id'])){
            $school_id = $body['school_id'];//根据搜索条件查询
        }

        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 15;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        if($adminUserInfo['school_status'] == 1){       //
            $SchoolInfo = School::where('is_del',1)->get(); //获取分校列表
        }else{
            $SchoolInfo = [];
        }
        $admin_count = self::where(['is_del'=>1,'school_id'=>$school_id])
                        ->where(function($query) use ($body){
                            if(!empty($body['search'])){
                                $query->where('realname','like','%'.$body['search'].'%')
                                    ->orWhere('username','like','%'.$body['search'].'%')
                                    ->orWhere('mobile','like','%'.$body['search'].'%');
                            }
                        })->count();
        $sum_page = ceil($admin_count/$pagesize);
        $adminUserData = [];
        if($admin_count >0){
            $adminUserData =  self::leftjoin('ld_role','ld_role.id', '=', 'ld_admin.role_id')
                ->where(['ld_admin.is_del'=>1,'ld_admin.school_id'=>$school_id])
                ->where(function($query) use ($body,$school_id){
                    if(!empty($body['search'])){
                        $query->where('ld_admin.realname','like','%'.$body['search'].'%')
                        ->orWhere('ld_admin.username','like','%'.$body['search'].'%')
                        ->orWhere('ld_admin.mobile','like','%'.$body['search'].'%');
                    }
                })->select('ld_admin.id as adminid','ld_admin.username','ld_admin.realname','ld_admin.sex','ld_admin.mobile','ld_role.role_name','ld_role.auth_desc','ld_admin.is_forbid')->offset($offset)->limit($pagesize)->get();
            foreach($adminUserData as $key=>&$v){
                $v['mobile'] = substr_replace($v['mobile'],'****',3,4);
            }
        }
        $arr['code']= 200;
        $arr['msg'] = 'Success';

        $arr['data'] = ['admin_list' => $adminUserData ,'school_list'=>$SchoolInfo, 'total' => $admin_count , 'pagesize' =>$pagesize , 'page' => $page,'search'=>$body['search'],'sum_page'=>$sum_page,'school_id'=>$school_id];
        return $arr;
    }

}
