<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use App\Models\Enrolment;
use App\Models\CourseSchool;
use App\Models\Order;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Tools\MTCloud;
use App\Models\Coureschapters;
use App\Models\Coures;


class Student extends Model {
    //指定别的表名
    public $table      = 'ld_student';
    //时间戳设置
    public $timestamps = false;

    public function collectionLessons() {
        return $this->belongsToMany('App\Models\Lesson', 'ld_collections')->withTimestamps();
    }

    /*
     * @param  description   添加学员方法
     * @param  data          数组数据
     * @param  author        dzj
     * @param  ctime         2020-04-27
     * return  int
     */
    public static function insertStudent($data) {
        return self::insertGetId($data);
    }

    /*
     * @param  descriptsion    根据学员id获取详细信息
     * @param  参数说明         body包含以下参数[
     *     student_id   学员id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-27
     * return  array
     */
    public static function getStudentInfoById($body=[]) {
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断学员id是否合法
        if(!isset($body['student_id']) || empty($body['student_id']) || $body['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不合法'];
        }

        //key赋值
        $key = 'student:studentinfo:'.$body['student_id'];

        //判断此学员是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此学员不存在'];
        } else {
            //判断此学员在学员表中是否存在
            $student_count = self::where('id',$body['student_id'])->count();
            if($student_count <= 0){
                //存储学员的id值并且保存60s
                Redis::setex($key , 60 , $body['student_id']);
                return ['code' => 204 , 'msg' => '此学员不存在'];
            }
        }

        //根据id获取学员详细信息
        $student_info = self::where('id',$body['student_id'])->select('id','school_id','phone','real_name','sex','papers_type','papers_num','birthday','address_locus','age','educational','family_phone','office_phone','contact_people','contact_phone','email','qq','wechat','address','remark','head_icon','balance','reg_source','login_at')->first()->toArray();
        //判断头像是否为空
        if(empty($student_info['head_icon'])){
            $student_info['head_icon']  = 'https://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-07-01/159359854490285efc6250a7852.png';
        }
        //证件类型
        $papers_type_array = [1=>'身份证',2=>'护照',3=>'港澳通行证',4=>'台胞证',5=>'军官证',6=>'士官证',7=>'其他'];
        //学历数组
        $educational_array = [1=>'小学',2=>'初中',3=>'高中',4=>'大专',5=>'大本',6=>'研究生',7=>'博士生',8=>'博士后及以上'];
        //注册来源
        $reg_source_array  = [0=>'官网注册',1=>'手机端',2=>'线下录入'];
        //备注
        $student_info['remark']  = $student_info['remark'] && !empty($student_info['remark']) ? $student_info['remark'] : '';
        $student_info['educational_name']  = $student_info['educational'] && $student_info['educational'] > 0 ? $educational_array[$student_info['educational']] : '';
        $student_info['papers_type_name']  = $student_info['papers_type'] && $student_info['papers_type'] > 0 ? $papers_type_array[$student_info['papers_type']] : '';
        $student_info['reg_source']   = isset($reg_source_array[$student_info['reg_source']]) && !empty($reg_source_array[$student_info['reg_source']]) ? $reg_source_array[$student_info['reg_source']] : '';

