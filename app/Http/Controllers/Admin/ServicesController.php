<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Services;

class ServicesController extends Controller{

    /*
         * @param  分校工具条
         * @param  type
         * @param  author  苏振文
         * @param  ctime   2020/11/10 11:18
         * return  array
         */
    public function workboxlist(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //接受数据
        $data = self::$accept_data;
        if(!empty($data['type'])) {
            //先查询 如果有修改 如果没有添加
            $first = Services::where(['school_id' => $school_id, 'parent_id' => 0])->first();
            if (!empty($first)) {
                //修改
                $up = Services::where(['id' => $first['id']])->update(['ontype' => $data['type'], 'up_time' => date('Y-m-d H:i:s')]);
                if ($up) {
                    return response()->json(['code' => 200, 'msg' => '选择成功']);
                } else {
                    return response()->json(['code' => 201, 'msg' => '选择失败']);
                }
            } else {
                //添加
                $add = [
                    'school_id' => $school_id,
                    'ontype' => $data['type'],
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                $inser = Services::insert($add);
                if ($inser) {
                    return response()->json(['code' => 200, 'msg' => '选择成功']);
                } else {
                    return response()->json(['code' => 201, 'msg' => '选择失败']);
                }
            }
        }else{
            $first = Services::where(['school_id' => $school_id, 'parent_id' => 0])->first();
            if(!empty($first)){
                return response()->json(['code' => 200, 'msg' => '查询成功','data'=>$first['ontype']]);
            }else{
                return response()->json(['code' => 200, 'msg' => '查询成功','data'=>0]);
            }
        }
    }
    /*
         * @param  列表信息
         * @param  author  苏振文
         * @param  ctime   2020/11/10 11:42
         * return  array
         */
    public function servicelist(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $data=[];
        $qq = empty(Services::where(['school_id'=>$school_id,'type'=>1])->first()) ? Services::where(['school_id'=>$school_id,'type'=>2])->first():[];
        if(empty($qq)){
            $data[]=[];
        }else{
            $data[] = empty(Services::where(['school_id'=>$school_id,'type'=>1])->first()) ? Services::where(['school_id'=>$school_id,'type'=>2])->first():[];
        }
        $data[] = !empty(Services::where(['school_id'=>$school_id,'type'=>2])->first()) ? Services::where(['school_id'=>$school_id,'type'=>2])->first() :[];
        $data[] = !empty(Services::where(['school_id'=>$school_id,'type'=>3])->first()) ? Services::where(['school_id'=>$school_id,'type'=>3])->first() :[];
        $data[] = !empty(Services::where(['school_id'=>$school_id,'type'=>4])->first()) ? Services::where(['school_id'=>$school_id,'type'=>4])->first() :[];
        return response()->json(['code' => 200, 'msg' => '获取成功','data'=>$data]);
    }
    /*
         * @param  开启关闭通用
         * @param  $user_id     参数
         * @param  author  苏振文
         * @param  ctime   2020/11/10 11:30
         * return  array
         */
    public function openstatus(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //接受数据
        $data = self::$accept_data;
        //查询父级
        $first = Services::where(['school_id'=>$school_id,'parent_id'=>0])->first();
        //先查询 如果有修改 如果没有添加
        if($data['type'] <= 2){
            $qq = empty(Services::where(['school_id'=>$school_id,'type'=>1,'parent_id'=>$first['id']])->first()) ? Services::where(['school_id'=>$school_id,'type'=>2,'parent_id'=>$first['id']])->first():[];
        }else{
            $qq = Services::where(['school_id'=>$school_id,'type'=>$data['type'],'parent_id'=>$first['id']])->first();
        }
        if(!empty($qq)){
            //修改
            $up = Services::where(['id'=>$qq['id']])->update(['status'=>$data['status'],'up_time'=>date('Y-m-d H:i:s')]);
            if($up){
                return response()->json(['code' => 200, 'msg' => '选择成功']);
            }else{
                return response()->json(['code' => 201, 'msg' => '选择失败']);
            }
        }else{
            //添加
            $add = [
                'parent_id'=>$first['id'],
                'school_id'=>$school_id,
                'type' => $data['type'],
                'add_time' => date('Y-m-d H:i:s'),
                'status' => 0,
            ];
            $inser = Services::insert($add);
            if($inser){
                return response()->json(['code' => 200, 'msg' => '选择成功']);
            }else{
                return response()->json(['code' => 201, 'msg' => '选择失败']);
            }
        }
    }
    /*
         * @param  修改参数
         * @param  type  1qq2营销qq3微信4微博5手机号
         * @param  数据
         * @param  author  苏振文
         * @param  ctime   2020/11/10 14:58
         * return  array
         */
    public function upservice(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //接受数据
        $data = self::$accept_data;
        //查询父级
        $first = Services::where(['school_id'=>$school_id,'parent_id'=>0])->first();
        $up['up_time'] = date('Y-m-d H:i:s');
        if($data['type'] == 1){
            if(!isset($data['key']) || empty($data['key'])){
                return response()->json(['code' => 202, 'msg' => 'QQ号不能为空并且为数字']);
            }
            $up['key']=$data['key'];
        }
        if($data['type'] == 2){
            if(!isset($data['number']) || empty($data['number'])){
                return response()->json(['code' => 202, 'msg' => 'QQ号不能为空并且为数字']);
            }
            if(!isset($data['key']) || empty($data['key'])){
                return response()->json(['code' => 202, 'msg' => '营销QQ-Key值不能为空']);
            }
            $up['key'] = $data['key'];
            $up['sing'] = $data['number'];
        }
        if($data['type'] == 3){
            if(!isset($data['img']) || empty($data['img'])){
                return response()->json(['code' => 202, 'msg' => '微信二维码不能为空']);
            }
            if(!isset($data['key']) || empty($data['key'])){
                return response()->json(['code' => 202, 'msg' => '公众号不能为空']);
            }
            $up['key'] = $data['key'];
            $up['img'] = $data['img'];
        }
        if($data['type'] == 4){
            if(!isset($data['img']) || empty($data['img'])){
                return response()->json(['code' => 202, 'msg' => '微博二维码不能为空']);
            }
            if(!isset($data['number']) || empty($data['number'])){
                return response()->json(['code' => 202, 'msg' => '微博地址不能为空']);
            }
            if(!isset($data['key']) || empty($data['key'])){
                return response()->json(['code' => 202, 'msg' => '微博号不能为空']);
            }
            $up['key'] = $data['key'];
            $up['sing'] = $data['number'];
            $up['img'] = $data['img'];
        }
        if($data['type'] == 5){
            if(!isset($data['sing']) || empty($data['sing'])){
                return response()->json(['code' => 202, 'msg' => '请输入服务时间']);
            }
            if(!isset($data['key']) || empty($data['key'])){
                return response()->json(['code' => 202, 'msg' => '请正确输入电话号码']);
            }
            $up['key'] = $data['key'];
            $up['sing'] = $data['sing'];
        }
        $update = Services::where(['school_id'=>$school_id,'type'=>$data['type'],'parent_id'=>$first['id']])->update($up);
        if($update){
            return response()->json(['code' => 200, 'msg' => '修改成功']);
        }else{
            return response()->json(['code' => 201, 'msg' => '修改失败']);
        }
    }
}
