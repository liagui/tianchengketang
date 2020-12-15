<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\CourseRefSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Method;

class SubjectController extends Controller {

    /*
     * @param  科目列表
     * @param  current_count   count
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1
     * return  array
     */
    public function index(Request $request){
        //查询学科
        //登录
        //获取请求的平台端
        $platform = verifyPlat() ? verifyPlat() : 'pc';
        //获取用户token值
        $token = $request->input('user_token');
        //hash中token赋值
        $token_key   = "user:regtoken:".$platform.":".$token;
        //判断token值是否合法
        $redis_token = Redis::hLen($token_key);
        if($redis_token && $redis_token > 0) {
            //解析json获取用户详情信息
            $json_info = Redis::hGetAll($token_key); //获取请求的平台端
            //已登录
            $school_id = $json_info['school_id'];
            $subjects = CourseRefSubject::join("ld_course_subject","ld_course_subject.id","=","ld_course_ref_subject.parent_id")
            ->where("ld_course_ref_subject.to_school_id",$school_id)
            ->select('ld_course_ref_subject.id', 'ld_course_subject.subject_name as name','ld_course_ref_subject.parent_id as pid')
            ->orderBy('ld_course_ref_subject.id', 'desc')
            ->where('ld_course_ref_subject.is_del',0)
            ->groupBy("pid")
            ->get()->toArray();
        }else{
            $school_id = 37;
            //未登录
            $subjects = CourseRefSubject::join("ld_course_subject","ld_course_subject.id","=","ld_course_ref_subject.parent_id")
            ->where("ld_course_ref_subject.to_school_id",37)
            ->select('ld_course_ref_subject.id', 'ld_course_subject.subject_name as name','ld_course_ref_subject.parent_id as pid')
            ->orderBy('ld_course_ref_subject.id', 'desc')
            ->where('ld_course_ref_subject.is_del',0)
            ->groupBy("pid")
            ->get()->toArray();
        }
        foreach ($subjects as $k => $value) {
                $child = [['id' => 0, 'name' => '全部']];
                $subjects[$k]['childs'] = array_merge($child, Subject::where('parent_id', $value['pid'])
                ->select('id', 'subject_name as name', 'parent_id as pid')
                ->orderBy('create_at', 'desc')
                ->get()->toArray());
        }
        foreach ($subjects as $k => &$value) {
            foreach($value['childs'] as $kk => &$vv){
                if(isset($vv['pid'])){
                    $res = CourseRefSubject::select("child_id")->where(["parent_id"=>$vv['pid'],"child_id"=>$vv['id'],"is_del"=>0,'to_school_id'=>$school_id])->count();
                    if($res == 0){
                        unset($value['childs'][$kk]);
                    }
                }
            }
            $value['childs']  = array_values($value['childs']);
        }
        $all = [['id' => 0, 'name' => '全部', 'pid' => 0, 'childs' => []]];
        $data['subjects'] = array_merge($all, $subjects);
        $data['sort'] = [
            ['sort_id' => 0, 'name' => '综合'],
            ['sort_id' => 1, 'name' => '按热度'],
            ['sort_id' => 2, 'name' => '按价格升', 'type' => ['asc']],
            ['sort_id' => 3, 'name' => '按价格降', 'type' => ['desc']],
        ];
        $data['method'] = [
            ['method_id' => 0, 'name' => '全部'],
            ['method_id' => 1, 'name' => '直播'],
            ['method_id' => 2, 'name' => '录播'],
            ['method_id' => 3, 'name' => '其他']
        ];
        return $this->response($data);
    }
}
