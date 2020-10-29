<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

// use App\Models\Teacher;
// use App\Models\Admin;
// use App\Models\CouresSubject;
// use App\Models\Coures;
// use App\Models\Couresmethod;

use App\Tools\CurrentAdmin;
class School extends Model {
    //指定别的表名
    public $table = 'ld_school';
    //时间戳设置
    public $timestamps = false;

    public function lessons() {
        return $this->belongsToMany('App\Models\Lesson', 'ld_lesson_schools', 'school_id');
    }

    public function admins() {
        return $this->hasMany('App\Models\Admin');
    }
    //错误信息
     public static function message(){

        return [
            'page.required'  => json_encode(['code'=>'201','msg'=>'页码不能为空']),
            'page.integer'   => json_encode(['code'=>'202','msg'=>'页码类型不合法']),
            'limit.required' => json_encode(['code'=>'201','msg'=>'显示条数不能为空']),
            'limit.integer'  => json_encode(['code'=>'202','msg'=>'显示条数类型不合法']),
            'type.required' => json_encode(['code'=>'201','msg'=>'分类类型不能为空']),
            'type.integer'  => json_encode(['code'=>'202','msg'=>'分类类型不合法']),
            'school_id.required' => json_encode(['code'=>'201','msg'=>'学校标识不能为空']),
            'school_id.integer'  => json_encode(['code'=>'202','msg'=>'学校标识类型不合法']),
            'name.required' => json_encode(['code'=>'201','msg'=>'学校名称不能为空']),
            'name.unique' => json_encode(['code'=>'205','msg'=>'学校名称已存在']),
            'dns.required' => json_encode(['code'=>'201','msg'=>'学校域名不能为空']),
            'logo_url.required' => json_encode(['code'=>'201','msg'=>'学校LOGO不能为空']),
            'introduce.required' => json_encode(['code'=>'201','msg'=>'学校简介不能为空']),
            'username.required' => json_encode(['code'=>'201','msg'=>'账号不能为空']),
            'username.unique' => json_encode(['code'=>'205','msg'=>'账号已存在']),
            'password.required' => json_encode(['code'=>'201','msg'=>'密码不能为空']),
            'pwd.required' => json_encode(['code'=>'201','msg'=>'确认密码不能为空']),
            'mobile.required' => json_encode(['code'=>'201','msg'=>'联系方式不能为空']),
            'mobile.regex' => json_encode(['code'=>'202','msg'=>'联系方式类型不合法']),
            'id.required' => json_encode(['code'=>'201','msg'=>'学校标识不能为空']),
            'id.integer'  => json_encode(['code'=>'202','msg'=>'学校标识类型不合法']),
            'user_id.required' => json_encode(['code'=>'201','msg'=>'用户标识不能为空']),
            'user_id.integer'  => json_encode(['code'=>'202','msg'=>'用户标识类型不合法']),
            'realname.required'=> json_encode(['code'=>'201','msg'=>'联系人不能为空']),
            'role_id.required' => json_encode(['code'=>'201','msg'=>'角色标识不能为空']),
            'role_id.integer'  => json_encode(['code'=>'202','msg'=>'角色标识类型不合法']),
            'is_public.required' => json_encode(['code'=>'201','msg'=>'是否为公开课标识不能为空']),
            'is_public.integer'  => json_encode(['code'=>'202','msg'=>'是否为公开课标识类型不合法']),
            //laoxian新增
            'live_price.numeric'  => json_encode(['code'=>'201','msg'=>'直播并发单价只能是数字']),
            'live_price.min'  => json_encode(['code'=>'202','msg'=>'直播并发单价不能小于0']),
            'storage_price.numeric'  => json_encode(['code'=>'201','msg'=>'空间单价只能是数字']),
            'storage_price.min'  => json_encode(['code'=>'202','msg'=>'空间单价不能小于0']),
            'flow_price.numeric'  => json_encode(['code'=>'201','msg'=>'流量单价只能是数字']),
            'flow_price.min'  => json_encode(['code'=>'202','msg'=>'流量单价不能小于0']),
            'ifinto.integer'  => json_encode(['code'=>'202','msg'=>'是否开启网校系统入口参数不合法']),
            'status.integer'  => json_encode(['code'=>'202','msg'=>'不合法的网校状态']),
            'stauts.required'  => json_encode(['code'=>'202','msg'=>'网校状态必须设置']),
            'cur_type.required'  => json_encode(['code'=>'201','msg'=>'配置类型不合法']),
            'cur_content.required'  => json_encode(['code'=>'201','msg'=>'配置内容不合法']),
            'is_forbid.required'  => json_encode(['code'=>'201','msg'=>'开启状态不合法']),
            'page_type.required'  => json_encode(['code'=>'201','msg'=>'类型不为空']),
            'title.required'  => json_encode(['code'=>'201','msg'=>'title必填']),
            'keywords.required'  => json_encode(['code'=>'201','msg'=>'keywords必填']),
            'description.required'  => json_encode(['code'=>'201','msg'=>'description必填']),
        ];


    }


