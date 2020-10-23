<?php

namespace App\Http\Middleware;
use App\Models\Adminuser;
use App\Models\Role;
use App\Models\Admin;
use App\Models\School;
use App\Services\Admin\Role\RoleService;
use App\Services\Admin\Rule\RuleService;
use Closure;
use App\Tools\CurrentAdmin;

class ApiAuthToken {
    public function handle($request, Closure $next){

        /**
         * 登录者信息 是否有效
         */
        $user = CurrentAdmin::user();
        if(!isset($user['id']) || $user['id'] <=0 ){
            return response()->json(['code'=>403,'msg'=>'无此用户，请联系管理员']);
        }
        if($user['is_forbid'] != 1 ||$user['is_del'] != 1 ){
              return response()->json(['code'=>403,'msg'=>'此用户已被禁用或删除，请联系管理员']);
        }

        /**
         * 登录者所在学校是否有效
         */
        $schoolData = School::getSchoolOne(['id'=>$user['school_id'],'is_del'=>1],['id','name','is_forbid']);
        if($schoolData['code'] != 200){
            return response()->json(['code'=>403,'msg'=>'无此学校，请联系管理员']);
        }else{
            if($schoolData['data']['is_forbid'] != 1 ){
                return response()->json(['code'=>403,'msg'=>'学校已被禁用，请联系管理员']);
            }
        }

        /**
         * 登录这是否有效
         */
        $userInfo = Admin::GetUserOne(['id'=>$user['id'],'is_forbid'=>1,'is_del'=>1]); //获取用户信息
        if($userInfo['code'] != 200){
            return response()->json(['code'=>403,'msg'=>'无此用户，请联系管理员']);
        }

        /**
         * 角色验证
         */
        $roleInfo = Role::getRoleInfo(['id'=>$userInfo['data']['role_id'],'school_id'=>$schoolData['data']['id']]);//获取角色权限
        if(empty($roleInfo)){
            return response()->json(['code'=>403,'msg'=>'此用户没有权限,请联系管理员']);
        }
        //总部超级管理员 不限制
        if ($userInfo['data']['school_status'] == 1 && $roleInfo['is_super'] == 1) {
            return $next($request);
        }

        /**
         * 路由验证
         */
        $url = ltrim(parse_url($request->url(),PHP_URL_PATH),'/'); //获取路由连接
        $routerInfo = RuleService::getRouterInfoByUrl($url);//获取权限id
        if (empty($routerInfo)) {
            return response()->json(['code'=>403,'msg'=>'此用户没有权限,请联系管理员']);
        }

        //通用路由不限制
        if ($routerInfo['parent_id'] == -1) {
            return $next($request);
        }

        //查看角色路由 (路由限制)
        $groupList = RoleService::getRoleRuleGroupList($userInfo['data']['role_id']);
        if (empty($groupList)) {
            return response()->json(['code'=>403,'msg'=>'此用户没有权限,请联系管理员']);
        }

        $routerList = RuleService::getRouterListById(array_column([$routerInfo['id']], array_column($groupList, 'group_id')));
        if (! empty($routerList)) {
            return $next($request);
        } else {
            return response()->json(['code'=>403,'msg'=>'此用户没有权限???']);
        }

    }
}
