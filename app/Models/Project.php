<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\Course;
use App\Models\RegionFee;
use App\Models\CategoryRegion;
use App\Models\CategoryEducation;
use App\Models\Education;
use App\Models\AdminLog;

class Project extends Model {
    //指定别的表名
    public $table      = 'category';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   项目管理-添加项目/学科方法
     * @param  参数说明       body包含以下参数[
     *     project_id        项目id
     *     name              项目/学科名称
     *     is_hide           是否显示/隐藏
     * ]
     * @param author    dzj
     * @param ctime     2020-09-02
     * return string
     */
    public static function doInsertProjectSubject($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断项目id是否合法
        if(isset($body['project_id']) && $body['project_id'] > 0){
            //判断项目/学科名称是否为空
            if(!isset($body['name']) || empty($body['name'])){
                return ['code' => 201 , 'msg' => '请输入学科名称'];
            }

            //判断是否展示是否选择
            if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
                return ['code' => 202 , 'msg' => '展示方式不合法'];
            }

            //判断学科名称是否存在
            $is_exists = self::where('name' , $body['name'])->where('parent_id' , $body['project_id'])->where('is_del' , 0)->count();
            if($is_exists && $is_exists > 0){
                return ['code' => 203 , 'msg' => '此学科名称已存在'];
            }
        } else {
            //判断项目/学科名称是否为空
            if(!isset($body['name']) || empty($body['name'])){
                return ['code' => 201 , 'msg' => '请输入项目名称'];
            }

            //判断是否展示是否选择
            if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
                return ['code' => 202 , 'msg' => '展示方式不合法'];
            }

