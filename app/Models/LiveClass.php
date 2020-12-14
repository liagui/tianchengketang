<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Models\CourseMaterial;
use App\Models\LiveChild;
class LiveClass extends Model {

    //指定别的表名
    public $table = 'ld_course_shift_no';

    //时间戳设置
    public $timestamps = false;

        /*
         * @param  获取班号列表
         * @param  parent_id   所属学科大类id
         * @param  nature   资源属性
         * @param  is_forbid   资源状态
         * @param  name     课程单元名称
         * @param  author  zzk
         * @param  ctime   2020/6/23
         * return  array
         */

        public static function getLiveClassList($data){
            //每页显示的条数
            $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 15;
            $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
            $offset   = ($page - 1) * $pagesize;
            //直播单元id
            $resource_id = $data['resource_id'];
            //获取用户网校id
            $data['school_status'] = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
            $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            //获取总条数
            $total = self::where(['is_del'=>0,'resource_id'=>$resource_id,'school_id'=>$data['school_id']])->get()->count();
            //获取数据
            $list = self::where(['is_del'=>0,'resource_id'=>$resource_id,'school_id'=>$data['school_id']])->offset($offset)->limit($pagesize)->get();
            foreach($list as $k => $v){
                //添加总课次
                $list[$k]['class_num'] = self::join("ld_course_class_number","ld_course_class_number.shift_no_id","=","ld_course_shift_no.id")->where(['ld_course_class_number.shift_no_id'=>$v['id'],'ld_course_class_number.is_del'=>0])->count();
                //已上课次 课程开始时间超过当前时间
                $list[$k]['class_num_passed'] = self::join("ld_course_class_number","ld_course_class_number.shift_no_id","=","ld_course_shift_no.id")->where('ld_course_class_number.shift_no_id',$v['id'])->where('ld_course_class_number.start_at','<',time())->where('ld_course_class_number.is_del',0)->count();
                //待上课次  课程开始时间未超过当前时间
                $list[$k]['class_num_not'] = self::join("ld_course_class_number","ld_course_class_number.shift_no_id","=","ld_course_shift_no.id")->where('ld_course_class_number.shift_no_id',$v['id'])->where('ld_course_class_number.start_at','>',time())->where('ld_course_class_number.is_del',0)->count();
                // 课次名称 关联老师名称  课次时间
                $list[$k]['class_child'] = self::leftjoin("ld_course_class_number","ld_course_class_number.shift_no_id","=","ld_course_shift_no.id")
                ->select("ld_course_class_number.id","ld_course_class_number.name","ld_course_class_number.start_at","ld_course_class_number.end_at")
                ->where(["ld_course_shift_no.id"=>$v['id'],"ld_course_class_number.is_del"=>0])
                ->get();
            }
            foreach($list as $key => &$value){
                foreach($value['class_child'] as $k => &$v){
                        $teacher_name = LiveChild::join("ld_course_class_teacher","ld_course_class_number.id","=","ld_course_class_teacher.class_id")
                        ->join("ld_lecturer_educationa","ld_course_class_teacher.teacher_id","=","ld_lecturer_educationa.id")
                        ->select("ld_lecturer_educationa.real_name")
                        ->where(["ld_course_class_number.id"=>$v['id'],"ld_lecturer_educationa.type"=>2])
                        ->first();
                        if(!empty($teacher_name)){
                            $v['teacher_name'] = $teacher_name['real_name'];
                        }else{
                            $v['teacher_name'] = "";
                        }
                        $v['time'] = date("Y/m/d H:i",$v['start_at'])."-".date("H:i",$v['end_at']);
                    }
            }

            if($total > 0){
                return ['code' => 200 , 'msg' => '获取班号列表成功' , 'data' => ['LiveClass_list' => $list, 'total' => $total , 'pagesize' => $pagesize , 'page' => $page]];
            }else{
                return ['code' => 200 , 'msg' => '获取班号列表成功' , 'data' => ['LiveClass_list' => 0, 'total' => $total , 'pagesize' => $pagesize , 'page' => $page]];
            }
        }

