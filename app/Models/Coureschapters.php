<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Coureschapters extends Model {
    //指定别的表名
    public $table = 'ld_course_chapters';
    //时间戳设置
    public $timestamps = false;
    /*=============章================*/

    //章节列表
    public static function chapterList($data){
        $nature = isset($data['nature'])?$data['nature']:0;
        if($nature == 1){
            $course = CourseSchool::where(['id'=>$data['course_id']])->first()->toArray();
            $data['course_id'] = $course['course_id'];
        }
        $lists = self::where(['course_id'=>$data['course_id'],'is_del'=>0,])->orderBy(DB::Raw('case when sort =0 then 999999 else sort end'),'asc')->get()->toArray();
        $arr = self::demo($lists,0,0);
        return ['code' => 200 , 'msg' => '查询成功','data'=>$arr];
    }
    //添加章  章名 课程id 学校id根据课程id查询
    public static function chapterAdd($data){
        $course = Coures::select('school_id','nature')->where(['id'=>$data['course_id']])->first();
        //查询最后一条
        $first = self::where(['parent_id'=>0,'course_id'=>$data['course_id']])->orderBy('sort','desc')->first();
        $add = self::insert([
            'admin_id' => isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0,
            'school_id' => $course['school_id'],
            'course_id' => $data['course_id'],
            'name' => $data['name'],
            'sort' => $first['desc']+1,
        ]);
        if($add){
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id,
                'module_name'    =>  'chapterAdd',
                'route_url'      =>  'admin/Course/chapterAdd' ,
                'operate_method' =>  'Add' ,
                'content'        =>  '添加章或节操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '添加成功'];
        }else{
            return ['code' => 201 , 'msg' => '添加失败'];
        }
    }
    //删除章/节  课程id 章/节id
    public static function chapterDel($data){
        $course = Coures::select('school_id','nature')->where(['id'=>$data['course_id']])->first();
        if($course['nature'] == 1){
            return ['code' => 201 , 'msg' => '授权课程，无法操作'];
        }
        $del = self::where(['id'=>$data['id']])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
        if($del){
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'chapterDel' ,
                'route_url'      =>  'admin/Course/chapterDel' ,
                'operate_method' =>  'Del' ,
                'content'        =>  '删除章或节操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '删除成功'];
        }else{
            return ['code' => 202 , 'msg' => '删除失败'];
        }

    }
    //修改章
    public static function chapterUpdate($data){
        if(!isset($data['name']) || empty($data['name'])){
            return ['code' => 201 , 'msg' => '请添加章名'];
        }
        $course = Coures::select('school_id','nature')->where(['id'=>$data['course_id']])->first();
        if($course['nature'] == 1){
            return ['code' => 201 , 'msg' => '授权课程，无法操作'];
        }
        $del = self::where(['id'=>$data['id']])->update(['name'=>$data['name'],'update_at'=>date('Y-m-d H:i:s')]);
        if($del){
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'chapterUpdate' ,
                'route_url'      =>  'admin/Course/chapterUpdate' ,
                'operate_method' =>  'Update' ,
                'content'        =>  '修改章信息操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }

    /*============节==============*/
    //单条详情
    public static function sectionFirst($data){
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '请传参'];
        }
        $list = self::where(['id'=>$data['section_id'],'is_del'=>0])->first();
        //资源名称resource_id
        $resource='';
        if($list['resource_id'] != ''){
            $r= Video::select('resource_name')->where(['id'=>$list['resource_id'],'is_del'=>0])->first();
            $resource = $r['resource_name'];
        }
        $list['mt_video_name'] = $resource;
        //查询录播课程名称
        $section = Couresmaterial::select('material_name as name','material_size as size','material_url as url','type')->where(['parent_id'=>$data['section_id'],'mold'=>1,'is_del'=>0])->get();
        foreach ($section as $k=>&$v){
            if($v['type'] == 1){
                $v['typeName'] = '材料';
            }
            if($v['type'] == 2){
                $v['typeName'] = '辅料';
            }
            if($v['type'] == 3){
                $v['typeName'] = '其他';
            }
        }
        $list['filearr'] = $section;
        return ['code' => 200 , 'msg' => '查询成功','data'=>$list];
    }
    //添加节
    public static function sectionAdd($data){
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '请传参'];
        }
        if(!isset($data['course_id']) || empty($data['course_id'])){
            return ['code' => 201 , 'msg' => '请选择课程'];
        }

        $course = Coures::select('school_id','nature')->where(['id'=>$data['course_id']])->first();

        if(!isset($data['chapter_id']) || empty($data['chapter_id'])){
            return ['code' => 201 , 'msg' => '请选择章'];
        }
        if(!isset($data['type']) || empty($data['type'])){
            return ['code' => 201 , 'msg' => '请选择节类型'];
        }
        if(!isset($data['name']) || empty($data['name'])){
            return ['code' => 201 , 'msg' => '请填写节名称'];
        }
