<?php

/**
 * ysh
 * 2020-10-23
 */
namespace App\Services\Admin\School;

use App\Models\Admin;
use App\Models\AdminManageSchool;
use App\Services\Admin\Role\RoleService;
use App\Tools\CurrentAdmin;
use JWTAuth;

class SchoolService
{

    /**
     * 获取
     * @param $schoolId
     */
    public function getManageSchoolToken($schoolId)
    {
        $userInfo = CurrentAdmin::user();
        if (empty($userInfo)) {
            return response()->json([ 'code' => 401, 'msg' => '用户信息错误']);
        }
        if ($userInfo['school_status'] != 1) {
            return response()->json([ 'code' => 401, 'msg' => '用户信息错误']);
        }

        //当前用户是否有管理网校的权限
        if ($userInfo['is_manage_all_school'] != 1) {
            $adminManageSchoolInfo = AdminManageSchool::query()
                ->where('admin_id', $userInfo['id'])
                ->where('school_id', $schoolId)
                ->where('is_del', 0)
                ->first();
            if (empty($adminManageSchoolInfo)) {
                return response()->json([ 'code' => 401, 'msg' => '管理员权限异常']);
            }
        }

        $roleInfo = RoleService::getSuperRoleBySchoolId($schoolId);
        if (empty($roleInfo)) {
            return response()->json([ 'code' => 401, 'msg' => '角色信息错误']);
        }

        $tokenUser = Admin::query()->where('school_id', $schoolId)
            ->where('role_id', $roleInfo['id'])
            ->where('is_del', 1)
            ->where('is_forbid',1)
            ->orderBy('id', 'asc')
            ->first();
        if (empty($tokenUser)) {
            return response()->json([ 'code' => 401, 'msg' => '管理员信息异常']);
        }
        $token = JWTAuth::fromUser($tokenUser);
        $tokenData = [
            'token' => $token,
            'school_status' => $tokenUser['school_status']
        ];
        return response()->json([ 'code' => 200, 'msg' => '获取成功', 'data' => $tokenData]);

    }
}
