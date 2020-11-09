<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\SchoolOrder;
use Illuminate\Support\Facades\Log;

/**
 * 库存购物车
 * @author laoxian
 */
class StockShopCart extends Model {
    //指定别的表名
    public $table      = 'ld_stock_shopcart';
    //时间戳设置
    public $timestamps = false;

    //提示信息
    public static function message()
    {
        return [
            'courseid.required'   => json_encode(['code'=>'202','msg'=>'课程参数不能为空']),
            'courseid.integer'   => json_encode(['code'=>'202','msg'=>'课程参数不合法']),
            'gid.required'   => json_encode(['code'=>'202','msg'=>'购物车标识不能为空']),
            'gid.integer'   => json_encode(['code'=>'202','msg'=>'购物车标识不合法']),
            'operate.required'   => json_encode(['code'=>'202','msg'=>'操作方法不能为空']),
            'ncourseid.integer'   => json_encode(['code'=>'202','msg'=>'增加库存课程参数不合法']),
            'ncourseid.required'   => json_encode(['code'=>'202','msg'=>'增加库存课程参数不能为空']),
            'stocks.integer'   => json_encode(['code'=>'202','msg'=>'增加库存参数不合法']),
            'stocks.required'   => json_encode(['code'=>'202','msg'=>'增加库存参数不能为空']),
            'stocks.min'   => json_encode(['code'=>'202','msg'=>'增加库存参数不合法']),
        ];
    }

    /**
     * 课程展示
     */
    public static function courseIndex($params)
    {
        $pagesize = (int)isset($params['pagesize']) && $params['pagesize'] > 0 ? $params['pagesize'] : 20;
        $page     = isset($params['page']) && $params['page'] > 0 ? $params['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //预定义条件
        $whereArr = [
            ['ld_course.school_id','=',1],//总校
            ['ld_course.status','=',1],//在售
            ['ld_course.is_del','=',0],//未删除
        ];
        //一级学科
        if(isset($params['parentid']) && $params['parentid']){
            $whereArr[] = ['ld_course.parent_id','=',$params['parentid']];
        }
        //二级学科
        if(isset($params['childid']) && $params['childid']){
            $whereArr[] = ['ld_course.child_id','=',$params['childid']];
        }
        //课程类别  1直播, 2录播, 3其他
        if(isset($params['type']) && $params['type']){
            if(!in_array($params['type'],[1,2])){
                return ['code'=>203,'msg'=>'课程类别不合法'];
            }
            $whereArr[] = ['method.method_id','=',$params['type']];
        }
        //
        $field = [
            'ld_course.id','ld_course.parent_id','ld_course.child_id','ld_course.title',
            'ld_course.cover','ld_course.nature','ld_course.status','ld_course.pricing',
            'ld_course.buy_num','ld_course.impower_price','method.method_id'];
        $orderby = 'ld_course.id';
        //总校课程
        $query = Coures::leftJoin('ld_course_method as method','ld_course.id','=','method.course_id')->where($whereArr);
        $total = $query->count();
        $lists = $query->select($field)->orderBy($orderby)
            ->offset($offset)->limit($pagesize)->get()->toArray();

        //查找已授权课程
        $course_schoolids = CourseSchool::where('to_school_id',$params['schoolid'])->pluck('course_id')->toArray();
        //print_r($course_schoolids);die();

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

                $v['buy_nember'] = 0;//销售量
                $v['sum_nember'] = 0;//库存总量
                $v['surplus'] = 0;//剩余库存
                $v['ishave'] = 0;
                //已授权课程
                if($course_schoolids && in_array($v['id'],$course_schoolids)){
                    $v['ishave'] = 1;
                    $v['parent_name'] = isset($subjectArr[$v['parent_id']])?$subjectArr[$v['parent_id']]:'';
                    $v['child_name'] = isset($subjectArr[$v['child_id']])?$subjectArr[$v['child_id']]:'';

                    $v['buy_nember'] = Order::whereIn('pay_status',[3,4])->where('nature',0)->where(['school_id'=>$params['schoolid'],'class_id'=>$v['id'],'status'=>2,'oa_status'=>1])->count();
                    $v['sum_nember'] = CourseStocks::where(['school_id'=>$params['schoolid'],'course_id'=>$v['id'],'is_del'=>0])->sum('add_number');
                    $v['surplus'] = $v['sum_nember']-$v['buy_nember'] <=0 ?0:$v['sum_nember']-$v['buy_nember']; //剩余库存量
                }

                $v['method_name'] = isset($methodArr[$v['method_id']])?$methodArr[$v['method_id']]:'';
            }

        }
        $data = [
            'list'=>$lists,
            'total'=>$total,
            'total_page'=>ceil($total/$pagesize),
        ];

