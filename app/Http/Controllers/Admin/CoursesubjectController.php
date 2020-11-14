<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\CouresSubject;

class CoursesubjectController extends Controller {
   /*
        * @param  课程学科列表
        * @param  author  苏振文
        * @param  ctime   2020/6/24 10:41
        * return  array
        */
   public function subjectList(){
       //获取用户学校
       $school_status = AdminLog::getAdminInfo()->admin_user->school_status;
       $school_id = AdminLog::getAdminInfo()->admin_user->school_id;
       $list = CouresSubject::subjectList($school_status,$school_id);
       return response()->json($list);
   }
   /*
        * @param  学科大类添加
        * @param  author  苏振文
        * @param  ctime   2020/6/24 14:25
        * return  array
        */
   public function subjectAdd(){

       //获取用户id,用户分校
       $user_id = AdminLog::getAdminInfo()->admin_user->id;
       $school_id = AdminLog::getAdminInfo()->admin_user->school_id;
       $data = self::$accept_data;
       if(!isset($data) || empty($data)){
           return response()->json(['code' => 201 , 'msg' => '参数有误']);
       }
       if(!isset($data['subject_name']) || empty($data['subject_name'])){
           return response()->json(['code' => 202 , 'msg' => '学科名称不能为空']);
       }
       if(!isset($data['parent_id']) || empty($data['parent_id'])){
           $data['parent_id'] = 0;
       }
       $add = CouresSubject::subjectAdd($user_id,$school_id,$data);
       return response()->json($add);
   }
   /*
        * @param  删除
        * @param  author  苏振文
        * @param  ctime   2020/6/24 14:44
        * return  array
        */
   public function subjectDel(){
       //获取用户id,用户分校
       $user_id = AdminLog::getAdminInfo()->admin_user->id;
       $data = self::$accept_data;
       if(!isset($data) || empty($data)){
           return response()->json(['code' => 201 , 'msg' => '参数有误']);
       }
       if(!isset($data['id']) || empty($data['id'])){
           return response()->json(['code' => 202 , 'msg' => 'id不能为空']);
       }
       $del = CouresSubject::subjectDel($user_id,$data);
       return response()->json($del);
   }
   /*
        * @param  单条详情
        * @param  author  苏振文
        * @param  ctime   2020/6/24 15:06
        * return  array
        */
   public function subjectOnes(){
       $data = self::$accept_data;
       if(!isset($data) || empty($data)){
           return response()->json(['code' => 201 , 'msg' => '参数有误']);
       }
       if(!isset($data['id']) || empty($data['id'])){
           return response()->json(['code' => 202 , 'msg' => 'id不能为空']);
       }
       $find = CouresSubject::subjectOnes($data);
       return response()->json($find);
   }

   /*
        * @param  修改
        * @param  author  苏振文
        * @param  ctime   2020/6/24 15:06
        * return  array
        */
   public function subjectUpdate(){
       //获取用户id,用户分校
       $user_id = AdminLog::getAdminInfo()->admin_user->id;
       $data = self::$accept_data;
       $find = CouresSubject::subjectUpdate($user_id,$data);
       return response()->json($find);
   }
   /*
        * @param 修改学科大类状态
        * @param  $user_id     参数
        * @param  author  苏振文
        * @param  ctime   2020/6/24 15:16
        * return  array
        */
   public function subjectForStatus(){
       //获取用户id,用户分校
       $user_id = AdminLog::getAdminInfo()->admin_user->id;
       $data = self::$accept_data;
       if(!isset($data) || empty($data)){
           return response()->json(['code' => 201 , 'msg' => '参数有误']);
       }
       if(!isset($data['id']) || empty($data['id'])){
           return response()->json(['code' => 202 , 'msg' => 'id不能为空']);
       }
       $up = CouresSubject::subjectForStatus($user_id,$data);
       return response()->json($up);
   }

    /*
          * @param 修改排序
          * @param  $id     学科id[1,2,3  ... ...]
          * @param  author  sxh
          * @param  ctime   2020-10-23
          * return  array
          */
    public function subjectListSort(){
        try{
            //获取用户学校
            $school_status = AdminLog::getAdminInfo()->admin_user->school_status;
            $school_id = AdminLog::getAdminInfo()->admin_user->school_id;
            $data = CouresSubject::subjectListSort(self::$accept_data,$school_status,$school_id);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
}
