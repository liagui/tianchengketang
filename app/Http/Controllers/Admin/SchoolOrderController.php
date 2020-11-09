<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolOrder;
use Illuminate\Http\Request;
use Validator;
use App\Tools\MTCloud;
use Log;

/**
 * 线下订单 [手动打款, 库存充值, 直播并发, 空间, 流量服务购买等]
 * @author laoxian
 */
class SchoolOrderController extends Controller {

    //需要schoolid的方法
    protected $need_schoolid = [
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
            }
            $schools = School::find($schoolid);
            if(empty($schools)){
                header('Content-type: application/json');
                echo json_encode(['code' => '202', 'msg' => '找不到当前学校']);
                die();
            }
        }
    }

    /**
     * 获取订单搜索条件
     */
    public function searchKey()
    {
        $arr = [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'status'=>[
                    ['value'=>'1','name'=>'待审核'],
                    ['value'=>'2','name'=>'审核通过'],
                    ['value'=>'3','name'=>'驳回'],
                ],
                'type'=>[
                    ['value'=>'1','name'=>'预充金额'],
                    ['value'=>'2','name'=>'购买服务'],
                ],
            ],
        ];
        return response()->json($arr);
    }

    /**
     * 查看线下订单
     * @author laoxian
     * @time 2020/10/22
     */
    public function index(Request $request){
        $post = $request->all();
        $validator = Validator::make($post, [
            'status'   => 'integer',
            'type' => 'integer',
        ],SchoolOrder::Message());
        if ($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),true));
        }

        $return = SchoolOrder::getlist($post);
        return response()->json($return);
    }

    /**
     * 查看订单
     * @author laoxian
     * @time 2020/10/22
     */
    public function detail(Request $request)
    {
        $id = $request->input('id');
        if(!$id || !is_numeric($id)){
            return response()->json(['code'=>201,'msg'=>'参数不合法']);
        }
        $return = SchoolOrder::detail($id);

        return response()->json($return);
    }

    /**
     * 订单审核
     * @author laoxian
     * @time 2020/10/22
     */
    public function operate(Request $request)
    {
        $id = $request->input('id');
        if(!$id || !is_numeric($id)){
            return response()->json(['code'=>201,'msg'=>'参数不合法']);
        }
        $status = $request->input('status');
        if(!$status || !is_numeric($status)){
            return response()->json(['code'=>201,'msg'=>'审核状态参数不合法']);
        }
        $return = SchoolOrder::doedit($request->all());

        return response()->json($return);
    }

}