        /*
         * @param  添加直播单元班号
         * @param  resource_id   课程单元id
         * @param  name   班号名称
         * @param  content   班号信息
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function AddLiveClass($data){
            unset($data['/admin/liveClass/add']);
            //课程单元id
            if(empty($data['resource_id']) || !isset($data['resource_id'])){
                return ['code' => 201 , 'msg' => '直播单元id不能为空'];
            }
            //班号名称
            if(empty($data['name']) || !isset($data['name'])){
                return ['code' => 201 , 'msg' => '班号名称不能为空'];
            }
            //班号信息
            if(empty($data['content']) || !isset($data['content'])){
                return ['code' => 201 , 'msg' => '班号信息不能为空'];
            }

            //缓存查出用户id和分校id
            $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            $data['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

            $data['create_at'] = date('Y-m-d H:i:s');
            $data['update_at'] = date('Y-m-d H:i:s');
            $add = self::insert($data);
            if($add){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $data['admin_id']  ,
                    'module_name'    =>  'LiveClass' ,
                    'route_url'      =>  'admin/LiveClass/add' ,
                    'operate_method' =>  'insert' ,
                    'content'        =>  '新增数据'.json_encode($data) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                return ['code' => 200 , 'msg' => '添加成功'];
            }else{
                return ['code' => 202 , 'msg' => '添加失败'];
            }
        }
        /*
         * @param  更新直播单元班号
         * @param  resource_id   课程单元id
         * @param  name   班号名称
         * @param  content   班号信息
         * @param  id   班号id
         * @param  author  zzk
         * @param  ctime   2020/6/29
         * return  array
         */
        public static function updateLiveClass($data){
            unset($data['/admin/updateLiveClass']);
            //班号id
            if(empty($data['id']) || !isset($data['id'])){
                return ['code' => 201 , 'msg' => '直播单元id不能为空'];
            }
            //课程单元id
            if(empty($data['resource_id']) || !isset($data['resource_id'])){
                return ['code' => 201 , 'msg' => '直播单元id不能为空'];
            }
            //班号名称
            if(empty($data['name']) || !isset($data['name'])){
                return ['code' => 201 , 'msg' => '班号名称不能为空'];
            }
            //班号信息
            if(empty($data['content']) || !isset($data['content'])){
                return ['code' => 201 , 'msg' => '班号信息不能为空'];
            }
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            $data['admin_id'] = $admin_id;

            $data['update_at'] = date('Y-m-d H:i:s');
            $id = $data['id'];
            unset($data['id']);
            $res = self::where(['id'=>$id])->update($data);
            if($res){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $data['admin_id']  ,
                    'module_name'    =>  'LiveClass' ,
                    'route_url'      =>  'admin/updateLiveClass' ,
                    'operate_method' =>  'update' ,
                    'content'        =>  '更新数据'.json_encode($data) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                return ['code' => 200 , 'msg' => '更新成功'];
            }else{
                return ['code' => 202 , 'msg' => '更新失败'];
            }
        }

        /*
         * @param  更改直播单元班号状态
         * @param  id   班号id
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function updateLiveClassStatus($data){
            if(empty($data['id']) || !isset($data['id'])){
                return ['code' => 201 , 'msg' => '参数为空或格式错误'];
            }
            $LiveClassOne = self::where(['id'=>$data['id']])->first();
            if(!$LiveClassOne){
                return ['code' => 201 , 'msg' => '参数不对'];
            }
            //查询是否和课程关联
            //等学科写完继续
            $is_forbid = ($LiveClassOne['is_forbid']==1)?0:1;
            $update = self::where(['id'=>$data['id']])->update(['is_forbid'=>$is_forbid,'update_at'=>date('Y-m-d H:i:s')]);
            if($update){
                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'LiveClass' ,
                    'route_url'      =>  'admin/updateLiveClassStatus' ,
                    'operate_method' =>  'update' ,
                    'content'        =>  '操作'.json_encode($data) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                return ['code' => 200 , 'msg' => '修改成功'];
            }else{
                return ['code' => 202 , 'msg' => '修改失败'];
            }
        }

        /*
         * @param  直播单元班号删除
         * @param  id   班号id
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function updateLiveClassDelete($data){

            //判断班号id
            if(empty($data['id'])|| !isset($data['id'])){
                return ['code' => 201 , 'msg' => '参数为空或格式错误'];
            }
			//判断直播资源id
            if(empty($data['live_id'])|| !isset($data['live_id'])){
                return ['code' => 201 , 'msg' => '直播资源id为空'];
            }
            $LiveClassOne = self::where(['id'=>$data['id']])->first();
            if(!$LiveClassOne){
                return ['code' => 204 , 'msg' => '参数不正确'];
            }
            //查询是否关联课程
            $course_class_number = CourseLiveResource::where(['resource_id'=>$data['live_id'],'is_del'=>0])->count();
            if($course_class_number > 0){
                return ['code' => 204 , 'msg' => '该资源已关联课程，无法删除'];
            }
            //等学科写完继续
            $update = self::where(['id'=>$data['id']])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
            if($update){
                //删除该班号下所有课次
                LiveChild::where(['shift_no_id'=>$data['id']])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'LiveClass' ,
                    'route_url'      =>  'admin/deleteLiveClass' ,
                    'operate_method' =>  'delete' ,
                    'content'        =>  '软删除id为'.$data['id'],
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                return ['code' => 200 , 'msg' => '删除成功'];
            }else{
                return ['code' => 202 , 'msg' => '删除失败'];
            }
        }
        /*
         * @param  直播单元班号详情
         * @param  id   班号id
         * @param  author  zzk
         * @param  ctime   2020/6/30
         * return  array
         */
        public static function getLiveClassOne($data){
            //判断班号id
            if(empty($data['id'])|| !isset($data['id'])){
                return ['code' => 201 , 'msg' => '参数为空或格式错误'];
            }
            $LiveClassOne = self::where(['id'=>$data['id']])->first();
            if(!$LiveClassOne){
                return ['code' => 204 , 'msg' => '参数不正确'];
            }
            return ['code' => 200 , 'msg' => '获取直播班号详情成功' , 'data' => $LiveClassOne];
        }

