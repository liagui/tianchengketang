<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
class Article extends Model {
    //指定别的表名
    public $table = 'ld_article';
    //时间戳设置
    public $timestamps = false;
    /*
         * @param  获取文章列表
         * @param  school_id   分校id
         * @param  type_id   分类id
         * @param  title   名称
         * @param  author  苏振文
         * @param  ctime   2020/4/27 9:48
         * return  array
         */
    public static function getArticleList($data){
        //获取用户网校id
        $data['role_id'] = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //每页显示的条数
        $pagesize = (int)isset($data['pageSize']) && $data['pageSize'] > 0 ? $data['pageSize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        $total = self::leftJoin('ld_school','ld_school.id','=','ld_article.school_id')
            ->leftJoin('ld_article_type','ld_article_type.id','=','ld_article.article_type_id')
            ->leftJoin('ld_admin','ld_admin.id','=','ld_article.user_id')
            ->where(function($query) use ($data,$school_id) {
                if($data['role_id'] == 1){
                    if(!empty($data['school_id']) && $data['school_id'] != 0){
                        $query->where('ld_article.school_id',$data['school_id']);
                    }
                }else{
                    $query->where('ld_article.school_id',$school_id);
                }
                if(isset($data['type_id']) && !empty($data['type_id'] != ''&&$data['type_id'] != 0 )){
                    $query->where('ld_article.article_type_id',$data['type_id']);
                }
                if(isset($data['title']) && !empty($data['title'] != '')){
                    $query->where('ld_article.title','like','%'.$data['title'].'%')
                        ->orwhere('ld_article.id',$data['title']);
                }
            })
            ->where(['ld_article.is_del'=>1,'ld_article_type.is_del'=>1,'ld_article_type.status'=>1,'ld_admin.is_del'=>1,'ld_admin.is_forbid'=>1,'ld_school.is_del'=>1,'ld_school.is_forbid'=>1])
            ->count();
        if($total > 0){
            $list = self::select('ld_article.id','ld_article.title','ld_article.create_at','ld_article.status','ld_article.is_recommend','ld_school.name','ld_article_type.typename','ld_admin.username')
                ->leftJoin('ld_school','ld_school.id','=','ld_article.school_id')
                ->leftJoin('ld_article_type','ld_article_type.id','=','ld_article.article_type_id')
                ->leftJoin('ld_admin','ld_admin.id','=','ld_article.user_id')
                ->where(function($query) use ($data,$school_id) {
                    //判断总校 查询所有或一个分校
                    if($data['role_id'] == 1){
                        if(!empty($data['school_id']) && $data['school_id'] != 0){
                            $query->where('ld_article.school_id',$data['school_id']);
                        }
                    }else{
                        //分校查询当前学校
                        $query->where('ld_article.school_id',$school_id);
                    }
                    if(isset($data['type_id']) && !empty($data['type_id'] != '' &&$data['type_id'] != 0)){
                        $query->where('ld_article.article_type_id',$data['type_id']);
                    }
                    if(isset($data['title']) && !empty($data['title'] != '')){
                        $query->where('ld_article.title','like','%'.$data['title'].'%')
                            ->orwhere('ld_article.id',$data['title']);
                    }
                })
                ->where(['ld_article.is_del'=>1,'ld_article_type.is_del'=>1,'ld_article_type.status'=>1,'ld_admin.is_del'=>1,'ld_admin.is_forbid'=>1,'ld_school.is_del'=>1,'ld_school.is_forbid'=>1])
                ->orderBy('ld_article.id','desc')
                ->offset($offset)->limit($pagesize)->get();
            foreach ($list as $k=>&$v){
                $acc = Articleaccessory::select('id','accessory_name as name','accessory_url as url')->where(['article_id'=>$v['id'],'status'=>0])->get();
                $v['accessory'] = $acc;
            }
        }else{
            $list=[];
        }
        //分校列表
        $sschool_id = isset($data['school_id'])?$data['school_id']:$school_id;
        $schooltype = self::schoolANDtype($data['role_id'],$sschool_id);
        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$total
        ];
        return ['code' => 200 , 'msg' => '查询成功','data'=>$list,'school'=>$schooltype[0],'type'=>$schooltype[1],'where'=>$data,'page'=>$page];
    }
    /*
         * @param 修改文章状态
         * @param  $id 文章id
         * @param  author  苏振文
         * @param  ctime   2020/4/28 15:43
         * return  array
         */
    public static function editStatus($data){
        if(empty($data['id']) || !isset($data['id'])){
            return ['code' => 201 , 'msg' => '参数为空或格式错误'];
        }
        $articleOnes = self::where(['id'=>$data['id']])->first();
        if(!$articleOnes){
            return ['code' => 201 , 'msg' => '参数不对'];
        }
        $status = ($articleOnes['status']==1)?0:1;
        $update = self::where(['id'=>$data['id']])->update(['status'=>$status,'update_at'=>date('Y-m-d H:i:s')]);
        if($update){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Article' ,
                'route_url'      =>  'admin/Article/editStatus' ,
                'operate_method' =>  'update' ,
                'content'        =>  '操作'.json_encode($data) ,
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
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/4/28 17:33
         * return  array
         */
    public static function editDelToId($data){
        //判断分类id
        if(empty($data['id'])|| !isset($data['id'])){
            return ['code' => 201 , 'msg' => '参数为空或格式错误'];
        }
        $articleOnes = self::where(['id'=>$data['id']])->first();
        if(!$articleOnes){
            return ['code' => 204 , 'msg' => '参数不正确'];
        }
        if($articleOnes['is_del'] == 0){
            return ['code' => 200 , 'msg' => '删除成功'];
        }
        $update = self::where(['id'=>$data['id']])->update(['is_del'=>0,'update_at'=>date('Y-m-d H:i:s')]);
        if($update){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Article' ,
                'route_url'      =>  'admin/Article/editDelToId' ,
                'operate_method' =>  'delete' ,
                'content'        =>  '软删除id为'.$data['id'],
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '删除成功'];
        }else{
            return ['code' => 202 , 'msg' => '删除失败'];
        }
    }
    /*
         * @param  新增
         * @param  school_id   分校id
         * @param  article_type_id   分类id
         * @param  title   标题
         * @param  image   封面
         * @param  key_word   关键词
         * @param  sources  来源
         * @param  accessory   附件
         * @param  description  摘要
         * @param  text   正文
         * @param  author  苏振文
         * @param  ctime   2020/4/28 17:45
         * return  array
         */
    public static function addArticle($data){
        //判断分类id
        if(empty($data['article_type_id']) || !isset($data['article_type_id'])){
            return ['code' => 201 , 'msg' => '请正确选择分类'];
        }
        //判断标题
        if(empty($data['title']) || !isset($data['title'])){
            return ['code' => 201 , 'msg' => '标题不能为空'];
        }
        //判断图片
        if(empty($data['image']) || !isset($data['image'])){
            return ['code' => 201 , 'msg' => '图片不能为空'];
        }
        //判断摘要
        if(empty($data['description']) || !isset($data['description'])){
            return ['code' => 201 , 'msg' => '摘要不能为空'];
        }
        //缓存查出用户id和分校id
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        if($role_id != 1){
            $data['school_id'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        }
        unset($data['/admin/article/addArticle']);
        $data['user_id'] = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        $data['update_at'] = date('Y-m-d H:i:s');
        $data['text'] = isset($data['text'])?$data['text']:'';
        $access = $data['accessory'];
        unset($data['accessory']);
        $add = self::insertGetId($data);
        if(isset($access) || !empty($access)){
            $accessory = json_decode($access,true);
            foreach ($accessory as $k=>$v){
                Articleaccessory::insert([
                                    'article_id' => $add,
                                    'accessory_name' => $v['name'],
                                    'accessory_url' => $v['url'],
                                 ]);
            }
        }
        if($add){
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $data['user_id']  ,
                'module_name'    =>  'Article' ,
                'route_url'      =>  'admin/Article/addArticle' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增数据'.json_encode($data) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '添加成功'];
        }else{
            return ['code' => 202 , 'msg' => '添加失败'];
        }
    }
    /*
         * @param  单条查询
         * @param  $id    文章id
         * @param  author  苏振文
         * @param  ctime   2020/4/28 19:30
         * return  array
         */
    public static function findOne($data){
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        if(empty($data['id']) || !isset($data['id'])){
            return ['code' => 201 , 'msg' => '参数为空'];
        }
        $schooltype = self::schoolANDtype($role_id);
        $find = self::select('ld_article.*','ld_school.name','ld_article_type.typename')
            ->leftJoin('ld_school','ld_school.id','=','ld_article.school_id')
            ->leftJoin('ld_article_type','ld_article_type.id','=','ld_article.article_type_id')
            ->where(['ld_article.id'=>$data['id'],'ld_article.is_del'=>1,'ld_school.is_del'=>1])
            ->first();
        $find['accessory'] = Articleaccessory::select('id','accessory_name as name','accessory_url as url')->where(['article_id'=>$find['id'],'status'=>0])->get()->toArray();
        if($find){
            unset($find['user_id'],$find['share'],$find['status'],$find['is_del'],$find['create_at'],$find['update_at']);
            return ['code' => 200 , 'msg' => '获取成功','data'=>$find,'school'=>$schooltype[0],'type'=>$schooltype[1]];
        }else{
            return ['code' => 202 , 'msg' => '获取失败'];
        }

    }
    /*
         * @param  单条修改
         * @param  id   文章id
         * @param  article_type_id   分类id
         * @param  title   标题
         * @param  image   封面
         * @param  key_word   关键词
         * @param  sources  来源
         * @param  accessory   附件
         * @param  description  摘要
         * @param  text   正文
         * @param  author  苏振文
         * @param  ctime   2020/4/28 19:43
         * return  array
         */
    public static function exitForId($data){
        if(empty($data['id'])){
            return ['code' => 201 , 'msg' => 'id为空或格式不正确'];
        }
        //判断分类id
        if(empty($data['article_type_id'])){
            return ['code' => 201 , 'msg' => '分类为空或格式不正确'];
        }
        //判断标题
        if(empty($data['title'])){
            return ['code' => 201 , 'msg' => '标题为空或格式不正确'];
        }
        //判断图片
        if(empty($data['image'])){
            return ['code' => 201 , 'msg' => '图片为空或格式不正确'];
        }
        //判断摘要
        if(empty($data['description'])){
            return ['code' => 201 , 'msg' => '摘要为空或格式不正确'];
        }
        //判断正文
        if(empty($data['text'])){
            return ['code' => 201 , 'msg' => '正文为空或格式不正确'];
        }
        $data['update_at'] = date('Y-m-d H:i:s');
        $id = $data['id'];
        unset($data['id']);
        unset($data['/admin/article/exitForId']);
        $data['key_word'] = isset($data['key_word'])?$data['key_word']:'';
        $access = isset($data['accessory'])?$data['accessory']:'';
        unset($data['accessory']);
        $data['text'] = isset($data['text'])?$data['text']:'';
        $res = self::where(['id'=>$id])->update($data);
        if(isset($access) || empty($access)){
            Articleaccessory::where('article_id',$id)->update(['status'=>1]);
            $accessory = json_decode($access,true);
            foreach ($accessory as $k=>$v){
                $one = Articleaccessory::where(['article_id'=>$id,'accessory_name'=>$v['name'],'accessory_url'=>$v['url']])->first();
                if(!empty($one)){
                    Articleaccessory::where('id',$one['id'])->update(['status'=>0]);
                }else{
                    Articleaccessory::insert([
                        'article_id' => $id,
                        'accessory_name' => $v['name'],
                        'accessory_url' => $v['url'],
                    ]);
                }
            }
        }
        if($res){
            //获取后端的操作员id
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>   $admin_id  ,
                'module_name'    =>  'Article' ,
                'route_url'      =>  'admin/Article/exitForId' ,
                'operate_method' =>  'update' ,
                'content'        =>  '修改id'.$id.'的内容,'.json_encode($data),
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code' => 200 , 'msg' => '更新成功'];
        }else{
            return ['code' => 202 , 'msg' => '更新失败'];
        }
    }


    /*
         * @param  推荐
         * @param  author  苏振文
         * @param  ctime   2020/7/18 15:37
         * return  array
         */
    public static function recommendId($data){
        if(empty($data['id'])){
            return ['code' => 201 , 'msg' => 'id为空或格式不正确'];
        }
        $article = self::where(['id'=>$data['id']])->first();
        $recom = $article['is_recommend'] == 1 ? 0 : 1;
        $up = self::where(['id'=>$data['id']])->update(['is_recommend'=>$recom]);
        if($up){
            return ['code' => 200 , 'msg' => '状态修改成功'];
        }else{
            return ['code' => 202 , 'msg' => '状态修改失败'];
        }
    }

    public static function schoolANDtype($role_id,$school_id=0){
        if($role_id == 1){
            if($school_id != 0){
                $type = Articletype::select('id as value','typename as label')->where(['status'=>1,'is_del'=>1,'school_id'=>$school_id])->get()->toArray();
            }else{
                $type = Articletype::select('id as value','typename as label')->where(['status'=>1,'is_del'=>1])->get()->toArray();
            }
            $school = School::select('id as value','name as label')->where(['is_forbid'=>1,'is_del'=>1])->get()->toArray();
        }else{
            $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            $school = School::select('id as value','name as label')->where(['id'=>$school_id,'is_forbid'=>1,'is_del'=>1])->get()->toArray();
            $type = Articletype::select('id as value','typename as label')->where(['school_id'=>$school_id,'status'=>1,'is_del'=>1])->get()->toArray();
        }
        $data=[
            0 => $school,
            1 => $type
        ];
        return $data;
    }



}
