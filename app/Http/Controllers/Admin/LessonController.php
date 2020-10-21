<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Tools\CCCloud\CCCloud;
use Illuminate\Http\Request;
use App\Tools\CurrentAdmin;
use DB;
use Validator;
use App\Models\Teacher;
use App\Models\LessonSchool;
use App\Tools\MTCloud;
use App\Models\LiveChild;
use App\Models\LiveTeacher;
use App\Models\Live;
use App\Models\School;

class LessonController extends Controller {


    /**
     * @param  分校课程列表
     * @param  pagesize   count
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1
     * return  array
     */
    public function schoolLesson(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $pagesize = $request->input('pagesize') ?: 15;
        $page     = $request->input('page') ?: 1;
        $offset   = ($page - 1) * $pagesize;
        $subject_id = $request->input('subject_id') ?: 0;
        $method = $request->input('method') ?: 0;
        //授权课程ID
        $authLessonIds = LessonSchool::where('school_id', $request->input('school_id'))->pluck('lesson_id');
        //自增课程ID
        $adminIds = School::find($request->input('school_id'))->admins->pluck('id');
        $lessonIds = Lesson::whereIn('admin_id', $adminIds)->pluck('id');
        if(!empty($lessonIds) && !empty($authLessonIds)){
            $resIda = array_merge($lessonIds->toArray(), $authLessonIds->toArray());
        }
        $data =  Lesson::with('subjects', 'methods')
                ->whereIn('id', $resIda)
                ->whereHas('subjects', function ($query) use ($subject_id)
                    {
                       if($subject_id != 0){
                            $query->where('id', $subject_id);
                        }
                    })
                ->whereHas('methods', function ($query) use ($method)
                    {
                        if($method != 0){
                            $query->where('id', $method);
                        }
                    });
        $lessons = [];
        foreach ($data->get()->toArray() as $key=>$value) {

            $flipped_haystack = array_flip($authLessonIds->toArray());
            if(isset($flipped_haystack[$value['id']]))
            {
                $value['is_auto'] = 2;
            }else{
                $value['is_auto'] = 1;
            }
            $lessons[] = $value;
        }

        $total = collect($lessons)->count();
        $lesson = collect($lessons)->skip($offset)->take($pagesize);
        $data = [
            'page_data' => $lesson,
            'total' => $total,
        ];
        return $this->response($data);
    }

    /**
     * @param  课程列表
     * @param  pagesize   count
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1
     * return  array
     */
    public function index(Request $request){
        $pagesize = $request->input('pagesize') ?: 15;
        $page     = $request->input('page') ?: 1;
        $offset   = ($page - 1) * $pagesize;
        $subject_id = $request->input('subject_id') ?: 0;
        $method = $request->input('method') ?: 0;
        $status = $request->input('status') ?: 0;
        $auth = (int)$request->input('auth') ?: 0;
        $public = (int)$request->input('public') ?: 0;
        $keyWord = $request->input('keyword') ?: 0;
        $user = CurrentAdmin::user();
        $data =  Lesson::with('subjects', 'methods')->select('id', 'admin_id', 'title', 'cover', 'price', 'favorable_price', 'buy_num', 'status', 'is_del', 'is_forbid', 'is_recommend')
                ->where(['is_del' => 0, 'is_forbid' => 0])

                ->whereHas('subjects', function ($query) use ($subject_id)
                    {
                       if($subject_id != 0){
                            $query->where('id', $subject_id);
                        }
                    })
                ->whereHas('methods', function ($query) use ($method)
                    {
                        if($method != 0){
                            $query->where('id', $method);
                        }
                    })
                ->where(function($query) use ($status){
                    if($status == 0){
                        $query->whereIn("status", [1, 2, 3]);
                    }else{
                        $query->where("status", $status);
                    }
                })
                ->where(function($query) use ($keyWord){
                    if(!empty($keyWord)){
                        $query->where('title', 'like', '%'.$keyWord.'%');
                    }
                });
        $lessons = [];

        foreach ($data->get()->toArray() as $value) {

            if($auth == 0){
                if($value['is_auth'] == 1 || $value['is_auth'] == 2){
                    $lessons[] = $value;
                }

            }else{
                if($value['is_auth'] == $auth){
                    $lessons[] = $value;
                }
            }
        }
        $total = collect($lessons)->count();
        $lesson = collect($lessons)->skip($offset)->take($pagesize);
        $data = [
            'page_data' => $lesson,
            'total' => $total,
        ];
        return $this->response($data);
    }

