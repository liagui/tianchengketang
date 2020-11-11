<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\AdminLog;

class StudentCourse extends Model {
    //指定别的表名
    public $table      = 'student_course';
    //时间戳设置
    public $timestamps = false;

    /*
     * @param  description   开课管理-确认开课方法
     * @param  参数说明       body包含以下参数[
     *     student_id        学员id
     *     course_id         课程id
     * ]
     * @param author    dzj
     * @param ctime     2020-09-08
     * return string
     */
    public static function doUpdateCourseStatus($body=[]){
        //判断传过来的数组数据是否为空
        if(!$body || !is_array($body)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        //判断学员id是否合法
        if(!isset($body['student_id']) || empty($body['student_id']) || $body['student_id'] <= 0){
            return ['code' => 202 , 'msg' => '学员id不合法'];
        }

        //判断课程id是否合法
        if(!isset($body['course_id']) || empty($body['course_id']) || $body['course_id'] <= 0){
            return ['code' => 202 , 'msg' => '课程id不合法'];
        }

        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

        //判断学员id和课程id判断是否报名了
        $is_exists = self::where('student_id' , $body['student_id'])->where('course_id' , $body['course_id'])->first();
        if($is_exists && !empty($is_exists)){
            //开课状态
            $status = $is_exists['status'] > 0 ? 0 : 1;
            //组装数组信息
            $array = [
                'status'              =>   $status ,
                'update_time'         =>   date('Y-m-d H:i:s')
            ];

            //开启事务
            DB::beginTransaction();
            try {
                //根据id更新信息
                if(false !== self::where('student_id' , $body['student_id'])->where('course_id' , $body['course_id'])->update($array)){
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

        } else {
            //组装数组信息
            $array = [
                'student_id'          =>   $body['student_id'] ,
                'course_id'           =>   $body['course_id'] ,
                'status'              =>   1 ,
                'create_id'           =>   $admin_id ,
                'create_time'         =>   date('Y-m-d H:i:s')
            ];

            //开启事务
            DB::beginTransaction();
            try {

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

            } catch (\Exception $ex) {
                DB::rollBack();
                return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
            }
        }
    }
}
