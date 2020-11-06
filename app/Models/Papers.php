<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use App\Models\PapersExam;
use App\Models\Bank;
use App\Models\QuestionSubject;
use App\Models\Region;
use Validator;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class Papers extends Model {
    //指定别的表名
    public $table      = 'ld_question_papers';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   增加试卷的方法
     * @param  参数说明       body包含以下参数[
     *     subject_id      科目id
     *     bank_id         题库id
     *     papers_name     试卷名称
     *     diffculty       试题类型(1代表真题,2代表模拟题,3代表其他)
     *     papers_time     答题时间
     *     area            所属区域
     *     cover_img       封面图片
     *     content         试卷描述
     *     type            选择题型(1代表单选题2代表多选题3代表不定项4代表判断题5填空题6简答题7材料题)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-07
     * return string
     */
    public static function doInsertPapers($body=[]){
        //规则结构
        $rule = [
            'subject_id'     =>   'bail|required|numeric|min:1' ,
            'bank_id'        =>   'bail|required|numeric|min:1' ,
            'papers_name'    =>   'bail|required' ,
            'diffculty'      =>   'bail|required|numeric|between:1,3' ,
            'papers_time'    =>   'bail|required|numeric' ,
            'area'           =>   'bail|required|numeric|min:1' ,
            'cover_img'      =>   'bail|required' ,
            'content'        =>   'bail|required' ,
            'type'           =>   'bail|required'
        ];

        //信息提示
        $message = [
            'subject_id.required'   =>  json_encode(['code'=>201,'msg'=>'科目id为空']) ,
            'subject_id.min'        =>  json_encode(['code'=>202,'msg'=>'科目id不合法']) ,
            'bank_id.required'      =>  json_encode(['code'=>201,'msg'=>'题库id为空']) ,
            'bank_id.min'           =>  json_encode(['code'=>202,'msg'=>'题库id不合法']) ,
            'papers_name.required'  =>  json_encode(['code'=>201,'msg'=>'试卷名称为空']) ,
            'diffculty.required'    =>  json_encode(['code'=>201,'msg'=>'请选择试题类型']) ,
            'diffculty.between'     =>  json_encode(['code'=>202,'msg'=>'试题类型不合法']) ,
            'papers_time.required'  =>  json_encode(['code'=>201,'msg'=>'请输入答题时间']) ,
            'papers_time.numeric'   =>  json_encode(['code'=>202,'msg'=>'答题时间不合法']) ,
            'area.required'         =>  json_encode(['code'=>201,'msg'=>'请选择所属区域']) ,
            'area.min'              =>  json_encode(['code'=>202,'msg'=>'所属区域不合法']) ,
            'cover_img.required'    =>  json_encode(['code'=>201,'msg'=>'请上传封面图片']) ,
            'content.required'      =>  json_encode(['code'=>201,'msg'=>'请输入试卷描述']) ,
            'type.required'         =>  json_encode(['code'=>201,'msg'=>'请选择题型'])
            //'type.between'          =>  json_encode(['code'=>202,'msg'=>'选择题型不合法'])
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //试卷数组信息组装
        $papers_array = [
            'subject_id'    =>   $body['subject_id'] ,
            'bank_id'       =>   $body['bank_id'] ,
            'papers_name'   =>   $body['papers_name'] ,
            'diffculty'     =>   $body['diffculty'] ,
            'papers_time'   =>   $body['papers_time'] ,
            'area'          =>   $body['area'] ,
            'cover_img'     =>   $body['cover_img'] ,
            'content'       =>   $body['content'] ,
            'type'          =>   $body['type'] ,
            'admin_id'      =>   $admin_id ,
            'create_at'     =>   date('Y-m-d H:i:s'),
            'is_publish' => 0
        ];

        //开启事务
        DB::beginTransaction();

        //判断题库id对应的题库是否存在
        $bank_count = Bank::where("id",$body['bank_id'])->where("is_del" , 0)->count();
        if($bank_count <= 0){
            return ['code' => 204 , 'msg' => '此题库信息不存在'];
        }

        //判断科目id对应的科目是否存在
        $bank_count = QuestionSubject::where("id",$body['subject_id'])->where("is_del" , 0)->count();
        if($bank_count <= 0){
            return ['code' => 204 , 'msg' => '此科目信息不存在'];
        }

        //判断地区id对应的地区是否存在
        $area_count = Region::where("id",$body['area'])->count();
        if($area_count <= 0){
            return ['code' => 204 , 'msg' => '此地区不存在'];
        }

        //将数据插入到表中
        $papers_id = self::insertGetId($papers_array);
        if($papers_id && $papers_id > 0){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Question' ,
                'route_url'      =>  'admin/question/doInsertPapers' ,
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
    }

    /*
     * @param  description   更改试卷的方法
     * @param  参数说明       body包含以下参数[
     *     papers_id       试卷id
     *     papers_name     试卷名称
     *     diffculty       试题类型(1代表真题,2代表模拟题,3代表其他)
     *     papers_time     答题时间
     *     area            所属区域
     *     cover_img       封面图片
     *     content         试卷描述
     *     type            选择题型(1代表单选题2代表多选题3代表不定项4代表判断题5填空题6简答题7材料题)
     * ]
     * @param author    dzj
     * @param ctime     2020-05-07
     * return string
     */
    public static function doUpdatePapers($body=[]){
        //规则结构
        $rule = [
            'papers_id'      =>   'bail|required|numeric|min:1' ,
            'papers_name'    =>   'bail|required' ,
            'diffculty'      =>   'bail|required|numeric|between:1,3' ,
            'papers_time'    =>   'bail|required|numeric' ,
            'area'           =>   'bail|required|numeric|min:1' ,
            'cover_img'      =>   'bail|required' ,
            'content'        =>   'bail|required' ,
            'type'           =>   'bail|required'
        ];

        //信息提示
        $message = [
            'papers_id.required'    =>  json_encode(['code'=>201,'msg'=>'试卷id为空']) ,
            'papers_id.min'         =>  json_encode(['code'=>202,'msg'=>'试卷id不合法']) ,
            'papers_name.required'  =>  json_encode(['code'=>201,'msg'=>'试卷名称为空']) ,
            'diffculty.required'    =>  json_encode(['code'=>201,'msg'=>'请选择试题类型']) ,
            'diffculty.between'     =>  json_encode(['code'=>202,'msg'=>'试题类型不合法']) ,
            'papers_time.required'  =>  json_encode(['code'=>201,'msg'=>'请输入答题时间']) ,
            'papers_time.numeric'   =>  json_encode(['code'=>202,'msg'=>'答题时间不合法']) ,
            'area.required'         =>  json_encode(['code'=>201,'msg'=>'请选择所属区域']) ,
            'area.min'              =>  json_encode(['code'=>202,'msg'=>'所属区域不合法']) ,
            'cover_img.required'    =>  json_encode(['code'=>201,'msg'=>'请上传封面图片']) ,
            'content.required'      =>  json_encode(['code'=>201,'msg'=>'请输入试卷描述']) ,
            'type.required'         =>  json_encode(['code'=>201,'msg'=>'请选择题型'])
            //'type.between'          =>  json_encode(['code'=>202,'msg'=>'选择题型不合法'])
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //key赋值
        $key = 'papers:update:'.$body['papers_id'];

        //判断此试卷是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此试卷不存在'];
        } else {
            //判断此试卷在试卷表中是否存在
            $papers_count = self::where('id',$body['papers_id'])->count();
            if($papers_count <= 0){
                //存储试卷的id值并且保存60s
                Redis::setex($key , 60 , $body['papers_id']);
                return ['code' => 204 , 'msg' => '此试卷不存在'];
            }
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //获取试卷id
        $papers_id = $body['papers_id'];

        //试卷数组信息组装
        $papers_array = [
            'papers_name'   =>   $body['papers_name'] ,
            'diffculty'     =>   $body['diffculty'] ,
            'papers_time'   =>   $body['papers_time'] ,
            'area'          =>   $body['area'] ,
            'cover_img'     =>   $body['cover_img'] ,
            'content'       =>   $body['content'] ,
            'type'          =>   $body['type'] ,
            'update_at'     =>   date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();

        //判断地区id对应的地区是否存在
        $area_count = Region::where("id",$body['area'])->count();
        if($area_count <= 0){
            return ['code' => 204 , 'msg' => '此地区不存在'];
        }

        //根据试卷id更新信息
        if(false !== self::where('id',$papers_id)->update($papers_array)){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Question' ,
                'route_url'      =>  'admin/question/doUpdatePapers' ,
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
    }

    /*
     * @param  descriptsion    删除试卷的方法
     * @param  参数说明         body包含以下参数[
     *      papers_id   试卷id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-07
     * return  array
     */
    public static function doDeletePapers($body=[]) {
        //规则结构
        $rule = [
            'papers_id'   =>   'bail|required|min:1'
        ];

        //信息提示
        $message = [
            'papers_id.required'    =>  json_encode(['code'=>201,'msg'=>'试卷id为空']) ,
            'papers_id.min'         =>  json_encode(['code'=>202,'msg'=>'试卷id不合法']) ,
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //key赋值
        $key = 'papers:delete:'.$body['papers_id'];

        //判断此试卷是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此试卷不存在'];
        } else {
            //判断此试卷在试卷表中是否存在
            $papers_count = self::where('id',$body['papers_id'])->count();
            if($papers_count <= 0){
                //存储试卷的id值并且保存60s
                Redis::setex($key , 60 , $body['papers_id']);
                return ['code' => 204 , 'msg' => '此试卷不存在'];
            }
        }

        //追加更新时间
        $data = [
            'is_del'     => 1 ,
            'update_at'  => date('Y-m-d H:i:s')
        ];

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //开启事务
        DB::beginTransaction();

        //根据试卷id更新删除状态
        if(false !== self::where('id',$body['papers_id'])->update($data)){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Question' ,
                'route_url'      =>  'admin/question/doDeletePapers' ,
                'operate_method' =>  'delete' ,
                'content'        =>  json_encode($body) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
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
    }

    /*
     * @param  descriptsion    试卷发布/取消发布的方法
     * @param  参数说明         body包含以下参数[
     *      papers_id   试卷id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-07
     * return  array
     */
    public static function doPublishPapers($body=[]) {
        //规则结构
        $rule = [
            'papers_id'   =>   'bail|required|min:1'
        ];

        //信息提示
        $message = [
            'papers_id.required'    =>  json_encode(['code'=>201,'msg'=>'试卷id为空']) ,
            'papers_id.min'         =>  json_encode(['code'=>202,'msg'=>'试卷id不合法']) ,
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //key赋值
        $key = 'papers:publish:'.$body['papers_id'];

        //判断此试卷是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此试卷不存在'];
        } else {
            //判断此试卷在试卷表中是否存在
            $papers_count = self::where('id',$body['papers_id'])->count();
            if($papers_count <= 0){
                //存储试卷的id值并且保存60s
                Redis::setex($key , 60 , $body['papers_id']);
                return ['code' => 204 , 'msg' => '此试卷不存在'];
            }
        }

        //根据试卷的id获取试卷的状态
        $is_publish = self::where('id',$body['papers_id'])->pluck('is_publish');

        //追加更新时间
        $data = [
            'is_publish' => $is_publish[0] > 0 ? 0 : 1 ,
            'update_at'  => date('Y-m-d H:i:s')
        ];

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //开启事务
        DB::beginTransaction();

        //根据试卷id更新试卷状态
        if(false !== self::where('id',$body['papers_id'])->update($data)){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Question' ,
                'route_url'      =>  'admin/question/doPublishPapers' ,
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
    }


    /*
     * @param  descriptsion    根据试卷id获取试卷详情信息
     * @param  参数说明         body包含以下参数[
     *     papers_id   试卷id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-05-07
     * return  array
     */
    public static function getPapersInfoById($body=[]) {
        //规则结构
        $rule = [
            'papers_id'   =>   'bail|required|min:1'
        ];

        //信息提示
        $message = [
            'papers_id.required'    =>  json_encode(['code'=>201,'msg'=>'试卷id为空']) ,
            'papers_id.min'         =>  json_encode(['code'=>202,'msg'=>'试卷id不合法']) ,
        ];

        $validator = Validator::make($body , $rule , $message);
        if ($validator->fails()) {
            return json_decode($validator->errors()->first() , true);
        }

        //key赋值
        $key = 'papers:papersinfo:'.$body['papers_id'];

        //判断此试卷是否被请求过一次(防止重复请求,且数据信息不存在)
        if(Redis::get($key)){
            return ['code' => 204 , 'msg' => '此试卷不存在'];
        } else {
            //判断此试卷在试卷表中是否存在
            $papers_count = self::where('id',$body['papers_id'])->count();
            if($papers_count <= 0){
                //存储试卷的id值并且保存60s
                Redis::setex($key , 60 , $body['papers_id']);
                return ['code' => 204 , 'msg' => '此试卷不存在'];
            }
        }

        //根据id获取试卷详细信息
        $papers_info = self::select('papers_name','diffculty','papers_time','area','cover_img','content','type')->findOrFail($body['papers_id']);
        return ['code' => 200 , 'msg' => '获取试卷信息成功' , 'data' => $papers_info];
    }

    /*
     * @param  descriptsion    获取试卷列表
     * @param  author          dzj
     * @param  ctime           2020-05-07
     * return  array
     */
    public static function getPapersList($body=[]) {
        //每页显示的条数
        $pagesize = isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 15;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //判断题库的id是否为空
        if(!isset($body['bank_id']) || $body['bank_id'] <= 0){
            return ['code' => 201 , 'msg' => '题库id为空'];
        }

        //获取当前的科目
        if(!isset($body['subject_id']) || empty($body['subject_id'])){
            //获取当前的科目
            //$subject_info = QuestionSubject::select("id as subject_id")->where("admin_id" ,"=" , $admin_id)->where("bank_id" , "=" , $body['bank_id'])->where("is_del" , "=" , 0)->first();
            $subject_info = QuestionSubject::select("id as subject_id")->where("bank_id" , "=" , $body['bank_id'])->where("is_del" , "=" , 0)->first();
            //根据题库id获取第一个科目的id
            if($subject_info && !empty($subject_info)){
                $subject_info = $subject_info->toArray();
                $body['subject_id'] = $subject_info['subject_id'];
            } else {
                $body['subject_id'] = 0;
            }
        }

        //获取试卷的总数量
        //$papers_count = self::where('is_del' , '=' , 0)->where('admin_id' , '=' , $admin_id)->where("subject_id" , "=" , $body['subject_id'])->count();
        $papers_count = self::where('is_del' , '=' , 0)->where(function($query) use ($body){
            //题库的id
            $query->where('bank_id' , '=' , $body['bank_id']);

            //删除状态
            $query->where('is_del' , '=' , 0);

            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

            //获取科目的id
            if(isset($body['subject_id']) && !empty($body['subject_id']) && $body['subject_id'] > 0){
                $query->where('subject_id' , '=' , $body['subject_id']);
            }

            //获取试卷类型
            if(isset($body['diffculty']) && !empty($body['diffculty']) && $body['diffculty'] > 0 && in_array($body['diffculty'] , [1,2,3])){
                $query->where('diffculty' , '=' , $body['diffculty']);
            }

            //获取试卷状态
            if(isset($body['is_publish']) && strlen($body['is_publish']) > 0 && $body['is_publish'] >= 0){
                $is_publish = $body['is_publish'] > 0 ? 1 : 0;
                $query->where('is_publish' , '=' , $is_publish);
            }

            //获取试卷名称
            if(isset($body['papers_name']) && !empty($body['papers_name'])){
                $query->where('papers_name','like','%'.$body['papers_name'].'%');
            }

            //操作员id
            //$query->where('admin_id' , '=' , $admin_id);
        })->count();

        //判断试卷数量是否为空
        if($papers_count > 0){
            //获取试卷列表
            $papers_list = self::select('id as papers_id','papers_name','papers_time','is_publish','signle_score','more_score','judge_score','options_score','pack_score','short_score','material_score','type')->where(function($query) use ($body){
                //题库的id
                $query->where('bank_id' , '=' , $body['bank_id']);

                //删除状态
                $query->where('is_del' , '=' , 0);

                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

                //获取科目的id
                if(isset($body['subject_id']) && !empty($body['subject_id']) && $body['subject_id'] > 0){
                    $query->where('subject_id' , '=' , $body['subject_id']);
                }

                //获取试卷类型
                if(isset($body['diffculty']) && !empty($body['diffculty']) && $body['diffculty'] > 0 && in_array($body['diffculty'] , [1,2,3])){
                    $query->where('diffculty' , '=' , $body['diffculty']);
                }

                //获取试卷状态
                if(isset($body['is_publish']) && strlen($body['is_publish']) > 0 && $body['is_publish'] >= 0){
                    $is_publish = $body['is_publish'] > 0 ? 1 : 0;
                    $query->where('is_publish' , '=' , $is_publish);
                }

                //获取试卷名称
                if(isset($body['papers_name']) && !empty($body['papers_name'])){
                    $query->where('papers_name','like','%'.$body['papers_name'].'%');
                }

                //操作员id
                //$query->where('admin_id' , '=' , $admin_id);
            })->orderByDesc('create_at')->offset($offset)->limit($pagesize)->get()->toArray();

            foreach($papers_list as $k=>$v){
                $consult = [];

                //字符串转数组
                $type_array     = explode(',' , $v['type']);

                //单选题总题数
                if(in_array(1,$type_array)){
                    $signle_count     = PapersExam::leftJoin('ld_question_exam','ld_question_exam.id','=','ld_question_papers_exam.exam_id')
                                        ->where("ld_question_papers_exam.papers_id" , "=" , $v['papers_id'])->where("ld_question_papers_exam.type" , "=" , 1)->where('ld_question_papers_exam.is_del' , '=' , 0)->where('ld_question_exam.is_del' , '=' , 0)->count();
                    if($signle_count > 0){
                        $consult[] = ['type'=>'单选题' , 'count' => $signle_count , 'score' => $v['signle_score'] , 'sum_score' => $v['signle_score'] * $signle_count];
                    } else {
                        $consult[] = ['type'=>'单选题' , 'count' => 0 , 'score' => $v['signle_score'] , 'sum_score' => 0];
                    }
                }

                //多选题总题数
                if(in_array(2,$type_array)){
                    $more_count     = PapersExam::leftJoin('ld_question_exam','ld_question_exam.id','=','ld_question_papers_exam.exam_id')
                        ->where("ld_question_papers_exam.papers_id" , "=" , $v['papers_id'])->where("ld_question_papers_exam.type" , "=" , 2)->where('ld_question_papers_exam.is_del' , '=' , 0)->where('ld_question_exam.is_del' , '=' , 0)->count();
                    if($more_count > 0){
                        $consult[] = ['type'=>'多选题' , 'count' => $more_count , 'score' => $v['more_score'] , 'sum_score' => $v['more_score'] * $more_count];
                    } else {
                        $consult[] = ['type'=>'多选题' , 'count' => 0 , 'score' => $v['more_score'] , 'sum_score' => 0];
                    }
                }

                //不定项总题数
                if(in_array(4,$type_array)){
                    $options_count     = PapersExam::leftJoin('ld_question_exam','ld_question_exam.id','=','ld_question_papers_exam.exam_id')
                        ->where("ld_question_papers_exam.papers_id" , "=" , $v['papers_id'])->where("ld_question_papers_exam.type" , "=" , 4)->where('ld_question_papers_exam.is_del' , '=' , 0)->where('ld_question_exam.is_del' , '=' , 0)->count();
                    if($options_count > 0){
                        $consult[]     = ['type'=>'不定项' , 'count' => $options_count , 'score' => $v['options_score'] , 'sum_score' => $v['options_score'] * $options_count];
                    } else {
                        $consult[]     = ['type'=>'不定项' , 'count' => 0 , 'score' => $v['options_score'] , 'sum_score' => 0];
                    }
                }

                //判断题总题数
                if(in_array(3,$type_array)){
                    $judge_count     = PapersExam::leftJoin('ld_question_exam','ld_question_exam.id','=','ld_question_papers_exam.exam_id')
                        ->where("ld_question_papers_exam.papers_id" , "=" , $v['papers_id'])->where("ld_question_papers_exam.type" , "=" , 4)->where('ld_question_papers_exam.is_del' , '=' , 0)->where('ld_question_exam.is_del' , '=' , 0)->count();
                    if($judge_count > 0){
                        $consult[]     = ['type'=>'判断题' , 'count' => $judge_count , 'score' => $v['judge_score'] , 'sum_score' => $v['judge_score'] * $judge_count];
                    } else {
                        $consult[]     = ['type'=>'判断题' , 'count' => 0 , 'score' => $v['judge_score'] , 'sum_score' => 0];
                    }
                }

                //填空题总题数
                if(in_array(5,$type_array)){
                    $pack_count     = PapersExam::leftJoin('ld_question_exam','ld_question_exam.id','=','ld_question_papers_exam.exam_id')
                        ->where("ld_question_papers_exam.papers_id" , "=" , $v['papers_id'])->where("ld_question_papers_exam.type" , "=" , 4)->where('ld_question_papers_exam.is_del' , '=' , 0)->where('ld_question_exam.is_del' , '=' , 0)->count();
                    if($pack_count > 0){
                        $consult[]     = ['type'=>'填空题' , 'count' => $pack_count , 'score' => $v['pack_score'] , 'sum_score' => $v['pack_score'] * $pack_count];
                    } else {
                        $consult[]     = ['type'=>'填空题' , 'count' => 0 , 'score' => $v['pack_score'] , 'sum_score' => 0];
                    }
                }

                //简答题总题数
                if(in_array(6,$type_array)){
                    $short_count     = PapersExam::leftJoin('ld_question_exam','ld_question_exam.id','=','ld_question_papers_exam.exam_id')
                        ->where("ld_question_papers_exam.papers_id" , "=" , $v['papers_id'])->where("ld_question_papers_exam.type" , "=" , 4)->where('ld_question_papers_exam.is_del' , '=' , 0)->where('ld_question_exam.is_del' , '=' , 0)->count();
                    if($short_count > 0){
                        $consult[]     = ['type'=>'简答题' , 'count' => $short_count , 'score' => $v['short_score'] , 'sum_score' => $v['short_score'] * $short_count];
                    } else {
                        $consult[]     = ['type'=>'简答题' , 'count' => 0 , 'score' => $v['short_score'] , 'sum_score' => 0];
                    }
                }

                //材料总题数
                if(in_array(7,$type_array)){
                    $material_count     = PapersExam::leftJoin('ld_question_exam','ld_question_exam.id','=','ld_question_papers_exam.exam_id')
                        ->where("ld_question_papers_exam.papers_id" , "=" , $v['papers_id'])->where("ld_question_papers_exam.type" , "=" , 4)->where('ld_question_papers_exam.is_del' , '=' , 0)->where('ld_question_exam.is_del' , '=' , 0)->count();
                    if($material_count > 0){
                        $consult[]     = ['type'=>'材料题' , 'count' => $material_count , 'score' => $v['material_score'] , 'sum_score' => $v['material_score'] * $material_count];
                    } else {
                        $consult[]     = ['type'=>'材料题' , 'count' => 0 , 'score' => $v['material_score'] , 'sum_score' => 0];
                    }
                }

                //试卷试题类型赋值
                $papers_list[$k]['exam_list']      = $consult;
                $papers_list[$k]['exam_sum_score'] = array_sum(array_column($papers_list[$k]['exam_list'], 'sum_score'));
            }
            return ['code' => 200 , 'msg' => '获取试卷列表成功' , 'data' => ['papers_list' => $papers_list , 'total' => $papers_count , 'pagesize' => $pagesize , 'page' => $page]];
        }
        return ['code' => 200 , 'msg' => '获取试卷列表成功' , 'data' => ['papers_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page]];
    }
}
