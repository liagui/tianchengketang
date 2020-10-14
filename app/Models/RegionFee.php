<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\AdminLog;
use App\Models\Project;

class RegionFee extends Model {
    //指定别的表名
    public $table      = 'region_registration_fee';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   项目管理-添加地区方法
     * @param  参数说明       body包含以下参数[
     *     project_id        项目id
     *     region_name       地区名称
     *     cost              报名费价格
     *     is_hide           是否显示/隐藏
     * ]
     * @param author    dzj
     * @param ctime     2020-09-04
     * return string
     */
    public static function doInsertRegion($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }
        
        //判断项目id是否合法
        if(!isset($body['project_id']) || empty($body['project_id']) || $body['project_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }

        //判断地区名称是否为空
        if(!isset($body['region_name']) || empty($body['region_name'])){
            return ['code' => 201 , 'msg' => '请输入地区名称'];
        }
        
        //判断课程价格是否为空
        if(!isset($body['cost'])){
            return ['code' => 201 , 'msg' => '请输入报名价格'];
        }

        //判断是否展示是否选择
        if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
            return ['code' => 202 , 'msg' => '展示方式不合法'];
        }
        
        //判断父级id是否在表中是否存在
        $is_exists_parentId = Project::where('id' , $body['project_id'])->where('parent_id' , 0)->where('is_del' , 0)->count();
        if(!$is_exists_parentId || $is_exists_parentId <= 0){
            return ['code' => 203 , 'msg' => '此项目名称不存在'];
        }

        //判断地区名称是否存在
        $is_exists = self::where('category_id' , $body['project_id'])->where('region_name' , $body['region_name'])->where('is_del' , 0)->count();
        if($is_exists && $is_exists > 0){
            return ['code' => 203 , 'msg' => '此地区名称已存在'];
        }
        
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        
        //组装课程数组信息
        $region_array = [
            'category_id'         =>   isset($body['project_id']) && $body['project_id'] > 0 ? $body['project_id'] : 0 ,
            'region_name'         =>   $body['region_name'] ,
            'cost'                =>   $body['cost'] ,
            'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
            'admin_id'            =>   $admin_id ,
            'create_time'         =>   date('Y-m-d H:i:s')
        ];
        
        //开启事务
        DB::beginTransaction();

        //将数据插入到表中
        if(false !== self::insertGetId($region_array)){
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
     * @param  description   项目管理-修改地区方法
     * @param  参数说明       body包含以下参数[
     *     region_id         地区id
     *     region_name       地区名称
     *     cost              报名费价格
     *     is_hide           是否显示/隐藏
     *     is_del            是否删除
     * ]
     * @param author    dzj
     * @param ctime     2020-09-04
     * return string
     */
    public static function doUpdateRegion($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }
        
        //判断地区id是否合法
        if(!isset($body['region_id']) || empty($body['region_id']) || $body['region_id'] <= 0){
            return ['code' => 202 , 'msg' => '地区id不合法'];
        }

        //判断地区名称是否为空
        if(!isset($body['region_name']) || empty($body['region_name'])){
            return ['code' => 201 , 'msg' => '请输入地区名称'];
        }
        
        //判断报名费价格是否为空
        if(!isset($body['cost'])){
            return ['code' => 201 , 'msg' => '请输入报名费价格'];
        }

        //判断是否展示是否选择
        if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
            return ['code' => 202 , 'msg' => '展示方式不合法'];
        }
        
        //判断此地区得id是否存在此地区
        $is_exists_region = self::where('id' , $body['region_id'])->first();
        if(!$is_exists_region || empty($is_exists_region)){
            return ['code' => 203 , 'msg' => '此地区不存在'];
        }

        //判断地区名称是否存在
        $is_exists = self::where('category_id' , $is_exists_region['category_id'])->where('region_name' , $body['region_name'])->where('is_del' , 0)->count();
        if($is_exists && $is_exists > 0){
            //组装地区数组信息
            $region_array = [
                'cost'                =>   $body['cost'] ,
                'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                'is_del'              =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                'update_time'         =>   date('Y-m-d H:i:s')
            ];
        } else {
            //组装地区数组信息
            $region_array = [
                'region_name'         =>   $body['region_name'] ,
                'cost'                =>   $body['cost'] ,
                'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                'is_del'              =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                'update_time'         =>   date('Y-m-d H:i:s')
            ];
        }
        
        //开启事务
        DB::beginTransaction();

        //根据地区id更新信息
        if(false !== self::where('id',$body['region_id'])->update($region_array)){
            //事务提交
            DB::commit();
            return ['code' => 200 , 'msg' => '修改成功'];
        } else {
            //事务回滚
            DB::rollBack();
            return ['code' => 203 , 'msg' => '修改失败'];
        }
    }
    
    /*
     * @param  description   项目管理-地区报名费详情方法
     * @param  参数说明       body包含以下参数[
     *     region_id         地区id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function getRegionInfoById($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }
        
        //判断地区id是否合法
        if(!isset($body['region_id']) || empty($body['region_id']) || $body['region_id'] <= 0){
            return ['code' => 202 , 'msg' => '地区id不合法'];
        }
        
        //根据id获取地区报名费的详情
        $info = self::select('region_name','cost','is_hide','is_del')->where('id' , $body['region_id'])->where('is_del' , 0)->first();
        if($info && !empty($info)){
            return ['code' => 200 , 'msg' => '获取详情成功' , 'data' => $info];
        } else {
            return ['code' => 203 , 'msg' => '此地区不存在或已删除'];
        }
    }
    
    /*
     * @param  description   项目管理-地区列表接口
     * @param  参数说明       body包含以下参数[
     *     project_id        项目id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-03
     * return string
     */
    public static function getRegionList($body=[]){
        //判断项目的id是否为空
        if(!isset($body['project_id']) || $body['project_id'] <= 0){
            return ['code' => 202 , 'msg' => '项目id不合法'];
        }
        
        //通过项目的id获取地区列表
        $region_list = self::select('id as region_id' , 'region_name' , 'cost' , 'region_name as label' , 'id as value')->where('category_id' , $body['project_id'])->where('is_del' , 0)->get();
        return ['code' => 200 , 'msg' => '获取地区列表成功' , 'data' => $region_list];
    }
    
    /*
     * @param  description   项目管理-地区所有项目列表接口
     * @param author    dzj
     * @param ctime     2020-09-19
     * return string
     */
    public static function getRegionProjectList(){
        //获取地区下面所有的项目列表
        $project_list = self::selectRaw("any_value(category_id) as id")->where('is_del' , 0)->groupBy("category_id")->get()->toArray();
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
