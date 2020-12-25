<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Models\School;
use App\Models\StudentMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;


class UserController extends Controller {
    /*
     * @param  description   用户详情信息
     * @param author    dzj
     * @param ctime     2020-05-23
     * return string
     */
    public function getUserInfoById() {
        //获取提交的参数
        try{
            //根据用户id获取用户详情
            $user_info = Student::select("id as user_id" , "token  as user_token" , "user_type" , "head_icon" , "real_name" , "phone" , "nickname" , "sign" , "papers_type" , "papers_num" , "balance" , "school_id")->find(self::$accept_data['user_info']['user_id']);
            if($user_info && !empty($user_info)){
                //证件名称
                $user_info['papers_name']  = $user_info['papers_type'] > 0 ? parent::getPapersNameByType($user_info['papers_type']) : '';
                //余额
                $user_info['balance']      = floatval($user_info['balance']);
                $user_info['user_token']      = self::$accept_data['user_info']['user_token'];
                return response()->json(['code' => 200 , 'msg' => '获取学员信息成功' , 'data' => ['user_info' => $user_info]]);
            } else {
                return response()->json(['code' => 203 , 'msg' => '获取学员信息失败']);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   用户更新信息方法
     * @param  参数说明       body包含以下参数[
     *     head_icon             头像(非必传)
     *     real_name             姓名(非必传)
     *     nickname              昵称(非必传)
     *     sign                  签名(非必传)
     *     papers_name           证件名称(非必传)
     *     papers_num            证件号码(非必传)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-25
     * return string
     */
    public function doUserUpdateInfo() {
        //获取提交的参数
        $body = self::$accept_data;
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return response()->json(['code' => 202 , 'msg' => '传递数据不合法']);
        }

        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'pc';

        //hash中的token的key值
        $token_key   = "user:regtoken:".$platform.":".$body['user_token'];

        //空数组赋值
        $where = [];

        //判断头像是否为空
        if(isset($body['head_icon']) && !empty($body['head_icon'])){
            $where['head_icon'] = $body['head_icon'];
            //设置redis的头像值
            Redis::hSet($token_key , 'head_icon' , $body['head_icon']);
        }

        //判断姓名是否为空
        if(isset($body['real_name']) && !empty($body['real_name'])){
            $where['real_name'] = $body['real_name'];
            //设置redis的姓名值
            Redis::hSet($token_key , 'real_name' , $body['real_name']);
        }

        //判断昵称是否为空
        if(isset($body['nickname']) && !empty($body['nickname'])){
            $where['nickname']  = $body['nickname'];
            //设置redis的昵称值
            Redis::hSet($token_key , 'nickname' , $body['nickname']);
        }

        //判断签名是否为空
        if(isset($body['sign']) && !empty($body['sign'])){
            $where['sign']      = $body['sign'];
            //设置redis的签名值
            Redis::hSet($token_key , 'sign' , $body['sign']);
        }

        //判断证件名称是否为空
        if(isset($body['papers_name']) && !empty($body['papers_name'])){
            //根据证件名称获取证件类的id
            $papers_type = array_search($body['papers_name'], [1=>'身份证' , 2=>'护照' , 3=>'港澳通行证' , 4=>'台胞证' , 5=>'军官证' , 6=>'士官证' , 7=>'其他']);
            $where['papers_type'] = $papers_type ? $papers_type : 0;
            //设置redis的证件值
            Redis::hMset($token_key , ['papers_type' => $where['papers_type'] , 'papers_name' => parent::getPapersNameByType($where['papers_type'])]);
        }

        //判断证件号码是否为空
        if(isset($body['papers_num']) && !empty($body['papers_num'])){
            $where['papers_num'] = $body['papers_num'];
            //设置redis的证件号码值
            Redis::hSet($token_key , 'papers_num' , $body['papers_num']);
        }
        $where['update_at']  = date('Y-m-d H:i:s');

        //开启事务
        DB::beginTransaction();
        try{

            //更新用户信息
            $rs = Student::where("id" , $body['user_info']['user_id'])->update($where);
            if($rs && !empty($rs)){
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
     * @param  description   获取用户多网校方法
     * @param  参数说明       body包含以下参数[
     *     phone             手机号(必传)
     * ]
     * @param author    dzj
     * @param ctime     2020-09-16
     * return string
     */
    public function getUserMoreSchoolList(){
        try {
            $body = self::$accept_data;
            //判断传过来的数组数据是否为空
            if(!$body || !is_array($body)){
                return response()->json(['code' => 202 , 'msg' => '传递数据不合法']);
            }

            //判断是否游客模式
            if(isset(self::$accept_data['user_info']['user_type']) && !empty(self::$accept_data['user_info']['user_type']) && self::$accept_data['user_info']['user_type'] == 1){
                //网校数组设置
                $school_array = [];

                //通过用户手机号获取网校列表
                $user_school_list = Student::where('phone' , self::$accept_data['user_info']['phone'])->get()->toArray();
                if($user_school_list && !empty($user_school_list)){
                    //获取网校的个数
                    $school_count = count($user_school_list);
                    if($school_count && $school_count >= 2){
                        foreach($user_school_list as $k=>$v){
                            //通过网校的id获取网校的信息
                            $school_info = School::where('id' , $v['school_id'])->first();
                            $school_array[] = [
                                'school_id'      =>  $school_info['id'] ,
                                'school_name'    =>  $school_info['name'] ,
                                'default_school' =>  $v['is_set_school']
                            ];
                        }
                        return response()->json(['code' => 200 , 'msg' => '获取网校列表成功' , 'data' => $school_array]);
                    } else {
                        return response()->json(['code' => 200 , 'msg' => '获取网校列表成功' , 'data' => []]);
                    }
                } else {
                    return response()->json(['code' => 200 , 'msg' => '获取网校列表成功' , 'data' => []]);
                }
            } else {
                return response()->json(['code' => 200 , 'msg' => '获取网校列表成功' , 'data' => []]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   设置默认网校的方法
     * @param  参数说明       body包含以下参数[
     *     phone             手机号(必传)
     * ]
     * @param author    dzj
     * @param ctime     2020-09-16
     * return string
     */
    public function doSetDefaultSchool(){
        $body = self::$accept_data;
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return response()->json(['code' => 202 , 'msg' => '传递数据不合法']);
        }

        //判断分校id是否传递
        if(!isset($body['school_id']) || $body['school_id'] <= 0){
            return response()->json(['code' => 202 , 'msg' => '请选择网校']);
        }

        //判断此网校id是否存在
        $is_exists_info = School::where('id' , $body['school_id'])->count();
        if(!$is_exists_info || $is_exists_info <= 0){
            return response()->json(['code' => 203 , 'msg' => '此网校不存在']);
        }

        //判断此用户手机号下面是否有此网校
        $user_login = Student::where("phone" , self::$accept_data['user_info']['phone'])->where('school_id' , $body['school_id'])->first();
        if(!$user_login || empty($user_login)){
            return response()->json(['code' => 203 , 'msg' => '此手机号下面无此网校']);
        }

        //生成随机唯一的token
        $token = self::setAppLoginToken(self::$accept_data['user_info']['phone']);

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
            'is_show_shcool' => 0 ,
            'school_array'   => []
        ];

        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'pc';

        //hash中的token的key值
        $token_key   = "user:regtoken:".$platform.":".$token;
        $token_phone = "user:regtoken:".$platform.":".$user_login->phone;

        //开启事务
        DB::beginTransaction();
        try {

            //更新用户默认网校
            $update_default_school = Student::where("phone" , self::$accept_data['user_info']['phone'])->update(['is_set_school' => 0 , 'update_at' => date('Y-m-d H:i:s')]);
            if($update_default_school && !empty($update_default_school)){
                //更新选中的网校设为默认
                Student::where("phone" , self::$accept_data['user_info']['phone'])->where('school_id' , $body['school_id'])->update(['is_set_school' => 1 , 'update_at' => date('Y-m-d H:i:s')]);
                //事务提交
                DB::commit();

                //redis存储信息
                Redis::hMset($token_key , $user_info);
                Redis::hMset($token_phone , $user_info);
                return response()->json(['code' => 200 , 'msg' => '设置成功' ,'data' => ['user_info' => $user_info]]);
            } else {
                //事务回滚
                DB::rollBack();
                return response()->json(['code' => 203 , 'msg' => '设置失败']);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //返回头像默认地址
    public function GetuserImg(){

        $img = [
            "girl" => "http://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-11-20/160587361823785fb7afd26c951.png",//女同学

            "boy" => "http://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-11-20/160587359375355fb7afb976b8c.png",//男同学
        ];

        return response()->json(['code' => 200 , 'msg' => '返回成功' ,'data' => $img]);

    }

    /*
     * @param  description   用户退出登录接口
     * @param author    dzj
     * @param ctime     2020-06-01
     * return string
     */
    public function doLoginOut(){
        try {
            //获取用户token
            $token   =   self::$accept_data['user_info']['user_token'];

            //获取请求的平台端
            $platform = verifyPlat() ? verifyPlat() : 'pc';

            //hash中的token的key值
            $token_key   = "user:regtoken:".$platform.":".$token;
            $token_phone = "user:regtoken:".$platform.":".self::$accept_data['user_info']['phone'];

            //删除redis中用户token
            Redis::del($token_key);
            Redis::del($token_phone);
            return response()->json(['code' => 200 , 'msg' => '退出成功']);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /**
     *  课程表 接口
     * @return \Illuminate\Http\JsonResponse
     */
    public function timetable(){

        $data = self::$accept_data;
        $validator = Validator::make($data, [
            'start_time' => 'required|date'
        ], School::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $student_id = $data["user_info"]['user_id'];
        $school_id  = $data['user_info']['school_id'];


        $arr = Course::getClassTimetableByDate($student_id,$school_id,$data['start_time']);
        return response()->json(['code'=>200,'msg'=>'success','data'=>$arr]);
    }

    /**
     *  我的 消息
     */
    public function myMessage(){
        $data = self::$accept_data;
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        Log::info("myMessage: [pageSize:".$pagesize."page:".$page."offset:".$offset."]".PHP_EOL);
        // 获取 登录 的 两个数据
        $student_id = $data["user_info"]['user_id'];
        $school_id  = $data['user_info']['school_id'];

        // 按照 消息的 状态 进行 查询
        $msg_status  = 0 ;
        if(isset($data['status'])){
            $msg_status = $data['status'];
        }

        $student_meaasge  = new StudentMessage();
        $arr = $student_meaasge->getMessageByStudentAndSchoolId($student_id,$school_id,$msg_status,$offset,$pagesize);

        return response()->json(['code'=>200,'msg'=>'success','data'=>$arr]);

    }
    public function MessageCount(){
        $data = self::$accept_data;

        // 获取 登录 的 两个数据
        $student_id = $data["user_info"]['user_id'];
        $school_id  = $data['user_info']['school_id'];

        $student_meaasge  = new StudentMessage();

        // 这个 接口 中 涉及到 一个 功能 将 消息设定成 已读
        if(isset($data['id'])){
            $student_meaasge ->setMessageRead($data['id']);
        }

        //获取 已读 未读 消息 列表
        $ret_date = $student_meaasge ->getMessageStatistics($student_id,$school_id);
        return response()->json(['code'=>200,'msg'=>'success','data'=> $ret_date ]);
    }



}
