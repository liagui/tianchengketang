<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CourseRefResource;
use App\Models\Coureschapters;
use Illuminate\Support\Facades\DB;
class Video extends Model {


    public $table = 'ld_course_video_resource';
    //时间戳设置
    public $timestamps = false;
	/**
     * The attributes that are mass assignable.
     *
     * @var array
     */
        /*
         * @param  获取录播资源列表
         * @param  parent_id   所属学科id
         * @param  resource_type   资源类型
         * @param  nature   资源属性
         * @param  status   资源状态
         * @param  resource_name   资源名称
         * @param  id   资源id
         * @param  author  zzk
         * @param  ctime   2020/6/23
         * return  array
         */
        public static function getVideoList($data){
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
                    //总校
                    //获取总条数
                $total = self::join('ld_course_subject', 'ld_course_subject.id', '=', 'ld_course_video_resource.parent_id')->where(function($query) use ($data){
                // //获取后端的操作员id
                // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                // //操作员id
                // $query->where('ld_course_video_resource.admin_id' , '=' , $admin_id);
                //学校id
                $query->where('ld_course_video_resource.school_id' , '=' , $data['school_id']);
                //删除状态
                $query->where('ld_course_video_resource.is_del' , '=' , 0);
                //判断学科id是否为空
                if(isset($data['parent_id'])){
                    $s_id = json_decode($data['parent_id']);
                    if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                        $data['parent_id'] = $s_id[0];
                        if(!empty($s_id[1])){
                            $data['child_id'] = $s_id[1];
                        }
                        $query->where('ld_course_video_resource.parent_id' , '=' , $data['parent_id']);
                    }
                }
                //判断学科小类
                if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                    $query->where('ld_course_video_resource.child_id' , '=' , $data['child_id']);
                }
                //判断资源类型是否为空
                if(isset($data['resource_type']) && !empty(isset($data['resource_type'])) && $data['resource_type'] != 0){
                    $query->where('ld_course_video_resource.resource_type' , '=' , $data['resource_type']);
                }
                //判断资源状态是否为空
                if(isset($data['status']) && !empty(isset($data['status'])) && $data['status'] != 3){
                    $query->where('ld_course_video_resource.status' , '=' , $data['status']);
                }
                //判断资源id是否为空
                if(isset($data['id']) && !empty(isset($data['id']))){
                    $query->where('ld_course_video_resource.id' , '=' , $data['id']);
                }
                //判断资源名称是否为空
                if(isset($data['resource_name']) && !empty(isset($data['resource_name']))){
                    $query->where('resource_name','like','%'.$data['resource_name'].'%')->orWhere('ld_course_video_resource.id','like','%'.$data['resource_name'].'%');
                }
            })->get()->count();
            //获取所有列表
            if($total > 0){
                    $list = self::join('ld_course_subject', 'ld_course_subject.id', '=', 'ld_course_video_resource.parent_id')
                    ->select('*','ld_course_video_resource.parent_id','ld_course_video_resource.id','ld_course_video_resource.create_at')
                    ->where(function($query) use ($data){
                        // //获取后端的操作员id
                        // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                        // //操作员id
                        // $query->where('ld_course_video_resource.admin_id' , '=' , $admin_id);
                        //学校id
                        $query->where('ld_course_video_resource.school_id' , '=' , $data['school_id']);
                        //删除状态
                        $query->where('ld_course_video_resource.is_del' , '=' , 0);
                        //判断学科id是否为空
                        if(isset($data['parent_id'])){
                            $s_id = json_decode($data['parent_id']);
                            if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                                $data['parent_id'] = $s_id[0];
                                if(!empty($s_id[1])){
                                    $data['child_id'] = $s_id[1];
                                }
                                $query->where('ld_course_video_resource.parent_id' , '=' , $data['parent_id']);
                            }
                        }
                        if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                            $query->where('ld_course_video_resource.child_id','=' , $data['child_id']);
                        }
                        //判断资源类型是否为空
                        if(isset($data['resource_type']) && !empty(isset($data['resource_type'])) && $data['resource_type'] != 0){
                            $query->where('ld_course_video_resource.resource_type' , '=' , $data['resource_type']);
                        }
                        //判断资源状态是否为空
                        if(isset($data['status']) && !empty(isset($data['status']))  && $data['status'] != 3){
                            $query->where('ld_course_video_resource.status' , '=' , $data['status']);
                        }
                        //判断资源id是否为空
                        if(isset($data['id']) && !empty(isset($data['id']))){
                            $query->where('ld_course_video_resource.id' , '=' , $data['id']);
                        }
                        //判断资源名称是否为空
                        if(isset($data['resource_name']) && !empty(isset($data['resource_name']))){
                            $query->where('resource_name','like','%'.$data['resource_name'].'%')->orWhere('ld_course_video_resource.id','like','%'.$data['resource_name'].'%');
                        }

                    })->offset($offset)->limit($pagesize)->orderBy('ld_course_video_resource.id','desc')->get();
                    foreach($list as $k =>&$v){
                        $v['nature']  = 1;
                    }
                }else{
                    $list = [];
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
                    //分校查询当前学校自增  和授权数据
                    //查询授权资源和自增资源
                    //自增数据
                    //获取总条数
                    $count1 = self::join('ld_course_subject', 'ld_course_subject.id', '=', 'ld_course_video_resource.parent_id')->where(function($query) use ($data){
                        // //获取后端的操作员id
                        // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                        // //操作员id
                        // $query->where('ld_course_video_resource.admin_id' , '=' , $admin_id);
                        //学校id
                        $query->where('ld_course_video_resource.school_id' , '=' , $data['school_id']);
                        //删除状态
                        $query->where('ld_course_video_resource.is_del' , '=' , 0);
                        //判断学科id是否为空
                        if(isset($data['parent_id'])){
                            $s_id = json_decode($data['parent_id']);
                            if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                                $data['parent_id'] = $s_id[0];
                                if(!empty($s_id[1])){
                                    $data['child_id'] = $s_id[1];
                                }
                                $query->where('ld_course_video_resource.parent_id' , '=' , $data['parent_id']);
                            }
                        }
                        //判断学科小类
                        if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                            $query->where('ld_course_video_resource.child_id' , '=' , $data['child_id']);
                        }
                        //判断资源类型是否为空
                        if(isset($data['resource_type']) && !empty(isset($data['resource_type'])) && $data['resource_type'] != 0){
                            $query->where('ld_course_video_resource.resource_type' , '=' , $data['resource_type']);
                        }
                        //判断资源属性是否为空
                        if(isset($data['nature']) && !empty(isset($data['nature']))){

                        }
                        //判断资源状态是否为空
                        if(isset($data['status']) && !empty(isset($data['status'])) && $data['status'] != 3){
                            $query->where('ld_course_video_resource.status' , '=' , $data['status']);
                        }
                        //判断资源id是否为空
                        if(isset($data['id']) && !empty(isset($data['id']))){
                            $query->where('ld_course_video_resource.id' , '=' , $data['id']);
                        }
                        //判断资源名称是否为空
                        if(isset($data['resource_name']) && !empty(isset($data['resource_name']))){
                            $query->where('ld_course_video_resource.resource_name','like','%'.$data['resource_name'].'%')->orWhere('ld_course_video_resource.id','like','%'.$data['resource_name'].'%');
                        }
                    })->get()->count();
                    //获取所有列表
                    if($count1 > 0){
                            $list1 = self::join('ld_course_subject', 'ld_course_subject.id', '=', 'ld_course_video_resource.parent_id')->select('*','ld_course_video_resource.parent_id','ld_course_video_resource.id','ld_course_video_resource.create_at')->where(function($query) use ($data){
                                // //获取后端的操作员id
                                // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                                // //操作员id
                                // $query->where('ld_course_video_resource.admin_id' , '=' , $admin_id);
                                //学校id
                                $query->where('ld_course_video_resource.school_id' , '=' , $data['school_id']);
                                //删除状态
                                $query->where('ld_course_video_resource.is_del' , '=' , 0);
                                //判断学科id是否为空
                                if(isset($data['parent_id'])){
                                    $s_id = json_decode($data['parent_id']);
                                    if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                                        $data['parent_id'] = $s_id[0];
                                        if(!empty($s_id[1])){
                                            $data['child_id'] = $s_id[1];
                                        }
                                        $query->where('ld_course_video_resource.parent_id' , '=' , $data['parent_id']);
                                    }
                                }
                                if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                                    $query->where('ld_course_video_resource.child_id','=' , $data['child_id']);
                                }
                                //判断资源类型是否为空
                                if(isset($data['resource_type']) && !empty(isset($data['resource_type'])) && $data['resource_type'] != 0){
                                    $query->where('ld_course_video_resource.resource_type' , '=' , $data['resource_type']);
                                }
                                //判断资源状态是否为空
                                if(isset($data['status']) && !empty(isset($data['status']))  && $data['status'] != 3){
                                    $query->where('ld_course_video_resource.status' , '=' , $data['status']);
                                }
                                //判断资源id是否为空
                                if(isset($data['id']) && !empty(isset($data['id']))){
                                    $query->where('ld_course_video_resource.id' , '=' , $data['id']);
                                }
                                //判断资源名称是否为空
                                if(isset($data['resource_name']) && !empty(isset($data['resource_name']))){
                                    $query->where('resource_name','like','%'.$data['resource_name'].'%')->orWhere('ld_course_video_resource.id','like','%'.$data['resource_name'].'%');
                                }

                            })->orderBy('ld_course_video_resource.id','desc')->get()->toArray();
                        }
                    //授权数据
                    $count2 =CourseRefResource::join("ld_course_video_resource","ld_course_ref_resource.resource_id","=","ld_course_video_resource.id")->select('*','ld_course_video_resource.parent_id','ld_course_video_resource.id')
                    ->join('ld_course_subject', 'ld_course_subject.id', '=', 'ld_course_video_resource.parent_id')->where(function($query) use ($data){
                        // //获取后端的操作员id
                        // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                        // //操作员id
                        // $query->where('ld_course_video_resource.admin_id' , '=' , $admin_id);
                        //关联数据条件
                        $query->where(["to_school_id"=>$data['school_id'],"ld_course_ref_resource.type"=>0,"ld_course_ref_resource.is_del"=>0]);
                        //删除状态
                        $query->where('ld_course_video_resource.is_del' , '=' , 0);
                        //判断学科id是否为空
                        if(isset($data['parent_id'])){
                            $s_id = json_decode($data['parent_id']);
                            if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                                $data['parent_id'] = $s_id[0];
                                if(!empty($s_id[1])){
                                    $data['child_id'] = $s_id[1];
                                }
                                $query->where('ld_course_video_resource.parent_id' , '=' , $data['parent_id']);
                            }
                        }
                        //判断学科小类
                        if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                            $query->where('ld_course_video_resource.child_id' , '=' , $data['child_id']);
                        }
                        //判断资源类型是否为空
                        if(isset($data['resource_type']) && !empty(isset($data['resource_type'])) && $data['resource_type'] != 0){
                            $query->where('ld_course_video_resource.resource_type' , '=' , $data['resource_type']);
                        }
                        //判断资源状态是否为空
                        if(isset($data['status']) && !empty(isset($data['status'])) && $data['status'] != 3){
                            $query->where('ld_course_video_resource.status' , '=' , $data['status']);
                        }
                        //判断资源id是否为空
                        if(isset($data['id']) && !empty(isset($data['id']))){
                            $query->where('ld_course_video_resource.id' , '=' , $data['id']);
                        }
                        //判断资源名称是否为空
                        if(isset($data['resource_name']) && !empty(isset($data['resource_name']))){
                            $query->where('ld_course_video_resource.resource_name','like','%'.$data['resource_name'].'%')->orWhere('ld_course_video_resource.id','like','%'.$data['resource_name'].'%');
                        }
                    })->get()->count();
                    if($count2 > 0){
                        $list2 = CourseRefResource::join("ld_course_video_resource","ld_course_ref_resource.resource_id","=","ld_course_video_resource.id")->select('*','ld_course_video_resource.parent_id','ld_course_video_resource.id','ld_course_video_resource.create_at')
                        ->join('ld_course_subject', 'ld_course_subject.id', '=', 'ld_course_video_resource.parent_id')->where(function($query) use ($data){
                            // //获取后端的操作员id
                            // $admin_id= isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                            // //操作员id
                            // $query->where('ld_course_video_resource.admin_id' , '=' , $admin_id);
                            //关联数据条件
                            $query->where(["to_school_id"=>$data['school_id'],"ld_course_ref_resource.type"=>0,"ld_course_ref_resource.is_del"=>0]);
                            //删除状态
                            $query->where('ld_course_video_resource.is_del' , '=' , 0);
                            //判断学科id是否为空
                            if(isset($data['parent_id'])){
                                $s_id = json_decode($data['parent_id']);
                                if(isset($data['parent_id']) && !empty(isset($data['parent_id']) && count($s_id) > 0)){
                                    $data['parent_id'] = $s_id[0];
                                    if(!empty($s_id[1])){
                                        $data['child_id'] = $s_id[1];
                                    }
                                    $query->where('ld_course_video_resource.parent_id' , '=' , $data['parent_id']);
                                }
                            }
                            //判断学科小类
                            if(isset($data['child_id']) && !empty(isset($data['child_id']))){
                                $query->where('ld_course_video_resource.child_id' , '=' , $data['child_id']);
                            }
                            //判断资源类型是否为空
                            if(isset($data['resource_type']) && !empty(isset($data['resource_type'])) && $data['resource_type'] != 0){
                                $query->where('ld_course_video_resource.resource_type' , '=' , $data['resource_type']);
                            }
                            //判断资源状态是否为空
                            if(isset($data['status']) && !empty(isset($data['status'])) && $data['status'] != 3){
                                $query->where('ld_course_video_resource.status' , '=' , $data['status']);
                            }
                            //判断资源id是否为空
                            if(isset($data['id']) && !empty(isset($data['id']))){
                                $query->where('ld_course_video_resource.id' , '=' , $data['id']);
                            }
                            //判断资源名称是否为空
                            if(isset($data['resource_name']) && !empty(isset($data['resource_name']))){
                                $query->where('ld_course_video_resource.resource_name','like','%'.$data['resource_name'].'%')->orWhere('ld_course_video_resource.id','like','%'.$data['resource_name'].'%');
                            }
                        })->get()->toArray();
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
                        $total = $count2 + $count1;
                        if($total > 0){
                            $arr = array_merge($list1,$list2);
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
                return ['code' => 200 , 'msg' => '获取录播资源列表成功' , 'data' => ['video_list' => $list, 'total' => $total , 'pagesize' => $pagesize , 'page' => $page]];
        }
        /*
         * @param  获取录播资源详情
         * @param  id   资源id
         * @param  author  zzk
         * @param  ctime   2020/6/23
         * return  array
         */
        public static function getVideoOne($data){
            if(empty($data['id'])){
                return ['code' => 201 , 'msg' => '录播资源id不合法' , 'data' => []];
            }
            $one = self::where("is_del",0)->where("id",$data['id'])->first();
            if(!empty($one['child_id'])){
                $one['parent_id'] = [$one['parent_id'],$one['child_id']];
            }
            if($one['child_id'] == 0){
                $one['parent_id'] = [$one['parent_id']];
            }
            unset($one['child_id']);
            return ['code' => 200 , 'msg' => '获取录播资源列表成功' , 'data' => $one];

        }
        /*
         * @param  更改资源状态
         * @param  id   资源id
         * @param  author  zzk
         * @param  ctime   2020/6/23
         * return  array
         */
        public static function updateVideoStatus($data){
            if(empty($data['id']) || !isset($data['id'])){
                return ['code' => 201 , 'msg' => '参数为空或格式错误'];
            }
            $videoOne = self::where(['id'=>$data['id']])->first();
            if(!$videoOne){
                return ['code' => 201 , 'msg' => '参数不对'];
            }
            //查询是否和课程关联
            $res = Coureschapters::where(["resource_id"=>$data['id'],"is_del"=>0])->first();
            if($res){
                return ['code' => 204 , 'msg' => '该资源已关联课程，无法修改状态'];
            }
            //等学科写完继续
            $status = ($videoOne['status']==1)?0:1;
            $update = self::where(['id'=>$data['id']])->update(['status'=>$status,'update_at'=>date('Y-m-d H:i:s')]);
            if($update){
                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'video' ,
                    'route_url'      =>  'admin/updateVideoStatus' ,
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
         * @param  录播资源删除
         * @param  id   资源id
         * @param  author  zzk
         * @param  ctime   2020/6/24
         * return  array
         */
        public static function updateVideoDelete($data){
            //判断录播资源id
            if(empty($data['id'])|| !isset($data['id'])){
                return ['code' => 201 , 'msg' => '参数为空或格式错误'];
            }
            $videoOne = self::where(['id'=>$data['id']])->first();
            if(!$videoOne){
                return ['code' => 204 , 'msg' => '参数不正确'];
            }
            //查询是否关联课程
            //等学科写完继续
            //查询是否和课程关联
            $is_coures = Coureschapters::where(["resource_id"=>$data['id'],"is_del"=>0])->first();
            if($is_coures){
                return ['code' => 204 , 'msg' => '该资源已关联课程，无法删除'];
            }
            //查询是否是授权资源
            //查询是否授权
            //分校才进行查询
            //获取用户网校id
            $data['school_status'] = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
            $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            //分校资源
            if($data['school_status'] != 1){
                if(!empty($data['school_id']) && $data['school_id'] != ''){
                    //去授权表查询直播id
                    $res = CourseRefResource::where(['resource_id'=>$data['id'],'type'=>0,'to_school_id'=>$data['school_id']])->first();
                    if($res){
                        return ['code' => 204 , 'msg' => '该资源是授权资源，无法删除'];
                    }
                }
            }
            $update = self::where(['id'=>$data['id']])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
            if($update){
                //获取后端的操作员id
                $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'video' ,
                    'route_url'      =>  'admin/deleteVideo' ,
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
         * @param  添加录播资源
         * @param  parent_id   所属学科id
         * @param  resource_type   资源类型
         * @param  nature   资源属性
         * @param  status   资源状态
         * @param  resource_name   资源名称
         * @param  id   资源id
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function AddVideo($data){
            //判断大类id
            unset($data['/admin/video/add']);
            if(empty($data['parent_id']) || !isset($data['parent_id'])){
                return ['code' => 201 , 'msg' => '请正确选择分类'];
            }
            //判断课程id
            if(empty($data['course_id']) || !isset($data['course_id'])){
                return ['code' => 201 , 'msg' => '课程id不能为空'];
            }
            //判断欢拓视频id
            if(empty($data['mt_video_id']) || !isset($data['mt_video_id'])){
                return ['code' => 201 , 'msg' => '欢拓视频id不能为空'];
            }
            //判断资源名称
            if(empty($data['resource_name']) || !isset($data['resource_name'])){
                return ['code' => 201 , 'msg' => '资源名称不能为空'];
            }
            //判断资源类型
            if(empty($data['resource_type']) || !isset($data['resource_type'])){
                return ['code' => 201 , 'msg' => '资源类型不能为空'];
            }
            //判断视频时长
            if(empty($data['mt_duration']) || !isset($data['mt_duration'])){
                return ['code' => 201 , 'msg' => '视频时长不能为空'];
            }
            //判断资源url
            if(empty($data['resource_url']) || !isset($data['resource_url'])){
                return ['code' => 201 , 'msg' => '资源url不能为空'];
            }
            //判断资源大小
            if(empty($data['resource_size']) || !isset($data['resource_size'])){
                return ['code' => 201 , 'msg' => '资源大小不能为空'];
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


            $resource = new SchoolResource();
            // 这里 扣减 空间
            $resource->updateSpaceUsage($data["school_id"],$data['resource_size'],'');
            //  这里 扣减 流量
            $resource->updateTrafficUsage($data["school_id"],$data['resource_size'],date("Y-m-d H:i:s"));



            if($add){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $data['admin_id']  ,
                    'module_name'    =>  'Video' ,
                    'route_url'      =>  'admin/Video/add' ,
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
         * @param  更新录播资源
         * @param  id 资源id
         * @param  parent_id   所属学科id
         * @param  resource_type   资源类型
         * @param  nature   资源属性
         * @param  status   资源状态
         * @param  resource_name   资源名称
         * @param  id   资源id
         * @param  author  zzk
         * @param  ctime   2020/6/28
         * return  array
         */
        public static function updateVideo($data){

            //判断大类id
            unset($data['/admin/updateVideo']);
            if(empty($data['parent_id']) || !isset($data['parent_id'])){
                return ['code' => 201 , 'msg' => '请正确选择分类'];
            }
            //判断资源名称
            if(empty($data['resource_name']) || !isset($data['resource_name'])){
                return ['code' => 201 , 'msg' => '资源名称不能为空'];
            }
            //判断资源类型
            if(empty($data['resource_type']) || !isset($data['resource_type'])){
                return ['code' => 201 , 'msg' => '资源类型不能为空'];
            }
            //判断资源url
            if(empty($data['resource_url']) || !isset($data['resource_url'])){
                return ['code' => 201 , 'msg' => '资源url不能为空'];
            }
            //判断资源大小
            if(empty($data['resource_size']) || !isset($data['resource_size'])){
                return ['code' => 201 , 'msg' => '资源大小不能为空'];
            }
            $s_id = json_decode($data['parent_id']);
                $data['parent_id'] = $s_id[0];
            if(!empty($s_id[1])){
                $data['child_id'] = $s_id[1];
            }else{
                $data['child_id'] = 0;
            }

            //查询是否是授权资源
            //查询是否授权
            //分校才进行查询
            //获取用户网校id
            $data['school_status'] = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
            $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            //分校资源
            if($data['school_status'] != 1){
                if(!empty($data['school_id']) && $data['school_id'] != ''){
                    //去授权表查询直播id
                    $res = CourseRefResource::where(['resource_id'=>$data['id'],'type'=>0,'to_school_id'=>$data['school_id']])->first();
                    if($res){
                        return ['code' => 204 , 'msg' => '该资源是授权资源，无法更新'];
                    }
                }
            }

            $id = $data['id'];

            unset($data['id']);
            unset($data['school_status']);
            unset($data['school_id']);
			unset($data['pingtai']);
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
			$data['course_id'] = empty($data['course_id']) ? 0 : $data['course_id'];
			$data['mt_video_id'] = empty($data['mt_video_id']) ? 0 : $data['mt_video_id'];
			$data['mt_duration'] = empty($data['mt_duration']) ? 0 : $data['mt_duration'];
            $data['admin_id'] = $admin_id;
            $data['update_at'] = date('Y-m-d H:i:s');
            $res = self::where(['id'=>$id])->update($data);
            if($res){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $admin_id  ,
                    'module_name'    =>  'Video' ,
                    'route_url'      =>  'admin/updateVideo' ,
                    'operate_method' =>  'update' ,
                    'content'        =>  '修改id'.$id.'的内容,'.json_encode($data),
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                return ['code' => 200 , 'msg' => '更新成功'];
            }else{
                return ['code' => 202 , 'msg' => '更新失败'];
            }
        }

// region 添加或者更新资源 CC 点播业务
    /*
     *
     *  添加录播资源 使用 CC 提供的资源
     * @param  parent_id   所属学科id
     * @param  resource_type   资源类型
     * @param  nature   资源属性
     * @param  status   资源状态
     * @param  resource_name   资源名称
     * @param  id   资源id
     * @param  author  zzk
     * @param  ctime   2020/6/28
     * return  array
     */
    public static function AddVideoForCC($data){
        //判断大类id
        unset($data['/admin/video/add']);
        if(empty($data['parent_id']) || !isset($data['parent_id'])){
            return ['code' => 201 , 'msg' => '请正确选择分类'];
        }

        //判断资源名称
        if(empty($data['resource_name']) || !isset($data['resource_name'])){
            return ['code' => 201 , 'msg' => '资源名称不能为空'];
        }
        //判断资源类型
        if(empty($data['resource_type']) || !isset($data['resource_type'])){
            return ['code' => 201 , 'msg' => '资源类型不能为空'];
        }

        //判断资源url
        if(empty($data['resource_url']) || !isset($data['resource_url'])){
            return ['code' => 201 , 'msg' => '资源url不能为空'];
        }
        //判断资源大小
        if(empty($data['resource_size']) || !isset($data['resource_size'])){
            return ['code' => 201 , 'msg' => '资源大小不能为空'];
        }

        //判断 cc 点播的的 id 属性
        if(empty($data['cc_video_id']) || !isset($data['cc_video_id'])){
            return ['code' => 201 , 'msg' => '视频点播id不能为空'];
        }

        // 重点设定 服务商是 CC 直报
        $data['service'] = 'CC';

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

        //  清理掉不必要的参数
        unset($data['pingtai']);

        $add = self::insert($data);

        $resource = new SchoolResource();
        // 这里 扣减 空间
        $resource->updateSpaceUsage($data["school_id"],$data['resource_size'],"");
        //  这里 扣减 流量
        $resource->updateTrafficUsage($data["school_id"],$data['resource_size'],date("Y-m-d H:i:s"));



        if($add){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $data['admin_id']  ,
                'module_name'    =>  'Video' ,
                'route_url'      =>  'admin/Video/add' ,
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
             * @param  更新录播资源
             * @param  id 资源id
             * @param  parent_id   所属学科id
             * @param  resource_type   资源类型
             * @param  nature   资源属性
             * @param  status   资源状态
             * @param  resource_name   资源名称
             * @param  id   资源id
             * @param  author  zzk
             * @param  ctime   2020/6/28
             * return  array
             */
    public static function updateVideoForCC($data){
        //判断大类id
        unset($data['/admin/updateVideo']);
        if(empty($data['parent_id']) || !isset($data['parent_id'])){
            return ['code' => 201 , 'msg' => '请正确选择分类'];
        }
        //判断课程id
        if(empty($data['course_id']) || !isset($data['course_id'])){
            return ['code' => 201 , 'msg' => '课程id不能为空'];
        }

        //判断资源名称
        if(empty($data['resource_name']) || !isset($data['resource_name'])){
            return ['code' => 201 , 'msg' => '资源名称不能为空'];
        }
        //判断资源类型
        if(empty($data['resource_type']) || !isset($data['resource_type'])){
            return ['code' => 201 , 'msg' => '资源类型不能为空'];
        }

        //判断资源url
        if(empty($data['resource_url']) || !isset($data['resource_url'])){
            return ['code' => 201 , 'msg' => '资源url不能为空'];
        }
        //判断资源大小
        if(empty($data['resource_size']) || !isset($data['resource_size'])){
            return ['code' => 201 , 'msg' => '资源大小不能为空'];
        }
        $s_id = json_decode($data['parent_id']);
        $data['parent_id'] = $s_id[0];
        if(!empty($s_id[1])){
            $data['child_id'] = $s_id[1];
        }else{
            $data['child_id'] = 0;
        }

        //查询是否是授权资源
        //查询是否授权
        //分校才进行查询
        //获取用户网校id
        $data['school_status'] = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //分校资源
        if($data['school_status'] != 1){
            if(!empty($data['school_id']) && $data['school_id'] != ''){
                //去授权表查询直播id
                $res = CourseRefResource::where(['resource_id'=>$data['id'],'type'=>0,'to_school_id'=>$data['school_id']])->first();
                if($res){
                    return ['code' => 204 , 'msg' => '该资源是授权资源，无法更新'];
                }
            }
        }

        $id = $data['id'];
        unset($data['id']);
        unset($data['school_status']);
        unset($data['school_id']);
        //获取后端的操作员id
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        $data['admin_id'] = $admin_id;
        $data['update_at'] = date('Y-m-d H:i:s');
        $res = self::where(['id'=>$id])->update($data);
        if($res){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Video' ,
                'route_url'      =>  'admin/updateVideo' ,
                'operate_method' =>  'update' ,
                'content'        =>  '修改id'.$id.'的内容,'.json_encode($data),
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '更新成功'];
        }else{
            return ['code' => 202 , 'msg' => '更新失败'];
        }
    }

    /**
     *  调整视频文件的分类
     * @param $cc_video_id
     * @param string $type
     * @return array
     */
    public function auditVideo($cc_video_id,$type = 1){

        $data["audit"] = intval($type);

        //获取后端的操作员id
        // $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        $info = self::where(['cc_video_id'=>$cc_video_id])->first();

        $res = self::where(['cc_video_id'=>$cc_video_id])->update($data);
        if($res){

            $video_info = self::where("cc_video_id",$cc_video_id)->first()->toArray();

            return ['code' => 200 , 'msg' => '更新成功', 'video_info' => $video_info];
        }else{
            return ['code' => 202 , 'msg' => '更新失败'];
        }

    }

    /**
     *  调整视频文件的分类
     * @param $cc_video_id
     * @param $mt_duration
     * @return array
     */
    public function addVideoDuration($cc_video_id,int $mt_duration){


        $data["mt_duration"] = intval($mt_duration);
        //获取后端的操作员id
        // $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        $res = self::where(['cc_video_id'=>$cc_video_id])->update($data);
        if($res){

            return ['code' => 200 , 'msg' => '更新成功'];
        }else{
            return ['code' => 202 , 'msg' => '更新失败'];
        }

    }


    public function VideoToCCLive($cc_video_id,$cc_info){

        $data = array();
        (isset($cc_info['cc_room_id']))?$data["cc_room_id"] = $cc_info['cc_room_id']:"";
        (isset($cc_info['cc_live_id']))?$data["cc_live_id"] = $cc_info['cc_live_id']:"";
        (isset($cc_info['cc_record_id']))?$data["cc_record_id"] = $cc_info['cc_record_id']:"";
        (isset($cc_info['cc_view_pass']))?$data["cc_view_pass"] = $cc_info['cc_view_pass']:"";

        //获取后端的操作员id
        // $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        $res = self::where(['cc_video_id'=>$cc_video_id])->update($data);
        if($res){

//            $video_info = self::where("cc_video_id",$cc_video_id)->first()->toArray();
//            //添加日志操作
//            AdminLog::insertAdminLog([
//                'admin_id'       =>   $admin_id  ,
//                'module_name'    =>  'Video' ,
//                'route_url'      =>  'admin/updateVideo' ,
//                'operate_method' =>  'update' ,
//                'content'        =>  'CC 审核 cc_video_id'.$cc_video_id.'的内容,')
//                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
//                'create_at'      =>  date('Y-m-d H:i:s')
//            ]);
            return ['code' => 200 , 'msg' => '更新成功'];
        }else{
            return ['code' => 202 , 'msg' => '更新失败'];
        }

    }


    public  function  moveVideoT0Category( $video_id,$parent_id,$child_id ){
        $category =  new Category();

    }




// endregion
}

