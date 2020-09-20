<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonStock;
use Illuminate\Http\Request;
use App\Tools\CurrentAdmin;
use DB;
use Validator;
use App\Models\Teacher;

class LessonStockController extends Controller {

    /**
     * @param  库存列表
     * @param  current_count   count
     * @param  author  孙晓丽
     * @param  ctime   2020/5/15 
     * return  array
     */
    public function index(Request $request){
        $pagesize = $request->input('pagesize') ?: 15;
        $page     = $request->input('page') ?: 1;
        $offset   = ($page - 1) * $pagesize;
        $lesson_id = $request->input('lesson_id');
        $school_id = $request->input('school_id');
        $data =  LessonStock::where(['lesson_id' => $lesson_id, 'school_id' => $school_id]);
        $total = $data->count();
        $stock = $data->select('current_number', 'add_number', 'created_at')->orderBy('created_at', 'desc')->skip($offset)->take($pagesize)->get();

        $data = [
            'page_data' => $stock,
            'total' => $total,
            'current_stock_number' => 0,
            'stock_number' => (int)$data->sum('add_number'),
        ];
        return $this->response($data);
    }

    /**
     * 授权课程添加库存.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required|integer',
            'school_id' => 'required',
            'add_number' => 'required',
            'current_number' => 'required',

        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $data = $request->all();
        try {
            $this->create($data);
        } catch (Exception $e) {
            Log::error('创建失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        return $this->response('创建成功');
    }


    public function create($data)
    {
        $user = CurrentAdmin::user();
        return LessonStock::create([
            'admin_id' => $user->id,
            'lesson_id' => $data['lesson_id'],
            'school_pid' =>$user->school_id,
            'school_id' => $data['school_id'],
            'current_number' => $data['current_number'],
            'add_number' => $data['add_number'],
        ]);
    }
}
