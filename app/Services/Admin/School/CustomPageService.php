<?php


namespace App\Services\Admin\School;


use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\CustomPageConfig;
use App\Tools\CurrentAdmin;

class CustomPageService
{

    /**
     * 获取自定义页面列表
     * @param $pageType
     * @param $page
     * @param $pageSize
     * @return array
     */
    public function getList($pageType, $page, $pageSize)
    {

        $adminInfo = CurrentAdmin::user();

        $customeQuery = CustomPageConfig::query()
            ->where('school_id', $adminInfo->school_id)
            ->where('page_type', $pageType)
            ->where('is_del', 0);
        if ($pageType == 1) {
            $customeQuery->where('parent_id', 0);
        }

        //获取总数
        $total = $customeQuery->count();
        //获取总页数
        $totalPage = ceil($total/$pageSize);

        //总数大于0
        if ($total > 0) {
            $customeList = $customeQuery->select(
                'id', 'parent_id', 'admin_id', 'school_id',
                'page_type', 'custom_type', 'name', 'sign', 'url',
                'link_type', 'is_new_open', 'sort', 'is_forbid', 'update_time'
                )
                ->orderBy('sort', 'asc')
                ->orderBy('id', 'desc')
                ->limit($pageSize)
                ->offset(($page - 1) * $pageSize)
                ->get()
                ->toArray();

        } else {
            $customeList = [];
        }

        $dataList = [];
        if (! empty($customeList)) {
            $adminIdList = array_column($customeList, 'admin_id');
            $adminList = Admin::query()
                ->whereIn('id', $adminIdList)
                ->select('id', 'username')
                ->get()
                ->toArray();
            $adminList = array_column($adminList, 'username', 'id');
            foreach ($customeList as $item) {
                $item['admin_name'] = empty($adminList[$item['admin_id']]) ? '' : $adminList[$item['admin_id']];
                array_push($dataList, $item);
            }
        }

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=>[
                'total' => $total,
                'total_page' => $totalPage,
                'page' => $page,
                'pagesize' => $pageSize,
                'list' => $dataList
            ]
        ];

    }

    /**
     * 详情
     * @param $id
     * @return array
     */
    public function details($id)
    {
        //获取登录者数据
        $adminInfo = CurrentAdmin::user();

        $customeQuery = CustomPageConfig::query()
            ->where('id', $id)
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0)
            ->select(
                'id', 'parent_id', 'admin_id', 'page_type', 'custom_type',
                'name', 'sign', 'url', 'link_type',
                'is_new_open', 'sort', 'is_forbid', 'title', 'text'
            )
            ->first();

        if (! empty($customeQuery)) {
            $data = $customeQuery->toArray();
            if ($data['page_type'] == 1 && $data['custom_type'] == 2 && $data['parent_id'] > 0) {
                return  [
                    'code' => 403,
                    'msg' => '页面数据错误'
                ];
            }

            $data['child_list'] = [];
            if ($data['page_type'] == 1 && $data['custom_type'] == 2) {
                $data['child_list'] = CustomPageConfig::query()
                    ->where('school_id', $adminInfo->school_id)
                    ->where('parent_id', $data['id'])
                    ->where('page_type', $data['page_type'])
                    ->where('custom_type', $data['custom_type'])
                    ->where('is_del', 0)
                    ->select('id', 'name', 'text')
                    ->get()
                    ->toArray();
            }

        } else {
            return  [
                'code' => 403,
                'msg' => '页面数据错误'
            ];
        }




        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=> $data
        ];

    }

    /**
     * 新增
     * @param $data
     * @return array
     */
    public function addInfo($data)
    {
        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        /**
         * 根据页面类型 分别处理数据
         */
        //自定义页面
        if ($data['page_type'] == 1) {
            //页面url
            $data['url'] = '/custom/' . $data['sign'];

            //验证sign是否重复
            $total = CustomPageConfig::query()
                ->where('school_id', $adminInfo->school_id)
                ->where('sign', $data['sign'])
                ->where('is_del', 0)
                ->count();

            if ($total > 0) {
                return [
                    'code' => 403,
                    'msg' => '此单页url已存在，请更换'
                ];
            }

        } else {
            //内容管理 无连接情况
            if ($data['link_type'] == 3) {
                $data['url'] = '';
            }
        }
        //子页面
        $childList = [];
        if (! empty($data['child_list'])) {
            $childList = $data['child_list'];
        }
        //清理子列表参数
        unset($data['child_list']);

        //插入用 默认数据
        $insertData = [
            'admin_id' => $adminInfo->cur_admin_id,
            'school_id' => $adminInfo->school_id,
            'text' => '',
            'parent_id' => 0,
            'custom_type' => 0,
            'title' => ''
        ];
        //插入用数据
        $insertData = array_merge($insertData, $data);

        //插入主数据
        $insertId = CustomPageConfig::query()
            ->insertGetId($insertData);

        //内容管理
        if ($data['page_type'] == 2) {

            $updateData = [
                'sign' => $insertId
            ];

            if ($data['link_type'] == 2) {
                $updateData['url'] = '/custom/' . $insertId;
                $data['url'] = '' . $updateData['url'];
            }

            CustomPageConfig::query()
                ->where('id', $insertId)
                ->update($updateData);

            $data['sign'] = (string)$insertId;
        }

        //子页面数据
        if (! empty($childList)) {
            $insertListData = [];
            foreach ($childList as $item) {
                $extendData = [
                    'parent_id' => $insertId,
                    'name' => $item['name'],
                    'sign' => '',
                    'url' => '',
                    'text' => $item['text']
                ];
                $insertListData[] = array_merge($insertData, $extendData);
            }
            CustomPageConfig::query()
                ->insert($insertListData);
        }


        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'SchoolSet',
            'route_url'      =>  'admin/pageset/addInfo',
            'operate_method' =>  'insert',
            'content'        =>  json_encode($data),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=> [
                'id' => $insertId,
                'url' => $data['url'],
                'sign' => $data['sign']
            ]
        ];

    }

    /**
     * 更改
     * @param $data
     * @return array
     */
    public function editInfo($data)
    {

        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        //获取数据是否存在
        $customPageInfo = CustomPageConfig::query()
            ->where('id', $data['id'])
            ->where('school_id', $adminInfo->school_id)
            ->where('page_type', $data['page_type'])
            ->where('is_del', 0)
            ->select('id', 'page_type', 'parent_id', 'custom_type')
            ->first();

        //空 返回异常
        if (empty($customPageInfo)) {
            return [
                'code' => 403,
                'msg' => '自定义页面异常'
            ];
        } else {
            //自定义页面 - 精确查找 - 只能编辑 - 组页面
            if ($customPageInfo->page_type == 1 && $customPageInfo->custom_type == 2 && $customPageInfo->parent_id > 0) {
                return [
                    'code' => 403,
                    'msg' => '自定义页面异常'
                ];
            }
        }

        //自定义页面
        if ($data['page_type'] == 1) {

            $data['url'] = '/custom/' . $data['sign'];

            //验证sign是否重复
            $total = CustomPageConfig::query()
                ->where('school_id', $adminInfo->school_id)
                ->where('sign', $data['sign'])
                ->where('page_type', $data['page_type'])
                ->where('is_del', 0)
                ->where('id', '<>', $data['id'])
                ->count();

            if ($total > 0) {
                return [
                    'code' => 403,
                    'msg' => '此单页url已存在，请更换'
                ];
            }

        } else {
            $data['sign'] =  (string)$data['id'];

            //默认链接
            if ($data['link_type'] == 2) {
                $data['url'] = '/custom/' . $data['id'];
            }

            //内容管理 无连接情况
            if ($data['link_type'] == 3) {
                $data['url'] = '';
            }

        }

        //子页面
        $childList = [];
        if (! empty($data['child_list'])) {
            $childList = $data['child_list'];
        }
        unset($data['child_list']);

        //需要更新的数组
        $needUpdateChildList = [];
        //需要新增的数组
        $needInsertChildList = [];
        //需要删除的 id 数组
        $needDelChildIdList = [];
        //当前存在的子页面id列表
        $existsChildIdList = [];

        //查看已有的子页面
        if ($customPageInfo->page_type == 1 && $customPageInfo->custom_type == 2) {
            //当前已存在的
            $existsChildIdList = CustomPageConfig::query()
                ->where('school_id', $adminInfo->school_id)
                ->where('parent_id', $data['id'])
                ->where('page_type', $customPageInfo->page_type)
                ->where('custom_type', $customPageInfo->custom_type)
                ->select('id')
                ->pluck('id')
                ->toArray();

            $curChildIdList = [];
            foreach ($childList as $item) {
                if ($item['id'] > 0) {
                    $curChildIdList[] = $item['id'];
                }

                //存在则更新
                if (in_array($item['id'], $existsChildIdList)) {
                    $needUpdateChildList[] = $item;
                } else {
                    $needInsertChildList[] = [
                        'admin_id' => $adminInfo->cur_admin_id,
                        'school_id' => $adminInfo->school_id,
                        'text' => $item['text'],
                        'parent_id' => $data['id'],
                        'page_type' => $data['page_type'],
                        'custom_type' => $data['custom_type'],
                        'name' => $item['name'],
                    ];
                }
            }
            $needDelChildIdList = array_diff($existsChildIdList, $curChildIdList);
        }




        //更新用数据
        $updateData = $data;
        unset($updateData['id'], $updateData['page_type']);

        CustomPageConfig::query()
            ->where('id', $data['id'])
            ->update($updateData);
        //需要删除的
        if (! empty($needDelChildIdList)) {
            CustomPageConfig::query()
                ->whereIn('id', $needDelChildIdList)
                ->update(['is_del' => 1]);
        }
        //需要更新的
        if (! empty($needUpdateChildList)) {
            foreach ($needUpdateChildList as $item) {
                CustomPageConfig::query()
                    ->where('id', $item['id'])
                    ->update([
                        'is_del' => 0,
                        'is_forbid' => 1,
                        'name' => $item['name'],
                        'text' => $item['text']
                    ]);
            }
        }
        //需要插入的
        if (! empty($needInsertChildList)) {
            CustomPageConfig::query()
                ->insert($needInsertChildList);
        }

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'SchoolSet',
            'route_url'      =>  'admin/pageset/editInfo',
            'operate_method' =>  'update',
            'content'        =>  json_encode($data),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=> [
                'id' => $data['id'],
                'url' => $data['url'],
                'sign' => $data['sign']
            ]
        ];

    }

    /**
     * 删除
     * @param $idList
     * @return array
     */
    public function delInfo($idList)
    {

        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        if (! is_array($idList)) {
            $idList = explode(',', $idList);
        }

        $customPageList = CustomPageConfig::query()
            ->whereIn('id', $idList)
            ->where('school_id', $adminInfo->school_id)
            ->select('id', 'parent_id', 'page_type', 'custom_type')
            ->get()
            ->toArray();

        $searchIdList = [];
        foreach ($customPageList as $item) {
            if ($item['page_type'] == 1 && $item['custom_type'] == 2 && $item['parent_id'] == 0) {
                array_push($searchIdList, $item['id']);
            }
        }

        CustomPageConfig::query()
            ->whereIn('id', $idList)
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0)
            ->update(['is_del' => 1]);

        //删除精确查找的子级数据
        if (! empty($searchIdList)) {
            CustomPageConfig::query()
                ->where('school_id', $adminInfo->school_id)
                ->whereIn('parent_id', $searchIdList)
                ->where('is_del', 0)
                ->update(['is_del' => 1]);
        }
        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'SchoolSet',
            'route_url'      =>  'admin/pageset/delInfo',
            'operate_method' =>  'delete',
            'content'        =>  json_encode(['id_list' => $idList]),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
        ];

    }

    /**
     * 开启关闭
     * @param $idList
     * @param $isForbid
     * @return array
     */
    public function openInfo($idList, $isForbid)
    {

        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        if (! is_array($idList)) {
            $idList = empty($idList) ? [] : explode(',', $idList);
        }

        if (! empty($idList)) {
            CustomPageConfig::query()
                ->whereIn('id', $idList)
                ->where('school_id', $adminInfo->school_id)
                ->update(['is_forbid' => $isForbid]);

        }

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'SchoolSet',
            'route_url'      =>  'admin/pageset/openInfo',
            'operate_method' =>  'set',
            'content'        =>  json_encode(['id_list' => $idList, 'is_forbid' => $isForbid]),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
        ];
    }

    /**
     * 排序
     * @param $infoList
     * @return array
     */
    public function sortInfo($infoList)
    {

        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        if (! is_array($infoList)) {
            $infoList = json_decode($infoList, true);
        }

        if (! empty($infoList)) {
            foreach ($infoList as $item) {
                CustomPageConfig::query()
                    ->whereIn('id', $item['id'])
                    ->where('school_id', $adminInfo->school_id)
                    ->update(['sort' => $item['sort']]);
            }
        }

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'SchoolSet',
            'route_url'      =>  'admin/pageset/sortInfo',
            'operate_method' =>  'set',
            'content'        =>  json_encode(['info_list' => $infoList]),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);


        return [
            'code'=>200,
            'msg'=>'Success',
        ];
    }

}
