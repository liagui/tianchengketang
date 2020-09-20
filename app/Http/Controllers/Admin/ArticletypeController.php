<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Articletype;
use App\Models\CouresSubject;

class ArticletypeController extends Controller {
    /*
         * @param  获取分类列表
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/4/29 14:29
         * return  array
         */
    public function getTypeList(){
        try{
            $list = Articletype::getArticleList(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  禁用&启用分类
         * @param  $id
         * @param  $type 0禁用1启用
         * @param  author  苏振文
         * @param  ctime   2020/4/30 14:19
         * return  array
         */
    public function editStatusForId(){
        try{
            $list = Articletype::editStatusToId(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  删除分类
         * @param  $id 分类id
         * @param  author  苏振文
         * @param  ctime   2020/4/30 14:31
         * return  array
         */
    public function exitDelForId(){
        try{
            $list = Articletype::editDelToId(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  添加
         * @param  author  苏振文
         * @param  ctime   2020/4/30 14:43
         * return  array
         */
    public function addType(){
        try{
            $list = Articletype::addType(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  修改分类信息
         * @param  author  苏振文
         * @param  ctime   2020/4/30 15:11
         * return  array
         */
    public function exitTypeForId(){
        try{
            $list = Articletype::editForId(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /*
         * @param  单条查询
         * @param  $id 类型id
         * @param  author  苏振文
         * @param  ctime   2020/5/4 10:00
         * return  array
         */
    public function OnelistType(){
        try{
            $list = Articletype::oneFind(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
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
    public function ArticleTypeLead(){
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
        //获取excel表格中分类
        $exam_list = self::doImportExcel(new \App\Imports\UsersImport , $path);
        foreach ($exam_list['data'] as $k=>$v){
            if($v[0] && $v[0] > 0){
                Articletype::insertGetId([
                    'id' => $v[0],
                    'school_id' => 1,
                    'typename' => $v[6] && !empty($v[6]) ? $v[6] : '' ,
                    'user_id' => 1,
                    'description' => $v[7] && !empty($v[7]) ? $v[7] : '' ,
                    'create_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        return response()->json(['code' => 200 , 'msg' => '导入成功']);
    }
}
