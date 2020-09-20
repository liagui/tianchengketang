<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Live;
use App\Models\Subject;
use Illuminate\Http\Request;
use  App\Tools\CurrentAdmin;
use Validator;
use App\Tools\MTCloud;
use App\Models\LessonLive;
use App\Models\Lesson;
use App\Models\LessonChild;
use App\Models\LiveClassChild;
use App\Models\LiveChild;
use App\Models\Teacher;

class LiveController extends Controller {

    /*
     * @param  课程关联的直播列表
     * @param  current_count   count
     * @param  author  孙晓丽
     * @param  ctime   2020/6/14
     * return  array
     */
    public function lessonRelatedLive(Request $request){
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson_id = $request->input('lesson_id');
        $data = Live::with('lessons')->select('id', 'admin_id', 'name', 'created_at')
                ->with(['class' => function ($query) {
                    $query->where(['is_del' => 0, 'is_forbid' => 0])->select('id', 'name', 'live_id');
                }])
                ->where('is_del', 0)
                ->orderBy('created_at', 'desc')
                ->whereHas('lessons', function ($query) use ($lesson_id)
                    {
                        $query->where('id', $lesson_id);
                    });
        $total = $data->count();
        $live = $data->orderBy('created_at', 'desc')->get();
        $lives = [];
        foreach ($live as $key => $value) {
            $lives[$key]['name'] = $value['name'];
            $lives[$key]['class'] = $value['class'];
        }
        $data = [
            'page_data' => $lives,
            'total' => $total,
        ];
        return $this->response($data);
    }

    /*
     * @param  直播列表
     * @param  author  zzk
     * @param  ctime   2020/6/28
     * return  array
     */
    public function index(Request $request){
        try{
            $list = Live::getLiveList(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /*
     * @param  未删除和未禁用的直播列表
     * @param  pagesize   page
     * @param  author  孙晓丽
     * @param  ctime   2020/6/13
     * return  array
     */
    public function list(Request $request){
        $subject_id = $request->input('subject_id') ?: 0;
        $keyWord = $request->input('keyword') ?: 0;
        $data = Live::select('id', 'admin_id', 'name', 'created_at')->with('subjects')
                ->where(['is_del' => 0, 'is_forbid' => 0])
                ->orderBy('created_at', 'desc')
                ->whereHas('subjects', function ($query) use ($subject_id)
                    {
                       if($subject_id != 0){
                            $query->where('id', $subject_id);
                        }
                    })
                ->where(function($query) use ($keyWord){
                    if(!empty($keyWord)){
                        $query->where('name', 'like', '%'.$keyWord.'%');
                    }
                });
        $total = $data->count();
        $lives = [];
        foreach ($data->get()->toArray() as $key => $value) {
            $lives[$key]['id'] =  $value['id'];
            $lives[$key]['name'] =  $value['name'];
            $lives[$key]['subject_id'] =  $value['subject_id'];
            $lives[$key]['subject_first_name'] =  $value['subject_first_name'];
            $lives[$key]['subject_second_name'] =  $value['subject_second_name'];
        }
        $data = [
            'page_data' => $lives,
            'total' => $total,
        ];
        return $this->response($data);
    }

    /*
     * @param  直播详情
     * @param  直播id
     * @param  author  zzk
     * @param  ctime   2020/6/28
     * return  array
     */
    public function show(Request $request) {
        try{
            $one = Live::getLiveOne(self::$accept_data);
            return response()->json($one);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  班号列表
     * @param  直播id
     * @param  author  zzk
     * @param  ctime   2020/5/18
     * return  array
     */
    public function classList(Request $request) {
        //获取班号列表
    }


    /**
     * 添加直播资源.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //获取提交的参数
        try{
            $data = Live::AddLive(self::$accept_data);
            return response()->json($data);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /**
     * 获取直播关联课程ID.
     *
     * @param  live_id
     * @return array
     */
    public function lessonId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'live_id' => 'required|json',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $live = Live::find($request->input('live_id'));
        if(empty($live)){
            return $this->response('直播资源不存在', 202);
        }
        try {
            $lessonIds = $live->lessons()->pluck('lesson_id');
        } catch (Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        return $this->response($lessonIds);
    }

    /**
     * 直播批量关联课程.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function lesson(Request $request)
    {
        try{
            $list = Live::liveRelationLesson(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
    //关联课程列表
    public function lessonList(){
        try{
            $list = Live::LessonList(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }


    /**
     * 修改直播资源
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request) {
        try{
            $list = Live::updateLive(self::$accept_data);
            return response()->json($list);
        } catch (Exception $ex) {
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
            $one = Live::updateLiveStatus(self::$accept_data);
            return response()->json($one);
        } catch (Exception $ex) {
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
            $one = Live::updateLiveDelete(self::$accept_data);
            return response()->json($one);
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }
}
