<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
    public function getListByIndexSet(){
        $type = !isset($this->data['type']) || $this->data['type']<=0 ?0:$this->data['type'];

        $teacherArr = Teacher::where(['school_id'=>$this->school['id'],'is_del'=>0,'is_forbid'=>0,'type'=>2])->select('id','head_icon','real_name','describe','number','is_recommend','teacher_icon')->orderBy('number','desc')->get()->toArray(); //自增讲师

        $natureTeacherArr = CourseRefTeacher::leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_ref_teacher.teacher_id')
            ->where(['ld_course_ref_teacher.to_school_id'=>$this->school['id'],'ld_course_ref_teacher.is_del'=>0,'ld_lecturer_educationa.type'=>2])
            ->select('ld_lecturer_educationa.id','ld_lecturer_educationa.head_icon','ld_lecturer_educationa.real_name','ld_lecturer_educationa.describe','ld_lecturer_educationa.number','ld_lecturer_educationa.is_recommend','ld_lecturer_educationa.teacher_icon')->get()->toArray();//授权讲师

        if(!empty($natureTeacherArr)){

            foreach($natureTeacherArr as $key=>&$v){
                $natureCourseArr =  CourseSchool::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course_school.course_id')
                    ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                    ->where(['ld_course_school.is_del'=>0,'ld_course_school.to_school_id'=>$this->school['id'],'ld_course_school.status'=>1,'ld_lecturer_educationa.id'=>$v['id']])
                    ->select('ld_course_school.id as course_id ','ld_course_school.cover','ld_course_school.title','ld_course_school.pricing','ld_course_school.buy_num','ld_lecturer_educationa.id')
                    ->get()->toArray();
                $courseIds = array_column($natureCourseArr, 'course_id');
                $v['number'] = count($natureCourseArr);//开课数量
                $sumNatureCourseArr = array_sum(array_column($natureCourseArr,'buy_num'));//虚拟购买量
                $realityBuyum= Order::whereIn('class_id',$courseIds)->where(['school_id'=>$this->school['id'],'nature'=>1,'status'=>2])->whereIn('pay_status',[3,4])->count();//实际购买量 （授权课程订单class_id 对应的是ld_course_school 的id ）
                $v['student_number'] = $sumNatureCourseArr+$realityBuyum;
                $v['grade'] =  '5.0';
                $v['star_num'] = 5;
                $v['is_nature'] = 1;
            }
        }

        if(!empty($teacherArr)){
            foreach($teacherArr as $key=>&$vv){
                $couresArr  = Coures::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course.id')
                    ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                    ->where(['ld_course.is_del'=>0,'ld_course.school_id'=>$this->school['id'],'ld_course.status'=>1,'ld_lecturer_educationa.id'=>$vv['id']])
                    ->select('ld_course.cover','ld_course.title','ld_course.pricing','ld_course.buy_num','ld_lecturer_educationa.id','ld_course.id as course_id')
                    ->get()->toArray();

                $courseIds = array_column($couresArr, 'course_id');
                $vv['number'] = count($couresArr);//开课数量
                $sumNatureCourseArr = array_sum(array_column($couresArr,'buy_num'));//虚拟购买量
                $realityBuyum = Order::whereIn('class_id',$courseIds)->where(['school_id'=>$this->school['id'],'nature'=>0,'status'=>2])->whereIn('pay_status',[3,4])->count();//实际购买量
                $vv['student_number'] = $sumNatureCourseArr+$realityBuyum;
                $vv['grade'] =  '5.0';
                $vv['star_num'] = 5;
                $vv['is_nature'] = 0;
            }
        }

        if(!empty($natureTeacherArr) || !empty($teacherArr)){
            $teacherData = array_merge($natureTeacherArr,$teacherArr);
            if( $type==1 ){
                $sort = array_column($teacherData, 'student_number');
                array_multisort($sort, SORT_DESC, $teacherData);
            }
            $teacherData = array_unique($teacherData, SORT_REGULAR);
        }else{
            $teacherData=[];
        }
        $pagesize = isset($this->data['pagesize']) && $this->data['pagesize'] > 0 ? $this->data['pagesize'] : 20;
        $page     = isset($this->data['page']) && $this->data['page'] > 0 ? $this->data['page'] : 1;

        $start=($page-1)*$pagesize;
        $limit_s=$start+$pagesize;
        $info=[];
        for($i=$start;$i<$limit_s;$i++){
            if(!empty($teacherData[$i])){
                array_push($info,$teacherData[$i]);
            }
        }
        return response()->json(['code'=>200,'msg'=>'Succes','data'=>$info,'total'=>count($teacherData)]);

    }

}
