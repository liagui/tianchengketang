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
        //判断是统计还是客服
        if($data['ontype'] == 1){
            //查询是否开启
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
        if($data['ontype'] == 1){

        }else{

        }
    }
}
