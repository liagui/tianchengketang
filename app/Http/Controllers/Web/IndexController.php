<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Coures;
use App\Models\Couresmethod;
use App\Models\CourseRefResource;
use App\Models\CourseRefTeacher;
use App\Models\CourseSchool;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Articletype;
use App\Models\FootConfig;
use App\Models\Admin;
use App\Models\CouresSubject;
use App\Models\Subject;
use App\Models\Order;
use App\Models\WebLog;

class IndexController extends Controller {
    protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        // $this->school = School::where(['dns'=>$this->data['school_dns']])->first();
        $this->school = School::where(['dns'=>$this->data['dns']])->first(); //改前
        $this->userid = isset($this->data['user_info']['user_id'])?$this->data['user_info']['user_id']:0;
		//$this->school = $this->getWebSchoolInfo($this->data['dns']); //改后

    }
    /*
     * @param  description   首页轮播图接口
     * @param author    dzj
     * @param ctime     2020-05-25
     * return string
     */
    public function getChartList() {
        //获取提交的参数
        try{
            $rotation_chart_list = [
                [
                    'chart_id'     =>   1 ,
                    'title'        =>   '轮播图1' ,
                    'jump_url'     =>   '' ,
                    'pic_image'    =>   "https://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-06-17/159238101090725ee9ce52b4dbc.jpg" ,
                    'type'         =>   1 ,
                    'lession_info' => [
                        'lession_id'  => 1 ,
                        'lession_name'=> '课程名称1'
                    ]
                ] ,
                [
                    'chart_id'     =>   2 ,
                    'title'        =>   '轮播图2' ,
                    'jump_url'     =>   '' ,
                    'pic_image'    =>   "https://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-06-17/159238104323565ee9ce73db673.jpg" ,
                    'type'         =>   1 ,
                    'lession_info' =>   [
                        'lession_id'  => 0 ,
                        'lession_name'=> ''
                    ]
                ] ,
                [
                    'chart_id'     =>   3 ,
                    'title'        =>   '轮播图3' ,
                    'jump_url'     =>   '' ,
                    'pic_image'    =>   "https://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-06-17/159238106166285ee9ce85ea7e0.jpg" ,
                    'type'         =>   1 ,
                    'lession_info' => [
                        'lession_id'  => 2 ,
                        'lession_name'=> '课程名称2'
                    ]
                ]
            ];

            return response()->json(['code' => 200 , 'msg' => '获取轮播图列表成功' , 'data' => $rotation_chart_list]);
        } catch (\Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
    }



    //讲师列表
    public function teacherList(){
    	$limit = 8;
        $courseRefTeacher = CourseRefTeacher::leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_ref_teacher.teacher_id')
            ->where(['to_school_id'=>$this->school['id'],'type'=>2])
            ->select('ld_lecturer_educationa.id','ld_lecturer_educationa.head_icon','ld_lecturer_educationa.real_name','ld_lecturer_educationa.describe','ld_lecturer_educationa.number','ld_lecturer_educationa.teacher_icon')
            ->limit($limit)->get()->toArray(); //授权讲师
        $courseRefTeacher = array_unique($courseRefTeacher, SORT_REGULAR);
        $count = count($courseRefTeacher);
        if($count >0){ //授权讲师信息
            foreach($courseRefTeacher as $key=>&$teacher){
                $natureCourseArr =  CourseSchool::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course_school.course_id')
                        ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                        ->where(['ld_course_school.is_del'=>0,'ld_course_school.to_school_id'=>$this->school['id'],'ld_course_school.status'=>1,'ld_lecturer_educationa.id'=>$teacher['id']])
                        ->select('ld_course_school.cover','ld_course_school.title','ld_course_school.pricing','ld_course_school.buy_num','ld_lecturer_educationa.id as teacher_id','ld_course_school.id as course_id')
                        ->get()->toArray();
                $courseIds = array_column($natureCourseArr, 'course_id');
                $teacher['number'] = count($natureCourseArr);//开课数量
                $sumNatureCourseArr = array_sum(array_column($natureCourseArr,'buy_num'));//虚拟购买量
                $realityBuyum= Order::whereIn('class_id',$courseIds)->where(['school_id'=>$this->school['id'],'nature'=>1,'status'=>2])->whereIn('pay_status',[3,4])->count();//实际购买量（授权课程订单class_id 对应的是ld_course_school 的id ）
                $teacher['student_number'] = $sumNatureCourseArr+$realityBuyum;
                $teacher['is_nature'] = 1;
                $teacher['star_num']= 5;
            }
        }
    	if($count<$limit){
            //自增讲师信息
    		$teacherData = Teacher::where(['school_id'=>$this->school['id'],'is_del'=>0,'type'=>2])->orderBy('number','desc')->select('id','head_icon','real_name','describe','number','teacher_icon')->limit($limit-$count)->get()->toArray();
            $teacherDataCount = count($teacherData);
            if($teacherDataCount >0){
                foreach($teacherData as $key=>&$vv){
                    $couresArr  = Coures::leftJoin('ld_course_teacher','ld_course_teacher.course_id','=','ld_course.id')
                        ->leftJoin('ld_lecturer_educationa','ld_lecturer_educationa.id','=','ld_course_teacher.teacher_id')
                        ->where(['ld_course.is_del'=>0,'ld_course.school_id'=>$this->school['id'],'ld_course.status'=>1,'ld_lecturer_educationa.id'=>$vv['id']])
                        ->select('ld_course.cover','ld_course.title','ld_course.pricing','ld_course.buy_num','ld_lecturer_educationa.id as teacher_id','ld_course.id as course_id')
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
    		$recomendTeacherArr=array_merge($courseRefTeacher,$teacherData);
    	}else{
            $recomendTeacherArr = $courseRefTeacher;
        }
        // //添加日志操作
        // WebLog::insertWebLog([
        //     'admin_id'       =>  $this->userid  ,
        //     'module_name'    =>  'Index' ,
        //     'route_url'      =>  'web/index/teacher' ,
        //     'operate_method' =>  'select' ,
        //     'content'        =>  '名师列表'.json_encode($recomendTeacherArr) ,
        //     'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
        //     'create_at'      =>  date('Y-m-d H:i:s')
        // ]);
    	return response()->json(['code'=>200,'msg'=>'Success','data'=>$recomendTeacherArr]);
    }
    //新闻资讯
    public function newInformation(){

    	$limit = !isset($this->data['limit']) || empty($this->data['limit']) || $this->data['limit']<=0 ? 4 : $this->data['limit'];
    	$where = ['ld_article_type.school_id'=>$this->school['id'],'ld_article_type.status'=>1,'ld_article_type.is_del'=>1,'ld_article.is_recommend'=>1];
    	$news = Articletype::leftJoin('ld_article','ld_article.article_type_id','=','ld_article_type.id')
             ->where($where)
             ->orderBy('ld_article.update_at','desc')
             ->limit($limit)->get()->toArray();
        $count = count($news);
        if($count<$limit){
            $where = ['ld_article_type.school_id'=>$this->school['id'],'ld_article_type.status'=>1,'ld_article_type.is_del'=>1,'ld_article.is_recommend'=>0];
            $noRecommendNews = Articletype::leftJoin('ld_article','ld_article.article_type_id','=','ld_article_type.id')
             ->where($where)
             ->limit($limit-$count)->get()->toArray();
            $news = array_merge($news,$noRecommendNews);
        }
        // //添加日志操作
        // WebLog::insertWebLog([
        //     'admin_id'       =>  $this->userid  ,
        //     'module_name'    =>  'Index' ,
        //     'route_url'      =>  'web/index/news' ,
        //     'operate_method' =>  'select' ,
        //     'content'        =>  '新闻资讯'.json_encode($news) ,
        //     'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
        //     'create_at'      =>  date('Y-m-d H:i:s')
        // ]);
        return response()->json(['code'=>200,'msg'=>'Success','data'=>$news]);
    }
    //首页信息
    public function index(){
    	$arr = [];

        $arr['logo'] = empty($this->school['logo_url'])?'':$this->school['logo_url'];

        $arr['header'] = $arr['footer'] = $arr['icp'] = [];
    	$admin = Admin::where('school_id',$this->school['id'])->select('school_status')->first();

		$footer = FootConfig::where(['school_id'=>$this->school['id'],'is_del'=>0,'is_open'=>0,'is_show'=>0,'type'=>2])->select('id','parent_id','name','url','create_at')->get();
    	if(!empty($footer)){
    		$arr['footer'] = getParentsList($footer);
    	}
        $icp = FootConfig::where(['school_id'=>$this->school['id'],'is_del'=>0,'is_open'=>0,'is_show'=>0,'type'=>3])->select('name')->first();
        if(empty($icp)){
            $arr['icp'] = '';
        }else{
            $arr['icp'] = $icp['name'];
        }
        $arr['header'] = FootConfig::where(['school_id'=>$this->school['id'],'is_del'=>0,'is_open'=>0,'is_show'=>0,'type'=>1])->select('id','parent_id','name','url','create_at','text')->orderBy('sort')->get();
        $logo =  FootConfig::where(['school_id'=>$this->school['id'],'is_del'=>0,'is_open'=>0,'is_show'=>0,'type'=>4])->select('logo')->orderBy('sort')->first();
        if(empty($logo)){
            $arr['index_logo'] = '';
        }else{
            $arr['index_logo'] = $logo['logo'];
        }
        $arr['status'] = $admin['school_status'];
        $arr['school_status'] = in_array($this->school['is_forbid'],[0,2])?0:1;//	是否禁用：0是,1否,2禁用前台,3禁用后台

        // //添加日志操作
        // WebLog::insertWebLog([
        //     'admin_id'       =>  $this->userid  ,
        //     'module_name'    =>  'Index' ,
        //     'route_url'      =>  'web/index/index' ,
        //     'operate_method' =>  'select' ,
        //     'content'        =>  '进入首页'.json_encode($arr) ,
        //     'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
        //     'create_at'      =>  date('Y-m-d H:i:s')
        // ]);
    	return response()->json(['code'=>200,'msg'=>'Success','data'=>$arr]);
    }
    //精品课程
    public function course(){
    	$course =  $zizengCourseData = $natureCourseData = $CouresData = [];
    	$subjectOne = CouresSubject::where(['school_id'=>$this->school['id'],'is_open'=>0,'is_del'=>0,'parent_id'=>0])->select('id')->get()->toArray();//自增学科大类
        $natuerSubjectOne = CourseSchool::select('parent_id')->where(['to_school_id'=>$this->school['id'],'is_del'=>0,'status'=>1])->select('parent_id as id')->groupBy('parent_id')->get()->toArray();//授权学科大类
        if(!empty($natuerSubjectOne)){
            foreach($natuerSubjectOne as $key=>&$v){
                $subject_name= Subject::where(['id'=>$v['id'],'is_del'=>0])->select('subject_name')->first();
                $v['subject_name'] =$subject_name['subject_name'];
            }
        }
        if(!empty($subjectOne)){
            foreach($subjectOne as $key=>&$va){
                $subject_name = Subject::where(['id'=>$va['id'],'is_del'=>0])->select('subject_name')->first();
                 $va['subject_name'] =$subject_name['subject_name'];
            }
        }

        if(!empty($subjectOne)&& !empty($natuerSubjectOne)){

             $subject=array_merge($subjectOne,$natuerSubjectOne);
             $last_names = array_column($subject,'id');
             array_multisort($last_names,SORT_ASC,$subject);
        }else{
            $subject = empty($subjectOne) ?$natuerSubjectOne:$subjectOne;
        }
        $newArr = [];
        foreach ($subject as $key => $val) {
            $natureCourseData = CourseSchool::where(['to_school_id'=>$this->school['id'],'is_del'=>0,'parent_id'=>$val['id'],'status'=>1])->get()->toArray();//授权课程
            $CouresData = Coures::where(['school_id'=>$this->school['id'],'is_del'=>0,'parent_id'=>$val['id'],'status'=>1])->get()->toArray(); //自增
            if(!empty($CouresData)){
                    foreach($CouresData as $key=>&$zizeng){
                        $zizeng['buy_num'] = $zizeng['buy_num']+$zizeng['watch_num'];
                        $zizeng['nature'] = 0;
                    }
                }
            if(!empty($natureCourseData)){
                foreach($natureCourseData as $key=>&$nature){
                    $nature['buy_num'] = $nature['buy_num']+$nature['watch_num'];
                    $nature['nature'] = 1;
                }
            }
            if(!empty($natureCourseData) && !empty($CouresData)){
                $courseArr = array_chunk(array_merge($natureCourseData,$CouresData),8)[0];
            }else{
                $courseArr = empty($natureCourseData)?$CouresData:$natureCourseData;
                if(!empty($courseArr)){
                    $courseArr = array_chunk($courseArr,8)[0];
                }
            }
            $newArr[$val['id']] = $courseArr;
        }
    	$arr = [
            'course'=>$newArr,
            'subjectOne'=>$subject,
        ];
        // //添加日志操作
        // WebLog::insertWebLog([
        //     'admin_id'       =>  $this->userid  ,
        //     'module_name'    =>  'Index' ,
        //     'route_url'      =>  'web/index/course' ,
        //     'operate_method' =>  'select' ,
        //     'content'        =>  '首页精品课程'.json_encode($arr) ,
        //     'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
        //     'create_at'      =>  date('Y-m-d H:i:s')
        // ]);
        return response()->json(['code'=>200,'msg'=>'Success','data'=>$arr]);
    }
    //获取公司信息
    public function getCompany(){
        $company['name'] = isset($this->school['name']) ?$this->school['name']:'';
        $company['account_name'] = isset($this->school['account_name']) ?$this->school['account_name']:'';
        $company['account_num'] =  isset($this->school['account_num']) ?$this->school['account_num']:'';
        $company['open_bank'] =  isset($this->school['open_bank']) ?$this->school['open_bank']:'';
        if($company['account_name'] == '' && $company['account_num'] == '' &&  $company['open_bank'] == ''  ){
            return response()->json(['code'=>201,'msg'=>'Success']);
        }
        // //添加日志操作
        // WebLog::insertWebLog([
        //     'admin_id'       =>  $this->userid  ,
        //     'module_name'    =>  'Index' ,
        //     'route_url'      =>  'web/index/getCompany' ,
        //     'operate_method' =>  'select' ,
        //     'content'        =>  '查看公司信息'.json_encode($company) ,
        //     'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
        //     'create_at'      =>  date('Y-m-d H:i:s')
        // ]);
        return response()->json(['code'=>200,'msg'=>'Success','data'=>$company]);
    }
    public function getPay(){
        if(!isset($this->data['id']) || $this->data['id'] <=0){
            return response()->json(['code'=>201,'msg'=>'id为空或类型不合法']);
        }
        $FootConfigArr =FootConfig::where(['id'=>$this->data['id'],'is_del'=>0,'is_show'=>0])->select('text')->first();
        // //添加日志操作
        // WebLog::insertWebLog([
        //     'admin_id'       =>  $this->userid  ,
        //     'module_name'    =>  'Index' ,
        //     'route_url'      =>  'web/index/getPay' ,
        //     'operate_method' =>  'select' ,
        //     'content'        =>  '查看对公信息'.json_encode($FootConfigArr) ,
        //     'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
        //     'create_at'      =>  date('Y-m-d H:i:s')
        // ]);
        return response()->json(['code'=>200,'msg'=>'Success','data'=>$FootConfigArr]);
    }
}
