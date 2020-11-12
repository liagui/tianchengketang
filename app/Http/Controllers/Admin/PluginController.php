<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Plugin;
use App\Models\Services;

class PluginController extends Controller{

    /*
         * @param  列表
         * @param  ontype 1统计2客服
         * @param  type 1百度2谷歌3腾讯4cnzz
         * @param  author  苏振文
         * @param  ctime   2020/11/10 15:44
         * return  array
         */
    public function pluginlist(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //接受数据
        $data = self::$accept_data;
        if(!isset($data['ontype']) || empty($data['ontype'])){
            return response()->json(['code' => 201, 'msg' => '类型为空']);
        }
        //判断是统计还是客服
        if($data['ontype'] == 1){
            //查询是否有数据
            $first = Plugin::where(['school_id'=>$school_id,'parent_id'=>0,'on_type'=>1])->first();
            if(!empty($first)){
                $return['open'] = $first['status'];
                for($i = 1 ; $i <=4 ; $i++){
                    $istype = Plugin::where(['school_id'=>$school_id,'parent_id'=>$first['id'],'type'=>$i,'on_type'=>1])->first();
                    if(!empty($istype)){
                        if($i==1){
                            $return['baidu'] = $istype['key'];
                        }else if($i == 2){
                            $return['guge'] = $istype['key'];
                        }else if($i == 3){
                            $return['tengx'] = $istype['key'];
                        }else if($i == 4){
                            $return['cnzz'] = $istype['key'];
                        }
                    }else{
                        if($i==1){
                            $return['baidu'] = "";
                        }else if($i == 2){
                            $return['guge'] = "";
                        }else if($i == 3){
                            $return['tengx'] = "";
                        }else if($i == 4){
                            $return['cnzz'] = "";
                        }
                    }
                }
            }else{
                $return['open'] = 0;
                $return['baidu'] = "";
                $return['guge'] = "";
                $return['tengx'] = "";
                $return['cnzz'] = "";
            }
            return response()->json(['code' => 200, 'msg' => '查询成功','data'=>$return]);
        }else{
            //在线客服查询是否开启
            $first = Plugin::where(['school_id'=>$school_id,'parent_id'=>0,'on_type'=>2])->first();
            if(!empty($first)){
                $return['open'] = $first['status'];
                $istype = Plugin::where(['school_id'=>$school_id,'parent_id'=>$first['id'],'type'=>1,'on_type'=>2])->first();
                $return['baidu'] = $istype['key'];
            }else{
                $return['open'] = 0;
                $return['baidu'] = "";
            }
            return response()->json(['code' => 200, 'msg' => '查询成功','data'=>$return]);
        }
    }
    /*
         * @param  开启关闭
         * @param  ontype
         * @param  author  苏振文
         * @param  ctime   2020/11/10 16:08
         * return  array
         */
    public function opendown(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //接受数据
        $data = self::$accept_data;
        if(!isset($data['ontype']) || empty($data['ontype'])){
            return response()->json(['code' => 201, 'msg' => '类型为空']);
        }
//        if(!isset($data['status']) || empty($data['status'])){
//            return response()->json(['code' => 201, 'msg' => '状态为空']);
//        }
        if($data['ontype'] == 1){
            $first = Plugin::where(['school_id'=>$school_id,'parent_id'=>0,'on_type'=>1])->first();
            if($first){
                $status = $first['status'] == 1 ? 0:1;
                $up = Plugin::where(['school_id'=>$school_id,'parent_id'=>0,'on_type'=>1])->update(['status'=>$status]);
                if ($up) {
                    return response()->json(['code' => 200, 'msg' => '操作成功']);
                } else {
                    return response()->json(['code' => 201, 'msg' => '操作失败']);
                }
            }else{
                $add=[
                    'school_id'=>$school_id,
                    'on_type' => 1,
                    'status'=>1,
                    'add_time'=>date('Y-m-d :H:i:s')
                ];
                $parent_id = Plugin::insertGetId($add);
                if($parent_id > 0){
                    for($i = 1 ; $i <= 4 ;$i++){
                        $adds = [
                            'parent_id'=>$parent_id,
                            'school_id'=>$school_id,
                            'on_type' => 1,
                            'type'=>$i,
                            'add_time'=>date('Y-m-d :H:i:s')
                        ];
                        Plugin::insert($adds);
                    }
                    return response()->json(['code' => 200, 'msg' => '操作成功']);
                }else{
                    return response()->json(['code' => 201, 'msg' => '操作失败']);
                }
            }
        }else{
            $first = Plugin::where(['school_id'=>$school_id,'parent_id'=>0,'on_type'=>2])->first();
            if($first){
                $status = $first['status'] == 1 ? 0:1;
                $up = Plugin::where(['school_id'=>$school_id,'parent_id'=>0,'on_type'=>2])->update(['status'=>$status]);
                if ($up) {
                    return response()->json(['code' => 200, 'msg' => '操作成功']);
                } else {
                    return response()->json(['code' => 201, 'msg' => '操作失败']);
                }
            }else{
                $add=[
                    'school_id'=>$school_id,
                    'on_type' => 2,
                    'status'=>1,
                    'add_time'=>date('Y-m-d :H:i:s')
                ];
                $parent_id = Plugin::insertGetId($add);
                if($parent_id > 0){
                    $adds = [
                        'parent_id'=>$parent_id,
                        'school_id'=>$school_id,
                        'on_type' => 2,
                        'type'=>1,
                        'add_time'=>date('Y-m-d :H:i:s')
                    ];
                    Plugin::insert($adds);
                  return response()->json(['code' => 200, 'msg' => '操作成功']);
                }else{
                  return response()->json(['code' => 201, 'msg' => '操作失败']);
                }
            }
        }
    }

    /*
         * @param  修改
         * @param  key
         * @param  type
         * @param  ontype
         * @param  author  苏振文
         * @param  ctime   2020/11/10 16:35
         * return  array
         */
    public function upplugin(){
        //获取后端的操作员id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        //接受数据
        $data = self::$accept_data;
        if(!isset($data['key']) || empty($data['key'])){
            return response()->json(['code' => 201, 'msg' => 'key值不能为空']);
        }
        if($data['ontype'] == 1){
            $up = Plugin::where(['school_id'=>$school_id,'on_type'=>1,'type'=>$data['type']])->update(['key'=>$data['key']]);
        }else{
            $up = Plugin::where(['school_id'=>$school_id,'on_type'=>2,'type'=>$data['type']])->update(['key'=>$data['key']]);
        }
        if ($up) {
            return response()->json(['code' => 200, 'msg' => '修改成功']);
        } else {
            return response()->json(['code' => 201, 'msg' => '修改失败']);
        }
    }
}
