<?php
namespace App\Models;

use App\Models\AdminLog;
use App\Models\CourseStocks;
use App\Models\SchoolOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Log;

/**
 * 直播商管理
 * @author laoxian
 */
class liveService extends Model {
    //指定别的表名   权限表
    public $table = 'ld_live_service';
    //时间戳设置
    public $timestamps = false;

    protected $fillable = [
        'id','name','isshow','short','create_at'
    ];

    protected $hidden = [
        'delete_at'
    ];

    //可批量修改字段
    protected static $multiFields = [
        'delete_at','isshow'
    ];

    //错误信息
    public static function message()
    {
        return [
            'name.required'  => json_encode(['code'=>'201','msg'=>'直播服务商名称不能为空']),
            'isshow.integer'   => json_encode(['code'=>'202','msg'=>'状态参数不合法']),
        ];
    }

    //批量库存错误信息
    public static function stocksMessage()
    {
        return [
            'course_id.required'  => json_encode(['code'=>'201','msg'=>'课程不能为空']),
            'add_number.required'   => json_encode(['code'=>'202','msg'=>'库存数不能为空']),
            'moneys.required'   => json_encode(['code'=>'202','msg'=>'输入金额不能为空']),
        ];
    }

    /**
     * 添加
     * @param [
     *  name string 直播商家名称
     *  isshow int 1=可用,2=不可用
     *  short string 描述
     * ]
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function add($params)
    {
        //补充参数并添加
        $lastid = self::insertGetId($params);
        if(!$lastid){
            return ['code'=>203,'msg'=>'添加失败, 请重试'];
        }

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/insert' ,
            'operate_method' =>  'insert' ,
            'content'        =>  '新增数据'.json_encode($params) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return ['code'=>200,'msg'=>'success'];//成功
    }

    /**
     * 查看记录
     * @param [
     *  page int 页码
     *  pagesize int 页大小
     * ]
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function getlist($params)
    {
        $page = (int) (isset($params['page']) && $params['page'])?$params['page']:1;
        $pagesize = (int) (isset($params['pagesize']) && $params['pagesize'])?$params['pagesize']:15;

        //固定条件
        $whereArr = [
            'delete_at'=>null
        ];

        //搜索条件
        if(isset($params['name']) && $params['name']){
            $whereArr[] = ['name','like','%'.$params['name'].'%'];
        }

        //总数
        $total = self::where($whereArr)->count();
        //结果集
        $list = self::where($whereArr)->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();
        $data = [
            'total'=>$total,
            'list'=>$list
        ];
        return ['code'=>200,'msg'=>'success','data'=>$data];
    }

    /**
     * 获取单条
     * @param id int id标识
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function detail($params){
        $id = isset($params['id'])?$params['id']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }

        $row = self::where('id',$id)->first();
        return ['code'=>200,'msg'=>'success','data'=>$row];
    }

    /**
     * 修改
     * @param [
     *  name string 直播商家名称
     *  isshow int 1=可用,2=不可用
     *  short string 描述
     * ]
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function doedit($params){
        $id = isset($params['id'])?$params['id']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }
        $row = self::where('id',$id)->update($params);

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/doedit' ,
            'operate_method' =>  'doedit' ,
            'content'        =>  '修改'.json_encode($params) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return ['code'=>200,'msg'=>'success,影响了'.$row.'行'];
    }

    /**
     * 删除
     * @param id int
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function dodelete($params){
        $id = isset($params['id'])?$params['id']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }
        $row = self::where('id',$id)->update(['delete_at'=>time()]);

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/delete' ,
            'operate_method' =>  'delete' ,
            'content'        =>  '删除数据'.json_encode($params) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return ['code'=>200,'msg'=>'success,影响了'.$row.'行'];
    }

    /**
     * 批量更新
     * @param ids string 逗号连接的id字符串
     * @param param string 字段
     * @param value string 值
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function domulti($params){
        $ids = isset($params['ids'])?$params['ids']:die();
        $key = isset($params['params'])?$params['params']:die();
        $value = isset($params['value'])?$params['value']:die();

        if(!in_array($key,self::$multiFields)){
            return ['code'=>201,'msg'=>'不合法的key'];
        }

        //整理
        $idarr = explode(',',$ids);
        $value = $key=='delete_at'?time():$value;

        $i = self::whereIn('id',$idarr)->update([$key=>$value]);
        /*foreach($idarr as $a){
            if(is_numeric($a)){
                $res = self::where('id',$id)->update([$key=>$value]);
                $i+=$res;
            }
        }*/
        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/domulti' ,
            'operate_method' =>  'update' ,
            'content'        =>  '修改数据'.json_encode($params) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return ['code'=>200,'msg'=>'success,影响了'.$i.'行'];
    }

    /**
     * 修改网校直播商
     * @param [
     *  schoolid int 网校
     *  liveid int 直播类别
     * ]
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function updateLivetype($params){
        $id = isset($params['liveid'])?$params['liveid']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }
        $row = self::where('id',$id)->value('id');
        if(!$row){
            return ['code'=>202,'msg'=>'找不到当前直播信息'];
        }

        //执行修改
        $res = School::where('id',$params['schoolid'])->update(['livetype'=>$id]);

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/updateLivetype' ,
            'operate_method' =>  'update' ,
            'content'        =>  '修改数据'.json_encode($params) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return ['code'=>200,'msg'=>'success'];
    }

    /**
     * 批量添加库存
     * @author laoxian
     * @return array
     */
    public static function doaddStocks($params)
    {
        if(isset($params['/admin/dashboard/course/addMultiStocks'])) unset($params['/admin/dashboard/course/addMultiStocks']);

        $params['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;//当前登录账号id
        $params['school_pid'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;//当前登录学校id
        $params['school_id'] = $params['schoolid'];
        unset($params['schoolid']);
        //可用测试信息, 学校3, 已出售课程,75,63,135,12,63,52 //授权表,282,285,286,293,296
        $courseArr = explode(',',trim($params['course_id'],','));//课程id组
        $stocksArrs = explode(',',trim($params['add_number'],','));//库存数组
        $moneyArrs = explode(',',trim($params['moneys'],','));//金额组
        unset($params['moneys']);
        if( count($courseArr) !=  count($stocksArrs) || count($stocksArrs) != count($moneyArrs) ){
            return ['code'=>203,'msg'=>'请检查输入数据是否完整'];
        }

        //将库存转换为授权id=>库存
        $stocksArr = [];
        foreach($stocksArrs as $k=>$v){
            $stocksArr[$courseArr[$k]] = $v;
        }
        //将基恩转换为授权id=>金额
        $moneysArr = [];
        foreach($moneyArrs as $k=>$v){
            $moneysArr[$courseArr[$k]] = $v;
        }
        $params['course_id'] = 0;//
        $params['add_number'] = 0;//
        $params['is_del'] = 1;//预定义不可用状态, 待审核通过后改为正常状态, is_del = 0;
        $params['is_forbid'] = 1;//预定义不可用状态, 待审核通过后改为正常状态, is_forbid = 0;

        //整理课程的真实课程id 与 总库存 与 销售量
        $record = self::courseStocksRecord($courseArr,$params['school_id'],$params['school_pid']);
        $courseidArr = $record['courseidArr'];//课程id组,授权id=>真实课程id
        $sum_current_numberArr = $record['sum_current_numberArr'];//课程总库存组
        $residue_numberArr = $record['residue_numberArr'];//课程销量组
        $priceArr = $record['prices'];//课程授权价格

        $oid = SchoolOrder::generateOid();
        $params['oid'] = $oid;

        //遍历添加库存 待计算金额
        $course_stocks_tmp = [];//储存可入库的库存二维数组
        $money = 0;//预定于订单总价
        foreach($courseidArr as $k=>$v){
            if((int)$stocksArr[$k] == 0){
                return ['code'=>204,'msg'=>'添加库存数不能为0'];
            }

            //得到当前课程已销售数目 与 总库存
            $residue_number = isset($residue_numberArr[$v])?$residue_numberArr[$v]:0;
            $sum_current_number = isset($sum_current_numberArr[$v])?$sum_current_numberArr[$v]:0;
            //当前课程
            $params['current_number'] = $residue_number<=0 ?$sum_current_number:(int)$sum_current_number-(int)$residue_number;
            if((int)$params['current_number']+(int)$params['add_number'] <0){
                return ['code'=>205,'msg'=>'添加库存数不能小于剩余库存数'];
            }
            $params['create_at'] = date('Y-m-d H:i:s');
            $params['course_id'] = $v;//课程
            $params['add_number'] = $stocksArr[$k];//本次添加库存数目
            $params['price'] = $priceArr[$v]?$priceArr[$v]:0;//授权单价
            //$money += $params['price']*$params['add_number'];//单课程的 价格*数量
            $money += $moneysArr[$k];//使用前台传过来的金额
            $course_stocks_tmp[] = $params;
        }

        //开启事务
        DB::beginTransaction();
        try {
            //if(isset($course_stocks_tmp['/admin/dashboard/course/addMultiStocks'])) unset($course_stocks_tmp['/admin/dashboard/course/addMultiStocks']);
            $res = courseStocks::insert($course_stocks_tmp);
            if(!$res){
                DB::rollBack();
                return ['code'=>206,'msg'=>'网络错误,请重试！'];
            }

            //遍历添加库存表完成(is_del=1,未生效的库存), 执行订单入库
            $order = [
                'oid' => $oid,
                'school_id' => $params['school_id'],
                'admin_id' => $params['admin_id'],
                'type' => 7,//批量添加库存
                'paytype' => 1,//内部支付
                'status' => 1,//待审核
                'online' => 0,//线下订单
                'money' => $money,
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }
            //将传输数据恢复原始模样插入日志表
            $parsms['course_id'] = implode(',',$courseArr);
            $parsms['add_number'] = implode(',',$stocksArrs);
            AdminLog::insertAdminLog([
                'admin_id'       =>  $params['admin_id'] ,
                'module_name'    =>  'SchoolCourseData' ,
                'route_url'      =>  'admin/SchoolCourseData/addMultiStocks' ,
                'operate_method' =>  'insert',
                'content'        =>  '批量添加库存'.json_encode($params),
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            DB::commit();

            Log::info('批量库存_库存表'.json_encode($course_stocks_tmp));
            return ['code'=>200,'msg'=>'添加成功'];

        }catch(\Exception $e){
            DB::rollback();
            return ['code'=>207,'msg'=>$e->getMessage(). $e->getLine()];
        }
    }

    /**
     * 批量取消授权
     */
    public static function cancalCourseSchool($params)
    {
        $arr = $subjectArr =
        $bankids = $questionIds = $updateTeacherArr = $updateSubjectArr =
        $updatelvboArr = $updatezhiboArr = $updateBank = $teacherIdArr =
        $nonatureCourseId =    $noNatuerTeacher_ids =  [];

        $courseIds =[$params['course_id']];
        if(empty($courseIds)){
            return ['code'=>205,'msg'=>'请选择取消授权课程'];
        }
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前学校id
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0; //当前登录学校的状态
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0; //当前登录的用户id
        $schoolArr =Admin::where(['school_id'=>$body['school_id'],'is_del'=>1])->first(); //前端传学校的id

        if($body['is_public'] == 0){
            //课程
            $natureData = CourseSchool::whereIn('id',$courseIds)->where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->select('course_id')->first();
            if(empty($natureData)){
                return ['code'=>207,'msg'=>'课程已经取消授权'];
            }
            $courseIds = [$natureData['course_id']];
            $nature = self::whereIn('course_id',$courseIds)->where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->get()->toArray(); //要取消的授权的课程
            if(empty($nature)){
                return ['code'=>207,'msg'=>'课程已经取消授权!'];
            }

            //取消授权课程的  科目
            foreach ($nature  as $kk => $vv) {
                $natureCourseArr[$kk]['parent_id'] = $vv['parent_id'];
                $natureCourseArr[$kk]['child_id'] = $vv['child_id'];
            }


            //非取消授权课程
            $noNatureCourse = self::whereNotIn('course_id',$courseIds)->where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0])->get()->toArray();//除取消授权课程的信息
            if(!empty($noNatureCourse)){
                foreach($noNatureCourse as $k=>$v){
                    $noNaturecourseSubjectArr[$k]['parent_id'] = $v['parent_id'];//非取消授权的课程类别组
                    $noNaturecourseSubjectArr[$k]['child_id'] = $v['child_id'];//非取消授权的课程类别组
                    array_push($nonatureCourseId,$v['course_id']);//非取消授权的课程id组别
                }
            }

            //要取消的教师信息
            $teachers_ids = Couresteacher::whereIn('course_id',$courseIds)->where(['is_del'=>0])->pluck('teacher_id')->toArray();
            if(!empty($nonatureCourseId)){
                //不取消的老师id组别
                $noNatuerTeacher_ids  =  Couresteacher::whereIn('course_id',$nonatureCourseId)->where(['is_del'=>0])->pluck('teacher_id')->toArray();
            }

            //现已经授权过的讲师
            $refTeacherArr  = CourseRefTeacher::where(['from_school_id'=>$school_id,'to_school_id'=>$body['school_id'],'is_del'=>0,'is_public'=>0])->pluck('teacher_id')->toArray();
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
                    'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
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
     * 获取课程库存
     * @param $courseArr array 授权课程id组
     * @param $school_id int 学校
     * @param $school_pid int 发起授权学校
     * @author laoxian
     * @return array
     */
    public static function courseStocksRecord($courseArr,$school_id,$school_pid)
    {

        //前端传的course_id 为ld_course_school自增id// 查询以授权id为key, courseid为value的数组
        if(count($courseArr)==1) $courseArr[] = $courseArr[0];
        $courseidArr = CourseSchool::whereIn('id',$courseArr)->pluck('course_id','id')->toArray();
        //print_r($courseidArr);die();

        //当前已经添加总库存
        $sum_current_numberArr = [];
        if(count($courseidArr)==1){
            $courseidArr_tmp = $courseidArr;
            $courseidArr_tmp[] = 0;
        }else{
            $courseidArr_tmp = $courseidArr;

        }
        $sum_current_number = courseStocks::where('school_id',$school_id)
            ->whereIn('course_id',$courseidArr_tmp)
            ->where(['school_pid'=>$school_pid,'is_del'=>0])
            ->orderBy('id','desc')
            ->select('course_id','add_number')->get()->toArray();
        //课程=>总库存
        foreach($sum_current_number as $k=>$v){
            if(isset($sum_current_numberArr[$v['course_id']])){
                $sum_current_numberArr[$v['course_id']] += $v['add_number'];
            }else{
                $sum_current_numberArr[$v['course_id']] = $v['add_number'];
            }
        }

        //购买量
        $residue_numberArr = [];
        $residue_numbers = Order::whereIn('class_id',$courseArr)//订单表存储的授权课程的授权表id
            ->where('pay_status',[3,4])//尾款 or 全款
            //暂时不用当前写法->where(['school_id'=>$school_id,'oa_status'=>1,'nature'=>1,'status'=>2])
            //暂时不用当前写法->groupBy('class_id')
            ->select('class_id','id')->get()->toArray();//DB::raw(",count(id) as used_stocks")

        //课程=>购买量
        foreach($residue_numbers as $k=>$v){
            if(isset($residue_numberArr[$v['class_id']])){
                $residue_numberArr[$v['class_id']] += 1;
            }else{
                $residue_numberArr[$v['class_id']] = 1;
            }
        }

        //天成单价 = 授权单价
        $priceArr = Coures::whereIn('id',$courseidArr)->pluck('impower_price as price','id')->toArray();

        return [
            'sum_current_numberArr'=>$sum_current_numberArr,
            'residue_numberArr'=>$residue_numberArr,
            'courseidArr'=>$courseidArr,
            'prices'=>$priceArr,
        ];
    }

    /**
     * 展示已授权课程
     */
    public static function onlyCourseSchool($schoolid)
    {
        //预定义条件
        $whereArr = [
            ['ld_course_school.to_school_id','=',$schoolid],//学校
            ['ld_course_school.is_del','=',0],//未删除
        ];

        //
        $field = [
            'ld_course_school.course_id as id','ld_course_school.parent_id','ld_course_school.child_id',
            'ld_course_school.title','ld_course_school.cover','method.method_id'];
        $orderby = 'ld_course_school.course_id';
        //总校课程
        $lists = CourseSchool::leftJoin('ld_course_method as method','ld_course_school.course_id','=','method.course_id')
            ->where($whereArr)->select($field)->orderBy($orderby)->get()->toArray();
        $lists = json_decode(json_encode($lists),true);

        //存储学科
        $subjectids = [];
        if(!empty($lists)){
            foreach($lists as $k=>$v){
                $subjectids[] = $v['parent_id'];
                $subjectids[] = $v['child_id'];
            }
            //科目名称
            if(count($subjectids)==1) $subjectids[] = $subjectids[0];
            $subjectArr = DB::table('ld_course_subject')
                ->whereIn('id',$subjectids)
                ->pluck('subject_name','id');
        }
        $methodArr = [1=>'直播','2'=>'录播',3=>'其他'];
        if(!empty($lists)){
            foreach($lists  as $k=>&$v){
                $v['parent_name'] = isset($subjectArr[$v['parent_id']])?$subjectArr[$v['parent_id']]:'';
                $v['child_name'] = isset($subjectArr[$v['child_id']])?$subjectArr[$v['child_id']]:'';

                $v['method_name'] = isset($methodArr[$v['method_id']])?$methodArr[$v['method_id']]:'';
            }

        }

        $data = [
            'list'=>$lists,
        ];

        return ['code' => 200 , 'msg' => 'success','data'=>$data];

    }



}
