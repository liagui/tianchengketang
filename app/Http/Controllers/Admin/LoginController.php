<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use App\Models\Adminuser;
use App\Models\Roleauth;
use App\Models\Authrules;
use Illuminate\Support\Facades\Redis;


class LoginController extends Controller {
  
    /*
     * @param  getUserAuth   获取用户权限
     * @param  return  array   返回用户信息及用户权限
     * @param author    lys
     * @param ctime     2020-04-27
     */
    public function getUserAuth(){

        $admin_id = 6;
        //$result = Adminuser::getUserOne($admin_id);
       
        if($result['code'] != 200){
           return ['code' => $result['code'],'msg' => $result['msg']];
        }
        $user_auth_arr = Redis::hget('auth',$result['data']['role_id']); //从redis取权限前两级
        if($user_auth_arr){
            $arr = [
                'admin_user'=>$result['data'],
                'admin_auth'=>json_decode($user_auth_arr,1)
            ];
            return response()->json(['code' => 200,'msg' => 'Success','data'=>$arr]);
        }else{
            $roleArr = Roleauth::getRoleOne($result['data']['role_id']);
            if($roleArr['code'] != 200){
               return response()->json(['code' => $roleArr['code'],'msg' => $roleArr['msg']]);
            }
            $userAuthArr = Authrules::getAdminAuthAll($roleArr['data']['auth_id']);
            if($userAuthArr['code'] != 200){
               return response()->json(['code' => $userAuthArr['code'],'msg' => $userAuthArr['msg']]);
            }else{
                $arr = [
                    'admin_user' => $result['data'],
                    'admin_auth' => $userAuthArr['data']
                ];
                Redis::hset('auth',$result['data']['role_id'],json_encode($userAuthArr['data']));
                return response()->json(['code' => 200,'msg' => 'Success','data' => $arr]);
            }
        }
    }
    
    
   
    

   
}
