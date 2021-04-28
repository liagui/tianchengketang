<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
            'update_num.required'   => json_encode(['code'=>'202','msg'=>'更新数量不能为空']),
            'update_num.integer'   => json_encode(['code'=>'202','msg'=>'更新数量不正确']),
            'update_num.min'   => json_encode(['code'=>'202','msg'=>'更新数量不能小于1']),
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
//            ['ld_course.status','=',1],//在售
            ['ld_course.is_del','=',0],//未删除
            ['method.is_del','=',0],//未删除
        ];
        //一级学科
        if(isset($params['parentid']) && $params['parentid']){
            $whereArr[] = ['ld_course.parent_id','=',$params['parentid']];
        }
        //二级学科
        if(isset($params['childid']) && $params['childid']){
            $whereArr[] = ['ld_course.child_id','=',$params['childid']];
        }
        //课程标题
        if(isset($params['search']) && $params['search']){
            $whereArr[] = ['ld_course.title','like','%'.$params['search'].'%'];
        }

        //课程类别  1直播, 2录播, 3其他
        if(isset($params['method']) && $params['method']){
            if(!in_array($params['method'],[1,2,3])){
                return ['code'=>203,'msg'=>'课程类别不合法'];
            }
            $whereArr[] = ['method.method_id','=',$params['method']];
        }
        //
        $field = [
            'ld_course.id','ld_course.parent_id','ld_course.child_id','ld_course.title',
            'ld_course.cover','ld_course.nature','ld_course.pricing','ld_course.impower_price',
            'ld_course.buy_num','method.method_id','ld_course.score'
        ];

        //排序 推荐-时间-销售量
        $order_sort = isset($params['ordersort'])?$params['ordersort']:'score';
        if($order_sort=='score' || $order_sort=='0'){
            $orderby = 'ld_course.score';
        } else if($order_sort=='date' || $order_sort=='1'){
            $orderby = 'ld_course.id';
        }else if($order_sort=='sales' || $order_sort=='2'){
            $orderby = 'ld_course.salesnum';
        }
        //总校课程
        $totalArr = Coures::leftJoin('ld_course_method as method','ld_course.id','=','method.course_id')
            ->select(DB::raw('count(ld_course.id) as total'))->where($whereArr)->where('ld_course.status',1)->groupBy('ld_course.id')->get()->toArray();
        $total = 0;
        foreach($totalArr as $v){
            $total += $v['total'];
        }
        $query = Coures::leftJoin('ld_course_method as method','ld_course.id','=','method.course_id')
            ->where($whereArr)->where('ld_course.status',1)->groupBy('ld_course.id');//以课程id分组, 排除因课程对应method表多个课程形式造成的课程重复
        if(isset($params['gettotal'])){
            $lists = $query->select($field)->orderByDesc($orderby)->get()->toArray();
        }else{

            if($order_sort=='score' || $order_sort=='0'){
                $lists = $query->select($field)->orderByDesc('ld_course.score')->orderByDesc('ld_course.id')
                    ->offset($offset)->limit($pagesize)->get()->toArray();
            } else if($order_sort=='date' || $order_sort=='1'){
                $lists = $query->select($field)->orderByDesc('ld_course.id')
                    ->offset($offset)->limit($pagesize)->get()->toArray();
            }else if($order_sort=='sales' || $order_sort=='2'){
                $lists = $query->select($field)->orderByDesc('ld_course.salesnum')
                    ->offset($offset)->limit($pagesize)->get()->toArray();
            }
        }

        //根据id对二维数组去重
        //$lists = uniquArr($lists,'id'); //groupby 可用后, 忽略此去重方法


        //查找已授权课程
        $course_schoolidArrs = CourseSchool::where('to_school_id',$params['schoolid'])->where('is_del',0)->select('id','course_id','status')->get()->toArray();

        //已授权课程的course_id组
        $course_schoolids = array_unique(array_column($course_schoolidArrs,'course_id'));

        //已授权课程的状态
        $course_statusArr = [];

        //已授权课程的course_id 对应 授权表id
        $course_courseschoolid = [];

        foreach($course_schoolidArrs as $k=>$v){
            $course_statusArr[$v['course_id']] = $v['status'];

            //course_id 对应 授权表id
            $course_courseschoolid[$v['course_id']] = $v['id'];
        }

        $courseids = [];
        //存储学科
        $subjectids = [];
        //储存购买量
        $buy_nemberArr = [];
        //储存总库存
        $sum_numberArr = [];

        //遍历获取数据
        if(!empty($lists)){

            foreach($lists as $k=>$v){
                $subjectids[] = $v['parent_id'];//父类
                $subjectids[] = $v['child_id'];//子类
                $courseids[] = $v['id'];//课程id
            }

            //科目名称
            if(count($subjectids)==1) $subjectids[] = $subjectids[0];
            $subjectArr = DB::table('ld_course_subject')
                ->whereIn('id',$subjectids)
                ->pluck('subject_name','id');

            //课程id组
            if(count($courseids)==1) $courseids[] = $courseids[0];
            //获取购买量 使用授权表id
            $course_school_ids = array_unique(array_column($course_schoolidArrs,'id'));
            if($course_school_ids){
                $buy_nember_listArr = Order::whereIn('pay_status',[3,4])//支付成功
                    ->where('nature',1)
                    ->whereIn('class_id',$course_school_ids)
                    ->where(['school_id'=>$params['schoolid'],'status'=>2,'oa_status'=>1])
                    ->get()->toArray();

                //已课程id为key拼装 课程id=>购买量数组
                foreach($buy_nember_listArr as $v){
                    if(!isset($buy_nemberArr[$v['class_id']])){
                        $buy_nemberArr[$v['class_id']] = 1;
                    }else{
                        $buy_nemberArr[$v['class_id']] ++;
                    }
                }
            }




            //获取总库存
            $sum_nember_listArr = CourseStocks::whereIn('course_id',$course_schoolids)
                                ->where(['school_id'=>$params['schoolid'],'is_del'=>0])
                                ->select(DB::raw('course_id,sum(add_number) as stocks'))
                                ->groupBy('course_id')
                                ->get()->toArray();
            //整理库存为课程id=>库存
            foreach($sum_nember_listArr as $v){
                $sum_numberArr[$v['course_id']] = $v['stocks'];
            }
        }

        //获取授课方式
        $mehtodArrs = Couresmethod::whereIn('course_id',$courseids)->where('is_del',0)->select('course_id','method_id')->get()->toArray();
        //整理授课方式
        $methodArr = [];
        $method_nameArr = [1=>'直播',2=>'录播',3=>'其他'];
        if(isset($mehtodArrs)){
            foreach($mehtodArrs as $k=>$v){
                if(isset($method_nameArr[$v['method_id']])){
                    $methodArr[$v['course_id']][] = $method_nameArr[$v['method_id']];
                }
            }

        }
        //判断授权的价格表中是否存在此分校
        $count = Schoolcourse::where(['school_id'=>$params['schoolid']])->count();
        if(!empty($lists)){
            foreach($lists  as $k=>&$v){
                //查询授权价格
                if($count > 0){
                    $impowerprice = Schoolcourse::where(['school_id'=>$params['schoolid'],'course_id'=>$v['id']])->first();
                    $v['impower_price'] = $impowerprice['course_price'];
                }
                $v['parent_name'] = isset($subjectArr[$v['parent_id']])?$subjectArr[$v['parent_id']]:'';
                $v['child_name'] = isset($subjectArr[$v['child_id']])?$subjectArr[$v['child_id']]:'';

                $v['buy_nember'] = 0;//销售量
                $v['sum_nember'] = 0;//库存总量
                $v['surplus'] = 0;//剩余库存
                $v['ishave'] = 0;
                $v['status'] = 0;
                //课程价格
                //查询授权价格 有就显示没有就0
                $prices = CourseSchool::where(['to_school_id'=>$params['schoolid'],'course_id'=>$v['id'],'is_del'=>0])->select('sale_price')->first();
                if(!empty($price)){
                    $v['pricing'] = $prices['pricing'];
                }else{
                    $v['pricing']=0;
                }
                //已授权课程
                if($course_schoolids && in_array($v['id'],$course_schoolids)){
                    $v['ishave'] = 1;
                    $v['status'] = isset($course_statusArr[$v['id']])?$course_statusArr[$v['id']]:0;

                    //本课程购买量
                    if(isset($course_courseschoolid[$v['id']])){
                        //本课程对应的授权表id, 订单表存的授权表的id, 所以转换一下
                        $course_courseschoolid_key = $course_courseschoolid[$v['id']];
                        $v['buy_nember'] = isset($buy_nemberArr[$course_courseschoolid_key])?$buy_nemberArr[$course_courseschoolid_key]:0;
                    }

                    //本课程销售量
                    $v['sum_nember'] = isset($sum_numberArr[$v['id']])?$sum_numberArr[$v['id']]:0;
                    //剩余库存量
                    $v['surplus'] = $v['sum_nember']-$v['buy_nember'] <=0 ?0:$v['sum_nember']-$v['buy_nember'];
                }

                $v['method_name'] = isset($methodArr[$v['id']])?implode(' ',$methodArr[$v['id']]):'';
            }
        }
        $data = [
            'list'=>$lists,
            'total'=>$total,
            'total_page'=>isset($params['gettotal'])?1:ceil($total/$pagesize),
        ];

        return ['code' => 200 , 'msg' => 'success','data'=>$data];
    }

    /**
     * 仅获取已关联的课程
     */
    public static function onlyCourseSchool($params)
    {
        //预定义条件
        $whereArr = [
            ['ld_course.school_id','=',1],//总校
            ['ld_course.status','=',1],//在售
            ['ld_course.is_del','=',0],//未删除
            ['ld_course_school.is_del','=',0],//网校端未删除或未取消授权
            ['ld_course_school.to_school_id','=',$params['schoolid']],//未删除
        ];
        //一级学科
        if(isset($params['parentid']) && $params['parentid']){
            $whereArr[] = ['ld_course.parent_id','=',$params['parentid']];
        }
        //二级学科
        if(isset($params['childid']) && $params['childid']){
            $whereArr[] = ['ld_course.child_id','=',$params['childid']];
        }
        //课程标题
        if(isset($params['search']) && $params['search']){
            $whereArr[] = ['ld_course.title','like','%'.$params['search'].'%'];
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
            'ld_course.cover','ld_course.nature','ld_course.status','ld_course.impower_price','ld_course.pricing',
            'ld_course.buy_num','method.method_id'];
        $orderby = 'ld_course.id';
        //总校课程
        $query = Coures::Join('ld_course_school','ld_course.id','=','ld_course_school.course_id')
            ->leftJoin('ld_course_method as method','ld_course.id','=','method.course_id')
            ->where($whereArr);//->groupBy('ld_course.id');//已课程id分组, 排除因课程对应method表多个课程形式造成的课程重复

        //获取结果
        $lists = $query->select($field)->orderByDesc($orderby)->get()->toArray();
        //根据id对二维数组去重
        $lists = uniquArr($lists,'id');
        $total = count($lists);
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
        //查找已授权课程
        $course_schoolidArrs = CourseSchool::where('to_school_id',$params['schoolid'])->where('is_del',0)->select('id','course_id','status')->get()->toArray();

        //已授权课程的course_id组
        $course_schoolids = array_unique(array_column($course_schoolidArrs,'course_id'));

        //已授权课程的状态
        $course_statusArr = [];

        //已授权课程的course_id 对应 授权表id
        $course_courseschoolid = [];

        foreach($course_schoolidArrs as $k=>$v){
            $course_statusArr[$v['course_id']] = $v['status'];

            //course_id 对应 授权表id
            $course_courseschoolid[$v['course_id']] = $v['id'];
        }

        $courseids = [];
        //存储学科
        $subjectids = [];
        //储存购买量
        $buy_nemberArr = [];
        //储存总库存
        $sum_numberArr = [];
        //课程id组
        if(count($courseids)==1) $courseids[] = $courseids[0];
        //获取购买量 使用授权表id
        $course_school_ids = array_unique(array_column($course_schoolidArrs,'id'));
        if($course_school_ids){
            $buy_nember_listArr = Order::whereIn('pay_status',[3,4])//支付成功
                ->where('nature',1)
                ->whereIn('class_id',$course_school_ids)
                ->where(['school_id'=>$params['schoolid'],'status'=>2,'oa_status'=>1])
                ->get()->toArray();

            //已课程id为key拼装 课程id=>购买量数组
            foreach($buy_nember_listArr as $v){
                if(!isset($buy_nemberArr[$v['class_id']])){
                    $buy_nemberArr[$v['class_id']] = 1;
                }else{
                    $buy_nemberArr[$v['class_id']] ++;
                }
            }
        }




        //获取总库存
        $sum_nember_listArr = CourseStocks::whereIn('course_id',$course_schoolids)
                            ->where(['school_id'=>$params['schoolid'],'is_del'=>0])
                            ->select(DB::raw('course_id,sum(add_number) as stocks'))
                            ->groupBy('course_id')
                            ->get()->toArray();
        //整理库存为课程id=>库存
        foreach($sum_nember_listArr as $v){
            $sum_numberArr[$v['course_id']] = $v['stocks'];
        }
        $methodArr = [1=>'直播','2'=>'录播',3=>'其他'];
        //判断授权的价格表中是否存在此分校
        $count = Schoolcourse::where(['school_id'=>$params['schoolid']])->count();
        if(!empty($lists)){
            foreach($lists  as $k=>&$v){
                if($count > 0){
                    $prcie = Schoolcourse::where(['school_id'=>$params['schoolid'],'course_id'=>$v['id']])->first();
                    $v['impower_price'] = $prcie['course_price'];
                }
                $v['parent_name'] = isset($subjectArr[$v['parent_id']])?$subjectArr[$v['parent_id']]:'';
                $v['child_name'] = isset($subjectArr[$v['child_id']])?$subjectArr[$v['child_id']]:'';

                $v['buy_nember'] = 0;//销售量
                $v['sum_nember'] = 0;//库存总量
                $v['surplus'] = 0;//剩余库存
                $v['status'] = 0;
                //已授权课程
                if($course_schoolids && in_array($v['id'],$course_schoolids)){
                    $v['ishave'] = 1;
                    $v['status'] = isset($course_statusArr[$v['id']])?$course_statusArr[$v['id']]:0;

                    //本课程购买量
                    if(isset($course_courseschoolid[$v['id']])){
                        //本课程对应的授权表id, 订单表存的授权表的id, 所以转换一下
                        $course_courseschoolid_key = $course_courseschoolid[$v['id']];
                        $v['buy_nember'] = isset($buy_nemberArr[$course_courseschoolid_key])?$buy_nemberArr[$course_courseschoolid_key]:0;
                    }

                    //本课程销售量
                    $v['sum_nember'] = isset($sum_numberArr[$v['id']])?$sum_numberArr[$v['id']]:0;
                    //剩余库存量
                    $v['surplus'] = $v['sum_nember']-$v['buy_nember'] <=0 ?0:$v['sum_nember']-$v['buy_nember'];
                }
                $v['ishave'] = 1;//固定代表是已授权课程
                $v['method_name'] = isset($methodArr[$v['method_id']])?$methodArr[$v['method_id']]:'';
            }

        }
        $data = [
            'total'=>$total,
            'list'=>$lists,
        ];

        return ['code' => 200 , 'msg' => 'success','data'=>$data];
    }

    /**
     * 加入购物车
     */
    public static function addShopCart($params)
    {

        //查询课程是否存在,并拿到授权价格
        $count = Schoolcourse::where(['school_id'=>$params['schoolid']])->count();
        if($count > 0){
            $courses = Schoolcourse::where(['school_id'=>$params['schoolid'],'course_id'=>$params['courseid']])->select('course_price')->first();
        }else{
            $courses = coures::where('id',$params['courseid'])->select('impower_price as course_price')->first();
        }
        if(empty($courses)){
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
        $price = (int) $courses['course_price'];//获取授权价格
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
        $priceArr = [];
        foreach($lists as $k=>$v){
            $courseids[] = $v['course_id'];
        }
        if($courseids){
            //课程名称,学科id
            if(count($courseids)==1) $courseids[] = $courseids[0];
            $courseArr = Coures::whereIn('id',$courseids)
                ->select('id','title','parent_id','child_id','cover','impower_price')->get()->toArray();
            $count = Schoolcourse::where(['school_id'=>$schoolid])->count();
            foreach($courseArr as $k=>$v){
                if($count > 0){
                    $price = Schoolcourse::where(['school_id'=>$schoolid,'course_id'=>$v['id']])->first();
                    $v['impower_price'] = $price['course_price'];
                }
                $course_subject[$v['id']]['parentid'] = $v['parent_id'];
                $course_subject[$v['id']]['childid'] = $v['child_id'];
                $subjects[] = $v['parent_id'];
                $subjects[] = $v['child_id'];
                $title[$v['id']] = $v['title'];
                $coverArr[$v['id']] = $v['cover'];
                $priceArr[$v['id']] = $v['impower_price'];
            }
            if($subjects){
                //科目名称
                $subjectArr = DB::table('ld_course_subject')
                    ->whereIn('id',$subjects)
                    ->pluck('subject_name','id');
            }
        }
        foreach($lists as $k=>$v)
        {
            $lists[$k]['title'] = isset($title[$v['course_id']])?$title[$v['course_id']]:'';
            $lists[$k]['parent_name'] = isset($subjectArr[$course_subject[$v['course_id']]['parentid']])?$subjectArr[$course_subject[$v['course_id']]['parentid']]:'';
            $lists[$k]['child_name'] = isset($subjectArr[$course_subject[$v['course_id']]['childid']])?$subjectArr[$course_subject[$v['course_id']]['childid']]:'';
            $lists[$k]['cover'] = isset($coverArr[$v['course_id']])?$coverArr[$v['course_id']]:'';
            $lists[$k]['price'] = isset($priceArr[$v['course_id']])?$priceArr[$v['course_id']]:'';
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
     * 购物车数量直接操作
     */
    public static function shopCartManageUpdate($params)
    {
        //
        $shopcart = self::where('id',$params['gid'])->where('school_id',$params['schoolid'])->select('number')->first();
        if(empty($shopcart)){
            return ['code'=>205,'msg'=>'找不到当前记录'];
        }

        $res = self::where('id',$params['gid'])->update(['number'=>$params['update_num']]);
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
        //我的购物车数据
        $lists = StockShopCart::where('school_id',$schoolid)->get()->toArray();
        if(empty($lists)){
            return ['code'=>203,'msg'=>'购物车为空'];
        }

        //组装课程id
        foreach($lists as $k=>$v){
            $courseids[] = $v['course_id'];
        }
        //防止whereIn报错
        if(count($courseids)==1) $courseids[] = $courseids[0];
        //已经授权过的课程
        $courseidArr = CourseSchool::whereIn('course_id',$courseids)->where('to_school_id',$schoolid)->where('is_del',0)->pluck('course_id')->toArray();

        //取差集得到未授权过得课程
        $wait_course_schoolids = array_diff($courseids,$courseidArr);

        DB::beginTransaction();
        try{
            //查看未授权课程是否有值
            if($wait_course_schoolids){
                //进行授权
                $flag = self::doCourseSchool($schoolid,$wait_course_schoolids);
                if(!$flag){
                    DB::rollBack();
                    return ['code'=>203,'msg'=>'结算失败, 请重试'];
                }
            }//主动授权end

            //获取授权价格
            $count = Schoolcourse::where(['school_id'=>$schoolid])->count();
            if($count>0){
                $priceArr = Schoolcourse::where('school_id',$schoolid)->whereIn('course_id',$courseids)->pluck('course_price','course_id');
            }else{
                $priceArr = Coures::whereIn('id',$courseids)->pluck('impower_price','id');
            }
            //整理入库存表数据
            $money = 0;
            $oid = SchoolOrder::generateOid();
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
            foreach($lists as $k=>$v)
            {
                $lists[$k]['oid'] = $oid;
                $lists[$k]['school_id'] = $schoolid;
                $lists[$k]['school_pid'] = 1;//直接赋值1代表总校
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

            //整理琐碎数据入数组
            $payinfo['oid'] = $oid;
            $payinfo['schoolid'] = $schoolid;
            $payinfo['admin_id'] = $admin_id;
            $payinfo['money'] = $money;

            //查询网校当前余额与 订单金额做对比
            $schools = School::where('id',$schoolid)->select('balance','give_balance')->first();
            $balance = $schools['balance'] + $schools['give_balance'];
            if($balance < $money){
                //1, 生成一个未支付的订单
                $payinfo['status'] = 1;//未支付
                $code = 2090;//生成未支付订单成功,返回固定状态码2090
                $msg = '账户余额不足,请充值';

                //定义库存是否可用状态
                $stock_statusArr['is_forbid'] = 1;
                $stock_statusArr['is_del'] = 1;
            }else{
                //2, 余额充足的情况, 生成一个支付状态是成功的订单
                $payinfo['status'] = 2;//支付成功
                $code = 200;
                $msg = 'success';

                //定义库存是否可用状态
                $stock_statusArr['is_forbid'] = 0;
                $stock_statusArr['is_del'] = 0;
            }

            //执行生成订单
            $return = self::createStocksMultiOrder($lists,$payinfo,$stock_statusArr,$schools);
            if($return['code']!=200){
                DB::rollBack();
                return $return;
            }

            //返回最终结果
            DB::commit();
            return [
                'code'=>$code,
                'msg'=>$msg,
                'data'=>[
                    'money'=>$money,
                ]
            ];

        }catch(\Exception $e){
            DB::rollBack();
            Log::error('购物车结算error_msg'.$e->getMessage().'_file_'.$e->getFile().'_line_'.$e->getLine());
            return ['code'=>209,'msg'=>'遇到异常, 结算失败'];
        }
    }

    /**
     * 生成一个购物车结算的订单
     */
    public static function createStocksMultiOrder($lists,$payinfo,$stock_statusArr,$schools)
    {
        //补充库存状态字段
        foreach($lists as $k=>$v){
            $lists[$k]['is_forbid'] = $stock_statusArr['is_forbid'];
            $lists[$k]['is_del'] = $stock_statusArr['is_del'];
        }
        //加入库存表
        $res = CourseStocks::insert($lists);
        if(!$res){
            return ['code'=>205,'msg'=>'库存更新失败, 请重试'];
        }

        //账户余额扣除
        //生成已支付订单并且金额>0时候执行账户扣费
        if($payinfo['status']==2 && $payinfo['money']){
            $return_account = SchoolAccount::doBalanceUpdate($schools,$payinfo['money'],$payinfo['schoolid']);
            if(!$return_account['code']){
                DB::rollBack();
                return ['code'=>203,'msg'=>'请检查余额是否充足'];
            }
        }

        //订单
        $order = [
            'oid'       => $payinfo['oid'],
            'school_id' => $payinfo['schoolid'],
            'admin_id'  => $payinfo['admin_id'],
            'type'      => 7,//批量购买库存
            'paytype'   => 5,//余额
            'status'    => $payinfo['status'],//支付状态1=未支付,2=已支付
            'online'    => 1,//线上订单
            'money'     => $payinfo['money'],
            'use_givemoney' => isset($return_account['use_givemoney'])?$return_account['use_givemoney']:0,//用掉了多少赠送金额
            'apply_time'=> date('Y-m-d H:i:s')
        ];
        $lastid = SchoolOrder::doinsert($order);
        if(!$lastid){
            return ['code'=>206,'msg'=>'网络错误, 请重试'];
        }

        //添加日志操作
        AdminLog::insertAdminLog([
            'admin_id'       =>  $payinfo['admin_id'] ,
            'module_name'    =>  'Service' ,
            'route_url'      =>  'admin/service/stock/shopCartPay' ,
            'operate_method' =>  'insert' ,
            'content'        =>  '新增订单'.json_encode($lists) ,
            'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        $res = self::where('school_id',$payinfo['schoolid'])->delete();
        return ['code'=>200,'msg'=>'success'];
    }

    /**
     * 更换库存页面
     * 查询课程信息与 剩余库存, 返回可更换的课程
     */
    public static function preReplaceStock($params)
    {
        //预定义条件
        $whereArr = [
            ['ld_course_school.course_id','!=',$params['courseid']],
            ['ld_course.status','=',1],//在售
            ['ld_course.is_del','=',0],//未删除
            ['ld_course_school.is_del','=',0],//网校端未删除或未取消授权
            ['ld_course_school.to_school_id','=',$params['schoolid']],
        ];
        //一级学科
        if(isset($params['parentid']) && $params['parentid']){
            $whereArr[] = ['ld_course.parent_id','=',$params['parentid']];
        }
        //二级学科
        if(isset($params['childid']) && $params['childid']){
            $whereArr[] = ['ld_course.child_id','=',$params['childid']];
        }

        //授权表课程信息
        $course = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('course_id',$params['courseid'])
            ->select('id','course_id','parent_id','child_id','title')
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
        //课程id
        $params['course_id'] = $course['course_id'];
        //授权表id
        $params['course_school_id'] = $course['id'];

        //获取课程价格 and 库存余量
        $data = self::getCoursePriceAndStock($params);
        $course['price'] = $data['price'];
        $stocks = $data['stocks'];

        //授权课程列表 field=课程id,课程标题, 用于可更换库存的课程展示
        $courseArr = CourseSchool::join('ld_course','ld_course_school.course_id','=','ld_course.id')
                ->where($whereArr)
                ->select('ld_course_school.course_id','ld_course_school.title','ld_course_school.parent_id','ld_course_school.child_id')
            ->get()->toArray();
        foreach ($courseArr as $arrk =>&$arrv){
            $prcie = Schoolcourse::where(['school_id'=>$params['schoolid'],'course_id'=>$arrv['course_id']])->first();
            $arrv['price'] = $prcie['course_price'];
        }
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
        $id = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('course_id',$params['courseid'])->value('id');
        if(!$id){
            return ['code'=>202,'msg'=>'课程查找失败'];
        }
        $params['course_id'] = $params['courseid'];
        $params['course_school_id'] = $id;
        //获取课程价格 and 库存余量
        $data = self::getCoursePriceAndStock($params);
        $price = (int) $data['price'];
        $stocks = (int) $data['stocks'];
        if($stocks<=0){
            return ['code'=>205,'msg'=>'没有可用库存'];
        }
        //此课程的可用于库存更换的 转换金额
        $surplus_money = $stocks >= $params['stocks']?$price * $params['stocks']: $price * $stocks;

        //ncourseid stocks
        $nprice = (int) Schoolcourse::where(['school_id'=>$params['schoolid'],'course_id'=>$params['course_id']])->value('course_price');
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
        //授权表课程信息
        $id = CourseSchool::where('to_school_id',$params['schoolid'])
            ->where('course_id',$params['courseid'])->value('id');
        if(!$id){
            return ['code'=>202,'msg'=>'课程查找失败'];
        }
        $params['course_id'] = $params['courseid'];//课程id
        $params['course_school_id'] = $id;//授权表id
        //获取课程价格 and 库存余量
        $data = self::getCoursePriceAndStock($params);
        $price = (int) $data['price'];
        $stocks = (int) $data['stocks'];
        if($stocks<=0){
            return ['code'=>205,'msg'=>'没有可用库存'];
        }
        //此课程可用于库存更换的 转换金额, 要替换课程库存小于等于当前课程库存时候, 一对一退费
        if($stocks>=$params['stocks']){
            $surplus_money = $price * $params['stocks'];
            $params['replaced_stocks'] = $params['stocks'];
        }else{
            $surplus_money = $price * $stocks;
            $params['replaced_stocks'] = $stocks;
        }


        //$surplus_money = $price * $stocks;

        //ncourseid stocks
        $count = Schoolcourse::where(['school_id'=>$params['schoolid']])->count();
        if($count > 0){
            $nprice = (int)Schoolcourse::where(['school_id'=>$params['schoolid'],'course_id'=>$params['course_id']])->value('course_price');
        }else{
            $nprice = (int) Coures::where('id',$params['ncourseid'])->value('impower_price');
        }
        $money = $nprice * (int) $params['stocks'];//更换库存所需金额

        //剩余金额-所需金额,多退少补 正数为退, 负数为补
        $new_money = $surplus_money - $money;

        //整理琐碎数据入数组
        $payinfo['price'] = $price;
        $payinfo['stocks'] = $stocks;
        $payinfo['nprice'] = $nprice;
        $payinfo['status'] = 2;//预定义已支付,当余额不足时覆盖这个变量

        $type = '';
        $schools = School::where('id',$params['schoolid'])->select('balance','give_balance')->first();
        $balance = $schools['balance'] + $schools['give_balance'];
        if($new_money==0){
            $type = '=';//刚好抵消

            //根据余额状态选择入库数据
            $stock_statusArr['is_forbid'] = 0;
            $stock_statusArr['is_del'] = 0;
            //msg
            $code = 200;
            $msg = 'success';
        }elseif($new_money<0){

            $type = '-';//需补费
            $new_money = 0-$new_money;//转换为正数

            if($balance<$new_money){
                //此时生成一个未支付订单
                $payinfo['status'] = 1;

                //根据余额状态选择入库数据
                $stock_statusArr['is_forbid'] = 1;
                $stock_statusArr['is_del'] = 1;
                //msg
                $code = 2090;
                $msg = '账户余额不足,请充值';
            }else{
                //扣费情况下, 余额充足

                //根据余额状态选择入库数据
                $stock_statusArr['is_forbid'] = 0;
                $stock_statusArr['is_del'] = 0;
                //msg
                $code = 200;
                $msg = 'success';
            }

        }elseif($new_money>0){
            $type = '+';//退费

            //根据余额状态选择入库数据
            $stock_statusArr['is_forbid'] = 0;
            $stock_statusArr['is_del'] = 0;
            //msg
            $code = 200;
            $msg = 'success';
        }
        //
        $payinfo['type'] = $type;
        $payinfo['nmoney'] = $new_money;//退/补费金额
        $payinfo['code'] = $code;

        //执行创建的订单
        $return = self::createReplaceStockOrder($params,$payinfo,$stock_statusArr,$schools);
        if($return['code']!=200){
            return $return;
        }
        //return 2090代表余额不足,并返回需支付金额, 不管前端显示与否
        return [
            'code'=>$code,
            'msg'=>$msg,
            'data'=>[
                'money'=>$new_money,
            ]
        ];

    }

    /**
     * 创建一个库存更换的 订单
     */
    public static function createReplaceStockOrder($params,$payinfo,$stock_statusArr,$schools)
    {
        //
        DB::beginTransaction();
        try{
            $oid = SchoolOrder::generateOid();
            //
            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

            $stocks_data = [];
            //被更换库存课程的 扣减库存
            $stocks_info = [];
            $stocks_info['oid'] = $oid;
            $stocks_info['admin_id'] = $admin_id;
            $stocks_info['school_pid'] = 1;//定义为总校
            $stocks_info['school_id'] = $params['schoolid'];
            $stocks_info['course_id'] = $params['course_id'];
            $stocks_info['price'] = $payinfo['price'];
            $stocks_info['add_number'] = 0-$params['replaced_stocks'];
            $stocks_info['create_at'] = date('Y-m-d H:i:s');
            //防止此订单未支付, 至支付期间被更换课程的库存有变动, 导致此订单有问题,
            //直接给扣减库存的状态定义为 有效, 若此订单未支付, 可人工为此课程重新添加库存
            //或在此失效订单加一个按钮, 一键恢复
            $stocks_info['is_forbid'] = 0;//$stock_statusArr['is_forbid'];
            $stocks_info['is_del'] = 0;//$stock_statusArr['is_del'];

            //第一条数据入 二维数组
            $stocks_data[] = $stocks_info;
            //要更换课程 增加库存
            $stocks_info['course_id'] = $params['ncourseid'];
            $stocks_info['price'] = $payinfo['nprice'];
            $stocks_info['add_number'] = $params['stocks'];
            $stocks_info['is_forbid'] = $stock_statusArr['is_forbid'];
            $stocks_info['is_del'] = $stock_statusArr['is_del'];
            //第二条数据 入 二维数组
            $stocks_data[] = $stocks_info;
            //入库
            $res = CourseStocks::insert($stocks_data);
            if(!$res){
                DB::rollBack();
                return ['code'=>206,'msg'=>'库存更新失败, 请重试'];
            }

            //账户余额
            if($payinfo['type']=='='){
                $res = true;
            }elseif($payinfo['type']=='+'){
                $res = $payinfo['nmoney']>0?School::where('id',$params['schoolid'])->increment('give_balance',$payinfo['nmoney']):0;
            }elseif($payinfo['type']=='-'){
                //code==200(代表要生成的是支付状态为成功的订单时), 执行余额扣除
                if($payinfo['nmoney']>0 && $payinfo['code']==200){
                    $return_account = SchoolAccount::doBalanceUpdate($schools,$payinfo['nmoney'],$params['schoolid']);
                    $res = $return_account['code'];
                }
            }
            //
            if(!$res){
                DB::rollBack();
                return ['code'=>209,'msg'=>'网络错误, 请重试'];
            }

            //订单
            $use_givemoney = isset($return_account['use_givemoney'])?$return_account['use_givemoney']:0;
            $order = [
                'oid'        => $oid,
                'school_id'  => $params['schoolid'],
                'admin_id'   => $admin_id,
                'type'       => $payinfo['type']=='+'?9:8,//8补费,9=退费(退费,和持平都定义为退费状态)
                'paytype'    => 5,//余额
                'status'     => $payinfo['status'],//支付状态
                'online'     => 1,//线上订单
                'money'      => $payinfo['nmoney'],
                'use_givemoney' => $use_givemoney,//用掉了多少赠送金额
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }
            //return success
            DB::commit();
            return ['code'=>200,'msg'=>'success'];

        }catch(\Exception $e){
            DB::rollBack();
            Log::error('更换库存错误_'.$e->getMessage());
            return ['code'=>211,'msg'=>'遇到异常, 请稍后重试'];
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
        $count = Schoolcourse::where(['school_id'=>$params['schoolid']])->count();
        if($count > 0){
            $price = (int)Schoolcourse::where(['school_id'=>$params['schoolid'],'course_id'=>$params['course_id']])->value('course_price');
        }else{
            $price = (int) Coures::where('id',$params['ncourseid'])->value('impower_price');
        }
        //总库存
        $total_num = CourseStocks::where('school_id',$params['schoolid'])
            ->where(['is_del'=>0,'course_id'=>$params['course_id']])
            ->where('is_given_away','=',0) // 替换库存的时候 库存数必须  不是赠送的(is_given_away ==  1 是赠送的库存)
            ->sum('add_number');

        //使用数量------------------------授权课程在订单表的课程id是授权表的id
        $whereArr = ['class_id'=>$params['course_school_id'],'school_id'=>$params['schoolid'],'oa_status'=>1,'nature'=>1,'status'=>2];
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
        $typeArr = [7,8,9];//6=单课程添加库存,7=购物车计算,8=库存补费,9=库存退费,6是总控订单, 此处排除显示
        if(isset($params['status']) && $params['status']){
            switch($params['status']){
                case 1://未支付
                    $whereArr[] = ['status','=',$params['status']];//订单状态
                    $typeArr = [7,8];//9是库存退费, 此处排除查询
                    break;
                case 2://已支付
                    $whereArr[] = ['status','=',$params['status']];//订单状态
                    $typeArr = [7,8];//9是库存退费, 此处排除查询
                    break;
                case 3://订单失效
                    $whereArr[] = ['status','=',$params['status']];//订单状态
                    $typeArr = [7,8];//9是库存退费, 此处排除查询
                    break;
                case 4://已退费
                    $whereArr[] = ['status','=',$params['status']];//订单状态
                    $typeArr = [9,9];//9是库存退费, 只查询9,防止whereIn出错,填充两个9
                    break;
            }
        }

        //结果集
        $field = ['id','oid','school_id','type','paytype','status','money','remark','admin_remark','apply_time','operate_time'];
        //
        $query = SchoolOrder::where($whereArr)->whereIn('type',$typeArr);//6789都属于库存类订单
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
            //库存退费只有已退费一种状态
            if($v['type']==9){
                $list[$k]['status_text'] = '已退费';
            }
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

    /**
     * 购物车结算同时对未授权课程先进行授权
     */
    public static function doCourseSchool($schoolid,$courseIds)
    {
        if(count($courseIds)==1){
            $courseid = implode(',',$courseIds);
            $courseIds[] = $courseid;
            $courseIds[] = $courseid;//填充数组>一个值, 否则不能使用whereIn
        }

        $arr = [];
        $subjectArr = $InsertSubjectRef = [];//科目
        $questionIds = $InsertQuestionArr = [];//问题
        $teacherIdArr = $InsertTeacherRef = [];//讲师
        $bankids = [];//答卷
        $InsertRecordVideoArr = [];//资源

        //当前登录的用户id
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;

        //要授权课程 所有信息
        $field = [
            'parent_id','child_id','title','keywords','cover',
            'pricing','sale_price','buy_num','expiry','describe',
            'introduce','status','watch_num','is_recommend',
            'id as course_id','school_id as from_school_id'
        ];
        $course = Coures::whereIn('id',$courseIds)->where(['is_del'=>0])
            ->select($field)->get()->toArray();
        if(!$course){
            return false;
        }
        foreach($course as $kc=>&$vc){
            $vc['from_school_id'] = 1;//定义为当前总校
            $vc['to_school_id'] = $schoolid;
            $vc['admin_id'] = $user_id;
            $vc['create_at'] = date('Y-m-d H:i:s');
            $courseSubjectArr[$kc]['parent_id'] = $vc['parent_id'];
            $courseSubjectArr[$kc]['child_id'] = $vc['child_id'];
        }//授权课程信息

        //要授权的教师信息
        $ids = Couresteacher::whereIn('course_id',$courseIds)->where('is_del',0)->pluck('teacher_id')->toArray();

        if($ids){
            $ids = array_unique($ids);
            //已经授权过的讲师信息
            $teacherIds = CourseRefTeacher::where(['to_school_id'=>$schoolid,'is_del'=>0,'is_public'=>0])->pluck('teacher_id')->toArray();
            if($teacherIds){
                $teacherIdArr = array_diff($ids,$teacherIds);//不在授权讲师表里的数据
            }else{
                $teacherIdArr = $ids;
            }
            if($teacherIdArr){
                foreach($teacherIdArr as $key => $id){
                    $InsertTeacherRef[$key]['from_school_id'] = 1;
                    $InsertTeacherRef[$key]['to_school_id'] = $schoolid;
                    $InsertTeacherRef[$key]['teacher_id'] = $id;
                    $InsertTeacherRef[$key]['is_public'] = 0;
                    $InsertTeacherRef[$key]['admin_id'] = $user_id;
                    $InsertTeacherRef[$key]['create_at'] = date('Y-m-d H:i:s');
                }
            }//讲师加入授权表end
        }

        //学科
        $courseSubjectArr = array_unique($courseSubjectArr,SORT_REGULAR);
        $subjectArr = CourseRefSubject::where(['from_school_id'=>$schoolid,'is_del'=>0,'is_public'=>0])
            ->select('parent_id','child_id')->get()->toArray();  //已经授权过的学科
        if($subjectArr){
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
            $InsertSubjectRef[$key]['from_school_id'] = 1;
            $InsertSubjectRef[$key]['to_school_id'] = $schoolid;
            $InsertSubjectRef[$key]['admin_id'] = $user_id;
            $InsertSubjectRef[$key]['create_at'] = date('Y-m-d H:i:s');
        }

        //录播资源
        $recordVideoIds = Coureschapters::whereIn('course_id',$courseIds)
            ->where(['is_del'=>0])->pluck('resource_id as id')->toArray(); //要授权的录播资源
        if(!empty($recordVideoIds)){
            $narturecordVideoIds = CourseRefResource::where(['to_school_id'=>$schoolid,'type'=>0,'is_del'=>0])
                ->pluck('resource_id as id ')->toArray(); //已经授权过的录播资源
            $recordVideoIds = array_diff($recordVideoIds,$narturecordVideoIds);
            foreach ($recordVideoIds as $key => $v) {
                $InsertRecordVideoArr[$key]['resource_id']=$v;
                $InsertRecordVideoArr[$key]['from_school_id'] = 1;
                $InsertRecordVideoArr[$key]['to_school_id'] = $schoolid;
                $InsertRecordVideoArr[$key]['admin_id'] = $user_id;
                $InsertRecordVideoArr[$key]['type'] = 0;
                $InsertRecordVideoArr[$key]['create_at'] = date('Y-m-d H:i:s');
            }
        }

        //直播资源
        $zhiboVideoIds = CourseLivesResource::whereIn('course_id',$courseIds)->where(['is_del'=>0])->pluck('id')->toArray();//要授权的直播资源
        if(!empty($zhiboVideoIds)){
            $narturezhiboVideoIds = CourseRefResource::where(['from_school_id'=>1,'to_school_id'=>$schoolid,'type'=>1,'is_del'=>0])->pluck('resource_id as id ')->toArray();
            $zhiboVideoIds = array_diff($zhiboVideoIds,$narturezhiboVideoIds);
            foreach ($zhiboVideoIds as $key => $v) {
                $InsertZhiboVideoArr[$key]['resource_id']=$v;
                $InsertZhiboVideoArr[$key]['from_school_id'] = 1;
                $InsertZhiboVideoArr[$key]['to_school_id'] = $schoolid;
                $InsertZhiboVideoArr[$key]['admin_id'] = $user_id;
                $InsertZhiboVideoArr[$key]['type'] = 1;
                $InsertZhiboVideoArr[$key]['create_at'] = date('Y-m-d H:i:s');
            }
        }

        //题库
        foreach($courseSubjectArr as $key=>&$vs){
            $bankIdArr = QuestionBank::where(['parent_id'=>$vs['parent_id'],'child_id'=>$vs['child_id'],'is_del'=>0,'school_id'=>$schoolid])->pluck('id')->toArray();
            if(!empty($bankIdArr)){
                foreach($bankIdArr as $k=>$vb){
                    array_push($bankids,$vb);
                }
            }
        }
        if(!empty($bankids)){
            $bankids=array_unique($bankids);
            $natureQuestionBank = CourseRefBank::where(['from_school_id'=>1,'to_school_id'=>$schoolid,'is_del'=>0])->pluck('bank_id')->toArray();
            $bankids = array_diff($bankids,$natureQuestionBank);
            foreach($bankids as $key=>$bankid){
                $InsertQuestionArr[$key]['bank_id'] =$bankid;
                $InsertQuestionArr[$key]['from_school_id'] = 1;
                $InsertQuestionArr[$key]['to_school_id'] = $schoolid;
                $InsertQuestionArr[$key]['admin_id'] = $user_id;
                $InsertQuestionArr[$key]['create_at'] = date('Y-m-d H:i:s');
            }
        }

        $teacherRes = CourseRefTeacher::insert($InsertTeacherRef);//教师
        if(!$teacherRes){
            return false;
        }
        $subjectRes = CourseRefSubject::insert($InsertSubjectRef);//学科
        if(!$subjectRes){
            return false;
        }

        if(!empty($InsertRecordVideoArr)){
            $InsertRecordVideoArr = array_chunk($InsertRecordVideoArr,500);
            foreach($InsertRecordVideoArr as $key=>$lvbo){
                $recordRes = CourseRefResource::insert($lvbo); //录播
                if(!$recordRes){
                    return false;
                }
            }
        }

        if(!empty($InsertZhiboVideoArr)){
            $InsertZhiboVideoArr = array_chunk($InsertZhiboVideoArr,500);
            foreach($InsertZhiboVideoArr as $key=>$zhibo){
                $zhiboRes = CourseRefResource::insert($zhibo); //直播
                if(!$zhiboRes){
                    return false;
                }
            }
        }

        $bankRes = CourseRefBank::insert($InsertQuestionArr); //题库
        if(!$bankRes){
            return false;
        }
        $courseRes = CourseSchool::insert($course); //
        if(!$courseRes){
            return false;
        }else{
            return true;
        }

    }

    /**
     * 恢复失效库存更换订单的库存
     * 当订单为库存补费, 并且状态为失效, operate_time为空的时候可执行此方法
     */
    public static function recoveryRefundOrderStocks($id,$schoolid)
    {
        $oid = SchoolOrder::where('id',$id)->where('school_id',$schoolid)->where('operate_time',null)->value('oid');
        if(!$oid){
            return ['code'=>203,'msg'=>'无效操作'];
        }

        // 将operate_time设置时间,前段根据此字段判断已经执行过库存恢复, 不再显示恢复库存按钮,
        // 将被替换课程的库存数据恢复
        $res = SchoolOrder::where('id',$id)->update(['operate_time'=>date('Y-m-d H:i:s')]);
        $res = CourseStocks::where('oid',$oid)->where('add_number','<',0)->update(['is_del'=>0,'is_forbid'=>0]);

        return ['code'=>200,'msg'=>'success'];
    }

    /**********************************未支付订单去支付********/

    /**
     * 购物车结算订单去支付
     */
    public static function stockShopCartOrderAgainPay($params)
    {

        //本订单库存表数据
        $wheres = [
            'oid'       => $params['oid'],
            'school_id' => $params['schoolid'],
            //'is_forbid' => 1,//禁用
            //'is_del'    => 1,//删除
        ];
        $field = ['id','course_id','price','add_number'];
        $lists = CourseStocks::where($wheres)->select($field)->get()->toArray();
        if(empty($lists)){
            return ['code'=>203,'msg'=>'未找到订单'];
        }

        //组装课程id
        foreach($lists as $k=>$v){
            $courseids[] = $v['course_id'];
        }
        //防止whereIn报错
        if(count($courseids)==1) $courseids[] = $courseids[0];

        //获取授权价格
        $count = Schoolcourse::where(['school_id'=>$params['schoolid']])->count();
        if($count > 0){
            $priceArr = Schoolcourse::where(['school_id'=>$params['schoolid']])->whereIn('course_id',$courseids)->value('course_price','course_id');
        }else{
            $priceArr = Coures::whereIn('id',$courseids)->pluck('impower_price','id');
        }
        //整理入库存表数据
        $money = 0;

        foreach($lists as $k=>$v)
        {
            $price = isset($priceArr[$v['course_id']])?$priceArr[$v['course_id']]:0;
            $lists[$k]['price'] = $price;
            $money += $v['add_number'] * $price;
        }

        //整理琐碎数据入数组
        $params['money'] = $money;

        //查询网校当前余额与 订单金额做对比
        $schools = School::where('id',$params['schoolid'])->select('balance','give_balance')->first();
        $balance = $schools['balance'] + $schools['give_balance'];
        if($balance < $money){
            //1, 余额不足
            return [
                'code'=>209,
                'msg'=>'账户余额不足,请充值',
                'data'=>[
                    'money'=>$money,
                ]
            ];

        }

        //2, 余额充足的情况, 将订单状态改为成功
        $return = self::UpdateStockNoPayOrder($schools,$lists,$params);
        if($return['code']!=200){

            return $return;
        }

        //返回最终结果

        return [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'money'=>$money,
            ]
        ];


    }

    /**
     * 更改库存订单状态 与 库存数据为有效
     * @param $schools object 学校账户信息
     * @param $params array 请求参数oid等
     * @param $list array 待更新的库存表数据
     * @return array
     */
    public static function UpdateStockNoPayOrder($schools,$lists,$params)
    {
        $datetime = date('Y-m-d H:i:s');
        DB::beginTransaction();
        try{
            //账户扣款
            if($params['money']>0){
                $return_account = SchoolAccount::doBalanceUpdate($schools,$params['money'],$params['schoolid']);
                if(!$return_account['code']){
                    DB::rollBack();
                    return ['code'=>201,'msg'=>'请检查余额是否充足'];
                }
            }
            //修改订单表状态为成功,订单金额, 修改支付时间
            $update = [
                'status'        => 2,//success
                'money'         => $params['money'],
                'use_givemoney' => isset($return_account['use_givemoney'])?$return_account['use_givemoney']:0,//用掉了多少赠送金额
                'operate_time'  => $datetime,
            ];
            $res = SchoolOrder::where('oid',$params['oid'])->where('school_id',$params['schoolid'])->update($update);
            if(!$res){
                DB::rollBack();
                return ['code'=>201,'msg'=>'支付失败, 请重试'];
            }

            //修改库存表价格 , 状态为有效
            $update = [
                'is_forbid' => 0,//禁用
                'is_del'    => 0,//删除
            ];
            $res = 0;
            foreach($lists as $k=>$v){
                $update['price'] = $v['price'];//当前价格
                $res +=CourseStocks::where('id',$v['id'])->update($update)?1:0;
            }
            if(count($lists)!=$res){
                //成功数量!=库存表待修改数量
                DB::rollBack();
                return ['code'=>201,'msg'=>'库存补充失败, 请重试'];
            }

            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;//当前登录账号id
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'Service' ,
                'route_url'      =>  ltrim($_SERVER['REQUEST_URI'],'/') ,
                'operate_method' =>  'update' ,
                'content'        =>  '库存重新支付'.json_encode($params) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  $datetime,
            ]);

            //提交
            DB::commit();
            return ['code'=>200,'msg'=>'success'];

        }catch(\Exception $e){
            DB::rollBack();
            Log::error('购物车结算订单重新支付_error_msg'.$e->getMessage().'_file_'.$e->getFile().'_line_'.$e->getLine());
            return ['code'=>500,'msg'=>'遇到异常'];
        }

    }

    /**
     * 库存更换订单去支付
     */
    public static function stockReplaceOrderAgainPay($params)
    {
        //本订单库存表数据
        $wheres = [
            'oid'       => $params['oid'],
            'school_id' => $params['schoolid'],
            //'is_forbid' => 1,//禁用
            //'is_del'    => 1,//删除
        ];
        $field = ['id','course_id','price','add_number','is_forbid'];
        $lists = CourseStocks::where($wheres)->select($field)->get()->toArray();
        if(empty($lists)){
            return ['code'=>203,'msg'=>'未找到订单'];
        }

        //组装课程id
        foreach($lists as $k=>$v){
            $courseids[] = $v['course_id'];
        }
        //防止whereIn报错
        if(count($courseids)==1) $courseids[] = $courseids[0];
        //获取授权价格
        $count = Schoolcourse::where(['school_id'=>$params['schoolid']])->count();
        if($count > 0){
            $priceArr = Schoolcourse::where(['school_id'=>$params['schoolid']])->whereIn('course_id',$courseids)->value('course_price','course_id');
        }else{
            $priceArr = Coures::whereIn('id',$courseids)->pluck('impower_price','id');
        }
        //整理入库存表数据
        $replace_money = 0;//可抵扣金额
        $need_money = 0;//需使用金额
        foreach($lists as $k=>$v)
        {
            $price = isset($priceArr[$v['course_id']])?$priceArr[$v['course_id']]:0;
            $lists[$k]['price'] = $price;
            if($v['is_forbid']){
                //增加的课程库存
                $need_money += $v['add_number'] * $price;
            }else{
                //被替换的库存
                $replace_money += $v['add_number'] * $price;
                //价格计算结束后, 销毁被替换的课程的数据, 因为下一步更换库存状态为成功的时候用不到
                unset($lists[$k]);
            }
        }
        //若需使用金额 小于等于 可抵扣金额, 证明此订单已经不属于库存补费订单, 与此订单形成初始状态不符, 此时做一个失效操作
        if($need_money<$replace_money){
            SchoolOrder::where('oid',$params['oid'])->update(['status'=>3]);
            return [
                'code' => 2090,
                'msg'  => '订单失效',
            ];
        }

        //整理琐碎数据入数组
        $params['money'] = $need_money - $replace_money;//需补充金额

        //查询网校当前余额与 订单金额做对比
        $schools = School::where('id',$params['schoolid'])->select('balance','give_balance')->first();
        $balance = $schools['balance'] + $schools['give_balance'];
        if($balance < $params['money']){
            //1, 余额不足
            return [
                'code'=>209,
                'msg'=>'账户余额不足,请充值',
                'data'=>[
                    'money'=>$params['money'],
                ]
            ];

        }

        //2, 余额充足的情况, 将订单状态改为成功
        $return = self::UpdateStockReplaceNoPayOrder($schools,$lists,$params);
        if($return['code']!=200){
            return $return;
        }

        //返回最终结果

        return [
            'code'=>200,
            'msg'=>'success',
            'data'=>[
                'money'=>$params['money'],
            ]
        ];

    }

    /**
     * 更换库存更换订单为成功状态
     */
    public static function UpdateStockReplaceNoPayOrder($schools,$lists,$params)
    {
        $datetime = date('Y-m-d H:i:s');
        DB::beginTransaction();
        try{
            //账户扣款
            if($params['money']>0){
                $return_account = SchoolAccount::doBalanceUpdate($schools,$params['money'],$params['schoolid']);
                if(!$return_account['code']){
                    DB::rollBack();
                    return ['code'=>201,'msg'=>'请检查余额是否充足'];
                }
            }
            //修改订单表状态为成功,订单金额, 修改支付时间
            $update = [
                'status'        => 2,//success
                'money'         => $params['money'],
                'use_givemoney' => isset($return_account['use_givemoney'])?$return_account['use_givemoney']:0,//用掉了多少赠送金额
                'operate_time'  => $datetime,
            ];
            $res = SchoolOrder::where('oid',$params['oid'])->where('school_id',$params['schoolid'])->update($update);
            if(!$res){
                DB::rollBack();
                return ['code'=>201,'msg'=>'支付失败, 请重试'];
            }

            //修改库存表价格 , 状态为有效
            $update = [
                'is_forbid' => 0,//正常
                'is_del'    => 0,//正常
            ];
            $res = 0;
            foreach($lists as $k=>$v){
                $update['price'] = $v['price'];//当前价格
                $res +=CourseStocks::where('id',$v['id'])->update($update)?1:0;
            }
            if(count($lists)!=$res){
                //成功数量!=库存表待修改数量
                DB::rollBack();
                return ['code'=>201,'msg'=>'库存补充失败, 请重试'];
            }

            $admin_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;//当前登录账号id
            //添加日志操作
            AdminLog::insertAdminLog([
                'admin_id'       =>  $admin_id ,
                'module_name'    =>  'Service' ,
                'route_url'      =>  ltrim($_SERVER['REQUEST_URI'],'/') ,
                'operate_method' =>  'update' ,
                'content'        =>  '库存更换订单重新支付'.json_encode($params) ,
                'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                'create_at'      =>  $datetime,
            ]);

            //提交
            DB::commit();
            return ['code'=>200,'msg'=>'success'];

        }catch(\Exception $e){
            DB::rollBack();
            Log::error('库存更换订单重新支付_error_msg'.$e->getMessage().'_file_'.$e->getFile().'_line_'.$e->getLine());
            return ['code'=>500,'msg'=>'遇到异常'];
        }

    }






}
