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

        //获取总数
        $total = $customeQuery->count();
        //获取总页数
        $totalPage = ceil($total/$pageSize);

        //总数大于0
        if ($total > 0) {
            $customeList = $customeQuery->select(
                'id', 'parent_id', 'admin_id', 'school_id',
                'page_type', 'name', 'sign', 'url',
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
                'id', 'parent_id', 'admin_id', 'page_type',
                'name', 'sign', 'url', 'link_type',
                'is_new_open', 'sort', 'is_forbid', 'text'
            )
            ->first();

        if (! empty($customeQuery)) {
            $data = $customeQuery->toArray();
        } else {
            $data = [];
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

        //插入用 默认数据
        $insertData = [
            'admin_id' => $adminInfo->cur_admin_id,
            'school_id' => $adminInfo->school_id,
            'text' => '',
            'parent_id' => 0,
        ];

        //自定义单页
        if ($data['page_type'] == 1) {

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

        //插入用数据
        $insertData = array_merge($insertData, $data);

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
        $total = CustomPageConfig::query()
            ->where('id', $data['id'])
            ->where('school_id', $adminInfo->school_id)
            ->where('page_type', $data['page_type'])
            ->where('is_del', 0)
            ->count();
        if ($total == 0) {
            return [
                'code' => 403,
                'msg' => '自定义页面异常'
            ];
        }

        //自定义单页
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

        //更新用数据
        $updateData = $data;
        unset($updateData['id'], $updateData['page_type']);

        CustomPageConfig::query()
            ->where('id', $data['id'])
            ->update($updateData);

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

        CustomPageConfig::query()
            ->whereIn('id', $idList)
            ->where('school_id', $adminInfo->school_id)
            ->update(['is_del' => 1]);

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