        //通过分校的id获取分校的名称
        if($student_info['school_id'] && $student_info['school_id'] > 0){
            $student_info['school_name']  = \App\Models\School::where('id',$student_info['school_id'])->value('name');
        } else {
            $student_info['school_name']  = '';
        }
        //余额
        $student_info['balance']  = $student_info['balance'] > 0 ? $student_info['balance'] : 0;
        //最后登录时间
        $student_info['login_at']  = $student_info['login_at'] && !empty($student_info['login_at']) ? $student_info['login_at'] : '';
        return ['code' => 200 , 'msg' => '获取学员信息成功' , 'data' => $student_info];
    }

    /*
     * @param  descriptsion    获取学员列表
     * @param  参数说明         body包含以下参数[
     *     student_id   学员id
     *     is_forbid    账号状态
     *     state_status 开课状态
     *     real_name    姓名
     *     pagesize     每页显示条数
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-27
     * return  array
     */
    public static function getStudentList($body=[]) {
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 15;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取分校的状态和id
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        $school_id     = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

        //判断是否是总校的状态
        if($school_status > 0 && $school_status == 1){
            //获取学员的总数量
            $student_count = self::where(function($query) use ($body){
                //判断报名状态是否选择
                if(isset($body['enroll_status']) && strlen($body['enroll_status']) > 0 && in_array($body['enroll_status'] , [1,2])){
                    //已报名
                    if($body['enroll_status'] > 0 && $body['enroll_status'] == 1){
                        $query->where('enroll_status' , '=' , 1);
                    } else if($body['enroll_status'] > 0 && $body['enroll_status'] == 2){
                        $query->where('enroll_status' , '=' , 0);
                    }
                }

                //判断学校id是否传递
                if(isset($body['school_id']) && $body['school_id'] > 0){
                    $query->where('school_id' , '=' , $body['school_id']);
                }

                //判断开课状态是否选择
                if(isset($body['state_status']) && strlen($body['state_status']) > 0 && in_array($body['state_status'] , [0,1,2])){
                    $state_status = $body['state_status'] > 0 ? $body['state_status'] : 0;
                    $query->where('state_status' , '=' , $state_status);
                }

                //判断账号状态是否选择
                if(isset($body['is_forbid']) && !empty($body['is_forbid']) && in_array($body['is_forbid'] , [1,2])){
                    $query->where('is_forbid' , '=' , $body['is_forbid']);
                }

                //判断搜索内容是否为空
                if(isset($body['search']) && !empty($body['search'])){
                    $query->where('real_name','like','%'.$body['search'].'%')->orWhere('phone','like','%'.$body['search'].'%');
                }
            })->count();

            //判断学员数量是否为空
            if($student_count > 0){
                //学员列表
                $student_list = self::where(function($query) use ($body){
                    //判断学科id是否选择
                    /*if(isset($body['subject_id']) && !empty($body['subject_id']) && $body['subject_id'] > 0){
                        $query->where('subject_id' , '=' , $body['subject_id']);
                    }*/
                    //判断报名状态是否选择
                    if(isset($body['enroll_status']) && strlen($body['enroll_status']) > 0 && in_array($body['enroll_status'] , [1,2])){
                        //已报名
                        if($body['enroll_status'] > 0 && $body['enroll_status'] == 1){
                            $query->where('enroll_status' , '=' , 1);
                        } else if($body['enroll_status'] > 0 && $body['enroll_status'] == 2){
                            $query->where('enroll_status' , '=' , 0);
                        }
                    }

                    //判断学校id是否传递
                    if(isset($body['school_id']) && $body['school_id'] > 0){
                        $query->where('school_id' , '=' , $body['school_id']);
                    }

                    //判断开课状态是否选择
                    if(isset($body['state_status']) && strlen($body['state_status']) > 0 && in_array($body['state_status'] , [0,1,2])){
                        $state_status = $body['state_status'] > 0 ? $body['state_status'] : 0;
                        $query->where('state_status' , '=' , $state_status);
                    }

                    //判断账号状态是否选择
                    if(isset($body['is_forbid']) && !empty($body['is_forbid']) && in_array($body['is_forbid'] , [1,2])){
                        $query->where('is_forbid' , '=' , $body['is_forbid']);
                    }

                    //判断搜索内容是否为空
                    if(isset($body['search']) && !empty($body['search'])){
                        $query->where('real_name','like','%'.$body['search'].'%')->orWhere('phone','like','%'.$body['search'].'%');
                    }
                })->select('id as student_id','real_name','phone','create_at','enroll_status','state_status','is_forbid','papers_type','papers_num','school_id')->orderByDesc('create_at')->offset($offset)->limit($pagesize)->get()->toArray();
                foreach($student_list as $k=>$v){
                    //根据学校id获取学校名称
                    $student_list[$k]['school_name']  = \App\Models\School::where('id',$v['school_id'])->value('name');
                }
                return ['code' => 200 , 'msg' => '获取学员列表成功' , 'data' => ['student_list' => $student_list , 'total' => $student_count , 'pagesize' => $pagesize , 'page' => $page]];
            }
            return ['code' => 200 , 'msg' => '获取学员列表成功' , 'data' => ['student_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
        } else {
            //获取学员的总数量
            $student_count = self::where(function($query) use ($body){
                //判断报名状态是否选择
                if(isset($body['enroll_status']) && strlen($body['enroll_status']) > 0 && in_array($body['enroll_status'] , [1,2])){
                    //已报名
                    if($body['enroll_status'] > 0 && $body['enroll_status'] == 1){
                        $query->where('enroll_status' , '=' , 1);
                    } else if($body['enroll_status'] > 0 && $body['enroll_status'] == 2){
                        $query->where('enroll_status' , '=' , 0);
                    }
                }

                //判断学校id是否传递
                if(isset($body['school_id']) && $body['school_id'] > 0){
                    $query->where('school_id' , '=' , $body['school_id']);
                } else {
                    $school_id     = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
                    $query->where('school_id' , '=' , $school_id);
                }

                //判断开课状态是否选择
                if(isset($body['state_status']) && strlen($body['state_status']) > 0 && in_array($body['state_status'] , [0,1,2])){
                    $state_status = $body['state_status'] > 0 ? $body['state_status'] : 0;
                    $query->where('state_status' , '=' , $state_status);
                }

                //判断账号状态是否选择
                if(isset($body['is_forbid']) && !empty($body['is_forbid']) && in_array($body['is_forbid'] , [1,2])){
                    $query->where('is_forbid' , '=' , $body['is_forbid']);
                }

                //判断搜索内容是否为空
                if(isset($body['search']) && !empty($body['search'])){
                    $query->where('real_name','like','%'.$body['search'].'%')->orWhere('phone','like','%'.$body['search'].'%');
                }
            })->where('school_id' , $school_id)->count();

            //判断学员数量是否为空
            if($student_count > 0){
                //学员列表
                $student_list = self::where(function($query) use ($body){
                    //判断学科id是否选择
                    /*if(isset($body['subject_id']) && !empty($body['subject_id']) && $body['subject_id'] > 0){
                        $query->where('subject_id' , '=' , $body['subject_id']);
                    }*/
                    //判断报名状态是否选择
                    if(isset($body['enroll_status']) && strlen($body['enroll_status']) > 0 && in_array($body['enroll_status'] , [1,2])){
                        //已报名
                        if($body['enroll_status'] > 0 && $body['enroll_status'] == 1){
                            $query->where('enroll_status' , '=' , 1);
                        } else if($body['enroll_status'] > 0 && $body['enroll_status'] == 2){
                            $query->where('enroll_status' , '=' , 0);
                        }
                    }

                    //判断学校id是否传递
                    if(isset($body['school_id']) && $body['school_id'] > 0){
                        $query->where('school_id' , '=' , $body['school_id']);
                    } else {
                        $school_id     = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
                        $query->where('school_id' , '=' , $school_id);
                    }

                    //判断开课状态是否选择
                    if(isset($body['state_status']) && strlen($body['state_status']) > 0 && in_array($body['state_status'] , [0,1,2])){
                        $state_status = $body['state_status'] > 0 ? $body['state_status'] : 0;
                        $query->where('state_status' , '=' , $state_status);
                    }

                    //判断账号状态是否选择
                    if(isset($body['is_forbid']) && !empty($body['is_forbid']) && in_array($body['is_forbid'] , [1,2])){
                        $query->where('is_forbid' , '=' , $body['is_forbid']);
                    }

                    //判断搜索内容是否为空
                    if(isset($body['search']) && !empty($body['search'])){
                        $query->where('real_name','like','%'.$body['search'].'%')->orWhere('phone','like','%'.$body['search'].'%');
                    }
                })->select('id as student_id','real_name','phone','create_at','enroll_status','state_status','is_forbid','papers_type','papers_num','school_id')->where('school_id' , $school_id)->orderByDesc('create_at')->offset($offset)->limit($pagesize)->get()->toArray();
                foreach($student_list as $k=>$v){
                    //根据学校id获取学校名称
                    $student_list[$k]['school_name']  = \App\Models\School::where('id',$v['school_id'])->value('name');
                }
                return ['code' => 200 , 'msg' => '获取学员列表成功' , 'data' => ['student_list' => $student_list , 'total' => $student_count , 'pagesize' => $pagesize , 'page' => $page]];
            }
            return ['code' => 200 , 'msg' => '获取学员列表成功' , 'data' => ['student_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
        }
    }


    /*
     * @param  descriptsion    获取学员转校列表
     * @param  参数说明         body包含以下参数[
     *     search    姓名/手机号
     * ]
     * @param  author          dzj
     * @param  ctime           2020-07-29
     * return  array
     */
    public static function getStudentTransferSchoolList($body=[]) {
        //操作员id
        $school_id     = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

        //判断搜索得姓名/手机号是否为空
        if(isset($body['search']) && !empty($body['search'])){
            //学员列表
            $student_list = self::where(function($query) use ($body){
                //判断搜索内容是否为空
                if(isset($body['search']) && !empty($body['search'])){
                    $query->where('real_name','like','%'.$body['search'].'%')->orWhere('phone','like','%'.$body['search'].'%');
                }
            })->select('id as student_id','real_name','phone','create_at','enroll_status','state_status','is_forbid','school_id')->orderByDesc('create_at')->get()->toArray();

            //判断学员列表是否为空
            if($student_list && !empty($student_list)){
                foreach($student_list as $k=>$v){
                    //根据学校id获取学校名称
                    $school_name = \App\Models\School::where('id',$v['school_id'])->value('name');
                    $student_list[$k]['school_name']  = $school_name && !empty($school_name) ? $school_name : '';
                }
                return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => $student_list];
            } else {
                return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => []];
            }
        } else {
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => []];
        }
    }

    /*
     * @param  descriptsion    学员转校功能
     * @param  参数说明         body包含以下参数[
     *     student_id   学员id
     *     school_id    分校id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-07-29
     * return  array
     */
    public static function doTransferSchool($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断转校得参数是否为空
        if(!isset($body['transfer_school']) || empty($body['transfer_school'])){
            return ['code' => 201 , 'msg' => '转校参数为空'];
        }

        //转校参数赋值
        $transfer_school = json_decode($body['transfer_school'] , true);

        //获取分校的状态和id
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        $admin_id      = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //判断是分校还是总校
        if($school_status > 0 && $school_status == 1){
            //操作时间赋值
            $time = date('Y-m-d H:i:s');

            //循环获取学员和指定分校
            foreach($transfer_school as $k=>$v){
                //学员id赋值
                $student_id = $v['student_id'];

                //根据学员id获取学员详情
                $student_info = self::where('id',$student_id)->first();

                //学校id赋值
                $school_id  = $v['school_id'];

                //组装数组信息
                $transfer_array = [
                    'admin_id'         =>   $admin_id ,
                    'student_id'       =>   $student_id ,
                    'from_school_id'   =>   $student_info['school_id'] ,
                    'to_school_id'     =>   $school_id ,
                    'create_at'        =>   $time
                ];

                //根据学员id更新信息
                if(false !== self::where('id',$student_id)->update(['school_id' => $school_id , 'update_at' => $time])){
                    //添加日志操作
                    AdminLog::insertAdminLog([
                        'admin_id'       =>   $admin_id  ,
                        'module_name'    =>  'Student' ,
                        'route_url'      =>  'admin/student/doTransferSchool' ,
                        'operate_method' =>  'insert' ,
                        'content'        =>  '转校详情'.json_encode($transfer_array) ,
                        'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                        'create_at'      =>  date('Y-m-d H:i:s')
                    ]);
                }
            }

            //返回值信息
            return ['code' => 200 , 'msg' => '操作成功'];
        } else {
            return ['code' => 202 , 'msg' => '分校没有转校权限'];
        }
    }

    /*
     * @param  description   修改学员的方法
     * @param  参数说明       body包含以下参数[
     *     student_id   学员id
     *     phone        手机号
     *     real_name    学员姓名
     *     sex          性别(1男,2女)
     *     papers_type  证件类型(1代表身份证,2代表护照,3代表港澳通行证,4代表台胞证,5代表军官证,6代表士官证,7代表其他)
     *     papers_num   证件号码
     *     birthday     出生日期
     *     address_locus户口所在地
     *     age          年龄
     *     educational  学历(1代表小学,2代表初中,3代表高中,4代表大专,5代表大本,6代表研究生,7代表博士生,8代表博士后及以上)
     *     family_phone 家庭电话号
     *     office_phone 办公电话
     *     contact_people  紧急联系人
     *     contact_phone   紧急联系电话
     *     email           邮箱
     *     qq              QQ号码
     *     wechat          微信
     *     address         地址
     *     remark          备注
     * ]
     * @param author    dzj
     * @param ctime     2020-04-27
     * return string
     */
    public static function doUpdateStudent($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断学员id是否合法
        if(!isset($body['student_id']) || empty($body['student_id']) || $body['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不合法'];
        }

        //判断学员的学校id是否为空
        if(!isset($body['school_id']) || $body['school_id'] <= 0){
            return ['code' => 201 , 'msg' => '请选择学校id'];
        }

        //判断手机号是否为空
        if(!isset($body['phone']) || empty($body['phone'])){
            return ['code' => 201 , 'msg' => '请输入手机号'];
        } else if(!preg_match('#^13[\d]{9}$|^14[\d]{9}$|^15[\d]{9}$|^17[\d]{9}$|^18[\d]{9}|^16[\d]{9}|^19[\d]{9}$#', $body['phone'])) {
            return ['code' => 202 , 'msg' => '手机号不合法'];
        }

        //判断姓名是否为空
        if(!isset($body['real_name']) || empty($body['real_name'])){
            return ['code' => 201 , 'msg' => '请输入姓名'];
        }

        //判断性别是否选择
        if(isset($body['sex']) && !empty($body['sex']) && !in_array($body['sex'] , [1,2])){
            return ['code' => 202 , 'msg' => '性别不合法'];
        }

        //判断证件类型是否合法
        if(isset($body['papers_type']) && !empty($body['papers_type']) && !in_array($body['papers_type'] , [1,2,3,4,5,6,7])){
            return ['code' => 202 , 'msg' => '证件类型不合法'];
        }

        //判断年龄是否为空
        if(isset($body['age']) && !empty($body['age']) && $body['age'] < 0){
            return ['code' => 201 , 'msg' => '请输入年龄'];
        }

        //判断最高学历是否合法
        if(isset($body['educational']) && !empty($body['educational']) && !in_array($body['educational'] , [1,2,3,4,5,6,7,8])){
            return ['code' => 202 , 'msg' => '最高学历类型不合法'];
        }

        //获取学员id
        $student_id = $body['student_id'];

        //key赋值
        $key = 'student:update:'.$student_id;

        //判断此学员是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此学员不存在'];
        } else {
            //判断此学员在学员表中是否存在
            $student_count = self::where('id',$student_id)->count();
            if($student_count <= 0){
                //存储学员的id值并且保存60s
                Redis::setex($key , 60 , $student_id);
                return ['code' => 204 , 'msg' => '此学员不存在'];
            }
        }

        //获取分校的状态和id
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        $school_id     = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

        //组装学员数组信息
        $student_array = [
            'phone'         =>   $body['phone'] ,
            'real_name'     =>   $body['real_name'] ,
            'sex'           =>   isset($body['sex']) && $body['sex'] == 1 ? 1 : 2 ,
            'papers_type'   =>   isset($body['papers_type']) && in_array($body['papers_type'] , [1,2,3,4,5,6,7]) ? $body['papers_type'] : 0 ,
            'papers_num'    =>   isset($body['papers_num']) && !empty($body['papers_num']) ? $body['papers_num'] : '' ,
            'birthday'      =>   isset($body['birthday']) && !empty($body['birthday']) ? $body['birthday'] : '' ,
            'address_locus' =>   isset($body['address_locus']) && !empty($body['address_locus']) ? $body['address_locus'] : '' ,
            'age'           =>   isset($body['age']) && $body['age'] > 0 ? $body['age'] : 0 ,
            'educational'   =>   isset($body['educational']) && in_array($body['educational'] , [1,2,3,4,5,6,7,8]) ? $body['educational'] : 0 ,
            'family_phone'  =>   isset($body['family_phone']) && !empty($body['family_phone']) ? $body['family_phone'] : '' ,
            'office_phone'  =>   isset($body['office_phone']) && !empty($body['office_phone']) ? $body['office_phone'] : '' ,
            'contact_people'=>   isset($body['contact_people']) && !empty($body['contact_people']) ? $body['contact_people'] : '' ,
            'contact_phone' =>   isset($body['contact_phone']) && !empty($body['contact_phone']) ? $body['contact_phone'] : '' ,
            'email'         =>   isset($body['email']) && !empty($body['email']) ? $body['email'] : '' ,
            'qq'            =>   isset($body['qq']) && !empty($body['qq']) ? $body['qq'] : '' ,
            'wechat'        =>   isset($body['wechat']) && !empty($body['wechat']) ? $body['wechat'] : '' ,
            'address'       =>   isset($body['address']) && !empty($body['address']) ? $body['address'] : '' ,
            'remark'        =>   isset($body['remark']) && !empty($body['remark']) ? $body['remark'] : '' ,
            //'school_id'     =>   $school_status > 0 && $school_status == 1 ? $body['school_id'] : $school_id ,
            'school_id'     =>   $body['school_id'] ,
            'update_at'     =>   date('Y-m-d H:i:s')
        ];

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;


        //根据学员id获取学员信息
        $student_info = self::find($student_id);
        if($student_info['phone'] != $body['phone']){
            //根据手机号判断是否注册
            $is_mobile_exists = self::where('school_id' , $body['school_id'])->where("phone" , $body['phone'])->count();
            if($is_mobile_exists > 0){
                return ['code' => 205 , 'msg' => '此手机号已存在'];
            }
        }
        //开启事务
        DB::beginTransaction();
        try {
            //根据学员id更新信息
            if(false !== self::where('id',$student_id)->update($student_array)){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Student' ,
                    'route_url'      =>  'admin/student/doUpdateStudent' ,
                    'operate_method' =>  'update' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '更新成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '更新失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }

    }



    /*
     * @param  description   添加学员的方法
     * @param  参数说明       body包含以下参数[
     *     phone        手机号
     *     real_name    学员姓名
     *     sex          性别(1男,2女)
     *     papers_type  证件类型(1代表身份证,2代表护照,3代表港澳通行证,4代表台胞证,5代表军官证,6代表士官证,7代表其他)
     *     papers_num   证件号码
     *     birthday     出生日期
     *     address_locus户口所在地
     *     age          年龄
     *     educational  学历(1代表小学,2代表初中,3代表高中,4代表大专,5代表大本,6代表研究生,7代表博士生,8代表博士后及以上)
     *     family_phone 家庭电话号
     *     office_phone 办公电话
     *     contact_people  紧急联系人
     *     contact_phone   紧急联系电话
     *     email           邮箱
     *     qq              QQ号码
     *     wechat          微信
     *     address         地址
     *     remark          备注
     * ]
     * @param author    dzj
     * @param ctime     2020-04-27
     * return string
     */
    public static function doInsertStudent($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断学员的学校id是否为空
        if(!isset($body['school_id']) || $body['school_id'] <= 0){
            return ['code' => 201 , 'msg' => '请选择学校id'];
        }

        //判断手机号是否为空
        if(!isset($body['phone']) || empty($body['phone'])){
            return ['code' => 201 , 'msg' => '请输入手机号'];
        } else if(!preg_match('#^13[\d]{9}$|^14[\d]{9}$|^15[\d]{9}$|^17[\d]{9}$|^18[\d]{9}|^16[\d]{9}|^19[\d]{9}$#', $body['phone'])) {
            return ['code' => 202 , 'msg' => '手机号不合法'];
        }

        //判断姓名是否为空
        if(!isset($body['real_name']) || empty($body['real_name'])){
            return ['code' => 201 , 'msg' => '请输入姓名'];
        }

        //判断性别是否选择
        if(isset($body['sex']) && !empty($body['sex']) && !in_array($body['sex'] , [1,2])){
            return ['code' => 202 , 'msg' => '性别不合法'];
        }

        //判断证件类型是否合法
        if(isset($body['papers_type']) && !empty($body['papers_type']) && !in_array($body['papers_type'] , [1,2,3,4,5,6,7])){
            return ['code' => 202 , 'msg' => '证件类型不合法'];
        }

        //判断年龄是否为空
        if(isset($body['age']) && !empty($body['age']) && $body['age'] < 0){
            return ['code' => 201 , 'msg' => '请输入年龄'];
        }

        //判断最高学历是否合法
        if(isset($body['educational']) && !empty($body['educational']) && !in_array($body['educational'] , [1,2,3,4,5,6,7,8])){
            return ['code' => 202 , 'msg' => '最高学历类型不合法'];
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        $school_id= isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;

        //手机号后八位用于密码
        $password = substr($body['phone'] , -8);

        //正常用户昵称
        $nickname = randstr(8);

        //判断手机号是否存在
        $is_exists_mobile = self::where("phone" , $body['phone'])->first();
        if($is_exists_mobile && !empty($is_exists_mobile)){
            $password = $is_exists_mobile['password'];
        } else {
            $password = password_hash($password , PASSWORD_DEFAULT);
        }

        //组装学员数组信息
        $student_array = [
            'phone'         =>   $body['phone'] ,
            //'password'      =>   password_hash('12345678' , PASSWORD_DEFAULT) ,
            'password'      =>   $password ,
            'nickname'      =>   $nickname ,
            'real_name'     =>   $body['real_name'] ,
            'sex'           =>   isset($body['sex']) && $body['sex'] == 1 ? 1 : 2 ,
            'papers_type'   =>   isset($body['papers_type']) && in_array($body['papers_type'] , [1,2,3,4,5,6,7]) ? $body['papers_type'] : 0 ,
            'papers_num'    =>   isset($body['papers_num']) && !empty($body['papers_num']) ? $body['papers_num'] : '' ,
            'birthday'      =>   isset($body['birthday']) && !empty($body['birthday']) ? $body['birthday'] : '' ,
            'address_locus' =>   isset($body['address_locus']) && !empty($body['address_locus']) ? $body['address_locus'] : '' ,
            'age'           =>   isset($body['age']) && $body['age'] > 0 ? $body['age'] : 0 ,
            'educational'   =>   isset($body['educational']) && in_array($body['educational'] , [1,2,3,4,5,6,7,8]) ? $body['educational'] : 0 ,
            'family_phone'  =>   isset($body['family_phone']) && !empty($body['family_phone']) ? $body['family_phone'] : '' ,
            'office_phone'  =>   isset($body['office_phone']) && !empty($body['office_phone']) ? $body['office_phone'] : '' ,
            'contact_people'=>   isset($body['contact_people']) && !empty($body['contact_people']) ? $body['contact_people'] : '' ,
            'contact_phone' =>   isset($body['contact_phone']) && !empty($body['contact_phone']) ? $body['contact_phone'] : '' ,
            'email'         =>   isset($body['email']) && !empty($body['email']) ? $body['email'] : '' ,
            'qq'            =>   isset($body['qq']) && !empty($body['qq']) ? $body['qq'] : '' ,
            'wechat'        =>   isset($body['wechat']) && !empty($body['wechat']) ? $body['wechat'] : '' ,
            'address'       =>   isset($body['address']) && !empty($body['address']) ? $body['address'] : '' ,
            'remark'        =>   isset($body['remark']) && !empty($body['remark']) ? $body['remark'] : '' ,
            'admin_id'      =>   $admin_id ,
            //'school_id'     =>   $school_status > 0 && $school_status == 1 ? $body['school_id'] : $school_id ,
            'school_id'     =>   $body['school_id'] ,
            'reg_source'    =>   2 ,
            'create_at'     =>   date('Y-m-d H:i:s')
        ];


        //根据手机号判断是否注册
        $is_mobile_exists = self::where('school_id' , $body['school_id'])->where("phone" , $body['phone'])->count();
        if($is_mobile_exists > 0){
            return ['code' => 205 , 'msg' => '此手机号已存在'];
        }
        //开启事务
        DB::beginTransaction();
        try {
            //将数据插入到表中
            if(false !== self::insertStudent($student_array)){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Student' ,
                    'route_url'      =>  'admin/student/doInsertStudent' ,
                    'operate_method' =>  'insert' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '添加成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '添加失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }

    }

    /*
     * @param  descriptsion    启用/禁用的方法
     * @param  参数说明         body包含以下参数[
     *     is_forbid      是否启用(1代表启用,2代表禁用)
     *     student_id     学员id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-28
     * return  array
     */
    public static function doForbidStudent($body=[]) {
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断学员id是否合法
        if(!isset($body['student_id']) || empty($body['student_id']) || $body['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不合法'];
        }

        //key赋值
        $key = 'student:forbid:'.$body['student_id'];

        //判断此学员是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此学员不存在'];
        } else {
            //判断此学员在学员表中是否存在
            $student_count = self::where('id',$body['student_id'])->count();
            if($student_count <= 0){
                //存储学员的id值并且保存60s
                Redis::setex($key , 60 , $body['student_id']);
                return ['code' => 204 , 'msg' => '此学员不存在'];
            }
        }

        //根据学员的id获取学员的状态
        $is_forbid = self::where('id',$body['student_id'])->pluck('is_forbid');

        //追加更新时间
        $data = [
            'is_forbid'    => $is_forbid[0] > 1 ? 1 : 2 ,
            'update_at'    => date('Y-m-d H:i:s')
        ];

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //开启事务
        DB::beginTransaction();
        try {
            //根据学员id更新账号状态
            if(false !== self::where('id',$body['student_id'])->update($data)){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Student' ,
                    'route_url'      =>  'admin/student/doForbidStudent' ,
                    'operate_method' =>  'update' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '操作成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '操作失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }

    }


    /*
     * @param  description   导入学员功能方法
     * @param  参数说明       $body导入数据参数[
     *     is_insert         是否执行插入操作(1表示是,0表示否)
     * ]
     * @param  author        dzj
     * @param  ctime         2020-07-21
    */
    public static function doImportUser($body=[] , $is_insert = 0){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 201 , 'msg' => '暂无导入的数据信息'];
        }

        //判断导入试题信息是否为空
        if(!$body['data'] || empty($body['data'])){
            return ['code' => 201 , 'msg' => '导入数据为空'];
        }

        //课程分类
        $course_type = json_decode($body['course_type'] , true);

        //根据课程参数相关信息
        $lession_id    = $body['course_id'];
        $lession_price = $body['sale_price'];
        $nature        = $body['nature'];

        //课程大小分类
        $course_parent_id = isset($course_type[0]) && $course_type[0] > 0 ? $course_type[0] : 0;
        $course_child_id  = isset($course_type[1]) && $course_type[1] > 0 ? $course_type[1] : 0;

        //分校id
        $school_id = $body['school_id'];

        //证件类型数组
        $papers_type_array = [1 => '身份证' , 2 => '护照' , 3 => '港澳通行证' , 4 => '台胞证' , 5 => '军官证' , 6 => '士官证' , 7 => '其他'];

        //最高学历数组
        $educational_array = [1 => '小学' , 2 => '初中' , 3 => '高中' , 4 => '大专' , 5 => '大本' , 6 => '研究生' , 7 => '博士生' , 8 => '博士后及以上'];

        //支付类型数组
        $payment_type_array = [1 => '定金' , 2 => '部分尾款' , 3 => '最后一笔款' , 4 => '全款'];

        //支付方式数组
        $payment_method_array = [1 => '微信' , 2 => '支付宝' , 3 => '银行转账'];

        //学员列表赋值
        $student_list = $body['data'];

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //空数组赋值
        $arr = [];
        foreach($student_list as $k=>$v){
            $phone     = !empty($v[0]) ? trim($v[0]) : '';    //手机号
            $real_name = !empty($v[2]) ? trim($v[2]) : '';    //姓名
            if(!empty($v[3])){
                $sex       = trim($v[3]) == '男' ? 1 : 2;    //性别
            } else {
                $sex       = 0;    //性别
            }


            //判断证件类型
            if($v[4] && !empty($v[4])){
                $papers_type = array_search(trim($v[4]) , $papers_type_array);
            } else {
                $papers_type = 0;
            }

            //证件号码
            $papers_num = !empty($v[5]) ? trim($v[5]) : '';
            //生日
            $birthday   = !empty($v[6]) ? trim($v[6]) : '';
            //户口所在地
            $address_locus = !empty($v[7]) ? trim($v[7]) : '';
            //年龄
            $age = !empty($v[8]) ? trim($v[8]) : 0;

            //判断学历
            if($v[9] && !empty($v[9])){
                $educational = array_search(trim($v[9]) , $educational_array);
            } else {
                $educational = 0;
            }

            //家庭电话号
            $family_phone = !empty($v[10]) ? trim($v[10]) : '';
            //办公电话
            $office_phone = !empty($v[11]) ? trim($v[11]) : '';
            //紧急联系人
            $contact_people = !empty($v[12]) ? trim($v[12]) : '';
            //紧急联系电话
            $contact_phone  = !empty($v[13]) ? trim($v[13]) : '';
            //邮箱
            $email          = !empty($v[14]) ? trim($v[14]) : '';
            //邮箱
            $qq             = !empty($v[15]) ? trim($v[15]) : '';
            //微信
            $wechat         = !empty($v[16]) ? trim($v[16]) : '';
            //省
            $province       = !empty($v[17]) ? trim($v[17]) : 0;
            //市
            $city           = !empty($v[18]) ? trim($v[18]) : 0;
            //县
            $county         = !empty($v[19]) ? trim($v[19]) : 0;
            //详细地址
            $address        = !empty($v[20]) ? trim($v[20]) : '';
            //备注
            $remark         = !empty($v[21]) ? trim($v[21]) : '';

            //正常用户昵称
            $nickname = randstr(8);

            //支付金额
            $pay_fee = !empty($v[22]) ? trim($v[22]) : 0;
            //支付类型
            if($v[23] && !empty($v[23])){
                $payment_type = array_search(trim($v[23]) , $payment_type_array);
            } else {
                $payment_type = 0;
            }

            //支付方式
            if($v[24] && !empty($v[24])){
                $payment_method = array_search(trim($v[24]) , $payment_method_array);
            } else {
                $payment_method = 0;
            }

            //手机号后八位用于密码
            $password = substr($phone , -8);

            //判断此手机号是否注册过
            $is_exists_phone = self::where('school_id' , $school_id)->where('phone' , $phone)->count();
            if($is_exists_phone <= 0){
                //判断手机号是否存在
                $is_exists_mobile = self::where("phone" , $phone)->first();
                if($is_exists_mobile && !empty($is_exists_mobile)){
                    $password = $is_exists_mobile['password'];
                } else {
                    $password = password_hash($password , PASSWORD_DEFAULT);
                }

                //学员插入操作
                $user_id = self::insertGetId([
                    'admin_id'       =>  $admin_id ,
                    'school_id'      =>  $school_id ,
                    'phone'          =>  $phone ,
                    'nickname'       =>  $nickname ,
                    'password'       =>  $password ,
                    'real_name'      =>  $real_name ,
                    'sex'            =>  $sex ,
                    'papers_type'    =>  $papers_type ,
                    'papers_num'     =>  $papers_num  ,
                    'address_locus'  =>  $address_locus ,
                    'age'            =>  $age ,
                    'educational'    =>  $educational ,
                    'family_phone'   =>  $family_phone ,
                    'office_phone'   =>  $office_phone ,
                    'contact_people' =>  $contact_people ,
                    'contact_phone'  =>  $contact_phone ,
                    'email'          =>  $email ,
                    'qq'             =>  $qq ,
                    'wechat'         =>  $wechat ,
                    'province_id'    =>  $province ,
                    'city_id'        =>  $city ,
                    'birthday'       =>  $birthday ,
                    'address'        =>  $address ,
                    'remark'         =>  $remark ,
                    'reg_source'     =>  2 ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);

                //添加报名表数据
                if($user_id && $user_id > 0){
                    //判断支付类型和支付方式是否合法
                    if(in_array($payment_type , [1,2,3,4]) && in_array($payment_method , [1,2,3])){
                        //报名数据信息追加
                        $enroll_array = [
                            'school_id'      =>   $school_id ,
                            'student_id'     =>   $user_id ,
                            'parent_id'      =>   $course_parent_id ,
                            'child_id'       =>   $course_child_id ,
                            'lession_id'     =>   $lession_id ,
                            'lession_price'  =>   $lession_price ,
                            'student_price'  =>   $pay_fee ,
                            'payment_type'   =>   $payment_type ,
                            'payment_method' =>   $payment_method ,
                            'payment_fee'    =>   $pay_fee ,
                            'payment_time'   =>   date('Y-m-d H:i:s') ,
                            'admin_id'       =>   $admin_id ,
                            'status'         =>   1 ,
                            'create_at'      =>   date('Y-m-d H:i:s')
                        ];

                        //添加报名信息
                        $enroll_id = Enrolment::insertEnrolment($enroll_array);
                        if($enroll_id && $enroll_id > 0){
                            //订单表插入逻辑
                            $enroll_array['nature']  =  $nature;
                            Order::offlineStudentSignupNotaudit($enroll_array);
                        }
                    }
                }
            } else {
                //通过手机号获取学员的id
                $user_info = self::where('school_id' , $school_id)->where('phone' , $phone)->first();
                $user_id   = $user_info['id'];

                //判断此学员在报名表中是否支付类型和支付金额分校是否存在
                $is_exists = Enrolment::where('school_id' , $school_id)->where('student_id' , $user_id)->where('lession_id' , $lession_id)->where('payment_type' , $payment_type)->where('payment_fee' , $pay_fee)->count();
                if($is_exists <= 0){
                    //添加报名表数据
                    if($user_id && $user_id > 0){
                        //判断支付类型和支付方式是否合法
                        if(in_array($payment_type , [1,2,3,4]) && in_array($payment_method , [1,2,3])){
                            //报名数据信息追加
                            $enroll_array = [
                                'school_id'      =>   $school_id ,
                                'student_id'     =>   $user_id ,
                                'parent_id'      =>   $course_parent_id ,
                                'child_id'       =>   $course_child_id ,
                                'lession_id'     =>   $lession_id ,
                                'lession_price'  =>   $lession_price ,
                                'student_price'  =>   $pay_fee ,
                                'payment_type'   =>   $payment_type ,
                                'payment_method' =>   $payment_method ,
                                'payment_fee'    =>   $pay_fee ,
                                'payment_time'   =>   date('Y-m-d H:i:s') ,
                                'admin_id'       =>   $admin_id ,
                                'status'         =>   1 ,
                                'create_at'      =>   date('Y-m-d H:i:s')
                            ];

                            //添加报名信息
                            $enroll_id = Enrolment::insertEnrolment($enroll_array);
                            if($enroll_id && $enroll_id > 0){
                                //订单表插入逻辑
                                $enroll_array['nature']  =  $nature;
                                Order::offlineStudentSignupNotaudit($enroll_array);
                            }
                        }
                    }
                }
            }
        }
        //返回信息数据
        return ['code' => 200 , 'msg' => '导入试题列表成功' , 'data' => $arr];
    }
    //获取学员学校进度列表
    public static function getStudentStudyList($data){

        //每页显示的条数
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 10;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //查询学员id
        $student = self::where("phone",$data['phone'])->first();
        //dd($student);
        if($student['school'] == 1){
            $course_id = $data['course_id'];
        }else{
            //自增
            $res = Coures::select()->where(["id"=>$data['course_id'],"school_id"=>$student['school_id']])->first();
            $course_id = $res['id'];
            if(empty($res)){
                //授权课程
                $res = CourseSchool::select()->where(["id"=>$data['course_id'],"to_school_id"=>$student['school_id']])->first();
                $course_id = $res['course_id'];
            }
        }
        $uid = $student['id'];
        //查询章
        DB::enableQueryLog();
        $chapters =  Coureschapters::select('id', 'name', 'parent_id as pid')
                ->where([ 'course_id' => $course_id,'is_del'=>0,'parent_id'=>0])
                ->orderBy('create_at', 'asc')->get()->toArray();
                $a = DB::getQueryLog();
                //print_r($a);
        foreach ($chapters as $key => $value) {
            //查询小节
            $chapters[$key]['childs'] = Coureschapters::join("ld_course_video_resource","ld_course_chapters.resource_id","=","ld_course_video_resource.id")
                ->select('ld_course_chapters.id','ld_course_chapters.name','ld_course_chapters.resource_id','ld_course_video_resource.course_id','ld_course_video_resource.mt_video_id','ld_course_video_resource.mt_duration')
                ->where(['ld_course_chapters.is_del'=> 0, 'ld_course_chapters.parent_id' => $value['id'],'ld_course_chapters.course_id' => $course_id])->get()->toArray();
        }
        foreach ($chapters as $k => &$v) {
            //获取用户使用课程时长
            foreach($v['childs'] as $kk => &$vv){
                    $course_id = $vv['course_id'];
                    //获取缓存  判断是否存在
                    if(Redis::get('VisitorList')){
                    //     //存在
                         $data  = Redis::get('VisitorList');
                    }else{
                        //不存在
                        // TODO:  这里替换欢托的sdk CC 直播的 获取到观众的列表 功能待定
                        $MTCloud = new MTCloud();
                        $VisitorList =  $MTCloud->coursePlaybackVisitorList($course_id,1,100);
                        Redis::set('VisitorList', json_encode($VisitorList));
                        Redis::expire('VisitorList',10);
                        $data  = Redis::get('VisitorList');
                    }
                    $res = json_decode($data,1);
                    if(!empty($res['data'])){
                        $vv['use_duration']  = $res['data'];
                    }else{
                        $vv['use_duration']  = array();
                    }
            }
        }
        foreach($chapters as $k => &$v){
            foreach($v['childs'] as $kk => &$vv){
                if(count($vv['use_duration']) > 0){
                    foreach($vv['use_duration'] as $kkk => $vvv){
                        if($vvv['uid'] == $uid){
                            $vv['use_duration'] = $vvv['duration'];
                        }else{
                            if(is_array($vv['use_duration'])){
                                $vv['use_duration'] = 0;
                            }
                        }
                    }
                }else{
                    $vv['use_duration'] = 0;
                }
            }
        }
        foreach($chapters as $k => &$v){
            foreach($v['childs'] as $k1 => &$vv){
                if($vv['use_duration'] == 0){
                    $vv['use_duration'] = "未学习";
                }else{
                     $vv['use_duration'] =  "已学习".  sprintf("%01.2f", $vv['use_duration']/$vv['mt_duration']*100).'%';
                }
                $seconds = $vv['mt_duration'];
                $hours = intval($seconds/3600);
                $vv['mt_duration'] = $hours.":".gmdate('i:s', $seconds);
            }

        }

        $count = count($chapters);

        $total = $count;
        if($total > 0){
            $arr = array_merge($chapters);
            $start=($page-1)*$pagesize;
            $limit_s=$start+$pagesize;
            $chapters=[];
            for($i=$start;$i<$limit_s;$i++){
                if(!empty($arr[$i])){
                    array_push($chapters,$arr[$i]);
                }
            }
        }else{
            $chapters=[];
        }
        $data = [];
        $data['chapters'] = $chapters;
        $data['count'] = $count;
        return $data;
    }
}
