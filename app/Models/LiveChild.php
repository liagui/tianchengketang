<?php
namespace App\Models;
use App\Models\LiveClassChildTeacher;
use App\Models\CourseLiveClassChild;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Database\Eloquent\Model;
use App\Tools\MTCloud;
class LiveChild extends Model {
    //指定别的表名
    public $table = 'ld_course_class_number';

    //时间戳设置
    public $timestamps = false;

        /*
         * @param  获取班号课次列表
         * @param  shift_no_id   班号id
         * @param  author  zzk
         * @param  ctime   2020/6/23
         * return  array
         */

        public static function getLiveClassChildList($data){
            //每页显示的条数
            $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 15;
            $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
            $offset   = ($page - 1) * $pagesize;
            //直播单元id
            $shift_no_id = $data['shift_no_id'];
            //获取总条数
            $total = self::where(['is_del'=>0,'shift_no_id'=>$shift_no_id])->get()->count();
            //获取数据
            $list = self::where(['is_del'=>0,'shift_no_id'=>$shift_no_id])->offset($offset)->limit($pagesize)
            ->orderBy("id","desc")
            ->get();
            //开始时间  结束时间  转为  日期 星期  时段  和 09:00 - 12:00
            foreach($list as  $key => $value){
                //查询关联老师
                $teacher_id = LiveClassChildTeacher::join("ld_lecturer_educationa","ld_lecturer_educationa.id","=","ld_course_class_teacher.teacher_id")
                ->select("ld_lecturer_educationa.id")
                ->where('class_id',$value['id'])
                ->where("ld_lecturer_educationa.type",2)->first();
                //查询关联教务
                $senate_id = LiveClassChildTeacher::join("ld_lecturer_educationa","ld_lecturer_educationa.id","=","ld_course_class_teacher.teacher_id")
                ->select("ld_lecturer_educationa.id")
                ->where('class_id',$value['id'])
                ->where("ld_lecturer_educationa.type",1)->get()->toArray();
                if(!is_null($teacher_id)){
                    $value['teacher_id']  = $teacher_id['id'];
                }else{
                    $value['teacher_id']  = "";
                }
                $value['senate_id']  = $senate_id;
                $value['date'] = date('Y-m-d',$value['start_at']);
                $weekarray=array("日","一","二","三","四","五","六");
                $value['week'] = "星期".$weekarray[date('w',$value['start_at'])];
                $no = date("H",$value['start_at']);
                if ($no>=0&&$no<=12){
                    $value['period'] = "上午";
                }
                if ($no>12&&$no<=24){
                    $value['period'] = "下午";
                }
                $value['time'] = date('H:i',$value['start_at']).'-'.date('H:i',$value['end_at']);
                unset($value['start_at']);
                unset($value['end_at']);
            }
            foreach($list as  $key => &$value){
                if(count($value['senate_id'])  > 0){
                    $value['senate_id'] = array_column($value['senate_id'], 'id');
                }else{
                    $value['senate_id'] = [];
                }
            }
            if($total > 0){
                return ['code' => 200 , 'msg' => '获取班号课次列表成功' , 'data' => ['LiveClassChild_list' => $list, 'total' => $total , 'pagesize' => $pagesize , 'page' => $page]];
            }else{
                return ['code' => 200 , 'msg' => '获取班号课次成功' , 'data' => ['LiveClassChild_list' => [], 'total' => $total , 'pagesize' => $pagesize , 'page' => $page]];
            }
        }
        //课次详情
        public static function getLiveClassChildListOne($data){
            if(empty($data['id'])){
                return ['code' => 201 , 'msg' => '课次id不合法'];
            }
            $one = self::where("is_del",0)->where("id",$data['id'])->first();
            //更改时间格式
            $one['date'] = date('Y-m-d',$one['start_at']);
            $one['time'] = [date('H:i:s',$one['start_at']),date('H:i:s',$one['end_at'])];
            unset($one['start_at']);
            unset($one['end_at']);
            return ['code' => 200 , 'msg' => '获取课次详情成功' , 'data' => $one];

        }
        /*
         * @param  添加直播单元班号课次
         * @param  shift_no_id   班号id
         * @param  start_at 课次开始时间
         * @param  end_at 课次结束时间
         * @param  name   课次名称
         * @param  class_hour  课时
         * @param  is_free  是否收费(1代表是,0代表否)
         * @param  is_bullet  是否弹幕(1代表是,0代表否)
         * @param  live_type  选择模式(1语音云3大班5小班6大班互动)
         * @param  author  zzk
         * @param  ctime   2020/6/29
         * return  array
         */
        public static function AddLiveClassChild($data){
            unset($data['/admin/liveChild/add']);
            //处理时间
            $res = json_decode($data['time']);
            $data['start_at'] = strtotime($data['date'].$res[0]);
            $data['end_at'] = strtotime($data['date'].$res[1]);
            //班号id
            if(empty($data['shift_no_id']) || !isset($data['shift_no_id'])){
                return ['code' => 201 , 'msg' => '班号id不能为空'];
            }
            //课次开始时间
            if(empty($data['start_at']) || !isset($data['start_at'])){
                return ['code' => 201 , 'msg' => '课次开始时间不能为空'];
            }
            // //课次开始时间
            // if($data['start_at'] < time()){
            //     return ['code' => 201 , 'msg' => '课次开始时间不能小于当前时间'];
            // }
            //课次结束时间
            if(empty($data['end_at']) || !isset($data['end_at'])){
                return ['code' => 201 , 'msg' => '课次结束时间不能为空'];
            }
            //课次名称
            if(empty($data['name']) || !isset($data['name'])){
                return ['code' => 201 , 'msg' => '课次名称不能为空'];
            }
            //课时
            if(empty($data['class_hour']) || !isset($data['class_hour'])){
                return ['code' => 201 , 'msg' => '课时不能为空'];
            }
            //选择模式
            if(empty($data['live_type']) || !isset($data['live_type'])){
                return ['code' => 201 , 'msg' => '选择模式不能为空'];
            }
            unset($data['date']);
            unset($data['time']);
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
                    'module_name'    =>  'LiveClassChild' ,
                    'route_url'      =>  'admin/liveChild/add' ,
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
         * @param  更新直播单元班号课次
         * @param  shift_no_id   班号id
         * @param  start_at 课次开始时间
         * @param  end_at 课次结束时间
         * @param  name   课次名称
         * @param  class_hour  课时
         * @param  is_free  是否收费(1代表是,0代表否)
         * @param  is_bullet  是否弹幕(1代表是,0代表否)
         * @param  live_type  选择模式(1语音云3大班5小班6大班互动)
         * @param  id  课次id
         * @param  author  zzk
         * @param  ctime   2020/6/29
         * return  array
         */
        public static function updateLiveClassChild($data){
            unset($data['/admin/updateLiveChild']);
            //处理时间
            $res = json_decode($data['time']);
            $data['start_at'] = strtotime($data['date'].$res[0]);
            $data['end_at'] = strtotime($data['date'].$res[1]);
            //课次id
            if(empty($data['id']) || !isset($data['id'])){
                return ['code' => 201 , 'msg' => '课次id不能为空'];
            }
            //班号id
            if(empty($data['shift_no_id']) || !isset($data['shift_no_id'])){
                return ['code' => 201 , 'msg' => '班号id不能为空'];
            }
            //课次开始时间
            if(empty($data['start_at']) || !isset($data['start_at'])){
                return ['code' => 201 , 'msg' => '课次开始时间不能为空'];
            }
            //课次结束时间
            if(empty($data['end_at']) || !isset($data['end_at'])){
                return ['code' => 201 , 'msg' => '课次结束时间不能为空'];
            }
            // //课次开始时间
            // if($data['start_at'] < time()){
            //     return ['code' => 201 , 'msg' => '课次开始时间不能小于当前时间'];
            // }
            //课次名称
            if(empty($data['name']) || !isset($data['name'])){
                return ['code' => 201 , 'msg' => '课次名称不能为空'];
            }
            //课时
            if(empty($data['class_hour']) || !isset($data['class_hour'])){
                return ['code' => 201 , 'msg' => '课时不能为空'];
            }
            //选择模式
            if(empty($data['live_type']) || !isset($data['live_type'])){
                return ['code' => 201 , 'msg' => '选择模式不能为空'];
            }
            unset($data['date']);
            unset($data['time']);
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            $data['admin_id'] = $admin_id;
            $data['update_at'] = date('Y-m-d H:i:s');
            $id = $data['id'];
            unset($data['id']);
            $res = self::where(['id'=>$id])->update($data);
            if($res){
                //更新数据  更新发布状态
                self::where(['id'=>$id])->update(['status'=>0]);
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $data['admin_id']  ,
                    'module_name'    =>  'LiveClassChild' ,
                    'route_url'      =>  'admin/updateLiveChild' ,
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
         * @param  ctime   2020/6/29
         * return  array
         */
        public static function updateLiveClassChildStatus($data){
            return ['code' => 200 , 'msg' => '暂未开放'];
        }

        /*
         * @param  直播单元班号课次删除
         * @param  id   课次id
         * @param  author  zzk
         * @param  ctime   2020/6/29
         * return  array
         */
        public static function updateLiveClassChildDelete($data){
            //课次id
            if(empty($data['id'])|| !isset($data['id'])){
                return ['code' => 201 , 'msg' => '参数为空或格式错误'];
            }
            $LiveClassOne = self::where(['id'=>$data['id']])->first();
            if(!$LiveClassOne){
                return ['code' => 204 , 'msg' => '参数不正确'];
            }
            //课次已发布到欢拓无法删除
            $LiveClassOne1 = self::where(['id'=>$data['id'],'status' => 1])->first();
            if($LiveClassOne1){
                return ['code' => 204 , 'msg' => '该课次已发布到欢拓，无法删除该课次'];
            }
            $update = self::where(['id'=>$data['id'],'is_del'=>0])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
            if($update){
                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'LiveClassChild' ,
                    'route_url'      =>  'admin/deleteLiveChild' ,
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
        //关联讲师教务
        public static function LiveClassChildTeacher($data){
            unset($data['/admin/teacherLiveChild']);
            //课次id
            if(empty($data['class_id'])|| !isset($data['class_id'])){
                return ['code' => 201 , 'msg' => '课次id不能为空'];
            }
            $id = $data['class_id'];
            //讲师id
            if(empty($data['teacher_id'])|| !isset($data['teacher_id'])){
                return ['code' => 201 , 'msg' => '讲师id不能为空'];
            }
            //查询是否关联老师
            $teacher = LiveClassChildTeacher::where("class_id",$data['class_id'])->first();
            if(!empty($teacher)){
                //删除所有之前关联的数据
                LiveClassChildTeacher::where("class_id",$data['class_id'])->delete();
            }
            //教务id
            if(isset($data['senate_id'])){
                //教师id和课次关联
                $res = json_decode($data['senate_id']);
                array_push($res,$data['teacher_id']);
                foreach($res as $k => $v){
                    $data[$k]['class_id'] = $data['class_id'];
                    $data[$k]['teacher_id'] = $v;
                    $data[$k]['create_at'] = date('Y-m-d H:i:s');
                    $data[$k]['update_at'] = date('Y-m-d H:i:s');
                }
                    unset($data['class_id']);
                    unset($data['teacher_id']);
                    unset($data['senate_id']);
            }else{
                $data['create_at'] = date('Y-m-d H:i:s');
                $data['update_at'] = date('Y-m-d H:i:s');
            }
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            $add = LiveClassChildTeacher::insert($data);
            if($add){
                self::where(['id'=>$id])->update(['status'=>0]);
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id,
                    'module_name'    =>  'LiveClassChildTeacher' ,
                    'route_url'      =>  'admin/teacherLiveChild' ,
                    'operate_method' =>  'insert' ,
                    'content'        =>  '新增数据'.json_encode($data) ,
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                return ['code' => 200 , 'msg' => '关联成功'];
            }else{
                return ['code' => 202 , 'msg' => '关联失败'];
            }
        }
        //发布课次到欢拓
        public static function creationLiveClassChild($data){
            //课次id
            unset($data['/admin/creationLive']);
            if(empty($data['class_id']) || !isset($data['class_id'])){
                return ['code' => 201 , 'msg' => '课次id不能为空'];
            }
            //查询该课次进行发布到欢拓
            $one = self::join('ld_course_class_teacher', 'ld_course_class_teacher.class_id', '=', 'ld_course_class_number.id')
                        ->join('ld_lecturer_educationa', 'ld_lecturer_educationa.id', '=', 'ld_course_class_teacher.teacher_id')
                        ->select(['*','ld_course_class_number.id'])
                        ->where(['ld_course_class_number.id'=>$data['class_id'],'ld_course_class_number.is_del'=>0,'ld_course_class_number.status'=>0,'ld_lecturer_educationa.type'=>2])
                        ->first();
            if(!$one){
                return ['code' => 204 , 'msg' => '课次已发布成功'];
            }
            //查询该课次是已发布 修改信息
            $CourseLiveClassChild = CourseLiveClassChild::where(["class_id"=>$data['class_id']])->first();
            if($CourseLiveClassChild){
                //更新课次
                // TODO:  这里替换欢托的sdk CC 直播的 ?? 这里是更新课程信息？？ is ok
                // 但是 cc直播的整合是 一个课 对应一个直播间 所以这里不需要更新？？
//                $MTCloud = new MTCloud();
//                $res = $MTCloud->courseUpdate(
//                    $course_id = $CourseLiveClassChild['course_id'],
//                    $account   = $one['teacher_id'],
//                    $course_name = $one['name'],
//                    $start_time = date("Y-m-d H:i:s",$one['start_at']),
//                    $end_time   = date("Y-m-d H:i:s",$one['end_at']),
//                    $nickname   = $one['real_name']
//                );
                // 更新 课次 绑定的 CC 直播间的 信息
                $CCCloud = new CCCloud();
                $room_info = $CCCloud ->update_room_info($CourseLiveClassChild->course_id, $one->name,
                    $one->name,$one->barrage);


                if($room_info['code'] == 0){
                        //更新发布状态
                        $update = self::where(['id'=>$data['class_id'],'status'=>0])->update(['status'=>1,'update_at'=>date('Y-m-d H:i:s')]);
                        if($update){
                            //获取后端的操作员id
                            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                            //添加日志操作
                            AdminLog::insertAdminLog([
                                'admin_id'       =>   $admin_id  ,
                                'module_name'    =>  'LiveClassChild' ,
                                'route_url'      =>  'admin/updateStatusLiveChild' ,
                                'operate_method' =>  'update' ,
                                'content'        =>  '更新id为'.$data['class_id'],
                                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                                'create_at'      =>  date('Y-m-d H:i:s')
                            ]);
                            //课次关联表更新数据
                            //获取后端的操作员id
                            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                            $data['course_name'] = $one['name'];
                            $data['nickname'] = $one['real_name'];
                            $data['account'] = $one['teacher_id'];
                            $data['start_time'] = $one['start_at'];
                            $data['end_time'] = $one['end_at'];
                            $data['bid'] = "";
                            $data['admin_id'] = $admin_id;
                            $data['update_at'] = date('Y-m-d H:i:s');
                            $id = $CourseLiveClassChild['id'];
                            $update = CourseLiveClassChild::where(['id'=>$id])->update($data);
                            if($update){
                                //添加日志操作
                                AdminLog::insertAdminLog([
                                    'admin_id'       =>   $admin_id  ,
                                    'module_name'    =>  'LiveClassChild' ,
                                    'route_url'      =>  'admin/liveChild/add' ,
                                    'operate_method' =>  'update' ,
                                    'content'        =>  '更新数据'.json_encode($data) ,
                                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                                    'create_at'      =>  date('Y-m-d H:i:s')
                                ]);
                                return ['code' => 200 , 'msg' => '更新发布成功'];
                            }else{
                                return ['code' => 202 , 'msg' => '更新发布失败'];
                            }

                        }else{
                            return ['code' => 202 , 'msg' => '更新发布状态失败'];
                        }

                    }else{
                        return ['code' => 204 , 'msg' => $room_info['msg']];
                    }
            }else{
                //发布课次
                // TODO:  这里替换欢托的sdk CC 直播的 is ok

                //$MTCloud = new MTCloud();
//                $res = $MTCloud->courseAdd(
//                    $course_name = $one['name'],
//                    $account   = $one['teacher_id'],
//                    $start_time = date("Y-m-d H:i:s",$one['start_at']),
//                    $end_time   = date("Y-m-d H:i:s",$one['end_at']),
//                    $nickname   = $one['real_name']
//                );

                $CCCloud = new CCCloud();
                //产生 教师端 和 助教端 的密码 默认一致
                $password= $CCCloud ->random_password();
                $password_user = $CCCloud ->random_password();
                $room_info = $CCCloud ->create_room($one['name'], $one['name'],$password,$password,$password_user);

                if($room_info['code'] == 0){
                    //更新发布状态
                    $update = self::where(['id'=>$data['class_id'],'status'=>0])->update(['status'=>1,'update_at'=>date('Y-m-d H:i:s')]);
                    if($update){
                        //获取后端的操作员id
                        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                        //添加日志操作
                        AdminLog::insertAdminLog([
                            'admin_id'       =>   $admin_id  ,
                            'module_name'    =>  'LiveClassChild' ,
                            'route_url'      =>  'admin/updateStatusLiveChild' ,
                            'operate_method' =>  'update' ,
                            'content'        =>  '更新id为'.$data['class_id'],
                            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                            'create_at'      =>  date('Y-m-d H:i:s')
                        ]);
                        //课次关联表添加数据
                        $insert['class_id'] = $data['class_id'];
                        $insert['admin_id'] = $admin_id;
                        $insert['course_name'] = $one['name'];
                        $insert['account'] = $one['teacher_id'];
                        $insert['start_time'] = $one['start_at'];
                        $insert['end_time'] = $one['end_at'];
                        $insert['nickname'] = $one['real_name'];
                        $insert['partner_id'] = 0;
                        $insert['bid'] = 0;
                        $insert['course_id'] = $room_info['data']['room_id'];
                        $insert['zhubo_key'] = $password;
                        $insert['admin_key'] = $password;
                        $insert['user_key'] = $password_user;
                        $insert['add_time'] = time();
                        $insert['status'] = 1;
                        $insert['create_at'] = date('Y-m-d H:i:s');
                        $insert['update_at'] = date('Y-m-d H:i:s');
                        $add = CourseLiveClassChild::insert($insert);
                        if($add){
                            //添加日志操作
                            AdminLog::insertAdminLog([
                                'admin_id'       =>   $insert['admin_id']  ,
                                'module_name'    =>  'LiveClassChild' ,
                                'route_url'      =>  'admin/liveChild/add' ,
                                'operate_method' =>  'insert' ,
                                'content'        =>  '新增数据'.json_encode($data) ,
                                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                                'create_at'      =>  date('Y-m-d H:i:s')
                            ]);
                            return ['code' => 200 , 'msg' => '发布成功'];
                        }else{
                            return ['code' => 202 , 'msg' => '发布失败'];
                        }

                    }else{
                        return ['code' => 202 , 'msg' => '更新发布状态失败'];
                    }

                }else{
                    return ['code' => 204 , 'msg' => $room_info['msg']];
                }
            }
        }


        //添加班号课次资料
        public static function uploadLiveClassChild($data){
            unset($data["/admin/uploadLiveClassChild"]);
            //课次id
            if(empty($data['parent_id']) || !isset($data['parent_id'])){
                return ['code' => 201 , 'msg' => '课次id不能为空'];
            }
            //查询该班号是否有资料
            $teacher = CourseMaterial::where(["parent_id"=>$data['parent_id'],"mold"=>3])->first();
            if(!empty($teacher)){
                //删除所有之前关联的数据
                CourseMaterial::where(["parent_id"=>$data['parent_id'],"mold"=>3])->delete();
            }
            //资料合集
            $res = json_decode($data['filearr'],1);
            unset($data['filearr']);
            foreach($res as $k =>$v){
                $data['type'] = $v['type'];
                $data['material_name'] = $v['name'];
                $data['material_size'] = $v['size'];
                $data['material_url'] = $v['url'];
                $data['mold'] = 3;
                //缓存查出用户id和分校id
                $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
                $data['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                $data['create_at'] = date('Y-m-d H:i:s');
                $data['update_at'] = date('Y-m-d H:i:s');
                $add = CourseMaterial::insert($data);
            }
            if($add){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $data['admin_id']  ,
                    'module_name'    =>  'LiveClassChildMaterial' ,
                    'route_url'      =>  'admin/uploadLiveClassChild' ,
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
        //获取课次资源列表
        public static function getLiveClassMaterial($data){
            //课次id
            if(empty($data['parent_id']) || !isset($data['parent_id'])){
                return ['code' => 201 , 'msg' => '课次id不能为空'];
            }
            $total = CourseMaterial::where(['is_del'=>0,'parent_id'=>$data['parent_id'],'mold'=>3])->get()->count();
            if($total > 0){
                $list = CourseMaterial::select("id","type","material_name as name","material_size as size","material_url as url")->where(['is_del'=>0,'parent_id'=>$data['parent_id'],'mold'=>3])->get();
                foreach($list as $k => $v){
                    if($v['type'] == 1){
                        $v['typeName'] = "材料";
                    }else if($v['type'] == 2){
                        $v['typeName'] = "辅料";
                    }else{
                        $v['typeName'] = "其他";
                    }
                }
                return ['code' => 200 , 'msg' => '获取课次资料列表成功' , 'data' => ['LiveClass_list_child_Material' => $list]];
            }else{
                return ['code' => 200 , 'msg' => '获取课次资料列表成功' , 'data' => ['LiveClass_list_child_Material' => []]];
            }
        }
        //删除课次资料
        public static function deleteLiveClassMaterial($data){
            //资料id
            if(empty($data['id']) || !isset($data['id'])){
                return ['code' => 201 , 'msg' => '资料id不能为空'];
            }
            $update = CourseMaterial::where(['id'=>$data['id'],'mold'=>3])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
            if($update){
                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'LiveClassChildMaterial' ,
                    'route_url'      =>  'admin/deleteLiveClassChildMaterial' ,
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
}

