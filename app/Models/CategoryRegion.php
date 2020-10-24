<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\AdminLog;
use App\Models\Project;
use App\Models\RegionFee;

class CategoryRegion extends Model {
    //指定别的表名
    public $table      = 'category_region';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   项目管理-地区关联项目方法
     * @param  参数说明       body包含以下参数[
     *     project_id        项目id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-04
     * return string
     */
    public static function doInsertCategoryRegion($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }
        
        //判断项目id是否合法
        if(!isset($body['project_id']) || empty($body['project_id']) || $body['project_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }
        
        //判断父级id是否在表中是否存在
        $is_exists_parentId = Project::where('id' , $body['project_id'])->where('parent_id' , 0)->where('is_del' , 0)->count();
        if(!$is_exists_parentId || $is_exists_parentId <= 0){
            return ['code' => 203 , 'msg' => '此项目名称不存在'];
        }
        
        //判断此项目在地区是否添加过
        $is_exists_add = self::where('parent_id' , $body['project_id'])->where('is_del' , 0)->count();
        if($is_exists_add && $is_exists_add > 0){
            return ['code' => 203 , 'msg' => '此项目已被添加过'];
        }
        
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        
        //数组信息
        $array = [
            'parent_id'           =>   isset($body['project_id']) && $body['project_id'] > 0 ? $body['project_id'] : 0 ,
            'admin_id'            =>   $admin_id ,
            'create_time'         =>   date('Y-m-d H:i:s')
        ];
        
        //开启事务
        DB::beginTransaction();

        //将数据插入到表中
        if(false !== self::insertGetId($array)){
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
     * @param  description   项目管理-修改地区关联的项目
     * @param  参数说明       body包含以下参数[
     *     project_id        项目id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-04
     * return string
     */
    public static function doUpdateCategoryRegion($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }
        
        //判断的项目id是否合法
        if(!isset($body['pre_project_id']) || empty($body['pre_project_id']) || $body['pre_project_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }
        
        //判断的选中后的项目id是否合法
        if(!isset($body['last_project_id']) || empty($body['last_project_id']) || $body['last_project_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }
        
        //判断此项目在地区是否存在
        $is_exists = self::where('parent_id' , $body['pre_project_id'])->where('is_del' , 0)->count();
        if(!$is_exists || $is_exists <= 0){
            return ['code' => 202 , 'msg' => '此项目不存在'];
        }
        
        //判断父级id是否在表中是否存在
        $is_exists_parentId = Project::where('id' , $body['last_project_id'])->where('parent_id' , 0)->where('is_del' , 0)->count();
        if(!$is_exists_parentId || $is_exists_parentId <= 0){
            return ['code' => 203 , 'msg' => '此项目名称不存在'];
        }
        
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        
        //根据项目的id获取地区的id
        $region_id = RegionFee::select('id')->where('category_id' , $body['pre_project_id'])->get()->toArray();
        $region_ids= array_column($region_id , 'id');
        
        //开启事务
        DB::beginTransaction();
        
        //判断是否删除
        if(isset($body['is_del']) && $body['is_del'] == 1){
            //更新数据信息
            if(false !== self::where('parent_id' , $body['pre_project_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')])){
                RegionFee::where('category_id',$body['pre_project_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '修改成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '修改失败'];
            }
        } else {
            //更新数据信息
            if(false !== RegionFee::whereIn('id',$region_ids)->update(['category_id' => $body['last_project_id'] , 'update_time' => date('Y-m-d H:i:s')])){
                self::where('parent_id' , $body['pre_project_id'])->update(['is_del' => 1 , 'update_time' => date('Y-m-d H:i:s')]);
                $is_exists = self::where('parent_id' , $body['last_project_id'])->where('is_del' , 0)->count();
                if(!$is_exists || $is_exists <= 0){
                    //数组信息
                    $array = [
                        'parent_id'           =>   $body['last_project_id'] ,
                        'admin_id'            =>   $admin_id ,
                        'create_time'         =>   date('Y-m-d H:i:s')
                    ];
                    self::insertGetId($array);
                }
                //事务提交
                DB::commit();
                return ['code' => 200 , 'msg' => '修改成功'];
            } else {
                //事务回滚
                DB::rollBack();
                return ['code' => 203 , 'msg' => '修改失败'];
            }
        }
    }
    
    /*
     * @param  description   项目管理-地区所有项目列表接口
     * @param author    dzj
     * @param ctime     2020-09-19
     * return string
     */
    public static function getRegionProjectList(){
        //获取地区下面所有的项目列表
        $project_list = self::select('parent_id as id')->where('is_del' , 0)->orderByDesc('create_time')->get()->toArray();
        //判断是否为空
        if($project_list && !empty($project_list)){
            //空数组赋值
            $arr = [];
            foreach($project_list as $k=>$v){
                //根据项目id获取项目信息
                $project_info = Project::where('id' , $v['id'])->where('parent_id' , 0)->first();
                
                //获取学科得列表
                $subject_list = Project::select('id','name','id as value','name as label','is_del','is_hide')->where('parent_id' , $v['id'])->where('is_del' , 0)->orderByDesc('create_time')->get()->toArray();
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
                    'subject_list' => $subject_list
                ];
            }
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => $arr];
        } else {
            return ['code' => 200 , 'msg' => '获取列表成功' , 'data' => []];
        }
    }
}