        //添加班号课程资料
        public static function uploadLiveClass($data){
            //班号id
            unset($data["/admin/uploadLiveClass"]);
            if(empty($data['parent_id']) || !isset($data['parent_id'])){
                return ['code' => 201 , 'msg' => '班号id不能为空'];
            }
            //查询该班号是否有资料
            $teacher = CourseMaterial::where(["parent_id"=>$data['parent_id'],"mold"=>2])->first();
            if(!empty($teacher)){
                //删除所有之前关联的数据
                CourseMaterial::where(["parent_id"=>$data['parent_id'],"mold"=>2])->delete();
            }
            //资料合集
            $res = json_decode($data['filearr'],1);
            $add = 0;
            if(!empty($res)){
                unset($data['filearr']);
                foreach($res as $k =>$v){
                    $data['type'] = $v['type'];
                    $data['material_name'] = $v['name'];
                    $data['material_size'] = $v['size'];
                    $data['material_url'] = $v['url'];
                    $data['mold'] = 2;
                    //缓存查出用户id和分校id
                    $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
                    $data['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

                    $data['create_at'] = date('Y-m-d H:i:s');
                    $data['update_at'] = date('Y-m-d H:i:s');
                    $add = CourseMaterial::insert($data);
                }
                if($add>0){
                    //添加日志操作
                    AdminLog::insertAdminLog([
                        'admin_id'       =>   $data['admin_id']  ,
                        'module_name'    =>  'LiveClassMaterial' ,
                        'route_url'      =>  'admin/uploadLiveClass' ,
                        'operate_method' =>  'insert' ,
                        'content'        =>  '新增数据'.json_encode($data) ,
                        'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                        'create_at'      =>  date('Y-m-d H:i:s')
                    ]);
                    return ['code' => 200 , 'msg' => '添加成功'];
                }else{
                    return ['code' => 202 , 'msg' => '添加失败'];
                }
            }else{
                return ['code' => 200 , 'msg' => '添加成功!'];
            }
        }
        //获取班号资料列表
        public static function getLiveClassMaterial($data){
            //班号id
            if(empty($data['parent_id']) || !isset($data['parent_id'])){
                return ['code' => 201 , 'msg' => '班号id不能为空'];
            }
            $total = CourseMaterial::where(['is_del'=>0,'parent_id'=>$data['parent_id'],'mold'=>2])->get()->count();
            if($total > 0){
                $list = CourseMaterial::select("id","type","material_name as name","material_size as size","material_url as url")->where(['is_del'=>0,'parent_id'=>$data['parent_id'],'mold'=>2])->get();
                foreach($list as $k => &$v){
                    if($v['type'] == 1){
                        $v['typeName'] = "材料";
                    }else if($v['type'] == 2){
                        $v['typeName'] = "辅料";
                    }else{
                        $v['typeName'] = "其他";
                    }
                }
                return ['code' => 200 , 'msg' => '获取班号资料列表成功' , 'data' => ['LiveClass_list_Material' => $list]];
            }else{
                return ['code' => 200 , 'msg' => '获取班号资料列表成功' , 'data' => ['LiveClass_list_Material' => []]];
            }
        }
        //删除班号课次资源
        public static function deleteLiveClassMaterial($data){
            //资料id
            if(empty($data['id']) || !isset($data['id'])){
                return ['code' => 201 , 'msg' => '资料id不能为空'];
            }
            $update = CourseMaterial::where(['id'=>$data['id'],'mold'=>2])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
            if($update){
                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'LiveClassMaterial' ,
                    'route_url'      =>  'admin/deleteLiveClassMaterial' ,
                    'operate_method' =>  'delete' ,
                    'content'        =>  '软删除id为'.$data['id'],
                    'ip'             =>  $_SERVER['REMOTE_ADDR'],
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                return ['code' => 200 , 'msg' => '删除成功'];
            }else{
                return ['code' => 202 , 'msg' => '删除失败'];
            }
        }
}
