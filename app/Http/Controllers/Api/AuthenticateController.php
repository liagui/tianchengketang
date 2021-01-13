<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\School;
use Validator;
use Log;
use JWTAuth;
use Illuminate\Support\Facades\DB;
use Lysice\Sms\Facade\SmsFacade;

class AuthenticateController extends Controller {
    /*
     * @param  description   注册方法
     * @param  参数说明       body包含以下参数[
     *     phone             手机号(必传)
     *     password          密码(必传)
     *     device            设备唯一标识(非必传)
     *     verifycode        验证码(必传)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-22
     * return string
     */
    public function doUserRegister() {
        return response()->json(['code' => 202 , 'msg' => '禁止注册']);
        try {
            $body = self::$accept_data;
            //判断传过来的数组数据是否为空
            if(!$body || !is_array($body)){
                return response()->json(['code' => 202 , 'msg' => '传递数据不合法']);
            }

            //判断手机号是否为空
            if(!isset($body['phone']) || empty($body['phone'])){
                return response()->json(['code' => 201 , 'msg' => '请输入手机号']);
            } else if(!preg_match('#^13[\d]{9}$|^14[\d]{9}$|^15[\d]{9}$|^17[\d]{9}$|^18[\d]{9}|^16[\d]{9}|^19[\d]{9}$#', $body['phone'])) {
                return response()->json(['code' => 202 , 'msg' => '手机号不合法']);
            }

            //判断密码是否为空
            if(!isset($body['password']) || empty($body['password'])){
                return response()->json(['code' => 201 , 'msg' => '请输入密码']);
            }

            //判断验证码是否为空
            if(!isset($body['verifycode']) || empty($body['verifycode'])){
                return response()->json(['code' => 201 , 'msg' => '请输入验证码']);
            }

            //验证码合法验证
            $verify_code = Redis::get('user:register:'.$body['phone']);
            if(!$verify_code || empty($verify_code)){
                return ['code' => 201 , 'msg' => '请先获取验证码'];
            }

            //判断验证码是否一致
            if($verify_code != $body['verifycode']){
                return ['code' => 202 , 'msg' => '验证码错误'];
            }

            //key赋值
            $key = 'user:isregister:'.$body['phone'];

            //判断此学员是否被请求过一次(防止重复请求,且数据信息存在)
            if(Redis::get($key)){
                return response()->json(['code' => 205 , 'msg' => '此手机号已被注册']);
            } else {
                //判断用户手机号是否注册过
                $student_count = User::where("phone" , $body['phone'])->count();
                if($student_count > 0){
                    //存储学员的手机号值并且保存60s
                    Redis::setex($key , 60 , $body['phone']);
                    return response()->json(['code' => 205 , 'msg' => '此手机号已被注册']);
                }
            }

            //生成随机唯一的token
            $token = self::setAppLoginToken($body['phone']);

            //正常用户昵称
            $nickname = randstr(8);

            //获取请求的平台端
            $platform = verifyPlat() ? verifyPlat() : 'pc';

            //开启事务
            DB::beginTransaction();

            //封装成数组
            $user_data = [
                'phone'     =>    $body['phone'] ,
                'password'  =>    password_hash($body['password'] , PASSWORD_DEFAULT) ,
                'nickname'  =>    $nickname ,
                'device'    =>    isset($body['device']) && !empty($body['device']) ? $body['device'] : '' ,
                'reg_source'=>    1 ,
                'school_id' =>    1 ,
                'create_at' =>    date('Y-m-d H:i:s'),
                'login_at'  =>    date('Y-m-d H:i:s')
            ];

            //将数据插入到表中
            $user_id = User::insertGetId($user_data);
            if($user_id && $user_id > 0){
                $user_info = ['user_id' => $user_id , 'user_token' => $token , 'user_type' => 1  , 'head_icon' => '' , 'real_name' => '' , 'phone' => $body['phone'] , 'nickname' => $nickname , 'sign' => '' , 'papers_type' => '' , 'papers_name' => '' , 'papers_num' => '' , 'balance' => 0 , 'school_id' => 1];
                //redis存储信息
                Redis::hMset("user:regtoken:".$platform.":".$token , $user_info);
                Redis::hMset("user:regtoken:".$platform.":".$body['phone'] , $user_info);

                //事务提交
                DB::commit();
                return response()->json(['code' => 200 , 'msg' => '注册成功' , 'data' => ['user_info' => $user_info]]);
            } else {
                //事务回滚
                DB::rollBack();
                return response()->json(['code' => 203 , 'msg' => '注册失败']);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   登录方法
     * @param  参数说明       body包含以下参数[
     *     phone             手机号
     *     password          密码
     * ]
     * @param author    dzj
     * @param ctime     2020-05-23
     * return string
     */
    public function doUserLogin() {
        $body = self::$accept_data;
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return response()->json(['code' => 202 , 'msg' => '传递数据不合法']);
        }

        //判断手机号是否为空
        if(!isset($body['phone']) || empty($body['phone'])){
            return response()->json(['code' => 201 , 'msg' => '请输入手机号']);
        } else if(!preg_match('#^13[\d]{9}$|^14[\d]{9}$|^15[\d]{9}$|^17[\d]{9}$|^18[\d]{9}|^16[\d]{9}|^19[\d]{9}$#', $body['phone'])) {
            return response()->json(['code' => 202 , 'msg' => '手机号不合法']);
        }

        //判断密码是否为空
        if(!isset($body['password']) || empty($body['password'])){
            return response()->json(['code' => 201 , 'msg' => '请输入密码']);
        }

        //判断用户是否多网校注册
        $is_more_school = User::where('phone' , $body['phone'])->count();
        if($is_more_school && $is_more_school >= 2){
            //判断此手机号是否设置了默认的网校
            $is_exists_school = User::where('phone' , $body['phone'])->where('is_set_school' , 1)->first();
            if($is_exists_school && !empty($is_exists_school)){
                //是否显示网校弹框
                $is_show_shcool = 0;
                $school_array   = [];
                $is_set_school  = 1;
            } else {
                //是否显示网校弹框
                $is_show_shcool = 1;
                $school_array   = [];
                $user_school_list   = User::where('phone' , $body['phone'])->get()->toArray();
                if($user_school_list && !empty($user_school_list)){
                    foreach($user_school_list as $k=>$v){
                        //通过网校的id获取网校的信息
                        $school_info = School::where('id' , $v['school_id'])->first();
                        $school_array[] = [
                            'school_id'      =>  $school_info['id'] ,
                            'school_name'    =>  $school_info['name'] ,
                            'default_school' =>  $v['is_set_school']
                        ];
                    }
                }
                $is_set_school  = 0;
            }
        } else {
            //是否显示网校弹框
            $is_show_shcool = 0;
            $school_array   = [];
            $is_set_school  = 0;
        }

        //key赋值
        $key = 'user:login:'.$body['phone'];

        //判断此学员是否被请求过一次(防止重复请求,且数据信息存在)
        if(Redis::get($key)){
            return response()->json(['code' => 204 , 'msg' => '此手机号未注册']);
        } else {
            //判断用户手机号是否注册过
            $student_count = User::where("phone" , $body['phone'])->count();
            if($student_count <= 0){
                //存储学员的手机号值并且保存60s
                Redis::setex($key , 60 , $body['phone']);
                return response()->json(['code' => 204 , 'msg' => '此手机号未注册']);
            }
        }

        //生成随机唯一的token
        $token = self::setAppLoginToken($body['phone']);

        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'app';

        //hash中的token的key值
        $token_key   = "user:regtoken:".$platform.":".$token;
        $token_phone = "user:regtoken:".$platform.":".$body['phone'];



        //开启事务
        DB::beginTransaction();
        try {
            //根据手机号和密码进行登录验证
            $user_login = User::where("phone",$body['phone'])->where('is_set_school' , $is_set_school)->first();
            if(!$user_login || empty($user_login)){
                return response()->json(['code' => 204 , 'msg' => '此手机号未注册']);
            }
            //验证密码是否合法
            if(password_verify($body['password']  , $user_login->password) != false){
                if($user_login['app_login_err_number'] >= 5){
                     //判断时间是否过了60s
                    if(time()-$user_login['app_end_login_err_time']<=10){
                        return response()->json(['code' => 203 , 'msg' => '你的密码已锁定，请5分钟后再试！']);
                    }else{
                         //走正常登录  并修改登录时间和登录次数
                        $userRes=User::where("phone",$body['phone'])->where('school_id' , $user_login->school_id)->update(['app_login_err_number'=>1,'app_end_login_err_time'=>time(),'update_at'=>date('Y-m-d H:i:s')]);
                        if($userRes){
                            DB::commit();
                            return response()->json(['code' => 203 , 'msg' => '密码错误，您还有4次机会。']);
                        }
                    }
                }else{
                    $error_number = $user_login['app_login_err_number']+1;
                     //登录  并修改次数和登录时间
                    $userRes = User::where("phone",$body['phone'])->where('school_id' , $user_login->school_id)->update(['app_login_err_number'=>$error_number,'app_end_login_err_time'=>time(),'update_at'=>date('Y-m-d H:i:s')]);
                    if($userRes){
                        DB::commit();
                    }
                    $err_number = 5-$error_number;
                    if($err_number <=0){
                        return response()->json(['code' => 203 , 'msg' => '你的密码已锁定，请5分钟后再试。']);
                    }
                    return response()->json(['code' => 203 , 'msg' => '密码错误，您还有'.$err_number.'次机会。']);
                }
            }else{
                if(time()-$user_login['app_end_login_err_time']<=10){
                    return response()->json(['code' => 203 , 'msg' => '你的密码已锁定，请5分钟后再试0']);
                }
            }

            //判断此手机号是否被禁用了
            if($user_login->is_forbid == 2){
                return response()->json(['code' => 207 , 'msg' => '账户已禁用']);
            }
            $userRs = User::where("phone" , $body['phone'])->update([ "update_at" => date('Y-m-d H:i:s') , "login_at" => date('Y-m-d H:i:s'),"app_login_err_number"=>0,"app_end_login_err_time"=>0]);
            if($userRs){
                DB::commit();
            }
            //用户详细信息赋值
            $user_info = [
                'user_id'    => $user_login->id ,
                'user_token' => $token ,
                'user_type'  => 1 ,
                'head_icon'  => $user_login->head_icon ,
                'real_name'  => $user_login->real_name ,
                'phone'      => $user_login->phone ,
                'nickname'   => $user_login->nickname ,
                'sign'       => $user_login->sign ,
                'papers_type'=> $user_login->papers_type ,
                'papers_name'=> $user_login->papers_type > 0 ? parent::getPapersNameByType($user_login->papers_type) : '',
                'papers_num' => $user_login->papers_num ,
                'balance'    => $user_login->balance > 0 ? floatval($user_login->balance) : 0 ,
                'school_id'  => $user_login->school_id ,
                'is_show_shcool' => $is_show_shcool ,
                'school_array'   => $school_array
            ];

            //更新token
            $rs = User::where("phone" , $body['phone'])->update(["password" => password_hash($body['password'] , PASSWORD_DEFAULT) , "update_at" => date('Y-m-d H:i:s') , "login_at" => date('Y-m-d H:i:s')]);
            if($rs && !empty($rs)){
                //事务提交
                DB::commit();

                //判断redis中值是否存在
                $hash_len = Redis::hLen($token_phone);
                if($hash_len && $hash_len > 0){
                    //获取手机号下面对应的token信息
                    $key_info = Redis::hMGet($token_phone , ['user_token']);
                    Redis::del("user:regtoken:".$platform.":".$key_info[0]);
                    Redis::del($token_phone);
                }

                //redis存储信息
                Redis::hMset($token_key , $user_info);
                Redis::hMset($token_phone , $user_info);
            } else {
                //事务回滚
                DB::rollBack();
            }
            return response()->json(['code' => 200 , 'msg' => '登录成功' , 'data' => ['user_info' => $user_info]]);
        } catch (\Exception $ex) {
            DB::rollBack();

            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   游客登录方法
     * @param  参数说明       body包含以下参数[
     *     device            设备唯一标识(必传)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-23
     * return string
     */
    public function doVisitorLogin() {
        $body = self::$accept_data;
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return response()->json(['code' => 202 , 'msg' => '传递数据不合法']);
        }

        //判断设备唯一标识是否为空
        if(!isset($body['device']) || empty($body['device'])){
            return response()->json(['code' => 201 , 'msg' => '设备唯一标识为空']);
        }

        //生成随机唯一的token
        $token = self::setAppLoginToken($body['device']);

        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'pc';

        //hash中的token的key值
        $token_key    = "user:regtoken:".$platform.":".$token;
        $token_device = "user:regtoken:".$platform.":".$body['device'];

        //通过设备唯一标识判断是否注册过
        $student_info = User::where("device" , $body['device'])->first();

        //开启事务
        DB::beginTransaction();
        try {

            //判断是否是存在用户信息
            if($student_info && !empty($student_info)){
                //判断此手机号是否被禁用了
                if($student_info->is_forbid == 2){
                    DB::rollBack();
                    return response()->json(['code' => 207 , 'msg' => '账户已禁用']);
                }

                //用户详细信息赋值
                $user_info = [
                    'user_id'    => $student_info->id ,
                    'user_token' => $token ,
                    'user_type'  => 2 ,
                    'head_icon'  => $student_info->head_icon ,
                    'real_name'  => $student_info->real_name ,
                    'phone'      => $student_info->phone ,
                    'nickname'   => $student_info->nickname ,
                    'sign'       => $student_info->sign ,
                    'papers_type'=> $student_info->papers_type ,
                    'papers_name'=> $student_info->papers_type > 0 ? parent::getPapersNameByType($student_info->papers_type) : '',
                    'papers_num' => $student_info->papers_num ,
                    'balance'    => $student_info->balance > 0 ? floatval($student_info->balance) : 0 ,
                    'school_id'  => $student_info->school_id ,
                    'device'     => isset($body['device']) && !empty($body['device']) ? $body['device'] : '' ,
                    'is_show_shcool' => 0 ,
                    'school_array'   => []
                ];

                //更新token
                $rs = User::where("device" , $body['device'])->update(["update_at" => date('Y-m-d H:i:s') , "login_at" => date('Y-m-d H:i:s')]);
                if($rs && !empty($rs)){
                    //事务提交
                    DB::commit();

                    //判断redis中值是否存在
                    $hash_len = Redis::hLen($token_device);
                    if($hash_len && $hash_len > 0){
                        //获取设备下面对应的token信息
                        $key_info = Redis::hMGet($token_device , ['user_token']);
                        Redis::del("user:regtoken:".$platform.":".$key_info[0]);
                        Redis::del($token_device);
                    }

                    //redis存储信息
                    Redis::hMset($token_key , $user_info);
                    Redis::hMset($token_device , $user_info);
                    return response()->json(['code' => 200 , 'msg' => '登录成功' , 'data' => ['user_info' => $user_info]]);
                } else {
                    //事务回滚
                    DB::rollBack();
                    return response()->json(['code' => 203 , 'msg' => '登录失败']);
                }
            } else {
                //游客昵称
                $nickname = '游客'.randstr(8);

                //封装成数组
                $user_data = [
                    'device'    =>    isset($body['device']) && !empty($body['device']) ? $body['device'] : '' ,
                    'reg_source'=>    1 ,
                    'school_id' =>    30 ,
                    'nickname'  =>    $nickname ,
                    'user_type' =>    2 ,
                    'create_at' =>    date('Y-m-d H:i:s')
                ];

                $user_id = User::insertGetId($user_data);
                if($user_id && $user_id > 0){
                    //用户详细信息赋值
                    $user_info = [
                        'user_id'    => $user_id ,
                        'user_token' => $token ,
                        'user_type'  => 2 ,
                        'head_icon'  => '' ,
                        'real_name'  => '' ,
                        'phone'      => '' ,
                        'nickname'   => $nickname ,
                        'sign'       => '' ,
                        'papers_type'=> '' ,
                        'papers_name'=> '' ,
                        'papers_num' => '' ,
                        'balance'    => 0  ,
                        'school_id'  => 30  ,
                        'device'     => isset($body['device']) && !empty($body['device']) ? $body['device'] : '' ,
                        'is_show_shcool' => 0 ,
                        'school_array'   => []
                    ];

                    //事务提交
                    DB::commit();

                    //redis存储信息
                    Redis::hMset($token_key , $user_info);
                    Redis::hMset($token_device , $user_info);
                    return response()->json(['code' => 200 , 'msg' => '登录成功' , 'data' => ['user_info' => $user_info]]);
                } else {
                    //事务回滚
                    DB::rollBack();
                    return response()->json(['code' => 203 , 'msg' => '登录失败']);
                }
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   找回密码方法
     * @param  参数说明       body包含以下参数[
     *     phone             手机号
     *     password          新密码
     *     verifycode        验证码
     * ]
     * @param author    dzj
     * @param ctime     2020-05-23
     * return string
     */
    public function doUserForgetPassword() {
        $body = self::$accept_data;
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return response()->json(['code' => 202 , 'msg' => '传递数据不合法']);
        }

        //判断手机号是否为空
        if(!isset($body['phone']) || empty($body['phone'])){
            return response()->json(['code' => 201 , 'msg' => '请输入手机号']);
        } else if(!preg_match('#^13[\d]{9}$|^14[\d]{9}$|^15[\d]{9}$|^17[\d]{9}$|^18[\d]{9}|^16[\d]{9}|^19[\d]{9}$#', $body['phone'])) {
            return response()->json(['code' => 202 , 'msg' => '手机号不合法']);
        }

        //判断密码是否为空
        if(!isset($body['password']) || empty($body['password'])){
            return response()->json(['code' => 201 , 'msg' => '请输入新密码']);
        }
        //正则表达式8-16位字符（英文/数字/符号）至少两种或下划线组合
        if(strlen($body['password']) <8){
            return response()->json(['code'=>207,'msg'=>'密码长度小于8位']);
        }
        if(!preg_match('/^(\w*(?=\w*\d)(?=\w*[A-Za-z])\w*){8,16}$/', $body['password'])) {
            return response()->json(['code'=>207,'msg'=>'密码格式不正确，请重新输入']);
        }
        //判断验证码是否为空
        if(!isset($body['verifycode']) || empty($body['verifycode'])){
            return response()->json(['code' => 201 , 'msg' => '请输入验证码']);
        }

        //验证码合法验证
        $verify_code = Redis::get('user:forget:'.$body['phone']);
        if(!$verify_code || empty($verify_code)){
            return ['code' => 201 , 'msg' => '请先获取验证码'];
        }

        //判断验证码是否一致
        if($verify_code != $body['verifycode']){
            return ['code' => 202 , 'msg' => '验证码错误'];
        }

        //key赋值
        $key = 'user:login:'.$body['phone'];

        //判断此学员是否被请求过一次(防止重复请求,且数据信息存在)
        if(Redis::get($key)){
            return response()->json(['code' => 204 , 'msg' => '此手机号未注册']);
        } else {
            //判断用户手机号是否注册过
            $student_info = User::where("phone" , $body['phone'])->first();
            if(!$student_info || empty($student_info)){
                //存储学员的手机号值并且保存60s
                Redis::setex($key , 60 , $body['phone']);
                return response()->json(['code' => 204 , 'msg' => '此手机号未注册']);
            }
        }

        //判断此手机号是否被禁用了
        if($student_info->is_forbid == 2){
            return response()->json(['code' => 207 , 'msg' => '账户已禁用']);
        }

        //开启事务
        DB::beginTransaction();
        try {

            //将数据插入到表中
            $update_user_password = User::where("phone" , $body['phone'])->update(['password' => password_hash($body['password'] , PASSWORD_DEFAULT) , 'update_at' => date('Y-m-d H:i:s')]);
            if($update_user_password && !empty($update_user_password)){
                //事务提交
                DB::commit();
                return response()->json(['code' => 200 , 'msg' => '更新成功']);
            } else {
                //事务回滚
                DB::rollBack();
                return response()->json(['code' => 203 , 'msg' => '更新失败']);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   获取验证码方法
     * @param  参数说明       body包含以下参数[
     *     verify_type     验证码类型(1代表注册,2代表找回密码)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-22
     * return string
     */
    public function doSendSms(){
        $body = self::$accept_data;
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return response()->json(['code' => 202 , 'msg' => '传递数据不合法']);
        }

        //判断验证码类型是否合法
        if(!isset($body['verify_type']) || !in_array($body['verify_type'] , [1,2])){
            return response()->json(['code' => 202 , 'msg' => '验证码类型不合法']);
        }

        //判断手机号是否为空
        if(!isset($body['phone']) || empty($body['phone'])){
            return response()->json(['code' => 201 , 'msg' => '请输入手机号']);
        } else if(!preg_match('#^13[\d]{9}$|^14[\d]{9}$|^15[\d]{9}$|^17[\d]{9}$|^18[\d]{9}|^16[\d]{9}|^19[\d]{9}$#', $body['phone'])) {
            return response()->json(['code' => 202 , 'msg' => '手机号不合法']);
        }

        //判断是注册还是忘记密码
        if($body['verify_type'] == 1){
            //设置key值
            $key = 'user:register:'.$body['phone'];
            //保存时间(5分钟)
            $time= 300;
            //短信模板code码
            $template_code = 'SMS_180053367';

            //判断用户手机号是否注册过
            $student_info = User::where("phone" , $body['phone'])->first();
            if($student_info && !empty($student_info)){
                return response()->json(['code' => 205 , 'msg' => '此手机号已被注册']);
            }
        } else {
            //设置key值
            $key = 'user:forget:'.$body['phone'];
            //保存时间(30分钟)
            $time= 1800;
            //短信模板code码
            $template_code = 'SMS_190727799';

            //判断用户手机号是否注册过
            $student_info = User::where("phone" , $body['phone'])->first();
            if(!$student_info || empty($student_info)){
                return response()->json(['code' => 204 , 'msg' => '此手机号未注册']);
            }

            //判断此手机号是否被禁用了
            if($student_info->is_forbid == 2){
                return response()->json(['code' => 207 , 'msg' => '账户已禁用']);
            }
        }

        //判断验证码是否过期
        $code = Redis::get($key);
        if(!$code || empty($code)){
            //随机生成验证码数字,默认为6位数字
            $code = rand(100000,999999);
        }

        //发送验证信息流
        $data = ['mobile' => $body['phone'] , 'TemplateParam' => ['code' => $code] , 'template_code' => $template_code];
        $send_data = SmsFacade::send($data);

        //判断发送验证码是否成功
        if($send_data->Code == 'OK'){
            //存储学员的id值
            Redis::setex($key , $time , $code);
            return response()->json(['code' => 200 , 'msg' => '发送短信成功']);
        } else {
            return response()->json(['code' => 203 , 'msg' => '发送短信失败' , 'data' => $send_data->Message]);
        }
    }

    //删除redis指定key的所有键值信息
    public function doDelRedisKeys(){
        //获取所有的指定的前缀的信息列表
        $key_list = Redis::keys('user:regtoken:*');
        Redis::del($key_list);
        return response()->json(['code' => 200 , 'msg' => '删除成功']);
    }

    //删除redis指定key的所有键值信息
    public function getRedisKeys(){
        //获取所有的指定的前缀的信息列表
        $key_list =  Redis::keys('user:regtoken:*');
        return response()->json(['code' => 200 , 'msg' => '获取成功' , 'data'=> $key_list]);
    }
}
