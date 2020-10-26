<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Teacher;
use App\Services\Admin\Role\RoleService;
use App\Services\Admin\Rule\RuleService;
use Illuminate\Http\Request;
use App\Models\Admin as Adminuser;
use App\Models\School;
use Illuminate\Support\Facades\Redis;
use App\Tools\CurrentAdmin;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminLog;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller {

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
    public function getAdminUserList(){

        $result = Adminuser::getAdminUserList(self::$accept_data);
        if($result['code'] == 200){
            return response()->json($result);
        }else{
            return response()->json($result);
        }
    }

    /*
     * @param  description  更改用户状态（启用、禁用）
     * @param  参数说明       body包含以下参数[
     *     id           用户id
     * ]
     * @param author    lys
     * @param ctime     2020-04-29
     */

    public function upUserForbidStatus(){
        $data =  self::$accept_data;
        $where = [];
        $updateArr = [];
        if( !isset($data['id']) || empty($data['id']) || is_int($data['id']) ){
            return response()->json(['code'=>201,'msg'=>'账号id为空或缺少或类型不合法']);
        }
        $userInfo = Adminuser::getUserOne(['id'=>$data['id']]);
        if($userInfo['code'] !=200){
            return response()->json(['code'=>$userInfo['code'],'msg'=>$userInfo['msg']]);
        }
        if($userInfo['data']['is_forbid'] == 1)  $updateArr['is_forbid'] = 0;  else  $updateArr['is_forbid'] = 1;
        $result = Adminuser::upUserStatus(['id'=>$data['id']],$updateArr);
        if($result){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   CurrentAdmin::user()['id'] ,
                'module_name'    =>  'Adminuser' ,
                'route_url'      =>  'admin/adminuser/upUserForbidStatus' ,
                'operate_method' =>  'update' ,
                'content'        =>  json_encode($data),
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return response()->json(['code'=>200,'msg'=>'Success']);
        }else{
            return response()->json(['code'=>204,'msg'=>'网络超时，请重试']);
        }

    }
    /*
     * @param  description  更改用户状态（删除）
     * @param  参数说明       body包含以下参数[
     *     id           用户id
     * ]
     * @param author    lys
     * @param ctime     2020-04-29   7.11
     */
    public function upUserDelStatus(){
        $data =  self::$accept_data;
        $where = [];
        $updateArr = [];
        if( !isset($data['id']) || empty($data['id']) || is_int($data['id']) ){
            return response()->json(['code'=>201,'msg'=>'账号id为空或缺少或类型不合法']);
        }
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : -1;
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //7.11  begin
        $zongxiaoAdminArr = Adminuser::where(['id'=>$data['id']])->first();
        $zongxiaoRoleArr = Role::getRoleInfo(['id' => $zongxiaoAdminArr['role_id']]);
        $zongxiaoSchoolArr = School::where('id',$zongxiaoAdminArr['school_id'])->first();
        if($zongxiaoRoleArr['is_super'] == 1 && $zongxiaoSchoolArr['super_id'] == $zongxiaoAdminArr['id']){
            return response()->json(['code'=>203,'msg'=>'超级管理员信息，不能删除']);
        }
         //7.11  end
        $userInfo = Adminuser::findOrFail($data['id']);
        $userInfo->is_del = 0;
        if($userInfo->save()){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   CurrentAdmin::user()['id'] ,
                'module_name'    =>  'Adminuser' ,
                'route_url'      =>  'admin/adminuser/upUserDelStatus' ,
                'operate_method' =>  'update' ,
                'content'        =>  json_encode($data),
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return response()->json(['code'=>200,'msg'=>'Success']);
        }else{
            return response()->json(['code'=>204,'msg'=>'网络超时，请重试']);
        }
    }

    /*
     * @param  description   添加后台账号
     * @param  参数说明       body包含以下参数[
     *     school_id       所属学校id
     *     username         账号
     *     realname        姓名
     *     mobile          手机号
     *     sex             性别
     *     password        密码
     *     pwd             确认密码
     *     role_id         角色id
     *     teacher_id      关联讲师id串
     * ]
     * @param author    lys
     * @param ctime     2020-04-29   5.12修改账号唯一性验证
     */

    public function doInsertAdminUser(){
        $data = self::$accept_data;
        $validator = Validator::make($data,
                [
                    'school_id' => 'required|integer',
                    'username' => 'required',
                    'realname' => 'required',
                    'mobile' => 'required|regex:/^1[3456789][0-9]{9}$/',
                    'sex' => 'required|integer',
                    'password'=>'required',
                    'pwd'=>'required',
                    'role_id' => 'required|integer',
                ],
                Adminuser::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $data['teacher_id'] = !isset($data['teacher_id'])  || empty($data['teacher_id']) || $data['teacher_id']<=0 ? 0: $data['teacher_id'];
        if($data['teacher_id']>0){
            $count  = Adminuser::where(['teacher_id'=>$data['teacher_id'],'school_id'=>$data['school_id'],'is_del'=>1])->count();
            if($count>=1){
                return response()->json(['code'=>207,'msg'=>'该教师已被其他账号绑定！']);
            }
        }

        if(strlen($data['password']) <8){
            return response()->json(['code'=>207,'msg'=>'密码长度不能小于8位']);
        }
        if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['password'])) {
            return response()->json(['code'=>207,'msg'=>'密码格式不正确，请重新输入']);
        }
        if($data['password'] != $data['pwd']){
            return response()->json(['code'=>206,'msg'=>'登录密码不一致']);
        }
        if(isset($data['pwd'])){
            unset($data['pwd']);
        }
        $count  = Adminuser::where('username',$data['username'])->count();
        if($count>0){
            return response()->json(['code'=>205,'msg'=>'用户名已存在']);
        }

        if(isset($data['/admin/adminuser/doInsertAdminUser'])){
            unset($data['/admin/adminuser/doInsertAdminUser']);
        }

        $data['school_status']=CurrentAdmin::user()['school_status'] == 1 ?1:0;
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['admin_id'] = CurrentAdmin::user()['id'];
        $result = Adminuser::insertAdminUser($data);
        if($result>0){

            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   CurrentAdmin::user()['id'] ,
                'module_name'    =>  'Adminuser' ,
                'route_url'      =>  'admin/adminuser/doInsertAdminUser' ,
                'operate_method' =>  'insert' ,
                'content'        =>  json_encode($data),
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return   response()->json(['code'=>200,'msg'=>'添加成功']);
        }else{
            return  response()->json(['code'=>203,'msg'=>'网络超时，请重试']);
        }
    }
    /*
     * @param  description   获取账号信息（编辑）
     * @param  参数说明       body包含以下参数[
     *      id => 账号id
     * ]
     * @param author    lys
     * @param ctime     2020-05-04
     */

    public function getAdminUserUpdate(){
        $data = self::$accept_data;
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : -1;
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        if( !isset($data['id']) || empty($data['id']) ){
            return response()->json(['code'=>201,'msg'=>'用户表示缺少或为空']);
        }
        $adminUserArr = Adminuser::getUserOne(['id'=>$data['id']]);
        if($adminUserArr['code'] != 200){
            return response()->json(['code'=>204,'msg'=>'用户不存在']);
        }
        $adminUserArr['data']['school_name']  = School::getSchoolOne(['id'=>$adminUserArr['data']['school_id'],'is_forbid'=>1,'is_del'=>1],['name'])['data']['name'];
        $roleAuthArr = Role::getRoleList(['school_id'=>$adminUserArr['data']['school_id'],'is_del' => 0],['id','role_name']);
        $teacherArr = [];
        $adminUserArr['data']['teacher_name'] = '';

        if(!empty($adminUserArr['data']['teacher_id'])){
            $adminUserArr['data']['teacher_name'] = Teacher::where('id',$adminUserArr['data']['teacher_id'])->where('is_del',0)->where('is_forbid',0)->select('real_name')->first();
        }
        $arr = [
            'admin_user'=> $adminUserArr['data'],
            // 'teacher' =>   $teacherArr,  //讲师组
            'role_auth' => $roleAuthArr, //权限组
        ];
        return response()->json(['code'=>200,'msg'=>'获取信息成功','data'=>$arr]);

    }

    /*
     * @param  description   账号信息（编辑）
     * @param  参数说明       body包含以下参数[
     *      id => 账号id
            school_id => 学校id
            username => 账号名称
            realname => 真实姓名
            mobile => 联系方式
            sex => 性别
            password => 登录密码
            pwd => 确认密码
            role_id => 角色id
            teacher_id => 老师id组
     * ]
     * @param author    lys
     * @param ctime     2020-05-04
     */

    public function doAdminUserUpdate(){
        $data = self::$accept_data;
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : -1;
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        $validator = Validator::make($data,
                [
                'id' => 'required|integer',
                'school_id' => 'required|integer',
                'username' => 'required',
                'realname' => 'required',
                'mobile' => 'required|regex:/^1[3456789][0-9]{9}$/',
                'sex' => 'required|integer',

                'role_id' => 'required|integer',
                ],
                Adminuser::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $data['teacher_id']= !isset($data['teacher_id']) || empty($data['teacher_id']) || $data['teacher_id']<=0 ?0 :$data['teacher_id'];
        if($data['teacher_id']>0){
            $count  = Adminuser::where(['teacher_id'=>$data['teacher_id'],'school_id'=>$data['school_id'],'is_del'=>1])->where('id','!=',$data['id'])->count();
            if($count>=1){
                return response()->json(['code'=>207,'msg'=>'该教师已被其他账号绑定！']);
            }
        }
        //7.11  begin
       if($school_status  == 1){//总校
            $zongxiaoAdminArr = Adminuser::where(['id'=>$data['id']])->first();
            $zongxiaoRoleArr = Role::getRoleInfo(['id' => $zongxiaoAdminArr['role_id']]);
            $zongxiaoSchoolArr = School::where('id',$zongxiaoAdminArr['school_id'])->first();
            if($zongxiaoRoleArr['is_super'] == 1 && $zongxiaoSchoolArr['super_id'] == $zongxiaoAdminArr['id']){
                return response()->json(['code'=>203,'msg'=>'超级管理员信息，不能编辑']);
            }
        }
        if($school_status == 0){//分校
            $zongxiaoAdminArr = Adminuser::where(['id'=>$data['id']])->first();
            $zongxiaoRoleArr = Role::getRoleInfo(['id' => $zongxiaoAdminArr['role_id']]);
            $zongxiaoSchoolArr = School::where('id',$zongxiaoAdminArr['school_id'])->first();
            if($zongxiaoRoleArr['is_super'] == 1 && $zongxiaoSchoolArr['super_id'] == $zongxiaoAdminArr['id'] && $zongxiaoSchoolArr['super_id'] != $user_id   ){
                return response()->json(['code'=>203,'msg'=>'超级管理员信息，不能编辑!!!']);
            }
        }
         //7.11  end
        if(isset($data['password']) && isset($data['pwd'])){

            if(strlen($data['password']) <8){
                return response()->json(['code'=>207,'msg'=>'密码长度不能小于8位']);
            }
            if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['password'])) {
                return response()->json(['code'=>207,'msg'=>'密码格式不正确，请重新输入']);
            }
            if(!empty($data['password'])|| !empty($data['pwd']) ){
               if($data['password'] != $data['pwd'] ){
                    return ['code'=>206,'msg'=>'两个密码不一致'];
                }else{
                    $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
            }
            unset($data['pwd']);
        }


        if(isset($data['/admin/adminuser/doAdminUserUpdate'])){
            unset($data['/admin/adminuser/doAdminUserUpdate']);
        }
        $where['username']   = $data['username'];
        $count = Adminuser::where($where)->where('id','!=',$data['id'])->count();
        if($count >=1 ){
             return response()->json(['code'=>205,'msg'=>'用户名已存在']);
        }

        $admin_id  = CurrentAdmin::user()['id'];

        DB::beginTransaction();
        try {
            // if(Adminuser::where(['school_id'=>$data['school_id'],'is_del'=>1])->count() == 1){  //判断该账号是不是分校超管 5.14
            //     if(Roleauth::where(['school_id'=>$data['school_id'],'is_del'=>1])->count() <1){
            //         $roleAuthArr = Roleauth::where(['id'=>$data['role_id']])->select('auth_id')->first()->toArray();
            //         $roleAuthArr['role_name'] = '超级管理员';
            //         $roleAuthArr['auth_desc'] = '拥有所有权限';
            //         $roleAuthArr['is_super'] = 1;
            //         $roleAuthArr['school_id'] = $data['school_id'];
            //         $roleAuthArr['admin_id']  = $admin_id;
            //         $role_id = Roleauth::insertGetId($roleAuthArr);
            //         if($role_id<=0){
            //              return   response()->json(['code'=>500,'msg'=>'角色创建失败']);
            //         }
            //         $data['role_id'] = $role_id;
            //     }
            // }
            $data['updated_at'] = date('Y-m-d H:i:s');
            $result = Adminuser::where('id','=',$data['id'])->update($data);
            if($result){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id,
                    'module_name'    =>  'Adminuser',
                    'route_url'      =>  'admin/adminuser/doAdminUserUpdate',
                    'operate_method' =>  'update' ,
                    'content'        =>  json_encode($data),
                    'ip'             =>  $_SERVER["REMOTE_ADDR"],
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return   response()->json(['code'=>200,'msg'=>'更改成功']);
            }else{
                DB::rollBack();
                return   response()->json(['code'=>203,'msg'=>'网络超时，请重试']);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return   response()->json(['code'=>500,'msg'=> $e->__toString()]);
        }

    }

    /*
     * @param  description   后台用户修改密码
     * @param  参数说明       body包含以下参数[
     *     oldpassword       旧密码
     *     newpassword       新密码
     *     repassword        确认密码
     * ]
     * @param author    dzj
     * @param ctime     2020-07-11
     */
    public function doAdminUserUpdatePwd(){
        $data =  self::$accept_data;
        //判断传过来的数组数据是否为空
        if(!$data || !is_array($data)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断旧密码是否为空
        if(!isset($data['oldpassword']) || empty($data['oldpassword'])){
            return ['code' => 201 , 'msg' => '旧密码为空'];
        }

        //判断新密码是否为空
        if(!isset($data['newpassword']) || empty($data['newpassword'])){
            return ['code' => 201 , 'msg' => '新密码为空'];
        }

        //判断确认密码是否为空
        if(!isset($data['repassword']) || empty($data['repassword'])){
            return ['code' => 201 , 'msg' => '确认密码为空'];
        }

        //判断两次输入的密码是否相等
        if($data['newpassword'] != $data['repassword']){
            return ['code' => 202 , 'msg' => '两次密码输入不一致'];
        }

        //获取后端的用户id
        $admin_id  = CurrentAdmin::user()['id'];

        //根据用户的id获取用户详情
        $admin_info = Adminuser::where('id' , $admin_id)->first();

        //判断输入的旧密码是否正确
        if(password_verify($data['oldpassword']  , $admin_info['password']) === false){
            return response()->json(['code' => 203 , 'msg' => '旧密码错误']);
        }

        //开启事务
        DB::beginTransaction();
        try {
            //更改后台用户的密码
            $rs = Adminuser::where('id' , $admin_id)->update(['password' => password_hash($data['newpassword'], PASSWORD_DEFAULT) , 'updated_at' => date('Y-m-d H:i:s')]);
            if($rs && !empty($rs)){
                //事务提交
                DB::commit();
                return response()->json(['code' => 200 , 'msg' => '更改成功']);
            } else {
                //事务回滚
                DB::rollBack();
                return response()->json(['code' => 203 , 'msg' => '更改失败']);
            }

        } catch (\Exception $e) {
            //事务回滚
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $e->__toString()]);

        }
    }
}
