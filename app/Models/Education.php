<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\Major;
use App\Models\AdminLog;

class Education extends Model {
    //指定别的表名
    public $table      = 'education';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   项目管理-添加院校方法
     * @param  参数说明       body包含以下参数[
     *     parent_id         项目id
     *     child_id          学科id
     *     education_name    院校名称
     *     is_hide           是否显示/隐藏
     * ]
     * @param author    dzj
     * @param ctime     2020-09-04
     * return string
     */
    public static function doInsertEducation($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断分类父级id是否合法
        if(!isset($body['parent_id']) || empty($body['parent_id']) || $body['parent_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }

        //判断分类子级id是否合法
        if(!isset($body['child_id']) || empty($body['child_id']) || $body['child_id'] <= 0){
            return ['code' => 202 , 'msg' => '学科id不合法'];
        }

        //判断院校名称是否为空
        if(!isset($body['education_name']) || empty($body['education_name'])){
            return ['code' => 201 , 'msg' => '请输入院校名称'];
        }

        //判断是否展示是否选择
        if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
            return ['code' => 202 , 'msg' => '展示方式不合法'];
        }

        //判断父级id是否在表中是否存在
        $is_exists_parentId = Project::where('id' , $body['parent_id'])->where('parent_id' , 0)->where('is_del' , 0)->count();
        if(!$is_exists_parentId || $is_exists_parentId <= 0){
            return ['code' => 203 , 'msg' => '此项目名称不存在'];
        }

        //判断子级id是否在表中是否存在
        $is_exists_childId = Project::where('id' , $body['child_id'])->where('parent_id' , $body['parent_id'])->where('is_del' , 0)->count();
        if(!$is_exists_childId || $is_exists_childId <= 0){
            return ['code' => 203 , 'msg' => '此学科名称不存在'];
        }

        //判断院校名称是否存在
        $is_exists = self::where('parent_id' , $body['parent_id'])->where('child_id' , $body['child_id'])->where('education_name' , $body['education_name'])->where('is_del' , 0)->count();
        if($is_exists && $is_exists > 0){
            return ['code' => 203 , 'msg' => '此院校名称已存在'];
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //组装院校数组信息
        $school_array = [
            'parent_id'           =>   isset($body['parent_id']) && $body['parent_id'] > 0 ? $body['parent_id'] : 0 ,
            'child_id'            =>   isset($body['child_id']) && $body['child_id'] > 0 ? $body['child_id'] : 0 ,
            'education_name'      =>   $body['education_name'] ,
            'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
            'admin_id'            =>   $admin_id ,
            'create_time'         =>   date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();
        try {
            //将数据插入到表中
            if(false !== self::insertGetId($school_array)){
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
     * @param  description   项目管理-修改院校方法
     * @param  参数说明       body包含以下参数[
     *     school_id         院校id
     *     education_name    院校名称
     *     is_hide           是否显示/隐藏
     *     is_del            是否删除(是否删除1已删除)
     * ]
     * @param author    dzj
     * @param ctime     2020-09-04
     * return string
     */
    public static function doUpdateEducation($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断院校id是否合法
        if(!isset($body['school_id']) || empty($body['school_id']) || $body['school_id'] <= 0){
            return ['code' => 202 , 'msg' => '院校id不合法'];
        }

        //判断院校名称是否为空
        if(!isset($body['education_name']) || empty($body['education_name'])){
            return ['code' => 201 , 'msg' => '请输入院校名称'];
        }

        //判断是否展示是否选择
        if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
            return ['code' => 202 , 'msg' => '展示方式不合法'];
        }

        //判断此院校得id是否存在此院校
        $is_exists_school = self::where('id' , $body['school_id'])->first();
        if(!$is_exists_school || empty($is_exists_school)){
            return ['code' => 203 , 'msg' => '此院校不存在'];
        }

        //判断院校名称是否存在
        $is_exists = self::where('parent_id' , $is_exists_school['parent_id'])->where('child_id' , $is_exists_school['child_id'])->where('education_name' , $body['education_name'])->where('is_del' , 0)->count();
        if($is_exists && $is_exists > 0){
            //组装院校数组信息
            $school_array = [
                'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                'is_del'              =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                'update_time'         =>   date('Y-m-d H:i:s')
            ];
        } else {
            //组装院校数组信息
            $school_array = [
                'education_name'      =>   $body['education_name'] ,
                'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                'is_del'              =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                'update_time'         =>   date('Y-m-d H:i:s')
            ];
        }

        //开启事务
        DB::beginTransaction();
        try {
            //判断此项目学科是否删除
            if(isset($body['is_del']) && $body['is_del'] == 1){
                //删除院校下面所有得专业
                $major_count = Major::where('education_id' , $body['school_id'])->where('is_del' , 0)->count();
                if($major_count && $major_count > 0){
                    Major::where('education_id' , $body['school_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                }
            }

            //根据院校id更新信息
            if(false !== self::where('id',$body['school_id'])->update($school_array)){
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '修改成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '修改失败'];
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
        }

    }

    /*
     * @param  description   项目管理-院校详情方法
     * @param  参数说明       body包含以下参数[
     *     school_id         院校id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function getSchoolInfoById($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断院校id是否合法
        if(!isset($body['school_id']) || empty($body['school_id']) || $body['school_id'] <= 0){
            return ['code' => 202 , 'msg' => '院校id不合法'];
        }

        //根据id获取院校的详情
        $info = self::select('education_name','is_hide','is_del')->where('id' , $body['school_id'])->where('is_del' , 0)->first();
        if($info && !empty($info)){
            return ['code' => 200 , 'msg' => '获取详情成功' , 'data' => $info];
        } else {
            return ['code' => 203 , 'msg' => '此院校不存在或已删除'];
        }
    }

    /*
     * @param  description   项目管理-院校列表接口
     * @param  参数说明       body包含以下参数[
     *     project_id        项目id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-04
     * return string
     */
    public static function getEducationList($body=[]){
        //判断项目的id是否为空
        if(!isset($body['parent_id']) || $body['parent_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }

        //判断学科id是否传递
        if(!isset($body['child_id']) || $body['child_id'] <= 0){
            //通过项目的id获取院校列表
            $school_list = self::select('id as school_id' , 'education_name' , 'education_name as label' , 'id as value')->where('parent_id' , $body['parent_id'])->where('is_del' , 0)->get();
        } else {
            //通过项目的id获取院校列表
            $school_list = self::select('id as school_id' , 'education_name' , 'education_name as label' , 'id as value')->where('parent_id' , $body['parent_id'])->where('child_id' , $body['child_id'])->where('is_del' , 0)->get();
        }
        return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => $school_list];
    }

    /*
     * @param  description   项目管理-学历成本所有项目列表接口
     * @param author    dzj
     * @param ctime     2020-09-19
     * return string
     */
    public static function getEducationProjectList(){
        //获取地区下面所有的项目列表
        $project_list = self::selectRaw("any_value(parent_id) as id")->where('is_del' , 0)->groupBy("parent_id")->get()->toArray();
        //判断是否为空
        if($project_list && !empty($project_list)){
            //空数组赋值
            $arr = [];
            foreach($project_list as $k=>$v){
                //通过项目id获取学科id
                $subject_ids  = self::selectRaw("any_value(child_id) as id")->where('parent_id' , $v['id'])->groupBy("child_id")->get()->toArray();
                $subject_id_list = array_column($subject_ids, 'id');

                //根据项目id获取项目信息
                $project_info = Project::where('id' , $v['id'])->where('parent_id' , 0)->first();

                //获取学科得列表
                $subject_list = Project::select('id','name','id as value','name as label','is_del','is_hide')->where('parent_id' , $v['id'])->whereIn('id' , $subject_id_list)->where('is_del' , 0)->orderByDesc('create_time')->get()->toArray();
                if($subject_list && !empty($subject_list)){
                    //根据项目得id获取学科得列表
                    $subject_list = $subject_list && !empty($subject_list) ? $subject_list : [];
                } else {
                    $subject_list = [] ;
                }

                //数组赋值
                $arr[] = [
                    'id'      =>   $v['id'] ,
                    'name'    =>   $project_info['name'] ,
                    'label'   =>   $project_info['name'] ,
                    'value'   =>   $v['id'] ,
                    'is_del'  =>   $project_info['is_del'] ,
                    'is_hide' =>   $project_info['is_hide'] ,
                    'subject_list' => $subject_list ,
                ];
            }
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => $arr];
        } else {
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => []];
        }
    }
}
