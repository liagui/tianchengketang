<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Role\RoleService;
use Validator;
use App\Tools\CurrentAdmin;

class RoleController extends Controller {

    /**
     * 获取角色列表
     * @param RoleService $roleService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getRoleList(RoleService $roleService)
    {
        $data =  self::$accept_data;
        //判断传过来的数组数据是否为空
        if(!$data || !is_array($data)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        $return  = $roleService->getRoleList($data);
        return response()->json($return);

    }

    /**
     * 角色删除
     * @param RoleService $roleService
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function doRoleDel(RoleService $roleService){
        $data = self::$accept_data;
        if( !isset($data['id']) || empty($data['id'])  || $data['id']<=0 ){
            return response()->json(['code'=>201,'msg'=>'角色标识为空或缺少或类型不合法']);
        }
        return $roleService->doRoleDel($data);
    }

    /*
     * @param  upRoleStatus   添加角色
     * @param  $data=[
                'r_name'=> 角色名称
                'auth_id'=> 权限串
                'auth_desc'=> 角色描述
                'admin_id'=> 添加人
                'school_id'=> 所属学校id
        ]                 添加数组
     * @param  author    lys
     * @param  ctime     2020-04-30
     */
    //注：隐含问题 是不是超级管理员权限
    public function doRoleInsert(RoleService $roleService)
    {
        $data = self::$accept_data;
        if(!isset($data['role_name']) || empty($data['role_name'])){
           return response()->json(['code'=>201,'msg'=>'角色名称为空或缺少']);
        }
        if(!isset($data['auth_id']) || empty($data['auth_id'])){
          return response()->json(['code'=>201,'msg'=>'权限组id为空或缺少']);
        }
        if(!isset($data['auth_desc']) || empty($data['auth_desc'])){
            return response()->json(['code'=>201,'msg'=>'权限描述为空或缺少']);
        }
        unset($data['/admin/role/doRoleAuthInsert']);
        $data['admin_id'] = CurrentAdmin::user()['id'];
        $data['school_id'] = CurrentAdmin::user()['school_id'];

        return $roleService->doRoleInsert($data);
    }

    /*
     * @param  descriptsion   获取角色信息（编辑）
     * @param  $data=[
                'id'=> 角色id
        ]                 查询条件
     * @param  author    lys
     * @param  ctime     2020-04-30
     */
    public function getRoleInfo(RoleService $roleService){
        $data = self::$accept_data;
        $where = [];
        $updateArr = [];
        if( !isset($data['id']) ||  empty($data['id'])){
            return response()->json(['code'=>201,'msg'=>'参数为空或缺少参数']);
        }
        return $roleService->getRoleInfo($data);
    }

    /*
     * @param  descriptsion   编辑角色信息（编辑）
     * @param  $data=[
                'id'=> 角色id
                'role_name'=> 角色名称
                'auth_desc'=> 权限描述
                'auth_id'=> 权限id组

        ]
     * @param  author    lys
     * @param  ctime     2020-04-30
     */
    public function doRoleUpdate(RoleService $roleService) {
        $data = self::$accept_data;
        if( !isset($data['id']) ||  empty($data['id'])){
            return response()->json(['code'=>201,'msg'=>'角色id为空或缺少']);
        }
        if( !isset($data['role_name']) ||  empty($data['role_name'])){
            return response()->json(['code'=>201,'msg'=>'角色名称为空或缺少']);
        }
        if( !isset($data['auth_desc']) ||  empty($data['auth_desc'])){
            return response()->json(['code'=>201,'msg'=>'角色权限描述为空或缺少']);
        }
        if( !isset($data['auth_id']) ||  empty($data['auth_id'])){
            return response()->json(['code'=>201,'msg'=>'权限组id为空或缺少']);
        }
        if(isset($data['/admin/role/doRoleAuthUpdate'])){
            unset($data['/admin/role/doRoleAuthUpdate']);
        }
        return $roleService->doRoleUpdate($data);
    }


    /*
     * @param  description   获取角色权限列表
     * @param author    lys
     * @param ctime     2020-04-29
    */
    public  function getRoleInsert(RoleService $roleService){
        return $roleService->getRoleInsert();
    }

}
