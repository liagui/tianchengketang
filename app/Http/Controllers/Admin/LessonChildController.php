<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonChild;
use Illuminate\Http\Request;
use App\Tools\CurrentAdmin;
use DB;
use Validator;
use App\Models\Teacher;

class LessonChildController extends Controller {

    /**
     * @param  章节列表
     * @param  current_count   count
     * @param  author  孙晓丽
     * @param  ctime   2020/5/8 
     * @return  array
     */
    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required',
            'pid'       => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $pagesize = $request->input('pagesize') ?: 15;
        $page     = $request->input('page') ?: 1;
        $offset   = ($page - 1) * $pagesize;
        $lesson_id = $request->input('lesson_id');
        $pid = $request->input('pid') ?: 0;
        $total = LessonChild::where([
                'is_del' => 0,
                'is_forbid' => 0,
                'lesson_id' => $lesson_id,
                'pid' => $pid
            ])->count();
        $lesson = LessonChild::where(['is_del' => 0, 'is_forbid' => 0, 'lesson_id' => $lesson_id, 'pid' => $pid])
            ->skip($offset)->take($pagesize)
            ->get();
        if($pid == 0){
            foreach ($lesson as $k => $value) {
                $lesson[$k]['childs'] = LessonChild::where(['is_del' => 0, 'is_forbid' => 0, 'lesson_id' => $lesson_id, 'pid' => $value->id])->get();
            }
        }
    
        $data = [
            'page_data' => $lesson,
            'total' => $total,
        ];
        return $this->response($data);
    }


    /**
     * @param  章节详情
     * @param  课程id
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1 
     * @return  \Illuminate\Http\Response
     */
    public function show(Request $request) {
        $validator = Validator::make($request->all(), [
            'id'        => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson = LessonChild::select('id', 'name', 'description')->find($request->input('id'));
        $lesson['childs'] = LessonChild::select('id', 'name', 'category', 'description' ,'url', 'is_free')->where('pid', $request->input('id'))->get();
        if(empty($lesson)){
            return $this->response('课程不存在', 404);
        }
        return $this->response($lesson);
    }


    /**
     * 添加章节.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required',
            'name'      => 'required',
            'pid'       => 'required',
            'is_free'   => 'required_unless:pid,0',
            'category'  => 'required_unless:pid,0',
            'url'       => 'json',
            'video_id'  => 'integer'
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $videoIds = $request->input('video_id'); 
        $user = CurrentAdmin::user();
        try {
            $lesson = LessonChild::create([
                    'admin_id' => intval($user->id),
                    'lesson_id' => $request->input('lesson_id'),
                    'name'      => $request->input('name'),
                    'pid'       => $request->input('pid'),
                    'category'  => $request->input('category') ?: 0, 
                    'url'       => $request->input('url'),
                    'is_free'   => $request->input('is_free') ?: 0,
                ]);

            if(!empty($videoIds)){
                $lesson->videos()->attach($videoIds); 
            }

        } catch (Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        return $this->response('创建成功');
    }


    /**
     * 修改章节.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request) {
        $validator = Validator::make($request->all(), [
            'id'        => 'required',
            'url'       => 'json',
            'video_id'  => 'integer'
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $videoIds = $request->input('video_id');
        try {
            $lesson = LessonChild::findOrFail($request->input('id'));
            $lesson->lesson_id = $request->input('lesson_id') ?: $lesson->lesson_id;
            $lesson->name = $request->input('name') ?: $lesson->name;
            $lesson->pid = $request->input('pid') ?: $lesson->pid;
            $lesson->category = $request->input('category') ?: $lesson->category;
            $lesson->url = $request->input('url') ?: $lesson->url;
            $lesson->is_free = $request->input('is_free') ?: $lesson->is_free;
            $lesson->save();
            if(!empty($videoIds)){
                $lesson->videos()->detach(); 
                $lesson->videos()->attach($videoIds); 
            }
            return $this->response("修改成功");
        } catch (Exception $e) {
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
    public function destroy(Request $request) {
        $validator = Validator::make($request->all(), [
            'id'        => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $lesson = LessonChild::findOrFail($request->input('id'));
        if($lesson->is_del == 1){
            return $this->response("已经删除", 205);
        }
        $lesson->is_del = 1;
        if (!$lesson->save()) {
            return $this->response("删除失败", 500);
        }
        return $this->response("删除成功");
    }
}
