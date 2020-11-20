<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;
use App\Models\OpenCourseTeacher;
use App\Models\Teacher;
use App\Models\Admin;
use App\Models\CouresSubject;
use App\Tools\CurrentAdmin;
class OpenCourse extends Model {
    //指定别的表名
    public $table = 'ld_course_open';
    //时间戳设置
    public $timestamps = false;

    //错误信息
    public static function message()
    {
        return [
            'openless_id.required'  => json_encode(['code'=>'201','msg'=>'公开课标识不能为空']),
            'openless_id.integer'   => json_encode(['code'=>'202','msg'=>'公开课标识类型不合法']),
            'page.required'  => json_encode(['code'=>'201','msg'=>'页码不能为空']),
            'page.integer'   => json_encode(['code'=>'202','msg'=>'页码类型不合法']),
            'limit.required' => json_encode(['code'=>'201','msg'=>'显示条数不能为空']),
            'limit.integer'  => json_encode(['code'=>'202','msg'=>'显示条数类型不合法']),
            'subject.required' => json_encode(['code'=>'201','msg'=>'学科标识不能为空']),
            'title.required' => json_encode(['code'=>'201','msg'=>'课程标题不能为空']),
            // 'name.unique' => json_encode(['code'=>'205','msg'=>'学校名称已存在']),
            'keywords.required' => json_encode(['code'=>'201','msg'=>'课程关键字不能为空']),
            'cover.required' => json_encode(['code'=>'201','msg'=>'课程封面不能为空']),
            'time.required' => json_encode(['code'=>'201','msg'=>'开课时间段不能为空']),
            'date.required' => json_encode(['code'=>'201','msg'=>'开课日期不能为空']),
            'is_barrage.required' => json_encode(['code'=>'201','msg'=>'弹幕ID不能为空']),
            'is_barrage.integer' => json_encode(['code'=>'202','msg'=>'弹幕ID不合法']),
            'live_type.required' => json_encode(['code'=>'201','msg'=>'直播类型不能为空']),
            'live_type.integer' => json_encode(['code'=>'202','msg'=>'直播类型不合法']),
            'edu_teacher_id.required'  => json_encode(['code'=>'201','msg'=>'教务标识不能为空']),
            'lect_teacher_id.required'  => json_encode(['code'=>'201','msg'=>'讲师标识不能为空']),
            'subject.required'  => json_encode(['code'=>'201','msg'=>'学科不能为空']),
            'introduce.required'  => json_encode(['code'=>'201','msg'=>'课程简介不能为空']),

        ];
    }

    public static function subject(){

        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $zizengSubject = CouresSubject::where(['school_id'=>$school_id,'is_del'=>0,'is_open'=>0])->select('id','parent_id','subject_name as name')->get()->toArray();
        $natureSubeject = CourseRefSubject::where(['to_school_id'=>$school_id,'is_del'=>0])->select('parent_id','child_id')->get()->toArray();
        // 8.13 调整 不区分课程还是公开课
        $subject = $subjectArr =  $subjectData = $newdata = [];
        if(!empty($natureSubeject)){
            $natureSubeject = array_unique($natureSubeject,SORT_REGULAR);
            foreach($natureSubeject as $key=>$v){
                if(!isset($newdata[$v['parent_id']])){
                    $newdata[$v['parent_id']] = $v;
                }
                $newdata[$v['parent_id']]['childs'][] =$v['child_id'];
            }

            foreach($newdata as $k=>$v){
                $twos = CouresSubject::select('id','subject_name as name')->where(['id'=>$v['parent_id'],'is_del'=>0,'is_open'=>0])->first();
                $twsss = CouresSubject::select('id','admin_id','subject_name as name')->whereIn('id',$v['childs'])->where(['is_del'=>0,'is_open'=>0])->get()->toArray();
                $twos['childs'] = $twsss;
                $subjectArr[] =$twos;
            }
        }
        if(!empty($zizengSubject)){
            $zizengSubject = self::demo($zizengSubject,0,0);
        }
        if(!empty($zizengSubject) && !empty($subjectArr)){
            $subjectData = array_merge($zizengSubject,$subjectArr);
        }else{
            $subjectData = !empty($zizengSubject)?$zizengSubject:$subjectArr;
        }
        return ['code' => 200 , 'msg' => '获取成功','data'=>$subjectData];
    }
     //递归
    public static function demo($arr,$id,$level){
        $list =array();
        foreach ($arr as $k=>$v){
            if ($v['parent_id'] == $id){
                $aa = self::demo($arr,$v['id'],$level+1);
                if(!empty($aa)){
                    $v['level']=$level;
                    $v['childs'] = $aa;
                }
                $list[] = $v;
            }
        }
        return $list;
    }