        return ['code' => 200 , 'msg' => 'success','data'=>$data];
    }

    /**
     * 加入购物车
     */
    public static function addShopCart($params)
    {
        //拿到课程真实id
        $coursesid = coures::where('id',$params['courseid'])->value('id');
        if(!$coursesid){
            return ['code'=>209,'msg'=>'找不到当前课程'];
        }
        /*$course_id = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('id',$params['courseid'])->value('course_id');
        if(!$course_id){
            return ['code'=>209,'msg'=>'找不到当前课程'];
        }*/

        //查看是否已存在
        $gid = self::where('course_id',$params['courseid'])->where('school_id',$params['schoolid'])->value('id');
        if($gid){
            //$res = self::where('id',$gid)->increment('number');
            return ['code'=>205,'msg'=>'购物车已经存在当前课程'];
        }

        //加入购物车
        $price = (int) Coures::where('id',$params['courseid'])->value('impower_price');//获取授权价格
        $data['school_id'] = $params['schoolid'];
        $data['course_id'] = $params['courseid'];
        $data['price'] = $price;
        $data['number'] = 1;

        $res = self::insert($data);
        if($res){
            $arr = ['code'=>200,'msg'=>'success'];
        }else{
            $arr = ['code'=>201,'msg'=>'加入购物车失败'];
        }
        return $arr;
    }

    /**
     * 购物车查看
     */
    public static function ShopCart($schoolid)
    {
        $lists = StockShopCart::where('school_id',$schoolid)->get()->toArray();
        //课程名称, 学科, 单价, 数量
        $course_subject = [];
        $courseids = [];
        $subjects = [];
        $title = [];
        $subjectArr = [];
        $coverArr = [];
        foreach($lists as $k=>$v){
            $courseids[] = $v['course_id'];
        }
        if($courseids){
            //课程名称,学科id
            //if(count($courseids)==1) $courseids[] = $courseids[0];
            $courseArr = Coures::whereIn('id',$courseids)
                ->select('id','title','parent_id','child_id','cover','impower_price as price')->get()->toArray();
            foreach($courseArr as $k=>$v){
                $course_subject[$v['id']]['parentid'] = $v['parent_id'];
                $course_subject[$v['id']]['childid'] = $v['child_id'];
                $subjects[] = $v['parent_id'];
                $subjects[] = $v['child_id'];
                $title[$v['id']] = $v['title'];
                $coverArr[$v['id']] = $v['cover'];
            }
            if($subjects){
                //科目名称
                $subjectArr = DB::table('ld_course_subject')
                    ->whereIn('id',$subjects)
                    ->pluck('subject_name','id');
            }
            //授权价格
            //$priceArr = Coures::whereIn('id',$courseids)->pluck('impower_price','id');
        }
        foreach($lists as $k=>$v)
        {
            $lists[$k]['title'] = isset($title[$v['course_id']])?$title[$v['course_id']]:'';
            $lists[$k]['parent_name'] = isset($subjectArr[$course_subject[$v['course_id']]['parentid']])?$subjectArr[$course_subject[$v['course_id']]['parentid']]:'';
            $lists[$k]['child_name'] = isset($subjectArr[$course_subject[$v['course_id']]['childid']])?$subjectArr[$course_subject[$v['course_id']]['childid']]:'';
            //$lists[$k]['price'] = isset($priceArr[$v['course_id']])?$priceArr[$v['course_id']]:0;
            $lists[$k]['cover'] = isset($coverArr[$v['course_id']])?$coverArr[$v['course_id']]:'';
        }

        return ['code'=>200,'msg'=>'SUCCESS','data'=>['list'=>$lists,'total'=>count($lists)]];
    }

    /**
     * 数据库数量操作
     */
    public static function ShopCartNumOperate($params)
    {
        if(!in_array($params['operate'],['in','de'])){
            return ['code'=>203,'msg'=>'操作不合法'];
        }
        //
        $shopcart = self::where('id',$params['gid'])->where('school_id',$params['schoolid'])->select('number')->first();
        if(empty($shopcart)){
            return ['code'=>205,'msg'=>'找不到当前记录'];
        }
        if($params['operate']=='de' && $shopcart['number']<=1){
            return ['code'=>206,'msg'=>'购物车数量已经是最少了'];
        }
        $operate = $params['operate']=='in'?'increment':'decrement';
        $res = self::where('id',$params['gid'])->{$operate}('number');
        if($res){
            $arr = ['code'=>200,'msg'=>'success'];
        }else{
            $arr = ['code'=>201,'msg'=>'更新购物车数量失败'];
        }
        return $arr;
    }

    /**
     * 购物车去结算
     */
    public static function shopCartPay($schoolid)
    {
        $lists = StockShopCart::where('school_id',$schoolid)->get()->toArray();
        if(empty($lists)){
            return ['code'=>203,'msg'=>'购物车为空'];
        }
        //获取价格
        foreach($lists as $k=>$v){
            $courseids[] = $v['course_id'];
        }

        //授权价格
        if(count($courseids)==1) $courseids[] = $courseids[0];
        $priceArr = Coures::whereIn('id',$courseids)->pluck('impower_price','id');
        //整理入库存表数据
        $money = 0;
        $oid = SchoolOrder::generateOid();
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        foreach($lists as $k=>$v)
        {
            $lists[$k]['oid'] = $oid;
            $lists[$k]['school_id'] = $schoolid;
            $lists[$k]['school_pid'] = $schoolid;
            $lists[$k]['admin_id'] = $admin_id;
            $price = isset($priceArr[$v['course_id']])?$priceArr[$v['course_id']]:0;
            $lists[$k]['price'] = $price;
            $money += $v['number'] * $price;
            $lists[$k]['create_at'] = date('Y-m-d H:i:s');
            $lists[$k]['add_number'] = $v['number'];

            unset($lists[$k]['number']);
            unset($lists[$k]['ischeck']);
            unset($lists[$k]['id']);
        }
        //查询网校当前余额与 订单金额做对比
        $balance = (int) School::where('id',$schoolid)->value('balance');
        if($balance < $money){
            return ['code'=>203,'msg'=>'当前余额不足'];
        }

        DB::beginTransaction();
        try{
            //加入库存表
            $res = CourseStocks::insert($lists);
            if(!$res){
                DB::rollBack();
                return ['code'=>205,'msg'=>'库存扣除失败, 请重试'];
            }

            //订单
            $order = [
                'oid' => $oid,
                'school_id' => $schoolid,
                'admin_id' => $admin_id,
                'type' => 7,//库存退费
                'paytype' => 5,//余额
                'status' => 2,//已支付
                'online' => 1,//线上订单
                'money' => $money,
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>206,'msg'=>'网络错误, 请重试'];
            }
            //账户余额
            $res = $money?School::where('id',$schoolid)->decrement('balance',$money):true;
            if(!$res){
                DB::rollBack();
                return ['code'=>207,'msg'=>'网络错误'];
            }

            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'SchoolData' ,
                'route_url'      =>  'admin/service/stock/shopCartPay' ,
                'operate_method' =>  'insert' ,
                'content'        =>  '新增订单'.json_encode($lists) ,
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);

            $res = self::where('school_id',$schoolid)->delete();

            DB::commit();
            return ['code'=>200,'msg'=>'success'];
        }catch(\Exception $e){
            DB::rollBack();
            return ['code'=>209,'msg'=>$e->getMessage()];
        }
    }

    /**
     * 更换库存页面
     * 查询课程信息与 剩余库存, 返回可更换的课程
     */
    public static function preReplaceStock($params)
    {
        //授权表课程信息
        $course = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('id',$params['courseid'])
            ->select('course_id','parent_id','child_id','title')
            ->first();
        if(empty($course)){
            return ['code'=>202,'msg'=>'课程查找失败'];
        }
        $course = json_decode(json_encode($course),true);

        //科目名称
        $subjectArr = DB::table('ld_course_subject')
            ->whereIn('id',[$course['parent_id'],$course['child_id']])
            ->pluck('subject_name','id');
        $course['parent'] = isset($subjectArr[$course['parent_id']])?$subjectArr[$course['parent_id']]:'';
        $course['child'] = isset($subjectArr[$course['child_id']])?$subjectArr[$course['child_id']]:'';
        //真实课程id
        $params['course_id'] = $course['course_id'];

        //获取课程价格 and 库存余量
        $data = self::getCoursePriceAndStock($params);
        $course['price'] = $data['price'];
        $stocks = $data['stocks'];

        //授权课程列表 field=课程id,课程标题
        $courseArr = CourseSchool::where('to_school_id',$params['schoolid'])
                ->where('is_del',0)->where('status',1)->select('course_id','title')->get()->toArray();

        return [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'stocks'=>$stocks,
                'courseinfo'=>$course,
                'courselist'=>$courseArr,
            ]
        ];
    }

    /**
     * 更换库存详情
     * 1, 根据被退还课程(授权表id)获取 剩余库存以及价格,得到可用金额
     * 2, 根据要增加库存课程id(课程表id)得到价格, 计算当前要退还的库存 多退少补详情
     */
    public static function replaceStockDetail($params)
    {
        //授权表课程信息
        $course_id = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('id',$params['courseid'])->value('course_id');
        if(!$course_id){
            return ['code'=>202,'msg'=>'课程查找失败'];
        }
        $params['course_id'] = $course_id;
        //获取课程价格 and 库存余量
        $data = self::getCoursePriceAndStock($params);
        $price = (int) $data['price'];
        $stocks = (int) $data['stocks'];
        if($stocks<=0){
            return ['code'=>205,'msg'=>'没有可用库存'];
        }
        //此课程剩余库存的 转换金额
        $surplus_money = $price * $stocks;

        //ncourseid stocks
        $nprice = (int) Coures::where('id',$params['ncourseid'])->value('impower_price');
        $money = $nprice * (int) $params['stocks'];//更换库存所需金额

        //剩余金额-所需金额,多退少补 正数为退, 负数为补
        $nmoney = $surplus_money - $money;
        $type = '';
        if($nmoney==0){
            $type = '=';//刚好抵消
        }elseif($nmoney<0){
            $type = '-';//需补费
            $nmoney = 0-$nmoney;//转换为正数
        }elseif($nmoney>0){
            $type = '+';//退费
        }
        return [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'type'=>$type,
                'money'=>$nmoney,
            ]
        ];

    }

    /**
     * 执行替换库存
     */
    public static function doReplaceStock($params)
    {
        //s授权表课程信息
        $course_id = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('id',$params['courseid'])->value('course_id');
        if(!$course_id){
            return ['code'=>202,'msg'=>'课程查找失败'];
        }
        $params['course_id'] = $course_id;
        //获取课程价格 and 库存余量
        $data = self::getCoursePriceAndStock($params);
        $price = (int) $data['price'];
        $stocks = (int) $data['stocks'];
        if($stocks<=0){
            return ['code'=>205,'msg'=>'没有可用库存'];
        }
        //此课程剩余库存的 转换金额
        $surplus_money = $price * $stocks;

        //ncourseid stocks
        $nprice = (int) Coures::where('id',$params['ncourseid'])->value('impower_price');
        $money = $nprice * (int) $params['stocks'];//更换库存所需金额

        //剩余金额-所需金额,多退少补 正数为退, 负数为补
        $nmoney = $surplus_money - $money;
        $type = '';
        if($nmoney==0){
            $type = '=';//刚好抵消
        }elseif($nmoney<0){
            $type = '-';//需补费
            $nmoney = 0-$nmoney;//转换为正数
            $balance = School::where('id',$params['schoolid'])->value('balance');
            if($balance<$nmoney){
                return ['code'=>205,'msg'=>'余额不足'];
            }

        }elseif($nmoney>0){
            $type = '+';//退费
        }

        DB::beginTransaction();
        try{
            $oid = SchoolOrder::generateOid();
            //
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;

            $stocks_data = [];
            //扣减库存
            $stocks_info = [];
            $stocks_info['oid'] = $oid;
            $stocks_info['admin_id'] = $admin_id;
            $stocks_info['school_id'] = $stocks_info['school_pid'] = $params['schoolid'];
            $stocks_info['course_id'] = $course_id;
            $stocks_info['price'] = $price;
            $stocks_info['add_number'] = 0-$stocks;
            $stocks_info['create_at'] = date('Y-m-d H:i:s');
            //第一条数据
            $stocks_data[] = $stocks_info;
            //增加库存
            $stocks_info['course_id'] = $params['ncourseid'];
            $stocks_info['price'] = $nprice;
            $stocks_info['add_number'] = $params['stocks'];
            //第二条数据
            $stocks_data[] = $stocks_info;
            //入库
            $res = CourseStocks::insert($stocks_data);
            if(!$res){
                DB::rollBack();
                return ['code'=>206,'msg'=>'库存扣除失败, 请重试'];
            }
            //订单
            $order = [
                'oid' => $oid,
                'school_id' => $params['schoolid'],
                'admin_id' => $admin_id,
                'type' => $type=='+'?9:8,//8补费,9=退费
                'paytype' => 5,//余额
                'status' => 2,//已支付
                'online' => 1,//线上订单
                'money' => $nmoney,
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }

            //账户余额
            if($type=='='){
                $res = true;
            }else{
                $operate = $type=='+'?'increment':'decrement';
                $res = School::where('id',$params['schoolid'])->{$operate}('balance',$nmoney);
            }

            if(!$res){
                DB::rollBack();
                return ['code'=>209,'msg'=>'网络错误, 请重试'];
            }
            DB::commit();
            return ['code'=>200,'msg'=>'success'];

        }catch(\Exception $e){
            DB::rollBack();
            Log::error('更换库存错误_'.$e->getMessage());
            return ['code'=>211,'msg'=>'更换失败, 请联系管理员解决'];
        }

    }

    /**
     * 获取课程价格 and 库存余量
     * @param $params
     * @return array
     */
    public static function getCoursePriceAndStock($params)
    {
        //课程授权价格
        $price = Coures::where('id',$params['course_id'])->value('impower_price');

        //总库存
        $total_num = CourseStocks::where('school_id',$params['schoolid'])
            ->where(['is_del'=>0,'course_id'=>$params['course_id']])->sum('add_number');

        //使用数量------------------------授权课程在订单表的课程id是授权表的id
        $whereArr = ['class_id'=>$params['courseid'],'school_id'=>$params['schoolid'],'oa_status'=>1,'nature'=>1,'status'=>2];
        $use_nums = Order::whereIn('pay_status',[3,4])
            ->where($whereArr)->count();
        //剩余库存
        $stocks = $total_num-$use_nums;

        return ['price'=>$price,'stocks'=>$stocks];
    }

    /**
     * 库存订单
     */
    public static function stockOrder($params)
    {
        $schoolid = $params['schoolid'];
        $page = (int) (isset($params['page']) && $params['page'])?$params['page']:1;
        $pagesize = (int) (isset($params['pagesize']) && $params['pagesize'])?$params['pagesize']:15;

        //预定义固定条件
        $whereArr = [
            ['school_id','=',$schoolid],//学校
            ['online','=',1],//线上订单
        ];

        //搜索条件
        if(isset($params['status']) && $params['status']){
            $whereArr[] = ['status','=',$params['status']];//订单状态
        }
        if(isset($params['type']) && $params['type']){
            $whereArr[] = ['type','=',$params['type']];//订单类型
        }

        //结果集
        $field = ['id','oid','school_id','type','paytype','status','money','remark','admin_remark','apply_time','operate_time'];
        //
        $query = SchoolOrder::where($whereArr)->whereIn('type',[6,7,8,9]);//6789都属于库存类订单
        //总数
        $total = $query->count();
        $list = $query->select($field)->orderBy('id','desc')
            ->offset(($page-1)*$pagesize)
            ->limit($pagesize)->get()->toArray();
        $texts = SchoolOrder::tagsText(['pay','online_status','service','type']);
        foreach($list as $k=>$v){
            //订单类型
            $list[$k]['type_text'] = isset($texts['type_text'][$v['type']])?$texts['type_text'][$v['type']]:'';
            //支付类型
            $list[$k]['paytype_text'] = isset($texts['pay_text'][$v['paytype']])?$texts['pay_text'][$v['paytype']]:'';
            //订单状态
            $list[$k]['status_text'] = isset($texts['online_status_text'][$v['status']])?$texts['online_status_text'][$v['status']]:'';
            //服务类型
            $list[$k]['service_text'] = isset($texts['service_text'][$v['type']])?$texts['service_text'][$v['type']]:'';
            //备注 and 管理员备注
            $list[$k]['remark'] = $v['remark']?:'';
            $list[$k]['admin_remark'] = $v['admin_remark']?:'';
        }

        $data = [
            'total'=>$total,
            'total_page'=> ceil($total/$pagesize),
            'list'=>$list,
            //'texts'=>self::tagsText(['pay','status','service','type']),
        ];
        return ['code'=>200,'msg'=>'success','data'=>$data];


    }

}
