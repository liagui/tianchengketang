<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Method;
use Illuminate\Http\Request;
use App\Tools\CurrentAdmin;
use Validator;

class MethodController extends Controller {

    /*
     * @param  授课方式列表
     * @param  current_count   count
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1 
     * return  array
     */
    public function index(Request $request){
        $total = Method::where(['is_del' => 0,'is_forbid' => 0])->count();
        $method = Method::where(['is_del' => 0, 'is_forbid' => 0])->get();
        $data = [
            'page_data' => $method,
            'total' => $total,
        ];
        return $this->response($data);
    }


    /**
     * 添加授课方式.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $user = CurrentAdmin::user();
        try {
            Method::create([
                'admin_id' => intval($user->id),
                'name' => $request->input('name')
            ]);
        } catch (Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        return $this->response('创建成功');
    }


    /**
     * 修改授课方式
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request) {
        $validator = Validator::make($request->all(), [
            'id'   => 'required',
            'name' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        try {
            $method = Method::findOrFail($request->input('id'));
            $method->name = $request->input('name') ?: $method->name;
            $method->is_forbid = $request->input('is_forbid') ?: $method->is_forbid;
            $method->save();
            return $this->response("修改成功");
        } catch (Exception $e) {
            Log::error('修改科目信息失败' . $e->getMessage());
            return $this->response("修改成功");
        }
    }


    /**
     * 删除授课方式
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
        $method = Method::findOrFail($request->input('id'));
        if($method->is_del == 1){
            return $this->response("已经删除", 404);
        }
        $method->is_del = 1;
        if (!$method->save()) {
            return $this->response("删除失败", 500);
        }
        return $this->response("删除成功");
    }
}