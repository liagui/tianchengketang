<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use phpDocumentor\Reflection\Types\Self_;
use Illuminate\Support\Facades\DB;

class CouresSubject extends Model {
    //指定别的表名
    public $table = 'ld_course_subject';
    //时间戳设置
    public $timestamps = false;
    /*
         * @param  列表
         * @param  school_status  1总校其他是分校
         * @param  school_id  分校id
         * @param  author  苏振文
         * @param  ctime   2020/6/24 10:44
         * return  array
         */
    public static function subjectList($school_status = 1,$school_id = 1){
        $where['is_del'] = 0;
        $where['parent_id'] = 0;
       if($school_status != 1){
           $where['school_id'] = $school_id;
       }
       $list =self::select('id','subject_name','description','is_open')
           ->where($where)
           ->get()->toArray();

       foreach ($list as $k=>&$v){
           $sun = self::select('id','subject_name','is_open')
               ->where(['parent_id'=>$v['id'],'is_del'=>0])->get();
           $v['subset'] = $sun;
       }

       return ['code' => 200 , 'msg' => '获取成功','data'=>$list];
    }
    //添加
    public static function subjectAdd($user_id,$school_id,$data){
		//判断学科的唯一性
       //判断学科大类的唯一性
        $name = empty($data['parent_id']) ? '大类' : '小类';
        $find = self::where(['admin_id'=>$user_id,'school_id'=>$school_id,'subject_name'=>$data['subject_name'],'is_del'=>0,'parent_id'=>$data['parent_id']])->first();
        if($find){
                 return ['code' => 203 , 'msg' => '此学科'.$name.'已存在'];
        }
        $add = self::insert(['admin_id' => $user_id,
                          'parent_id' => $data['parent_id'],
                          'school_id' => $school_id,
                          'subject_name' => $data['subject_name'],
                          'subject_cover' => isset($data['subject_cover'])?$data['subject_cover']:'',
                          'description' => isset($data['description'])?$data['description']:'',
						  'sort' => 1
                ]);
        if($add){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'subjectAdd' ,
                'route_url'      =>  'admin/Coursesubject/subjectAdd' ,
                'operate_method' =>  'add' ,
                'content'        =>  '添加操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '添加成功'];
        }else{
            return ['code' => 203 , 'msg' => '添加失败'];
        }
    }

