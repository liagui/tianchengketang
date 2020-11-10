<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SchoolOrder;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\DB;

class CourseStocks extends Model {
    //指定别的表名
    public $table = 'ld_course_stocks';
    //时间戳设置
    public $timestamps = false;
    //错误信息
    public static function message(){
        return [
            'school_id.required' => json_encode(['code'=>'201','msg'=>'学校标识不能为空']),
            'school_id.integer'  => json_encode(['code'=>'202','msg'=>'学校标识类型不合法']),
            'course_id.required' => json_encode(['code'=>'201','msg'=>'课程标识不能为空']),
            'course_id.integer'  => json_encode(['code'=>'202','msg'=>'课程标识类型不合法']),
        ];
    }
	/*
     * @param  descriptsion 库存列表
     * @param  $school_id   学校id
     * @param  $course_id   课程id
     * @param  author       lys
     * @param  ctime   2020/6/29
     * return  array
     *///暂时没有问题
    public static function getCourseStocksList($data){
    	$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;//当前登录学校id
        unset($data['/admin/courstocks/getList']);
        $CourseSchoolData = CourseSchool::where('id',$data['course_id'])->select('course_id')->first(); //前端传的course_id 为ld_course_school自增id
    	$info = self::where('school_id',$data['school_id'])->where(['school_pid'=>$school_id,'is_del'=>0,'course_id'=>$CourseSchoolData['course_id']])->orderBy('id','desc')->select('id','create_at','current_number','add_number','school_id')->get();
    	$sum_current_number = 0;

    	$residue_number = Order::whereIn('pay_status',[3,4])->where(['class_id'=>$data['course_id'],'school_id'=>$data['school_id'],'oa_status'=>1,'nature'=>1,'status'=>2])->count();
        if(!empty($info)){
            foreach($info as $k=>$v){
                $sum_current_number += $v['add_number'];
            }
        }
    	$residue_number = $sum_current_number <= 0||$residue_number<=0 ?$sum_current_number:(int)$sum_current_number-(int)$residue_number;
    	return ['code'=>200,'msg'=>'success','data'=>$info,'sum_current_number'=>$sum_current_number,'residue_number'=>$residue_number];
    }
    /*
     * @param  descriptsion 添加库存
     * @param  $school_id   学校id
     * @param  $course_id   课程id
     * @param  $add_number   添加库存数
     * @param  author       lys
     * @param  ctime   2020/6/29
     * return  array
     */
   	public static function doInsertStocks($data){
        unset($data['/admin/courstocks/doInsertStocks']);
   		$data['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;//当前登录账号id
   		$data['school_pid'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;//当前登录学校id
        $CourseSchoolData = CourseSchool::where('id',$data['course_id'])->select('course_id')->first(); //前端传的course_id 为ld_course_school自增id
   		$sum_current_number = self::where('school_id',$data['school_id'])->where(['school_pid'=>$data['school_pid'],'is_del'=>0,'course_id'=>$CourseSchoolData['course_id']])->orderBy('id','desc')->sum('add_number');//当前已经添加总库存

   		$residue_number = Order::whereIn('pay_status',[3,4])->where(['class_id'=>$data['course_id'],'school_id'=>$data['school_id'],'oa_status'=>1,'nature'=>1,'status'=>2])->count(); //使用数量
        if((int)$data['add_number'] == 0){
            return ['code'=>203,'msg'=>'添加库存数不能为0'];
        }
	   	$data['current_number'] = $residue_number<=0 ?$sum_current_number:(int)$sum_current_number-(int)$residue_number;  //剩余库存
        if((int)$data['current_number']+(int)$data['add_number'] <0){
            return ['code'=>203,'msg'=>'添加库存数不能小于剩余库存数'];
        }
   		$data['create_at'] = date('Y-m-d H:i:s');
        $data['course_id'] = $CourseSchoolData['course_id'];

        //////////2020/10/22 19:45 author laoxian
        //将库存表初始状态改为不可用状态, 并添加一个offline_order, 改订单审核通过后, 将绑定库存改为可用状态
        $oid = SchoolOrder::generateOid();
        $data['oid'] = $oid;
        $data['is_del'] = 1;//预定义不可用状态, 待审核通过后改为正常状态, is_del = 0;
        $data['is_forbid'] = 1;//预定义不可用状态, 待审核通过后改为正常状态, is_forbid = 0;
        $data['price'] = Coures::where('id',$data['course_id'])->value('impower_price')?:0;


        //开启事务
        DB::beginTransaction();
        try{
            $result = self::insert($data);
            if(!$result){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }
            //遍历添加库存表完成(is_del=1,未生效的库存), 执行订单入库
            $order = [
                'oid' => $oid,
                'school_id' => $data['school_id'],
                'admin_id' => $data['admin_id'],
                'type' => 6,//添加库存
                'paytype' => 1,//内部支付
                'status' => 1,//待审核
                'online' => 0,//线下订单
                'money' => $data['price']*$data['add_number'],//订单金额
                'apply_time' => date('Y-m-d H:i:s'),
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }
            DB::commit();
            Log::info('单个课程库存_库存表'.json_encode($data));

            //Log
            AdminLog::insertAdminLog([
                'admin_id'       =>   $data['admin_id'] ,
                'module_name'    =>  'Courstocks' ,
                'route_url'      =>  'admin/courstocks/doInsertStocks' ,
                'operate_method' =>  'insert',
                'content'        =>  '库存添加'.json_encode($data),
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code'=>200,'msg'=>'添加成功'];
        }catch(\Exception $e){
            DB::rollback();
            return ['code'=>207,'msg'=>$e->getMessage()];
        }
        ///////////////////////////////

   	}
	
	/**
     * 获取授权课程
     */
    public static function getGiveCourse($data)
    {
        //每页显示的条数
        $pagesize = isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //判断课程id为否为空
        if(empty($data['course_id']) || !is_numeric($data['course_id']) || $data['course_id'] <= 0){
            return ['code' => 202 , 'msg' => '课程id不能为空'];
        }
        //获取授权课程总数
        $count = CourseSchool::leftJoin('ld_school','ld_school.id','=','ld_course_school.to_school_id')
            ->where(['ld_course_school.course_id'=>$data['course_id'],'ld_course_school.status'=>1,'ld_course_school.is_del'=>0])->count();
        //授权课程
        $course_school = CourseSchool::leftJoin('ld_school','ld_school.id','=','ld_course_school.to_school_id')
                        ->where(['ld_course_school.course_id'=>$data['course_id'],'ld_course_school.status'=>1,'ld_course_school.is_del'=>0])
                        ->select('ld_school.name','ld_course_school.course_id','ld_course_school.to_school_id','ld_course_school.title')
                        ->offset($offset)->limit($pagesize)
                        ->get();
        if($course_school){
            $course_school = $course_school->toarray();
            foreach ($course_school as $k =>$v){
                //总库存
                $course_school[$k]['stocksCount'] = CourseStocks::where(['school_id'=>$v['to_school_id'],'course_id'=>$v['course_id'],'is_del'=>0,'is_forbid'=>0])->sum('add_number');
                //销量
                $course_school[$k]['num'] = Order::whereIn('pay_status',[3,4])->where(['school_id'=>$v['to_school_id'],'class_id'=>$v['course_id'],'oa_status'=>1,'nature'=>1,'status'=>2])->count();
                //现有库存
                $nowstocks = (int)$course_school[$k]['stocksCount'] - (int)$course_school[$k]['num'];
                $course_school[$k]['nowstocks'] = $nowstocks < 0 ? 0 : $nowstocks;
            }
            return ['code'=>200,'msg'=>'获取授权信息成功','data'=>$course_school,'total'=>$count];
        }
        return ['code'=>200,'msg'=>'获取授权信息成功','data'=>'','total'=>''];
    }

}
