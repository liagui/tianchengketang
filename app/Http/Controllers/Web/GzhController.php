<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Coures;
use App\Models\Couresteacher;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\PaySet;
use App\Models\School;
use App\Models\Teacher;
use App\Tools\WxpayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\Student;

use App\Models\WebLog;

class GzhController extends Controller {
    private $appid = 'wx191328b7484877c8';
    private $appsecret  = '427f022509534aab2d3073bef1a2c265';

    public function doUserGzhLogin(){
        $echoStr = $_GET["echostr"];//从微信用户端获取一个随机字符赋予变量echostr
        //valid signature , option访问地61行的checkSignature签名验证方法，如果签名一致，输出变量echostr，完整验证配置接口的操作
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }
    //签名验证程序    ，checkSignature被18行调用。官方加密、校验流程：将token，timestamp，nonce这三个参数进行字典序排序，然后将这三个参数字符串拼接成一个字符串惊喜shal加密，开发者获得加密后的字符串可以与signature对比，表示该请求来源于微信。
    private function checkSignature()
    {
        $signature = $_GET["signature"];//从用户端获取签名赋予变量signature
        $timestamp = $_GET["timestamp"];//从用户端获取时间戳赋予变量timestamp
        $nonce = $_GET["nonce"];  //从用户端获取随机数赋予变量nonce
        $token = 'lystoken';//将常量token赋予变量token
        $tmpArr = array($token, $timestamp, $nonce);//简历数组变量tmpArr
        sort($tmpArr, SORT_STRING);//新建排序
        $tmpStr = implode( $tmpArr );//字典排序
        $tmpStr = sha1( $tmpStr );//shal加密
        //tmpStr与signature值相同，返回真，否则返回假
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }

