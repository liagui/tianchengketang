<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use App\Models\ServiceRecord;
use Validator;

class PurServiceController extends Controller {

    //需要schoolid的方法
    protected $need_schoolid = [
        'getPrice',//获取单价
        'purLive',//购买直播并发
        'purStorage',//购买空间
        'purFlow',//购买流量
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

    public function getPrice(Request $request)
    {
        //学校信息
        $schoolid = $request->input('schoolid');
        $schools = School::where('id',$schoolid)->select('live_price','storage_price','flow_price')->first();
        $arr = [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'live_price'=>$schools->live_price>0?:ENV('LIVE_PRICE'),
                'storage_price'=>$schools->storage_price>0?:ENV('STORAGE_PRICE'),
                'flow_price'=>$schools->flow_price>0?:ENV('FLOW_PRICE'),
            ],
        ];
        return response()->json($arr);

    }

    /**
     *购买服务-直播并发
     */
    public function purLive(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'end_time' => 'required|date',
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }
        //执行
        $post['type'] = 1;
        $return = ServiceRecord::purService($post);
        return response()->json($return);

    }

    /**
     *购买服务-空间
     */
    public function purStorage(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'end_time' => 'required|date',
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }
        //执行
        $post['type'] = 2;
        $return = ServiceRecord::purService($post);
        return response()->json($return);
    }

    /**
     *购买服务-直播流量
     */
    public function purFlow(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'end_time' => 'required|date',
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }
        //执行
        $post['type'] = 3;
        $return = ServiceRecord::purService($post);
        return response()->json($return);
    }
}
