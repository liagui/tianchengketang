<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\SchoolOrder;
use Illuminate\Support\Facades\Log;

/**
 * 手动打款日志
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
     * 加入购物车
     */
    public static function addShopCart($params)
    {
        //拿到课程真实id
        $course_id = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('id',$params['courseid'])->value('course_id');

        //查看是否已存在
        $gid = self::where('course_id',$course_id)->where('school_id',$params['schoolid'])->value('id');
        if($gid){
            //$res = self::where('id',$gid)->increment('number');
            return ['code'=>205,'msg'=>'购物车已经存在当前课程'];
        }

        //加入购物车
        $price = (int) Coures::where('id',$course_id)->value('impower_price');//获取授权价格
        $data['school_id'] = $params['schoolid'];
        $data['course_id'] = $course_id;
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
        foreach($lists as $k=>$v){
            $courseids[] = $v['course_id'];
        }
        if($courseids){
            //课程名称,学科id
            //if(count($courseids)==1) $courseids[] = $courseids[0];
            $courseArr = CourseSchool::whereIn('course_id',$courseids)
                ->where('to_school_id',$schoolid)
                ->select('title','parent_id','child_id','course_id')->get()->toArray();
            foreach($courseArr as $k=>$v){
                $course_subject[$v['course_id']]['parentid'] = $v['parent_id'];
                $course_subject[$v['course_id']]['childid'] = $v['child_id'];
                $subjects[] = $v['parent_id'];
                $subjects[] = $v['child_id'];
                $title[$v['course_id']] = $v['title'];
            }
            if($subjects){
                //科目名称
                $subjectArr = DB::table('ld_course_subject')
                    ->whereIn('id',$subjects)
                    ->pluck('subject_name','id');
            }
            //授权价格
            $priceArr = Coures::whereIn('id',$courseids)->pluck('impower_price','id');
        }
        foreach($lists as $k=>$v)
        {
            $lists[$k]['title'] = isset($title[$v['course_id']])?$title[$v['course_id']]:'';
            $lists[$k]['parent_name'] = isset($subjectArr[$course_subject[$v['course_id']]['parentid']])?$subjectArr[$course_subject[$v['course_id']]['parentid']]:'';
            $lists[$k]['child_name'] = isset($subjectArr[$course_subject[$v['course_id']]['childid']])?$subjectArr[$course_subject[$v['course_id']]['childid']]:'';
            $lists[$k]['price'] = isset($priceArr[$v['course_id']])?$priceArr[$v['course_id']]:0;
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

        //使用数量
        $whereArr = ['class_id'=>$params['course_id'],'school_id'=>$params['schoolid'],'oa_status'=>1,'nature'=>1,'status'=>2];
        $use_nums = Order::whereIn('pay_status',[3,4])
            ->where($whereArr)->count();
        //剩余库存
        $stocks = $total_num-$use_nums;

        return ['price'=>$price,'stocks'=>$stocks];
    }



}