    /*
         * @param  descriptsion 获取学校信息
         * @param  $school_id   学校id
         * @param  $field   字段列
         * @param  $page   页码
         * @param  $limit  显示条件
         * @param  author       lys
         * @param  ctime   2020/4/29
         * return  array
         */
    public static function getSchoolOne($where,$field = ['*']){
        $schoolInfo = self::where($where)->select($field)->first();
        if($schoolInfo){
            return ['code'=>200,'msg'=>'获取学校信息成功','data'=>$schoolInfo];
        }else{
            return ['code'=>204,'msg'=>'学校信息不存在'];
        }
    }
        /*
         * @param  descriptsion 获取学校信息
         * @param  $field   字段列
         * @param  author       lys
         * @param  ctime   2020/4/30
         * return  array
         */
    public static  function getSchoolAlls($field = ['*']){
        return  self::select($field)->get()->toArray();

    }

    /*
         * @param  分校列表
         * @param  author  苏振文
         * @param  ctime   2020/4/28 14:43
         * return  array
         */
    public static function SchoolAll($where=[],$field=['*']){
        $list = self::select($field)->where($where)->get()->toArray();
        return $list;
    }
    /*
         * @param  修改分校超级管理员信息
         * @param  author  lys
         * @param  ctime   2020/5/7
         * return  array
         */
    public static function doAdminUpdate($data){
        if(!$data || !is_array($data)){
             return ['code'=>202,'msg'=>'传参不合法'];
        }
        $update = [];
        if(isset($data['password']) && isset($data['pwd'])){
            if(strlen($data['password']) <8){
                return response()->json(['code'=>207,'msg'=>'密码长度不能小于8位']);
            }
            if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['password'])) {
                return response()->json(['code'=>207,'msg'=>'密码格式不正确，请重新输入']);
            }
            if(!empty($data['password'])|| !empty($data['pwd']) ){
               if($data['password'] != $data['pwd'] ){
                    return ['code'=>206,'msg'=>'两个密码不一致'];
                }else{
                    $update['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
            }
        }
        if(isset($data['realname']) && !empty($data['realname'])){
             $update['realname'] =  $data['realname'];
        }
        if(isset($data['mobile']) && !empty($data['mobile'])){
             $update['mobile'] =  $data['mobile'];
        }
        $result = Admin::where('id',$data['user_id'])->update($update);
        if($result){
            AdminLog::insertAdminLog([
                'admin_id'       =>   CurrentAdmin::user()['id'] ,
                'module_name'    =>  'School' ,
                'route_url'      =>  'admin/school/doAdminUpdate' ,
                'operate_method' =>  'update',
                'content'        =>  json_encode($data),
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code'=>200,'msg'=>'更新成功'];
        }else{
            return ['code'=>203,'msg'=>'更新失败'];
        }
    }

    /*
     * @param  获取分校讲师列表
     * @param  author  lys
     * @param  ctime   2020/5/7
     * return  array
     */
    public static function getSchoolTeacherList($data){
            $school= School::find($data['school_id']);  //获取学校信息
            $teacher = Teacher::where(['school_id'=>$data['school_id'],'is_del' =>0,'type'=>2])->select('id','head_icon','real_name','describe','school_id')->get()->toArray();//学校自己添加的讲师
            $natureTeacher = CourseRefTeacher::leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_ref_teacher.teacher_id')
                            ->where(['ld_lecturer_educationa.type'=>2,'ld_course_ref_teacher.to_school_id'=>$data['school_id'],'ld_course_ref_teacher.is_del'=>0])
                            ->select('ld_lecturer_educationa.id','ld_lecturer_educationa.head_icon','ld_lecturer_educationa.real_name','ld_lecturer_educationa.describe','ld_lecturer_educationa.school_id')
                            ->get()->toArray();

            if(!empty($teacher)){
                foreach($teacher as $key => &$v){
                    $v['school_status'] ='自增讲师';
                }
            }
             if(!empty($natureTeacher)){
                foreach($natureTeacher as $key => &$vv){
                    $vv['school_status'] ='授权讲师';
                }
            }

            $teacher = array_merge($teacher,$natureTeacher);
            $teacher = array_unique($teacher,SORT_REGULAR);
            $arr = [
                    'code'=>200,
                    'msg'=>'Success',
                    'data'=>[
                            'school'=>$school,
                            'teacher'=>$teacher
                        ]
            ];
            return $arr;
    }

    /*
     * @param  获取分校课程列表
     * @param  author  lys
     * @param  ctime   2020/6/24
     * return  array
     */  //7.4调整 暂时不考虑分页
    public static function getSchoolLessonList($data){
        if(!isset($data['school_id']) ||empty($data['school_id']) || $data['school_id'] <=0){
            return ['code'=>201,'msg'=>'学校标识为空或参数有误'];
        }
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登陆学校id
        $pagesize = (int)isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        $nature = !isset($data['nature']) || empty($data['nature']) ?'':$data['nature'];  //授权搜索 1 自增 2 授权
        $arr = [];
        $course = Coures::where(function($query) use ($data) {
                $query->where('school_id',$data['school_id']);
                //学科大类
                if(!empty($data['subjectOne']) && $data['subjectOne'] != ''){
                    $query->where('parent_id',$data['subjectOne']);
                }
                //学科小类
                if(!empty($data['subjectTwo']) && $data['subjectTwo'] != ''){
                    $query->where('child_id',$data['subjectTwo']);
                }
            })->select('id','title','cover','nature','status','pricing','school_id','id as course_id')
            ->orderBy('id','desc')->get()->toArray();//自增课程

        $natureCourse = CourseSchool::leftJoin('ld_course','ld_course.id','=','ld_course_school.course_id')
                        ->where(function($query) use ($data,$school_id) {
                            if(!empty($data['subjectOne']) && $data['subjectOne'] != ''){
                                $query->where('ld_course.parent_id',$data['subjectOne']);
                            }
                            if(!empty($data['subjectTwo']) && $data['subjectTwo'] != ''){
                                $query->where('ld_course.child_id',$data['subjectTwo']);
                            }
                            $query->where('ld_course_school.to_school_id',$data['school_id']);
                            $query->where('ld_course_school.from_school_id',$school_id);
                            $query->where('ld_course_school.is_del',0);
                })->select('ld_course_school.id','ld_course_school.title','ld_course_school.cover','ld_course_school.pricing','ld_course_school.status','ld_course_school.course_id')
                ->get()->toArray(); //授权课程信息（分校）

            if(!empty($course)){
                foreach($course as $key=>$va){
                    $course[$key]['nature'] = 0; //自增
                }
            }
            if(!empty($natureCourse)){
                foreach($natureCourse as $key=>$vb){
                    $natureCourse[$key]['nature'] = 1; //授权
                }
            }
            switch ($nature) {
                case '1':
                    $arr = $course;
                    break;
                 case '2':
                    $arr = $natureCourse;
                    break;
                default:
                    $arr = array_merge($course,$natureCourse);
                    break;
            }

            if(!empty($arr)){
                foreach($arr  as $k=>&$v){
                    $v['school_dns'] = School::where('id',$data['school_id'])->select('dns')->first()['dns'];
                    if($v['nature'] == 0){
                        $v['sum_nember'] = 0;
                        $v['buy_nember'] = Order::whereIn('pay_status',[3,4])->where('nature',0)->where(['school_id'=>$data['school_id'],'class_id'=>$v['id'],'status'=>2,'oa_status'=>1])->count();
                        $v['surplus'] = 0;
                    }
                    if($v['nature'] == 1){
                        $v['buy_nember'] = Order::whereIn('pay_status',[3,4])->where('nature',1)->where(['school_id'=>$data['school_id'],'class_id'=>$v['id'],'status'=>2,'oa_status'=>1])->count();
                        $v['sum_nember'] = CourseStocks::where(['school_pid'=>$school_id,'school_id'=>$data['school_id'],'course_id'=>$v['course_id'],'is_del'=>0])->sum('add_number');
                        $v['surplus'] = $v['sum_nember']-$v['buy_nember'] <=0 ?0:$v['sum_nember']-$v['buy_nember']; //剩余库存量
                    }
                    $where=[
                        'course_id'=>$v['course_id'],
                        'is_del'=>0
                    ];
                    if(!empty($data['type'])) {
                       $where['method_id'] = $data['type'];
                    }
                    $method = Couresmethod::select('method_id')->where($where)->get()->toArray();
                    if(!$method){
                        unset($arr[$k]);
                    }else{
                        foreach ($method as $key=>&$val){
                            if($val['method_id'] == 1){
                                $val['method_name'] = '直播';
                            }
                            if($val['method_id'] == 2){
                                $val['method_name'] = '录播';
                            }
                            if($val['method_id'] == 3){
                                $val['method_name'] = '其他';
                            }
                        }
                        $v['method'] = $method;
                    }
                }
            }

            $start=($page-1)*$pagesize;
            $limit_s=$start+$pagesize;
            $info=[];
            for($i=$start;$i<$limit_s;$i++){
                if(!empty($arr[$i])){
                    array_push($info,$arr[$i]);
                }
            }
            return ['code' => 200 , 'msg' => '查询成功','data'=>$info,'total'=>count($arr)];
        }



    /*
     * @param  获取网校公开课列表
     * @param  author  lys
     * @param  ctime   2020/7/4
     * return  array
     */
    public static function getOpenLessonList($data){
        $pagesize = (int)isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登陆学校id
        $nature = !isset($data['nature']) || empty($data['nature']) ?'':$data['nature'];  //授权搜索 1 自增 2 授权
        $arr = [];
        $openCourse = OpenCourse::where(function($query) use ($data) {//自增
                $query->where('school_id',$data['school_id']);
                //学科大类
                if(!empty($data['subjectOne']) && $data['subjectOne'] != ''){
                    $query->where('parent_id',$data['subjectOne']);
                }
                //学科小类
                if(!empty($data['subjectTwo']) && $data['subjectTwo'] != ''){
                    $query->where('child_id',$data['subjectTwo']);
                }
                $query->where('is_del',0);
            })->select('id','title','cover','status','school_id','start_at','end_at')
            ->orderBy('id','desc')
            ->get()->toArray();

        $natureOpenCourse = CourseRefOpen::leftJoin('ld_course_open','ld_course_open.id','=','ld_course_ref_open.course_id')
                            ->where(function($query) use ($data,$school_id) {
                                if(!empty($data['subjectOne']) && $data['subjectOne'] != ''){
                                    $query->where('ld_course_open.parent_id',$data['subjectOne']);
                                }
                                if(!empty($data['subjectTwo']) && $data['subjectTwo'] != ''){
                                    $query->where('ld_course_open.child_id',$data['subjectTwo']);
                                }
                                $query->where('ld_course_ref_open.to_school_id',$data['school_id']);
                                $query->where('ld_course_ref_open.from_school_id',$school_id);
                                $query->where('ld_course_ref_open.is_del',0);
                    })->select('ld_course_ref_open.course_id as id','ld_course_open.title','ld_course_open.cover','ld_course_ref_open.status','ld_course_open.start_at','ld_course_open.end_at')->get()->toArray(); //授权公开课信息（分校）
        if(!empty($openCourse)){
            foreach($openCourse as $key =>$v){
                $openCourse[$key]['nature'] = '自增课程';
            }
        }
        if(!empty($natureOpenCourse)){
            foreach($natureOpenCourse as $key =>$v){
                $natureOpenCourse[$key]['nature'] = '授权课程';
            }
        }
       switch ($nature) {
            case '1':
              $arr = $openCourse;
                break;
             case '2':
                $arr = $natureOpenCourse;
                break;
            default:
                $arr = array_merge($openCourse,$natureOpenCourse);
                break;
        }

        if(!empty($arr)){

            foreach($arr as $k =>$v){
                $arr[$k]['school_dns'] =  School::where('id',$data['school_id'])->select('dns')->first()['dns'];
                $watch_num = OpenLivesChilds::where('lesson_id',$v['id'])->where(['is_del'=>0])->select('watch_num')->first();
                $arr[$k]['watch_num'] = empty($watch_num) || $watch_num['watch_num'] <=0 ?0:$watch_num['watch_num'];
                $arr[$k]['start_at'] = date('Y-m-d H:i:s',$v['start_at']);
                $arr[$k]['end_at']   = date('Y-m-d H:i:s',$v['end_at']);
            }
        }
        $start=($page-1)*$pagesize;
        $limit_s=$start+$pagesize;
        $data=[];
        for($i=$start;$i<$limit_s;$i++){
            if(!empty($arr[$i])){
                array_push($data,$arr[$i]);
            }
        }
        return ['code' => 200 , 'msg' => '查询成功','data'=>$arr,'total'=>count($arr)];
    }
     /*
     * @param  获取学科列表
     * @param  author  lys
     * @param  ctime   2020/7/5
     * return  array
     */
    public static function getSubjectList($data){
        $arr = $subjectArr  = $newIdsArr = $subjectIdsArr = $subjectIdsData = $natureSubjectIdsData = [];
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登陆学校id
        $subjectIdsArr = $zizengSubject = CouresSubject::where(['school_id'=>$data['school_id'],'is_open'=>0,'is_del'=>0])->select('id','parent_id','subject_name')->get()->toArray();
        // if($data['is_public'] == 1){//公开课
            $OpenCourseNatureSubject = CourseRefOpen::leftJoin('ld_course_open','ld_course_open.id','=','ld_course_ref_open.course_id')
                            ->where(function($query) use ($data,$school_id) {
                                $query->where('ld_course_ref_open.to_school_id',$data['school_id']);
                                $query->where('ld_course_ref_open.from_school_id',$school_id);
                                $query->where('ld_course_ref_open.is_del',0);
                    })->select('ld_course_open.parent_id','ld_course_open.child_id')->get()->toArray(); //授权公开课信息（分校）
            // if(!empty($natureSubject)){
            //     $arr =array_unique($natureSubject, SORT_REGULAR);
            //     if(!empty($arr)){
            //         foreach($arr as $k=>$v){
            //             array_push($newIdsArr,$v['parent_id']);
            //             array_push($newIdsArr,$v['child_id']);
            //         }
            //         $newIdsArr = array_unique($newIdsArr);
            //         $natureSubjectIdsData = CouresSubject::whereIn('id',$newIdsArr)->where(['is_open'=>0,'is_del'=>0])->select('id','parent_id','subject_name')->get()->toArray();
            //     }
            // }
            // $subjectIdsArr = array_merge($natureSubjectIdsData,$zizengSubject);

            // if(!empty($subjectIdsArr)){
            //     $subjectIdsData = getParentsList($subjectIdsArr);
            // }
            // return ['code'=>200,'msg'=>'Success','data'=>$subjectIdsData];
        // }
        // if($data['is_public'] == 0){//课程

            $courseNatureSubject = CourseSchool::where(function($query) use ($data,$school_id) {
                                $query->where('to_school_id',$data['school_id']);
                                $query->where('from_school_id',$school_id);
                                $query->where('is_del',0);
                    })->select('parent_id','child_id')
                    ->get()->toArray(); //授权课程信息（分校）
            if(!empty($OpenCourseNatureSubject) && !empty($courseNatureSubject)){
                $natureSubject = array_merge($OpenCourseNatureSubject,$courseNatureSubject);
            }else{
                $natureSubject = !empty($OpenCourseNatureSubject) ?$OpenCourseNatureSubject:$courseNatureSubject;
            }
            if(!empty($natureSubject)){
                $arr =array_unique($natureSubject, SORT_REGULAR);

                // foreach($arr as $k=>$v){
                //      $subjectArr  = CourseRefSubject::where(['to_school_id'=>$data['school_id'],'is_del'=>0,'parent_id'=>$v['parent_id'],'child_id'=>$v['child_id']])->select('parent_id','child_id')->get()->toArray();
                // }

                if(!empty($arr)){
                    foreach($arr as $k=>$v){
                        array_push($newIdsArr,$v['parent_id']);
                        array_push($newIdsArr,$v['child_id']);
                    }
                    $newIdsArr = array_unique($newIdsArr);
                    $natureSubjectIdsData = CouresSubject::whereIn('id',$newIdsArr)->where(['is_open'=>0,'is_del'=>0])->select('id','parent_id','subject_name')->get()->toArray();
                }
            }

            $subjectIdsArr = array_merge($natureSubjectIdsData,$zizengSubject);

            if(!empty($subjectIdsArr)){
                $subjectIdsData = getParentsList($subjectIdsArr);
            }

            return ['code'=>200,'msg'=>'Success','data'=>$subjectIdsData];

        // }

    }


}


