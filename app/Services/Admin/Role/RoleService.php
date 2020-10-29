<?php

namespace App\Services\Admin\Role;

use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\Role;
use App\Models\RoleRuleGroup;
use App\Models\RuleGroup;
use App\Models\RuleGroupRouter;
use App\Services\Admin\Rule\RuleService;
use App\Tools\CurrentAdmin;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /**
     * 获取角色列表
     * @param $params
     * @return array
     */
    public function getRoleList($params)
    {

        $adminUserInfo  = CurrentAdmin::user();  //当前登录用户所有信息
        //判断搜索条件是否合法
        $params['search'] = !isset($params['search']) && empty($params['search']) ?'':$params['search'];

        $pagesize = isset($params['pagesize']) && $params['pagesize'] > 0 ? $params['pagesize'] : 15;
        $page     = isset($params['page']) && $params['page'] > 0 ? $params['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        $roleQuery = Role::query()
            ->where('is_del',0)
            ->where('school_id',$adminUserInfo['school_id']);

        if (!empty($params['search'])) {
            $roleQuery = $roleQuery->where('role_name','like','%'.$params['search'].'%');
        }

        /**
         * 获取总数据
         */
        $total = $roleQuery->count();

        $sumPage = ceil($total/$pagesize);
        if ($total > 0) {

            $roleList = $roleQuery->select('role_name', 'auth_desc','create_time', 'id', 'admin_id')
                ->offset($offset)
                ->limit($pagesize)
                ->get()
                ->toArray();

            $adminIdList = array_column($roleList, 'admin_id');

            $adminList = Admin::query()
                ->whereIn('id', $adminIdList)
                ->where('is_del', 1)
                ->select('id', 'username')
                ->get()
                ->toArray();

            $adminData = array_column($adminList, 'username', 'id');

            foreach ($roleList as $key => $item) {
                $item['username'] = $adminData[$item['admin_id']] ?? '';
                $roleList[$key] = $item;
            }

            return ['code'=>200,'msg'=>'Success','data'=>['role_auth_list' => $roleList , 'total' => $total , 'pagesize' => $pagesize , 'page' => $page,'search' => $params['search'],'sum_page'=>$sumPage]];
        }
        return ['code'=>200,'msg'=>'Success','data'=>['role_auth_list' => [] , 'total' => 0 , 'pagesize' => $pagesize , 'page' => $page,'search' => $params['search'],'sum_page'=>$sumPage]];
    }

    /**
     * 角色添加
     * @param $data
     * [
     * role_name
     * school_id
     *
     * auth_id
     * ]
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function doRoleInsert($data)
    {
        /**
         * 检查角色是否存在
         */
        $role = Role::query()
            ->where(['role_name'=>$data['role_name'],'school_id'=>$data['school_id'],'is_del'=>0])
            ->first();

        if($role){
            return response()->json(['code'=>205,'msg'=>'角色已存在']);
        }

        /**
         * 检查是否存在超管
         */
        $role = Role::query()
            ->where(['school_id'=> $data['school_id'],'is_super'=>'1'])
            ->first();
        $data['create_time'] = date('Y-m-d H:i:s');

        if (! empty($role)) {
            $data['is_super'] = 0;
        } else {
            $data['is_super'] = 1;
        }
        $roleGroupList = explode(',',$data['auth_id']);
        $roleGroupList = array_unique($roleGroupList);
        $roleGroupList = array_diff($roleGroupList,['0']);

        //插入用数据
        $insertRoleData = [
            'role_name' => $data['role_name'],
            'auth_desc' => $data['auth_desc'],
            'admin_id' => $data['admin_id'],
            'is_super' => $data['is_super'],
            'school_id' => $data['school_id']
        ];
        DB::beginTransaction();
        try{
            if ($roleId = Role::query()->insertGetId($insertRoleData)) {

                $roleRuleGroupData = [];
                foreach ($roleGroupList as $val) {
                    $roleRuleGroupData[] = [
                        'role_id' => $roleId,
                        'group_id' => $val
                    ];
                }
                if (! empty($roleRuleGroupData)) {
                    RoleRuleGroup::query()->insert($roleRuleGroupData);
                }
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $data['admin_id'],
                    'module_name'    =>  'Role',
                    'route_url'      =>  'admin/role/doRoleInsert',
                    'operate_method' =>  'insert',
                    'content'        =>  json_encode($data),
                    'ip'             =>  $_SERVER["REMOTE_ADDR"],
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return response()->json(['code'=>200,'msg'=>'添加成功']);
            } else {
                DB::rollback();
                return response()->json(['code'=>201,'msg'=>'添加失败']);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return ['code' => 500 , 'msg' => $e->__toString()];
        }
    }


    /**
     * 角色删除
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function doRoleDel($data)
    {
        if (Admin::query()->where(['role_id'=>$data['id'],'is_del'=>1])->count() > 0) {  //  角色使用中无法删除    5.14
            return response()->json(['code'=>205,'msg'=>'角色使用中,不能删除']);
        }

        $roleData = Role::query()->where('id',$data['id'])->first();
        if (empty($roleData)) {
            return response()->json(['code'=>201,'msg'=>'角色标识为空或缺少或类型不合法']);
        }
        $roleData = $roleData->toArray();

        if($roleData['is_super'] == 1){
            return response()->json(['code'=>203,'msg'=>'超管角色，不能删除']);
        }

        DB::beginTransaction();
        try {
            Role::query()
                ->where('id', $data['id'])
                ->update(['is_del' => 1]);

            RoleRuleGroup::query()
                ->where('role', $data['id'])
                ->update(['is_del' => 1]);

            AdminLog::insertAdminLog([
                'admin_id'       =>   CurrentAdmin::user()['id'],
                'module_name'    =>  'Role' ,
                'route_url'      =>  'admin/role/doRoleDel' ,
                'operate_method' =>  'update' ,
                'content'        =>  json_encode(['id'=>$data['id']]),
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);

            DB::commit();
            return response()->json(['code'=>200,'msg'=>'更改成功']);
        } catch (\Exception $e) {
            DB::rollback();
            return ['code' => 500 , 'msg' => $e->__toString()];
        }
    }


    /**
     * 角色更新
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function doRoleUpdate($data) {

        $admin = CurrentAdmin::user();
        $schoolId = $admin['school_id'];
        $adminId = $admin['id'];
        $count = Role::query()
            ->where('role_name','=',$data['role_name'])
            ->where('id','!=',$data['id'])
            ->where('school_id','=',$schoolId)
            ->where('is_del',0)
            ->count();
        if($count>=1){
            return response()->json(['code'=>205,'msg'=>'角色名称已存在']);
        }

        /**
         * 传输的 权限组
         */
        $ruleGroupData = explode(',',$data['auth_id']);
        $ruleGroupData = array_unique($ruleGroupData);
        $ruleGroupData = array_diff($ruleGroupData, ['0']);

        /**
         * 当前的权限组
         */
        $existsRuleGroupList = RoleRuleGroup::query()
            ->where('role_id', $data['id'])
            ->where('is_del', 0)
            ->select('group_id')
            ->get()
            ->toArray();
        $existsRuleGroupData = array_column($existsRuleGroupList, 'group_id');

        /**
         * 需要新增加的
         */
        $needInsertGroupIdList = array_diff($ruleGroupData, $existsRuleGroupData);
        $insertGroupData = [];
        if (! empty($needInsertGroupIdList)) {
            foreach ($needInsertGroupIdList as $val) {
                $insertGroupData[] = [
                    'role_id' => $data['id'],
                    'group_id' => $val
                ];
            }
        }
        /**
         * 需要删除的
         */
        $needDelGroupIdList = array_diff($existsRuleGroupData, $ruleGroupData);

        DB::beginTransaction();
        try {  //5.15

            Role::query()
                ->where('id', $data['id'])
                ->update([
                    'role_name' => $data['role_name'],
                    'auth_desc' => $data['auth_desc']
                ]);

            //删除
            if (! empty($needDelGroupIdList)) {
                RoleRuleGroup::query()
                    ->where('role_id', $data['id'])
                    ->whereIn('group_id', $needDelGroupIdList)
                    ->update(['is_del' => 1]);
            }
            //新增
            if (! empty($insertGroupData)) {
                RoleRuleGroup::query()
                    ->insert($insertGroupData);
            }

            AdminLog::insertAdminLog([
                'admin_id'       =>   CurrentAdmin::user()['id'] ,
                'module_name'    =>  'Role' ,
                'route_url'      =>  'admin/role/doRoleAuthUpdate' ,
                'operate_method' =>  'update' ,
                'content'        =>  json_encode($data),
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json(['code'=>200,'msg'=>'更改成功']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['code'=>500,'msg'=>$e->__toString()]);
        }
    }



    public function getRoleInfo($data) {

        $where = [];
        $updateArr = [];
        if(empty($data['id'])){
            return response()->json(['code'=>201,'msg'=>'参数为空或缺少参数']);
        }
        $roleInfo = Role::getRoleInfo(['id'=>$data['id'],'is_del' => 0],['id','role_name','auth_desc','school_id']);

        if(empty($roleInfo)){
            return response()->json(['code'=>204,'msg'=>'角色信息不存在']);
        }

        $data['school_status'] = CurrentAdmin::user()['school_status'];
        $schoolId =  CurrentAdmin::user()['school_id'];

        $authArr = self::getRuleGroupListBySchoolId($schoolId, $data['school_status']);
        $authArr  = getAuthArr($authArr);

        $groupList = self::getRoleRuleGroupList($data['id']);
        $groupIdList = array_column($groupList, 'group_id');

        $roleAuthData['data'] = $roleInfo;
        $roleAuthData['data']['map_auth_id'] = empty($groupIdList) ? null : implode(',', $groupIdList);

        $arr = [
            'code'=>200,
            'msg'=>'获取角色成功',
            'data'=>[
                'id' => $data['id'], //角色id
                'role_auth_data' =>$roleAuthData['data'],
                'auth' =>$authArr
            ]
        ];
        return  response()->json($arr);
    }

    /**
     * 获取当前网校的可用权限
     * @param $schoolId
     * @param int $schoolStatus
     * @return array
     */
    public static function getRuleGroupListBySchoolId($schoolId, $schoolStatus = 0)
    {
        //当前用户是总校 并且 还是总校的权限
        if($schoolStatus == 1 && $schoolId == 1) {

            //当前用户是总校 并且 还是总校的权限
            $authArr = RuleGroup::query()
                ->where('school_type', 1)
                ->where(['is_del' => 0, 'is_forbid' => 1])
                ->select(['id', 'title', 'parent_id'])
                ->get()
                ->toArray();

        } elseif ($schoolStatus == 1 && $schoolId != 1) {

            //当前用户是总校 查分校的权限
            $authArr = RuleGroup::query()
                ->where('school_type', 0)
                ->where(['is_del' => 0, 'is_forbid' => 1])
                ->select(['id', 'title', 'parent_id'])
                ->get()
                ->toArray();

        } else {

            //当前用户是分校 查分校的权限
            $roleId = Role::query()
                ->where(['school_id' => $schoolId, 'is_super' => 1])
                ->select('id')
                ->value('id');

            if (empty($roleId)) {
                $authArr = [];
            } else {
                $groupList = self::getRoleRuleGroupList($roleId);
                $groupIdList = array_column($groupList, 'group_id');
                //分校的数据
                $authArr = RuleGroup::query()
                    ->whereIn('id', $groupIdList)
                    ->where('school_type', 0)
                    ->where(['is_del'=>0,'is_forbid'=>1])
                    ->select(['id','title','parent_id'])
                    ->get()
                    ->toArray();

            }
        }
        return $authArr;
    }

    /**
     * 获取角色添加时显示的 权限组
     * @return \Illuminate\Http\JsonResponse
     */
    public  function getRoleInsert(){
        try{
            $adminInfo = CurrentAdmin::user();

            $authArr = self::getRuleGroupListBySchoolId($adminInfo['school_id'], $adminInfo['school_status']);
            $authArr  = getAuthArr($authArr);

            $arr = [
                'auth'=>$authArr,
                'school_id'=>$adminInfo['school_id'],
                'admin_id' =>$adminInfo['id'],
            ];
            return response()->json(['code' => 200 , 'msg' => '获取信息成功' , 'data' => $arr]);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->__toString()]);
        }
    }

    /**
     * 获取角色权限（不适用总部的超管）
     * @param $roleId
     * @return array
     */
    public static function getRoleRuleGroupList($roleId)
    {
        return RoleRuleGroup::query()
            ->where('role_id', $roleId)
            ->where('is_del', 0)
            ->select('group_id')
            ->get()
            ->toArray();
    }

    /**
     * @param $roleId
     * @param int $schoolType
     * @return array
     */
    public static function getRoleRouterList($roleId, $schoolType = 0)
    {
        $isSuper = 0;
        //总校 判断是否超级管理员
        if ($schoolType == 1) {
            $isSuper = Role::query()
                ->where('id', $roleId)
                ->where('is_del', 0)
                ->value('is_super');
        }
        //总校 且 为超级管理员
        if ($schoolType == 1 && $isSuper == 1) {
            $groupList = RuleGroup::query()
                ->where('school_type', 1)
                ->where('is_del', 0)
                ->where('is_forbid', 1)
                ->select('id as group_id')
                ->get()
                ->toArray();
        } else {
            $groupList =  self::getRoleRuleGroupList($roleId);
        }

        if(empty($groupList)){
            return ['code'=>204,'msg'=>'角色信息不存在'];
        }
        $adminRuths = RuleService::getGroupRouterAll(array_column($groupList, 'group_id'));
        if($adminRuths['code'] != 200){
            return ['code'=>$adminRuths['code'],'msg'=>$adminRuths['msg']];
        }
        return ['code'=>200,'msg'=>'success','data'=>$adminRuths['data']];

    }

    /**
     * 获取学校下 超级管理员角色信息
     * @param $school
     * @return array
     */
    public static function getSuperRoleBySchoolId($school)
    {
        $roleData = Role::query()->where('school_id', $school)
            ->where('is_super', 1)
            ->where('is_del', 0)
            ->select('id', 'role_name', 'auth_desc', 'admin_id', 'is_super', 'school_id')
            ->first();
        if (empty($roleData)) {
            return [];
        } else {
            return $roleData->toArray();
        }
    }
}
