<?php


namespace App\Services\Admin\School;


use App\Models\AdminLog;
use App\Models\Notice;
use App\Tools\CurrentAdmin;

class NoticeService
{

    /**
     * 获取未读通知数量
     * @return array
     */
    public function getUnreadTotal()
    {
        //登录人信息
        $adminInfo = CurrentAdmin::user();

        //组装查询
        $total = Notice::query()
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0)
            ->where('is_read', 0)
            ->count();

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=>[
                'total' => $total,
            ]
        ];

    }

    /**
     * 获取协议列表
     * @param $page
     * @param $pageSize
     * @return array
     */
    public function getList($page, $pageSize)
    {
        //登录人信息
        $adminInfo = CurrentAdmin::user();

        //组装查询
        $noticeQuery = Notice::query()
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0);

        //获取总数
        $total = $noticeQuery->count();
        //获取总页数
        $totalPage = ceil($total/$pageSize);

        //总数大于0
        if ($total > 0) {
            $noticeList = $noticeQuery->select(
                'id', 'notice_type', 'title', 'is_read', 'create_time'
                )
                ->orderBy('id', 'desc')
                ->limit($pageSize)
                ->offset(($page - 1) * $pageSize)
                ->get()
                ->toArray();

        } else {
            $noticeList = [];
        }

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=>[
                'total' => $total,
                'total_page' => $totalPage,
                'page' => $page,
                'pagesize' => $pageSize,
                'list' => $noticeList
            ]
        ];

    }

    /**
     * 查看协议内容
     * @param $id 协议id
     * @return array
     */
    public function getInfo($id)
    {
        //获取登录者数据
        $adminInfo = CurrentAdmin::user();

        $noticeQuery = Notice::query()
            ->where('id', $id)
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0)
            ->select(
                'id', 'notice_type', 'title', 'is_read', 'create_time', 'text'
            )
            ->first();

        if (! empty($noticeQuery)) {
            $data = $noticeQuery->toArray();
        } else {
            return [
                'code' => 403,
                'msg' => '通知异常'
            ];
        }

        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=> $data
        ];
    }

    /**
     * 设置通知已读
     * @param $idList
     * @return array
     */
    public function readInfo($idList)
    {
        //当前操作员信息
        $adminInfo = CurrentAdmin::user();

        if (! is_array($idList)) {
            $idList = explode(',', $idList);
        }

        Notice::query()
            ->whereIn('id', $idList)
            ->where('school_id', $adminInfo->school_id)
            ->where('is_del', 0)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_admin_id' => $adminInfo->cur_admin_id]);

        //插入操作记录
        AdminLog::insertAdminLog([
            'admin_id'       =>   $adminInfo->cur_admin_id,
            'module_name'    =>  'Notice',
            'route_url'      =>  'admin/notice/readInfo',
            'operate_method' =>  'set',
            'content'        =>  json_encode(['id_list' => $idList]),
            'ip'             =>  $_SERVER['REMOTE_ADDR'],
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return [
            'code'=>200,
            'msg'=>'Success',
        ];
    }

}
