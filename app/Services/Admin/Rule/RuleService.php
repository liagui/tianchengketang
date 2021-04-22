<?php

namespace App\Services\Admin\Rule;

use App\Models\RuleRouter;
use App\Models\RuleGroup;
use App\Models\RoleGroupRouter;

class RuleService
{
    /*
     * @param  descriptsion 权限查询(全部)
     * @param  $auth_id     权限id组
     * @param  author      lys
     * @param  ctime   2020/4/27 15:00
     * return  array
     */
    public static function getGroupRouterAll($groupId)
    {

        //判断权限id是否为空
        if(empty($groupId)){
            return ['code'=>202,'msg'=>'参数类型有误'];
        }

        if (!is_array($groupId)) {
            $groupIdList = explode(',',$groupId);
        } else {
            $groupIdList = $groupId;
        }

        /**
         * 获取权限组数据
         */
        $groupList = RuleGroup::query()
            ->whereIn('id',$groupIdList)
            ->where(['is_del' => 0, 'is_forbid'=>1])
            ->select('id','title','parent_id')
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
        if (empty($groupList)) {
            return ['code'=>204,'msg'=>'权限信息不存在,请联系管理员'];
        }

        //当前可用的 权限组id
        $groupIdList = array_column($groupList, 'id');
//
//        /**
//         * 获取组与路由关系
//         */
//        $groupRouterList = RuleGroupRouter::query()
//            ->whereIn('group_id', $groupIdList)
//            ->where(['is_del' => 0])
//            ->select('group_id', 'router_id')
//            ->get()
//            ->toArray();
//        if (empty($groupRouterList)) {
//            return ['code'=>204,'msg'=>'权限信息不存在,请联系管理员'];
//        }
//        $routerIdList = array_column($groupRouterList, 'router_id');
//        /**
//         * 获取路由信息
//         */
//        $routerList = RuleRouter::query()
//            ->whereIn('id', $routerIdList)
//            ->where(['is_del' => 0, 'is_forbid' => 1])
//            ->select('id', 'back_url')
//            ->get()
//            ->toArray();
//        if (empty($routerList)) {
//            return ['code'=>204,'msg'=>'权限信息不存在,请联系管理员'];
//        }

//        /**
//         * 将关系数据处理成以组id的数据
//         */
//        $groupRouterData = [];
//        foreach ($groupRouterList as $item) {
//            $groupRouterData[$item['group_id']][] = $item['router_id'];
//        }

//        /**
//         * 将路由数据处理
//         */
//        $routerData = array_column($routerList,'back_url','id');

//        /**
//         * 将权限组 分组
//         */
//        $groupData = [];
//        $secondGroupData = [];
//        foreach ($groupList as $key => $item) {
//            if ($item['parent_id'] == 0) {
//                $groupData[$item['id']] = $item;
//            } else {
//                $secondGroupData[$item['parent_id']][] = $item;
//            }
//        }
//
//        /**
//         * 循环处理数据
//         */
//        foreach ($groupData as $key => $item) {
//            //外层数据不存在
//            if (empty($item['id'])) {
//                unset($groupData[$key]);
//            } else {
//                //顶层数据存在
//                if (! empty($groupRouterData[$item['id']])) {
//
//                    //获取第一个子类
//                    $routerId = $groupRouterData[$item['id']][0];
//                    $item['auth_id'] = $routerId;
//                    $item['name'] = !isset($routerData[$routerId]) ?'':substr(substr($routerData[$routerId],5),0,-8);
//                    //处理子节点
//                    if (! empty($item['child_array'])) {
//                        foreach ($item['child_array'] as $cKey => $cItem) {
//                            if (! empty($groupRouterData[$cItem['id']])) {
//                                $routerId = $groupRouterData[$cItem['id']][0];
//                                $cItem['auth_id'] = $routerId;
//                                $cItem['name'] = !isset($routerData[$routerId]) ? '':substr($routerData[$routerId],5);
//                                $item['child_array'][$cKey] = $cItem;
//                            } else {
//                                $cItem['auth_id'] = 0;
//                                $cItem['name'] = '';
//                                $item['child_array'][$cKey] = $cItem;
////                                unset($item['child_array'][$cKey]);
//                            }
//                            if (empty($item['child_array'])) {
//                                unset($groupData[$key]);
//                            }
//                        }
//                    }
//                    $groupData[$key] = $item;
//                } else {
//                    $item['auth_id'] = 0;
//                    $item['name'] = '';
////                    unset($groupData[$key]);
//                }
//            }
//        }

        if($groupIdList){
            return ['code'=>200,'msg'=>'获取权限信息成功','data'=>$groupIdList];
        }else{
            return ['code'=>204,'msg'=>'权限信息不存在,请联系管理员'];
        }
    }



    /**
     * 获取路由信息
     * @param $backUrl
     * @return array|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public static function getRouterInfoByUrl($backUrl)
    {
        $return = RuleRouter::query()
            ->where(['back_url'=>$backUrl])
            ->where('is_del', 0)
            ->where('is_forbid', 1)
            ->first();
        if (! empty($return)) {
            $return = $return->toArray();
        } else {
            $return = [];
        }
        return $return;
    }

    /**
     * 获取 某个路由是否属于 权限组
     */
    public static function getRouterListById($routerIdList, $groupIdList = [])
    {
        
        $query =  RuleGroupRouter::query();

        if (! empty($routerIdList)) {
            $query->whereIn('router_id', $routerIdList);
        }
        if (! empty($groupIdList)) {
            $query->whereIn('group_id', $groupIdList);
        }
        return $query->where('is_del', 0)
            ->select('id', 'group_id')
            ->get()
            ->toArray();

    }

}
