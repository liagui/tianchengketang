<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Subject;
use App\Models\LiveClass;
use App\Models\Admin;
use App\Models\Coures;
use App\Models\CourseRefResource;
use App\Models\CourseLiveResource;
use App\Models\LiveChild;
class Live extends Model {

    //指定别的表名
    public $table = 'ld_course_livecast_resource';
    //时间戳设置
    public $timestamps = false;
/*
         * @param  获取直播资源列表
         * @param  parent_id   所属学科大类id
         * @param  nature   资源属性
         * @param  is_forbid   资源状态
         * @param  name     课程单元名称
         * @param  author  zzk
         * @param  ctime   2020/6/23
         * return  array
         */
        public static function getLiveList($data){
            //每页显示的条数
            $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 15;
            $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
            $offset   = ($page - 1) * $pagesize;
            //判断登录状态
            //获取用户网校id
            $data['school_status'] = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
            $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

            //总校资源
            if($data['school_status'] == 1){

                if(!empty($data['school_id']) && $data['school_id'] != ''){

                    //获取总条数
                $total = self::join('ld_course_subject','ld_course_subject.id','=','ld_course_livecast_resource.parent_id')->select('*','ld_course_livecast_resource.parent_id','ld_course_livecast_resource.child_id')->where(function($query) use ($data){
                    // //获取后端的操作员id
                    // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                    // //操作员id
                    // $query->where('ld_course_livecast_resource.admin_id' , '=' , $admin_id);
                    //学校id
                    $query->where('ld_course_livecast_resource.school_id' , '=' , $data['school_id']);
                    //删除状态
                    $query->where('ld_course_livecast_resource.is_del' , '=' , 0);
                    //判断学科id是否为空
                    if(isset($data['parent_id'])){
                        $s_id = json_decode($data['parent_id']);
                        if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                            $data['parent_id'] = $s_id[0];
                            if(!empty($s_id[1])){
                                $data['child_id'] = $s_id[1];
                            }
                            $query->where('ld_course_livecast_resource.parent_id' , '=' , $data['parent_id']);
                        }
                    }
                    //判断学科小类
                    if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                        $query->where('ld_course_livecast_resource.child_id' , '=' , $data['child_id']);
                    }
                    //判断资源状态是否为空
                    if(isset($data['is_forbid']) && $data['is_forbid'] != 3){
                        $query->where('ld_course_livecast_resource.is_forbid' , '=' , $data['is_forbid']);
                    }
                    //判断课程单元名称是否为空
                    if(isset($data['name']) && !empty(isset($data['name']))){
                        $query->where('ld_course_livecast_resource.name','like','%'.$data['name'].'%')->orWhere('ld_course_livecast_resource.id','like','%'.$data['name'].'%');
                    }
                })->get()->count();
                //获取所有列表
                if($total > 0){
                    $list = self::join('ld_course_subject','ld_course_subject.id','=','ld_course_livecast_resource.parent_id')->select('*','ld_course_livecast_resource.parent_id','ld_course_livecast_resource.child_id','ld_course_livecast_resource.id','ld_course_livecast_resource.create_at','ld_course_livecast_resource.admin_id')->where(function($query) use ($data){
                        // //获取后端的操作员id
                        // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                        // //操作员id
                        // $query->where('ld_course_livecast_resource.admin_id' , '=' , $admin_id);
                        //学校id
                    $query->where('ld_course_livecast_resource.school_id' , '=' , $data['school_id']);
                        //删除状态
                        $query->where('ld_course_livecast_resource.is_del' , '=' , 0);
                        //判断学科id是否为空
                        if(isset($data['parent_id'])){
                            $s_id = json_decode($data['parent_id']);
                            if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                                $data['parent_id'] = $s_id[0];
                                if(!empty($s_id[1])){
                                    $data['child_id'] = $s_id[1];
                                }
                                $query->where('ld_course_livecast_resource.parent_id' , '=' , $data['parent_id']);
                            }
                        }
                        //判断学科小类
                        if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                            $query->where('ld_course_livecast_resource.child_id' , '=' , $data['child_id']);
                        }
                        //判断资源状态是否为空
                        if(isset($data['is_forbid'])  && $data['is_forbid'] != 3){
                            $query->where('ld_course_livecast_resource.is_forbid' , '=' , $data['is_forbid']);
                        }
                        //判断课程单元名称是否为空
                        if(isset($data['name']) && !empty(isset($data['name']))){
                            $query->where('ld_course_livecast_resource.name','like','%'.$data['name'].'%')->orWhere('ld_course_livecast_resource.id','like','%'.$data['name'].'%');
                        }

                    })->offset($offset)->limit($pagesize)
                        ->orderBy("ld_course_livecast_resource.id","desc")
                        ->get();
                    }else{
                        $list=[];
                    }
                    //自增数据
                    foreach($list as $k => &$v){
                        $v['nature'] = 1;
                    }
                    if(isset($data['nature']) && $data['nature'] == 2){
                        $list=[];
                        $total = 0;
                    }
                }
            }else{

                //分校数据
                //自增
                //获取总条数
                    $count1 = self::join('ld_course_subject','ld_course_subject.id','=','ld_course_livecast_resource.parent_id')->select('*','ld_course_livecast_resource.parent_id','ld_course_livecast_resource.child_id')->where(function($query) use ($data){
                        // //获取后端的操作员id
                        // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                        // //操作员id
                        // $query->where('ld_course_livecast_resource.admin_id' , '=' , $admin_id);
                        //学校id
                        $query->where('ld_course_livecast_resource.school_id' , '=' , $data['school_id']);
                        //删除状态
                        $query->where('ld_course_livecast_resource.is_del' , '=' , 0);
                        //判断学科id是否为空
                        if(isset($data['parent_id'])){
                            $s_id = json_decode($data['parent_id']);
                            if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                                $data['parent_id'] = $s_id[0];
                                if(!empty($s_id[1])){
                                    $data['child_id'] = $s_id[1];
                                }
                                $query->where('ld_course_livecast_resource.parent_id' , '=' , $data['parent_id']);
                            }
                        }
                        //判断学科小类
                        if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                            $query->where('ld_course_livecast_resource.child_id' , '=' , $data['child_id']);
                        }
                        //判断资源状态是否为空
                        if(isset($data['is_forbid']) && $data['is_forbid'] != 3){
                            $query->where('ld_course_livecast_resource.is_forbid' , '=' , $data['is_forbid']);
                        }
                        //判断课程单元名称是否为空
                        if(isset($data['name']) && !empty(isset($data['name']))){
                            $query->where('ld_course_livecast_resource.name','like','%'.$data['name'].'%')->orWhere('ld_course_livecast_resource.id','like','%'.$data['name'].'%');
                        }
                    })->get()->count();
                    //获取所有列表
                    if($count1 > 0){
                        $list1 = self::join('ld_course_subject','ld_course_subject.id','=','ld_course_livecast_resource.parent_id')->select('*','ld_course_livecast_resource.parent_id','ld_course_livecast_resource.child_id','ld_course_livecast_resource.id','ld_course_livecast_resource.create_at','ld_course_livecast_resource.admin_id')->where(function($query) use ($data){
                            // //获取后端的操作员id
                            // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                            // //操作员id
                            // $query->where('ld_course_livecast_resource.admin_id' , '=' , $admin_id);
                            //学校id
                        $query->where('ld_course_livecast_resource.school_id' , '=' , $data['school_id']);
                            //删除状态
                            $query->where('ld_course_livecast_resource.is_del' , '=' , 0);
                            //判断学科id是否为空
                            if(isset($data['parent_id'])){
                                $s_id = json_decode($data['parent_id']);
                                if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                                    $data['parent_id'] = $s_id[0];
                                    if(!empty($s_id[1])){
                                        $data['child_id'] = $s_id[1];
                                    }
                                    $query->where('ld_course_livecast_resource.parent_id' , '=' , $data['parent_id']);
                                }
                            }
                            //判断学科小类
                            if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                                $query->where('ld_course_livecast_resource.child_id' , '=' , $data['child_id']);
                            }
                            //判断资源状态是否为空
                            if(isset($data['is_forbid'])  && $data['is_forbid'] != 3){
                                $query->where('ld_course_livecast_resource.is_forbid' , '=' , $data['is_forbid']);
                            }
                            //判断课程单元名称是否为空
                            if(isset($data['name']) && !empty(isset($data['name']))){
                                $query->where('ld_course_livecast_resource.name','like','%'.$data['name'].'%')->orWhere('ld_course_livecast_resource.id','like','%'.$data['name'].'%');
                            }

                        })->orderBy("ld_course_livecast_resource.id","desc")
                          ->get()->toArray();
                        }
                //授权
                $count2 = CourseRefResource::join("ld_course_livecast_resource","ld_course_ref_resource.resource_id","=","ld_course_livecast_resource.id")
                ->join('ld_course_subject','ld_course_subject.id','=','ld_course_livecast_resource.parent_id')->select('*','ld_course_livecast_resource.parent_id','ld_course_livecast_resource.child_id')->where(function($query) use ($data){
                    // //获取后端的操作员id
                    // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                    // //操作员id
                    // $query->where('ld_course_livecast_resource.admin_id' , '=' , $admin_id);
                    //关联数据条件
                    $query->where(["to_school_id"=>$data['school_id'],"ld_course_ref_resource.type"=>1,"ld_course_ref_resource.is_del"=>0]);
                    //删除状态
                    $query->where('ld_course_livecast_resource.is_del' , '=' , 0);
                    //判断学科id是否为空
                    if(isset($data['parent_id'])){
                        $s_id = json_decode($data['parent_id']);
                        if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                            $data['parent_id'] = $s_id[0];
                            if(!empty($s_id[1])){
                                $data['child_id'] = $s_id[1];
                            }
                            $query->where('ld_course_livecast_resource.parent_id' , '=' , $data['parent_id']);
                        }
                    }
                    //判断学科小类
                    if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                        $query->where('ld_course_livecast_resource.child_id' , '=' , $data['child_id']);
                    }
                    //判断资源状态是否为空
                    if(isset($data['is_forbid']) && $data['is_forbid'] != 3){
                        $query->where('ld_course_livecast_resource.is_forbid' , '=' , $data['is_forbid']);
                    }
                    //判断课程单元名称是否为空
                    if(isset($data['name']) && !empty(isset($data['name']))){
                        $query->where('ld_course_livecast_resource.name','like','%'.$data['name'].'%')->orWhere('ld_course_livecast_resource.id','like','%'.$data['name'].'%');
                    }
                })->get()->count();
                //获取所有列表
                if($count2 > 0){
                    $list2 = CourseRefResource::join("ld_course_livecast_resource","ld_course_ref_resource.resource_id","=","ld_course_livecast_resource.id")
                    ->join('ld_course_subject','ld_course_subject.id','=','ld_course_livecast_resource.parent_id')->select('*','ld_course_livecast_resource.parent_id','ld_course_livecast_resource.child_id','ld_course_livecast_resource.id','ld_course_livecast_resource.create_at','ld_course_livecast_resource.admin_id')->where(function($query) use ($data){
                        // //获取后端的操作员id
                        // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                        // //操作员id
                        // $query->where('ld_course_livecast_resource.admin_id' , '=' , $admin_id);
                        //关联数据条件
                    $query->where(["to_school_id"=>$data['school_id'],"ld_course_ref_resource.type"=>1,"ld_course_ref_resource.is_del"=>0]);
                        //删除状态
                        $query->where('ld_course_livecast_resource.is_del' , '=' , 0);
                        //判断学科id是否为空
                        if(isset($data['parent_id'])){
                            $s_id = json_decode($data['parent_id']);
                            if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                                $data['parent_id'] = $s_id[0];
                                if(!empty($s_id[1])){
                                    $data['child_id'] = $s_id[1];
                                }
                                $query->where('ld_course_livecast_resource.parent_id' , '=' , $data['parent_id']);
                            }
                        }
                        //判断学科小类
                        if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                            $query->where('ld_course_livecast_resource.child_id' , '=' , $data['child_id']);
                        }
                        //判断资源状态是否为空
                        if(isset($data['is_forbid'])  && $data['is_forbid'] != 3){
                            $query->where('ld_course_livecast_resource.is_forbid' , '=' , $data['is_forbid']);
                        }
                        //判断课程单元名称是否为空
                        if(isset($data['name']) && !empty(isset($data['name']))){
                            $query->where('ld_course_livecast_resource.name','like','%'.$data['name'].'%')->orWhere('ld_course_livecast_resource.id','like','%'.$data['name'].'%');
                        }

                    })->orderBy("ld_course_livecast_resource.id","desc")
                      ->get()->toArray();
                    }
                    if(!isset($list1)){
                        $list1 = [];
                    }
                    if(!isset($list2)){
                        $list2 = [];
                    }
                    //自增数据
                    foreach($list1 as $k => &$v){
                        $v['nature'] = 1;
                    }
                    //授权数据
                    foreach($list2 as $k => &$v){
                            $v['nature'] = 2;
                    }

                    //数据总数  等于  自增数据加授权数据
                    //判断搜索条件  自增资源和授权资源  1为自增  2为授权 3为全部
                    if(isset($data['nature']) && $data['nature']== 1){
                        $total = $count1;
                        if($total > 0){
                            $arr = array_merge($list1);
                            $start=($page-1)*$pagesize;
                            $limit_s=$start+$pagesize;
                            $list=[];
                            for($i=$start;$i<$limit_s;$i++){
                                if(!empty($arr[$i])){
                                    array_push($list,$arr[$i]);
                                }
                            }
                        }else{
                            $list=[];
                        }
                    }else if(isset($data['nature']) &&  $data['nature'] == 2){
                        $total = $count2;
                        if($total > 0){
                            $arr = array_merge($list2);
                            $start=($page-1)*$pagesize;
                            $limit_s=$start+$pagesize;
                            $list=[];
                            for($i=$start;$i<$limit_s;$i++){
                                if(!empty($arr[$i])){
                                    array_push($list,$arr[$i]);
                                }
                            }
                        }else{
                            $list=[];
                        }
                    }else{
                        $total = $count1 + $count2;
                        $arr = array_merge($list1,$list2);
                        if($total > 0){
                            $start=($page-1)*$pagesize;
                            $limit_s=$start+$pagesize;
                            $list=[];
                            for($i=$start;$i<$limit_s;$i++){
                                if(!empty($arr[$i])){
                                    array_push($list,$arr[$i]);
                                }
                            }
                        }else{
                            $list=[];
                        }
                    }

            }
                foreach($list as $k => &$live){
                    //获取班号数量
                    $live['class_num'] = LiveClass::where(["is_del" => 0,"school_id"=>$data['school_id']])->where("resource_id",$live['id'])->count();
                    $live['admin_name'] = Admin::where("is_del",1)->where("id",$live['admin_id'])->select("username")->first()['username'];
                    $live['subject_child_name'] = Subject::where("is_del",0)->where("id",$live['child_id'])->select("subject_name")->first()['subject_name'];
                }
                return ['code' => 200 , 'msg' => '获取直播资源列表成功' , 'data' => ['Live_list' => $list, 'total' => $total , 'pagesize' => $pagesize , 'page' => $page]];
            }

        /*
         * @param  获取直播资源详情
         * @param  id   资源id
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function getLiveOne($data){
            if(empty($data['id'])){
                return ['code' => 201 , 'msg' => '直播资源id不合法' , 'data' => []];
            }
            $one = self::where("is_del",0)->where("id",$data['id'])->first();
            //获取学科小类和大类
            $one['subject_name'] = Subject::where("is_del",0)->where("id",$one['parent_id'])->select("subject_name")->first()['subject_name'];
            $one['subject_child_name'] = Subject::where("is_del",0)->where("id",$one['child_id'])->select("subject_name")->first()['subject_name'];
            //添加总课时  该资源下所有班号下课次的所有课时
            $one['sum_class_hour'] = LiveClass::join('ld_course_class_number','ld_course_shift_no.id','=','ld_course_class_number.shift_no_id')
            ->where("resource_id",$one['id'])->where(["ld_course_class_number.is_del"=>0,"ld_course_shift_no.is_del"=>0])->sum("class_hour");
            if(!empty($one['child_id'])){
                $one['parent_id'] = [$one['parent_id'],$one['child_id']];
            }
            if($one['child_id'] == 0){
                $one['parent_id'] = [$one['parent_id']];
            }
            unset($one['child_id']);
            return ['code' => 200 , 'msg' => '获取直播资源列表成功' , 'data' => $one];

        }

        /*
         * @param  更改资源状态
         * @param  id   资源id
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function updateLiveStatus($data){
            if(empty($data['id']) || !isset($data['id'])){
                return ['code' => 201 , 'msg' => '参数为空或格式错误'];
            }
            $LiveOne = self::where(['id'=>$data['id']])->first();
            if(!$LiveOne){
                return ['code' => 201 , 'msg' => '参数不对'];
            }
            //查询是否和课程关联
            if($LiveOne['is_forbid'] == 1){
                return ['code' => 204 , 'msg' => '该资源已关联课程，无法修改状态'];
            }
            $is_forbid = ($LiveOne['is_forbid']==2)?0:2;
            $update = self::where(['id'=>$data['id']])->update(['is_forbid'=>$is_forbid,'update_at'=>date('Y-m-d H:i:s')]);
            if($update){
                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Live' ,
                    'route_url'      =>  'admin/updateLiveStatus' ,
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
         * @param  直播资源删除
         * @param  id   资源id
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function updateLiveDelete($data){
            //判断直播资源id
            if(empty($data['id'])|| !isset($data['id'])){
                return ['code' => 201 , 'msg' => '参数为空或格式错误'];
            }
            $LiveOne = self::where(['id'=>$data['id']])->first();
            if(!$LiveOne){
                return ['code' => 204 , 'msg' => '参数不正确'];
            }
            //查询是否授权
            //分校才进行查询
            //获取用户网校id
            $data['school_status'] = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
            $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            //分校资源
            if($data['school_status'] != 1){
                if(!empty($data['school_id']) && $data['school_id'] != ''){
                    //去授权表查询直播id
                    $res = CourseRefResource::where(['resource_id'=>$data['id'],'type'=>1,'to_school_id'=>$data['school_id']])->first();
                    if($res){
                        return ['code' => 204 , 'msg' => '该资源是授权资源，无法删除'];
                    }
                }
            }
            //查询是否是关联课程
            $LiveOnes = self::where(['id'=>$data['id'],'is_forbid'=>1])->first();
            if($LiveOnes){
                return ['code' => 204 , 'msg' => '该资源已关联课程，无法删除'];
            }
            $update = self::where(['id'=>$data['id']])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
            if($update){
                //删除该直播单元下所有的班号和课次
                //获取班号
                $shift_no_id = LiveClass::select("id")->where(['resource_id'=>$data['id']])->first()['id'];
                LiveClass::where(['resource_id'=>$data['id']])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
                LiveChild::where(['shift_no_id'=>$shift_no_id])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Live' ,
                    'route_url'      =>  'admin/deleteLive' ,
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
         * @param  添加直播资源
         * @param  parent_id   所属学科大类id
         * @param  child_id   所属学科小类id
         * @param  nature   资源属性
         * @param  status   资源状态
         * @param  name   资源名称
         * @param  introduce   资源介绍
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function AddLive($data){
            //判断大类id
            unset($data['/admin/live/add']);
            if(empty($data['parent_id']) || !isset($data['parent_id'])){
                return ['code' => 201 , 'msg' => '请正确选择分类'];
            }
            //判断资源名称
            if(empty($data['name']) || !isset($data['name']) || (strlen($data['name'])>180)){
                return ['code' => 201 , 'msg' => '资源名称不能为空或资源名称过长'];
            }
            //判断资源介绍
            if(empty($data['introduce']) || !isset($data['introduce'])){
                return ['code' => 201 , 'msg' => '资源介绍不能为空'];
            }
            $s_id = json_decode($data['parent_id']);
                $data['parent_id'] = $s_id[0];
            if(!empty($s_id[1])){
                $data['child_id'] = $s_id[1];
            }else{
                $data['child_id'] = 0;
            }
            //缓存查出用户id和分校id
            $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            $data['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

            //nature资源属性
            $data['nature'] = 0;
            $data['create_at'] = date('Y-m-d H:i:s');
            $data['update_at'] = date('Y-m-d H:i:s');
            $add = self::insert($data);
            if($add){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $data['admin_id']  ,
                    'module_name'    =>  'Live' ,
                    'route_url'      =>  'admin/Live/add' ,
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
         * @param  更改直播资源
         * @param  parent_id   所属学科大类id
         * @param  child_id   所属学科小类id
         * @param  nature   资源属性
         * @param  status   资源状态
         * @param  name   资源名称
         * @param  introduce   资源介绍
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function updateLive($data){
            //判断大类id
            unset($data['/admin/updateLive']);
            if(empty($data['parent_id']) || !isset($data['parent_id'])){
                return ['code' => 201 , 'msg' => '请正确选择分类'];
            }
            //判断资源名称
            if(empty($data['name']) || !isset($data['name'])){
                return ['code' => 201 , 'msg' => '资源名称不能为空'];
            }
            //判断资源介绍
            if(empty($data['introduce']) || !isset($data['introduce'])){
                return ['code' => 201 , 'msg' => '资源介绍不能为空'];
            }
            $s_id = json_decode($data['parent_id']);
                $data['parent_id'] = $s_id[0];
            if(!empty($s_id[1])){
                $data['child_id'] = $s_id[1];
            }else{
                $data['child_id'] = 0;
            }
            //查询是否授权
            //分校才进行查询
            //获取用户网校id
            $data['school_status'] = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
            $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            //分校资源
            if($data['school_status'] != 1){
                if(!empty($data['school_id']) && $data['school_id'] != ''){
                    //去授权表查询直播id
                    $res = CourseRefResource::where(['resource_id'=>$data['id'],'type'=>1,'to_school_id'=>$data['school_id']])->first();
                    if($res){
                        return ['code' => 204 , 'msg' => '该资源是授权资源，无法删除'];
                    }
                }
            }
            $id = $data['id'];
            unset($data['id']);
            unset($data['school_id']);
            unset($data['school_status']);
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            $data['admin_id'] = $admin_id;
            $data['update_at'] = date('Y-m-d H:i:s');
            $res = self::where(['id'=>$id])->update($data);
            if($res){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $data['admin_id']  ,
                    'module_name'    =>  'Live' ,
                    'route_url'      =>  'admin/updateLive' ,
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
        //关联课次列表
        public static function LessonList($data){
            //搜索  学科搜索  课程名字搜索   展示当前关联的课程

            //直播资源id
            if(empty($data['resource_id']) || !isset($data['resource_id'])){
                return ['code' => 201 , 'msg' => '直播资源id不能为空'];
            }
            //判断只显示当前关联的课程
            if(isset($data['is_show']) && $data['is_show'] == 1){
		
                $list = CourseLiveResource::join('ld_course','ld_course.id','=','ld_course_live_resource.course_id')
                ->join("ld_course_subject","ld_course_subject.id","=","ld_course.parent_id")
                ->select('*','ld_course.parent_id','ld_course.child_id','ld_course.id','ld_course.create_at','ld_course.admin_id')->where(function($query) use ($data){
                    //删除状态
                    $query->where('ld_course.is_del' , '=' , 0);
                    if(isset($data['parent_id'])){
                        $s_id = json_decode($data['parent_id']);
                        if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                            $data['parent_id'] = $s_id[0];
                            if(!empty($s_id[1])){
                                $data['child_id'] = $s_id[1];
                            }
                            $query->where('ld_course.parent_id' , '=' , $data['parent_id']);
                        }
                    }
                    //判断当前资源
                    if(isset($data['resource_id']) && !empty(isset($data['resource_id']))){
                        $query->where('ld_course_live_resource.resource_id' , '=' , $data['resource_id']);
                    }
                    //判断学科小类
                    if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                        $query->where('ld_course.child_id' , '=' , $data['child_id']);
                    }
                    //判断课程单元名称是否为空
                    if(isset($data['title']) && !empty(isset($data['title']))){
                        $query->where('ld_course.title','like','%'.$data['title'].'%');
                    }
                })->get();
                foreach($list as $k => $live){
                    $live['is_relevance'] = 1;
                    $res = Subject::where("is_del",0)->where("id",$live['child_id'])->select("subject_name")->first()['subject_name'];
                    if(!empty($res)){
                        $live['subject_child_name'] = $res;
                    }else{
                        $live['subject_child_name'] = "";
                    }
                }
            }else{
				
				if($data['nature'] == 2){
                    return ['code' => 209 , 'msg' => '此资源为授权资源，如需修改请联系管理员'];
                }
                $list = Coures::join('ld_course_subject','ld_course_subject.id','=','ld_course.parent_id')
				->select('*','ld_course.parent_id','ld_course.child_id','ld_course.id','ld_course.create_at','ld_course.admin_id')
				->where(function($query) use ($data){
                    //删除状态
                    $query->where('ld_course.is_del' , '=' , 0);
                    if(isset($data['parent_id'])){
                        $s_id = json_decode($data['parent_id']);
                        if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                            $data['parent_id'] = $s_id[0];
                            if(!empty($s_id[1])){
                                $data['child_id'] = $s_id[1];
                            }
                            $query->where('ld_course.parent_id' , '=' , $data['parent_id']);
                        }
                    }
                    //判断学科小类
                    if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                        $query->where('ld_course.child_id' , '=' , $data['child_id']);
                    }
                    //判断课程单元名称是否为空
                    if(isset($data['title']) && !empty(isset($data['title']))){
                        $query->where('ld_course.title','like','%'.$data['title'].'%');
                    }
                })->get()->toArray();

                foreach($list as $k => $live){
					$method = Couresmethod::select('method_id')->where(['course_id'=>$live['id'],'is_del'=>0,'method_id'=>1])->count();
                    if($method<=0){
                        unset($list[$k]);
                    }
					$res = Subject::where("is_del",0)->where("id",$live['child_id'])->select("subject_name")->first()['subject_name'];
                    if(!empty($res)){
                        $list[$k]['subject_child_name'] = $res;
                    }else{
                        $list[$k]['subject_child_name'] = "";
                    }
                    /*$res = Subject::where("is_del",0)->where("id",$live['child_id'])->select("subject_name")->first();
                    if(!empty($res)){
                        $list[$k]['subject_child_name'] = $res['subject_name'];
                    }else{
                        $list[$k]['subject_child_name'] = "";
                    }
                    $gl = CourseLiveResource::select("course_id")->where("is_del",0)->where("course_id",$live['id'])->where("resource_id",$data['resource_id'])->first();
                    if(empty($gl)){
                        $list[$k]['is_relevance'] = 0;
                    }else{
                        $list[$k]['is_relevance'] = 1;
                    }*/
                }
				$list = array_values($list);
            }
            return ['code' => 200 , 'msg' => '获取课程列表成功' , 'data' => $list];

        }

        //资源关联课程
        public static function liveRelationLesson($data){

            //直播资源id
            unset($data["/admin/liveRelationLesson"]);
            if(empty($data['resource_id']) || !isset($data['resource_id'])){
                return ['code' => 201 , 'msg' => '直播资源id不能为空'];
            }
            $resource_id = $data['resource_id'];
            //获取班号
            $banhao = LiveClass::where(['resource_id'=>$resource_id,'is_del'=>0,'is_forbid'=>0])->first();
            if(empty($banhao)){
                return ['code' => 201 , 'msg' => '没有班号，无法关联'];
            }
            //课程id
            if(!isset($data['course_id'])){
                return ['code' => 201 , 'msg' => '课程id不能为空'];
            }

            //查询该班号是否有资料
            $teacher = CourseLiveResource::where(["resource_id"=>$data['resource_id']])->first();
            if(!empty($teacher)){
                //删除所有之前关联的数据
                CourseLiveResource::where(["resource_id"=>$data['resource_id']])->delete();
            }


            $res = json_decode($data['course_id']);
            foreach($res as $k => $v){
				$course = Coures::where(['id'=>$v,'status'=>1,'is_del'=>0])->select('title')->first();
                if(!empty($course)){
                    return ['code' => 201 , 'msg' => '该课程-'.$course['title'].'状态为在售，无法修改'];
                }
                $data[$k]['resource_id'] = $data['resource_id'];
                $data[$k]['course_id'] = $v;
                $data[$k]['shift_id'] = $banhao['id'];
                $data[$k]['create_at'] = date('Y-m-d H:i:s');
                $data[$k]['update_at'] = date('Y-m-d H:i:s');
            }
            unset($data['resource_id']);
            unset($data['course_id']);
            $add = CourseLiveResource::insert($data);
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            if($add){
                //关联成功后修改  资源状态
                self::where(['id'=>$resource_id])->update(['is_forbid'=>1,'update_at'=>date('Y-m-d H:i:s')]);
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'liveRelationLesson' ,
                    'route_url'      =>  'admin/liveRelationLesson' ,
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
}
