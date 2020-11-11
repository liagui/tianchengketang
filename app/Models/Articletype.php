<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
class Articletype extends Model {
    //指定别的表名
    public $table = 'ld_article_type';
    //时间戳设置
    public $timestamps = false;
    /*
         * @param  获取分类列表
         * @param  school_id   分校id
         * @param  title   名称
         * @param  author  苏振文
         * @param  ctime   2020/4/27 9:48
         * return  array
         */
    public static function getArticleList($data){
        //获取用户网校id
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        $where['ld_article_type.is_del'] = 1;
        if($role_id == 1){
           if(!empty($data['school_id']) && $data['school_id'] != ''){
               $where['ld_article_type.school_id'] = $data['school_id'];
           }
        }else{
           $where['ld_article_type.school_id'] = $school_id;
        }
       $whereschool = ($role_id == 1)?(empty($data['school_id']))?'':$data['school_id']:$school_id;
        $total = self::leftJoin('ld_school','ld_school.id','=','ld_article_type.school_id')
            ->leftJoin('ld_admin','ld_admin.id','=','ld_article_type.user_id')
            ->where($where)->count();
        $typelist = self::select('ld_article_type.id','ld_article_type.typename','ld_article_type.status','ld_article_type.description','ld_school.name','ld_admin.username')
            ->leftJoin('ld_school','ld_school.id','=','ld_article_type.school_id')
            ->leftJoin('ld_admin','ld_admin.id','=','ld_article_type.user_id')
            ->where($where)
            ->orderBy('ld_article_type.id','desc')
            ->offset($offset)->limit($pagesize)->get();
         //获取分校列表
        if($role_id == 1){
            $school = School::select('id as value','name as label')->where(['is_forbid'=>1,'is_del'=>1])->get()->toArray();
        }else{
            $school = School::select('id as value','name as label')->where(['id'=>$school_id,'is_forbid'=>1,'is_del'=>1])->get()->toArray();
        }
        //分页
        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$total
        ];
        return ['code' => 200 , 'msg' => '获取成功','data'=>$typelist,'school'=>$school,'where'=>$whereschool,'page'=>$page];
    }
    /*
         * @param  修改状态
         * @param  $id 分类id
         * @param  author  苏振文
         * @param  ctime   2020/4/30 14:22
         * return  array
         */
    public static function editStatusToId($data){
        if(empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数为空或格式错误'];
        }
        $find = self::where(['id'=>$data['id'],'is_del'=>1])->first();
        if(!$find){
            return ['code' => 201 , 'msg' => '参数错误'];
        }
        $status = ($find['status']==1)?0:1;
        $up = self::where(['id'=>$data['id']])->update(['status'=>$status,'update_at'=>date('Y-m-d H:i:s')]);
        if($up){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Articletype' ,
                'route_url'      =>  'admin/Articletype/editStatusToId' ,
                'operate_method' =>  'update' ,
                'content'        =>  '文章分类状态'.json_encode($data) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }

    /*
         * @param  软删除
         * @param  $id     参数
         * @param  author  苏振文
         * @param  ctime   2020/4/30 15:38
         * return  array
         */
    public static function editDelToId($data){
        if(empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数为空或格式错误'];
        }
        $articleOnes = self::where(['id'=>$data['id']])->first();
        if(!$articleOnes){
            return ['code' => 201 , 'msg' => '参数错误'];
        }
        $key = 'article_editDelToId'.$data['id'];
        if(Redis::get($key)){
            return json_decode(Redis::get('article_editDelToId'.$data['id']),true);
        }else{
            //判断分类下是否有文章
            $article = Article::where(['article_type_id'=>$data['id'],'is_del'=>1])->get()->toArray();
            if(!empty($article[0])){
                Redis::setex($key,'60',json_encode(['code' => 203 , 'msg' => '此分类下有文章，无法删除']));
                return ['code' => 203 , 'msg' => '此分类下有文章，无法删除'];
            }else{
                if($articleOnes['is_del'] == 0){
                    Redis::setex($key,'60',json_encode(['code' => 200 , 'msg' => '删除成功']));
                    return ['code' => 200 , 'msg' => '删除成功'];
                }
                $update = self::where(['id'=>$data['id']])->update(['is_del'=>0,'update_at'=>date('Y-m-d H:i:s')]);
                if($update){
                    //获取后端的操作员id
                    $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
                    //添加日志操作
                    AdminLog::insertAdminLog([
                        'admin_id'       =>   $admin_id  ,
                        'module_name'    =>  'Articletype' ,
                        'route_url'      =>  'admin/Articletype/editDelToId' ,
                        'operate_method' =>  'delete' ,
                        'content'        =>  '软删除文章分类'.json_encode($data) ,
                        'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                        'create_at'      =>  date('Y-m-d H:i:s')
                    ]);
                    Redis::setex($key,'60',json_encode(['code' => 200 , 'msg' => '删除成功']));
                    return ['code' => 200 , 'msg' => '删除成功'];
                }else{
                    return ['code' => 202 , 'msg' => '删除失败'];
                }
            }
        }
    }
    /*
         * @param  添加分类
         * @param  $typename  类型名称
         * @param  $description  类型简介
         * @param  author  苏振文
         * @param  ctime   2020/4/30 14:44
         * return  array
         */
    public static function addType($data){
        $data['user_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        if($role_id != 1){
            $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            $data['school_id'] = $school_id ;
        }
        unset($data['/admin/article/addType']);
        if($data['typename'] == ''){
            return ['code' => 201 , 'msg' => '名称不能为空'];
        }
        $ones = self::where($data)->first();
        if($ones){
            return ['code' => 202 , 'msg' => '数据已存在'];
        }else {
            unset($data['id']);
            $data['description'] = isset($data['description'])?$data['description']:'';
            $add = self::insert($data);
            if($add){
                //添加日志操作
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $data['user_id']  ,
                    'module_name'    =>  'Articletype' ,
                    'route_url'      =>  'admin/Articletype/addType' ,
                    'operate_method' =>  'insert' ,
                    'content'        =>  '添加文章分类'.json_encode($data) ,
                    'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                return ['code' => 200 , 'msg' => '添加成功'];
            }else{
                return ['code' => 202 , 'msg' => '添加失败'];
            }
        }
    }
    /*
         * @param  修改信息
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/4/30 15:15
         * return  array
         */
    public static function editForId($data){
        //判断id
        if(empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数id为空或格式不正确'];
        }
        if(empty($data['typename']) || $data['typename']==''){
            return ['code' => 201 , 'msg' => '参数名称为空或格式不正确'];
        }
        $id = $data['id'];
        unset($data['id']);
        unset($data['schoolname']);
        unset($data['page']);
        unset($data['pageSize']);
        unset($data['/admin/article/exitTypeForId']);
        $data['update_at'] = date('Y-m-d H:i:s');
        $update = self::where(['id'=>$id])->update($data);
        if($update){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Articletype' ,
                'route_url'      =>  'admin/Articletype/editForId' ,
                'operate_method' =>  'update' ,
                'content'        =>  '文章分类修改id为'.$id.json_encode($data) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '修改失败'];
        }
    }

    /*
         * @param  单条查询
         * @param  $id
         * @param  author  苏振文
         * @param  ctime   2020/5/4 10:02
         * return  array
         */
    public static function oneFind($data){
        //判断id
        if(empty($data['id'])){
            return ['code' => 201 , 'msg' => '参数id为空或格式不正确'];
        }
        //缓存
        $key = 'articletype_oneFind_'.$data['id'];
//        if(Redis::get($key)) {
//            return ['code' => 200 , 'msg' => '获取成功','data'=>json_decode(Redis::get($key),true)];
//        }else{
            $find = self::select('ld_article_type.id','ld_article_type.typename','ld_article_type.description','ld_school.id as school_id')
                ->leftJoin('ld_school','ld_school.id','=','ld_article_type.school_id')
                ->where(['ld_article_type.id'=>$data['id'],'ld_article_type.is_del'=>1])
                ->first();

//            Redis::setex($key,60,json_encode($find));
            return ['code' => 200 , 'msg' => '获取成功','data'=>$find];
//        }
    }
}