//        if(!isset($data['resource_id']) || empty($data['resource_id'])){
//            return ['code' => 201 , 'msg' => '请选择资源'];
//        }
        if($course['sale_price'] ==0){
            $is_free = 0;
        }else{
            $is_free = 1;
        }
        //查询最后一个小节
        $first = self::where(['parent_id'=>$data['chapter_id'],'course_id'=>$data['course_id']])->orderBy('sort','desc')->first();
        try{
            DB::beginTransaction();
            $insert = self::insertGetId([
                'admin_id' => isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0,
                'school_id' => $course['school_id'],
                'parent_id' => $data['chapter_id'],
                'course_id' => $data['course_id'],
                'resource_id    ' => isset($data['resource_id'])?$data['resource_id']:0,
                'name' => $data['name'],
                'type' => $data['type'],
                'is_free' => isset($data['is_free'])?$data['is_free']:$is_free,
                'sort' => $first['sort'] + 1
            ]);
            //判断小节资料
            if(!empty($data['filearr'])){
                $filearr = json_decode($data['filearr'],true);
                foreach ($filearr as $k=>$v){
                    Couresmaterial::insert([
                        'admin_id' => isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0,
                        'school_id' => $course['school_id'],
                        'parent_id' => $insert,
                        'course_id' => $data['course_id'],
                        'type' => $v['type'],
                        'material_name' => $v['name'],
                        'material_size' => $v['size'],
                        'material_url' => $v['url'],
                        'mold' => 1,
                    ]);
                }
            }
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'sectionAdd' ,
                'route_url'      =>  'admin/Course/sectionAdd' ,
                'operate_method' =>  'Add' ,
                'content'        =>  '添加节操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return ['code' => 200 , 'msg' => '添加成功'];
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //修改节
    public static function sectionUpdate($data){
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '参数为空'];
        }
        unset($data['/admin/course/sectionUpdate']);
        $filearr = json_decode($data['filearr'],true);
        unset($data['filearr']);
        unset($data['mt_video_name']);
        $data['update_at'] = date('Y-m-d H:i:s');
        $up = self::where(['id'=>$data['id']])->update($data);
        if($up){
            Couresmaterial::where(['parent_id'=>$data['id'],'mold'=>1])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
            if(!empty($filearr)){
                foreach ($filearr as $k=>$v){
                    $materialones = Couresmaterial::where(['material_url'=>$v['url'],'mold'=>1])->first();
                    if($materialones){
                        Couresmaterial::where(['id'=>$materialones['id']])->update(['is_del'=>0,'update_at'=>date('Y-m-d H:i:s')]);
                    }else{
                        Couresmaterial::insert([
                            'admin_id' => isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0,
                            'school_id' => 0,
                            'parent_id' => $data['id'],
                            'course_id' => 0,
                            'type' => $v['type'],
                            'material_name' => $v['name'],
                            'material_size' => $v['size'],
                            'material_url' => $v['url'],
                            'mold' => 1,
                        ]);
                    }
                }
            }
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'sectionUpdate' ,
                'route_url'      =>  'admin/Course/sectionUpdate' ,
                'operate_method' =>  'Update' ,
                'content'        =>  '修改节操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }
    //小节资料删除
    public static function sectionDataDel($data){
        if(!isset($data) || empty($data)){
            return ['code' => 201 , 'msg' => '参数为空'];
        }
        if(!isset($data['course_id']) || empty($data['course_id'])){
            return ['code' => 201 , 'msg' => '请选择课程'];
        }
        $course = Coures::select('nature')->where(['id'=>$data['course_id']])->first();
        if($course['nature'] == 1){
            return ['code' => 202 , 'msg' => '授权课程，无法操作'];
        }
        $del = Couresmaterial::where(['id'=>$data['id']])->update(['is_del'=>1,'update_at'=>date('Y-m-d H:i:s')]);
        if($del){
            $user_id = AdminLog::getAdminInfo()->admin_user->cur_admin_id;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $user_id  ,
                'module_name'    =>  'sectionDataDel' ,
                'route_url'      =>  'admin/Course/sectionDataDel' ,
                'operate_method' =>  'Del' ,
                'content'        =>  '删除节操作'.json_encode($data) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '删除成功'];
        }else{
            return ['code' => 202 , 'msg' => '删除失败'];
        }
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
                }else{
                    $v['childs'] = [];
                }
                $list[] = $v;
            }
        }
        return $list;
    }

	 /*
     * @param  updateChapterListSort   更改章节排序
     * @param        章节id,[1,2,3,4 ...  ....]
     * @param author    sxh
     * @param ctime     2020-10-24
     * return string
     */
    public static function updateChapterListSort($body=[])
    {
        //判断id是否合法
        if (!isset($body['id']) || empty($body['id'])) {
            return ['code' => 202, 'msg' => 'id不合法'];
        }
        //获取章节id
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
                        'module_name' => 'updateChapterListSort',
                        'route_url' => 'admin/course/updateChapterListSort',
                        'operate_method' => 'update',
                        'content' => '更改排序操作'.json_encode($body),
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
