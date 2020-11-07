<?php


namespace App\Services\Admin\School;


use App\Models\Admin;
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


    public function addInfo($data)
    {

        $adminInfo = CurrentAdmin::user();

        $insertData = [
            'admin_id' => $adminInfo->id,
            'school_id' => $adminInfo->school_id,
            'text' => '',
            'parent_id' => 0,
        ];

        //自定义单页
        if ($data['page_type'] == 1) {

            $data['parent_id'] = 0;
            $data['url'] = ''; //@todo

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

        }

        //内容管理 无连接情况
        if ($data['page_type'] == 2 && $data['link_type'] == 3) {
            $data['url'] = '';
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
                //@todo 处理当前的url
                $updateData['url'] = '';
                $data['url'] = '';
            }

            CustomPageConfig::query()
                ->where('id', $insertId)
                ->update($updateData);

            $data['sign'] = (string)$insertId;
        }

        //@todo 操作日志

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



}
