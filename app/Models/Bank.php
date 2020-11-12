<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use Validator;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionSubject as Subject;
use App\Models\Papers;
use App\Models\Exam;
use App\Models\CourseRefBank;
use Illuminate\Support\Facades\Redis;

class Bank extends Model {
    //指定别的表名
    public $table      = 'ld_question_bank';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   添加题库方法
     * @param  data          数组数据
     * @param  author        dzj
     * @param  ctime         2020-05-06
     * return  int
     */
    public static function insertBank($data) {
        return self::insertGetId($data);
    }

    /*
     * @param  descriptsion    获取题库列表
     * @param  author          dzj
     * @param  ctime           2020-05-06
     * return  array
     */
    public static function getBankList($body=[]) {
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 15;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;

        //判断登录的后台用户是否是总校状态
        if($school_status > 0 && $school_status == 1){
            //获取题库的总数量
            $bank_count = self::where('is_del' , '=' , 0)->where('school_id' , '=' , $school_id)->count();
            if($bank_count > 0){
                //获取题库列表
                $bank_list = self::select('id as bank_id','topic_name','describe','is_open')->withCount(['subjectToBank as subject_count' => function($query) {
                    $query->where('is_del' , '=' , 0);
                } , 'papersToBank as papers_count' => function($query) {
                    $query->where('is_del' , '=' , 0);
                } , 'examToBank as exam_count' => function($query) {
                    $query->where('is_del' , '=' , 0);
                }])->where(function($query) use ($body){
                    //删除状态
                    $query->where('is_del' , '=' , 0);

                    //获取后端的操作员id
                    $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

                    //操作员id
                    $query->where('school_id' , '=' , $school_id);
                })->orderByDesc('create_at')->offset($offset)->limit($pagesize)->get();
                return ['code' => 200 , 'msg' => '获取题库列表成功' , 'data' => ['bank_list' => $bank_list , 'total' => $bank_count , 'pagesize' => $pagesize , 'page' => $page]];
            }
            return ['code' => 200 , 'msg' => '获取题库列表成功' , 'data' => ['bank_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
        } else {
            //自增的题库列表
            $bank_list = self::select('id as bank_id','topic_name','describe','is_open')->withCount(['subjectToBank as subject_count' => function($query) {
                $query->where('is_del' , '=' , 0);
            } , 'papersToBank as papers_count' => function($query) {
                $query->where('is_del' , '=' , 0);
            } , 'examToBank as exam_count' => function($query) {
                $query->where('is_del' , '=' , 0);
            }])->where(function($query) use ($body){
                //删除状态
                $query->where('is_del' , '=' , 0);

                //获取后端的操作员id
                $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

                //操作员id
                $query->where('school_id' , '=' , $school_id);
            })->orderByDesc('create_at')->get()->toArray();

            $arr = [];

            //授权的题库列表
            $bank_list2 = DB::table('ld_course_ref_bank')->where('to_school_id' , $school_id)->where('is_del' , 0)->orderByDesc('create_at')->get()->toArray();
            foreach($bank_list2 as $k=>$v){
                //通过题库的id获取题库详情
                $bank_info = Bank::where('id' , $v->bank_id)->first();

                //科目数量
                $subject_count  =  Subject::where('bank_id' , $v->bank_id)->where('is_del' , 0)->where('subject_name' , '!=' , "")->count();
                //试卷数量
                $papers_count   =  Papers::where('bank_id' , $v->bank_id)->where('is_del' , 0)->count();
                //科目数量
                $exam_count     =  Exam::where('bank_id' , $v->bank_id)->where('is_del' , 0)->count();

                $arr[] = [
                    'bank_id'       =>    $v->bank_id ,
                    'topic_name'    =>    $bank_info['topic_name'] ,
                    'describe'      =>    $bank_info['describe'] ,
                    'is_open'       =>    $bank_info['is_open'] ,
                    'subject_count' =>    $subject_count ,
                    'papers_count'  =>    $papers_count ,
                    'exam_count'    =>    $exam_count
                ];
            }

            //获取总条数
            $bank_sum_array = array_merge((array)$bank_list , (array)$arr);

            $count = count($bank_sum_array);//总条数
            $array = array_slice($bank_sum_array,$offset,$pagesize);
            return ['code' => 200 , 'msg' => '获取题库列表成功' , 'data' => ['bank_list' => $array , 'total' => $count , 'pagesize' => (int)$pagesize , 'page' => (int)$page]];
        }
    }

    /*
     * @param  descriptsion    判断是否授权题库
     * @param  author          dzj
     * @param  ctime           2020-07-14
     * return  array
     */
    public static function getBankIsAuth($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断题库id是否合法
        if(!isset($body['bank_id']) || empty($body['bank_id']) || $body['bank_id'] <= 0){
            return ['code' => 202 , 'msg' => '题库id不合法'];
        }

        //学校id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

        //判断此题库是否授权
        $is_auth = CourseRefBank::where('to_school_id' , $school_id)->where('bank_id' , $body['bank_id'])->where('is_del' , 0)->count();
        if($is_auth <= 0){
            return ['code' => 200 , 'msg' => '此题库未授权' , 'data' => $body['bank_id']];
        } else {
            return ['code' => 203 , 'msg' => '此题库已授权' , 'data' => $body['bank_id']];
        }
    }

    /*
     * @param  descriptsion    根据题库id获取题库详情信息
     * @param  参数说明         body包含以下参数[
     *     bank_id   题库id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-06
     * return  array
     */
    public static function getBankInfoById($body=[]) {
        //规则结构
        $rule = [
            'bank_id'   =>   'bail|required|min:1'
        ];

        //信息提示
        $message = [
            'bank_id.required'    =>  json_encode(['code'=>201,'msg'=>'题库id为空']) ,
            'bank_id.min'         =>  json_encode(['code'=>202,'msg'=>'题库id不合法']) ,
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //key赋值
        $key = 'bank:bankinfo:'.$body['bank_id'];

        //判断此题库是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此题库不存在'];
        } else {
            //判断此题库在题库表中是否存在
            $bank_count = self::where('id',$body['bank_id'])->count();
            if($bank_count <= 0){
                //存储题库的id值并且保存60s
                Redis::setex($key , 60 , $body['bank_id']);
                return ['code' => 204 , 'msg' => '此题库不存在'];
            }
        }

        //根据id获取题库详细信息
        $bank_info = self::where('id',$body['bank_id'])->select('topic_name','parent_id','child_id','describe','subject_id')->first()->toArray();

        //根据科目id获取科目信息
        //$subject_list = Subject::select('id','subject_name')->whereIn('id' , explode(',' , $bank_info['subject_id']))->where('subject_name' , '!=' , "")->where('is_del' , 0)->get()->toArray();
        $subject_list = Subject::select('id','subject_name')->where('bank_id' , $body['bank_id'])->where('subject_name' , '!=' , "")->where('is_del' , 0)->get()->toArray();
        if($subject_list && !empty($subject_list)){
            foreach($subject_list as $k=>$v){
                $subject_list[$k]['disabled'] = true;
            }
        }
        //科目列表赋值
        $bank_info['subject_list']    = $subject_list && !empty($subject_list) ? $subject_list : [];
        if($bank_info['child_id'] && $bank_info['child_id'] > 0){
            $bank_info['lession_subject'] = [$bank_info['parent_id'],$bank_info['child_id']];
        } else {
            $bank_info['lession_subject'] = [$bank_info['parent_id']];
        }
        return ['code' => 200 , 'msg' => '获取题库信息成功' , 'data' => $bank_info];
    }

    /*
     * @param  descriptsion    更改题库的方法[二期功能]
     * @param  参数说明         body包含以下参数[
     *     bank_id     题库id
     *     topic_name  题库名称
     *     subject_id  科目id
     *     parent_id   一级分类id
     *     child_id    二级分类id
     *     describe    题库描述
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-06
     * return  array
     */
    /*public static function doUpdateBank($body=[]){
        //规则结构
        $rule = [
            'bank_id'      =>   'bail|required|numeric|min:1' ,
            'topic_name'   =>   'bail|required' ,
            'subject_id'   =>   'bail|required' ,
            'parent_id'    =>   'bail|required|numeric|min:1' ,
            'child_id'     =>   'bail|required|numeric|min:1' ,
            'describe'     =>   'bail|required'
        ];

        //信息提示
        $message = [
            'bank_id.required'      =>  json_encode(['code'=>201,'msg'=>'题库id为空']) ,
            'bank_id.min'           =>  json_encode(['code'=>202,'msg'=>'题库id不合法']) ,
            'topic_name.required'   =>  json_encode(['code'=>201,'msg'=>'请输入题库名称']) ,
            'subject_id.required'   =>  json_encode(['code'=>201,'msg'=>'请添加科目']) ,
            'parent_id.required'    =>  json_encode(['code'=>201,'msg'=>'请选择一级分类']) ,
            'parent_id.min'         =>  json_encode(['code'=>202,'msg'=>'一级分类id不合法']) ,
            'child_id.required'     =>  json_encode(['code'=>201,'msg'=>'请选择二级分类']) ,
            'child_id.min'          =>  json_encode(['code'=>202,'msg'=>'二级分类id不合法']) ,
            'describe.required'     =>  json_encode(['code'=>201,'msg'=>'请输入题库描述']) ,
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //键赋值
        $key = 'admin:'.$admin_id.':bank:'.$body['bank_id'];

        //追加到list列表中【方便后期hash获取】
        Redis::rpush('bank_list' , $key);

        //存储题库信息到redis中
        Redis::hMset($key , $body);

        $aaa = Redis::hMset($key , $body);
        if(Redis::exists($key)) {
            $bbb = Redis::hMget($key , array('topic_name'));
        echo "<pre>";
        print_r($bbb);
        }
    }*/


    /*
     * @param  descriptsion    更新题库列表的方法[二期功能]
     * @param  author          dzj
     * @param  ctime           2020-05-06
     * return  array
     */
    /*public static function doUpdateBankList(){
        //存储当前题库更新的时间
        $current_time_length = Redis::strlen('bank_update_time');
        if($current_time_length > 0){
            $current_time = Redis::get('bank_update_time');
        } else {
            //获取当前服务器时间
            $current_time = date('Y-m-d H:i');
            Redis::set('bank_update_time' , $current_time);
        }

        //获取更新时间
        $update_time = date('Y-m-d H:i:s');

        //返回题库列表的长度
        $bank_length = Redis::lLen('bank_list');

        //判断列表长度是否大于0
        if($bank_length > 0){
            //获取所有需要更新的题库数据
            $bank_list = Redis::lrange('bank_list' , 0 , -1);

            //空数组赋值
            $arr = [];

            //循环获取列表的key值
            foreach($bank_list as $k=>$v){
                //通过hash的key值获取题库的数据
                $bank_info = Redis::hMget($v , ['bank_id' , 'topic_name' , 'subject_id' , 'parent_id' , 'child_id' , 'describe']);

                //题库的id
                $bank_id = $bank_info[0];

                //重新组装成数组
                $arr_field = [
                    'topic_name' =>  $bank_info[1] ,   //题库的名称
                    'subject_id' =>  $bank_info[2] ,   //科目的id(多个逗号分隔)
                    'parent_id'  =>  $bank_info[3] ,   //一级分类的id
                    'child_id'   =>  $bank_info[4] ,   //二级分类的id
                    'describe'   =>  $bank_info[5] ,   //题库的描述
                    'update_at'  =>  $update_time
                ];

                //更新题库信息
                if(false !== self::where('id',$bank_id)->update($arr_field)){
                    //更新题库科目的所属题库id
                    Subject::doUpdateBankIds(['bank_id' => $bank_id , 'subject_ids' => $arr_field['subject_id'] , 'update_at' => $update_time]);

                    //添加日志操作
                    AdminLog::insertAdminLog([
                        'admin_id'       =>   $admin_id  ,
                        'module_name'    =>  'Question' ,
                        'route_url'      =>  'admin/question/doUpdateBankList' ,
                        'operate_method' =>  'update' ,
                        'content'        =>  json_encode($arr_field) ,
                        'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                        'create_at'      =>  $update_time
                    ]);

                    //从redis中删除hash数据
                    Redis::del($v);

                    //从redis中删除题库list数据
                    Redis::del('bank_list');
                }
            }
        }
        //获取最新更新题库时间
        Redis::set('bank_update_time' , $update_time);
        return ['code' => 200 , 'msg' => '更新成功'];
    }*/


    /*
     * @param  descriptsion    更改题库的方法
     * @param  参数说明         body包含以下参数[
     *     bank_id     题库id
     *     topic_name  题库名称
     *     subject_id  科目id
     *     parent_id   一级分类id
     *     child_id    二级分类id
     *     describe    题库描述
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-06
     * return  array
     */
    public static function doUpdateBank($body=[]){
        //规则结构
        $rule = [
            'bank_id'      =>   'bail|required|numeric|min:1' ,
            'topic_name'   =>   'bail|required' ,
            'parent_id'    =>   'bail|required|numeric|min:1' ,
            //'child_id'     =>   'bail|required|numeric|min:1' ,
            'describe'     =>   'bail|required'
        ];

        //信息提示
        $message = [
            'bank_id.required'      =>  json_encode(['code'=>201,'msg'=>'题库id为空']) ,
            'bank_id.min'           =>  json_encode(['code'=>202,'msg'=>'题库id不合法']) ,
            'topic_name.required'   =>  json_encode(['code'=>201,'msg'=>'请输入题库名称']) ,
            'parent_id.required'    =>  json_encode(['code'=>201,'msg'=>'请选择一级分类']) ,
            'parent_id.min'         =>  json_encode(['code'=>202,'msg'=>'一级分类id不合法']) ,
            //'child_id.required'     =>  json_encode(['code'=>201,'msg'=>'请选择二级分类']) ,
            //'child_id.min'          =>  json_encode(['code'=>202,'msg'=>'二级分类id不合法']) ,
            'describe.required'     =>  json_encode(['code'=>201,'msg'=>'请输入题库描述']) ,
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //key赋值
        $key = 'bank:update:'.$body['bank_id'];

        //判断此题库是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此题库不存在'];
        } else {
            //判断此题库在题库表中是否存在
            $bank_count = self::where('id',$body['bank_id'])->count();
            if($bank_count <= 0){
                //存储题库的id值并且保存60s
                Redis::setex($key , 60 , $body['bank_id']);
                return ['code' => 204 , 'msg' => '此题库不存在'];
            }
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //数据数组组装
        $array_bank = [
            'topic_name'   =>  $body['topic_name'] ,
            'parent_id'    =>  $body['parent_id'] ,
            'child_id'     =>  isset($body['child_id']) && $body['child_id'] > 0 ? $body['child_id'] : 0 ,
            'describe'     =>  $body['describe'] ,
            'update_at'    =>  date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();
        try {
            //根据题库id更新信息
            if(false !== self::where('id',$body['bank_id'])->update($array_bank)){
                //判断是否传递科目列表
                if(isset($body['subject_list']) && !empty($body['subject_list'])){
                    $array_bank['bank_id']       = $body['bank_id'];
                    $array_bank['subject_list']  = $body['subject_list'];
                    $array_bank['is_insert']     = 2;

                    //更新题库科目的所属题库id
                    Subject::doUpdateBankIds($array_bank);
                }

                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Question' ,
                    'route_url'      =>  'admin/question/doUpdateBank' ,
                    'operate_method' =>  'update' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
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
     * @param  description   增加题库的方法
     * @param  参数说明         body包含以下参数[
     *     topic_name  题库名称
     *     subject_id  科目id
     *     parent_id   一级分类id
     *     child_id    二级分类id
     *     describe    题库描述
     * ]
     * @param author    dzj
     * @param ctime     2020-04-29
     * return string
     */
    public static function doInsertBank($body=[]){
        //规则结构
        $rule = [
            'topic_name'   =>   'bail|required' ,
            'subject_list' =>   'bail|required' ,
            'parent_id'    =>   'bail|required|numeric|min:1' ,
            //'child_id'     =>   'bail|required|numeric|min:1' ,
            'describe'     =>   'bail|required'
        ];

        //信息提示
        $message = [
            'topic_name.required'   =>  json_encode(['code'=>201,'msg'=>'请输入题库名称']) ,
            'subject_list.required' =>  json_encode(['code'=>201,'msg'=>'请添加科目']) ,
            'parent_id.required'    =>  json_encode(['code'=>201,'msg'=>'请选择一级分类']) ,
            'parent_id.min'         =>  json_encode(['code'=>202,'msg'=>'一级分类id不合法']) ,
            //'child_id.required'     =>  json_encode(['code'=>201,'msg'=>'请选择二级分类']) ,
            //'child_id.min'          =>  json_encode(['code'=>202,'msg'=>'二级分类id不合法']) ,
            'describe.required'     =>  json_encode(['code'=>201,'msg'=>'请输入题库描述']) ,
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        $school_id= isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

        //数据数组组装
        $array_bank = [
            'topic_name'   =>  $body['topic_name'] ,
            'parent_id'    =>  $body['parent_id'] ,
            'child_id'     =>  isset($body['child_id']) && $body['child_id'] > 0 ? $body['child_id'] : 0 ,
            'describe'     =>  $body['describe'] ,
            'admin_id'     =>  $admin_id ,
            'school_id'    =>  $school_id ,
            'create_at'    =>  date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();
        try {
            //将数据插入到表中
            $bank_id = self::insertGetId($array_bank);
            if($bank_id && $bank_id > 0){
                //更新题库科目的所属题库id
                $body['bank_id']   = $bank_id;
                $body['is_insert'] = 1;
                Subject::doUpdateBankIds($body);

                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Question' ,
                    'route_url'      =>  'admin/question/doInsertBank' ,
                    'operate_method' =>  'insert' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
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
     * @param  descriptsion    删除题库的方法
     * @param  参数说明         body包含以下参数[
     *      bank_id   题库id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-29
     * return  array
     */
    public static function doDeleteBank($body=[]) {
        //规则结构
        $rule = [
            'bank_id'   =>   'bail|required|min:1'
        ];

        //信息提示
        $message = [
            'bank_id.required'    =>  json_encode(['code'=>201,'msg'=>'题库id为空']) ,
            'bank_id.min'         =>  json_encode(['code'=>202,'msg'=>'题库id不合法']) ,
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //key赋值
        $key = 'bank:delete:'.$body['bank_id'];

        //判断此题库是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此题库不存在'];
        } else {
            //判断此题库在题库表中是否存在
            $bank_count = self::where('id',$body['bank_id'])->count();
            if($bank_count <= 0){
                //存储题库的id值并且保存60s
                Redis::setex($key , 60 , $body['bank_id']);
                return ['code' => 204 , 'msg' => '此题库不存在'];
            }
        }

        //追加更新时间
        $data = [
            'is_del'     => 1 ,
            'update_at'  => date('Y-m-d H:i:s')
        ];

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //开启事务
        DB::beginTransaction();
        try {
            //根据题库id更新删除状态
            if(false !== self::where('id',$body['bank_id'])->update($data)){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Question' ,
                    'route_url'      =>  'admin/question/doDeleteBank' ,
                    'operate_method' =>  'delete' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '删除成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '删除失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }
    }

    /*
     * @param  descriptsion    题库开启/关闭的方法
     * @param  参数说明         body包含以下参数[
     *      bank_id   题库id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-06
     * return  array
     */
    public static function doOpenCloseBank($body=[]) {
        //规则结构
        $rule = [
            'bank_id'   =>   'bail|required|min:1'
        ];

        //信息提示
        $message = [
            'bank_id.required'    =>  json_encode(['code'=>201,'msg'=>'题库id为空']) ,
            'bank_id.min'         =>  json_encode(['code'=>202,'msg'=>'题库id不合法']) ,
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //key赋值
        $key = 'bank:open:'.$body['bank_id'];

        //判断此题库是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此题库不存在'];
        } else {
            //判断此题库在题库表中是否存在
            $bank_count = self::where('id',$body['bank_id'])->count();
            if($bank_count <= 0){
                //存储题库的id值并且保存60s
                Redis::setex($key , 60 , $body['bank_id']);
                return ['code' => 204 , 'msg' => '此题库不存在'];
            }
        }

        //根据题库的id获取题库的状态
        $is_open = self::where('id',$body['bank_id'])->pluck('is_open');

        //追加更新时间
        $data = [
            'is_open'    => $is_open[0] > 0 ? 0 : 1 ,
            'update_at'  => date('Y-m-d H:i:s')
        ];

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //开启事务
        DB::beginTransaction();
        try {
            //根据题库id更新题库状态
            if(false !== self::where('id',$body['bank_id'])->update($data)){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Question' ,
                    'route_url'      =>  'admin/question/doOpenCloseBank' ,
                    'operate_method' =>  'update' ,
                    'content'        =>  json_encode($body) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
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
     * @param  descriptsion 获取科目与题库建立的联系
     * @param  author  duzhijian
     * @param  ctime   2020-05-06
     * return  array
     */
    public function subjectToBank() {
        return $this->hasMany('App\Models\QuestionSubject' , 'bank_id' , 'id');
    }

    /*
     * @param  descriptsion 获取试卷与题库建立的联系
     * @param  author  duzhijian
     * @param  ctime   2020-05-06
     * return  array
     */
    public function papersToBank() {
        return $this->hasMany('App\Models\Papers' , 'bank_id' , 'id');
    }

    /*
     * @param  descriptsion 获取试题与题库建立的联系
     * @param  author  duzhijian
     * @param  ctime   2020-05-06
     * return  array
     */
    public function examToBank() {
        return $this->hasMany('App\Models\Exam' , 'bank_id' , 'id');
    }
}
