<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use App\Models\SchoolAccount;
use Validator;

/**
 * 手动打款
 * @author laoxian
 */
class SchoolAccountController extends Controller {

    //需要schoolid的方法
    protected $need_schoolid = [
        'addAccount',//账户新增记录
        'getAccountList',//获取记录
        'detail',//记录详情
    ];

    /**
     * 初始化
     */
    public function __construct(Request $request)
    {
        list($path,$action) = explode('@',$request->route()[1]['uses']);
        //schoolid检查
        if(in_array($action,$this->need_schoolid)) {
            $schoolid = $request->input('schoolid');
            if (!$schoolid || !is_numeric($schoolid)) {
                //return response()->json(['code'=>'201','msg'=>'网校标识错误']);
                header('Content-type: application/json');
                echo json_encode(['code' => '201', 'msg' => '网校标识错误']);
                die();
            }else{
                $schools = School::find($schoolid);
                if(empty($schools)){
                    header('Content-type: application/json');
                    echo json_encode(['code' => '202', 'msg' => '找不到当前学校']);
                    die();
                }
            }
        }

        //删除
        //$data =  $request->except(['token']);
        //赋值 与 替换
        //$data =  $request->offsetSet('字段1',变量);
        //$request->merge(['字段1'=>1,'字段2'=>2]);
    }

    /**
     * 网校充值线下
     * @param [
     *      schoolid int 网校
     *      type int 类型
     *      money int 金额
     *      give_money int 赠送金额
     *      time datetime 时间
     *      remark string 备注
     * ]
     * @author laoxian
     * @time 2020/10/16
     * @return array
     */
    public function addAccount(Request $request)
    {
        //数据
        $post = $request->all();
        //参数整理
        $arr = ['money','give_money','schoolid','remark'];
        foreach($post as $k=>$v){
            if(!in_array($k,$arr)){
                unset($post[$k]);
            }
        }
        $validator = Validator::make($post, [
            //'schoolid'   => 'required|integer|min:1',
            //'type' => 'required|integer|min:1',
            'money' => 'numeric',
            'give_money' => 'numeric',
        ],SchoolAccount::message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }
        //执行
        $return = SchoolAccount::insertAccount($post);
        return response()->json($return);
    }

    /**
     * 撤销
     */
    public function revert(Request $request)
    {
        return response()->json(['code'=>201,'msg'=>'fail']);
    }

    /**
     * 账户变动列表
     * @param schoolid int 网校
     * @param page int 页码
     * @param pagesize int 页大小
     * @author laoxian
     * @return array
     */
    public function getAccountList(Request $request)
    {
        $result = (new SchoolAccount)::getlist($request->all());
        return response()->json($result);
    }

    /**
     * 获取单条
     */
    public function detail(Request $request){
        $return = SchoolAccount::detail($request->all());
        return response()->json($return);
    }

}
