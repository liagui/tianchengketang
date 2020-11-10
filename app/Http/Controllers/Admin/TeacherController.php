<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Coures;
use App\Models\CourseRefTeacher;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\Teacher;

class TeacherController extends Controller {
    /*
     * @param  description   添加讲师教务的方法
     * @param  参数说明       body包含以下参数[
     *     head_icon    头像
     *     phone        手机号
     *     real_name    讲师姓名/教务姓名
     *     sex          性别
     *     qq           QQ号码
     *     wechat       微信号
     *     parent_id    学科一级分类id
     *     child_id     学科二级分类id
     *     describe     讲师描述/教务描述
     *     content      讲师详情
     *     type         老师类型(1代表教务,2代表讲师)
     * ]
     * @param author    dzj
     * @param ctime     2020-04-25
     */
    public function doInsertTeacher() {
        //获取提交的参数
        try{
            $data = Teacher::doInsertTeacher(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '添加成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   更改讲师教务的方法
     * @param  参数说明       body包含以下参数[
     *     teacher_id   讲师或教务id
     *     head_icon    头像
     *     phone        手机号
     *     real_name    讲师姓名/教务姓名
     *     sex          性别
     *     qq           QQ号码
     *     wechat       微信号
     *     parent_id    学科一级分类id
     *     child_id     学科二级分类id
     *     describe     讲师描述/教务描述
     *     content      讲师详情
     *     teacher_id   老师id
     * ]
     * @param author    dzj
     * @param ctime     2020-04-25
     */
    public function doUpdateTeacher() {
        //获取提交的参数
        try{
            $data = Teacher::doUpdateTeacher(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '更改成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    删除老师的方法
     * @param  参数说明         body包含以下参数[
     *      teacher_id   讲师或教务id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-25
     */
    public function doDeleteTeacher(){
        //获取提交的参数
        try{
            $data = Teacher::doDeleteTeacher(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '删除成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    判断是否授权讲师教务
     * @param  author          dzj
     * @param  ctime           2020-07-14
     * return  array
     */
    public function getTeacherIsAuth(){
        //获取提交的参数
        try{
            $data = Teacher::getTeacherIsAuth(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '此老师未授权']);
            } else {
                return response()->json(['code' => 203 , 'msg' => '此老师已授权']);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    推荐老师的方法
     * @param  参数说明         body包含以下参数[
     *      is_recommend   是否推荐(1代表推荐,2代表不推荐)
     *      teacher_id   讲师或教务id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-04-25
     */
    public function doRecommendTeacher(){
        //获取提交的参数
        try{
            $data = Teacher::doRecommendTeacher(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '操作成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  descriptsion    讲师/教务启用/禁用方法
     * @param  参数说明         body包含以下参数[
     *      teacher_id   讲师或教务id
     * ]
     * @param  author          dzj
     * @param  ctime           2020-06-17
     */
    public function doForbidTeacher(){
        //获取提交的参数
        try{
            $data = Teacher::doForbidTeacher(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '操作成功']);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   根据讲师或教务id获取详细信息
     * @param  参数说明       body包含以下参数[
     *     teacher_id   讲师或教务id
     * ]
     * @param author    dzj
     * @param ctime     2020-04-25
     */
    public function getTeacherInfoById(){
        //获取提交的参数
        try{
            $data = Teacher::getTeacherInfoById(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取老师信息成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   讲师或教务列表
     * @param  参数说明       body包含以下参数[
     *     real_name   讲师或教务姓名
     *     type        老师类型(1代表教务,2代表讲师)
     * ]
     * @param author    dzj
     * @param ctime     2020-04-25
     */
    public function getTeacherList(){
        //获取提交的参数
        try{
            $data = Teacher::getTeacherList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取老师列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    /*
     * @param  description   讲师或教务搜索列表
     * @param  参数说明       body包含以下参数[
     *     parent_id     学科分类id
     *     real_name     老师姓名
     * ]
     * @param author    dzj
     * @param ctime     2020-04-29
    */
    public function getTeacherSearchList(){
        //获取提交的参数
        try{
            //判断token或者body是否为空
            /*if(!empty($request->input('token')) && !empty($request->input('body'))){
                $rsa_data = app('rsa')->servicersadecrypt($request);
            } else {
                $rsa_data = [];
            }*/

            //获取讲师教务搜索列表
            $data = Teacher::getTeacherSearchList(self::$accept_data);
            if($data['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '获取老师搜索列表成功' , 'data' => $data['data']]);
            } else {
                return response()->json(['code' => $data['code'] , 'msg' => $data['msg']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }

    //列表
    public function getListByIndexSet()
    {
        $topNum = empty(self::$accept_data['top_num']) ? 1 : self::$accept_data['top_num'];
        $isRecommend = isset(self::$accept_data['is_recommend']) ? self::$accept_data['is_recommend'] : 1;
        $schoolId = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;

        $limit = $topNum;
        $courseRefTeacherQuery = CourseRefTeacher::query()->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_ref_teacher.teacher_id')
            ->where([
                    'to_school_id' => $schoolId,
                    'type' => 2
            ])
            ->select(
                'ld_lecturer_educationa.id','ld_lecturer_educationa.head_icon',
                'ld_lecturer_educationa.real_name','ld_lecturer_educationa.describe',
                'ld_lecturer_educationa.number','ld_lecturer_educationa.teacher_icon'
            ); //授权讲师
        if ($isRecommend == 1) {
            $courseRefTeacherQuery->orderBy('ld_lecturer_educationa.is_recommend', 'desc');
        }
        $courseRefTeacher = $courseRefTeacherQuery->limit($limit)
            ->get()
            ->toArray(); //授权讲师

        $courseRefTeacher = array_unique($courseRefTeacher, SORT_REGULAR);
        $count = count($courseRefTeacher);
        if($count >0){ //授权讲师信息
            foreach($courseRefTeacher as $key=>&$teacher){
                $natureCourseArr =  CourseSchool::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course_school.course_id')
                    ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                    ->where(['ld_course_school.is_del'=>0,'ld_course_school.to_school_id'=>$schoolId,'ld_course_school.status'=>1,'ld_lecturer_educationa.id'=>$teacher['id']])
                    ->select('ld_course_school.cover','ld_course_school.title','ld_course_school.pricing','ld_course_school.buy_num','ld_lecturer_educationa.id as teacher_id','ld_course_school.id as course_id')
                    ->get()->toArray();
                $courseIds = array_column($natureCourseArr, 'course_id');
                $teacher['number'] = count($natureCourseArr);//开课数量
                $sumNatureCourseArr = array_sum(array_column($natureCourseArr,'buy_num'));//虚拟购买量
                $realityBuyum= Order::whereIn('class_id',$courseIds)->where(['school_id'=>$schoolId,'nature'=>1,'status'=>2])->whereIn('pay_status',[3,4])->count();//实际购买量（授权课程订单class_id 对应的是ld_course_school 的id ）
                $teacher['student_number'] = $sumNatureCourseArr+$realityBuyum;
                $teacher['is_nature'] = 1;
                $teacher['star_num']= 5;
            }
        }
        if($count<$limit) {
            //自增讲师信息
            $teacherDataQuery = Teacher::where(['school_id'=>$schoolId,'is_del'=>0,'type'=>2])->orderBy('number','desc')->select('id','head_icon','real_name','describe','number','teacher_icon');
            if ($isRecommend == 1) {
                $teacherDataQuery->orderBy('is_recommend', 'desc');
            }

            $teacherData = $teacherDataQuery->limit($limit-$count)
                ->get()
                ->toArray();
            $teacherDataCount = count($teacherData);
            if($teacherDataCount >0){
                foreach($teacherData as $key=>&$vv){
                    $couresArr  = Coures::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course.id')
                        ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                        ->where(['ld_course.is_del'=>0,'ld_course.school_id'=>$schoolId,'ld_course.status'=>1,'ld_lecturer_educationa.id'=>$vv['id']])
                        ->select('ld_course.cover','ld_course.title','ld_course.pricing','ld_course.buy_num','ld_lecturer_educationa.id as teacher_id','ld_course.id as course_id')
                        ->get()->toArray();

                    $courseIds = array_column($couresArr, 'course_id');
                    $vv['number'] = count($couresArr);//开课数量
                    $sumNatureCourseArr = array_sum(array_column($couresArr,'buy_num'));//虚拟购买量
                    $realityBuyum = Order::whereIn('class_id',$courseIds)->where(['school_id'=>$schoolId,'nature'=>0,'status'=>2])->whereIn('pay_status',[3,4])->count();//实际购买量
                    $vv['student_number'] = $sumNatureCourseArr+$realityBuyum;
                    $vv['grade'] =  '5.0';
                    $vv['star_num'] = 5;
                    $vv['is_nature'] = 0;
                }
            }
            $recomendTeacherArr=array_merge($courseRefTeacher,$teacherData);
        }else{
            $recomendTeacherArr = $courseRefTeacher;
        }
        return response()->json(['code'=>200,'msg'=>'Success','data'=>$recomendTeacherArr]);

    }

}
