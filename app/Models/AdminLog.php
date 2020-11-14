<?php
namespace App\Models;

use App\Tools\CurrentAdmin;
use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model {
    //指定别的表名
    public $table      = 'ld_admin_operate_log';
    //时间戳设置
    public $timestamps = false;
    public static $admin_user;

    public static $operateList = [
        'add' => '添加',
        'courseDel' => '删除',
        'Del' => '删除',
        'delete' => '删除',
        'insert' => '添加',
        'insert/update' => '添加/更新',
        'recommend' => '设置',
        'set' => '设置',
        'update' => '更新',
    ];

    /*
     * @param  description   获取后端用户基本信息
     * @param  data          数组数据
     * @param  author        dzj
     * @param  ctime         2020-05-05
     * return  int
     */
    public static function getAdminInfo(){
        self::$admin_user['admin_user'] = \App\Tools\CurrentAdmin::user();
        return (object)self::$admin_user;
    }

    /*
     * @param  description   添加后台日志的方法
     * @param  data          数组数据
     * @param  author        dzj
     * @param  ctime         2020-04-27
     * return  int
     */
    public static function insertAdminLog($data) {

        if (empty($data['school_id'])) {
            $data['school_id'] = self::getAdminInfo()->admin_user->school_id;
        }
        return self::insertGetId($data);
    }




    /*
     * @param  description   获取用户列表
     * @param  参数说明       body包含以下参数[
     *     search       搜索条件 （非必填项）
     *     page         当前页码 （不是必填项）
     *     limit        每页显示条件 （不是必填项）
     *     school_id    学校id  （非必填项）
     * ]
     * @param author    lys
     * @param ctime     2020-04-29
     */
    public static function getLogList($body=[]){

        $adminUserInfo  = CurrentAdmin::user();  //当前登录用户所有信息

        $schoolId = $adminUserInfo->school_id;//学校


        $pageSize = empty($body['pagesize']) ? 15 : $body['pagesize'] ;
        $page     = empty($body['page']) ? 1: $body['page'];

        $page = (int)$page;
        $pageSize = (int)$pageSize;


        $query = self::query()
            ->join('ld_admin', 'ld_admin.id', '=', 'ld_admin_operate_log.admin_id')
            ->where('ld_admin.school_id', $schoolId);

        if (! empty($body['school_id'])) {
            $query->where('ld_admin_operate_log.school_id', $body['school_id']);
        }
        if (! empty($body['username'])) {
            $query->where('ld_admin.username', $body['username']);
        }

        $total = $query->count();
        $totalPage = ceil($total/$pageSize);

        $returnList = [];

        if ($total > 0) {
            $offset   = ($page - 1) * $pageSize;

            $logList = $query->selectRaw('ld_admin_operate_log.*,ld_admin.username')
                ->orderBy('ld_admin_operate_log.id', 'desc')
                ->offset($offset)
                ->limit($pageSize)
                ->get()
                ->toArray();

            if (! empty($logList)) {

                /**
                 * 获取学校数据
                 */
                $schoolIdList = array_column($logList, 'school_id');
                $schoolIdList = array_unique($schoolIdList);
                $schoolList = School::query()
                    ->whereIn('id', $schoolIdList)
                    ->select('id', 'name')
                    ->get()
                    ->toArray();

                $schoolList = array_column($schoolList, 'name', 'id');


                /**
                 * 获取路由信息
                 */
                $routerUrlList = array_column($logList, 'route_url');
                $routerUrlList = array_unique($routerUrlList);
                $routerList = [];
                $routerListBase = RuleRouter::query()
                    ->whereIn('back_url', $routerUrlList)
                    ->select('back_url', 'title')
                    ->get()
                    ->toArray();
                foreach ($routerListBase as $item) {
                    $routerList[strtolower($item['back_url'])] = $item['title'];
                }


                foreach ($logList as $item) {
                    $item['school_name'] = empty($schoolList[$item['school_id']]) ? '' : $schoolList[$item['school_id']];
                    $item['route_url_desc'] = empty($routerList[strtolower($item['route_url'])]) ? '' : $routerList[strtolower($item['route_url'])];
                    $item['module_name_desc'] = ''; //@todo
                    $item['operate_method_desc'] = empty(self::$operateList[$item['operate_method']]) ? '' : self::$operateList[$item['operate_method']];
                    array_push($returnList, $item);
                }
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
                'list' => $returnList
            ]
        ];
    }


    /*
     * @param  description   获取用户列表
     * @param  参数说明       body包含以下参数[
     *     search       搜索条件 （非必填项）
     *     page         当前页码 （不是必填项）
     *     limit        每页显示条件 （不是必填项）
     *     school_id    学校id  （非必填项）
     * ]
     * @param author    lys
     * @param ctime     2020-04-29
     */
    public static function getLogParams()
    {

        //操作人数据
        $adminInfo = self::getAdminInfo()['admin_user'];

        //学校列表
        $schoolList = [];
        if ($adminInfo->school_status == 1) {
            $schoolList = School::query()
                ->where('is_del', 1)
                ->select('id', 'name')
                ->get()
                ->toArray();
        }


        /**
         * @todo 需要完善
         */
        return [
            'code'=>200,
            'msg'=>'Success',
            'data'=>[
                'school_list' => $schoolList,
                'module_list' => [
                    [
                        'id' => '网校',
                        'name' => '网校',
                    ],
                    [
                        'id' => '服务',
                        'name' => '服务',
                    ]
                ],
                'operate_list' => [
                    [
                        'id' => 'insert',
                        'name' => '插入',
                    ],
                    [
                        'id' => 'update',
                        'name' => '更新',
                    ]
                ],
            ]
        ];
    }

}