    /*
         * @param  descriptsion 获取公开课信息
         * @param  $school_id  公开课id
         * @param  author       lys
         * @param  ctime   2020/4/29
         * return  array
         */
    public static function getOpenLessById($where,$field = ['*']){
        $openCourseInfo = self::where($where)->select($field)->first()->toArray();
        if($openCourseInfo){
            return ['code'=>200,'msg'=>'获取课程信息成功','data'=>$openCourseInfo];
        }else{
            return ['code'=>204,'msg'=>'课程信息不存在'];
        }
    }
    /*
         * @param  descriptsion 获取公开课列表
         * @param  author       lys
         * @param  ctime   2020/4/29
         * return  array
         */
    public static function getList($body){
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;

        $ref_open_less_arr = $open_less_arr = [];
        $pagesize = !isset($body['pagesize']) || $body['pagesize'] < 0 ?  15:$body['pagesize'];
        $page     = !isset($body['page']) || $body['page'] < 0 ?1:$body['page'];
        $where['parent_id'] = !isset($body['parent_id'])|| empty($body['parent_id'])  ?'':$body['parent_id'];
        $where['child_id'] =  !isset($body['child_id']) || empty($body['child_id']) ?'':$body['child_id'];
        $where['status'] =  !isset($body['status']) || empty($body['status']) ?'':$body['status'];
        $where['time']  =  !isset($body['time']) || empty($body['time']) ?[]:json_decode($body['time'],1);
        $nature = !isset($body['nature']) || empty($body['nature']) ?'':$body['nature'];  //授权搜索 1 自增 2 授权
        if(!empty($where['time']) ){
            $where['start_at'] =  substr($where['time'][0],0,10);
            $where['end_at']  = substr($where['time'][1],0,10);
        }

        //自增公开课
        $open_less_arr = self::where(function($query) use ($where,$school_id){
            if(!empty($where['parent_id']) && $where['parent_id'] != '' && $where['parent_id'] > 0){
                $query->where('parent_id',$where['parent_id']);
            }
            if(!empty($where['child_id']) && $where['child_id'] != '' && $where['child_id'] > 0){
                $query->where('child_id',$where['child_id']);
            }
            if(!empty($where['status']) && $where['status'] != '' ){
                if($where['status'] == 1){
                    $query->where('status',0);
                }
                if($where['status'] == 2){
                    $query->where('status',1);
                }
                if($where['status'] == 3){
                    $query->where('status',2);
                }
            }
            if(!empty($where['time']) && $where['time'] != ''){
                $query->where('start_at','>',$where['start_at']);
                $query->where('end_at','<',$where['end_at']);
            }
            $query->where('school_id',$school_id);
            $query->where('is_del',0);
         })->get()->toArray();

        if($school_status <1){
            //授权公开课
            $ref_open_less_arr = CourseRefOpen::leftJoin('ld_course_open','ld_course_ref_open.course_id','=','ld_course_open.id')
            ->where(function($query) use ($where,$school_id){
                if(!empty($where['parent_id']) && $where['parent_id'] != '' && $where['parent_id'] >0){
                    $query->where('parent_id',$where['parent_id']);
                }
                if(!empty($where['child_id']) && $where['child_id'] != '' && $where['child_id'] > 0){
                    $query->where('child_id',$where['child_id']);
                }
                if(!empty($where['status']) && $where['status'] != '' ){
                    switch ($where['status']) {
                        case '1': $query->where('ld_course_ref_open.status',0);      break;
                        case '2': $query->where('ld_course_ref_open.status',1);      break;
                        case '3': $query->where('ld_course_ref_open.status',2);      break;
                    }
                }
                if(!empty($where['time']) && $where['time'] != ''){
                    $query->where('start_at','>',$where['start_at']);
                    $query->where('end_at','<',$where['end_at']);
                }
                $query->where('to_school_id',$school_id);
                $query->where('ld_course_ref_open.is_del',0);
            })->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_course_open.start_at','ld_course_open.end_at','ld_course_ref_open.is_recommend','ld_course_ref_open.status')
                ->orderBy('ld_course_ref_open.create_at','desc')->get()->toArray();
            if(!empty($ref_open_less_arr)){
                foreach($ref_open_less_arr as $kb=>&$vb){
                    $vb['nature'] = 2; //授权
                }
            }
        }
        if(!empty($open_less_arr)){
            foreach($open_less_arr as $ka=>&$va){
                $va['nature'] = 1; //自增
            }
        }
        switch ($nature) {
            case '1'://自增
               $openCourseArr = $open_less_arr;
                break;
             case '2':
                 $openCourseArr = $ref_open_less_arr;
                break;
            default:
               $openCourseArr = array_merge($open_less_arr,$ref_open_less_arr);
                break;
        }
        if(!empty($openCourseArr)){
            foreach ($openCourseArr as $k => &$v) {
                $v['time'] = [date('Y-m-d H:i:s',$v['start_at']),date('Y-m-d H:i:s',$v['end_at'])];
                $teacherIdArr = OpenCourseTeacher::where('course_id',$v['id'])->where('is_del',0)->get(['teacher_id']);
                $v['teacher_name'] = Teacher::whereIn('id',$teacherIdArr)->where('is_del',0)->where('type',2)->first()['real_name'];
            }
        }
        $start=($page-1)*$pagesize;
        $limit_s=$start+$pagesize;
        $data=[];
        for($i=$start;$i<$limit_s;$i++){
            if(!empty($openCourseArr[$i])){
                array_push($data,$openCourseArr[$i]);
            }
        }

        return ['code'=>200,'msg'=>'Success','data'=>['open_less_list' => $data , 'total' => count($openCourseArr)  ,'page'=>$page]];
    }