    //删除
    public static function subjectDel($user_id,$data){
        //判断此学科是否有在售课程
        $find = self::where(['id'=>$data['id']])->first();
        if($find['parent_id'] != 0){
            $course1 = Coures::where(['child_id'=>$data['id'],'is_del'=>0,'status'=>1])->count();
            $course2 = CourseSchool::where(['child_id'=>$data['id'],'is_del'=>0,'status'=>1])->count();
            $course = $course1 + $course2;
        }else{
            $course1 = Coures::where(['parent_id'=>$data['id'],'is_del'=>0,'status'=>1])->count();
            $course2 = CourseSchool::where(['parent_id'=>$data['id'],'is_del'=>0,'status'=>1])->count();
            $course = $course1 + $course2;
        }
        if($course != 0){
            return ['code' => 202 , 'msg' => '关联的课程在售无法删除，请确认'];
        }
        $del = self::where(['id'=>$data['id']])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
        if($del){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'subjectDel' ,
                'route_url'      =>  'admin/Coursesubject/subjectDel' ,
                'operate_method' =>  'delete' ,
                'content'        =>  '删除操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '删除成功'];
        }else{
            return ['code' => 203 , 'msg' => '删除失败'];
        }
    }
    //单条详情
    public static function subjectOnes($data){
        $find = self::where(['id'=>$data['id'],'is_del'=>0])->first();
        if(!$find){
            return ['code' => 202 , 'msg' => '无此信息'];
        }
        return ['code' => 200 , 'msg' => '获取成功', 'data'=>$find];
    }
    //修改
    public static function subjectUpdate($user_id,$data){
        $data['update_at'] = date('Y-m-d H:i:s');
        unset($data['/admin/coursesubject/subjectUpdate']);
        $update = self::where(['id'=>$data['id']])->update($data);
        if($update){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'subjectUpdate' ,
                'route_url'      =>  'admin/Coursesubject/subjectUpdate' ,
                'operate_method' =>  'Update' ,
                'content'        =>  '修改操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }
    //学科上架下架
    public static function subjectForStatus($user_id,$data){
        $find = self::where(['id'=>$data['id'],'is_del'=>0])->first();
        if(!$find){
            return ['code' => 202 , 'msg' => '无此信息'];
        }

        $status = $find['is_open'] == 1?0:1;
        if($status == 1){
            //判断此学科是否有在售课程
            if($find['parent_id'] != 0){
                $course1 = Coures::where(['child_id'=>$data['id'],'is_del'=>0,'status'=>1])->count();
                $course2 = CourseSchool::where(['child_id'=>$data['id'],'is_del'=>0,'status'=>1])->count();
                $course = $course1 + $course2;
            }else{
                $course1 = Coures::where(['parent_id'=>$data['id'],'is_del'=>0,'status'=>1])->count();
                $course2 = CourseSchool::where(['child_id'=>$data['id'],'is_del'=>0,'status'=>1])->count();
                $course = $course1 + $course2;
            }
            if($course != 0){
                return ['code' => 202 , 'msg' => '关联的课程在售无法关闭，请确认'];
            }
        }
        $up = self::where(['id'=>$data['id']])->update(['is_open'=>$status,'update_at'=>date('Y-m-d H:i:s')]);
        if($up){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'subjectUpdate' ,
                'route_url'      =>  'admin/Coursesubject/subjectUpdate' ,
                'operate_method' =>  'Update' ,
                'content'        =>  '学科上架下架操作'.json_encode($data).'修改状态为'.$status ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }

    public static function GetSubjectNameById($school_id, $parent_id, $children_id)
    {
        //获取用户学校
        $school_id = !empty($school_id) && $school_id != 0 ? $school_id : AdminLog::getAdminInfo()->admin_user->school_id;
        $school_info = School::where("id", "=", $school_id)->select("name")->first()->toArray();;

        $one = self::select('id', 'parent_id', 'admin_id', 'school_id', 'subject_name as name', 'subject_cover as cover', 'subject_cover as cover', 'description', 'is_open', 'is_del', 'create_at')
            ->where([ 'is_del' => 0, 'is_open' => 0, 'school_id' => $school_id, 'id' => $parent_id ])
            ->first()->toArray();

        $twos = self::select('id', 'parent_id', 'admin_id', 'school_id', 'subject_name as name', 'subject_cover as cover', 'subject_cover as cover', 'description', 'is_open', 'is_del', 'create_at')
            ->where([ 'parent_id' => $one[ 'id' ], 'is_del' => 0, 'is_open' => 0, 'id' => $children_id ])->first()->toArray();

        return array(
            "school_name"   => $school_info[ 'name' ],
            "parent_name"   => $one[ 'name' ],
            "children_name" => $twos[ 'name' ]
        );
    }

    //课程模块 条件显示
    public static function couresWhere($data){
        //获取用户学校
        $school_id = isset($data['school_id']) && $data['school_id'] != 0?$data['school_id']:AdminLog::getAdminInfo()->admin_user->school_id;
        $one = self::select('id','parent_id','admin_id','school_id','subject_name as name','subject_cover as cover','subject_cover as cover','description','is_open','is_del','create_at')
            ->where(['is_del'=>0,'is_open'=>0,'school_id'=>$school_id])
            ->get()->toArray();
        //根据授权课程 获取分类
        $course = CourseSchool::select('parent_id')->where(['to_school_id'=>$school_id,'is_del'=>0])->groupBy('parent_id')->get()->toArray();
        $two=[];
        if(!empty($course)){
            //循环大类
            foreach ($course as $k=>$v){
                //大类的信息
                $twos  = self::select('id','parent_id','admin_id','school_id','subject_name as name','subject_cover as cover','subject_cover as cover','description','is_open','is_del','create_at')->where(['id'=>$v['parent_id'],'is_del'=>0,'is_open'=>0])->first();
                //判断父级科目数据是否存在
                if($twos && !empty($twos)){
                    //根据一级分类，查询授权的二级分类
                    $childcourse = CourseSchool::select('child_id')->where(['to_school_id'=>$school_id,'is_del'=>0,'parent_id'=>$twos['id']])->groupBy('child_id')->get()->toArray();
                    if(!empty($childcourse)){
                        foreach ($childcourse as $childk => $childv){
                            $twsss = self::select('id','parent_id','admin_id','school_id','subject_name as name','subject_cover as cover','subject_cover as cover','description','is_open','is_del','create_at')->where(['id'=>$childv['child_id'],'is_del'=>0,'is_open'=>0])->first();
                            $twos['childs'] = $twsss;

                        }
                    }
                    $two[] =$twos;
                }
            }
        }
        $list = self::demo($one,0,0);
        if(!empty($list) && !empty($two)){
            $listss = array_merge($list,$two);
        }else{
            $listss = !empty($list)?$list:$two;
        }
        return ['code' => 200 , 'msg' => '获取成功','data'=>$listss];
    }

    //资源模块 条件显示
    public static function couresWheres(){
        //获取用户学校
        $school_id = AdminLog::getAdminInfo()->admin_user->school_id;
        $one = self::select('id','parent_id','admin_id','school_id','sort','subject_name as name','subject_cover as cover','subject_cover as cover','description','is_open','is_del','create_at')
            ->where(['is_del'=>0,'school_id'=>$school_id])
			->orderBy(DB::Raw('case when sort =0 then 999999 else sort end'),'asc')
			->orderByDesc('id')
            ->get()->toArray();

        foreach ($one as $ks=>&$vs){
            $vs['nature'] =0;
            $vs['nature_status'] =false;
        }
        //根据授权课程 获取分类
        $course = CourseSchool::select('parent_id')->where(['to_school_id'=>$school_id,'is_del'=>0])->groupBy('parent_id')->get()->toArray();
        $two=[];
        if(!empty($course)){
            foreach ($course as $k=>$v){
                $twos = self::select('id','parent_id','admin_id','school_id','subject_name as name','subject_cover as cover','subject_cover as cover','description','is_open','is_del','create_at')->where(['id'=>$v['parent_id'],'is_del'=>0])->first();
                $twos['nature'] = 1;
                $twos['nature_status'] = true;
                $twsss = self::select('id','parent_id','admin_id','school_id','subject_name as name','subject_cover as cover','subject_cover as cover','description','is_open','is_del','create_at')->where(['parent_id'=>$v['parent_id'],'is_del'=>0])->get()->toArray();
                $twos['childs'] = $twsss;
                $two[] =$twos;
            }
        }
        $list = self::demo($one,0,0);
        if(!empty($list) && !empty($two)){
            $listss = array_merge($list,$two);
        }else{
            $listss = !empty($list)?$list:$two;
        }
        return ['code' => 200 , 'msg' => '获取成功','data'=>$listss];
    }

    //递归
    public static function demo($arr,$id,$level){
        $list =array();
        foreach ($arr as $k=>$v){
            if ($v['parent_id'] == $id){
                $aa = self::demo($arr,$v['id'],$level+1);
                if(!empty($aa)){
                    $v['level']=$level;
                    $v['childs'] = $aa;
                }
                $list[] = $v;
            }
        }
        return $list;
    }

	/*
     * @param  subjectListSort   更改学科排序
     * @param        学科id,[1,2,3,4 ...  ....]
     * @param author    sxh
     * @param ctime     2020-10-23
     * return string
     */
    public static function subjectListSort($body=[],$school_status = 1,$school_id = 1)
    {

        //判断id是否合法
        if (!isset($body['id']) || empty($body['id'])) {
            return ['code' => 202, 'msg' => 'id不合法'];
        }
		//where 条件
		$where['is_del'] = 0;
        $where['parent_id'] = 0;
        if($school_status != 1){
            $where['school_id'] = $school_id;
        }
        //获取学科id
        $id = json_decode($body['id'] , true);
        if(is_array($id) && count($id) > 0){
            //开启事务
            DB::beginTransaction();
            try {
                foreach($id as $k => $v) {
                    //数组信息封装
                    $chapters_array = [
                        'sort'      => $k+1,
                        'update_at' => date('Y-m-d H:i:s')
                    ];
                    $res = self::where('id', $v)->update($chapters_array);
                }
                if (false !== $res) {
                    //获取后端的操作员id
                    $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                    //添加日志操作
                    AdminLog::insertAdminLog([
                        'admin_id' => $admin_id,
                        'module_name' => 'subjectUpdate',
                        'route_url' => 'admin/coursesubject/subjectListSort',
                        'operate_method' => 'update',
                        'content' => '更改状态操作'.json_encode($body),
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'create_at' => date('Y-m-d H:i:s')
                    ]);
                    //事务提交
                    DB::commit();
                    return ['code' => 200, 'msg' => '更新成功'];
                } else {
                    //事务回滚
                    DB::rollBack();
                    return ['code' => 203, 'msg' => '失败'];
                }

            } catch (\Exception $ex) {
                DB::rollBack();
                return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
            }
        } else {
            return ['code' => 202, 'msg' => 'id不合法'];
        }
    }
}