            //判断项目名称是否存在
            $is_exists = self::where('name' , $body['name'])->where('parent_id' , 0)->where('is_del' , 0)->count();
            if($is_exists && $is_exists > 0){
                return ['code' => 203 , 'msg' => '此项目名称已存在'];
            }
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //组装项目数组信息
        $project_array = [
            'parent_id'     =>   isset($body['project_id']) && $body['project_id'] > 0 ? $body['project_id'] : 0 ,
            'name'          =>   $body['name'] ,
            'is_hide'       =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
            'admin_id'      =>   $admin_id ,
            'create_time'   =>   date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();
        try {
            //将数据插入到表中
            if(false !== self::insertGetId($project_array)){
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
     * @param  description   项目管理-修改项目/学科方法
     * @param  参数说明       body包含以下参数[
     *     prosub_id         项目/学科id
     *     name              项目/学科名称
     *     is_hide           是否显示/隐藏(前台隐藏0正常 1隐藏)
     *     is_del            是否删除(是否删除1已删除)
     * ]
     * @param author    dzj
     * @param ctime     2020-09-02
     * return string
     */
    public static function doUpdateProjectSubject($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断项目/学科id是否合法
        if(!isset($body['prosub_id']) || empty($body['prosub_id']) || $body['prosub_id'] <= 0){
            return ['code' => 202 , 'msg' => 'id不合法'];
        }

        //判断项目/学科名称是否为空
        if(!isset($body['name']) || empty($body['name'])){
            return ['code' => 201 , 'msg' => '请输入名称'];
        }

        //判断是否展示是否选择
        if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
            return ['code' => 202 , 'msg' => '展示方式不合法'];
        }

        //根据id获取信息
        $info = self::where('id' , $body['prosub_id'])->first();
        if(!$info || empty($info)){
            return ['code' => 203 , 'msg' => '此信息不存在'];
        }

        //判断是项目还是学科
        if($info['parent_id'] && $info['parent_id'] > 0){
            //判断学科名称是否存在
            $is_exists = self::where('name' , $body['name'])->where('parent_id' , $info['parent_id'])->where('is_del' , 0)->count();
            if($is_exists && $is_exists > 0){
                //组装项目数组信息
                $project_array = [
                    'is_hide'       =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                    'is_del'        =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                    'update_time'   =>   date('Y-m-d H:i:s')
                ];
            } else {
                //组装项目数组信息
                $project_array = [
                    'name'          =>   $body['name'] ,
                    'is_hide'       =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                    'is_del'        =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                    'update_time'   =>   date('Y-m-d H:i:s')
                ];
            }
        } else {
            //判断项目名称是否存在
            $is_exists = self::where('name' , $body['name'])->where('parent_id' , 0)->where('is_del' , 0)->count();
            if($is_exists && $is_exists > 0){
                //组装项目数组信息
                $project_array = [
                    'is_hide'       =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                    'is_del'        =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                    'update_time'   =>   date('Y-m-d H:i:s')
                ];
            } else {
                //组装项目数组信息
                $project_array = [
                    'name'          =>   $body['name'] ,
                    'is_hide'       =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                    'is_del'        =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                    'update_time'   =>   date('Y-m-d H:i:s')
                ];
            }
        }

        //开启事务
        DB::beginTransaction();
        try {
            //判断此项目学科是否删除
            if(isset($body['is_del']) && $body['is_del'] == 1){
                //根据id获取详情
                $parent_id = $info['parent_id'];
                if($parent_id && $parent_id > 0){ //学科
                    //删除学科下面所有得课程
                    $course_count = Course::where('category_one_id' , $parent_id)->where('category_tow_id' , $body['prosub_id'])->where('is_del' , 0)->count();
                    if($course_count && $course_count > 0){
                        Course::where('category_one_id' , $parent_id)->where('category_tow_id' , $body['prosub_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                    }

                    //删除学科下面所有得院校
                    $education_count = Education::where('parent_id' , $parent_id)->where('child_id' , $body['prosub_id'])->where('is_del' , 0)->count();
                    if($education_count && $education_count > 0){
                        Education::where('parent_id' , $parent_id)->where('child_id' , $body['prosub_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                    }
                } else { //项目
                    //删除项目下面所有得学科
                    $subject_count = self::where('parent_id' , $body['prosub_id'])->where('is_del' , 0)->count();
                    if($subject_count && $subject_count > 0){
                        self::where('parent_id' , $body['prosub_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                    }

                    //删除项目下面所有得地区报名费
                    $region_count = RegionFee::where('category_id' , $body['prosub_id'])->where('is_del' , 0)->count();
                    if($region_count && $region_count > 0){
                        RegionFee::where('category_id' , $body['prosub_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                    }

                    //删除项目下面所有得课程
                    $course_count = Course::where('category_one_id' , $body['prosub_id'])->where('is_del' , 0)->count();
                    if($course_count && $course_count > 0){
                        Course::where('category_one_id' , $body['prosub_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                    }

                    //删除学科下面所有得院校
                    $education_count = Education::where('parent_id' , $body['prosub_id'])->where('is_del' , 0)->count();
                    if($education_count && $education_count > 0){
                        Education::where('parent_id' , $body['prosub_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                    }

                    //删除地区关联项目的所有信息
                    $category_region = CategoryRegion::where('parent_id' , $body['prosub_id'])->where('is_del' , 0)->count();
                    if($category_region && $category_region > 0){
                        CategoryRegion::where('parent_id' , $body['prosub_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                    }

                    //删除学历成本项目关联所有信息
                    $category_education = CategoryEducation::where('parent_id' , $body['prosub_id'])->where('is_del' , 0)->count();
                    if($category_education && $category_education > 0){
                        CategoryEducation::where('parent_id' , $body['prosub_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                    }
                }
            }

            //根据项目/学科id更新信息
            if(false !== self::where('id',$body['prosub_id'])->update($project_array)){
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
     * @param  description   项目管理-项目/学科详情方法
     * @param  参数说明       body包含以下参数[
     *     info_id         项目/学科id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function getProjectSubjectInfoById($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断项目/学科id是否合法
        if(!isset($body['info_id']) || empty($body['info_id']) || $body['info_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目或学科id不合法'];
        }

        //根据id获取项目或者学科的详情
        $info = self::select('name','is_hide','is_del')->where('id' , $body['info_id'])->where('is_del' , 0)->first();
        if($info && !empty($info)){
            return ['code' => 200 , 'msg' => '获取详情成功' , 'data' => $info];
        } else {
            return ['code' => 203 , 'msg' => '此项目不存在或已删除'];
        }
    }

    /*
     * @param  description   项目管理-项目筛选学科列表接口
     * @param  参数说明       body包含以下参数[
     *     project_id        项目id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-03
     * return string
     */
    public static function getProjectSubjectList($body=[]) {
        //判断项目名称是否传递
        if(isset($body['project_name']) && !empty($body['project_name'])){
            //项目列表
            $project_list = self::select('id','name','id as value','name as label','is_del','is_hide')->where('name','like','%'.$body['project_name'].'%')->where('parent_id' , 0)->where('is_del' , 0)->orderByDesc('create_time')->get()->toArray();
        } else {
            //项目列表
            $project_list = self::select('id','name','id as value','name as label','is_del','is_hide')->where('parent_id' , 0)->where('is_del' , 0)->orderByDesc('create_time')->get()->toArray();
        }

        //判断是否为空
        if($project_list && !empty($project_list)){
            foreach($project_list as $k=>$v){
                //获取学科得列表
                $subject_list = self::select('id','name','id as value','name as label','is_del','is_hide')->where('parent_id' , $v['id'])->where('is_del' , 0)->orderByDesc('create_time')->get()->toArray();
                if($subject_list && !empty($subject_list)){
                    //根据项目得id获取学科得列表
                    $project_list[$k]['subject_list'] = $subject_list && !empty($subject_list) ? $subject_list : [];
                }
            }
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => $project_list];
        } else {
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => []];
        }
    }

    /*
     * @param  description   项目管理-根据项目id获取学科列表
     * @param  参数说明       body包含以下参数[
     *     project_id        项目id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-03
     * return string
     */
    public static function getSubjectList($body=[]) {
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断项目id是否合法
        if(!isset($body['project_id']) || empty($body['project_id']) || $body['project_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }

        //学科列表
        $subject_list = self::select('id','name','id as value','name as label','is_del','is_hide')->where('parent_id' , $body['project_id'])->where('is_del' , 0)->orderByDesc('create_time')->get()->toArray();

        //判断是否为空
        if($subject_list && !empty($subject_list)){
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => $subject_list];
        } else {
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => []];
        }
    }
}