    /*
     * @param  课程详情
     * @param  课程id
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1
     * return  array
     */
    public function show(Request $request) {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson = Lesson::with(['teachers' => function ($query) {
                $query->select('id as teacher_id', 'real_name');
            }])
        ->with(['subjects' => function ($query) {
                $query->select('id', 'name');
            }])
        ->with(['methods' => function ($query) {
                $query->select('id', 'name');
            }])
        ->find($request->input('id'));
        if(empty($lesson)){
            return $this->response('课程不存在', 404);
        }
        return $this->response($lesson);
    }


    /**
     * 添加课程.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required',
            'title' => 'required',
            'price' => 'required',
            'favorable_price' => 'required',
            'method_id' => 'required|json',
            'cover' => 'required',
            'description' => 'required',
            'introduction' => 'required',
            'is_public' => 'required',
            'nickname' => 'required_if:is_public,1',
            'start_at' => 'required_if:is_public,1',
            'end_at' => 'required_if:is_public,1',
            'teacher_id' => 'required_if:is_public,1',
        ]);

        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $methodIds = json_decode($request->input('method_id'), true);
        $subjectIds = json_decode($request->input('subject_id'), true);
        $teacherIds = json_decode($request->input('teacher_id'), true);
        $user = CurrentAdmin::user();
        //DB::beginTransaction(); //开启事务
        try {
            $lesson = Lesson::create([
                    'admin_id' => intval($user->id),
                    'title' => $request->input('title'),
                    'keyword' => $request->input('keyword') ?: NULL,
                    'price' => $request->input('price'),
                    'favorable_price' => $request->input('favorable_price'),
                    'cover' => $request->input('cover'),
                    'description' => $request->input('description'),
                    'introduction' => $request->input('introduction'),
                    'is_public' => $request->input('is_public'),
                    'buy_num' => $request->input('buy_num') ?: 0,
                    'ttl' => $request->input('ttl') ?: 0,
                    'status' => $request->input('status') ?: 1,
                ]);
            if(!empty($teacherIds)){
                $lesson->teachers()->attach($teacherIds);
            }
            if(!empty($subjectIds)){
                $lesson->subjects()->attach($subjectIds);
            }
            if(!empty($methodIds)){
                $lesson->methods()->attach($methodIds);
            }
            if($request->input('is_public') == 1){
                $this->addLive($request->all(), $lesson->id);
            }
            //DB::commit();  //提交
        } catch (Exception $e) {
            //DB::rollback();  //回滚
            Log::error('创建失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        return $this->response('创建成功');
    }


    /**
     * 批量关联直播.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function relatedLive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required',
            'live_id' => 'required|json',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $liveIds = json_decode($request->input('live_id'), true);
        if(empty($liveIds)){
            return $this->response('直播资源ID不能为空', 202);
        }
        $lesson = Lesson::find($request->input('lesson_id'));
        if(empty($lesson)){
            return $this->response('课程不存在', 202);
        }
        $relatedLiveIds = $lesson->lives()->pluck('live_id');
        if(!empty($relatedLiveIds)){
            foreach ($relatedLiveIds as $key => $value) {
                $flipped_haystack = array_flip($liveIds);
                if(isset($flipped_haystack[$value]))
                {
                    return $this->response('不能重复关联直播', 202);
                }
            }
        }
        try {
            $lesson->lives()->attach($liveIds);
        } catch (Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        return $this->response('创建成功');
    }

    /**
     * 修改课程
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request) {
        $validator = Validator::make($request->all(), [
            'id'=> 'required',
            'is_public' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $methodIds = json_decode($request->input('method_id'), true);
        $subjectIds = json_decode($request->input('subject_id'), true);
        $teacherIds = json_decode($request->input('teacher_id'), true);
        try {
            $lesson = Lesson::findOrFail($request->input('id'));
            $lesson->title   = $request->input('title') ?: $lesson->title;
            $lesson->keyword = $request->input('keyword') ?: $lesson->keyword;
            $lesson->cover   = $request->input('cover') ?: $lesson->cover;
            $lesson->price   = $request->input('price') ?: $lesson->price;
            $lesson->favorable_price = $request->input('favorable_price') ?: $lesson->favorable_price;
            $lesson->introduction = $request->input('introduction') ?: $lesson->introduction;
            $lesson->description = $request->input('description') ?: $lesson->description;
            $lesson->buy_num = $request->input('buy_num') ?: $lesson->buy_num;
            $lesson->ttl     = $request->input('ttl') ?: $lesson->ttl;
            $lesson->start_at = $request->input('start_at') ?: $lesson->start_at;
            $lesson->end_at = $request->input('end_at') ?: $lesson->end_at;
            $lesson->status = $request->input('status') ?: $lesson->status;
            $lesson->save();
            $lesson->subjects()->sync($subjectIds);
            $lesson->teachers()->sync($teacherIds);
            $lesson->methods()->sync($methodIds);
            return $this->response("修改成功");
        } catch (Exception $e) {
            Log::error('修改课程信息失败' . $e->getMessage());
            return $this->response("修改成功");
        }
    }



    /**
     * 添加/修改课程资料
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Request $request) {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'url' => 'required|json',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson = Lesson::findOrFail($request->input('id'));;
        $lesson->url = $request->input('url');
        try {
            $lesson->save();
            return $this->response("修改成功");
        } catch (Exception $e) {
            Log::error('修改课程信息失败' . $e->getMessage());
            return $this->response("修改成功");
        }
    }

    /**
     * 修改课程状态
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request) {
        $validator = Validator::make($request->all(), [
            'id'     => 'required',
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        try {
            $lesson = Lesson::findOrFail($request->input('id'));
            $lesson->status = $request->input('status');
            $lesson->save();
            return $this->response("修改成功");
        } catch (Exception $e) {
            Log::error('修改课程信息失败' . $e->getMessage());
            return $this->response("修改成功");
        }
    }


    /**
     * 修改课程推荐状态
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function isRecommend(Request $request) {
        $validator = Validator::make($request->all(), [
            'id'     => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        try {
            $lesson = Lesson::findOrFail($request->input('id'));
            if($lesson->is_recommend == 1){
                $lesson->is_recommend = 0;
            }else{
                $lesson->is_recommend = 1;
            }
            $lesson->save();
            return $this->response("修改成功");
        } catch (Exception $e) {
            Log::error('修改课程信息失败' . $e->getMessage());
            return $this->response("修改成功");
        }
    }

    /**
     * 删除课程
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request) {
        $validator = Validator::make($request->all(), [
            'id'     => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson = Lesson::findOrFail($request->input('id'));
        if($lesson->is_del == 1){
            return $this->response("已经删除", 404);
        }
        $lesson->is_del = 1;
        if (!$lesson->save()) {
            return $this->response("删除失败", 500);
        }
        return $this->response("删除成功");
    }


    //公开课创建直播
    public function addLive($data, $lesson_id)
    {
        $user = CurrentAdmin::user();
        try {
            //todo: 这里替换了 欢托的sdk ok
//            $MTCloud = new MTCloud();
//            $res = $MTCloud->courseAdd($data['title'], $data['teacher_id'], $data['start_at'], $data['end_at'],
//                $data['nickname'], '', [
//                    'barrage' => $data['barrage'],
//                    'modetype' => $data['modetype'],
//                ]
//            );

            //todo: 这里替换了 欢托的sdk
            $CCCloud = new CCCloud();
            //产生 教师端 和 助教端 的密码 默认一致
            $password= $CCCloud ->random_password();

            $room_info = $CCCloud ->create_room($data['title'], $data['title'],$password,$password);

            if(!array_key_exists('code', $room_info) && !$room_info["code"] == 0){
                Log::error('欢拓创建失败:'.json_encode($room_info));
                return false;
            }
            $live = Live::create([
                    'admin_id' => intval($user->id),
                    'subject_id' => $data['subject_id'],
                    'name' => $data['title'],
                    'description' => $data['description'],
                ]);

            $live->lessons()->attach([$lesson_id]);
            $livechild =  LiveChild::create([
                            'admin_id'   => $user->id,
                            'live_id'    => $live->id,
                            'course_name' => $data['title'],
                            'account'     => $data['teacher_id'],
                            'start_time'  => $data['start_at'],
                            'end_time'    => $data['end_at'],
                            'nickname'    => $data['nickname'],
                            // 这两个数值是欢托有的但是CC没有的 因此 这两个保持空
                            // 'partner_id'  => $room_info['data']['partner_id'],
                            // 'bid'         => $room_info['data']['bid'],
                            'partner_id'  => "",
                            'bid'         => "",

                            // 这里存放的是 欢托的课程id 但是这里 改成 cc 的 直播id 直接进入直播间
                            // 'course_id'   => $room_info['data']['course_id'],
                            'course_id'   => $room_info['data']['room']['id'],

                            // 主播端 助教端 用户端的密码
                            'zhubo_key'   => $password,
                            'admin_key'   => $password,
                            'user_key'    => "",
                            // add time 是欢托存在的但是cc 没 这里默认获取系统时间戳
                            // 'add_time'    => $room_info['data']['add_time'],
                            'add_time'    => time(),
                        ]);
            LiveTeacher::create([
                'admin_id' => $user->id,
                'live_id' => $live->id,
                'live_child_id' => $livechild->id,
                'teacher_id' => $data['teacher_id'],
            ]);
        } catch (Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return false;
        }
        return true;
    }
}