    //登录
    public function login(){
        $school_dns = $_GET['school_dns'];
        $url = urlencode('https://api.longde999.cn/web/official/wxcode?school_dns='.$school_dns);
        $schoolData = School::select('id')->where(['dns'=>$school_dns,'is_del'=>1,'is_forbid'=>1])->first();
        if(!isset($schoolData['id'])&& $schoolData['id']<=0){
            echo '404';exit;
        }
        $payset = PaySet::select('id','wx_app_id','wx_appsecret')->where('school_id',$schoolData['id'])->first();
        if(!isset($payset['id'])&& $payset['id']<=0){
            echo '404';exit;
        }
        header("Location: https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$payset['wx_app_id']."&redirect_uri=".$url."&response_type=code&scope=snsapi_userinfo&state=hehongdu#wechat_redirect");
    }
    //获取用户code信息
    public function wxcode() {
        $school_dns = $_GET['school_dns'];
        $schoolData = School::select('id')->where(['dns'=>$school_dns,'is_del'=>1,'is_forbid'=>1])->first();
        if(!isset($schoolData['id'])&& $schoolData['id']<=0){
            echo '404';exit;
        }
        $payset = PaySet::select('id','wx_app_id','wx_appsecret')->where('school_id',$schoolData['id'])->first();
        if(!isset($payset['id'])&& $payset['id']<=0){
            echo '404';exit;
        }
        $code = $_GET['code'];
//        file_put_contents('wxcodeget.txt', '时间:'.date('Y-m-d H:i:s').print_r($_GET,true),FILE_APPEND);
        if($code){
            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$payset['wx_app_id']."&secret=".$payset['wx_appsecret']."&code=".$code."&grant_type=authorization_code";
            $rst = $this -> http_get($url);
            $data = json_decode($rst,TRUE);
            if($data){
                $uget = "https://api.weixin.qq.com/sns/userinfo?access_token=".$data['access_token']."&openid=".$data['openid']."&lang=zh_CN";
                $uinfo = $this -> http_get($uget);
                $uinfo = json_decode($uinfo,TRUE);
                $userinfo = Student::where(['open_id'=>$uinfo['openid']])->first();
                if(empty($userinfo)){
                    //跳转登录页
                    redis::set($uinfo['openid'],json_encode($uinfo));
                    header('location:http://'.$school_dns.'/#/phone/login?open_id='.$uinfo['openid']);
                }else{
                    if(empty($userinfo['phone'])){
                        //跳转登录页
                        redis::set($uinfo['openid'],$uinfo);
                        header('location:http://'.$school_dns.'/#/phone/login?open_id='.$uinfo['openid']);
                    }else{
                        if($userinfo['is_forbid'] == 2){
                            echo '404';exit;
                        }
                        //直接登录
                        header('location:http://'.$school_dns.'/#/home?open_id='.$uinfo['openid']);
                    }
                }
            }
        }
    }
    public function getUserInfo(){

        $body = self::$accept_data;
        if(!isset($body['open_id']) || empty($body['open_id'])){
            return ['code' => 202 , 'msg' => '此用户未关注公众号'];
        }
        //分校域名
        if(!isset($body['school_dns']) || empty($body['school_dns'])){
            return response()->json(['code' => 201 , 'msg' => '分校域名为空']);
        }
        $userInfo = Student::where(['open_id'=>$body['open_id']])->first();
        //根据分校的域名获取所属分校的id
        $school_id = School::where('dns' , $body['school_dns'])->value('id');
        //判断此分校是否存在
        if(!$school_id || $school_id <= 0){
            return response()->json(['code' => 203 , 'msg' => '此分校不存在']);
        }
        //判断此用户对应得分校是否是一样得
        if($userInfo['school_id'] != $school_id){
            return response()->json(['code' => 200 , 'msg' => '请登录','data'=>['status'=>0]]);
        }
        //生成随机唯一的token
        $token = self::setAppLoginToken($userInfo['phone']);
        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'pc';

        //hash中的token的key值
        $token_key   = "user:regtoken:".$platform.":".$token;
        $token_phone = "user:regtoken:".$platform.":".$userInfo['phone'].":".$school_id;

            //用户详细信息赋值
            $user_info = [
                'user_id'    => $userInfo->id ,
                'user_token' => $token ,
                'user_type'  => 1 ,
                'head_icon'  => $userInfo->head_icon ,
                'real_name'  => $userInfo->real_name ,
                'phone'      => $userInfo->phone ,
                'nickname'   => $userInfo->nickname ,
                'sign'       => $userInfo->sign ,
                'papers_type'=> $userInfo->papers_type ,
                'papers_name'=> $userInfo->papers_type > 0 ? parent::getPapersNameByType($userInfo->papers_type) : '',
                'papers_num' => $userInfo->papers_num ,
                'balance'    => $userInfo->balance > 0 ? floatval($userInfo->balance) : 0 ,
                'school_id'  => $userInfo->school_id
            ];
        DB::beginTransaction();
        try {
            //更新token
            $rs = Student::where('school_id' , $school_id)->where("open_id",$body['open_id'])->where("phone" , $userInfo['phone'])->update(['token'=>$token, "update_at" => date('Y-m-d H:i:s') , "login_at" => date('Y-m-d H:i:s')]);
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

            //判断是否设置了记住我
            if(isset($body['is_remember']) && $body['is_remember'] == 1){
                return response()->json(['code' => 200 , 'msg' => '登录成功' , 'data' => ['user_info' => $user_info,'status'=>1]])->withCookie(new SCookie('user_phone', $body['phone'] , time()+3600*24*30)) ->withCookie(new SCookie('user_password', password_hash($body['password'] , PASSWORD_DEFAULT) , time()+3600*24*30));
            } else {
                return response()->json(['code' => 200 , 'msg' => '登录成功' , 'data' => ['user_info' => $user_info,'status'=>1]]);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

//openid  school_dns  phone  pwd
    public function doWxLogin(){
        $body = self::$accept_data;
        $open_id = $body['open_id'];
        if(!isset($open_id) || empty($open_id)){
            return ['code' => 202 , 'msg' => '此用户未关注公众号'];
        }
        //分校域名
        if(!isset($body['school_dns']) || empty($body['school_dns'])){
            return response()->json(['code' => 201 , 'msg' => '分校域名为空']);
        }
        //判断手机号是否为空
        if(!isset($body['phone']) || empty($body['phone'])){
            return response()->json(['code' => 201 , 'msg' => '请输入手机号']);
        } else if(!preg_match('#^13[\d]{9}$|^14[\d]{9}$|^15[\d]{9}$|^17[\d]{9}$|^18[\d]{9}|^16[\d]{9}|^19[\d]{9}$#', $body['phone'])) {
            return response()->json(['code' => 202 , 'msg' => '手机号不合法']);
        }
        //分校域名
        //根据分校的域名获取所属分校的id
        $school_id = School::where('dns' , $body['school_dns'])->value('id');
        //判断此分校是否存在
        if(!$school_id || $school_id <= 0){
            return response()->json(['code' => 203 , 'msg' => '此分校不存在']);
        }

        DB::beginTransaction();
        try {
            //根据手机号和密码进行登录验证
            $user_login = Student::where('school_id' , $school_id)->where("phone",$body['phone'])->orderBy('id','desc')->first();
            if(!$user_login || empty($user_login)){
                return response()->json(['code' => 204 , 'msg' => '此手机号未注册']);
            }
            // if(!empty($user_login['open_id'])){
            //     return ['code' => 204 , 'msg' => '此手机号已绑定其他公众号'];
            // }
            //验证密码是否合法
            if(password_verify($body['password']  , $user_login->password) === false){
                return response()->json(['code' => 203 , 'msg' => '密码错误']);
            }
            //判断此手机号是否被禁用了
            if($user_login->is_forbid == 2){
                return response()->json(['code' => 207 , 'msg' => '账户已禁用']);
            }
            //判断此手机号是否被禁用了
            if($user_login->is_forbid == 3){
                return response()->json(['code' => 207 , 'msg' => '账户已删除']);
            }

            //判断此用户对应得分校是否是一样得
            if($user_login->school_id != $school_id){
                return response()->json(['code' => 203 , 'msg' => '该网校无此用户']);
            }
            //生成随机唯一的token
            $token = self::setAppLoginToken($body['phone']);
            if(empty(redis::get($open_id))){
                $nickname = '';
                $head_icon = '';
            }else{
                $redisArr = json_decode(redis::get($open_id),1);
                $nickname = $redisArr['nickname'];
                $head_icon = $redisArr['headimgurl'];
            }

            $platform = verifyPlat() ? verifyPlat() : 'pc';
            //hash中的token的key值
            $token_key   = "user:regtoken:".$platform.":".$token;
            $token_phone = "user:regtoken:".$platform.":".$body['phone'].":".$school_id;

        //用户详细信息赋值
            $user_info = [
                'user_id'    => $user_login->id ,
                'user_token' => $token ,
                'user_type'  => 1 ,
                'head_icon'  => $head_icon ,
                'real_name'  => $user_login->real_name ,
                'phone'      => $user_login->phone ,
                'nickname'   => $nickname ,
                'open_id'   =>  $open_id,
                'sign'       => $user_login->sign ,
                'papers_type'=> $user_login->papers_type ,
                'papers_name'=> $user_login->papers_type > 0 ? parent::getPapersNameByType($user_login->papers_type) : '',
                'papers_num' => $user_login->papers_num ,
                'balance'    => $user_login->balance > 0 ? floatval($user_login->balance) : 0 ,
                'school_id'  => $user_login->school_id
            ];
            //封装成数组修改数组
            $user_data = [
                'phone'     =>    $body['phone'] ,
                'password'  =>  password_hash($body['password'] , PASSWORD_DEFAULT),
                'open_id'   =>    $open_id,
                'head_icon' =>    $head_icon,
                'nickname'  =>    $nickname ,
                'school_id' =>    $school_id ,
                'update_at' =>  date('Y-m-d H:i:s'),
                'login_at'  =>    date('Y-m-d H:i:s')
            ];
            //绑定手机号
            $stduentRes = Student::where('phone',$body['phone'])->where(['school_id'=>$school_id])->update($user_data);
            if($stduentRes && !empty($stduentRes)){
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

            //判断是否设置了记住我
            if(isset($body['is_remember']) && $body['is_remember'] == 1){
                return response()->json(['code' => 200 , 'msg' => '登录成功' , 'data' => ['user_info' => $user_info]])->withCookie(new SCookie('user_phone', $body['phone'] , time()+3600*24*30)) ->withCookie(new SCookie('user_password', password_hash($body['password'] , PASSWORD_DEFAULT) , time()+3600*24*30));
            } else {
                return response()->json(['code' => 200 , 'msg' => '登录成功' , 'data' => ['user_info' => $user_info]]);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   注册方法
     * @param  参数说明       body包含以下参数[
     *     phone             手机号(必传)
     *     password          密码(必传)
     *     verifycode        验证码(必传)
     * ]
     * @param author    lys
     * @param ctime     2020-12-21
     * return string
     */
    public function doWxRegister() {
        $body = self::$accept_data;
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return response()->json(['code' => 202 , 'msg' => '传递数据不合法']);
        }
        $open_id = $body['open_id'];
        if(!isset($open_id) || empty($open_id)){
            return ['code' => 202 , 'msg' => '此用户未关注公众号'];
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

        //分校域名
        if(!isset($body['school_dns']) || empty($body['school_dns'])){
            return response()->json(['code' => 201 , 'msg' => '分校域名为空']);
        }

        //根据分校的域名获取所属分校的id
        $school_id = School::where('dns' , $body['school_dns'])->value('id');
        $school_id = isset($school_id) && !empty($school_id) && $school_id > 0 ? $school_id : 37;

        //验证码合法验证
        $verify_code = Redis::get('user:wxregister:'.$body['phone'].':'.$school_id);
        if(!$verify_code || empty($verify_code)){
            return ['code' => 201 , 'msg' => '请先获取验证码'];
        }

        //判断验证码是否一致
        if($verify_code != $body['verifycode']){
            return ['code' => 202 , 'msg' => '验证码错误'];
        }

        //key赋值
        $key = 'user:iswxregister:'.$body['phone'].':'.$school_id;

        //判断此学员是否被请求过一次(防止重复请求,且数据信息存在)
        if(Redis::get($key)){
            return response()->json(['code' => 205 , 'msg' => '此手机号已被注册']);
        } else {
            //判断用户手机号是否注册过
            $student_count = User::where("phone" , $body['phone'])->whereIn('is_forbid',[1,2])->count();
            if($student_count > 0){
                //存储学员的手机号值并且保存60s
                Redis::setex($key , 60 , $body['phone']);
                return response()->json(['code' => 205 , 'msg' => '此手机号已被注册']);
            }
        }

        //生成随机唯一的token
        $token = self::setAppLoginToken($body['phone']);

        //正常用户昵称/头像
        $nickname = empty(redis::get($open_id))?randstr(8):redis::get($open_id)['nickname'];
        $head_icon = empty(redis::get($open_id))?randstr(8):redis::get($open_id)['headimgurl'];

        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'pc';

        //开启事务
        DB::beginTransaction();
        try {

            //封装成数组
            $user_data = [
                'phone'     =>    $body['phone'] ,
                'open_id' => $open_id,
                'password'  =>    password_hash($body['password'] , PASSWORD_DEFAULT) ,
                'head_icon' =>    $head_icon,
                'nickname'  =>    $nickname ,
                'school_id' =>    $school_id ,
                'create_at' =>    date('Y-m-d H:i:s'),
                'login_at'  =>    date('Y-m-d H:i:s')
            ];

            //将数据插入到表中
            $user_id = User::insertGetId($user_data);
            if($user_id && $user_id > 0){
                $user_info = ['user_id' => $user_id , 'user_token' => $token , 'user_type' => 1  , 'opne_id'=>$open_id ,'head_icon' => '' , 'real_name' => '' , 'phone' => $body['phone'] , 'nickname' => $nickname , 'sign' => '' , 'papers_type' => '' , 'papers_name' => '' , 'papers_num' => '' , 'balance' => 0 , 'school_id' => $user_data['school_id']];
                //redis存储信息
                Redis::hMset("user:regtoken:".$platform.":".$token , $user_info);
                Redis::hMset("user:regtoken:".$platform.":".$body['phone'].":".$school_id , $user_info);

                //事务提交
                DB::commit();
                return response()->json(['code' => 200 , 'msg' => '注册成功' , 'data' => ['user_info' => $user_info]]);
            } else {
                //事务回滚
                DB::rollBack();
                return response()->json(['code' => 203 , 'msg' => '注册失败']);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    //微信支付
    public function wxpay(){
        //接收值
        $res = $_REQUEST;
        //查询学校信息
        $school = School::where(['dns'=>$res['school_dns']])->first();
        //支付信息
        $payinfo = PaySet::select('wx_app_id','wx_commercial_tenant_number','wx_api_key')->where(['school_id'=>$school['id']])->first();
        if(empty($payinfo) || empty($payinfo['wx_app_id']) || empty($payinfo['wx_commercial_tenant_number'])){
            return response()->json(['code' => 202, 'msg' => '商户号为空']);
        }
        //查询用户信息
        $user = Student::where(['open_id'=>$res['openid']])->first();
        //商品信息
        if(!empty($res['nature']) && $res['nature'] == 1){
            $course = CourseSchool::where(['id'=>$res['id'],'is_del'=>0,'status'=>1])->first();
        }else{
            $course = Coures::where(['id'=>$res['id'],'is_del'=>0,'status'=>1])->first();
        }
        //生成订单
        $data['order_number'] = date('YmdHis', time()) . rand(1111, 9999);
        $data['admin_id'] = 0;  //操作员id
        $data['order_type'] = 2;        //1线下支付 2 线上支付
        $data['student_id'] = $user['id'];
        $data['price'] = $course['sale_price'];
        $data['student_price'] = $course['pricing'];
        $data['lession_price'] = $course['pricing'];
        $data['pay_status'] = 4;
        $data['pay_type'] = 1;
        $data['status'] = 0;
        $data['oa_status'] = 0;              //OA状态
        $data['nature'] = $res['nature'];
        $data['class_id'] = $course['id'];
        $data['school_id'] = $school['id'];
        $add = Order::insertGetId($data);
        if($add){
            if($course['sale_price'] > 0 ){
                //微信进行支付
                $wxpay = new WxpayFactory();
                $return = $wxpay->getAppPayOrder($payinfo['wx_app_id'],$payinfo['wx_commercial_tenant_number'],$payinfo['wx_api_key'],$data['order_number'],$course['sale_price'],$course['title'],$res['openid']);
                if($return['code'] == 200){
                    return response()->json(['code' => 200, 'msg' =>'获取成功','data'=>$return['list']]);
                }else{
                    return response()->json(['code' => 202, 'msg' => $return['list']]);
                }
            }else{
                //计算课程有效期，回调
                if($res['nature'] == 1){
                    $lesson = CourseSchool::where(['id'=>$res['id']])->first();
                }else{
                    $lesson = Coures::where(['id'=>$res['id']])->first();
                }
                if($lesson['expiry'] ==0){
                    $validity = '3000-01-02 12:12:12';
                }else{
                    $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
                }
                $arrs = array(
                    'validity_time'=>$validity,
                    'status'=>2,
                    'oa_status'=>1,
                    'pay_time'=>date('Y-m-d H:i:s'),
                    'update_at'=>date('Y-m-d H:i:s')
                );
                Order::where(['id'=>$add])->update($arrs);
                $overorder = Order::where(['student_id'=>$user['id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                $userorder = Order::where(['student_id'=>$user['id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
                if($overorder == $userorder){
                    $state_status = 2;
                }else{
                    if($overorder > 0 ){
                        $state_status = 1;
                    }else{
                        $state_status = 0;
                    }
                }
                Student::where(['id'=>$user['id']])->update(['enroll_status'=>1,'state_status'=>$state_status]);
                return response()->json(['code' => 201, 'msg' =>'支付成功']);
             }
        }else{
            return response()->json(['code' => 202, 'msg' => '预订单生成失败']);
        }
    }
    public function wxAppnotify(){
        libxml_disable_entity_loader(true);
        $postStr = file_get_contents("php://input");  #接收微信返回数据xml格式
        $result = $this->XMLDataParse($postStr);
        $arr = $this->object_toarray($result); #对象转成数组
        file_put_contents('wxAppnotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        if ($arr['return_code'] == 'SUCCESS' && $arr['result_code'] == 'SUCCESS') {
            $orders = Order::where(['order_number'=>$arr['out_trade_no']])->first();
            if ($orders['status'] > 0) {
                return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            }else {
                DB::beginTransaction();
                try{
                    //修改订单状态  增加课程  修改用户收费状态
                    if($orders['nature'] == 1){
                        $lesson = CourseSchool::where(['id'=>$orders['class_id']])->first();
                    }else{
                        $lesson = Coures::where(['id'=>$orders['class_id']])->first();
                    }
                    if($lesson['expiry'] ==0){
                        $validity = '3000-01-02 12:12:12';
                    }else{
                        $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
                    }
                    $arrs = array(
                        'third_party_number'=>$arr['transaction_id'],
                        'validity_time'=>$validity,
                        'status'=>2,
                        'oa_status'=>1,
                        'pay_time'=>date('Y-m-d H:i:s'),
                        'update_at'=>date('Y-m-d H:i:s')
                    );
                    $res = Order::where(['order_number'=>$arr['out_trade_no']])->update($arrs);
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
                    if($overorder == $userorder){
                        $state_status = 2;
                    }else{
                        if($overorder > 0 ){
                            $state_status = 1;
                        }else{
                            $state_status = 0;
                        }
                    }
                    Student::where(['id'=>$orders['student_id']])->update(['enroll_status'=>1,'state_status'=>$state_status]);
                    if (!$res) {
                        //修改用户类型
                        throw new Exception('回调失败');
                    }
                    DB::commit();
                    return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
                }
            }
        }else{
            return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
        }
    }


public function wxh5pay(){
        //接收值
        $res = $_REQUEST;
        print_r($res);die;
        //查询学校信息
        $school = School::where(['dns'=>$res['school_dns']])->first();
        //支付信息
        $payinfo = PaySet::select('wx_app_id','wx_commercial_tenant_number','wx_api_key')->where(['school_id'=>$school['id']])->first();
        if(empty($payinfo) || empty($payinfo['wx_app_id']) || empty($payinfo['wx_commercial_tenant_number'])){
            return response()->json(['code' => 202, 'msg' => '商户号为空']);
        }
        //查询用户信息
        $user = Student::where(['id'=>$res['student_id']])->first();
        //商品信息
        if(!empty($res['nature']) && $res['nature'] == 1){
            $course = CourseSchool::where(['id'=>$res['id'],'is_del'=>0,'status'=>1])->first();
        }else{
            $course = Coures::where(['id'=>$res['id'],'is_del'=>0,'status'=>1])->first();
        }
        //生成订单
        $data['order_number'] = date('YmdHis', time()) . rand(1111, 9999);
        $data['admin_id'] = 0;  //操作员id
        $data['order_type'] = 2;        //1线下支付 2 线上支付
        $data['student_id'] = $user['id'];
        $data['price'] = $course['sale_price'];
        $data['student_price'] = $course['pricing'];
        $data['lession_price'] = $course['pricing'];
        $data['pay_status'] = 4;
        $data['pay_type'] = 1;
        $data['status'] = 0;
        $data['oa_status'] = 0;              //OA状态
        $data['nature'] = $res['nature'];
        $data['class_id'] = $course['id'];
        $data['school_id'] = $school['id'];
        $add = Order::insertGetId($data);
        if($add){
            if($course['sale_price'] > 0 ){
                //微信进行支付
                $wxpay = new WxpayFactory();
                $return = $wxpay->getH5PayOrder($payinfo['wx_app_id'],$payinfo['wx_commercial_tenant_number'],$payinfo['wx_api_key'],$data['order_number'],$course['sale_price'],$course['title'],$res['openid']);
                if($return['code'] == 200){
                    return response()->json(['code' => 200, 'msg' =>'获取成功','data'=>$return['list']]);
                }else{
                    return response()->json(['code' => 202, 'msg' => $return['list']]);
                }
            }else{
                //计算课程有效期，回调
                if($res['nature'] == 1){
                    $lesson = CourseSchool::where(['id'=>$res['id']])->first();
                }else{
                    $lesson = Coures::where(['id'=>$res['id']])->first();
                }
                if($lesson['expiry'] ==0){
                    $validity = '3000-01-02 12:12:12';
                }else{
                    $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
                }
                $arrs = array(
                    'validity_time'=>$validity,
                    'status'=>2,
                    'oa_status'=>1,
                    'pay_time'=>date('Y-m-d H:i:s'),
                    'update_at'=>date('Y-m-d H:i:s')
                );
                Order::where(['id'=>$add])->update($arrs);
                $overorder = Order::where(['student_id'=>$user['id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                $userorder = Order::where(['student_id'=>$user['id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
                if($overorder == $userorder){
                    $state_status = 2;
                }else{
                    if($overorder > 0 ){
                        $state_status = 1;
                    }else{
                        $state_status = 0;
                    }
                }
                Student::where(['id'=>$user['id']])->update(['enroll_status'=>1,'state_status'=>$state_status]);
                return response()->json(['code' => 200, 'msg' =>'支付成功']);
             }
        }else{
            return response()->json(['code' => 202, 'msg' => '预订单生成失败']);
        }
    }
    public function wxApph5notify(){
        libxml_disable_entity_loader(true);
        $postStr = file_get_contents("php://input");  #接收微信返回数据xml格式
        $result = $this->XMLDataParse($postStr);
        $arr = $this->object_toarray($result); #对象转成数组
        file_put_contents('wxAppnotify.txt', '时间:'.date('Y-m-d H:i:s').print_r($arr,true),FILE_APPEND);
        if ($arr['return_code'] == 'SUCCESS' && $arr['result_code'] == 'SUCCESS') {
            $orders = Order::where(['order_number'=>$arr['out_trade_no']])->first();
            if ($orders['status'] > 0) {
                return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            }else {
                DB::beginTransaction();
                try{
                    //修改订单状态  增加课程  修改用户收费状态
                    if($orders['nature'] == 1){
                        $lesson = CourseSchool::where(['id'=>$orders['class_id']])->first();
                    }else{
                        $lesson = Coures::where(['id'=>$orders['class_id']])->first();
                    }
                    if($lesson['expiry'] ==0){
                        $validity = '3000-01-02 12:12:12';
                    }else{
                        $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
                    }
                    $arrs = array(
                        'third_party_number'=>$arr['transaction_id'],
                        'validity_time'=>$validity,
                        'status'=>2,
                        'oa_status'=>1,
                        'pay_time'=>date('Y-m-d H:i:s'),
                        'update_at'=>date('Y-m-d H:i:s')
                    );
                    $res = Order::where(['order_number'=>$arr['out_trade_no']])->update($arrs);
                    $overorder = Order::where(['student_id'=>$orders['student_id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
                    $userorder = Order::where(['student_id'=>$orders['student_id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
                    if($overorder == $userorder){
                        $state_status = 2;
                    }else{
                        if($overorder > 0 ){
                            $state_status = 1;
                        }else{
                            $state_status = 0;
                        }
                    }
                    //分账订单入库，请求分账接口
                    $data['price'] = 0.01;  //实际支付金额
                    if($data['price']<6){
                        $fuwufei = 0.01;
                    }else{
                        $fuwufei = $data['price']*0.002;//分账后的服务费用
                    }
                    $data['price'] = $fuwufei;

                    $data['routing_order_number'] = date('YmdHis', time()) . rand(1111, 9999);
                    $data['order_id'] = 1;  //操作员id
                    $data['admin_id'] = 0;  //操作员id
                    $data['from_school_id'] = 0;  //操作员id
                    $data['to_school_id'] = 0;  //操作员id
                    $data['price'] = 0.01;  //操作员id
                    $data['add_time'] = date('Y-m-d H:i:s');  //操作员id
                    $add = WxRouting::insertGetId($data);
                    $wxpay = new WxpayFactory();
                    $return = $wxpay->getAppH5PayOrder('wx191328b7484877c8','1604760227','80966e130be700bf6e76b06912d2d5f2','1601424720',$arr['transaction_id'],$data['routing_order_number']);
                    Student::where(['id'=>$orders['student_id']])->update(['enroll_status'=>1,'state_status'=>$state_status]);
                    if (!$res) {
                        //修改用户类型
                        throw new Exception('回调失败');
                    }
                    DB::commit();
                    return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                } catch (\Exception $ex) {
                    DB::rollback();
                    return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
                }
            }
        }else{
            return "<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[error]]></return_msg></xml>";
        }
    }





    function http_get($url){

       $header = array(
           'Accept: application/json',
        );
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 超时设置,以秒为单位
        curl_setopt($curl, CURLOPT_TIMEOUT, 1);

        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);

        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //执行命令
        $data = curl_exec($curl);

        // 显示错误信息
        if (curl_error($curl)) {
            print "Error: " . curl_error($curl);
        } else {
            // 打印返回的内容
            curl_close($curl);
        }
        return $data;
    }

//xml格式数据解析函数
    function XMLDataParse($data){
        $xml = simplexml_load_string($data, NULL, LIBXML_NOCDATA);
        return $xml;
    }
    //把对象转成数组
    public function object_toarray($arr){
        if (is_object($arr)) {
            $arr = (array)$arr;
        }
        if (is_array($arr)) {
            foreach ($arr as $key => $value) {
                $arr[$key] = $this->object_toarray($value);
            }
        }
        return $arr;
    }
}
