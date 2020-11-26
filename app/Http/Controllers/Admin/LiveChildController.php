<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Live;
use App\Models\Subject;
use Illuminate\Http\Request;
use  App\Tools\CurrentAdmin;
use Validator;
use App\Tools\MTCloud;
use App\Models\LiveChild;
use Log;
use App\Listeners\LiveListener;

class LiveChildController extends Controller {

    /**
     * @param  直播课次列表
     * @param  pagesize   page
     * @param  author  zzk
     * @param  ctime   2020/6/29
     * @return  array
     */
    public function liveList(Request $request){
        try{
            $list = LiveChild::getLiveClassChildList(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /**
     * 添加课次.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {


        try{
            $list = LiveChild::AddLiveClassChild(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }


        // $validator = Validator::make($request->all(), [
        //     'lesson_child_id' => 'required',
        //     'live_id' => 'required',
        //     'lesson_name' => 'required',
        //     'teacher_id' => 'required',
        //     'start_time' => 'required',
        //     'end_time' => 'required',
        //     'nickname' => 'required',
        // ]);
        // if ($validator->fails()) {
        //     return $this->response($validator->errors()->first(), 202);
        // }
        // $user = CurrentAdmin::user();
        // try{
        //     $MTCloud = new MTCloud();
        //     $res = $MTCloud->courseAdd(
        //                 $request->input('lesson_name'),
        //                 $request->input('teacher_id'),
        //                 $request->input('start_time'),
        //                 $request->input('end_time'),
        //                 $request->input('nickname'),
        //                 '',
        //                 [
        //                     'barrage' => $request->input('barrage') ?: 0,
        //                     'modetype' => $request->input('modetype') ?: 3,
        //                 ]
        //             );
        //     Log::error('欢拓创建直播间:'.json_encode($res));
        //     if(!array_key_exists('code', $res) && !$res["code"] == 0){
        //         return $this->response('直播间创建失败', 500);
        //     }
        //     $livechild =  LiveChild::create([
        //                     'admin_id'    => $user->id,
        //                     'live_id'     => $request->input('live_id'),
        //                     'course_name' => $request->input('lesson_name'),
        //                     'account'    => $request->input('teacher_id'),
        //                     'start_time' => $request->input('start_time'),
        //                     'end_time'   => $request->input('end_time'),
        //                     'nickname'   => $request->input('nickname'),
        //                     'partner_id' => $res['data']['partner_id'],
        //                     'bid'        => $res['data']['bid'],
        //                     'course_id'  => $res['data']['course_id'],
        //                     'zhubo_key'  => $res['data']['zhubo_key'],
        //                     'admin_key'  => $res['data']['admin_key'],
        //                     'user_key'   => $res['data']['user_key'],
        //                     'add_time'   => $res['data']['add_time'],
        //                     'status'     => 1,
        //                 ]);

        //     LiveClassChild::create([
        //         'live_child_id' => $livechild->id,
        //         'lesson_child_id' => $request->input('lesson_child_id'),
        //         ]);
        //     LiveTeacher::create([
        //         'admin_id' => $user->id,
        //         'live_id' => $request->input('live_id'),
        //         'live_child_id' => $livechild->id,
        //         'teacher_id' => $request->input('teacher_id'),
        //         ]);
        // }catch(Exception $e){
        //     Log::error('创建失败:'.$e->getMessage());
        //     return $this->response($e->getMessage(), 500);
        // }
        // return $this->response($livechild);
    }
    /**
     * 更新课次
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function UpdateChild(Request $request) {
        //获取提交的参数
        try{
            $data = LiveChild::updateLiveClassChild(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /**
     * 启用/禁用课次
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request) {
        try{
            $one = LiveChild::updateLiveClassChildStatus(self::$accept_data);
            return response()->json($one);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /**
     * 删除课次
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request) {
        try{
            $one = LiveChild::updateLiveClassChildDelete(self::$accept_data);
            return response()->json($one);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //课次详情
    public function showOne(Request $request) {
        try{
            $one = LiveChild::getLiveClassChildListOne(self::$accept_data);
            return response()->json($one);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //关联讲师
    public function ClassChildRelevance(Request $request){
        try{
            $list = LiveChild::LiveClassChildTeacher(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //发布到欢拓创建直播
    public function creationLive(){
        try{
            $list = LiveChild::creationLiveClassChild(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            Log::error(LogDBExceiption($ex));
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /**
     * 添加班号课次课程资料
     */
    public function uploadLiveClassChild(){
        try{
            $list = LiveChild::uploadLiveClassChild(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //班号课次课程资料列表
    public function getLiveClassMaterial(){
        try{
            $list = LiveChild::getLiveClassMaterial(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //班号课次课程资料删除
    public function deleteLiveClassChildMaterial(){
        try{
            $list = LiveChild::deleteLiveClassMaterial(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    /**
     * 启动直播
     * @param
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function startLive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $live = LiveChild::findOrFail($request->input('id'));
        $MTCloud = new MTCloud();
        $res = $MTCloud->courseLaunch($live->course_id);
        Log::error('直播器启动:'.json_encode($res));
        if(!array_key_exists('code', $res) && !$res["code"] == 0){
            return $this->response('直播器启动失败', 500);
        }
        return $this->response($res['data']);
    }

    //更新直播状态
    public function listenLive(Request $request)
    {
        $handler = new LiveListener();
        $handlerMethod = 'handler';
        $MTCloud = new MTCloud();
        $MTCloud->registerCallbackHandler(array($handler,$handlerMethod));
        $MTCloud->callbackService();
    }

}
