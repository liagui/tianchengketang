<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonSchool;
use App\Models\School;
use App\Models\Lesson;
use App\Models\Admin;
use Illuminate\Http\Request;
use App\Tools\CurrentAdmin;
use DB;
use Validator;

class LessonSchoolController extends Controller {


    /**
     * @param  授权课程ID
     * @param  school_id
     * @param  author  孙晓丽
     * @param  ctime   2020/6/2
     * @return  array
     */
    public function lessonIdList(Request $request){
        $validator = Validator::make($request->all(), [
            'school_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $school_id = $request->input('school_id');
        $lessonIds = LessonSchool::where('school_id', $school_id)
                ->pluck('lesson_id');
        return $this->response($lessonIds);
    }

    /**
     * @param  分校授权课程列表
     * @param  current_count   count
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1
     * return  array
     */
    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'school_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $pagesize = $request->input('pagesize') ?: 15;
        $page     = $request->input('page') ?: 1;
        $offset   = ($page - 1) * $pagesize;
        $school_id = $request->input('school_id');
        $total = LessonSchool::where('school_id', $school_id)->count();
        $lesson = LessonSchool::where('school_id', $school_id)
                ->orderBy('created_at', 'desc')
                ->skip($offset)->take($pagesize)
                ->get();

        $data = [
            'page_data' => $lesson,
            'total' => $total,
        ];
        return $this->response($data);
    }



    /*
     * @param  分校课程详情
     * @param  课程id
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1
     * return  array
     */
    public function show($id) {
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required|json',
            'school_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson = LessonSchool::with('lesson')->find($id);
        if(empty($lesson)){
            return $this->response('不存在', 404);
        }
        return $this->response($lesson);
    }


    /**
     * 批量授权添加课程.
     *
     * @param  lesson_id school_id
     * @return json
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required|json',
            'school_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        //分校管理员列表
        $schoolAdminIds = Admin::where('school_id', $request->input('school_id'))->pluck('id');
        $user = CurrentAdmin::user();
        $lessonIds = json_decode($request->input('lesson_id'), true);
        //课程创建管理员
        $userIds = Lesson::whereIn('id', $lessonIds)->distinct()->pluck('admin_id');
        foreach ($userIds as $key => $value) {
            $flipped_haystack = array_flip($schoolAdminIds->toArray());
            if(isset($flipped_haystack[$value]))
            {
                return $this->response('自增课程无法再次授权', 202);
            }
        }
        $schoolLessonIds = School::find($request->input('school_id'))->lessons->pluck('id');
        foreach ($lessonIds as $k => $val) {
            $res = array_flip($schoolLessonIds->toArray());
            if(isset($res[$val]))
            {
                return $this->response('已经授权', 202);
            }
        }
        try {
                foreach ($lessonIds as $value) {
                    LessonSchool::create([
                        'admin_id' => intval($user->cur_admin_id),
                        'lesson_id' => $value,
                        'school_id' => $request->input('school_id'),
                    ]);
                }
        } catch (\Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        return $this->response('创建成功');
    }


    /**
     * 分校修改授权课程内容
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($id, Request $request) {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'keyword' => 'required',
            'cover' => 'required',
            'price' => 'required',
            'favorable_price' => 'required',
            'method' => 'required',
            'teacher_id' => 'required',
            'description' => 'required',
            'introduction' => 'required',
            'subject_id' => 'required',
            'is_public' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson = Lessonschool::findOrFail($id);;
        $lesson->title = $request->input('title') ?: $lesson->title;
        $lesson->keyword = $request->input('keyword') ?: $lesson->keyword;
        $lesson->cover = $request->input('cover') ?: $lesson->cover;
        $lesson->price = $request->input('price') ?: $lesson->price;
        $lesson->method = $request->input('method') ?: $lesson->method;
        $lesson->description = $request->input('description') ?: $lesson->description;
        $lesson->is_public = $request->input('is_public') ?: $lesson->is_public;
        try {
            $lesson->save();
            return $this->response("修改成功");
        } catch (\Exception $e) {
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
            'url' => 'required|json',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson = Lesson::findOrFail($id);;
        $lesson->url = $request->input('url');
        try {
            $lesson->save();
            return $this->response("修改成功");
        } catch (\Exception $e) {
            Log::error('修改课程信息失败' . $e->getMessage());
            return $this->response("修改成功");
        }
    }



    /**
     * 删除
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $lesson = Lesson::findOrFail($id);
        $lesson->is_del = 1;
        if (!$lesson->save()) {
            return $this->response("删除失败", 500);
        }
        return $this->response("删除成功");
    }
}
