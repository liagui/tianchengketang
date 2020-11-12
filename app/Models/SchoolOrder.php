<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SchoolAccount;
use App\Models\CourseStocks;
use App\Models\ServiceRecord;
use App\Models\SchoolResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 线下订单, 手动打款, 服务购买, 库存充值等
 * @author laoxian
 */
class SchoolOrder extends Model {
    //指定别的表名
    public $table = 'ld_school_order';
    //时间戳设置
    public $timestamps = false;

    //错误信息
    public static function message()
    {
        return [
            'status.integer'  => json_encode(['code'=>'201','msg'=>'状态参数不合法']),
            'type.integer'   => json_encode(['code'=>'202','msg'=>'类型参数不合法'])
        ];
    }

    /**
     * 订单列表
     * @author laoxian
     * @time 2020/10/22
     * @return array
     */
    public static function getlist($params)
    {
        $page = (int) (isset($params['page']) && $params['page'])?$params['page']:1;
        $pagesize = (int) (isset($params['pagesize']) && $params['pagesize'])?$params['pagesize']:15;

        //预定义固定条件
        $whereArr = [
            ['online','=',0]//线下订单
        ];

        //搜索条件
        if(isset($params['status']) && $params['status']){
            $whereArr[] = ['status','=',$params['status']];//订单状态
        }
        //订单类型:1=预充金额,2=赠送金额,3=购买直播并发,4=购买空间,5=购买流量,6=购买库存,7=批量购买库存,8=库存补费,9=库存退费
        if(isset($params['type']) && $params['type']){
            $types = ['a','b'];//预定义一个搜索结果一定为空的条件
            if($params['type']==1){
                $types = [1,2];
            }elseif($params['type']==2){
                $types = [3,4,5,6,7];
            }
            $whereArr[] = [function($query) use ($types){
                $query->whereIn('type', $types);
            }];
        }

        //总数
        $total = self::where($whereArr)->count();
        //结果集
        $field = ['id','oid','school_id','type','paytype','status','money','remark','admin_remark','apply_time','operate_time'];
        $list = self::where($whereArr)->select($field)->orderBy('id','desc')
                ->offset(($page-1)*$pagesize)
                ->limit($pagesize)->get()->toArray();

        //获取网校id集合
        $schoolids = array_unique(array_column($list,'school_id'));
        $schoolArr = School::whereIn('id',$schoolids)->pluck('name','id');

        $texts = self::tagsText(['pay','status','service','type']);
        foreach($list as $k=>$v){
            //订单类型
            $list[$k]['type_text'] = isset($texts['type_text'][$v['type']])?$texts['type_text'][$v['type']]:'';
            //支付类型
            $list[$k]['paytype_text'] = isset($texts['pay_text'][$v['paytype']])?$texts['pay_text'][$v['paytype']]:'';
            //订单状态
            $list[$k]['status_text'] = isset($texts['status_text'][$v['status']])?$texts['status_text'][$v['status']]:'';
            //当支付方式是银行汇款时候, 状态字段独立处理
            if($v['paytype']==2){
                $status_text = '';
                if($v['status']==1){
                    $status_text = '汇款中';
                }elseif($v['status']==2){
                    $status_text = '已支付';
                }elseif($v['status']==3){
                    $status_text = '未支付';
                }
                $list[$k]['status_text'] = $status_text;
            }
            //服务类型
            $list[$k]['service_text'] = isset($texts['service_text'][$v['type']])?$texts['service_text'][$v['type']]:'';
            //备注 and 管理员备注
            $list[$k]['remark'] = $v['remark']?:'';
            $list[$k]['admin_remark'] = $v['admin_remark']?:'';

            $list[$k]['school_name'] = $schoolArr[$v['school_id']];
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
     * 订单详情
     * @author laoxian
     * @return array
     */
    public static function detail($id)
    {
        $data = self::find($id);
        $status_field = $data['online']?'online_status':'status';//线上与线下的标签名称不一样

        $data['school_name'] = School::where('id',$data['school_id'])->value('name');
        //标签字段
        $texts = self::tagsText(['pay',$status_field,'service','type','service_record']);
        if($data){
            //订单类型
            $data['type_text'] = isset($texts['type_text'][$data['type']])?$texts['type_text'][$data['type']]:'';
            //支付类型
            $data['paytype_text'] = isset($texts['pay_text'][$data['paytype']])?$texts['pay_text'][$data['paytype']]:'';
            //订单状态
            $data['status_text'] = isset($texts[$status_field.'_text'][$data['status']])?$texts[$status_field.'_text'][$data['status']]:'';

            //线上订单(online)的充值金额(type)银行汇款(paytype)未支付状态下(status) 显示汇款中
            if($data['type']==1 && $data['paytype']==2){
                    $data['status_text'] = '未知';
                if($data['status']==1) {
                    $data['status_text'] = '汇款中';
                }elseif($data['status']==2){
                    $data['status_text'] = '已支付';
                }elseif($data['status']==3){
                    $data['status_text'] = '未支付';
                }
            }
            //库存退费只有已退费一种状态
            if($data['type']==9){
                $data['status_text'] = '已退费';
            }
            //服务类型
            $data['service_text'] = isset($texts['service_text'][$data['type']])?$texts['service_text'][$data['type']]:'';
            //备注 and 管理员备注

            //type int 订单类型:[1=预充金额,2=赠送金额],[3=购买直播并发,4=购买空间,5=购买流量],6=购买库存,7=批量购买库存
            if(in_array($data['type'],[6,7])){
                $list = CourseStocks::where('oid',$data['oid'])
                        ->select('course_id','add_number')
                        ->get()->toArray();
                if(!empty($list)){
                    $courseids = array_column($list,'course_id');
                    $courseArrs = Coures::whereIn('id',$courseids)->select('impower_price','title','id')->get()->toArray();
                    //将id为key赋值新数组
                    $courseArr = [];
                    foreach($courseArrs as $k=>$v){
                        $courseArr[$v['id']]['impower_price'] = $v['impower_price'];
                        $courseArr[$v['id']]['title'] = $v['title'];
                    }
                    foreach($list as $k=>&$v){
                        $v['title'] = isset($courseArr[$v['course_id']]['title'])?$courseArr[$v['course_id']]['title']:'';
                        $v['price'] = isset($courseArr[$v['course_id']]['impower_price'])?$courseArr[$v['course_id']]['impower_price']:0;
                        $v['money'] = (int) $v['price']* (int) $v['add_number'];//当前单元订单金额
                        $v['num'] = $v['add_number'];
                        unset($v['course_id']);
                        unset($v['add_number']);
                    }
                }
                $data['content'] = $list;
            }elseif(in_array($data['type'],[1,2])){
                /*$list = SchoolAccount::where('oid',$data['oid'])
                        ->select('type','money')
                        ->get()->toArray();
                foreach($list as $k=>&$v){
                    $v['type'] = isset($texts['service_text'][$v['type']])?$texts['service_text'][$v['type']]:'';
                }
                $data['content'] = $list;*/

            }elseif(in_array($data['type'],[3,4,5])){
                $list = ServiceRecord::where('oid',$data['oid'])
                        ->select('price','num','start_time','end_time','type')
                        ->get()->toArray();
                foreach($list as $k=>&$v){
                    $v['money'] = (int) $v['price']* (int) $v['num'];
                    $v['title'] = isset($texts['service_record_text'][$v['type']])?$texts['service_record_text'][$v['type']]:'';

                    if($v['type']==1){
                        $v['num'] = $v['num'].'个';
                    }elseif($v['type']==2){
                        $v['num'] = $v['num'].'G/月';
                    }elseif($v['type']==3){
                        $v['num'] = $v['num'].'G';
                    }
                    unset($v['start_time']);
                    unset($v['end_time']);
                    unset($v['type']);
                }
                $data['content'] = $list;
            }
        }
        return ['code'=>200,'msg'=>'success','data'=>$data];
    }

    /**
     * @param $data
     * @return mixed
     */
    public static function doinsert($data)
    {
        $lastid = self::insertGetId($data);

        Log::info('订单表_'.json_encode($data));
        return $lastid;
    }

    /**
     *审核线下订单
     */
    public static function doedit($params)
    {
        $id = $params['id'];
        $remark = isset($params['remark'])?$params['remark']:'';
        $status = $params['status'];//1=未审核,2=审核通过,3=驳回
        $data = self::find($id);
        if(!$data){
            return ['code'=>202,'找不到订单'];
        }
        if($data['status']==2){
            return ['code'=>203,'msg'=>'当前订单已审核通过, 不可更改状态'];
        }

        $arr = [
            'status'=>$status,
            'admin_remark'=>$remark,
            'manage_id'=>isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0,
            'operate_time'=>date('Y-m-d H:i:s')
        ];

        //开启事务
        DB::beginTransaction();
        try{
            //更改状态
            $res = self::where('id',$id)->update($arr);
            if(!$res){
                DB::rollBack();
                return ['code'=>204,'msg'=>'没有执行更改'];
            }
            if($status==1 || $status==3){//1=待审核,3=驳回, 此时不执行其他数据表操作
                DB::commit();
                return ['code'=>200,'msg'=>'success'];
            }
            //更改库存等操作
            //订单类型:1=预充金额,2=赠送金额,3=购买直播并发,4=购买空间,5=购买流量,6=购买库存,7=批量购买库存
            switch($data['type']){
                case 1:
                case 2:
                    $res1 = SchoolAccount::where('oid',$data['oid'])->update(['status'=>$status]);
                    if($res1){
                        $money = SchoolAccount::where('oid',$data['oid'])->sum('money');
                        $res1 = School::where('id',$data['school_id'])->increment('balance',$money);
                    }
                    break;
                case 3:
                    $resource = new SchoolResource();
                    $record = ServiceRecord::where('oid',$data['oid'])->first();
                    // 网校个并发数 参数： 网校id 开始时间 结束时间 增加的并发数
                    $resource ->addConnectionNum($data['school_id'],substr($record['start_time'],0,10),substr($record['end_time'],0,10),$record['num']);
                    $res1 = true;
                    break;
                case 4:
                    $resource = new SchoolResource();
                    //获取需要 升级 and 续费 数据
                    $record = self::getStorageUpdateDetail($data['school_id']);
                    //存在扩容的信息
                    if($record['num']>0){
                        // 增加一个网校的空间 参数: 学校id 增加的空间 时间 固定参数add 固定参数video 固定参数是否使用事务 false
                        // 注意 购买空间 空间这里没有时间
                        $resource ->updateSpaceUsage($data['school_id'],$record['num'], date("Y-m-d"),'add','video',false );

                    }
                    if($record['date']){
                        // 空间续费 参数:学校的id 延期时间（延期到哪年那月）
                        $resource ->updateSpaceExpiry($data['school_id'],substr($record['date'],0,1));
                    }

                    $res1 = true;
                    break;
                case 5:
                    $resource = new SchoolResource();
                    $record = ServiceRecord::where('oid',$data['oid'])->first();
                    // 增加一个网校的流量 参数：学校id 增加的流量（单位B，helper中有参数 可以转化） 购买的日期  固定参数add 是否使用事务固定false
                    // 注意 流量没时间 限制 随买随用
                    $resource->updateTrafficUsage($data['school_id'],$record['num'], date("Y-m-d"),"add",false);
                    break;
                case 6:
                case 7:
                    $res1 = CourseStocks::where('oid',$data['oid'])->update(['is_forbid'=>0,'is_del'=>0]);
                    break;

            }
            if(!$res1){
                DB::rollBack();
                return ['code'=>205,'msg'=>'网络错误, 请重试'];
            }
            DB::commit();
            Log::info('线下订单审核成功_'.json_encode($params));
            return ['code'=>200,'msg'=>'success'];

        }catch(\Exception $e){
            DB::rollBack();
            Log::info('线下订单审核失败'.json_encode($params));
            return ['code'=>206,'msg'=>$e->getMessage()];
        }

    }

    /**
     * 返回最近空间购买的续费与升级记录
     */
    public static function getStorageUpdateDetail($schoolid)
    {
        $order = DB::table('ld_school_order as order')
            ->join('ld_service_record as record','order.oid','=','record.oid')
            ->select('record.start_time','record.end_time','record.num')
            ->where('order.school_id',$schoolid)
            ->where('order.status',2)//审核通过 or 购买成功
            ->where('order.type',4)//订单表4代表空间
            ->where('record.type',2)//服务记录表2代表空间
            ->orderBy('order.id','desc')//获取最后一条购买成功的记录
            ->limit(2)->get()->toArray();
            $order = json_decode(json_encode($order),true);
            $info = [];
            if(count($order)==1){
                //只有一条数据时, 直接使用此数据的到期时间与数量
                $info['date'] = $order[0]['end_time'];
                $info['num'] = $order[0]['num'];
            }else{
                //存在两条数据时, 使用第二条数据的num-第一条num, 得到扩容的num
                $info['num'] = $order[1]['num'] = $order[0]['num'];
                //当第二条数据的日期大于第一条数据日期时, 判断为续费, 不然判断为没有续费
                $info['date'] = strtotime($order[1]['end_time'])>strtotime($order[0]['end_time'])?$order[1]['end_time']:0;
            }
            //整合得到的info有num与date两个字段, 以上两种情况num都有为0的情况, date在第二种情况才有为0的情况
            return $info;
    }

    /**
     * 为线下订单表生成一个订单号
     */
    public static function generateOid()
    {
        //拼接订单号
        $pre=date("Ymdhis");
        $num = mt_rand(100000,999999);
        $oid =$pre . $num;

        //查询数据库
        $info = self::where('oid',$oid)->orderBy('id','desc')->value('id');

        $info && $oid = self::generateOid();

        return $oid;
    }

    /**
     * 数据库状态标签字段 对应文字
     */
    public static function tagsText($tag)
    {
        $tagArr = [
            'status_text'=>[
                1=>'待审核',
                2=>'审核通过',
                3=>'驳回',
            ],
            'online_status_text'=>[
                1=>'未支付',
                2=>'支付成功',
                3=>'失效',
            ],
            'pay_text'=>[
                1=>'内部支付',
                2=>'银行汇款',
                3=>'支付宝支付',
                4=>'微信支付',
                5=>'余额',
            ],
            'type_text'=>[
                1=>'预充金额',
                2=>'预充金额',
                3=>'购买服务',
                4=>'购买服务',
                5=>'购买服务',
                6=>'购买服务',
                7=>'购买服务',
                8=>'库存补费',
                9=>'库存退费'
            ],
            'service_text'=>[
                1=>'充值金额',
                2=>'赠送金额',
                3=>'购买直播并发',
                4=>'购买空间',
                5=>'购买流量',
                6=>'授权课程库存',
                7=>'授权课程库存',
                8=>'授权课程库存',
                9=>'授权课程库存',
            ],
            'service_record_text'=>[
                1=>'购买直播并发',
                2=>'购买空间',
                3=>'购买流量'
            ]
        ];
        $tags = [];
        foreach($tag as $k=>$v){
            $vs = $v.'_text';
            $tags[$vs] = isset($tagArr[$vs])?$tagArr[$vs]:[];
        }
        return $tags;
    }



}
