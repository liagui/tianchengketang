<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Services\Admin\Role\RoleService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\School;
use App\Models\Teacher;
use Log;
use JWTAuth;
use Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Controllers\Admin\AdminUserController as AdminUser;

class AuthenticateController extends Controller {


    public function postLogin(Request $request) {

        $validator = Validator::make($request->all(), [
            'username'=> 'required',
            'password'=> 'required'
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }

        $credentials = $request->only('username', 'password');

        return $this->login($credentials, $request->input('school_status', 0));
    }

    public function register(Request $request) {
        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            return response($validator->errors()->first(), 202);
        }

        $user = $this->create($request->all())->toArray();

        return $this->login($user, $request->input('school_status', 0));
    }

    /**
     * 身份认证
     *
     * @param  array  $data
     * @return User
     */
    protected function login(array $data, $schoolStatus = 0)
    {
        try {
            $adminUserData = Admin::where(['username'=>$data['username']])->first();
            if (strlen($token = JWTAuth::attempt($data))<=0) {
                //先查数据是否存在
                if( !is_null($adminUserData) && !empty($adminUserData)){
                    if($adminUserData['login_err_number'] >= 5){
                         //判断时间是否过了60s
                         if(time()-$adminUserData['end_login_err_time']<=10){
                            return $this->response('你的密码已锁定，请5分钟后再试!', 401);
                         }else{
                             //走正常登录  并修改登录时间和登录次数
                             Admin::where("username",$data['username'])->update(['login_err_number'=>1,'end_login_err_time'=>time(),'updated_at'=>date('Y-m-d H:i:s')]);
                         }
                    }else{
                       //  //判断时间是否过了60s
                       // if(time()-$adminUserData['end_login_err_time']>=10){
                       //     $userRes = Admin::where("username",$data['username'])->update(['login_err_number'=>1,'end_login_err_time'=>time(),'updated_at'=>date('Y-m-d H:i:s')]);
                       //      if($userRes){

                       //          return $this->response('密码错误，您还有4次机会!!!', 401);
                       //      }
                       //  }else{
                            $error_number = $adminUserData['login_err_number']+1;
                             //登录  并修改次数和登录时间
                            Admin::where("username",$data['username'])->update(['login_err_number'=>$error_number,'end_login_err_time'=>time(),'updated_at'=>date('Y-m-d H:i:s')]);
                            $err_number = 5-$error_number;
                            if($err_number <=0){
                                return $this->response('你的密码已锁定，请5分钟后再试。', 401);
                            }
                            return $this->response('密码错误，您还有'.$err_number.'次机会！', 401);
                        //  }
                    }
                }else{
                    return $this->response('账号密码错误', 401);
                }
            }
        } catch (JWTException $e) {
            Log::error('创建token失败' . $e->getMessage());
            return $this->response('创建token失败', 500);
        }
        //验证严格的总控和中控
        $user = JWTAuth::user();
        if(!isset($user['school_status'])){
            if($adminUserData['login_err_number']==5){
                return $this->response('密码错误，您还有4次机会!', 401);
            }else{
                return $this->response('用户不合法', 401);
            }
        }else{
            if ($schoolStatus != $user->school_status) {
                if($adminUserData['login_err_number']==5){
                   return $this->response('密码错误，您还有4次机会!!', 401);
                }else{
                   return $this->response('用户不合法', 401);
                }
            }
        }
        $schoolinfo = School::where('id',$user['school_id'])->select('name','end_time')->first();
        $user['school_name'] = $schoolinfo->name;
        $user['token'] = $token;
        $this->setTokenToRedis($user->id, $token);
        //2020/11/05 is_forbid 由0禁用,1正常, 增加为0禁用,1正常,2禁用前台,3禁用后台zhaolaoxian
        if(in_array($user['is_forbid'],[0,3]) || ($user['is_del'] != 1) ){
              return response()->json(['code'=>403,'msg'=>'此用户已被禁用或删除，请联系管理员']);
        }
        //服务到期时间
        if($schoolinfo->end_time && time() > strtotime($schoolinfo->end_time)){
            return response()->json(['code'=>403,'msg'=>'网校服务时间已到期']);
        }
        if(time()-$adminUserData['end_login_err_time']<=10){
            return $this->response('你的密码已锁定，请5分钟后再试。。', 401);
        }
        Admin::where("username",$data['username'])->update(['login_err_number'=>0,'end_login_err_time'=>0,'updated_at'=>date('Y-m-d H:i:s')]);
        $AdminUser = new AdminUser();

        $user['auth'] = [];     //5.14 该账户没有权限返回空  begin
        $teacher = Teacher::where(['id'=>$user['teacher_id'],'is_del'=>0,'is_forbid'=>0])->first();
        $user['teacher_type'] =0;
        if ($teacher['type'] == 1) {
            $user['teacher_type'] =1;
        }
        if ($teacher['type'] == 2) {
            $user['teacher_type'] =2;
        }
        if ($user['role_id']>0) {

            $adminUser = RoleService::getRoleRouterList($user['role_id'], $user['school_status']);

            if($adminUser['code']!=200){
                return response()->json(['code'=>$adminUser['code'],'msg'=>$adminUser['msg']]);
            }

            $user['auth'] = $adminUser['data'];
        }               //5.14 end
        return $this->response($user);
    }
    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data) {
        return Validator::make($data, [
            'username' => 'required|max:255|unique:ld_admin',
            'mobile' => 'min:11',
            'password' => 'required|min:6',
            'email' => 'email',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data) {
        return Admin::create([
            'username' => $data['username'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),//bcrypt($data['password']),
            'email' => isset($data['email']) ?: '',
            'admin_id' => isset($data['admin_id']) ?: 0,
            'realname' => isset($data['realname']) ?: '',
            'sex' => isset($data['sex']) ?: 0,
            'mobile' => isset($data['mobile']) ?: '',
            'email' => isset($data['email']) ?: '',
        ]);
    }

    public function setTokenToRedis($userId, $token) {
        try {
            Redis::set('longde:admin:' . env('APP_ENV') . ':user:token', $userId, $token);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * 获取登录信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLoginUserInfo(Request $request)
    {

        $user = JWTAuth::user();
        $user['school_name'] = School::where('id',$user['school_id'])->select('name')->value('name');
        //2020/11/24 is_forbid 由0禁用,1正常, 增加为0禁用,1正常,2禁用前台,3禁用后台zhaolaoxian
        if(in_array($user['is_forbid'],[0,3]) || ($user['is_del'] != 1) ){
            return response()->json(['code'=>403,'msg'=>'此用户已被禁用或删除，请联系管理员']);
        }

        $user['auth'] = [];     //5.14 该账户没有权限返回空  begin
        $teacher = Teacher::where(['id'=>$user['teacher_id'],'is_del'=>0,'is_forbid'=>0])->first();
        $user['teacher_type'] =0;
        if ($teacher['type'] == 1) {
            $user['teacher_type'] =1;
        }
        if ($teacher['type'] == 2) {
            $user['teacher_type'] =2;
        }
        if ($user['role_id']>0) {

            $adminUser = RoleService::getRoleRouterList($user['role_id'], $user['school_status']);

            if($adminUser['code']!=200){
                return response()->json(['code'=>$adminUser['code'],'msg'=>$adminUser['msg']]);
            }

            $user['auth'] = $adminUser['data'];
        }               //5.14 end
        return $this->response($user);
    }

}
