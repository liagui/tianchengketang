<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Article;
use App\Models\Articletype;
use App\Models\School;
use App\Models\Comment;
use App\Models\Answers;
use App\Models\AnswersReply;

class ArticleController extends Controller {
    //获取分类和学校
    public function schoolList(){
        $school = self::$accept_data;
        $school_id = !empty($school['school_id'])?$school['school_id']:0;
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        $data = Article::schoolANDtype($role_id,$school_id);
        return response()->json(['code' => 200 , 'msg' =>'成功','school'=>$data[0],'type'=>$data[1]]);
    }

    public function listType(){
        //获取用户网校id
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $where['is_del'] = 1;
        $where['status'] = 1;
        if($role_id == 1){
            $data = self::$accept_data;
                $where['school_id'] = isset($data['school_id'])?$data['school_id']:1;
        }else{
            $where['school_id'] = $school_id;
        }
        $typelist = Articletype::select('id as value','typename as label')
            ->where($where)
            ->get()->toArray();
        //获取分校列表
        if($role_id == 1){
            $school = School::select('id as value','name as label')->where(['is_forbid'=>1,'is_del'=>1])->get()->toArray();
        }else{
            $school = School::select('id as value','name as label')->where(['id'=>$school_id,'is_forbid'=>1,'is_del'=>1])->get()->toArray();
        }
        return response()->json(['code' => 200 , 'msg' => '获取成功','data'=>$typelist,'school'=>$school]);
    }
    public function schoolLists(){
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //获取分校列表
        if($role_id == 1){
            $school = School::select('id as value','name as label')->where(['is_forbid'=>1,'is_del'=>1])->where('id', '>', 1)->get()->toArray();
        }else{
            $school = School::select('id as value','name as label')->where(['id'=>$school_id,'is_forbid'=>1,'is_del'=>1])->get()->toArray();
        }
        return response()->json(['code' => 200 , 'msg' => '获取成功','data'=>$school]);
    }
    /*
         * @param  新增文章
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/4/28 17:35
         * return  array
         */
    public function addArticle(){
        //获取提交的参数
        try{
            $data = Article::addArticle(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  获取文章列表
         * @param  school_id   分校id
         * @param  type_id   分类id1
         * @param  title   名称
         * @param  author  苏振文
         * @param  ctime   2020/4/27 9:48
         * return  array
         */
    public function getArticleList(){
        try{
            $list = Article::getArticleList(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  文章表禁用或启用
         * @param  $id    文章id
         * @param  author  苏振文
         * @param  ctime   2020/4/28 15:4  1
         * return  array
         */
    public function editStatusToId(){
        try{
            $list = Article::editStatus(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }

    }
    /*
         * @param  文章表删除
         * @param  $id    文章id
         * @param  author  苏振文
         * @param  ctime   2020/4/28 16:35
         * return  array
         */
    public function editDelToId(){
        try{
            $list = Article::editDelToId(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  单条查询
         * @param  $id
         * @param  author  苏振文
         * @param  ctime   2020/4/28 17:36
         * return  array
         */
    public function findToId(){
        try{
            $list = Article::findOne(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  文章修改
         * @param  $data  数组参数
         * @param  author  苏振文
         * @param  ctime   2020/4/28 19:42
         * return  array
         */
    public function exitForId(){
        try{
            $list = Article::exitForId(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  是否推荐
         * @param  author  苏振文
         * @param  ctime   2020/7/18 15:36
         * return  array
         */
    public function recommendId(){
        try{
            $list = Article::recommendId(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
        * @param  导入
        * @param  $user_id     参数
        * @param  author  苏振文
        * @param  ctime   2020/6/15 15:33
        * return  array
        */
    public function ArticleLead(){
        $file = $_FILES['file'];
        $is_correct_extensiton = self::detectUploadFileMIME($file);
        $excel_extension       = substr($_FILES['file']['name'], strrpos($_FILES['file']['name'], '.')+1);   //获取excel后缀名
        if($is_correct_extensiton <= 0 || !in_array($excel_extension , ['xlsx' , 'xls'])){
            return ['code' => 202 , 'msg' => '上传文件格式非法'];
        }
        //存放文件路径
        $file_path= app()->basePath() . "/public/upload/excel/";
        //判断上传的文件夹是否建立
        if(!file_exists($file_path)){
            mkdir($file_path , 0777 , true);
        }
        //重置文件名
        $filename = time() . rand(1,10000) . uniqid() . substr($file['name'], stripos($file['name'], '.'));
        $path     = $file_path.$filename;
        //判断文件是否是通过 HTTP POST 上传的
        if(is_uploaded_file($_FILES['file']['tmp_name'])){
            //上传文件方法
            move_uploaded_file($_FILES['file']['tmp_name'], $path);
        }
        //获取excel表格中试题列表
        $exam_list = self::doImportExcel(new \App\Imports\UsersImport , $path);
        foreach ($exam_list['data'] as $k=>$v){
            if($v[0] && $v[0] > 0){
                Article::insertGetId([
                    'id' => $v[0],
                    'school_id' => 1,
                    'title' => $v[17] && !empty($v[17]) ? $v[17] : '' ,
                    'key_word' => $v[18] && !empty($v[18]) ? $v[18] : '' ,
                    'sources' =>  $v[20] && !empty($v[20]) ? $v[20] : '' ,
                    'description' => $v[19] && !empty($v[19]) ? $v[19] : '' ,
                    'text' => $v[22] && !empty($v[22]) ? $v[22] : '' ,
                    'user_id' => 1,
                ]);
            }
        }
        return response()->json(['code' => 200 , 'msg' => '导入成功']);
    }
    /*
         * @param  文章关联分类
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/6/15 20:00
         * return  array
         */
    public function ArticleToType(){
        $file = $_FILES['file'];
        $is_correct_extensiton = self::detectUploadFileMIME($file);
        $excel_extension       = substr($_FILES['file']['name'], strrpos($_FILES['file']['name'], '.')+1);   //获取excel后缀名
        if($is_correct_extensiton <= 0 || !in_array($excel_extension , ['xlsx' , 'xls'])){
            return ['code' => 202 , 'msg' => '上传文件格式非法'];
        }
        //存放文件路径
        $file_path= app()->basePath() . "/public/upload/excel/";
        //判断上传的文件夹是否建立
        if(!file_exists($file_path)){
            mkdir($file_path , 0777 , true);
        }
        //重置文件名
        $filename = time() . rand(1,10000) . uniqid() . substr($file['name'], stripos($file['name'], '.'));
        $path     = $file_path.$filename;
        //判断文件是否是通过 HTTP POST 上传的
        if(is_uploaded_file($_FILES['file']['tmp_name'])){
            //上传文件方法
            move_uploaded_file($_FILES['file']['tmp_name'], $path);
        }
        //获取excel表格中试题列表
        $exam_list = self::doImportExcel(new \App\Imports\UsersImport , $path);
        foreach ($exam_list['data'] as $k=>$v){
           $first = Article::where(['id'=>$v[1]])->first();
           if($first){
               Article::where(['id'=>$first['id']])->update(['article_type_id'=>$v[2]]);
           }
        }
        return response()->json(['code' => 200 , 'msg' => '导入成功']);
    }

	/*
         * @param  getCommentList 获取评论列表
         * @param  $school_id     网校id
         * @param  $status        0禁用 1启用
         * @param  $name          教师/课程
         * @param  author  sxh
         * @param  ctime   2020/10/29
         * return  array
         */
    public function getCommentList(){
        try{
            $list = Comment::getCommentList(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
        * @param  editCommentToId 评论表禁用或启用
        * @param  $id    文章id
        * @param  author  sxh
        * @param  ctime   2020/4/28 15:4  1
        * return  array
        */
    public function editCommentToId(){
        try{
            $list = Comment::editCommentStatus(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }

    }

	/*
         * @param  getAnswersList 获取问答列表
         * @param  $is_top        1置顶
         * @param  $status        1显示 2不显示
         * @param  $page
         * @param  $pagesize
         * @param  author  sxh
         * @param  ctime   2020/10/30
         * return  array
         */
    public function getAnswersList(){

        try{
            $list = Answers::getAnswersList(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

	/*
        * @param  editAnswersStatus 问答列表  显示/不显示
        * @param  $id    问答id
        * @param  author  sxh
        * @param  ctime   2020/10/31
        * return  array
        */
    public function editAnswersStatus(){
        try{
            $list = Answers::editAnswersStatus(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }

    }

    /*
        * @param  editAnswersTopStatus 问答列表  置顶
        * @param  $id    问答id
        * @param  author  sxh
        * @param  ctime   2020/10/31
        * return  array
        */
    public function editAnswersTopStatus(){
        try{
            $list = Answers::editAnswersTopStatus(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }

    }

    /*
        * @param  addAnswersReply 问答回复
        * @param  $answers_id    问答id
        * @param  $content       回复内容
        * @param  author  sxh
        * @param  ctime   2020/10/31
        * return  array
        */
    public function addAnswersReply(){
        try{
            $list = AnswersReply::addAnswersReply(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }

    }

    /*
         * @param  getAnswersIsCheckList 获取问答审核列表
         * @param  $is_check   1通过 2未通过 3全部
         * @param  $page
         * @param  $pagesize
         * @param  author  sxh
         * @param  ctime   2020/11/2
         * return  array
         */
    public function getAnswersIsCheckList(){
        try{
            $list = Answers::getAnswersIsCheckList(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
        * @param  editAnswersIsCheckStatus 问答审核列表  通过/不通过
        * @param  $id         问答id
        * @param  $is_check   问答审核状态  1审核通过 2未审核
        * @param  author  sxh
        * @param  ctime   2020/10/31
        * return  array
        */
    public function editAnswersIsCheckStatus(){
        try{
            $list = Answers::editAnswersIsCheckStatus(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }

    }

    /*
       * @param  editAllAnswersIsCheckStatus 问答一键审核功能
       * @param  author  sxh
       * @param  ctime   2020/10/31
       * return  array
       */
    public function editAllAnswersIsCheckStatus(){
        try{
            $list = Answers::editAllAnswersIsCheckStatus();
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }

    }

	/*
       * @param  delAllAnswersStatus 批量删除功能
       * @param  author  sxh
       * @param  ctime   2020/11/4
       * return  array
       */
    public function delAllAnswersStatus(){
        try{
            $list = Answers::delAllAnswersStatus(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }

    }



    /**
     * 获取新闻列表
     * @return array
     */
    public function getListByIndexSet()
    {
        $topNum = empty(self::$accept_data['top_num']) ? 1 : self::$accept_data['top_num'];
        $isRecommend = isset(self::$accept_data['is_recommend']) ? self::$accept_data['is_recommend'] : 1;
        $schoolId = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

        $where = [
            'ld_article_type.school_id' => $schoolId,
            'ld_article_type.status' => 1,
            'ld_article_type.is_del' => 1,
            'ld_article.status' => 1,
            'ld_article.is_del' => 1
        ];

        $newsListQuery = Articletype::leftJoin('ld_article','ld_article.article_type_id','=','ld_article_type.id')
            ->where($where)
            ->select(
                'ld_article.id', 'ld_article.article_type_id', 'ld_article.title', 'ld_article.share', 'ld_article.watch_num',
                'ld_article.create_at', 'ld_article.image', 'ld_article.description', 'ld_article_type.typename'
            );
        if ($isRecommend == 1) {
            $newsListQuery->orderBy('ld_article.is_recommend','desc');
        }
        $newsList = $newsListQuery->orderBy('ld_article.update_at','desc')
            ->limit($topNum)
            ->get();

        if(!empty($newsList)){
            foreach ($newsList as $k => &$new) {
                if($new['share'] == null || $new['share'] == 'null'){
                    $new['share'] = 0;
                }
                if($new['watch_num'] == null || $new['watch_num'] == 'null'){
                    $new['watch_num'] = 0;
                }
                $new['share'] = $new['share'] + $new['watch_num'];
            }
        }
        return ['code'=>200,'msg'=>'Success','data'=>$newsList];
    }

}
