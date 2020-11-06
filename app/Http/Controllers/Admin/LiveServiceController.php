<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use App\Models\liveService;
use Validator;

/**
 * 第三方直播商家管理
 * @author laoxian
 */
class liveServiceController extends Controller {

    //需要schoolid的方法
    protected $need_schoolid = [
        'updateLivetype'
    ];

    /**
     * laoxian
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
            //查询学校
            $schools = School::find($schoolid);
            if(empty($schools)){
                header('Content-type: application/json');
                echo json_encode(['code' => '202', 'msg' => '找不到当前学校']);
                die();
            }
        }

        //删除
        //$data =  $request->except(['token']);
        //赋值 与 替换
        //$data =  $request->offsetSet('字段1',变量);
        //$request->merge(['字段1'=>1,'字段2'=>2]);
    }

    /**
     * 添加
     * @param [
     *      name string 直播商家名称
     *      isshow int 1=可用,2=不可用
     *      short string 描述
     * ]
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public function add(Request $request)
    {
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'name'   => 'required',
            'isshow' => 'integer',
        ],liveService::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }die();
        //执行
        $return = liveService::add($post);
        return response()->json($return);
    }

    /**
     * 撤销
     * @author laoxian
     * @time 2020/10/19
     */
    public function revert(Request $request)
    {
        return response()->json(['code'=>201,'msg'=>'fail']);
    }

    /**
     * 查看直播服务商列表
     * @param page int 页码
     * @param pagesize int 页大小
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public function index(Request $request)
    {
        $result = (new liveService)::getlist($request->all());
        return response()->json($result);
    }

    /**
     * 获取单条
     * @param id int
     * @author laoxian
     * @time 2020/10/19
     */
    public function detail(Request $request){
        $return = liveService::detail($request->all());
        return response()->json($return);
    }

    /**
     * 执行修改
     * @author laoxian
     * @time 2020/10/19
     */
    public function edit(Request $request){
        //数据
        $post = $request->all();
        $validator = Validator::make($post, [
            'name'   => 'required',
            'isshow' => 'integer',
        ],liveService::message());
        if ($validator->fails()) {
            header('Content-type: application/json');
            echo $validator->errors()->first();
            die();
        }

        $return = liveService::doedit($request->all());
        return response()->json($return);
    }

    /**
     * 删除
     * @author laoxian
     * @time 2020/10/19
     */
    public function delete(Request $request){
        $return = liveService::dodelete($request->all());
        return response()->json($return);
    }

    /**
     * 批量更新
     * @author laoxian
     * @time 2020/10/19
     */
    public function multi(Request $request){
        $return = liveService::domulti($request->all());
        return response()->json($return);
    }

    /**
     * 更新网校直播商
     * @author laoxian
     * @time 2020/10/19
     */
    public function updateLivetype(Request $request){
        $return = liveService::updateLivetype($request->all());
        return response()->json($return);
    }

}
