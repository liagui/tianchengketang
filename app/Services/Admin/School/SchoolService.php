<?php

/**
 * ysh
 * 2020-10-23
 */
namespace App\Services\Admin\School;

use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\AdminManageSchool;
use App\Models\SchoolBottomConfig;
use App\Models\SchoolConfig;
use App\Models\SchoolSeoConfig;
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
        //总校不允许更改
        if ($schoolId == 1) {
            return response()->json([ 'code' => 401, 'msg' => '管理的学校异常']);
        }

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


    /**
     * 获取配置数据
     * @param $schoolId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfig($schoolId)
    {
        //获取配置数据
        $configDataQuery = SchoolConfig::query()
            ->where('school_id', $schoolId)
            ->first();
        //数据处理
        if (empty($configDataQuery)) {
            $data = [
                'top_config' => '',
                'bottom_type_selected' => 0,
                'index_config' => '',
                'favicon_config' => '',
                'is_forbid_favicon' => 1,
            ];
        } else {

            $configInfo = $configDataQuery->toArray();
            $data = [
                'top_config' => $configInfo['top_config'],
                'bottom_type_selected' => $configInfo['bottom_type_selected'],
                'index_config' => $configInfo['index_config'],
                'favicon_config' => $configInfo['favicon_config'],
                'is_forbid_favicon' => $configInfo['is_forbid_favicon'],
            ];
        }


        $bottomList = SchoolBottomConfig::query()
            ->where('school_id', $schoolId)
            ->select('bottom_type', 'bottom_config')
            ->get()
            ->toArray();
        $data['bottom_list'] = $bottomList;

        return response()->json([ 'code' => 200, 'msg' => '获取成功', 'data' => $data]);

    }

    /**
     * 设置配置数据
     * @param $schoolId 学校id
     * @param $curType 类型
     * @param $curTypeSelected 当前选择 curtype = bottom_config 数据有效
     * @param $curContent 内容
     * @return \Illuminate\Http\JsonResponse
     */
    public function setConfig($schoolId, $curType, $curTypeSelected, $curContent)
    {
        //全部可操作类型
        $allType = [
            'top_config' => '',
            'index_config' => '',
            'favicon_config' => '',
            'bottom_type_selected' => 0,
            'bottom_config' => ''
        ];

        //验证类型合法性
        if (! in_array($curType, $allType)) {
            return response()->json([ 'code' => 403, 'msg' => '类型不合法']);
        }

        //操作人数据
        $adminInfo = CurrentAdmin::user();

        //底部设置
        if ($curType == 'bottom_config') {

            //当前的类型
            if (empty($curTypeSelected)) {
                return response()->json([ 'code' => 403, 'msg' => '类型不合法']);
            }

            //当前类型下的数据
            $schoolBottomQuery = SchoolBottomConfig::query()
                ->where('school_id', $schoolId)
                ->where('bottom_type', $curTypeSelected)
                ->first();


            if (empty($schoolBottomQuery)) {
                $insertData = [
                    'admin_id' => $adminInfo['id'],
                    'school_id' => $schoolId,
                    'bottom_type' => $curTypeSelected,
                    'bottom_config' => $curContent
                ];
                SchoolBottomConfig::query()->insert($insertData);
            } else {
                $schoolBottomQuery->bottom_config = $curContent;
                $schoolBottomQuery->save();
            }

        } else {

            if ($curType == 'bottom_type_selected') {
                $curContent = (int)$curContent;
                if (empty($curContent)) {
                    return response()->json([ 'code' => 403, 'msg' => '类型不合法']);
                }
            }

            //获取当前的配置数据
            $configQuery = SchoolConfig::query()
                ->where('school_id', $schoolId)
                ->first();

            //为空 填充其他默认值
            if (empty($configQuery)) {

                //默认值
                $insertData = [
                    'top_config' => '',
                    'index_config' => '',
                    'favicon_config' => '',
                    'bottom_type_selected' => 0,
                    'is_forbid_favicon' => 1,
                    'admin_id' => $adminInfo['id'],
                    'school_id' => $schoolId,
                ];
                $insertData[$curType] = $curContent;

                SchoolConfig::query()->insert($insertData);
            } else {
                $configQuery->$curType = $curContent;
                $configQuery->save();
            }

        }

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo['id'],
            'module_name'    =>  'School',
            'route_url'      =>  'admin/school/setConfig',
            'operate_method' =>  'update' ,
            'content'        =>  json_encode(['cur_type' => $curType, 'cur_type_selected' => $curTypeSelected, 'cur_content' => $curContent]),
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return response()->json([ 'code' => 200, 'msg' => '设置成功']);

    }

    /**
     * 获取学校SEO配置数据
     * @param $schoolId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSEOConfig($schoolId)
    {
        //浏览器图标配置
        $configQuery = SchoolConfig::query()
            ->where('school_id', $schoolId)
            ->first();
        if (empty($configQuery)) {
            $faviconInfo = [
                'is_forbid_favicon' => 1,
                'favicon_config' => ''
            ];
        } else {
            $configInfo = $configQuery->toArray();
            $faviconInfo = [
                'is_forbid_favicon' => $configInfo['is_forbid_favicon'],
                'favicon_config' => $configInfo['favicon_config']
            ];
        }
        //浏览器页面配置
        $pageList = SchoolSeoConfig::query()
            ->where('school_id', $schoolId)
            ->select('page_type', 'title', 'keywords', 'description', 'is_forbid')
            ->get()
            ->toArray();

        $data = [
            'favicon_info' => $faviconInfo,
            'page_list' => $pageList
        ];

        return response()->json([ 'code' => 200, 'msg' => '获取成功', 'data' => $data]);

    }

    /**
     * @param $schoolId
     * @param $pageType
     * @param $title
     * @param $keywords
     * @param $description
     */
    public function setPageSEOConfig($schoolId,$pageType, $title, $keywords, $description)
    {
        //查看当前数据
        $seoQuery = SchoolSeoConfig::query()
            ->where('school_id', $schoolId)
            ->where('page_type', $pageType)
            ->first();

        $adminInfo = CurrentAdmin::user();
        if (empty($seoQuery)) {
            $insertData = [
                'is_forbid' => 1,
                'admin_id' => $adminInfo['id'],
                'school_id' => $schoolId,
                'page_type' => $pageType,
                'title' => $title,
                'keywords' => $keywords,
                'description' => $description
            ];
            SchoolSeoConfig::query()->insert($insertData);

        } else {
            $seoQuery->title = $title;
            $seoQuery->keywords = $keywords;
            $seoQuery->description = $description;
            $seoQuery->save();
        }


        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo['id'] ,
            'module_name'    =>  'School' ,
            'route_url'      =>  'admin/school/setPageSEOConfig' ,
            'operate_method' =>  'update' ,
            'content'        =>  json_encode(['page_type' => $pageType, 'title' => $title, 'keywords' => $keywords, 'description' => $description]),
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return response()->json([ 'code' => 200, 'msg' => '设置成功']);

    }

    /**
     * SEO设置开启状态
     * @param $schoolId
     * @param $curType
     * @param $isForbid
     * @return \Illuminate\Http\JsonResponse
     */
    public function setSEOOpen($schoolId, $curType, $isForbid)
    {

        $adminInfo = CurrentAdmin::user();
        //浏览器设置
        if ($curType == 'favicon_config') {

            $configQuery = SchoolConfig::query()
                ->where('school_id', $schoolId)
                ->first();

            //设置默认数据
            if (empty($configQuery)) {

                $insertData = [
                    'admin_id' => $adminInfo['id'],
                    'school_id' => $schoolId,
                    'top_config' => '',
                    'bottom_type_selected' => 0,
                    'index_config' => '',
                    'favicon_config' => '',
                    'is_forbid_favicon' => $isForbid,
                ];

                SchoolConfig::query()->insert($insertData);
            } else {
                $configQuery->is_forbid_favicon = $isForbid;
                $configQuery->save();
            }

        } else {

            $seoQuery = SchoolSeoConfig::query()
                ->where('school_id', $schoolId)
                ->where('page_type', $curType)
                ->first();

            if (empty($seoQuery)) {
                $insertData = [
                    'admin_id' => $adminInfo['id'],
                    'school_id' => $schoolId,
                    'page_type' => $curType,
                    'is_forbid' => $isForbid
                ];

                SchoolSeoConfig::query()->insert($insertData);
            } else {
                $seoQuery->is_forbid = $isForbid;
                $seoQuery->save();
            }
        }

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo['id'] ,
            'module_name'    =>  'School' ,
            'route_url'      =>  'admin/school/setSEOOpen' ,
            'operate_method' =>  'update' ,
            'content'        =>  json_encode(['cur_type' => $curType, 'is_forbid' => $isForbid]),
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return response()->json([ 'code' => 200, 'msg' => '设置成功']);

    }

}
