<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\Subject;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Http\Request;
use  App\Tools\CurrentAdmin;
use Validator;
use App\Tools\MTCloud;
use Log;

class VideoController extends Controller {

    /*
     * @param  全部录播列表
     * @param  pagesize   page
     * @param  author  zzk
     * @param  ctime   2020/6/23
     * return  array
     */
    public function index(Request $request){
        try{
            $list = Video::getVideoList(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  录播详情
     * @param  录播id
     * @param  author  zzk
     * @param  ctime   2020/6/24
     * return  array
     */
    public function show(Request $request) {
        try{
            $one = Video::getVideoOne(self::$accept_data);
            return response()->json($one);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /**
     * 添加录播资源.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //获取提交的参数
        try{
            $data = Video::AddVideo(self::$accept_data);
            return response()->json($data);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /**
     * @param 修改录播资源
     *
     * @param  Request  $request
     * @param  int  $id
     * @return json
     */
    public function update(Request $request) {
        try{
            $list = Video::updateVideo(self::$accept_data);
            return response()->json($list);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /**
     * 启用/禁用
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request) {
        try{
            $one = Video::updateVideoStatus(self::$accept_data);
            return response()->json($one);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /**
     * 删除
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request) {
        try{
            $one = Video::updateVideoDelete(self::$accept_data);
            return response()->json($one);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }



    //获取欢拓录播资源上传地址
    public function uploadUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'video_md5'   =>  'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $MTCloud = new MTCloud();
        $options = [
            'course' => [
                'start_time' => date("Y-m-d H:i",strtotime("-1 day")),
            ] ,
        ];
        $res = $MTCloud->videoGetUploadUrl(1, 2, $request->input('title'), $request->input('video_md5'), $options);

        if(!array_key_exists('code', $res) || $res['code'] != 0){
            Log::error('上传失败code:'.$res['code'].'msg:'.json_encode($res));

            if($res['code'] == 1281){
                return $this->response($res['data']);
            }
            return $this->response('上传失败', 500);
        }
        return $this->response($res['data']);
    }

    //获取欢拓录播资源上传地址
    public function ccuploadUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'filename' => 'required',
            'filesize' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }

//        $MTCloud = new MTCloud();
//        $options = [
//            'course' => [
//                'start_time' => date("Y-m-d H:i",strtotime("-1 day")),
//            ] ,
//        ];
//        $res = $MTCloud->videoGetUploadUrl(1, 2, $request->input('title'), $request->input('video_md5'), $options);
        $cccloud = new CCCloud();
        $cccloud ->cc_video_create_upload_info($request->input('title'),"","",
            $request->input('filename'),$request->input('filesize'));

        if(!array_key_exists('code', $res) || $res['code'] != 0){
            Log::error('上传失败code:'.$res['code'].'msg:'.json_encode($res));

            if($res['code'] == 1281){
                return $this->response($res['data']);
            }
            return $this->response('上传失败', 500);
        }
        return $this->response($res['data']);
    }


    // region cc 的视频上传接口

    public function uploadUrlForCC(Request $request)
    {
        /**
         *  cc 文件上传 只可以上传视频文件
         *  1 客户端传递 title md5 category
         *  2 根据  服务器上的 分类来 搜索 cc 上同样存在的分类
         *  3 返回上传文件信息
         *  4 等待上传文件完成  异步回调
         *
         */
        //  string $title, string $tag, string $description, string $filename,
        //                                         string $filesize, string $categoryid = "")
        $validator = Validator::make($request->all(), [
            'title'     => 'required',
            'video_md5' => 'required',
            'filename' => 'required',
            'filesize' => 'required',

        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        // TODO:  这里替换欢托的sdk CC 直播的 点播的业务 暂时不处理
//        $CCCloud = new CCCloud();
//        $CCCloud ->cc_video_create_upload_info($request->input('title'),$request->input('title'),
//            $request->input('filename') ,$request->input('filesize'),);


        $MTCloud = new MTCloud();
        $options = [
            'course' => [
                'start_time' => date("Y-m-d H:i", strtotime("-1 day")),
            ],
        ];
        $res = $MTCloud->videoGetUploadUrl(1, 2, $request->input('title'), $request->input('video_md5'), $options);

        if (!array_key_exists('code', $res) || $res[ 'code' ] != 0) {
            Log::error('上传失败code:' . $res[ 'code' ] . 'msg:' . json_encode($res));

            if ($res[ 'code' ] == 1281) {
                return $this->response($res[ 'data' ]);
            }
            return $this->response('上传失败', 500);
        }
        return $this->response($res[ 'data' ]);

    }
   // endregion
}
