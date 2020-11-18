<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

class CourseSchool extends Model {
    //指定别的表名
    public $table = 'ld_course_school';
    //时间戳设置
    public $timestamps = false;


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
            'course_id.required' => json_encode(['code'=>'201','msg'=>'课程标识不能为空']),
            'course_id.integer'  => json_encode(['code'=>'202','msg'=>'课程标识类型不合法']),
            'is_public.required' => json_encode(['code'=>'201','msg'=>'课程类型标识不能为空']),
            'subjectOne.required' => json_encode(['code'=>'201','msg'=>'学科大类标识不能为空']),
        ];
    }
    /**
     * @param  授权课程IDs
     * @param  school_id
     * @param  author  李银生
     * @param  ctime   2020/6/30
     * @return  array 7.4 调整
     */
    public static function courseIds($body){
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登陆学校id
        if($body['is_public'] == 1){ //公开课
            $openCourseIds['ids'] = CourseRefOpen::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->pluck('course_id');
            $openCourseIds['is_public'] = 1;
            return ['code'=>200,'msg'=>'Success','data'=>$openCourseIds];
        }
        if($body['is_public'] == 0){ //课程
            $courseIds['ids'] = self::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->pluck('course_id');
            $courseIds['is_public'] = 0;
            return ['code'=>200,'msg'=>'Success','data'=>$courseIds];
        }
    }

   /**
     * @param  授权课程列表
     * @param  school_id
     * @param  author  李银生
     * @param  ctime   2020/6/30
     * @return  array 7.4 调整
     */
    public static function courseList($body){
    	$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登陆学校id
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0; //当前登陆学校id
        $OpenCourseArr =   $natureOpenCourse = $courseArr = $natureCourseArr = [];
        $zizengSubjectArr = CouresSubject::where('school_id',$school_id)->where(['is_open'=>0,'is_del'=>0])->select('id','subject_name')->get()->toArray();//自增大类小类（总校）
        $subjectArr = array_column($zizengSubjectArr,'subject_name','id');
        $schoolArr =Admin::where(['school_id'=>$body['school_id'],'is_del'=>1])->first();
        if($body['is_public'] == 1){//公开课
            $zizengOpenCourse = OpenCourse::where(function($query) use ($body,$school_id) {
                            if(!empty($body['subjectOne']) && $body['subjectOne'] != ''){
                                $query->where('parent_id',$body['subjectOne']);
                            }
                            if(!empty($body['subjectTwo']) && $body['subjectTwo'] != ''){
                                $query->where('child_id',$body['subjectTwo']);
                            }
                            if(!empty($body['search']) && $body['search'] != ''){
                                $query->where('title','like',"%".$body['search']."%");
                            }
                            $query->where('is_del',0);
                            $query->where('school_id',$school_id);
                        })->select('id','parent_id','child_id','title')->get()->toArray();//自增公开课信息（总校）
            $natureOpenCourse = CourseRefOpen::leftJoin('ld_course_open','ld_course_open.id','=','ld_course_ref_open.course_id')
                            ->where(function($query) use ($body,$school_id) {
                                if(!empty($body['subjectOne']) && $body['subjectOne'] != ''){
                                    $query->where('ld_course_open.parent_id',$body['subjectOne']);
                                }
                                if(!empty($body['subjectTwo']) && $body['subjectTwo'] != ''){
                                    $query->where('ld_course_open.child_id',$body['subjectTwo']);
                                }
                                if(!empty($body['search']) && $body['search'] != ''){
                                    $query->where('ld_course_open.title','like',"%".$body['search']."%");
                                }
                                $query->where('ld_course_ref_open.to_school_id',$body['school_id']);
                                $query->where('ld_course_ref_open.from_school_id',$school_id);
                                $query->where('ld_course_ref_open.is_del',0);
                        })->select('ld_course_ref_open.course_id as id','ld_course_open.parent_id','ld_course_open.child_id','ld_course_open.title')->get()->toArray(); //授权公开课信息（分校）

            if(!empty($zizengOpenCourse)&&!empty($natureOpenCourse)){
                foreach($natureOpenCourse as $k=>$r){
                    array_merge($zizengOpenCourse,$natureOpenCourse[$k]);
                }
                $OpenCourseArr = $zizengOpenCourse;
            }else{
                $OpenCourseArr = !empty($zizengOpenCourse)?$zizengOpenCourse:$natureOpenCourse;
            }
            if(!empty($OpenCourseArr)){
                $OpenCourseArr = array_unique($OpenCourseArr, SORT_REGULAR);
                foreach ($OpenCourseArr as $key => $v) {
                    $OpenCourseArr[$key]['subjectNameOne'] = !isset($subjectArr[$v['parent_id']])?'':$subjectArr[$v['parent_id']];
                    $OpenCourseArr[$key]['subjectNameTwo'] = !isset($subjectArr[$v['child_id']])?'':$subjectArr[$v['child_id']];
                    $OpenCourseArr[$key]['method'] = ['直播'];
                }
            }
            return ['code'=>200,'msg'=>'message','data'=>$OpenCourseArr];
        }
        if($body['is_public'] == 0){//课程
            $CourseArr =  $natureCourse = $zizengCourse = [];
            $zizengCourse = Coures::where(['school_id'=>$school_id,'nature'=>0])  //自增课程(总校)
                ->where(function($query) use ($body) {
                    if(!empty($body['subjectOne']) && $body['subjectOne'] != ''){
                        $query->where('parent_id',$body['subjectOne']);
                    }
                    if(!empty($body['subjectTwo']) && $body['subjectTwo'] != ''){
                        $query->where('child_id',$body['subjectTwo']);
                    }
                    if(!empty($body['search']) && $body['search'] != ''){
                        $query->where('title','like',"%".$body['search']."%");
                    }
                    $query->where('is_del',0);
                })->select('id','parent_id','child_id','title')->get()->toArray();
            $natureCourse = self::leftJoin('ld_course','ld_course.id','=','ld_course_school.course_id')
                ->where(function($query) use ($body,$school_id) {
                    if(!empty($body['subjectOne']) && $body['subjectOne'] != ''){
                        $query->where('ld_course.parent_id',$body['subjectOne']);
                    }
                    if(!empty($body['subjectTwo']) && $body['subjectTwo'] != ''){
                        $query->where('ld_course.child_id',$body['subjectTwo']);
                    }
                    if(!empty($body['search']) && $body['search'] != ''){
                        $query->where('ld_course.title','like',"%".$body['search']."%");
                    }
                    $query->where('ld_course_school.to_school_id',$body['school_id']); //被授权学校
                    $query->where('ld_course_school.from_school_id',$school_id); //授权学校
                    $query->where('ld_course_school.is_del',0);
            })->select('ld_course_school.course_id as id','ld_course.parent_id','ld_course.child_id','ld_course.title')->get()->toArray();
            //授权课程
                if(!empty($natureCourse)&&!empty($zizengCourse)){
                    foreach($natureCourse as $k=>$r){
                        array_merge($zizengCourse,$natureCourse[$k]);
                    }
                    $CourseArr = $zizengCourse;
                }else{
                    $CourseArr = !empty($zizengCourse)?$zizengCourse:$natureCourse;
                }
            if(!empty($CourseArr)){
                $CourseArr = array_unique($CourseArr, SORT_REGULAR);

                foreach ($CourseArr as $key => $v) {
                    $CourseArr[$key]['subjectNameOne'] = !isset($subjectArr[$v['parent_id']])?'':$subjectArr[$v['parent_id']];
                    $CourseArr[$key]['subjectNameTwo'] = !isset($subjectArr[$v['child_id']])?'':$subjectArr[$v['child_id']];
                    $method = Couresmethod::select('method_id')->where(['course_id'=>$v['id'],'is_del'=>0])->get()->toArray();
                    if(!$method){
                        unset($CourseArr[$key]);
                    }else{
                        $methodArr = [];
                        foreach ($method as $k=>&$val){
                            if($val['method_id'] == 1){
                                $val['method_name'] = '直播';
                            }
                            if($val['method_id'] == 2){
                                $val['method_name'] = '录播';
                            }
                            if($val['method_id'] == 3){
                                $val['method_name'] = '其他';
                            }
                            array_push($methodArr,$val['method_name']);
                        }
                      $CourseArr[$key]['method'] = $methodArr;
                    }
                }
            }
            $CourseArr = array_values($CourseArr);

            return ['code'=>200,'msg'=>'message','data'=>$CourseArr];
        }
    }
     /**
     * @param  批量授权
     * @param  school_id
     * @param  author  李银生
     * @param  ctime   2020/6/30
     * @return  array
     */
    public static function store($body){

        $arr = $subjectArr = $bankids = $questionIds = $InsertTeacherRef = $InsertSubjectRef = $InsertRecordVideoArr = $InsertZhiboVideoArr = $InsertQuestionArr = $teacherIdArr = [];
        // $courseIds=$body['course_id'];
    	// $courseIds = explode(',',$body['course_id']);
        $courseIds = array_unique(json_decode($body['course_id'],1)); //前端传值

        $searchs = [
            'parentid'=>isset($body['parentid'])?$body['parentid']:0,
            'childid'=>isset($body['childid'])?$body['childid']:0,
            'search'=>isset($body['search'])?$body['search']:0,
        ];
        if(empty($courseIds)){
            //课程id组为空, 全部取消[所有课程或当前搜索条件下的课程]授权
            $return = self::AllCancalCourseSchool($body['school_id'],$searchs);
            return $return;
            //return ['code'=>205,'msg'=>'请选择授权课程'];
        }
    	$school_id = 1;//isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前学校id
        $school_status = 0;//isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0; //当前登陆学校id
    	$user_id = 0;//isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0; //当前登录的用户id
        $schoolArr =Admin::where(['school_id'=>$body['school_id'],'is_del'=>1])->first();

        if($body['school_id'] == $school_id){
            return ['code'=>205,'msg'=>'自己不能给自己授权'];
        }
        if($schoolArr['school_status'] > $school_status){
            return ['code'=>205,'msg'=>'分校不能给总校授权'];
        }


        if($body['is_public'] == 1){ //公开课

            $natureOpenIds = CourseRefOpen::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->pluck('course_id')->toArray();
            $natureOpenIds = array_unique($natureOpenIds);
            if(!empty($natureOpenIds)){
                $courseIds = array_diff($courseIds,$natureOpenIds);
            }

            $ids = OpenCourseTeacher::whereIn('course_id',$courseIds)->where('is_del',0)->pluck('teacher_id')->toArray(); //要授权的教师信息

            if(!empty($ids)){

                $ids = array_unique($ids);
                $teacherIds = CourseRefTeacher::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0,'is_public'=>1])->pluck('teacher_id')->toArray();//已经授权过的讲师信息
                if(!empty($teacherIds)){
                    $teacherIdArr = array_diff($ids,$teacherIds);//不在授权讲师表里的数据
                }else{
                    $teacherIdArr = $ids;
                }
                if(!empty($teacherIdArr)){
                    foreach($teacherIdArr as $key => $id){
                        $InsertTeacherRef[$key]['from_school_id'] =$school_id;
                        $InsertTeacherRef[$key]['to_school_id'] =$body['school_id'];
                        $InsertTeacherRef[$key]['teacher_id'] =$id;
                        $InsertTeacherRef[$key]['is_public'] =1;
                        $InsertTeacherRef[$key]['admin_id'] = $user_id;
                        $InsertTeacherRef[$key]['create_at'] = date('Y-m-d H:i:s');
                    }
                }
            }

            $natureSubject = OpenCourse::where(function($query) use ($school_id,$courseIds) {
                                $query->whereIn('id',$courseIds);
                                $query->where('school_id',$school_id);
                                $query->where('is_del',0);
                        })->select('parent_id','child_id')->get()->toArray(); //要授权的学科信息
            $natureSubject = array_unique($natureSubject,SORT_REGULAR);

            $subjectArr = CourseRefSubject::where(['to_school_id'=>$body['school_id'],'from_school_id'=>$school_id,'is_del'=>0,'is_public'=>1])->select('parent_id','child_id')->get()->toArray();//已经授权的学科信息

            if(!empty($subjectArr)){
                foreach($natureSubject as $k=>$v){
                    foreach($subjectArr as $kk=>$bv){
                        if($v == $bv){
                            unset($natureSubject[$k]);
                        }
                    }
                }
            }

            if(!empty($natureSubject)){
                foreach($natureSubject as $key=>&$vs){
                    $vs['is_public'] = 1;
                    $vs['from_school_id'] =$school_id;
                    $vs['to_school_id'] =$body['school_id'];
                    $vs['admin_id'] =$user_id;
                    $vs['create_at'] =date('Y-m-d H:i:s');
                }
            }
            if(!empty($courseIds)){
                foreach($courseIds as $k=>$vv){
                    $refOpenInsert[$k]['admin_id'] = $user_id;
                    $refOpenInsert[$k]['from_school_id'] =$school_id;
                    $refOpenInsert[$k]['to_school_id'] =$body['school_id'];
                    $refOpenInsert[$k]['course_id'] = $vv;
                    $refOpenInsert[$k]['create_at'] =date('Y-m-d H:i:s');
                }
            }

            DB::beginTransaction();
            try{
                if(!empty($InsertTeacherRef)){
                    $teacherRes = CourseRefTeacher::insert($InsertTeacherRef);
                    if(!$teacherRes){
                        DB::rollback();
                        return ['code'=>203,'msg'=>'公开课授权未成功'];
                    }
                }
                if(!empty($natureSubject)){
                    $subjectRes = CourseRefSubject::insert($natureSubject);
                    if(!$subjectRes){
                        DB::rollback();
                        return ['code'=>203,'msg'=>'公开课授权未成功！'];
                    }
                }

                if(!empty($refOpenInsert)){
                    $refOpenRes = CourseRefOpen::insert($refOpenInsert);
                    if(!$refOpenRes){
                        DB::rollback();
                        return ['code'=>203,'msg'=>'公开课授权未成功！！'];
                    }

                }
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $user_id ,
                    'module_name'    =>  'Courschool' ,
                    'route_url'      =>  'admin/courschool/courseStore' ,
                    'operate_method' =>  'update',
                    'content'        =>  json_encode(array_merge($body,$InsertTeacherRef,$natureSubject,$refOpenInsert)),
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return ['code'=>200,'msg'=>'公开课授权成功！'];

            } catch (\Exception $e) {
                DB::rollback();
                return ['code' => 500 , 'msg' => $e->getMessage()];
            }
        }

        if($body['is_public'] == 0){  //课程

            $natureIds = self::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->pluck('course_id')->toArray();
            $natureIds = array_unique($natureIds);
            $courseIds = array_diff($courseIds, $natureIds);
            $course = Coures::whereIn('id',$courseIds)->where(['is_del'=>0])->select('parent_id','child_id','title','keywords','cover','pricing','sale_price','buy_num','expiry','describe','introduce','status','watch_num','is_recommend','id as course_id','school_id as from_school_id')->get()->toArray();//要授权课程 所有信息
            if(!empty($course)){
                foreach($course as $key=>&$vv){
                    $vv['from_school_id'] = $school_id;
                    $vv['to_school_id'] = $body['school_id'];
                    $vv['admin_id'] = $user_id;
                    $vv['create_at'] = date('Y-m-d H:i:s');
                    $courseSubjectArr[$key]['parent_id'] = $vv['parent_id'];
                    $courseSubjectArr[$key]['child_id'] = $vv['child_id'];
                }//授权课程信息
                $ids = Couresteacher::whereIn('course_id',$courseIds)->where('is_del',0)->pluck('teacher_id')->toArray(); //要授权的教师信息
                if(!empty($ids)){
                    $ids = array_unique($ids);
                    $teacherIds = CourseRefTeacher::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0,'is_public'=>0])->pluck('teacher_id')->toArray();//已经授权过的讲师信息
                    if(!empty($teacherIds)){
                        $teacherIdArr = array_diff($ids,$teacherIds);//不在授权讲师表里的数据
                    }else{
                        $teacherIdArr = $ids;
                    }
                    if(!empty($teacherIdArr)){
                        foreach($teacherIdArr as $key => $id){
                            $InsertTeacherRef[$key]['from_school_id'] =$school_id;
                            $InsertTeacherRef[$key]['to_school_id'] =$body['school_id'];
                            $InsertTeacherRef[$key]['teacher_id'] =$id;
                            $InsertTeacherRef[$key]['is_public'] =0;
                            $InsertTeacherRef[$key]['admin_id'] = $user_id;
                            $InsertTeacherRef[$key]['create_at'] = date('Y-m-d H:i:s');
                        }
                    }
                }
                //学科
                $courseSubjectArr = array_unique($courseSubjectArr,SORT_REGULAR);
                $subjectArr = CourseRefSubject::where(['to_school_id'=>$body['school_id'],'from_school_id'=>$school_id,'is_del'=>0,'is_public'=>0])->select('parent_id','child_id')->get()->toArray();  //已经授权过的学科
                if(!empty($subjectArr)){
                    foreach($courseSubjectArr as $k=>$v){
                        foreach($subjectArr as $kk=>$bv){
                            if($v == $bv){
                                unset($courseSubjectArr[$k]);
                            }
                        }
                    }
                }

                foreach($courseSubjectArr  as $key=>$v){
                        $InsertSubjectRef[$key]['is_public'] = 0;
                        $InsertSubjectRef[$key]['parent_id'] = $v['parent_id'];
                        $InsertSubjectRef[$key]['child_id'] = $v['child_id'];
                        $InsertSubjectRef[$key]['from_school_id'] = $school_id;
                        $InsertSubjectRef[$key]['to_school_id'] = $body['school_id'];
                        $InsertSubjectRef[$key]['admin_id'] = $user_id;
                        $InsertSubjectRef[$key]['create_at'] = date('Y-m-d H:i:s');
                }

                //录播资源
                $recordVideoIds = Coureschapters::whereIn('course_id',$courseIds)->where(['is_del'=>0])->pluck('resource_id as id')->toArray(); //要授权的录播资源
                if(!empty($recordVideoIds)){
                    $narturecordVideoIds = CourseRefResource::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'type'=>0,'is_del'=>0])->pluck('resource_id as id ')->toArray(); //已经授权过的录播资源
                    $recordVideoIds = array_diff($recordVideoIds,$narturecordVideoIds);
                    foreach ($recordVideoIds as $key => $v) {
                        $InsertRecordVideoArr[$key]['resource_id']=$v;
                        $InsertRecordVideoArr[$key]['from_school_id'] = $school_id;
                        $InsertRecordVideoArr[$key]['to_school_id'] = $body['school_id'];
                        $InsertRecordVideoArr[$key]['admin_id'] = $user_id;
                        $InsertRecordVideoArr[$key]['type'] = 0;
                        $InsertRecordVideoArr[$key]['create_at'] = date('Y-m-d H:i:s');
                    }
                }

                //直播资源
                $zhiboVideoIds = CourseLivesResource::whereIn('course_id',$courseIds)->where(['is_del'=>0])->pluck('id')->toArray();//要授权的直播资源
                if(!empty($zhiboVideoIds)){
                    $narturezhiboVideoIds = CourseRefResource::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'type'=>1,'is_del'=>0])->pluck('resource_id as id ')->toArray();
                    $zhiboVideoIds = array_diff($zhiboVideoIds,$narturezhiboVideoIds);
                    foreach ($zhiboVideoIds as $key => $v) {
                        $InsertZhiboVideoArr[$key]['resource_id']=$v;
                        $InsertZhiboVideoArr[$key]['from_school_id'] = $school_id;
                        $InsertZhiboVideoArr[$key]['to_school_id'] = $body['school_id'];
                        $InsertZhiboVideoArr[$key]['admin_id'] = $user_id;
                        $InsertZhiboVideoArr[$key]['type'] = 1;
                        $InsertZhiboVideoArr[$key]['create_at'] = date('Y-m-d H:i:s');
                    }
                }

                //题库
                foreach($courseSubjectArr as $key=>&$vs){
                    $bankIdArr = QuestionBank::where(['parent_id'=>$vs['parent_id'],'child_id'=>$vs['child_id'],'is_del'=>0,'school_id'=>$school_id])->pluck('id')->toArray();
                    if(!empty($bankIdArr)){
                        foreach($bankIdArr as $k=>$vb){
                            array_push($bankids,$vb);
                        }
                    }
                }
                if(!empty($bankids)){
                    $bankids=array_unique($bankids);
                    $natureQuestionBank = CourseRefBank::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->pluck('bank_id')->toArray();
                    $bankids = array_diff($bankids,$natureQuestionBank);
                    foreach($bankids as $key=>$bankid){
                        $InsertQuestionArr[$key]['bank_id'] =$bankid;
                        $InsertQuestionArr[$key]['from_school_id'] = $school_id;
                        $InsertQuestionArr[$key]['to_school_id'] = $body['school_id'];
                        $InsertQuestionArr[$key]['admin_id'] = $user_id;
                        $InsertQuestionArr[$key]['create_at'] = date('Y-m-d H:i:s');
                    }
                }
                DB::beginTransaction();
                try{
                    $teacherRes = CourseRefTeacher::insert($InsertTeacherRef);//教师
                    if(!$teacherRes){
                        DB::rollback();
                        return ['code'=>203,'msg'=>'教师授权未成功'];
                    }
                    $subjectRes = CourseRefSubject::insert($InsertSubjectRef);//学科
                    if(!$subjectRes){
                        DB::rollback();
                        return ['code'=>203,'msg'=>'学科授权未成功！'];
                    }

                    if(!empty($InsertRecordVideoArr)){
                        $InsertRecordVideoArr = array_chunk($InsertRecordVideoArr,500);
                        foreach($InsertRecordVideoArr as $key=>$lvbo){
                            $recordRes = CourseRefResource::insert($lvbo); //录播
                            if(!$recordRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'录播资源授权未成功！'];
                            }
                        }
                    }

                    if(!empty($InsertZhiboVideoArr)){
                        $InsertZhiboVideoArr = array_chunk($InsertZhiboVideoArr,500);

                        foreach($InsertZhiboVideoArr as $key=>$zhibo){
                            $zhiboRes = CourseRefResource::insert($zhibo); //直播
                            if(!$zhiboRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'直播资源授权未成功！'];
                            }
                        }
                    }

                    $bankRes = CourseRefBank::insert($InsertQuestionArr); //题库
                    if(!$bankRes){
                        DB::rollback();
                        return ['code'=>203,'msg'=>'题库授权未成功！'];
                    }

                    $courseRes = self::insert($course); //
                    if(!$courseRes){
                        AdminLog::insertAdminLog([
                            'admin_id'       =>   $user_id ,
                            'module_name'    =>  'Courschool' ,
                            'route_url'      =>  'admin/courschool/courseStore' ,
                            'operate_method' =>  'update',
                            'content'        =>  '课程授权'.json_encode($body),
                            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                            'create_at'      =>  date('Y-m-d H:i:s')
                        ]);
                        DB::rollback();
                        return ['code'=>203,'msg'=>'课程资源授权未成功！'];
                    }else{
                        //授权执行完毕, 执行取消授权
                        $new_arr = [
                            'school_id'=>$body['school_id'],
                            'course_id'=>$body['course_id'],
                        ];
                        $return = self::multiCancalCourseSchool($new_arr,$searchs);
                        if($return && isset($return) && $return['code']==200){
                            DB::commit();
                            return ['code'=>200,'msg'=>'课程授权成功'];
                        }else{
                            DB::rollBack();
                            return $return;
                        }
                        //////加入 授权课程时给反选的课程取消授权end

                    }

                } catch (\Exception $e) {
                    DB::rollback();
                    return ['code' => 500 , 'msg' => $e->getMessage()];
                }
            }else{
                //加一个else, 用于执行取消授权反选的课程
                DB::beginTransaction();
                try{
                    $new_arr = [
                        'school_id'=>$body['school_id'],
                        'courseids'=>$body['course_id'],
                    ];
                    $return = self::multiCancalCourseSchool($new_arr,$searchs);
                    if($return && isset($return) && $return['code']==200){
                        DB::commit();
                        return ['code'=>200,'msg'=>'课程授权成功'];
                    }else{
                        DB::rollBack();
                        return $return;
                    }
                }catch(\Exception $e){
                    DB::rollBack();
                    return ['code'=>209,'msg'=>'课程取消授权出现意外'.$e->getMessage() .': '.$e->getLine()];
                }
            }
        }
    }
    //授权课程列表学科大类
    public static function getNatureSubjectOneByid($data){

        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登陆学校id
        $ids= [];
        $ids = $zongSubjectIds = CouresSubject::where(['parent_id'=>0,'school_id'=>$school_id,'is_open'=>0,'is_del'=>0])->pluck('id')->toArray();//总校自增学科大类
        if($data['is_public'] == 1){//公开课
            $natureSujectIds = CourseRefOpen::leftJoin('ld_course_open','ld_course_open.id','=','ld_course_ref_open.course_id')
                            ->where(function($query) use ($data,$school_id) {
                                $query->where('ld_course_ref_open.to_school_id',$data['school_id']);
                                $query->where('ld_course_ref_open.from_school_id',$school_id);
                                $query->where('ld_course_ref_open.is_del',0);
                            })->pluck('ld_course_open.parent_id')->toArray();
        }
        if($data['is_public'] == 0 ){//课程
            $natureSujectIds = CourseSchool::leftJoin('ld_course','ld_course.id','=','ld_course_school.course_id')
                            ->where(function($query) use ($data,$school_id) {
                                $query->where('ld_course_school.to_school_id',$data['school_id']);
                                $query->where('ld_course_school.from_school_id',$school_id);
                                $query->where('ld_course_school.is_del',0);
                             })->pluck('ld_course.parent_id')->toArray();
        }

        if(!empty($natureSujectIds)){
           $natureSujectIds = array_unique($natureSujectIds);
           $ids = array_merge($zongSubjectIds,$natureSujectIds);
        }
        $subjectOneArr = CouresSubject::whereIn('id',$ids)->where(['is_open'=>0,'is_del'=>0])->select('id','subject_name')->get();
        return ['code'=>200,'msg'=>'Success','data'=>$subjectOneArr];
    }
    //授权课程列表小类
    public static function getNatureSubjectTwoByid($data){
        $subjectTwoArr = CouresSubject::where(['parent_id'=>$data['subjectOne'],'is_del'=>0,'is_open'=>0])->select('id','subject_name')->get();
        return ['code'=>200,'msg'=>'Success','data'=>$subjectTwoArr];
    }
   /**
    * @param  取消授权
    * @param  school_id
    * @param  author  李银生
    * @param  ctime   2020/6/30
    * @return  array
   */
    public static function courseCancel($body){
        $arr = $subjectArr = $bankids = $questionIds = $updateTeacherArr = $updateSubjectArr = $updatelvboArr = $updatezhiboArr = $updateBank = $teacherIdArr =$nonatureCourseId =    $noNatuerTeacher_ids =  [];
        //$courseIds=$body['course_id'];
        // $courseIds = explode(',',$body['course_id']);
        // $courseIds = json_decode($body['course_id'],1); //前端传值
        $courseIds =[$body['course_id']];
        if(empty($courseIds)){
           return ['code'=>205,'msg'=>'请选择取消授权课程'];
        }
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前学校id
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0; //当前登录学校的状态
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0; //当前登录的用户id
        $schoolArr =Admin::where(['school_id'=>$body['school_id'],'is_del'=>1])->first(); //前端传学校的id
        if($body['is_public'] == 1){
               //公开课
            $nature = CourseRefOpen::whereIn('course_id',$courseIds)->where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->pluck('course_id')->toArray(); //要取消的授权的课程

            if(empty($nature)){
                return ['code'=>207,'msg'=>'课程已经取消授权'];
            }

            $nature =OpenCourse::whereIn('id',$nature)->select('parent_id','child_id')->get()->toArray();
            foreach ($nature  as $kk => $vv) {
               $natureCourseArr[$kk]['parent_id'] = $vv['parent_id'];
               $natureCourseArr[$kk]['child_id'] = $vv['child_id'];
            }
            $noNatureCourse = CourseRefOpen::whereNotIn('course_id',$courseIds)->where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->pluck('course_id')->toArray();//除取消授权课程的信息
            if(!empty($noNatureCourse)){
                $noNatureCourse =OpenCourse::whereIn('id',$noNatureCourse)->select('id','parent_id','child_id')->get()->toArray();
                foreach($noNatureCourse as $k=>$v){
                     $noNaturecourseSubjectArr[$k]['parent_id'] = $v['parent_id'];
                     $noNaturecourseSubjectArr[$k]['child_id'] = $v['child_id'];
                     array_push($nonatureCourseId,$v['id']);
                }
            }

            //要取消的教师信息
            $teachers_ids = OpenCourseTeacher::whereIn('course_id',$courseIds)->where(['is_del'=>0])->pluck('teacher_id')->toArray(); //要取消授权的教师信息
            if(!empty($nonatureCourseId)){
                $noNatuerTeacher_ids  =  OpenCourseTeacher::whereIn('course_id',$nonatureCourseId)->where(['is_del'=>0])->pluck('teacher_id')->toArray(); //除取消授权的教师信息
            }
            $refTeacherArr  = CourseRefTeacher::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0,'is_public'=>1])->pluck('teacher_id')->toArray(); //现已经授权过的教师
            if(!empty($refTeacherArr)){
               $teachers_ids = array_unique($teachers_ids);
               if(!empty($noNatuerTeacher_ids)){
                    $noNatuerTeacher_ids = array_unique($noNatuerTeacher_ids);
                    $noNatuerTeacher_ids = array_intersect($refTeacherArr,$noNatuerTeacher_ids);
                    $arr = array_diff($teachers_ids,$noNatuerTeacher_ids);
                    if(!empty($arr)){
                       $updateTeacherArr = array_intersect($arr,$refTeacherArr);
                    }
                }else{
                   $updateTeacherArr = array_intersect($teachers_ids,$refTeacherArr); //$updateTecherArr 要取消授权的讲师信息
                }

            }
            if(!empty($noNaturecourseSubjectArr)){
                $noBankSubjectArr  = $noNaturecourseSubjectArr = array_unique($noNaturecourseSubjectArr,SORT_REGULAR);//除取消授权的学科信息
            }
            $bankSubjectArr = $natureCourseArr = array_unique($natureCourseArr,SORT_REGULAR);//要取消授权的学科信息
            $natureSubjectIds = CourseRefSubject::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0,'is_public'=>1])->select('parent_id','child_id')->get()->toArray();//已经授权过的学科信息
            if(!empty($natureSubjectIds)){
               $natureSubjectIds = array_unique($natureSubjectIds,SORT_REGULAR);
                if(!empty($noNaturecourseSubjectArr)){
                    foreach ($natureCourseArr as $ka => $va) {
                        foreach($noNaturecourseSubjectArr as $kb =>$vb){
                           if($va == $vb){
                                unset($natureCourseArr[$ka]);
                               //要取消的学科信息
                           }
                        }
                    }
                    if(!empty($natureCourseArr)){
                        foreach ($natureCourseArr as $ks => $vs) {
                            foreach($natureSubjectIds as$kn=>$vn ){
                                if($vs == $vn){
                                    unset($natureCourseArr[$ks]);
                                }
                            }
                        }
                       $updateSubjectArr = $natureCourseArr;
                    }

                }else{
                    foreach ($natureCourseArr as $ks => $vs) {
                        foreach($natureSubjectIds as$kn=>$vn ){
                            if($vs == $vn){
                               unset($natureCourseArr[$ks]);   //要取消的学科信息
                            }
                        }
                    }
                    $updateSubjectArr = $natureCourseArr;
               }
            }
            DB::beginTransaction();
            try{
                $updateTime = date('Y-m-d H:i:s');
                if(!empty($updateTeacherArr)){
                   foreach ($updateTeacherArr as $k => $vt) {
                       $teacherRes =CourseRefTeacher::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'teacher_id'=>$vt,'is_public'=>1])->update(['is_del'=>1,'update_at'=>$updateTime]);
                       if(!$teacherRes){
                           DB::rollback();
                           return ['code'=>203,'msg'=>'教师取消授权未成功'];
                       }
                   }
                }
                if(!empty($updateSubjectArr)){
                   $updateSubjectArr = array_unique($updateSubjectArr,SORT_REGULAR);
                   foreach ($updateSubjectArr as $k => $vs) {
                       $subjectRes =CourseRefSubject::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'parent_id'=>$vs['parent_id'],'child_id'=>$vs['child_id'],'is_public'=>1])->update(['is_del'=>1,'update_at'=>$updateTime]);
                       if(!$subjectRes){
                           DB::rollback();
                           return ['code'=>203,'msg'=>'学科取消授权未成功'];
                       }
                   }
                }
                if(!empty($courseIds)){
                    foreach ($courseIds as $key => $vc) {
                        $courseRes =CourseRefOpen::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'course_id'=>$vc])->update(['is_del'=>1,'update_at'=>$updateTime]);
                        if(!$courseRes){
                            DB::rollback();
                            return ['code'=>203,'msg'=>'公开课取消授权未成功'];
                        }
                    }
                }
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $user_id ,
                    'module_name'    =>  'Courschool' ,
                    'route_url'      =>  'admin/courschool/courseCancel' ,
                    'operate_method' =>  'update',
                    'content'        =>  '公开课取消授权'.json_encode(array_merge($body,$updateTeacherArr,$updateSubjectArr,$courseIds)),
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return ['code'=>200,'msg'=>'公开课课程取消授权成功'];

            } catch (\Exception $e) {
                DB::rollBack();
                return ['code' => 500 , 'msg' => $e->getMessage()];
            }
        }
        if($body['is_public'] == 0){
           //课程
            $natureData = self::whereIn('id',$courseIds)->where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->select('course_id')->first();
            if(empty($natureData)){
                return ['code'=>207,'msg'=>'课程已经取消授权'];
            }
            $courseIds = [$natureData['course_id']];
            $nature = self::whereIn('course_id',$courseIds)->where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->get()->toArray(); //要取消的授权的课程
            if(empty($nature)){
                return ['code'=>207,'msg'=>'课程已经取消授权!'];
            }
            foreach ($nature  as $kk => $vv) {
               $natureCourseArr[$kk]['parent_id'] = $vv['parent_id'];
               $natureCourseArr[$kk]['child_id'] = $vv['child_id'];
            }
            $noNatureCourse = self::whereNotIn('course_id',$courseIds)->where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->get()->toArray();//除取消授权课程的信息
            if(!empty($noNatureCourse)){
                foreach($noNatureCourse as $k=>$v){
                    $noNaturecourseSubjectArr[$k]['parent_id'] = $v['parent_id'];
                    $noNaturecourseSubjectArr[$k]['child_id'] = $v['child_id'];
                    array_push($nonatureCourseId,$v['course_id']);
                }
            }
            //要取消的教师信息
            $teachers_ids = Couresteacher::whereIn('course_id',$courseIds)->where(['is_del'=>0])->pluck('teacher_id')->toArray(); //要取消授权的教师信息
            if(!empty($nonatureCourseId)){
                $noNatuerTeacher_ids  =  Couresteacher::whereIn('course_id',$nonatureCourseId)->where(['is_del'=>0])->pluck('teacher_id')->toArray(); //除取消授权的教师信息
            }
            $refTeacherArr  = CourseRefTeacher::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0,'is_public'=>0])->pluck('teacher_id')->toArray(); //现已经授权过的讲师
            if(!empty($refTeacherArr)){
                $teachers_ids = array_unique($teachers_ids);
                if(!empty($noNatuerTeacher_ids)){
                    $noNatuerTeacher_ids = array_unique($noNatuerTeacher_ids);
                    $noNatuerTeacher_ids = array_intersect($refTeacherArr,$noNatuerTeacher_ids);
                    $arr = array_diff($teachers_ids,$noNatuerTeacher_ids);
                    if(!empty($arr)){
                       $updateTeacherArr = array_intersect($arr,$refTeacherArr);
                    }
                }else{
                   $updateTeacherArr = array_intersect($teachers_ids,$refTeacherArr); //$updateTecherArr 要取消授权的讲师信息
                }
            }
           //要取消的直播资源
           $zhibo_resourse_ids = CourseLivesResource::whereIn('course_id',$courseIds)->where('is_del',0)->pluck('id')->toArray(); //要取消授权的直播资源

           $no_natuer_zhibo_resourse_ids  =  CourseLivesResource::whereIn('course_id',$nonatureCourseId)->where('is_del',0)->pluck('id')->toArray(); //除取消授权的直播资源

           $refzhiboRescourse = CourseRefResource::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0,'type'=>1])->pluck('resource_id')->toArray(); //现在已经授权过的直播资源

           if(!empty($refzhiboRescourse)){
                $zhibo_resourse_ids = array_unique($zhibo_resourse_ids);
                if(!empty($no_natuer_zhibo_resourse_ids)){
                    $no_natuer_zhibo_resourse_ids = array_unique($no_natuer_zhibo_resourse_ids);
                    $no_natuer_zhibo_resourse_ids = array_intersect($refzhiboRescourse,$no_natuer_zhibo_resourse_ids);
                    $arr = array_diff($zhibo_resourse_ids,$no_natuer_zhibo_resourse_ids);
                    if(!empty($arr)){
                       $updatezhiboArr = array_intersect($arr,$refzhiboRescourse);
                    }
                }else{
                   $updatezhiboArr = array_intersect($zhibo_resourse_ids,$refzhiboRescourse); //$updatezhiboArr 要取消授权的讲师信息
                }
            }
            //要取消的录播资源
            $lvbo_resourse_ids = Coureschapters::whereIn('course_id',$courseIds)->where('is_del',0)->pluck('resource_id')->toArray(); //要取消授权的录播资源

            $no_natuer_lvbo_resourse_ids  =  Coureschapters::whereIn('course_id',$nonatureCourseId)->where('is_del',0)->pluck('resource_id')->toArray(); //除取消授权的录播资源

            $reflvboRescourse = CourseRefResource::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0,'type'=>0])->pluck('resource_id')->toArray(); //现在已经授权过的录播资源

            if(!empty($reflvboRescourse)){
               $lvbo_resourse_ids = array_unique($lvbo_resourse_ids);
               if(!empty($no_natuer_lvbo_resourse_ids)){
                    $no_natuer_lvbo_resourse_ids = array_unique($no_natuer_lvbo_resourse_ids);
                    $no_natuer_lvbo_resourse_ids = array_intersect($reflvboRescourse,$no_natuer_lvbo_resourse_ids);
                    $arr = array_diff($lvbo_resourse_ids,$no_natuer_lvbo_resourse_ids);
                    if(!empty($arr)){
                       $updatelvboArr = array_intersect($arr,$reflvboRescourse);
                    }
               }else{
                   $updatelvboArr = array_intersect($lvbo_resourse_ids,$reflvboRescourse); //$updatezhiboArr 要取消授权的讲师信息
               }
            }
            //学科
            $bankSubjectArr = $natureCourseArr = array_unique($natureCourseArr,SORT_REGULAR);//要取消授权的学科信息
            if(!empty($noNaturecourseSubjectArr)){
                $noBankSubjectArr  = $noNaturecourseSubjectArr = array_unique($noNaturecourseSubjectArr,SORT_REGULAR);//除取消授权的学科信息
            }

            $natureSubjectIds = CourseRefSubject::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0,'is_public'=>0])->select('parent_id','child_id')->get()->toArray();//已经授权过的学科信息
            if(!empty($natureSubjectIds)){
                $natureSubjectIds = array_unique($natureSubjectIds,SORT_REGULAR);
                if(!empty($noNaturecourseSubjectArr)){

                    foreach ($natureCourseArr as $ka => $va) {
                        foreach($noNaturecourseSubjectArr as $kb =>$vb){
                           if($va == $vb){
                                unset($natureCourseArr[$ka]);
                               //要取消的学科信息
                           }
                        }
                    }
                    if(!empty($natureCourseArr)){
                        foreach ($natureCourseArr as $ks => $vs) {
                            foreach($natureSubjectIds as$kn=>$vn ){
                                if($vs == $vn){
                                    unset($natureCourseArr[$ks]);
                                }
                            }
                        }
                       $updateSubjectArr = $natureCourseArr;
                    }

                }else{
                    foreach ($natureCourseArr as $ks => $vs) {
                        foreach($natureSubjectIds as$kn=>$vn ){
                            if($vs == $vn){
                               unset($natureCourseArr[$ks]);   //要取消的学科信息
                            }
                        }
                    }
                    $updateSubjectArr = $natureCourseArr;
                }
            }
            //题库
            //要取消的题库
            // $bankSubjectArr
            $natureBankId =  $noNatureBankId = [];
            // print_r($bankSubjectArr);
            // print_r($noBankSubjectArr);die;

            foreach ($bankSubjectArr as $key => $subject_id) {
                $bankArr = Bank::where($subject_id)->where(['is_del'=>0])->pluck('id')->toArray();

                if(!empty($bankArr)){
                    foreach($bankArr as $k=>$v){
                        array_push($natureBankId,$v);
                    }
                }
            }

            if(!empty($natureBankId)){
                //除要取消的题库
                //$noNaturecourseSubjectArr
                if(!empty($noBankSubjectArr)){
                    foreach($noBankSubjectArr as $key =>$subjectid){
                        $bankArr = Bank::where($subjectid)->where(['is_del'=>0])->pluck('id')->toArray();
                        if(!empty($bankArr)){
                            foreach($bankArr as $k=>$v){
                                array_push($noNatureBankId,$v);
                            }
                        }
                    }
                }
                $refBank =CourseRefBank::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->pluck('bank_id')->toArray(); //已经授权的题库
                if(!empty($refBank)){
                    $natureBankId = array_unique($natureBankId);
                    if(!empty($noNatureBankId)){
                       $noNatureBankId = array_unique($noNatureBankId);
                       $noNatureBankId = array_intersect($refBank,$noNatureBankId);
                       $arr = array_diff($natureBankId,$noNatureBankId);
                       if(!empty($arr)){
                           $updateBank = array_intersect($arr,$refBank);
                       }
                    }else{
                       $updateBank = array_intersect($natureBankId,$refBank); //$updateBank 要取消授权的题库
                    }
                }
            }

            DB::beginTransaction();
            try{
                $updateTime = date('Y-m-d H:i:s');
                if(!empty($updateTeacherArr)){

                    foreach ($updateTeacherArr as $k => $vt) {
                        $teacherRes =CourseRefTeacher::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'teacher_id'=>$vt,'is_public'=>0])->update(['is_del'=>1,'update_at'=>$updateTime]);
                        if(!$teacherRes){
                           DB::rollback();
                           return ['code'=>203,'msg'=>'教师取消授权未成功'];
                        }
                    }
                }
                if(!empty($updateSubjectArr)){
                    $updateSubjectArr = array_unique($updateSubjectArr,SORT_REGULAR);
                    foreach ($updateSubjectArr as $k => $vs) {
                        $subjectRes =CourseRefSubject::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'parent_id'=>$vs['parent_id'],'child_id'=>$vs['child_id']])->update(['is_del'=>1,'update_at'=>$updateTime]);

                        if(!$subjectRes){
                           DB::rollback();
                           return ['code'=>203,'msg'=>'学科取消授权未成功'];
                        }
                    }
                }

                if(!empty($updatelvboArr)){
                    $updatelvboArr = array_chunk($updatelvboArr,500);
                    foreach($updatelvboArr as $key=>$lvbo){
                        foreach ($lvbo as $k => $vl) {
                            $lvboRes =CourseRefResource::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'resource_id'=>$vl,'type'=>0])->update(['is_del'=>1,'update_at'=>$updateTime]);
                            if(!$lvboRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'录播资源取消授权未成功'];
                            }
                        }
                    }
                }

                if(!empty($updatezhiboArr)){
                    $updatezhiboArr = array_chunk($updatezhiboArr,500);
                    foreach($updatezhiboArr as $key=>$zhibo){
                        foreach ($zhibo as $k => $vz) {
                            $zhiboRes =CourseRefResource::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'resource_id'=>$vz,'type'=>1])->update(['is_del'=>1,'update_at'=>$updateTime]);
                            if(!$zhiboRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'直播资源取消授权未成功'];
                            }
                        }
                    }
                }
                if(!empty($updateBank)){
                    foreach ($updateBank as $k => $vb) {
                        $BankRes =CourseRefBank::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'bank_id'=>$vb])->update(['is_del'=>1,'update_at'=>$updateTime]);
                        if(!$BankRes){
                            DB::rollback();
                            return ['code'=>203,'msg'=>'题库取消授权未成功'];
                        }
                    }
                }
                if(!empty($courseIds)){

                    foreach ($courseIds as $key => $vc) {
                        $courseRes =self::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'course_id'=>$vc])->update(['is_del'=>1,'update_at'=>$updateTime]);
                        if(!$courseRes){
                            DB::rollback();
                            return ['code'=>203,'msg'=>'课程取消授权未成功'];
                        }
                    }
                }
                AdminLog::insertAdminLog([
                    'admin_id'       =>   $user_id ,
                    'module_name'    =>  'Courschool' ,
                    'route_url'      =>  'admin/courschool/courseCancel' ,
                    'operate_method' =>  'update',
                    'content'        =>  '课程取消授权'.json_encode(array_merge($body,$updateTeacherArr,$updateSubjectArr,$updatelvboArr,$updatezhiboArr,$updateBank,$courseIds)),
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
                ]);
                DB::commit();
                return ['code'=>200,'msg'=>'课程取消授权成功'];

            } catch (\Exception $e) {
                DB::rollBack();
                return ['code' => 500 , 'msg' => $e->getMessage()];
            }
        }

    }

    /**
    * @param  授权更新
    * @param  author  李银生
    * @param  ctime   2020/7/27
    * @return  array
   */
    public static function authorUpdate($body){
        $bankids = [];
        $from_school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登录的id
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0; //当前登录学校的状态
        if(!isset($body['school_id']) || $body['school_id'] <=0){
             return ['code'=>403,'msg'=>'学校标识不合法'];
        }
        $to_school_id = $body['school_id']; //一对一的做法
        if($school_status<=0){
            return ['code'=>403,'msg'=>'无权限，请联系管理员'];
        }
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0; //当前登录的用户id
        if($body['is_public'] == 1){ //公开课
            $schoolIds = School::where(['is_del'=>1,'is_forbid'=>1])->pluck('id')->toArray();
            if(empty($schoolIds)){
                return ['code'=>203,'msg'=>'学校信息不存在，授权更新未成功！'];
            }
            // foreach ($schoolIds as $k => $to_school_id) {  //一对多的做法
                $courseIds = CourseRefOpen::where(['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'is_del'=>0])->pluck('course_id')->toArray();
                if(!empty($courseIds)){
                      $ids = OpenCourseTeacher::whereIn('course_id',$courseIds)->where('is_del',0)->pluck('teacher_id')->toArray(); //要授权的教师信息
                    if(!empty($ids)){
                        $ids = array_unique($ids);
                        $teacherIds = CourseRefTeacher::where(['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'is_del'=>1])->pluck('teacher_id')->toArray();//已经授权过的讲师信息
                        if(!empty($teacherIds)){
                            $teacherIdArr = array_diff($ids,$teacherIds);//不在授权讲师表里的数据
                        }else{
                            $teacherIdArr = $ids;
                        }
                        if(!empty($teacherIdArr)){
                            foreach($teacherIdArr as $key => $id){
                                $InsertTeacherRef[$key]['from_school_id'] =$from_school_id;
                                $InsertTeacherRef[$key]['to_school_id'] =$to_school_id;
                                $InsertTeacherRef[$key]['teacher_id'] =$id;
                                $InsertTeacherRef[$key]['is_public'] =1;
                                $InsertTeacherRef[$key]['admin_id'] = $user_id;
                                $InsertTeacherRef[$key]['create_at'] = date('Y-m-d H:i:s');
                            }
                        }
                    }
                    $natureSubject = OpenCourse::where(function($query) use ($from_school_id) {
                                        $query->where('status',1);
                                        $query->where('school_id',$from_school_id);
                                        $query->where('is_del',0);
                                })->select('parent_id','child_id')->get()->toArray(); //要授权的学科信息
                    $natureSubject = array_unique($natureSubject,SORT_REGULAR);
                    $subjectArr = CourseRefSubject::where(['to_school_id'=>$to_school_id,'from_school_id'=>$from_school_id,'is_public'=>1,'is_del'=>0])->select('parent_id','child_id')->get();//已经授权的学科信息
                    if(!empty($subjectArr)){
                         foreach($natureSubject as $k=>$v){
                            foreach($subjectArr as $kk=>$bv){
                                if($v == $bv){
                                    unset($natureSubject[$k]);
                                }
                            }
                        }
                    }
                    if(!empty($natureSubject)){
                        foreach($natureSubject as $key=>&$vs){
                            $vs['is_public'] = 1;
                            $vs['from_school_id'] =$from_school_id;
                            $vs['to_school_id'] =$to_school_id;
                            $vs['admin_id'] =$user_id;
                            $vs['create_at'] =date('Y-m-d H:i:s');
                        }
                    }
                    DB::beginTransaction();
                    try{
                        if(!empty($InsertTeacherRef)){
                            $teacherRes = CourseRefTeacher::insert($InsertTeacherRef);
                            if(!$teacherRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'公开课授权更新未成功'];
                            }
                        }
                        if(!empty($natureSubject)){
                            $subjectRes = CourseRefSubject::insert($natureSubject);
                            if(!$subjectRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'公开课授权更新未成功！'];
                            }
                        }
                        AdminLog::insertAdminLog([
                            'admin_id'       =>   $user_id ,
                            'module_name'    =>  'Courschool' ,
                            'route_url'      =>  'admin/courschool/authorUpdate' ,
                            'operate_method' =>  'update',
                            'content'        =>  '公开课授权更新'.json_encode($body),
                            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                            'create_at'      =>  date('Y-m-d H:i:s')
                        ]);
                        DB::commit();
                        return ['code'=>200,'msg'=>'公开课授权更新成功！'];
                    } catch (\Exception $e) {
                        DB::rollback();
                        return ['code' => 500 , 'msg' => $e->getMessage()];
                    }
                }else{
                    return ['code'=>200,'msg'=>'公开课授权更新成功！'];
                }
            // }
        }
        if($body['is_public'] == 0){ //课程

            $schoolIds = School::where(['is_del'=>1,'is_forbid'=>1])->pluck('id')->toArray();
            if(empty($schoolIds)){
                return ['code'=>203,'msg'=>'学校信息不存在，授权更新未成功！'];
            }
            // foreach ($schoolIds as $k => $to_school_id) {
                $courseIds = self::where(['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'is_del'=>0])->pluck('course_id')->toArray();//已经授权过的课程id
                if(!empty($courseIds)){
                    $courseArr = Coures::whereIn('id',$courseIds)->select('id','parent_id','child_id')->get()->toArray();
                    if(empty($courseArr)){
                        return ['code'=>203,'msg'=>'课程信息不存在，请联系管理员！'];
                    }
                    foreach ($courseArr as $k => $vc) {
                        $updateSchoolCourseSubjectArr[$k]['parent_id'] = $vc['parent_id'];
                        $updateSchoolCourseSubjectArr[$k]['child_id'] = $vc['child_id'];
                        $updateSchoolCourseSubjectArr[$k]['course_id'] = $vc['id'];
                        $courseSubjectArr[$k]['parent_id'] = $vc['parent_id'];
                        $courseSubjectArr[$k]['child_id'] = $vc['child_id'];
                    }
                    $courseSubjectArr = array_unique($courseSubjectArr,SORT_REGULAR);
                    $ids = Couresteacher::whereIn('course_id',$courseIds)->where('is_del',0)->pluck('teacher_id')->toArray(); //要授权的教师信息
                    if(!empty($ids)){
                        $ids = array_unique($ids);
                        $teacherIds = CourseRefTeacher::where(['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'is_del'=>0])->pluck('teacher_id')->toArray();//已经授权过的讲师信息
                        if(!empty($teacherIds)){
                            $teacherIdArr = array_diff($ids,$teacherIds);//不在授权讲师表里的数据
                        }else{
                            $teacherIdArr = $ids;
                        }
                        if(!empty($teacherIdArr)){
                            foreach($teacherIdArr as $key => $id){
                                $InsertTeacherRef[$key]['from_school_id'] =$from_school_id;
                                $InsertTeacherRef[$key]['to_school_id'] =$to_school_id;
                                $InsertTeacherRef[$key]['teacher_id'] =$id;
                                $InsertTeacherRef[$key]['is_public'] =0;
                                $InsertTeacherRef[$key]['admin_id'] = $user_id;
                                $InsertTeacherRef[$key]['create_at'] = date('Y-m-d H:i:s');
                            }
                        }
                    }
                    //题库
                    foreach($courseSubjectArr as $key=>&$vs){
                        $bankIdArr = QuestionBank::where(['parent_id'=>$vs['parent_id'],'child_id'=>$vs['child_id'],'is_del'=>0,'school_id'=>$from_school_id])->pluck('id')->toArray();
                        if(!empty($bankIdArr)){
                            foreach($bankIdArr as $k=>$vb){
                                array_push($bankids,$vb);
                            }
                        }
                    }

                    if(!empty($bankids)){
                        $bankids=array_unique($bankids);
                        $natureQuestionBank = CourseRefBank::where(['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'is_del'=>0])->pluck('bank_id')->toArray();
                        $bankids = array_diff($bankids,$natureQuestionBank);
                        foreach($bankids as $key=>$bankid){
                            $InsertQuestionArr[$key]['bank_id'] =$bankid;
                            $InsertQuestionArr[$key]['from_school_id'] = $from_school_id;
                            $InsertQuestionArr[$key]['to_school_id'] = $to_school_id;
                            $InsertQuestionArr[$key]['admin_id'] = $user_id;
                            $InsertQuestionArr[$key]['create_at'] = date('Y-m-d H:i:s');
                        }
                    }
                    //学科
                    $subjectArr = CourseRefSubject::where(['to_school_id'=>$to_school_id,'from_school_id'=>$from_school_id,'is_del'=>0])->select('parent_id','child_id')->get()->toArray();  //已经授权过的学科
                    if(!empty($subjectArr)){
                        foreach($courseSubjectArr as $k=>$v){
                            foreach($subjectArr as $kk=>$bv){
                                if($v == $bv){
                                    unset($courseSubjectArr[$k]);
                                }
                            }
                        }
                    }
                    foreach($courseSubjectArr as $key=>$v){
                            $InsertSubjectRef[$key]['is_public'] = 0;
                            $InsertSubjectRef[$key]['parent_id'] = $v['parent_id'];
                            $InsertSubjectRef[$key]['child_id'] = $v['child_id'];
                            $InsertSubjectRef[$key]['from_school_id'] = $from_school_id;
                            $InsertSubjectRef[$key]['to_school_id'] = $to_school_id;
                            $InsertSubjectRef[$key]['admin_id'] = $user_id;
                            $InsertSubjectRef[$key]['create_at'] = date('Y-m-d H:i:s');
                    }
                    //录播资源授权更新未成功！
                    $recordVideoIds = Coureschapters::whereIn('course_id',$courseIds)->where(['is_del'=>0])->pluck('resource_id as id')->toArray(); //要授权的录播资源
                    if(!empty($recordVideoIds)){
                        $narturecordVideoIds = CourseRefResource::where(['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'type'=>0,'is_del'=>0])->pluck('resource_id as id ')->toArray(); //已经授权过的录播资源
                        $recordVideoIds = array_diff($recordVideoIds,$narturecordVideoIds);
                        foreach ($recordVideoIds as $key => $v) {
                            $InsertRecordVideoArr[$key]['resource_id']=$v;
                            $InsertRecordVideoArr[$key]['from_school_id'] = $from_school_id;
                            $InsertRecordVideoArr[$key]['to_school_id'] = $to_school_id;
                            $InsertRecordVideoArr[$key]['admin_id'] = $user_id;
                            $InsertRecordVideoArr[$key]['type'] = 0;
                            $InsertRecordVideoArr[$key]['create_at'] = date('Y-m-d H:i:s');
                        }
                    }

                    //直播资源
                    $zhiboVideoIds = CourseLivesResource::whereIn('course_id',$courseIds)->where(['is_del'=>0])->pluck('id')->toArray();//要授权的直播资源
                    if(!empty($zhiboVideoIds)){
                        $narturezhiboVideoIds = CourseRefResource::where(['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'type'=>1,'is_del'=>0])->pluck('resource_id as id ')->toArray();
                        $zhiboVideoIds = array_diff($zhiboVideoIds,$narturezhiboVideoIds);
                        foreach ($zhiboVideoIds as $key => $v) {
                            $InsertZhiboVideoArr[$key]['resource_id']=$v;
                            $InsertZhiboVideoArr[$key]['from_school_id'] = $from_school_id;
                            $InsertZhiboVideoArr[$key]['to_school_id'] = $to_school_id;
                            $InsertZhiboVideoArr[$key]['admin_id'] = $user_id;
                            $InsertZhiboVideoArr[$key]['type'] = 1;
                            $InsertZhiboVideoArr[$key]['create_at'] = date('Y-m-d H:i:s');
                        }
                    }

                    DB::beginTransaction();
                    try {
                        if(!empty($InsertTeacherRef)){
                            $teacherRes = CourseRefTeacher::insert($InsertTeacherRef);//教师
                            if(!$teacherRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'教师授权更新未成功'];
                            }
                        }
                        if(!empty($InsertSubjectRef)){
                            $subjectRes = CourseRefSubject::insert($InsertSubjectRef);//学科
                            if(!$subjectRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'学科授权更新未成功！'];
                            }
                        }

                        if(!empty($InsertRecordVideoArr)){

                            $InsertRecordVideoArr = array_chunk($InsertRecordVideoArr,500);
                            foreach($InsertRecordVideoArr as $key=>$lvbo){
                                $recordRes = CourseRefResource::insert($lvbo); //录播

                                if(!$recordRes){
                                    DB::rollback();
                                    return ['code'=>203,'msg'=>'录播资源授权更新未成功！'];
                                }
                            }
                        }

                        if(!empty($InsertZhiboVideoArr)){
                            $InsertZhiboVideoArr = array_chunk($InsertZhiboVideoArr,500);
                            foreach($InsertZhiboVideoArr as $key=>$zhibo){
                                $zhiboRes = CourseRefResource::insert($zhibo); //直播
                                if(!$zhiboRes){
                                    DB::rollback();
                                    return ['code'=>203,'msg'=>'直播资源授权更新未成功！'];
                                }
                            }
                        }
                        if(!empty($InsertQuestionArr)){
                            $bankRes = CourseRefBank::insert($InsertQuestionArr); //题库
                            if(!$bankRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'题库授权更新未成功！'];
                            }
                        }
                        $updateSchoolCourseSubjectArr=array_unique($updateSchoolCourseSubjectArr,SORT_REGULAR);

                        foreach($updateSchoolCourseSubjectArr as $key=>$courses){

                            $where = ['from_school_id'=>$from_school_id,'to_school_id'=>$to_school_id,'course_id'=>$courses['course_id']];
                            $update = ['parent_id'=>$courses['parent_id'],'child_id'=>$courses['child_id'],'update_at'=>date('Y-m-d H:i:s'),'admin_id'=>$user_id];
                            $courseRes = self::where($where)->update($update);
                            if(!$courseRes){
                                DB::rollback();
                                return ['code'=>203,'msg'=>'课程授权更新未成功！'];
                            }
                        }
                        AdminLog::insertAdminLog([
                            'admin_id'       =>   $user_id ,
                            'module_name'    =>  'Courschool' ,
                            'route_url'      =>  'admin/courschool/authorUpdate' ,
                            'operate_method' =>  'update',
                            'content'        =>  '课程授权更新'.json_encode($body),
                            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                            'create_at'      =>  date('Y-m-d H:i:s')
                        ]);
                        DB::commit();

                    } catch (\Exception $e) {
                        DB::rollback();
                        return ['code'=>$e->getCode(),'msg'=>$e->__toString()];
                    }
                }
            // }
            return ['code'=>200,'msg'=>'课程授权更新成功'];
        }
    }

    /**
     * 批量取消授权
     * 总校对分校进行课程授权, 前段展示效果, 已授权课程反选后, 则取消授权
     * 做法, 接收前段传值, 需要授权的课程, 查询数据库已经授权的课程, 差集, 得到本次取消授权的课程(授权id组, 或者课程id组)
     * 是否可取消条件, 只判断库存是否存在, 库存为0(不管课程什么状态, 可以取消, ) 库存>0, 不可取消
     * @param courseid array 进行授权的id数组(课程id)
     * @param schoolid int 学校
     * @author 赵老仙
     */
    public static function multiCancalCourseSchool($params,$search)
    {
        //为空直接返回
        if(empty($params['courseids'])){
            return ['code'=>200,'msg'=>'success'];
        }
        if(!$params['school_id']){
            return ['code'=>206,'msg'=>'取消授权失败'];
        }
        $params['courseids'] = json_decode($params['courseids'],true);

        //
        $whereArr = [
            ['ld_course.is_del','=',0],//总控未删除
            ['ld_course_school.to_school_id','=',$params['school_id']],//分校
            ['ld_course_school.is_del','=',0],//分校课程未未取消授权
        ];
        //一级学科
        if($search['parentid']){
            $whereArr[] = ['ld_course.parent_id','=',$search['parentid']];
        }
        //二级学科
        if($search['childid']){
            $whereArr[] = ['ld_course.child_id','=',$search['childid']];
        }
        //课程名称
        if($search['search']){
            $whereArr[] = ['ld_course.title','liek','%'.$search['search'].'%'];
        }

        //当前授权生效中的课程id组 course_id
        $now_nature_normal_courseids = Coures::leftJoin('ld_course_school','ld_course.id','=','ld_course_school.course_id')
            ->where($whereArr)->pluck('ld_course.id')->toArray();



        //当前授权生效中的课程id组 course_id
        //$now_nature_normal_courseids = self::where('to_school_id',$params['school_id'])->where($whereArr)->pluck('course_id')->toArray();
        //当前授权中, 对比本次要进行的授权 fun(差集), 得到要取消授权的课程id
        $cancal_courseids = array_diff($now_nature_normal_courseids,$params['courseids']);

        $arr = [];//
        $subjectArr = [];//科目
        $bankids = [];//考卷
        $questionIds = [];//题库
        $updateTeacherArr = [];//讲师
        $updateSubjectArr = [];//学科
        $updatelvboArr = [];//录播资源
        $updatezhiboArr = [];//直播资源
        $updateBank = [];//题库
        $teacherIdArr = [];//讲师
        $nonatureCourseId = [];//非取消授权的课程id组别, 课程id
        $noNatuerTeacher_ids = [];

        //取消授权课程 授权表id组
        /*$courseIds =[$params['course_id']];
        if(empty($courseIds)){
            return ['code'=>205,'msg'=>'请选择取消授权课程'];
        }*/

        $school_id = 1;//定义发起授权的网校是1, isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前学校id
        //$school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0; //当前登录学校的状态
        //$user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0; //当前登录的用户id
        //$schoolArr =Admin::where(['school_id'=>$body['school_id'],'is_del'=>1])->first(); //前端传学校的id

        //根据授权表id, 查找要取消授权课程是否存在
        /*$natureData = CourseSchool::whereIn('id',$courseIds)
            ->where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'is_del'=>0])
            ->select('course_id')->first();
        if(empty($natureData)){
            return ['code'=>207,'msg'=>'课程已经取消授权'];
        }

        //根据要取消授权的课程的课程id, 查询课程是否存在, 与上一段语句重复
        $courseIds = [$natureData['course_id']];*/
        $courseIds = $cancal_courseids;//使用上面赵老仙获取的将要取消授权的课程id组(course_id)
        $nature = CourseSchool::whereIn('course_id',$courseIds)
            ->where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'is_del'=>0])
            ->get()->toArray();
        if(empty($nature)){
            return ['code'=>207,'msg'=>'课程已经取消授权!'];
        }

        //遍历要取消授权课程的 科目 , 数组为新数组,未提前声明
        foreach ($nature  as $kk => $vv) {
            $natureCourseArr[$kk]['parent_id'] = $vv['parent_id'];
            $natureCourseArr[$kk]['child_id'] = $vv['child_id'];
        }


        //非取消授权课程, 用于查找依然需要用的科目
        $noNatureCourse = CourseSchool::whereNotIn('course_id',$courseIds)
            ->where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'is_del'=>0])
            ->get()->toArray();//除取消授权课程的信息
        if(!empty($noNatureCourse)){
            foreach($noNatureCourse as $k=>$v){
                //非取消授权的科目组
                $noNaturecourseSubjectArr[$k]['parent_id'] = $v['parent_id'];
                $noNaturecourseSubjectArr[$k]['child_id'] = $v['child_id'];
                //非取消授权的课程id组别
                array_push($nonatureCourseId,$v['course_id']);
            }
        }

        //要取消的教师信息
        $teachers_ids = Couresteacher::whereIn('course_id',$courseIds)
            ->where(['is_del'=>0])->pluck('teacher_id')->toArray();
        if(!empty($nonatureCourseId)){
            //当前需要用到的, 老师id组别
            $noNatuerTeacher_ids  =  Couresteacher::whereIn('course_id',$nonatureCourseId)
                ->where(['is_del'=>0])->pluck('teacher_id')->toArray();
        }

        //现已经授权过的讲师
        $whereArr = ['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'is_del'=>0,'is_public'=>0];
        $refTeacherArr  = CourseRefTeacher::where($whereArr)->pluck('teacher_id')->toArray();
        if(!empty($refTeacherArr)){
            $teachers_ids = array_unique($teachers_ids);

            //当前需要用到的, 老师id组别
            if(!empty($noNatuerTeacher_ids)){
                //当前需要用到的讲师, 去重
                $noNatuerTeacher_ids = array_unique($noNatuerTeacher_ids);
                //已经授权, 需要用到的, 交集, (感觉没必要, $noNatuerTeacher_ids 已经是全部需要用到的讲师ids),
                $noNatuerTeacher_ids = array_intersect($refTeacherArr,$noNatuerTeacher_ids);
                //要取消的, 相对于 需要用到的, 对比差集, 得到= (最终可以取消的)
                $arr = array_diff($teachers_ids,$noNatuerTeacher_ids);
                if(!empty($arr)){
                    //要取消的, 交集 已经授权的, 得到(最终可以取消的),
                    // 思考当时codeing可能是为了最终确认要取消的都是数据库中已存在的,
                    // 感觉多虑了, arr数据本身就是数据库去除的数据经过比较 计算得到的
                    $updateTeacherArr = array_intersect($arr,$refTeacherArr);
                }
            }else{
                //$noNatuerTeacher_ids 为null时, 证明当前网校已经用不到任何讲师, 直接将$teachers_ids 要取消的讲师信息取消授权即可
                // 但是又做了一个与$refTeacherArr的交集, 与else前一样, 估计是为了得到确切的数据库中存在的可以取消的讲师id
                $updateTeacherArr = array_intersect($teachers_ids,$refTeacherArr); //$updateTecherArr 要取消授权的讲师信息
            }
        }
        // 1,要取消的讲师id组 $updateTeacherArr


        //要取消的直播资源
        $zhibo_resourse_ids = CourseLivesResource::whereIn('course_id',$courseIds)
            ->where('is_del',0)->pluck('id')->toArray();

        //依然用到的直播资源id
        $no_natuer_zhibo_resourse_ids  =  CourseLivesResource::whereIn('course_id',$nonatureCourseId)
            ->where('is_del',0)->pluck('id')->toArray();

        //当前已经授权的直播资源id
        $wheres = ['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'is_del'=>0,'type'=>1];
        $refzhiboRescourse = CourseRefResource::where($wheres)->pluck('resource_id')->toArray(); //现在已经授权过的直播资源

        //获取要取消的直播资源的id
        if(!empty($refzhiboRescourse)){
            $zhibo_resourse_ids = array_unique($zhibo_resourse_ids);
            if(!empty($no_natuer_zhibo_resourse_ids)){
                $no_natuer_zhibo_resourse_ids = array_unique($no_natuer_zhibo_resourse_ids);
                $no_natuer_zhibo_resourse_ids = array_intersect($refzhiboRescourse,$no_natuer_zhibo_resourse_ids);
                $arr = array_diff($zhibo_resourse_ids,$no_natuer_zhibo_resourse_ids);
                if(!empty($arr)){
                    $updatezhiboArr = array_intersect($arr,$refzhiboRescourse);
                }
            }else{
                $updatezhiboArr = array_intersect($zhibo_resourse_ids,$refzhiboRescourse);
            }
        }
        // 2,要取消的直播资源组 $updatezhiboArr

        //要取消的录播资源
        $lvbo_resourse_ids = Coureschapters::whereIn('course_id',$courseIds)->where('is_del',0)->pluck('resource_id')->toArray(); //要取消授权的录播资源

        $no_natuer_lvbo_resourse_ids  =  Coureschapters::whereIn('course_id',$nonatureCourseId)->where('is_del',0)->pluck('resource_id')->toArray(); //除取消授权的录播资源

        $where_resource = ['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'is_del'=>0,'type'=>0];
        $reflvboRescourse = CourseRefResource::where($where_resource)->pluck('resource_id')->toArray(); //现在已经授权过的录播资源

        if(!empty($reflvboRescourse)){
            $lvbo_resourse_ids = array_unique($lvbo_resourse_ids);
            if(!empty($no_natuer_lvbo_resourse_ids)){
                $no_natuer_lvbo_resourse_ids = array_unique($no_natuer_lvbo_resourse_ids);
                $no_natuer_lvbo_resourse_ids = array_intersect($reflvboRescourse,$no_natuer_lvbo_resourse_ids);
                $arr = array_diff($lvbo_resourse_ids,$no_natuer_lvbo_resourse_ids);
                if(!empty($arr)){
                    $updatelvboArr = array_intersect($arr,$reflvboRescourse);
                }
            }else{
                $updatelvboArr = array_intersect($lvbo_resourse_ids,$reflvboRescourse); //$updatezhiboArr 要取消授权的讲师信息
            }
        }
        //3, 要取消的录播资源组, $updatelvboArr

        //学科
        $bankSubjectArr = $natureCourseArr = array_unique($natureCourseArr,SORT_REGULAR);//要取消授权的学科信息
        if(!empty($noNaturecourseSubjectArr)){
            $noBankSubjectArr  = $noNaturecourseSubjectArr = array_unique($noNaturecourseSubjectArr,SORT_REGULAR);//除取消授权的学科信息
        }
        //授权表中的科目id集合
        $wheres = ['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'is_del'=>0,'is_public'=>0];
        $natureSubjectIds = CourseRefSubject::where($wheres)->select('parent_id','child_id')->get()->toArray();//已经授权过的学科信息
        if(!empty($natureSubjectIds)){
            $natureSubjectIds = array_unique($natureSubjectIds,SORT_REGULAR);
            if(!empty($noNaturecourseSubjectArr)){

                foreach ($natureCourseArr as $ka => $va) {
                    foreach($noNaturecourseSubjectArr as $kb =>$vb){
                        if($va == $vb){
                            unset($natureCourseArr[$ka]);
                            //要取消的学科信息
                        }
                    }
                }
                if(!empty($natureCourseArr)){
                    foreach ($natureCourseArr as $ks => $vs) {
                        foreach($natureSubjectIds as$kn=>$vn ){
                            if($vs == $vn){
                                unset($natureCourseArr[$ks]);
                            }
                        }
                    }
                    $updateSubjectArr = $natureCourseArr;
                }

            }else{
                foreach ($natureCourseArr as $ks => $vs) {
                    foreach($natureSubjectIds as$kn=>$vn ){
                        if($vs == $vn){
                            unset($natureCourseArr[$ks]);   //要取消的学科信息
                        }
                    }
                }
                $updateSubjectArr = $natureCourseArr;
            }
        }

        //题库
        //要取消的题库
        // $bankSubjectArr
        $natureBankId =  $noNatureBankId = [];
        // print_r($bankSubjectArr);
        // print_r($noBankSubjectArr);die;

        foreach ($bankSubjectArr as $key => $subject_id) {
            $bankArr = Bank::where($subject_id)->where(['is_del'=>0])->pluck('id')->toArray();

            if(!empty($bankArr)){
                foreach($bankArr as $k=>$v){
                    array_push($natureBankId,$v);
                }
            }
        }

        if(!empty($natureBankId)){
            //除要取消的题库
            //$noNaturecourseSubjectArr
            if(!empty($noBankSubjectArr)){
                foreach($noBankSubjectArr as $key =>$subjectid){
                    $bankArr = Bank::where($subjectid)->where(['is_del'=>0])->pluck('id')->toArray();
                    if(!empty($bankArr)){
                        foreach($bankArr as $k=>$v){
                            array_push($noNatureBankId,$v);
                        }
                    }
                }
            }
            $refBank =CourseRefBank::where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'is_del'=>0])->pluck('bank_id')->toArray(); //已经授权的题库
            if(!empty($refBank)){
                $natureBankId = array_unique($natureBankId);
                if(!empty($noNatureBankId)){
                    $noNatureBankId = array_unique($noNatureBankId);
                    $noNatureBankId = array_intersect($refBank,$noNatureBankId);
                    $arr = array_diff($natureBankId,$noNatureBankId);
                    if(!empty($arr)){
                        $updateBank = array_intersect($arr,$refBank);
                    }
                }else{
                    $updateBank = array_intersect($natureBankId,$refBank); //$updateBank 要取消授权的题库
                }
            }
        }

        $updateTime = date('Y-m-d H:i:s');
        if(!empty($updateTeacherArr)){
            foreach ($updateTeacherArr as $k => $vt) {
                $teacherRes =CourseRefTeacher::where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'teacher_id'=>$vt,'is_public'=>0])->update(['is_del'=>1,'update_at'=>$updateTime]);
                if(!$teacherRes){
                    return ['code'=>203,'msg'=>'教师取消授权未成功'];
                }
            }
        }
        if(!empty($updateSubjectArr)){
            $updateSubjectArr = array_unique($updateSubjectArr,SORT_REGULAR);
            foreach ($updateSubjectArr as $k => $vs) {
                $subjectRes =CourseRefSubject::where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'parent_id'=>$vs['parent_id'],'child_id'=>$vs['child_id']])->update(['is_del'=>1,'update_at'=>$updateTime]);

                if(!$subjectRes){
                    return ['code'=>203,'msg'=>'学科取消授权未成功'];
                }
            }
        }

        if(!empty($updatelvboArr)){
            $updatelvboArr = array_chunk($updatelvboArr,500);
            foreach($updatelvboArr as $key=>$lvbo){
                foreach ($lvbo as $k => $vl) {
                    $lvboRes =CourseRefResource::where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'resource_id'=>$vl,'type'=>0])->update(['is_del'=>1,'update_at'=>$updateTime]);
                    if(!$lvboRes){
                        return ['code'=>203,'msg'=>'录播资源取消授权未成功'];
                    }
                }
            }
        }

        if(!empty($updatezhiboArr)){
            $updatezhiboArr = array_chunk($updatezhiboArr,500);
            foreach($updatezhiboArr as $key=>$zhibo){
                foreach ($zhibo as $k => $vz) {
                    $zhiboRes =CourseRefResource::where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'resource_id'=>$vz,'type'=>1])->update(['is_del'=>1,'update_at'=>$updateTime]);
                    if(!$zhiboRes){
                        return ['code'=>203,'msg'=>'直播资源取消授权未成功'];
                    }
                }
            }
        }
        if(!empty($updateBank)){
            foreach ($updateBank as $k => $vb) {
                $BankRes =CourseRefBank::where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'bank_id'=>$vb])->update(['is_del'=>1,'update_at'=>$updateTime]);
                if(!$BankRes){
                    return ['code'=>203,'msg'=>'题库取消授权未成功'];
                }
            }
        }
        if(!empty($courseIds)){
            foreach ($courseIds as $key => $vc) {
                $courseRes =self::where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'course_id'=>$vc])->update(['is_del'=>1,'update_at'=>$updateTime]);
                if(!$courseRes){
                    return ['code'=>203,'msg'=>'课程取消授权未成功'];
                }
            }
        }
        return ['code'=>200,'msg'=>'课程取消授权成功'];

    }

    /**
     * 全部取消[或全部取消某科目下全部课程]授权
     */
    public static function AllCancalCourseSchool($school_id,$search)
    {
        //
        $whereArr = [
            ['ld_course.is_del','=',0],//总控未删除
            ['ld_course_school.to_school_id','=',$school_id],//分校
            ['ld_course_school.is_del','=',0],//分校课程未未取消授权
        ];
        //一级学科
        if($search['parentid']){
            $whereArr[] = ['ld_course.parent_id','=',$search['parentid']];
        }
        //二级学科
        if($search['childid']){
            $whereArr[] = ['ld_course.child_id','=',$search['childid']];
        }
        //课程名称
        if($search['search']){
            $whereArr[] = ['ld_course.title','liek','%'.$search['search'].'%'];
        }

        //当前授权生效中的课程id组 course_id => 就是本次要全部取消授权的课程
        $cancal_courseids = Coures::leftJoin('ld_course_school','ld_course.id','=','ld_course_school.course_id')
            ->where($whereArr)->pluck('ld_course.id')->toArray();

        $arr = [];//
        $subjectArr = [];//科目
        $bankids = [];//考卷
        $questionIds = [];//题库
        $updateTeacherArr = [];//讲师
        $updateSubjectArr = [];//学科
        $updatelvboArr = [];//录播资源
        $updatezhiboArr = [];//直播资源
        $updateBank = [];//题库
        $teacherIdArr = [];//讲师
        $nonatureCourseId = [];//非取消授权的课程id组别, 课程id
        $noNatuerTeacher_ids = [];

        //取消授权课程 授权表id组
        /*$courseIds =[$params['course_id']];
        if(empty($courseIds)){
            return ['code'=>205,'msg'=>'请选择取消授权课程'];
        }*/

        $school_pid = 1;//定义发起授权的网校是1, isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前学校id
        //$school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0; //当前登录学校的状态
        //$user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0; //当前登录的用户id
        //$schoolArr =Admin::where(['school_id'=>$body['school_id'],'is_del'=>1])->first(); //前端传学校的id

        //根据授权表id, 查找要取消授权课程是否存在
        /*$natureData = CourseSchool::whereIn('id',$courseIds)
            ->where(['from_school_id'=>$school_id,'to_school_id'=>$params['school_id'],'is_del'=>0])
            ->select('course_id')->first();
        if(empty($natureData)){
            return ['code'=>207,'msg'=>'课程已经取消授权'];
        }

        //根据要取消授权的课程的课程id, 查询课程是否存在, 与上一段语句重复
        $courseIds = [$natureData['course_id']];*/
        $courseIds = $cancal_courseids;//使用上面赵老仙获取的将要取消授权的课程id组(course_id)
        $nature = CourseSchool::whereIn('course_id',$courseIds)
            ->where(['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'is_del'=>0])
            ->get()->toArray();
        if(empty($nature)){
            return ['code'=>207,'msg'=>'课程已经取消授权!'];
        }

        //遍历要取消授权课程的 科目 , 数组为新数组,未提前声明
        foreach ($nature  as $kk => $vv) {
            $natureCourseArr[$kk]['parent_id'] = $vv['parent_id'];
            $natureCourseArr[$kk]['child_id'] = $vv['child_id'];
        }


        //非取消授权课程, 用于查找依然需要用的科目
        $noNatureCourse = CourseSchool::whereNotIn('course_id',$courseIds)
            ->where(['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'is_del'=>0])
            ->get()->toArray();//除取消授权课程的信息
        if(!empty($noNatureCourse)){
            foreach($noNatureCourse as $k=>$v){
                //非取消授权的科目组
                $noNaturecourseSubjectArr[$k]['parent_id'] = $v['parent_id'];
                $noNaturecourseSubjectArr[$k]['child_id'] = $v['child_id'];
                //非取消授权的课程id组别
                array_push($nonatureCourseId,$v['course_id']);
            }
        }

        //要取消的教师信息
        $teachers_ids = Couresteacher::whereIn('course_id',$courseIds)
            ->where(['is_del'=>0])->pluck('teacher_id')->toArray();
        if(!empty($nonatureCourseId)){
            //当前需要用到的, 老师id组别
            $noNatuerTeacher_ids  =  Couresteacher::whereIn('course_id',$nonatureCourseId)
                ->where(['is_del'=>0])->pluck('teacher_id')->toArray();
        }

        //现已经授权过的讲师
        $whereArr = ['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'is_del'=>0,'is_public'=>0];
        $refTeacherArr  = CourseRefTeacher::where($whereArr)->pluck('teacher_id')->toArray();
        if(!empty($refTeacherArr)){
            $teachers_ids = array_unique($teachers_ids);

            //当前需要用到的, 老师id组别
            if(!empty($noNatuerTeacher_ids)){
                //当前需要用到的讲师, 去重
                $noNatuerTeacher_ids = array_unique($noNatuerTeacher_ids);
                //已经授权, 需要用到的, 交集, (感觉没必要, $noNatuerTeacher_ids 已经是全部需要用到的讲师ids),
                $noNatuerTeacher_ids = array_intersect($refTeacherArr,$noNatuerTeacher_ids);
                //要取消的, 相对于 需要用到的, 对比差集, 得到= (最终可以取消的)
                $arr = array_diff($teachers_ids,$noNatuerTeacher_ids);
                if(!empty($arr)){
                    //要取消的, 交集 已经授权的, 得到(最终可以取消的),
                    // 思考当时codeing可能是为了最终确认要取消的都是数据库中已存在的,
                    // 感觉多虑了, arr数据本身就是数据库去除的数据经过比较 计算得到的
                    $updateTeacherArr = array_intersect($arr,$refTeacherArr);
                }
            }else{
                //$noNatuerTeacher_ids 为null时, 证明当前网校已经用不到任何讲师, 直接将$teachers_ids 要取消的讲师信息取消授权即可
                // 但是又做了一个与$refTeacherArr的交集, 与else前一样, 估计是为了得到确切的数据库中存在的可以取消的讲师id
                $updateTeacherArr = array_intersect($teachers_ids,$refTeacherArr); //$updateTecherArr 要取消授权的讲师信息
            }
        }
        // 1,要取消的讲师id组 $updateTeacherArr


        //要取消的直播资源
        $zhibo_resourse_ids = CourseLivesResource::whereIn('course_id',$courseIds)
            ->where('is_del',0)->pluck('id')->toArray();

        //依然用到的直播资源id
        $no_natuer_zhibo_resourse_ids  =  CourseLivesResource::whereIn('course_id',$nonatureCourseId)
            ->where('is_del',0)->pluck('id')->toArray();

        //当前已经授权的直播资源id
        $wheres = ['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'is_del'=>0,'type'=>1];
        $refzhiboRescourse = CourseRefResource::where($wheres)->pluck('resource_id')->toArray(); //现在已经授权过的直播资源

        //获取要取消的直播资源的id
        if(!empty($refzhiboRescourse)){
            $zhibo_resourse_ids = array_unique($zhibo_resourse_ids);
            if(!empty($no_natuer_zhibo_resourse_ids)){
                $no_natuer_zhibo_resourse_ids = array_unique($no_natuer_zhibo_resourse_ids);
                $no_natuer_zhibo_resourse_ids = array_intersect($refzhiboRescourse,$no_natuer_zhibo_resourse_ids);
                $arr = array_diff($zhibo_resourse_ids,$no_natuer_zhibo_resourse_ids);
                if(!empty($arr)){
                    $updatezhiboArr = array_intersect($arr,$refzhiboRescourse);
                }
            }else{
                $updatezhiboArr = array_intersect($zhibo_resourse_ids,$refzhiboRescourse);
            }
        }
        // 2,要取消的直播资源组 $updatezhiboArr

        //要取消的录播资源
        $lvbo_resourse_ids = Coureschapters::whereIn('course_id',$courseIds)->where('is_del',0)->pluck('resource_id')->toArray(); //要取消授权的录播资源

        $no_natuer_lvbo_resourse_ids  =  Coureschapters::whereIn('course_id',$nonatureCourseId)->where('is_del',0)->pluck('resource_id')->toArray(); //除取消授权的录播资源

        $where_resource = ['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'is_del'=>0,'type'=>0];
        $reflvboRescourse = CourseRefResource::where($where_resource)->pluck('resource_id')->toArray(); //现在已经授权过的录播资源

        if(!empty($reflvboRescourse)){
            $lvbo_resourse_ids = array_unique($lvbo_resourse_ids);
            if(!empty($no_natuer_lvbo_resourse_ids)){
                $no_natuer_lvbo_resourse_ids = array_unique($no_natuer_lvbo_resourse_ids);
                $no_natuer_lvbo_resourse_ids = array_intersect($reflvboRescourse,$no_natuer_lvbo_resourse_ids);
                $arr = array_diff($lvbo_resourse_ids,$no_natuer_lvbo_resourse_ids);
                if(!empty($arr)){
                    $updatelvboArr = array_intersect($arr,$reflvboRescourse);
                }
            }else{
                $updatelvboArr = array_intersect($lvbo_resourse_ids,$reflvboRescourse); //$updatezhiboArr 要取消授权的讲师信息
            }
        }
        //3, 要取消的录播资源组, $updatelvboArr

        //学科
        $bankSubjectArr = $natureCourseArr = array_unique($natureCourseArr,SORT_REGULAR);//要取消授权的学科信息
        if(!empty($noNaturecourseSubjectArr)){
            $noBankSubjectArr  = $noNaturecourseSubjectArr = array_unique($noNaturecourseSubjectArr,SORT_REGULAR);//除取消授权的学科信息
        }
        //授权表中的科目id集合
        $wheres = ['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'is_del'=>0,'is_public'=>0];
        $natureSubjectIds = CourseRefSubject::where($wheres)->select('parent_id','child_id')->get()->toArray();//已经授权过的学科信息
        if(!empty($natureSubjectIds)){
            $natureSubjectIds = array_unique($natureSubjectIds,SORT_REGULAR);
            if(!empty($noNaturecourseSubjectArr)){

                foreach ($natureCourseArr as $ka => $va) {
                    foreach($noNaturecourseSubjectArr as $kb =>$vb){
                        if($va == $vb){
                            unset($natureCourseArr[$ka]);
                            //要取消的学科信息
                        }
                    }
                }
                if(!empty($natureCourseArr)){
                    foreach ($natureCourseArr as $ks => $vs) {
                        foreach($natureSubjectIds as$kn=>$vn ){
                            if($vs == $vn){
                                unset($natureCourseArr[$ks]);
                            }
                        }
                    }
                    $updateSubjectArr = $natureCourseArr;
                }

            }else{
                foreach ($natureCourseArr as $ks => $vs) {
                    foreach($natureSubjectIds as$kn=>$vn ){
                        if($vs == $vn){
                            unset($natureCourseArr[$ks]);   //要取消的学科信息
                        }
                    }
                }
                $updateSubjectArr = $natureCourseArr;
            }
        }

        //题库
        //要取消的题库
        // $bankSubjectArr
        $natureBankId =  $noNatureBankId = [];
        // print_r($bankSubjectArr);
        // print_r($noBankSubjectArr);die;

        foreach ($bankSubjectArr as $key => $subject_id) {
            $bankArr = Bank::where($subject_id)->where(['is_del'=>0])->pluck('id')->toArray();

            if(!empty($bankArr)){
                foreach($bankArr as $k=>$v){
                    array_push($natureBankId,$v);
                }
            }
        }

        if(!empty($natureBankId)){
            //除要取消的题库
            //$noNaturecourseSubjectArr
            if(!empty($noBankSubjectArr)){
                foreach($noBankSubjectArr as $key =>$subjectid){
                    $bankArr = Bank::where($subjectid)->where(['is_del'=>0])->pluck('id')->toArray();
                    if(!empty($bankArr)){
                        foreach($bankArr as $k=>$v){
                            array_push($noNatureBankId,$v);
                        }
                    }
                }
            }
            $refBank =CourseRefBank::where(['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'is_del'=>0])->pluck('bank_id')->toArray(); //已经授权的题库
            if(!empty($refBank)){
                $natureBankId = array_unique($natureBankId);
                if(!empty($noNatureBankId)){
                    $noNatureBankId = array_unique($noNatureBankId);
                    $noNatureBankId = array_intersect($refBank,$noNatureBankId);
                    $arr = array_diff($natureBankId,$noNatureBankId);
                    if(!empty($arr)){
                        $updateBank = array_intersect($arr,$refBank);
                    }
                }else{
                    $updateBank = array_intersect($natureBankId,$refBank); //$updateBank 要取消授权的题库
                }
            }
        }

        $updateTime = date('Y-m-d H:i:s');
        if(!empty($updateTeacherArr)){
            foreach ($updateTeacherArr as $k => $vt) {
                $teacherRes =CourseRefTeacher::where(['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'teacher_id'=>$vt,'is_public'=>0])->update(['is_del'=>1,'update_at'=>$updateTime]);
                if(!$teacherRes){
                    return ['code'=>203,'msg'=>'教师取消授权未成功'];
                }
            }
        }
        if(!empty($updateSubjectArr)){
            $updateSubjectArr = array_unique($updateSubjectArr,SORT_REGULAR);
            foreach ($updateSubjectArr as $k => $vs) {
                $subjectRes =CourseRefSubject::where(['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'parent_id'=>$vs['parent_id'],'child_id'=>$vs['child_id']])->update(['is_del'=>1,'update_at'=>$updateTime]);

                if(!$subjectRes){
                    return ['code'=>203,'msg'=>'学科取消授权未成功'];
                }
            }
        }

        if(!empty($updatelvboArr)){
            $updatelvboArr = array_chunk($updatelvboArr,500);
            foreach($updatelvboArr as $key=>$lvbo){
                foreach ($lvbo as $k => $vl) {
                    $lvboRes =CourseRefResource::where(['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'resource_id'=>$vl,'type'=>0])->update(['is_del'=>1,'update_at'=>$updateTime]);
                    if(!$lvboRes){
                        return ['code'=>203,'msg'=>'录播资源取消授权未成功'];
                    }
                }
            }
        }

        if(!empty($updatezhiboArr)){
            $updatezhiboArr = array_chunk($updatezhiboArr,500);
            foreach($updatezhiboArr as $key=>$zhibo){
                foreach ($zhibo as $k => $vz) {
                    $zhiboRes =CourseRefResource::where(['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'resource_id'=>$vz,'type'=>1])->update(['is_del'=>1,'update_at'=>$updateTime]);
                    if(!$zhiboRes){
                        return ['code'=>203,'msg'=>'直播资源取消授权未成功'];
                    }
                }
            }
        }
        if(!empty($updateBank)){
            foreach ($updateBank as $k => $vb) {
                $BankRes =CourseRefBank::where(['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'bank_id'=>$vb])->update(['is_del'=>1,'update_at'=>$updateTime]);
                if(!$BankRes){
                    return ['code'=>203,'msg'=>'题库取消授权未成功'];
                }
            }
        }
        if(!empty($courseIds)){
            foreach ($courseIds as $key => $vc) {
                $courseRes =self::where(['from_school_id'=>$school_pid,'to_school_id'=>$school_id,'course_id'=>$vc])->update(['is_del'=>1,'update_at'=>$updateTime]);
                if(!$courseRes){
                    return ['code'=>203,'msg'=>'课程取消授权未成功'];
                }
            }
        }
        return ['code'=>200,'msg'=>'课程取消授权成功'];
    }


}