    /*
         * @param  descriptsion 获取公开课列表
         * @param  author       lys
         * @param  ctime   2020/4/29
         * return  array
         */
    public static function getListByIndexSet($body, $schoolId)
    {

        $topNum = empty($body['top_num']) ? 1 : (int)$body['top_num'];
        $isRecommend = empty($data['is_recommend']) ? 0 : $data['is_recommend'];

        $where['parent_id'] = !isset($body['coursesubjectOne'])|| empty($body['coursesubjectOne'])  ?'':$body['coursesubjectOne'];
        $where['child_id'] =  !isset($body['coursesubjectTwo']) || empty($body['coursesubjectTwo']) ?'':$body['coursesubjectTwo'];

        //自增公开课
        $openCourseQuery = self::query()
            ->where('school_id', $schoolId)
            ->where('status',1)
            ->where('is_del',0)
            ->where(function ($query) use ($where) {
                if(!empty($where['parent_id']) && $where['parent_id'] != '' && $where['parent_id'] > 0){
                    $query->where('parent_id',$where['parent_id']);
                }
                if(!empty($where['child_id']) && $where['child_id'] != '' && $where['child_id'] > 0){
                    $query->where('child_id',$where['child_id']);
                }
            });

        if ($isRecommend == 1) {
            $openCourseQuery->orderBy('is_recommend', 'desc');
        }

        $open_less_arr = $openCourseQuery->orderBy('id', 'desc')
            ->limit($topNum)
            ->get()
            ->toArray();

        //授权公开课
        $openCourseQuery = CourseRefOpen::query()
            ->leftJoin('ld_course_open','ld_course_ref_open.course_id','=','ld_course_open.id')
            ->where('to_school_id', $schoolId)
            ->where('ld_course_ref_open.is_del',0)
            ->where('ld_course_ref_open.status',1)
            ->where(function ($query) use ($where) {
                if(!empty($where['parent_id']) && $where['parent_id'] != '' && $where['parent_id'] >0){
                    $query->where('parent_id',$where['parent_id']);
                }
                if(!empty($where['child_id']) && $where['child_id'] != '' && $where['child_id'] > 0){
                    $query->where('child_id',$where['child_id']);
                }
            })
            ->select('ld_course_open.id','ld_course_open.title','ld_course_open.cover','ld_course_open.start_at','ld_course_open.end_at','ld_course_ref_open.is_recommend','ld_course_ref_open.status');
        if ($isRecommend == 1) {
            $openCourseQuery->orderBy('ld_course_open.is_recommend', 'desc');
        }

        $ref_open_less_arr = $openCourseQuery->orderBy('ld_course_ref_open.create_at','desc')
            ->limit($topNum)
            ->get()
            ->toArray();

        $openCourseArr = array_merge($open_less_arr,$ref_open_less_arr);

        if(!empty($openCourseArr)){
            foreach ($openCourseArr as $k => &$v) {
                $v['time'] = [date('Y-m-d H:i:s',$v['start_at']),date('Y-m-d H:i:s',$v['end_at'])];
                $teacherIdArr = OpenCourseTeacher::where('course_id',$v['id'])->where('is_del',0)->get(['teacher_id']);
                $v['teacher_name'] = Teacher::whereIn('id',$teacherIdArr)->where('is_del',0)->where('type',2)->first()['real_name'];
            }
        }
        $data=[];
        for($i=0; $i<$topNum; $i++){
            if(!empty($openCourseArr[$i])){
                array_push($data,$openCourseArr[$i]);
            }
        }

        return ['code'=>200,'msg'=>'Success','data'=> $data];
    }

}


