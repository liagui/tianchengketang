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
         * @param  type 1 2 3 4
         * @param  author  苏振文
         * @param  ctime   2020/11/10 11:42
         * return  array
         */

    public function servicelist(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $data = self::$accept_data;
        if(empty($data['type'])){
            return response()->json(['code' => 201, 'msg' => '类型不能为空']);
        }
        $newarr =[
            'status'=>0,
            'parent_id'=>0,
            'school_id'=>$school_id,
            'bigtype'=>0,
            'key'=>0,
            'sing'=>0,
            'img'=>0,
            'ontype'=>0,
            'add_time'=>'',
        ];
        if($data['type'] == 1){
            //查询一级
            $parent = Services::where(['school_id'=>$school_id,'parent_id'=>0])->first();
            //查询二级
            $twoparent = Services::where(['school_id'=>$school_id,'parent_id'=>$parent['id'],'bigtype'=>1])->first();
            if(!empty($twoparent)){
                $qq = empty(Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>1,'parent_id'=>$twoparent['id'],'status'=>1])->first()) ? Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>2,'parent_id'=>$twoparent['id'],'status'=>1])->first():Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>1,'parent_id'=>$twoparent['id'],'status'=>1])->first();
                if(empty($qq)){
                    $newarr['type'] = 1;
                    $newarr['status'] = $twoparent['status'];
                    $datas=$newarr;
                }else{
                    $datas = empty(Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>1,'parent_id'=>$twoparent['id'],'status'=>1])->first()) ? Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>2,'parent_id'=>$twoparent['id'],'status'=>1])->first():Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>1,'parent_id'=>$twoparent['id'],'status'=>1])->first();
                    $datas['status'] = $twoparent['status'];
                }
            }else{
                $newarr['type'] = 1;
                $datas=$newarr;
            }
        }else{
            $newarr['type'] = $data['type'];
            $datas = !empty(Services::where(['school_id'=>$school_id,'type'=>$data['type']+1,'bigtype'=>0])->first()) ? Services::where(['school_id'=>$school_id,'type'=>$data['type']+1,'bigtype'=>0])->first() :$newarr;
            //kefu 拆分key
            if($data['type'] == 4){
                if(!empty($datas['key'])){
                    $newnew =[];
                    $newkey = explode(',',$datas['key']);
                    foreach ($newkey as $k=>$v){
                        $arr=['inputText' => $v];
                        $newnew[] = $arr;
                    }
                    $datas['key'] = $newnew;
                }
            }
        }
        return response()->json(['code' => 200, 'msg' => '获取成功','data'=>$datas]);
    }
    /*
         * @param  开启关闭通用
         * @param  type  QQ号/微信号/微博名/手机号
         * @param  author  苏振文
         * @param  ctime   2020/11/10 11:30
         * return  array
         */
    public function openstatus(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //接受数据
        $data = self::$accept_data;
        if(!isset($data['type']) || empty($data['type'])){
            return response()->json(['code' => 201, 'msg' => '类型为空']);
        }
        //查询父级
        $first = Services::where(['school_id'=>$school_id,'parent_id'=>0])->first();
        if(empty($first)){
            $id= Services::insertGetId(['school_id'=>$school_id,'parent_id'=>0]);
            $first['id'] = $id;
        }
        //先查询 如果有修改 如果没有添加
        if($data['type'] == 1){
            $qq = Services::where(['school_id'=>$school_id,'bigtype'=>1,'parent_id'=>$first['id']])->first();
        }else{
            $type = $data['type'] +1;
            $qq = Services::where(['school_id'=>$school_id,'type'=>$type,'parent_id'=>$first['id']])->first();
        }
        if(!empty($qq)){
            //修改
            $status = $qq['status'] == 1 ? 0:1;
            if($data['type'] == 1){
                $up = Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>0])->update(['status'=>$status,'up_time'=>date('Y-m-d H:i:s')]);
                if($up){
                    return response()->json(['code' => 200, 'msg' => '操作成功','data'=>$status]);
                }else{
                    return response()->json(['code' => 201, 'msg' => '操作失败']);
                }
            }else{
                $up = Services::where(['id'=>$qq['id']])->update(['status'=>$status,'up_time'=>date('Y-m-d H:i:s')]);
                if($up){
                    return response()->json(['code' => 200, 'msg' => '操作成功','data'=>$status]);
                }else{
                    return response()->json(['code' => 201, 'msg' => '操作失败']);
                }
            }
        }else{
            if($data['type'] == 1){
                //添加
                $add = [
                    'parent_id'=>$first['id'],
                    'school_id'=>$school_id,
                    'bigtype' => 1,
                    'add_time' => date('Y-m-d H:i:s'),
                    'status' => 1,
                ];
            }else{
                //添加
                $add = [
                    'parent_id'=>$first['id'],
                    'school_id'=>$school_id,
                    'type' => $data['type']+1,
                    'add_time' => date('Y-m-d H:i:s'),
                    'status' => 1,
                ];
            }
            $inser = Services::insert($add);
            if($inser){
                return response()->json(['code' => 200, 'msg' => '操作成功','data'=>1]);
            }else{
                return response()->json(['code' => 201, 'msg' => '操作失败']);
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
        if(!isset($data['type']) || empty($data['type'])){
            return response()->json(['code' => 201, 'msg' => '类型为空']);
        }
        //查询父级
        $first = Services::where(['school_id'=>$school_id,'parent_id'=>0])->first();
        if($data['type'] == 1){
            if(!isset($data['key']) || empty($data['key'])){
                return response()->json(['code' => 202, 'msg' => 'QQ号不能为空']);
            }
            if(!is_numeric($data['key'])) {
                return response()->json(['code' => 202, 'msg' => '请填写正确的qq号']);
            }
            if(strlen($data['key']) < 5 || strlen($data['key'])  > 15){
                return response()->json(['code' => 202, 'msg' => '请填写正确的qq号']);
            }
            //qq中间层
            $types = Services::where(['school_id'=>$school_id,'bigtype'=>1,'parent_id'=>$first['id'],'type'=>0])->first();
            if(Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>1,'parent_id'=>$types['id']])->first()){
                $up = Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>1,'parent_id'=>$types['id']])->update(['key'=>$data['key'],'up_time'=>date('Y-m-d H:i:s')]);
            }else{
                $newadd=[
                    'parent_id'=>$types['id'],
                    'school_id'=>$school_id,
                    'bigtype' => 1,
                    'type' => 1,
                    'add_time' => date('Y-m-d H:i:s'),
                    'status' => 1,
                    'key' => $data['key']
                ];
                $up = Services::insert($newadd);
            }
        }
        if($data['type'] == 2){
            if(!isset($data['number']) || empty($data['number'])){
                return response()->json(['code' => 202, 'msg' => 'QQ号不能为空']);
            }
            if(!is_numeric($data['number'])) {
                return response()->json(['code' => 202, 'msg' => '请填写正确的qq号']);
            }
            if(strlen($data['number']) < 5 || strlen($data['number'])  > 15){
                return response()->json(['code' => 202, 'msg' => '请填写正确的qq号']);
            }
            if(!isset($data['key']) || empty($data['key'])){
                return response()->json(['code' => 202, 'msg' => '营销QQ-Key值不能为空']);
            }
            //qq中间层
            $types = Services::where(['school_id'=>$school_id,'bigtype'=>1,'parent_id'=>$first['id'],'type'=>0])->first();
            if(Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>2,'parent_id'=>$types['id']])->first()){
                $up = Services::where(['school_id'=>$school_id,'bigtype'=>1,'type'=>2,'parent_id'=>$types['id']])->update(['key'=>$data['key'],'sing'=>$data['number'],'up_time'=>date('Y-m-d H:i:s')]);
            }else{
                $newadd=[
                    'parent_id'=>$types['id'],
                    'school_id'=>$school_id,
                    'bigtype' => 1,
                    'type' => 2,
                    'add_time' => date('Y-m-d H:i:s'),
                    'status' => 1,
                    'key' => $data['key'],
                    'sing' => $data['number']
                ];
                $up = Services::insert($newadd);
            }
        }
        if($data['type'] == 3){
            if(!isset($data['img']) || empty($data['img'])){
                return response()->json(['code' => 202, 'msg' => '微信二维码不能为空']);
            }
            if(!isset($data['key']) || empty($data['key'])){
                return response()->json(['code' => 202, 'msg' => '公众号不能为空']);
            }
            $types = Services::where(['school_id'=>$school_id,'type'=>3,'parent_id'=>$first['id']])->first();
            if(empty($types)){
                $newadd=[
                    'parent_id'=>$first['id'],
                    'school_id'=>$school_id,
                    'type' => 3,
                    'add_time' => date('Y-m-d H:i:s'),
                    'status' => 0,
                    'key' => $data['key'],
                    'img' => $data['img'],
                ];
                $up = Services::insert($newadd);
            }else{
                $up = Services::where(['school_id'=>$school_id,'type'=>3,'parent_id'=>$first['id']])->update(['key'=>$data['key'],'img'=>$data['img'],'up_time'=>date('Y-m-d H:i:s')]);
            }
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
            $types = Services::where(['school_id'=>$school_id,'type'=>4,'parent_id'=>$first['id']])->first();
            if(empty($types)){
                $newadd=[
                    'parent_id'=>$first['id'],
                    'school_id'=>$school_id,
                    'type' => 4,
                    'add_time' => date('Y-m-d H:i:s'),
                    'status' => 0,
                    'key' => $data['key'],
                    'sing' => $data['number'],
                    'img' => $data['img'],
                ];
               $up = Services::insert($newadd);
            }else{
                $up = Services::where(['school_id'=>$school_id,'type'=>4,'parent_id'=>$first['id']])->update(['key'=>$data['key'],'sing'=>$data['number'],'img'=>$data['img'],'up_time'=>date('Y-m-d H:i:s')]);
            }
        }
        if($data['type'] == 5){
            if(!isset($data['number']) || empty($data['number'])){
                return response()->json(['code' => 202, 'msg' => '请输入服务时间']);
            }
            if(!isset($data['Arr']) || empty($data['Arr'])){
                return response()->json(['code' => 202, 'msg' => '请正确输入电话号码']);
            }
            $newarr = json_decode($data['Arr'],true);
            $number = [];
            foreach ($newarr as $k=>$v){
                if(!is_numeric($v['inputText'])) {
                    return response()->json(['code' => 202, 'msg' => '请填写正确的手机号']);
                }
                if(strlen($v['inputText']) < 8 || strlen($v['inputText'])  > 12){
                    return response()->json(['code' => 202, 'msg' => '请填写正确的手机号']);
                }
                array_push($number,$v['inputText']);
            }
            $newnumber = implode(',',$number);
            $types = Services::where(['school_id'=>$school_id,'type'=>5,'parent_id'=>$first['id']])->first();
            if(empty($types)){
                $newadd=[
                    'parent_id'=>$first['id'],
                    'school_id'=>$school_id,
                    'type' => 5,
                    'add_time' => date('Y-m-d H:i:s'),
                    'status' => 0,
                    'key' => $newnumber,
                    'sing' => $data['number'],
                ];
              $up = Services::insert($newadd);
            }else{
              $up = Services::where(['school_id'=>$school_id,'type'=>5,'parent_id'=>$first['id']])->update(['key'=>$newnumber,'sing'=>$data['number'],'up_time'=>date('Y-m-d H:i:s')]);
            }
        }
        if($up){
            return response()->json(['code' => 200, 'msg' => '修改成功']);
        }else{
            return response()->json(['code' => 201, 'msg' => '修改失败']);
        }
    }
    /*
         * @param  qq选择
         * @param  type
         * @param  author  苏振文
         * @param  ctime   2020/11/11 14:24
         * return  array
         */
    public function qqelect(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //接受数据
        $data = self::$accept_data;
        if(!isset($data['type']) || empty($data['type'])){
            return response()->json(['code' => 201, 'msg' => '类型为空']);
        }
        if($data['type'] == 1){
           $first = Services::where(['school_id'=>$school_id,'type'=>1,'bigtype'=>1])->first();
           if($first){
               //如果存在，修改状态
               $up = Services::where(['school_id'=>$school_id,'type'=>1,'bigtype'=>1])->update(['status'=>1,'up_time'=>date('Y-m-d H:i:s')]);
               if($up){
                   Services::where(['school_id'=>$school_id,'type'=>2,'bigtype'=>1])->update(['status'=>0,'up_time'=>date('Y-m-d H:i:s')]);
                   return response()->json(['code' => 200, 'msg' => '修改成功','data'=>$first]);
               }else{
                   return response()->json(['code' => 201, 'msg' => '修改失败']);
               }
           }else{
               $parent = Services::where(['school_id'=>$school_id,'bigtype'=>1])->first();
               //如果不存在，添加数据，并修改状态
               $add = Services::insert(['parent_id'=>$parent['id'],'school_id'=>$school_id,'type'=>1,'bigtype'=>1,'status'=>1,'add_time'=>date('Y-m-d H:i:s')]);
               if($add){
                   Services::where(['school_id'=>$school_id,'type'=>2,'bigtype'=>1])->update(['status'=>0,'up_time'=>date('Y-m-d H:i:s')]);
                   return response()->json(['code' => 200, 'msg' => '修改成功','data'=>$add]);
               }else{
                   return response()->json(['code' => 201, 'msg' => '修改失败']);
               }
           }
        }else{
            $first = Services::where(['school_id'=>$school_id,'type'=>2,'bigtype'=>1])->first();
            if($first){
                //如果存在，修改状态
                $up = Services::where(['school_id'=>$school_id,'type'=>2,'bigtype'=>1])->update(['status'=>1,'up_time'=>date('Y-m-d H:i:s')]);
                if($up){
                    Services::where(['school_id'=>$school_id,'type'=>1,'bigtype'=>1])->update(['status'=>0,'up_time'=>date('Y-m-d H:i:s')]);
                    return response()->json(['code' => 200, 'msg' => '修改成功','data'=>$first]);
                }else{
                    return response()->json(['code' => 201, 'msg' => '修改失败']);
                }
            }else{
                $parent = Services::where(['school_id'=>$school_id,'bigtype'=>1])->first();
                //如果不存在，添加数据，并修改状态
                $add = Services::insert(['parent_id'=>$parent['id'],'school_id'=>$school_id,'type'=>2,'bigtype'=>1,'status'=>1,'add_time'=>date('Y-m-d H:i:s')]);
                if($add){
                    Services::where(['school_id'=>$school_id,'type'=>1,'bigtype'=>1])->update(['status'=>0,'up_time'=>date('Y-m-d H:i:s')]);
                    return response()->json(['code' => 200, 'msg' => '修改成功','data'=>$add]);
                }else{
                    return response()->json(['code' => 201, 'msg' => '修改失败']);
                }
            }
        }
    }
}
