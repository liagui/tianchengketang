<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use App\Models\ServiceRecord;
use Validator;

/**
 * 购买服务
 * @todo 服务统一单价设置
 * @author laoxian
 */
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

    /**
     * 查询网校购买服务单价
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPrice(Request $request)
    {
        //学校信息
        $schoolid = $request->input('schoolid');
        $schools = School::where('id',$schoolid)->select('live_price','storage_price','flow_price')->first();
        //网校已设置时, 使用本网校设置的金额, 否则使用统一价格
        $arr = [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'live_price'=>$schools->live_price>0?:(ENV('LIVE_PRICE')?:0),
                'storage_price'=>$schools->storage_price>0?:(ENV('STORAGE_PRICE')?:0),
                'flow_price'=>$schools->flow_price>0?:(ENV('FLOW_PRICE')?:0),
            ],
        ];
        return response()->json($arr);

    }

    /**
     *购买服务-直播并发
     * @param num int 数量
     * @param start_time date 时间
     * @param end_time date 时间
     * @param schoolid int 学校
     * @param money int 金额
     * @param remark string 备注
     * @author 赵老仙
     */
    public function purLive(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'start_time' => 'required|date',
            'end_time' => 'required|date',
            'money' => 'required|min:1|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }
        //执行
        $post['type'] = 1;//代表直播并发
        $return = ServiceRecord::purService($post);
        return response()->json($return);

    }

    /**
     * 购买服务-空间
     * @param num int 数量
     * @param month int 购买时长
     * @param schoolid int 学校
     * @param money int 金额
     * @param remark string 备注
     * @author 赵老仙
     */
    public function purStorage(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'month' => 'required|min:1|integer',
            'money' => 'required|min:1|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }

        //根据month生成start_time end_time
        $post['type'] = 2;//代表空间
        $post = ServiceRecord::storageRecord($post);

        //执行
        $return = ServiceRecord::purService($post);
        return response()->json($return);
    }

    /**
     * 购买服务-流量
     * @param num int 数量
     * @param schoolid int 学校
     * @param money int 金额
     * @param remark string 备注
     * @author 赵老仙
     */
    public function purFlow(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'num' => 'required|min:1|integer',
            'money' => 'required|min:1|numeric'
        ],ServiceRecord::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }
        //执行
        $post['type'] = 3;//代表流量
        $post['end_time'] = date('Y-m-d H:i:s');//
        //end_time 不能为空, 原型图更改后无此字段, 暂定义一个默认字段
        $return = ServiceRecord::purService($post);
        return response()->json($return);
    }
}