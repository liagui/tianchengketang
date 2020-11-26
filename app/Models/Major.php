<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\AdminLog;

class Major extends Model {
    //指定别的表名
    public $table      = 'major';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   项目管理-添加专业方法
     * @param  参数说明       body包含以下参数[
     *     education_id        院校id
     *     major_name          专业名称
     *     price               成本价格
     *     is_hide             是否隐藏(1是0否)
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function doInsertMajor($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断院校id是否合法
        if(!isset($body['education_id']) || empty($body['education_id']) || $body['education_id'] <= 0){
            return ['code' => 202 , 'msg' => '院校id不合法'];
        }

        //判断院校名称是否为空
        if(!isset($body['major_name']) || empty($body['major_name'])){
            return ['code' => 201 , 'msg' => '请输入专业名称'];
        }

        //判断成本价格是否为空
        if(!isset($body['price'])){
            return ['code' => 201 , 'msg' => '请输入成本价格'];
        }

        //判断是否展示是否选择
        if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
            return ['code' => 202 , 'msg' => '展示方式不合法'];
        }

        //判断院校名称是否存在
        $is_exists = self::where('education_id' , $body['education_id'])->where('major_name' , $body['major_name'])->where('is_del' , 0)->count();
        if($is_exists && $is_exists > 0){
            return ['code' => 203 , 'msg' => '此专业名称已存在'];
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //组装数组信息
        $major_array = [
            'education_id'        =>   isset($body['education_id']) && $body['education_id'] > 0 ? $body['education_id'] : 0 ,
            'major_name'          =>   $body['major_name'] ,
            'price'               =>   $body['price'] ,
            'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
            'admin_id'            =>   $admin_id ,
            'create_time'         =>   date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();
        try {
            //将数据插入到表中
            if(false !== self::insertGetId($major_array)){
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
     * @param  description   项目管理-修改专业方法
     * @param  参数说明       body包含以下参数[
     *     education_id        院校id
     *     major_name          专业名称
     *     price               成本价格
     *     is_hide             是否隐藏(1是0否)
     *     is_del              是否删除(1是)
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function doUpdateMajor($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断专业id是否合法
        if(!isset($body['major_id']) || empty($body['major_id']) || $body['major_id'] <= 0){
            return ['code' => 202 , 'msg' => '专业id不合法'];
        }

        //判断专业名称是否为空
        if(!isset($body['major_name']) || empty($body['major_name'])){
            return ['code' => 201 , 'msg' => '请输入专业名称'];
        }

        //判断成本价格是否为空
        if(!isset($body['price'])){
            return ['code' => 201 , 'msg' => '请输入成本价格'];
        }

        //判断是否展示是否选择
        if(isset($body['is_hide']) && !in_array($body['is_hide'] , [0,1])){
            return ['code' => 202 , 'msg' => '展示方式不合法'];
        }

        //判断此专业得id是否存在此专业
        $is_exists_major = self::where('id' , $body['major_id'])->first();
        if(!$is_exists_major || empty($is_exists_major)){
            return ['code' => 203 , 'msg' => '此专业不存在'];
        }

        //判断专业名称是否存在
        $is_exists = self::where('education_id' , $is_exists_major['education_id'])->where('major_name' , $body['major_name'])->where('is_del' , 0)->count();
        if($is_exists && $is_exists > 0){
            //组装专业数组信息
            $major_array = [
                'price'               =>   $body['price'] ,
                'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                'is_del'              =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                'update_time'         =>   date('Y-m-d H:i:s')
            ];
        } else {
            //组装专业数组信息
            $major_array = [
                'major_name'          =>   $body['major_name'] ,
                'price'               =>   $body['price'] ,
                'is_hide'             =>   isset($body['is_hide']) && $body['is_hide'] == 1 ? 1 : 0 ,
                'is_del'              =>   isset($body['is_del']) && $body['is_del'] == 1 ? 1 : 0 ,
                'update_time'         =>   date('Y-m-d H:i:s')
            ];
        }

        //开启事务
        DB::beginTransaction();
        try {
            //根据专业id更新信息
            if(false !== self::where('id',$body['major_id'])->update($major_array)){
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
     * @param  description   项目管理-专业详情方法
     * @param  参数说明       body包含以下参数[
     *     major_id          专业id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function getNajorInfoById($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断专业id是否合法
        if(!isset($body['major_id']) || empty($body['major_id']) || $body['major_id'] <= 0){
            return ['code' => 202 , 'msg' => '地区id不合法'];
        }

        //根据id获取专业的详情
        $info = self::select('major_name','price','is_hide','is_del')->where('id' , $body['major_id'])->where('is_del' , 0)->first();
        if($info && !empty($info)){
            return ['code' => 200 , 'msg' => '获取详情成功' , 'data' => $info];
        } else {
            return ['code' => 203 , 'msg' => '此专业不存在或已删除'];
        }
    }

    /*
     * @param  description   项目管理-专业列表接口
     * @param  参数说明       body包含以下参数[
     *     education_id         院校id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-07
     * return string
     */
    public static function getMajorList($body=[]){
        //判断院校的id是否为空
        if(!isset($body['education_id']) || $body['education_id'] <= 0){
            return ['code' => 202 , 'msg' => '院校id不合法'];
        }

        //通过院校的id获取专业列表
        $major_list = self::select('id as major_id' , 'major_name' , 'major_name as label' , 'id as value')->where('education_id' , $body['education_id'])->where('is_del' , 0)->get();
        return ['code' => 200 , 'msg' => '获取专业列表成功' , 'data' => $major_list];
    }
}
