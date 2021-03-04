<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Models\Collection;
use App\Models\AppLog;
use App\Models\Student;
use App\Models\Order;
use Illuminate\Support\Facades\DB;


class CollectionController extends Controller {

    //收藏列表
    public function index(Request $request){
        $pagesize = $request->input('pagesize') ?: 15;
        $page     = $request->input('page') ?: 1;
        $offset   = ($page - 1) * $pagesize;

        $data = Collection::join("ld_course","ld_collections.lesson_id","=","ld_course.id")
                    ->select('ld_course.id','ld_course.title','ld_course.cover','ld_course.buy_num')
                    ->where('student_id', self::$accept_data['user_info']['user_id'])
                    ->orderBy('created_at', 'desc');
        $total = $data->count();
        $student = $data->skip($offset)->take($pagesize)->get();
        $lessons = [];
        foreach ($student as $key => $value) {
            $is_collection = Collection::where(['student_id'=>self::$accept_data['user_info']['user_id'],'is_del'=>0,'lesson_id'=>$value['id']])->first();
            if($is_collection){
                $value['is_collection'] = 1;
            }else{
                $value['is_collection'] = 0;
            }
            //是否购买
            //学习人数   基数+订单数
            $ordernum = Order::where(['class_id' => $value['id'], 'status' => 2, 'oa_status' => 1])->count();
            $value['buy_num'] = $value['buy_num'] + $ordernum;
            $value['methods'] = DB::table('ld_course')->select('method_id')->join("ld_course_method","ld_course.id","=","ld_course_method.course_id")->where(['ld_course.id'=>$value['id']])->get();
        }
        foreach($student as $k => $v){
            foreach($v['methods'] as $kk => $vv){
                if($vv->method_id == 1){
                    $vv->name = "直播";
                }else if($vv->method_id == 2){
                    $vv->name = "录播";
                }else{
                    $vv->name = "其他";
                }
            }
        }
        $data = [
            'page_data' => $student,
            'total' => $total,
        ];
        //添加日志操作
        AppLog::insertAppLog([
            'admin_id'       =>  self::$accept_data['user_info']['user_id'],
            'module_name'    =>  'Collection' ,
            'route_url'      =>  'api/collection' ,
            'operate_method' =>  'select' ,
            'content'        =>  '课程收藏列表'.json_encode(['data'=>$data]) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return $this->response($data);
    }

     /**
     * @param 收藏课程.
     * @param
     * @param
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $student = Student::find(self::$accept_data['user_info']['user_id']);
        $lessonIds = $student->collectionLessons()->pluck('lesson_id');
        $flipped_haystack = array_flip($lessonIds->toArray());
        if ( isset($flipped_haystack[$request->input('lesson_id')]) )
        {
            return $this->response('已经收藏', 202);
        }
        try {
            $student->collectionLessons()->attach($request->input('lesson_id'));
        } catch (\Exception $e) {
            Log::error('收藏失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        //添加日志操作
        AppLog::insertAppLog([
            'admin_id'       =>  self::$accept_data['user_info']['user_id'],
            'module_name'    =>  'Collection' ,
            'route_url'      =>  'api/addCollection' ,
            'operate_method' =>  'Update' ,
            'content'        =>  '添加收藏成功'.json_encode(['data'=>$request->input('lesson_id')]) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return $this->response('收藏成功');
    }

    /**
     * @param 取消收藏课程.
     * @param
     * @param
     * @return \Illuminate\Http\Response
     */
    public function cancel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response($validator->errors()->first(), 202);
        }
        $student = Student::find(self::$accept_data['user_info']['user_id']);
        $lessonIds = $student->collectionLessons()->pluck('lesson_id');
        $flipped_haystack = array_flip($lessonIds->toArray());
        if (!isset($flipped_haystack[$request->input('lesson_id')]) )
        {
            return $this->response('已经取消', 202);
        }
        try {
            $student->collectionLessons()->detach($request->input('lesson_id'));
        } catch (\Exception $e) {
            Log::error('取消失败:'.$e->getMessage());
            return $this->response($e->getMessage(), 500);
        }
        //添加日志操作
        AppLog::insertAppLog([
            'admin_id'       =>  self::$accept_data['user_info']['user_id'],
            'module_name'    =>  'Collection' ,
            'route_url'      =>  'api/cancelCollection' ,
            'operate_method' =>  'Update' ,
            'content'        =>  '取消收藏课程'.json_encode(['data'=>$request->input('lesson_id')]) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return $this->response('取消成功');
    }

}
