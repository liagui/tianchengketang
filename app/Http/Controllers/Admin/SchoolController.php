<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\JWTRoleAuth;
use App\Models\Role;
use App\Models\RoleRuleGroup;
use App\Models\SchoolConnectionsCard;
use App\Models\SchoolConnectionsDistribution;
use App\Models\SchoolConnectionsLog;
use App\Models\SchoolTrafficLog;
use App\Models\SchoolResource;
use App\Services\Admin\Role\RoleService;
use App\Models\Admin as Adminuser;
use App\Models\School;
use App\Models\PaySet;
use App\Services\Admin\School\SchoolService;
use Illuminate\Support\Facades\Validator;
use App\Tools\CurrentAdmin;
use App\Models\AdminLog;
use App\Models\RuleGroup;
use App\Models\FootConfig;
use Illuminate\Support\Facades\DB;
use Log;

class SchoolController extends Controller
{

    public function details(){
        $data = self::$accept_data;
        $validator = Validator::make($data,
            ['school_id' => 'required|integer'],
            School::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $arr = School::where(['id'=>$data['school_id'],'is_del'=>1])->select('name','logo_url','introduce','dns')->first();
        return response()->json(['code'=>200,'msg'=>'success','data'=>$arr]);
    }
    /*
    * @param  description 获取分校列表
    * @param  参数说明       body包含以下参数[
    *     school_name       搜索条件
    *     school_dns        分校域名
    *     page         当前页码
    *     limit        每页显示条数
    * ]
    * @param author    lys
    * @param ctime     2020-05-05
    */
    public function getSchoolList(){
        $data = self::$accept_data;

        $pagesize = isset($data[ 'pagesize' ]) && $data[ 'pagesize' ] > 0 ? $data[ 'pagesize' ] : 15;
        $page = isset($data[ 'page' ]) && $data[ 'page' ] > 0 ? $data[ 'page' ] : 1;

        $offset = ($page - 1) * $pagesize;
        $where[ 'name' ] = empty($data[ 'school_name' ]) || !isset($data[ 'school_name' ]) ? '' : $data[ 'school_name' ];
        $where[ 'dns' ] = empty($data[ 'school_dns' ]) || !isset($data[ 'school_dns' ]) ? '' : $data[ 'school_dns' ];
        $school_count = School::where(function ($query) use ($where) {
            if ($where[ 'name' ] != '') {
                $query->where('name', 'like', '%' . $where[ 'name' ] . '%');
            }
            if ($where[ 'dns' ] != '') {
                $query->where('dns', 'like', '%' . $where[ 'dns' ] . '%');
            }
            $query->where('is_del', '=', 1);
        })->count();
        $sum_page = ceil($school_count / $pagesize);
        if ($school_count > 0) {
            $schoolArr = School::where(function ($query) use ($where) {
                if ($where[ 'name' ] != '') {
                    $query->where('name', 'like', '%' . $where[ 'name' ] . '%');
                }
                if ($where[ 'dns' ] != '') {
                    $query->where('dns', 'like', '%' . $where[ 'dns' ] . '%');
                }
                $query->where('is_del', '=', 1);
            })->select('id', 'name', 'logo_url', 'dns', 'is_forbid', 'logo_url')->offset($offset)->limit($pagesize)->get();

            return response()->json([ 'code' => 200, 'msg' => 'Success', 'data' => [ 'school_list' => $schoolArr, 'total' => $school_count, 'pagesize' => $pagesize, 'page' => $page, 'sum_page' => $sum_page, 'name' => $where[ 'name' ], 'dns' => $where[ 'dns' ] ] ]);
        }
        return response()->json([ 'code' => 200, 'msg' => 'Success', 'data' => [ 'school_list' => [], 'total' => 0, 'pagesize' => $pagesize, 'page' => $page, 'sum_page' => $sum_page, 'name' => $where[ 'name' ], 'dns' => $where[ 'dns' ] ] ]);
    }

    /*
     * @param  description 修改分校状态 (删除)
     * @param  参数说明       body包含以下参数[
     *     school_id      分校id
     * ]
     * @param author    lys
     * @param ctime     2020-05-06
     */
    public function doSchoolDel()
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [ 'school_id' => 'required|integer' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        try {
            DB::beginTransaction();
            $school = School::find($data[ 'school_id' ]);
            $school->is_del = 0;
            if (!$school->save()) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '删除失败,请重试' ]);
            }
            if (Adminuser::upUserStatus([ 'school_id' => $school[ 'id' ] ], [ 'is_del' => 0 ])) {
                AdminLog::insertAdminLog([
                    'admin_id'       => CurrentAdmin::user()[ 'id' ],
                    'module_name'    => 'School',
                    'route_url'      => 'admin/school/doSchoolDel',
                    'operate_method' => 'update',
                    'content'        => json_encode($data),
                    'ip'             => $_SERVER[ "REMOTE_ADDR" ],
                    'create_at'      => date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return response()->json([ 'code' => 200, 'msg' => '删除成功' ]);
            } else {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '删除失败,请重试' ]);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json([ 'code' => 500, 'msg' => $ex->__toString() ]);
        }
    }

    /*
     * @param  description 修改分校状态 (启禁)
     * @param  参数说明       body包含以下参数[
     *     school_id      分校id
     * ]
     * @param author    lys
     * @param ctime     2020-05-06
     * @2020/10/29 接口已弃用, 新代码在当前接口下方
     */
    public function doSchoolForbid_old(){
        /*$data = self::$accept_data;
        $validator = Validator::make($data,
            [ 'school_id' => 'required|integer' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        try {
            DB::beginTransaction();
            $school = School::where([ 'id' => $data[ 'school_id' ], 'is_del' => 1 ])->first();
            if ($school[ 'is_forbid' ] != 1) {
                $school->is_forbid = 1;
                $is_forbid = 1;
                $wx_pay_state = 1;
                $zfb_pay_state = 1;
                $hj_wx_pay_state = 1;
                $hj_zfb_pay_state = 1;
                $yl_pay_state = 1;

            } else {
                $school->is_forbid = 0;
                $is_forbid = 0;
                $wx_pay_state = -1;
                $zfb_pay_state = -1;
                $hj_wx_pay_state = -1;
                $hj_zfb_pay_state = -1;
                $yl_pay_state = -1;
            }
            if (!$school->save()) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '更新失败' ]);
            }
            if (!Adminuser::upUserStatus([ 'school_id' => $school[ 'id' ] ], [ 'is_forbid' => $is_forbid ])) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '更新失败' ]);
            }
            if (PaySet::where('school_id', $school[ 'id' ])->update([ 'wx_pay_state' => $wx_pay_state, 'zfb_pay_state' => $zfb_pay_state, 'hj_wx_pay_state' => $hj_wx_pay_state, 'hj_zfb_pay_state' => $hj_zfb_pay_state, 'yl_pay_state' => $yl_pay_state, 'update_at' => date('Y-m-d H:i:s') ])) {
                $data[ 'is_forbid' ] = $is_forbid; //修改后的状态
                AdminLog::insertAdminLog([
                    'admin_id'       => CurrentAdmin::user()[ 'id' ],
                    'module_name'    => 'School',
                    'route_url'      => 'admin/school/doSchoolForbid',
                    'operate_method' => 'update',
                    'content'        => json_encode($data),
                    'ip'             => $_SERVER[ "REMOTE_ADDR" ],
                    'create_at'      => date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return response()->json([ 'code' => 200, 'msg' => '更新成功' ]);
            } else {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '更新失败' ]);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json([ 'code' => 500, 'msg' => $ex->__toString() ]);
        }*/

    }

    /**
     * 修改分校状态 (正常, 禁用前台, 禁用后台, 全部禁用)
     * @param school_id int 学校
     * @param status int[0-3] 状态:0=禁用,1=正常,2=禁用前台,3=禁用后台,4=启用前台,5=启用后台
     * @author 赵老仙
     * @time 2020/10/27
     * @return \Illuminate\Http\JsonResponse
     */
    public function doSchoolForbid(){
        $data = self::$accept_data;
        $validator = Validator::make($data,
            ['school_id' => 'required|integer'],
            ['status' => 'required|integer'],
            School::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        if(!in_array($data['status'],[1,2,3,4,5,0]))
        {
            return response()->json(['code'=>204,'msg'=>'无效的状态']);
        }

        try{
            //DB::beginTransaction();
            $school = School::where(['id'=>$data['school_id'],'is_del'=>1])->first();
            if($school['is_forbid']==$data['status']){
                //DB::rollBack();
                return response()->json(['code'=>200,'msg'=>'没有数据更新']);
            }

            //
            switch($data['status']){
                case 0://禁用
                    $is_forbid = 0;//学校账号
                    $wx_pay_state = -1;//以下为支付管理
                    $zfb_pay_state = -1;
                    $hj_wx_pay_state = -1;
                    $hj_zfb_pay_state = -1;
                    $yl_pay_state = -1;
                    break;
                case 1://正常
                    $is_forbid = 1;
                    $wx_pay_state = 1;
                    $zfb_pay_state = 1;
                    $hj_wx_pay_state = 1;
                    $hj_zfb_pay_state = 1;
                    $yl_pay_state = 1;
                    break;
                case 2://禁用前台
                    if($school['is_forbid']==0){
                        //$is_forbid = 0;//当前账号禁用状态下, 禁用前台优先级较低, 不改变原始状态
                    }elseif($school['is_forbid']==1){
                        $is_forbid = 2;//账号正常状态下, 只禁用前台
                        $wx_pay_state = -1;
                        $zfb_pay_state = -1;
                        $hj_wx_pay_state = -1;
                        $hj_zfb_pay_state = -1;
                        $yl_pay_state = -1;
                    }elseif($school['is_forbid']==3){
                        $is_forbid = 0;//账号禁用后台状态下, 此时改为全部禁用
                        $wx_pay_state = -1;
                        $zfb_pay_state = -1;
                        $hj_wx_pay_state = -1;
                        $hj_zfb_pay_state = -1;
                        $yl_pay_state = -1;
                    }
                    break;
                case 3://禁用后台
                    if($school['is_forbid']==0){
                        //$is_forbid = 0;//当前账号禁用状态下, 禁用后台优先级较低, 不改变原始状态
                    }elseif($school['is_forbid']==1){
                        $is_forbid = 3;//账号正常状态下, 只禁用后台
                    }elseif($school['is_forbid']==2){
                        $is_forbid = 0;//账号禁用前台状态下, 增加禁用后台, 此时为全部禁用
                    }
                    break;
                case 4://启用前台
                    if($school['is_forbid']==0){
                        $is_forbid = 3;//当前账号禁用状态下, 启用前台, 则状态改为只禁用后台==3
                        $wx_pay_state = 1;
                        $zfb_pay_state = 1;
                        $hj_wx_pay_state = 1;
                        $hj_zfb_pay_state = 1;
                        $yl_pay_state = 1;
                    }elseif($school['is_forbid']==1){
                        //$is_forbid = 1;//账号正常状态下, 启用前台优先级较低, 不改变状态
                    }elseif($school['is_forbid']==2){
                        $is_forbid = 1;//账号只禁用前台状态下, 此时改为全部开启
                        $wx_pay_state = 1;
                        $zfb_pay_state = 1;
                        $hj_wx_pay_state = 1;
                        $hj_zfb_pay_state = 1;
                        $yl_pay_state = 1;
                    }elseif($school['is_forbid']==3){
                        //$is_forbid = 3;//账号只禁用后台状态下, 前台是开启的, 不用改变状态
                    }
                    break;
                case 5://启用后台
                    if($school['is_forbid']==0){
                        $is_forbid = 2;//当前账号禁用状态下, 启用后台, 则状态改为只禁用前台==2
                    }elseif($school['is_forbid']==1){
                        //$is_forbid = 1;//账号正常状态下, 启用后台优先级较低, 不改变状态
                    }elseif($school['is_forbid']==2){
                        //说$is_forbid = 2;//账号只禁用前台状态下, 后台是开启的, 不需要做改变
                    }elseif($school['is_forbid']==3){
                        $is_forbid = 1;//账号只禁用后台状态下, 改为全部开启
                    }
                    break;
            }

            if(isset($is_forbid)){
                $school->is_forbid = $is_forbid;
                if(!$school->save()){
                    //DB::rollBack();
                    return response()->json(['code' => 203 , 'msg' => '更新失败']);
                }
                // 账号状态依然保持 0和 1
                $is_forbid = in_array($is_forbid,[0,3])?0:1;
                Adminuser::upUserStatus(['school_id'=>$school['id']],['is_forbid'=>$is_forbid]);
                    //DB::rollBack();
                    //return response()->json(['code' => 203 , 'msg' => '更新失败']);

            }

            $res = true;
            if(isset($zfb_pay_state)){
                $update = [
                    'wx_pay_state'=>$wx_pay_state,
                    'zfb_pay_state'=>$zfb_pay_state,
                    'hj_wx_pay_state'=>$hj_wx_pay_state,
                    'hj_zfb_pay_state'=>$hj_zfb_pay_state,
                    'yl_pay_state'=>$yl_pay_state,
                    'update_at'=>date('Y-m-d H:i:s')
                ];
                $res = PaySet::where('school_id',$school['id'])->update($update);
            }

            if($res){
                //$data['is_forbid'] = $is_forbid; //修改后的状态
                AdminLog::insertAdminLog([
                    'admin_id'       =>   CurrentAdmin::user()['id']?:0 ,
                    'module_name'    =>  'School' ,
                    'route_url'      =>  'admin/school/doSchoolForbid' ,
                    'operate_method' =>  'update',
                    'content'        =>  json_encode($data),
                    'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                //DB::commit();
                return response()->json(['code' => 200 , 'msg' => '更新成功']);
            } else {
                //DB::rollBack();
                return response()->json(['code' => 203 , 'msg' => '更新失败']);
            }
        } catch (\Exception $ex) {
            //DB::rollBack();
            /*echo $ex->getMessage();
            echo '<br>';
            echo $ex->getLine();
            die();*/
            return response()->json(['code' => 500 , 'msg' => $ex->__toString()]);
        }

    }

    /*
     * @param  description 学校添加
     * @param  参数说明       body包含以下参数[
     *  'name' =>分校名称
        'dns' =>分校域名
        'logo_url' =>分校logo
        'introduce' =>分校简介
        'username' =>登录账号
        'password' =>登录密码
        'pwd' =>确认密码
        'realname' =>联系人(真实姓名)
        'mobile' =>联系方式
     * ]
     * @param author    lys
     * @param ctime     2020-05-06
     */
    public function doInsertSchool()
    {
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        $data = self::$accept_data;
        $validator = Validator::make(
            $data,
            [ 'name'          => 'required',
              'dns'           => 'required',
              'logo_url'      => 'required',
              'introduce'     => 'required',
              'username'      => 'required',
              'password'      => 'required',
              'pwd'           => 'required',
              'realname'      => 'required',
              'mobile'        => 'required|regex:/^1[3456789][0-9]{9}$/',
              'live_price'    => 'numeric|min:0',
              'storage_price' => 'numeric|min:0',
              'flow_price'    => 'numeric|min:0',
              'start_time' => 'required|date',
              'end_time' => 'required|date',
            ],
            School::message());

        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        if ($data[ 'password' ] != $data[ 'pwd' ]) {
            return response()->json([ 'code' => 206, 'msg' => '两次密码不一致' ]);
        }
        $count = School::where('name', $data[ 'name' ])->where('is_del', 1)->count();
        if ($count > 0) {
            return response()->json([ 'code' => 205, 'msg' => '网校名称已存在' ]);
        }
        $count = Adminuser::where('username', $data[ 'username' ])->count();
        if ($count > 0) {
            return response()->json([ 'code' => 205, 'msg' => '用户名已存在' ]);
        }
        $date = date('Y-m-d H:i:s');
        try {
            DB::beginTransaction();
            $school = [
                'name'         => $data[ 'name' ],
                'dns'          => $data[ 'dns' ],
                'logo_url'     => $data[ 'logo_url' ],
                'introduce'    => $data[ 'introduce' ],
                'admin_id'     => CurrentAdmin::user()[ 'id' ],
                'account_name' => !isset($data[ 'account_name' ]) || empty($data[ 'account_name' ]) ? '' : $data[ 'account_name' ],
                'account_num'  => !isset($data[ 'account_num' ]) || empty($data[ 'account_num' ]) ? '' : $data[ 'account_num' ],
                'open_bank'    => !isset($data[ 'open_bank' ]) || empty($data[ 'open_bank' ]) ? '' : $data[ 'open_bank' ],
                'create_time'  => $date
            ];
            /////////////////////////直播,空间,流量单价,是否展示分校入口:1=是,2=否
            if (isset($data[ 'live_price' ])) {
                $school[ 'live_price' ] = $data[ 'live_price' ];
            }
            if (isset($data[ 'storage_price' ])) {
                $school[ 'storage_price' ] = $data[ 'storage_price' ];
            }
            if (isset($data[ 'flow_price' ])) {
                $school[ 'flow_price' ] = $data[ 'flow_price' ];
            }
            if(isset($data['ifinto'])){
                $school['ifinto'] = ($data['ifinto']==true || $data['ifinto']=='true')?1:0;
            }
            $school['start_time'] = $data['start_time'];
            $school['end_time'] = $data['end_time'];
            //////////////////laoxian 2020/10/23 新增end
            $school_id = School::insertGetId($school);
            if ($school_id < 1) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '创建学校未成功' ]);
            }
            $admin = [
                'username'      => $data[ 'username' ],
                'password'      => password_hash($data[ 'password' ], PASSWORD_DEFAULT),
                'realname'      => $data[ 'realname' ],
                'mobile'        => $data[ 'mobile' ],
                'role_id'       => 0,
                'admin_id'      => CurrentAdmin::user()[ 'id' ],
                'school_id'     => $school_id,
                'school_status' => 0,
            ];

            $admin_id = Adminuser::insertGetId($admin);
            if ($admin_id < 0) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '创建账号未成功!' ]);
            }
            $schoolRes = School::where('id', $school_id)->update([ 'super_id' => $admin_id, 'update_time' => date('Y-m-d H:i:s') ]);
            if (!$schoolRes) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '创建账号未成功!!' ]);
            }

            $page_head_logo_insert = [
                [ 'parent_id' => 0, 'name' => '首页', 'url' => '/home', 'type' => 1, 'sort' => 1, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '课程', 'url' => '/onlineStudent', 'type' => 1, 'sort' => 2, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '公开课', 'url' => '/courses', 'type' => 1, 'sort' => 3, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '题库', 'url' => '/question', 'type' => 1, 'sort' => 4, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '新闻', 'url' => '/newsNotice', 'type' => 1, 'sort' => 5, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '名师', 'url' => '/teacher', 'type' => 1, 'sort' => 6, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '对公购买', 'url' => '/corporatePurchase', 'type' => 1, 'sort' => 7, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '扫码支付', 'url' => '/scanPay', 'type' => 1, 'sort' => 8, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 0 ],


            ];
            $pany_insert = [ 'parent_id' => 0, 'name' => $data[ 'name' ], 'type' => 3, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ];
            $page_foot_pid_insert = [
                [ 'parent_id' => 0, 'name' => '服务声明', 'url' => '/service/', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '关于我们', 'url' => '/about/', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '联系我们', 'url' => '/contactUs/', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '友情链接', 'url' => '', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
            ];
            $my_insert = [
                [ 'parent_id' => 0, 'name' => '关于我们', 'text' => '关于我们', 'type' => 5, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                [ 'parent_id' => 0, 'name' => '联系客服', 'text' => '联系客服', 'type' => 5, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
            ];
            $footPidIds = $footOne = $fooTwo = $fooThree = $footFore = [];
            foreach ($page_foot_pid_insert as $key => $pid) {
                $footPidId = FootConfig::insertGetId($pid);
                if ($footPidId < 1) {
                    DB::rollBack();
                    return response()->json([ 'code' => 203, 'msg' => '页面配置创建未成功!' ]);
                }
                array_push($footPidIds, $footPidId);
            }
            foreach ($footPidIds as $k => $id) {
                switch ($k) {
                    case '0':
                        $footOne = [
                            [ 'parent_id' => $id, 'name' => '服务规则', 'url' => 'rule', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '课程使用', 'url' => 'courseUse', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '免责声明', 'url' => 'disclaimer', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '退费服务', 'url' => 'refund', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                        ];
                        break;
                    case '1':
                        $fooTwo = [
                            [ 'parent_id' => $id, 'name' => '产品服务', 'url' => 'productService', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '名师简介', 'url' => 'teacherDetail', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '企业文化', 'url' => 'orgCulture', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '公司声明', 'url' => 'companyStatement', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                        ];
                        break;
                    case '2':
                        $fooThree = [
                            [ 'parent_id' => $id, 'name' => '电话咨询', 'url' => 'phoneCall', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '分校查询', 'url' => 'branchSchoolSearch', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '招商加盟', 'url' => 'joinIn', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                        ];
                        break;
                    case '3':
                        $footFore = [
                            [ 'parent_id' => $id, 'name' => '位置一', 'url' => '', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '位置一', 'url' => '', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '位置一', 'url' => '', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                            [ 'parent_id' => $id, 'name' => '位置一', 'url' => '', 'type' => 2, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ],
                        ];
                        break;
                }
            }
            $icp_insert = [ 'parent_id' => 0, 'logo' => $data[ 'logo_url' ], 'type' => 4, 'sort' => 8, 'sort' => 0, 'school_id' => $school_id, 'admin_id' => $user_id, 'create_at' => $date, 'status' => 1 ];

            $icp_res = FootConfig::insert($icp_insert);
            if (!$icp_res) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '页面配置创建未成功!!' ]);
            }
            $payname_res = FootConfig::insert($pany_insert);
            if (!$icp_res) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '页面配置创建未成功!!!' ]);
            }
            $page_head_logo_res = FootConfig::insert($page_head_logo_insert);
            if (!$page_head_logo_res) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '页面配置创建未成功!!!!' ]);
            }
            $footInsert = array_merge($footOne, $fooTwo, $fooThree, $footFore);
            $footRes = FootConfig::insert($footInsert);
            if (!$footRes) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '页面配置创建未成功!!!!!' ]);
            }
            $my_res = FootConfig::insert($my_insert);
            if (!$my_res) {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '页面配置创建未成功!!' ]);
            }
            $payconfig = [
                'zfb_app_public_key' => '',
                'zfb_public_key'     => '',
                'admin_id'           => CurrentAdmin::user()[ 'id' ],
                'school_id'          => $school_id,
                'create_at'          => date('Y-m-d H:i:s')
            ];
            if (PaySet::insertGetId($payconfig) > 0) {
                AdminLog::insertAdminLog([
                    'admin_id'       => CurrentAdmin::user()[ 'id' ],
                    'module_name'    => 'School',
                    'route_url'      => 'admin/school/doInsertSchool',
                    'operate_method' => 'update',
                    'content'        => json_encode($data),
                    'ip'             => $_SERVER[ "REMOTE_ADDR" ],
                    'create_at'      => date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return response()->json([ 'code' => 200, 'msg' => '创建账号成功' ]);
            } else {
                DB::rollBack();
                return response()->json([ 'code' => 203, 'msg' => '创建账号未成功!!!' ]);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json([ 'code' => 500, 'msg' => $ex->getMessage() ]);
        }
    }

    /*
     * @param  description 获取学校信息
     * @param  参数说明       body包含以下参数[
     *  'school_id' =>学校id
     * ]
     * @param author    lys
     * @param ctime     2020-05-06
     */
    public function getSchoolUpdate()
    {
        $data = self::$accept_data;
        $validator = Validator::make(
            $data,
            [ 'school_id' => 'required|integer' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        //
        $field = [
            'id', 'name', 'dns', 'logo_url', 'introduce',
            'account_name', 'account_num', 'open_bank','start_time',
            'end_time','live_price','storage_price','flow_price','ifinto','super_id'
            ];
        $school = School::where('id', $data[ 'school_id' ])->select($field)->first();
        $school = json_decode(json_encode($school),true);
        $school['ifinto'] = $school['ifinto']>0?true:false;//

        //管理员信息
        $field = ['username','realname','mobile'];
        $admin = Adminuser::where('id',$school['super_id'])->select($field)->first();
        $admin = json_decode(json_encode($admin),true);

        $school = array_merge($school,$admin);
        return response()->json([ 'code' => 200, 'msg' => 'Success', 'data' => $school ]);
    }

    /*
     * @param  description 修改分校信息
     * @param  参数说明       body包含以下参数[
     *  'id'=>分校id
        'name' =>分校名称
        'dns' =>分校域名
        'logo_url' =>分校logo
        'introduce' =>分校简介
     * ]
     * @param author    lys
     * @param ctime     2020-05-06
     */
    public function doSchoolUpdate()
    {
        $data = self::$accept_data;
        $schools = [];

        $validator = Validator::make(
            $data,
            [
                'id'            => 'required|integer',
                'name'          => 'required',
                'dns'           => 'required',
                'logo_url'      => 'required',
                'introduce'     => 'required',
                'live_price'    => 'numeric|min:0',
                'storage_price' => 'numeric|min:0',
                'flow_price' => 'numeric|min:0',
                'start_time' => 'required|date',
                'end_time' => 'required|date',
            ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        if (School::where([ 'name' => $data[ 'name' ], 'is_del' => 1 ])->where('id', '!=', $data[ 'id' ])->count() > 0) {
            return response()->json([ 'code' => 422, 'msg' => '学校已存在' ]);
        }
        if (isset($data[ '/admin/school/doSchoolUpdate' ])) {
            unset($data[ '/admin/school/doSchoolUpdate' ]);
        }
        $schools['name'] = isset($data['name'])?$data['name']:'';
        $schools['dns'] = isset($data['dns'])?$data['dns']:'';
        $schools['logo_url'] = isset($data['logo_url'])?$data['logo_url']:'';
        $schools['introduce'] = isset($data['introduce'])?$data['introduce']:'';
        $schools[ 'account_name' ] = !isset($data[ 'account_name' ]) || empty($data[ 'account_name' ]) ? '' : $data[ 'account_name' ];
        $schools[ 'account_num' ] = !isset($data[ 'account_num' ]) || empty($data[ 'account_num' ]) ? '' : $data[ 'account_num' ];
        $schools[ 'open_bank' ] = !isset($data[ 'open_bank' ]) || empty($data[ 'open_bank' ]) ? '' : $data[ 'open_bank' ];
        $schools[ 'update_time' ] = date('Y-m-d H:i:s');



        /////////////////////////直播,空间,流量单价,是否展示分校入口:1=是,2=否
        if (isset($data[ 'live_price' ])) {
            $schools[ 'live_price' ] = $data[ 'live_price' ] ?: 0;
        }
        if (isset($data[ 'storage_price' ])) {
            $schools[ 'storage_price' ] = $data[ 'storage_price' ] ?: 0;
        }
        if (isset($data[ 'flow_price' ])) {
            $schools[ 'flow_price' ] = $data[ 'flow_price' ] ?: 0;
        }
        if(isset($data['ifinto'])){
            $schools['ifinto'] = ($data['ifinto']==true || $data['ifinto']=='true')?1:0;
        }
        $schools['start_time'] = isset($data['start_time'])?$data['start_time']:null;
        $schools['end_time'] = isset($data['end_time'])?$data['end_time']:null;
        //////////////////laoxian 2020/10/23 新增

        if (School::where('id', $data[ 'id' ])->update($schools)) {
            AdminLog::insertAdminLog([
                'admin_id'       => CurrentAdmin::user()[ 'id' ]?:0,
                'module_name'    => 'School',
                'route_url'      => 'admin/school/doSchoolUpdate',
                'operate_method' => 'update',
                'content'        => json_encode($data),
                'ip'             => $_SERVER[ "REMOTE_ADDR" ],
                'create_at'      => date('Y-m-d H:i:s')
            ]);
            //执行账号信息更改
            $super_id = School::where('id', $data[ 'id' ])->value('super_id');
            if($super_id){
                $update = [];
                if(isset($data['password']) && isset($data['pwd']) && $data['password'] && $data['pwd'] ){
                    if(strlen($data['password']) <8){
                        return response()->json(['code'=>207,'msg'=>'密码长度不能小于8位']);
                    }
                    if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['password'])) {
                        return response()->json(['code'=>207,'msg'=>'密码格式不正确，请重新输入']);
                    }
                    if(!empty($data['password'])|| !empty($data['pwd']) ){
                        if($data['password'] != $data['pwd'] ){
                            return response()->json(['code'=>206,'msg'=>'两个密码不一致']);
                        }else{
                            $update['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                        }
                    }
                }

                if(isset($data['realname']) && !empty($data['realname'])){
                    $update['realname'] =  $data['realname'];
                }
                if(isset($data['mobile']) && !empty($data['mobile'])){
                    $update['mobile'] =  $data['mobile'];
                }
                if($update){
                    $result = Adminuser::where('id',$super_id)->update($update);
                }
            }

            return response()->json([ 'code' => 200, 'msg' => '更新成功' ]);
        } else {
            return response()->json([ 'code' => 200, 'msg' => '更新成功' ]);
        }
    }

    /*
     * @param  description 修改分校信息---权限管理
     * @param  参数说明       body包含以下参数[
     *      'id'=>分校id
     * ]
     * @param author    lys
     * @param ctime     2020-05-06
     */
    public function getSchoolAdminById()
    {
        $data = self::$accept_data;
        $validator = Validator::make(
            $data,
            [ 'id' => 'required|integer' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        if ($data['id'] == 1) {
            return response()->json(['code'=>201,'msg'=>'错误的网校标识']);
        }
        $schoolData = School::select(['name'])->find($data['id']);
        if(!$schoolData){
             return response()->json(['code'=>422,'msg'=>'无学校信息']);
        }
        $roleId = Role::query()->where([ 'school_id' => $data[ 'id' ], 'is_super' => 1 ])->select('id')->value('id'); //查询学校是否有超管人员角色
        if (empty($roleId)) {
            //无
            $adminUser = Adminuser::where([ 'school_id' => $data[ 'id' ], 'is_del' => 1 ])->select('id', 'username', 'realname', 'mobile')->first();
        } else {
            //有
            $adminUser = Adminuser::where([ 'school_id' => $data[ 'id' ], 'role_id' => $roleId, 'is_del' => 1 ])->select('id', 'username', 'realname', 'mobile')->first();
        }

        $adminUser[ 'role_id' ] = empty($roleId) ? 0 : $roleId;
        // $adminUser['auth_id'] = $roleAuthId['map_auth_id'] ? $roleAuthId['map_auth_id']:null;
        $groupIdList = [];
        if (!empty($roleId)) {
            $groupList = RoleService::getRoleRuleGroupList($roleId);
            $groupIdList = array_column($groupList, 'group_id');
        }

        $adminUser[ 'map_auth_id' ] = empty($groupIdList) ? null : implode(',', $groupIdList);  //
        $adminUser[ 'school_name' ] = !empty($schoolData[ 'name' ]) ? $schoolData[ 'name' ] : '';

        $authRules = RoleService::getRuleGroupListBySchoolId($data[ 'id' ], 1);
        $authRules = getAuthArr($authRules);

        $arr = [
            'admin'      => $adminUser,
            'auth_rules' => $authRules,
        ];
        return response()->json([ 'code' => 200, 'msg' => 'success', 'data' => $arr ]);
    }

    /*
     * @param  description 修改分校信息---权限管理 给分校超管赋权限
     * @param  参数说明       body包含以下参数[
     *      'id'=>分校id
            'role_id'=>角色id,
            'auth_id'=>权限组id
            'user_id'=>账号id
     * ]
     * @param author    lys
     * @param ctime     2020-05-15
     */
    public function doSchoolAdminById()
    {

        $data = self::$accept_data;
        $validator = Validator::make(
            $data,
            [
                'id'      => 'required|integer',
                'role_id' => 'required|integer',
                'user_id' => 'required|integer',
            ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        if($data['id'] == 1){
            return response()->json(['code'=>201,'msg'=>'错误的网校标识']);
        }

        if(!isset($data['auth_id'])){
             return response()->json(['code'=>201,'msg'=>'权限组标识缺少']);
        }
        if ($data[ 'role_id' ] > 0) {
            if (empty($data[ 'auth_id' ])) {
                return response()->json([ 'code' => 201, 'msg' => '权限组标识不能为空' ]);
            }
        }

        //最终权限组 信息
        $curGroupIdList = [];
        if (!empty($data[ 'auth_id' ])) {
            //获取有效的权限组
            $allGroupIdList = RuleGroup::query()
                ->where(['school_type' => 0, 'is_del'=>0,'is_forbid'=>1])
                ->orderBy('sort', 'asc')
                ->orderBy('id', 'asc')
                ->pluck('id')
                ->toArray();
            $groupIdList = explode(',', $data['auth_id']);
            $groupIdList = array_unique($groupIdList);
            $groupIdList = array_diff($groupIdList, [ '0' ]);

            foreach ($groupIdList as $v) {
                if (in_array($v, $groupIdList)) {
                    $curGroupIdList[] = $v;
                }
            }
        }

        //获取当前学校的超级管理员角色信息
        $roleInfo = Role::query()
            ->where([ 'school_id' => $data[ 'id' ], 'is_super' => 1, 'is_del' => 0 ])
            ->first(); //判断该网校有无超级管理员

        //存在时的处理数据
        if (!empty($roleInfo)) {

            $roleInfo = $roleInfo->toArray();

            //存在超管 则 就是本数据
            if ($roleInfo[ 'id' ] != $data[ 'role_id' ]) { //判断是否为超管
                return response()->json([ 'code' => 404, 'msg' => '非法请求' ]);
            }

            //获取 需要删除和 新增的
            $existsGroupList = RoleService::getRoleRuleGroupList($roleInfo[ 'id' ]);
            $existsGroupIdList = array_column($existsGroupList, 'group_id');

            $needInsertIdList = array_diff($curGroupIdList, $existsGroupIdList);
            $needDelIdList = array_diff($existsGroupIdList, $curGroupIdList);
            $needInsertData = [];
            foreach ($needInsertIdList as $val) {
                $needInsertData[] = [
                    'role_id'  => $roleInfo[ 'id' ],
                    'group_id' => $val
                ];
            }

            //如果存在删除的数据 则 此校的所有角色都需删除
            if (!empty($needDelIdList)) {
                $roleList = Role::query()->where([ 'is_del' => 0 ])->where('school_id', $data[ 'id' ])->select('id')->get()->toArray();
                $roleIdList = array_column($roleList, 'id');
            }

        } else {
            $roleInfo = [];
        }


        if (isset($data[ 'admin/school/doSchoolAdminById' ])) {
            unset($data[ 'admin/school/doSchoolAdminById' ]);
        }
        DB::beginTransaction();
        try {
            //是否存在超级管理员
            if (empty($roleInfo)) {
                //无
                $insert = [
                    'role_name'   => '超级管理员',
                    'auth_desc'   => '拥有所有权限',
                    'school_id'   => $data[ 'id' ],
                    'is_super'    => 1,
                    'admin_id'    => CurrentAdmin::user()[ 'id' ],
                    'create_time' => date('Y-m-d H:i:s')
                ];
                $roleId = Role::query()->insertGetId($insert);

                if (!empty($curGroupIdList)) {
                    $insertData = [];
                    foreach ($curGroupIdList as $val) {
                        $insertData[] = [
                            'role_id'  => $roleId,
                            'group_id' => $val
                        ];
                    }
                    RoleRuleGroup::query()->insert($insertData);
                }

                Adminuser::query()->where('id', $data[ 'user_id' ])->update([ 'role_id' => $roleId ]);

            } else {
                //有

                if (!empty($needInsertData)) {
                    RoleRuleGroup::query()->insert($needInsertData);
                }

                if (!empty($needDelIdList)) {
                    if (!empty($roleIdList)) {
                        RoleRuleGroup::query()
                            ->whereIn('role_id', $roleIdList)
                            ->whereIn('group_id', $needDelIdList)
                            ->update(['is_del' => 1]);
                    }
                }

            }
            AdminLog::insertAdminLog([
                'admin_id'       => CurrentAdmin::user()[ 'id' ],
                'module_name'    => 'School',
                'route_url'      => 'admin/school/doSchoolAdminById',
                'operate_method' => 'update',
                'content'        => json_encode($data),
                'ip'             => $_SERVER[ "REMOTE_ADDR" ],
                'create_at'      => date('Y-m-d H:i:s')
            ]);

            DB::commit();
            return response()->json([ 'code' => 200, 'msg' => '赋权成功' ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([ 'code' => 500, 'msg' => $e->getMessage() ]);
        }
    }

    /*
     * @param  description 修改分校信息---权限管理-账号编辑（获取）
     * @param  参数说明       body包含以下参数[
     *      'user_id'=>用户id
     * ]
     * @param author    lys
     * @param ctime     2020-05-07
     */
    public function getAdminById()
    {
        $data = self::$accept_data;
        $validator = Validator::make(
            $data,
            [ 'user_id' => 'required|integer' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        $admin = Adminuser::where('id', $data[ 'user_id' ])->select('id', 'realname', 'mobile')->get();
        return response()->json([ 'code' => 200, 'msg' => 'success', 'data' => $admin ]);
    }

    /*
     * @param  description 修改分校信息---权限管理-账号编辑
     * @param  参数说明       body包含以下参数[
     *      'id'=>用户id
     * ]
     * @param author    lys
     * @param ctime     2020-05-07
     */
    public function doAdminUpdate()
    {

        $data = self::$accept_data;
        $validator = Validator::make(
            $data,
            [
                'user_id' => 'required|integer',
                'mobile'  => 'regex:/^1[3456789][0-9]{9}$/',
            ], School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        $result = School::doAdminUpdate($data);
        AdminLog::insertAdminLog([
            'admin_id'       => CurrentAdmin::user()[ 'id' ],
            'module_name'    => 'School',
            'route_url'      => 'admin/school/doAdminUpdate',
            'operate_method' => 'update',
            'content'        => json_encode($data),
            'ip'             => $_SERVER[ "REMOTE_ADDR" ],
            'create_at'      => date('Y-m-d H:i:s')
        ]);
        return response()->json([ 'code' => $result[ 'code' ], 'msg' => $result[ 'msg' ] ]);
    }

    /*
     * @param  description 获取分校讲师列表
     * @param  参数说明       body包含以下参数[
     *      'school_id'=>学校id
     * ]
     * @param author    lys
     * @param ctime     2020-05-07
     */
    public function getSchoolTeacherList()
    {
        $validator = Validator::make(self::$accept_data,
            [ 'school_id' => 'required|integer' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }

        $result = School::getSchoolTeacherList(self::$accept_data);
        return response()->json($result);
    }
    /*
     * @param  description 获取分校课程列表
     * @param  参数说明       body包含以下参数[
     *      'school_id'=>学校id
     * ]
     * @param author    lys
     * @param ctime     2020-05-11
     *///7.4调整
    public function getLessonLists()
    {

        $validator = Validator::make(self::$accept_data,
            [ 'school_id' => 'required|integer' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        $result = School::getSchoolLessonList(self::$accept_data);
        return response()->json($result);
    }

    /*
     * @param  description 获取网校公开课列表
     * @param  参数说明       body包含以下参数[
     *      'school_id'=>学校id
     * ]
     * @param author    lys
     * @param ctime     2020-7-4
     */
    public function getOpenLessonList()
    {

        $validator = Validator::make(self::$accept_data,
            [ 'school_id' => 'required|integer' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        $result = School::getOpenLessonList(self::$accept_data);
        return response()->json($result);
    }

    public function getSubjectList()
    {
        $validator = Validator::make(self::$accept_data,
            [
                'school_id' => 'required|integer',
                'is_public' => 'required|integer'
            ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        $result = School::getSubjectList(self::$accept_data);
        return response()->json($result);
    }

    /**
     * 获取总控管理中控的token数据
     * @param SchoolService $schoolService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getManageSchoolToken(SchoolService $schoolService)
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [ 'school_id' => 'required|integer' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        return $schoolService->getManageSchoolToken($data[ 'school_id' ]);
    }

    /**
     * 获取配置数据
     * @param SchoolService $schoolService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfig(SchoolService $schoolService)
    {
        $userInfo = CurrentAdmin::user();
        return $schoolService->getConfig($userInfo['school_id']);

    }

    /**
     * 设置配置数据
     * @param SchoolService $schoolService
     * @return \Illuminate\Http\JsonResponse
     */
    public function setConfig(SchoolService $schoolService)
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            ['cur_type' => 'required',
            'cur_type_selected' =>  'required',
            'cur_content' => 'required'],
            School::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $userInfo = CurrentAdmin::user();
        return $schoolService->setConfig($userInfo['school_id'], $data['cur_type'], $data['cur_type_selected'], $data['cur_content']);

    }

    /**
     * 获取SEO配置
     * @param SchoolService $schoolService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSEOConfig(SchoolService $schoolService)
    {
        $userInfo = CurrentAdmin::user();
        return $schoolService->getSEOConfig($userInfo['school_id']);
    }

    /**
     * 设置页面seo
     * @param SchoolService $schoolService
     * @return \Illuminate\Http\JsonResponse
     */
    public function setPageSEOConfig(SchoolService $schoolService)
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'page_type' => 'required',
                'title' => 'required',
                'keywords' => 'required',
                'description' => 'required'
            ],
            School::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $userInfo = CurrentAdmin::user();

        return $schoolService->setPageSEOConfig($userInfo['school_id'], $data['page_type'], $data['title'], $data['keywords'], $data['description']);
    }

    /**
     * 设置SEO开启状态
     * @param SchoolService $schoolService
     * @return \Illuminate\Http\JsonResponse
     */
    public function setSEOOpen(SchoolService $schoolService)
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            ['cur_type' => 'required',
            'is_forbid' => "required"],
            School::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $userInfo = CurrentAdmin::user();
        return $schoolService->setSEOOpen($userInfo['school_id'], $data['cur_type'], empty($data['is_forbid']) ? 0 : 1);

    }



    /**
     *  获取一个网校的 流量记录
     * http://xx.com/api/school/trafficdetail
     * @api https://www.showdoc.com.cn/1081460683864458?page_id=5678468164600439
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSchoolTrafficdetail()
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'schoolid'  => 'required|integer',
                'start_date' => 'date',
                'end_date'   => 'date',
            ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }
        if(!isset($data['start_date']) or !isset($data['end_date']) ){
            $data['start_date'] = date("Y-m-d", strtotime("-15 Day"));
            $data['end_date'] = date("Y-m-d", strtotime("now"));
        }

        $school_traffic_log = new SchoolTrafficLog();
        $ret_list = $school_traffic_log->getTrafficLog($data[ 'school_id' ], $data[ 'start_date' ], $data[ 'end_date' ]);
        return response()->json([ 'code' => 0 , "data" =>$ret_list ]);

    }

    /**
     *  获取到并发数的日志的日志
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSchoolConnections()
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'schoolid'  => 'required|integer',
                'start_date' => 'date',
                'end_date'   => 'date',
            ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }

        $school_conn_log = new SchoolConnectionsLog();
        $ret_list = $school_conn_log->getConnectionsLog($data[ 'school_id' ], $data[ 'start_date' ], $data[ 'end_date' ]);
        return response()->json([ 'code' => 0 ,"data" => $ret_list] );

    }

    /**
     *  流量空间的 两个饼状图
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSchoolSpaceDeatil()
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [ 'schoolid' => 'required' ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }

        //$school_id = AdminLog::getAdminInfo()->admin_user->school_id;
        $school_resource = new SchoolResource();

        if (empty($school_id)) {
            return response()->json([ 'code' => 1, 'msg' => '无法获取网校id' ]);
        }
        // 获取 学校的id 获取到网校的 空间和流量使用详情
        $resource_info = $school_resource->getSpaceTrafficDetail($data[ 'school_id' ]);

        return response()->json(([ 'code' => 0 ,"data" => $resource_info] ));

    }

    public function setdistribution()
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'schoolid' => 'required|integer',
                'month'     => 'required|date',
                'num'       => 'required|integer',
            ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }

        //$school_id = AdminLog::getAdminInfo()->admin_user->school_id;
        $school_resource = new SchoolResource();
        $admin_id = AdminLog::getAdminInfo()->admin_user->id;
        // 设定 网校 某一个月份的 可用并发数
        $ret = $school_resource->setConnectionNumByDate($data[ 'school_id' ], $data[ 'num' ], $data[ 'month' ], $admin_id);
        if ($ret) {
            return response()->json([ 'code' => 0, 'msg' => '设定成功' ]);
        } else {
            return response()->json([ 'code' => 1, 'msg' => "设定失败" ]);
        }

    }

    public function getdistribution()
    {
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'schoolid' => 'required|integer',
                'month'     => 'required|date'
            ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }

        //$school_id = AdminLog::getAdminInfo()->admin_user->school_id;
        $school_card = new SchoolConnectionsCard();

        // 获取到网校某一个月份 的可用分配数
        $ret = $school_card->getNumByDate($data[ 'school_id' ], $data[ 'month' ]);
        if ($ret) {
            return response()->json([ 'code' => 0, 'msg' => '获取成功', "num" => $ret ]);
        } else {
            return response()->json([ 'code' => 1, 'msg' => "获取失败" ]);
        }

    }

    public function connectionDistribution(){
        $data = self::$accept_data;
        $validator = Validator::make($data,
            [
                'schoolid' => 'required|integer'
            ],
            School::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(), 1));
        }

        //$school_id = AdminLog::getAdminInfo()->admin_user->school_id;
        $school_distribution = new SchoolConnectionsDistribution();
        $ret = $school_distribution ->getDistribution($data[ 'school_id' ]);
        if ($ret) {
            return response()->json([ 'code' => 0, 'msg' => '获取成功', "num" => $ret ]);
        } else {
            return response()->json(([ 'code' => 1, 'msg' => "获取失败" ]));
        }
    }

}
