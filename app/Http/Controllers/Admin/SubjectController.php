<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use App\Tools\CurrentAdmin;
use DB;
use Validator;

class SubjectController extends Controller {

    /*
     * @param  科目列表
     * @param  pagesize   page
     * @param  author  孙晓丽
     * @param  ctime   2020/6/5
     * return  array
     */
    public function index(Request $request){
        $data = Subject::where(['is_del'=> 0, 'pid' => 0]);
        $total = $data->count();
        $subject = $data->get();
        foreach ($subject as $value) {
            $value['childs'] = Subject::select('id', 'name', 'is_del', 'is_forbid')->where(['is_del'=> 0, 'pid' => $value->id])->get();
        }
        return $this->response($subject);
    }


    /*
     * @param  搜索科目列表
     * @param  author  孙晓丽
     * @param  ctime   2020/6/5
     * return  array
     */
    public function searchList(Request $request){
        $data = Subject::select('id', 'name', 'is_del', 'is_forbid')->where(['is_del'=> 0, 'is_forbid' => 0, 'pid' => 0]);
        $total = $data->count();
        $subject = $data->get();
        foreach ($subject as $value) {
            $value['childs'] = $value->childs();
        }
        return $this->response($subject);
    }


    /*
     * @param  科目详情
     * @param  科目id
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
        $subject = Subject::find($request->input('id'));
        $subject['childs'] = $subject->childs();
        if(empty($subject)){
            return $this->response('科目不存在', 404);
        }
        return $this->response($subject);
    }


    /**
     * 添加科目.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pid' => 'required',
            'name' => 'required',
            'cover' => 'required_if:pid,0',
            'description' => 'required_if:pid,0',

        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $user = CurrentAdmin::user();

        try {
            $subject = Subject::create([
                    'admin_id' => intval($user->cur_admin_id),
                    'pid' => $request->input('pid'),
                    'name' => $request->input('name'),
                    'cover' => $request->input('cover'),
                    'description' => $request->input('description'),
                ]);
        } catch (\Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        return $this->response('创建成功');
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request) {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $subject = Subject::findOrFail($request->input('id'));
        $subject->pid = $request->input('pid') ?: $subject->pid;
        $subject->name = $request->input('name') ?: $subject->name;
        $subject->cover = $request->input('cover') ?: $subject->cover;
        $subject->description = $request->input('description') ?: $subject->description;
        try {
            $subject->save();
            return $this->response("修改成功");
        } catch (\Exception $e) {
            Log::error('修改科目信息失败' . $e->getMessage());
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
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $subject = Subject::findOrFail($request->input('id'));
        if($subject->is_del == 1){
            return $this->response("已经删除", 202);
        }
        $subject->is_del = 1;
        if (!$subject->save()) {
            return $this->response("删除失败", 500);
        }
        return $this->response("删除成功");
    }

    /**
     * 修改禁用状态
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request) {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $subject = Subject::findOrFail($request->input('id'));
        if($subject->is_forbid == 1){
            $subject->is_forbid = 0;
        }else{
            $subject->is_forbid = 1;
        }
        if (!$subject->save()) {
            return $this->response("禁用失败", 500);
        }
        return $this->response("禁用成功");
    }
}
