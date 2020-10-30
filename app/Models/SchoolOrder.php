<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SchoolAccount;
use App\Models\CourseStocks;
use App\Models\ServiceRecord;
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
            ['online','=',0]
        ];

        //搜索条件
        if(isset($params['status']) && $params['status']){
            $whereArr[] = ['status','=',$params['status']];//订单状态
        }
        if(isset($params['type']) && $params['type']){
            $whereArr[] = ['type','=',$params['type']];//订单类型
        }

        //总数
        $total = self::where($whereArr)->count();
        //结果集
        $field = ['id','oid','school_id','type','paytype','status','money','remark','admin_remark','apply_time','operate_time'];
        $list = self::where($whereArr)->select($field)
                ->offset(($page-1)*$pagesize)
                ->limit($pagesize)->get()->toArray();
        $texts = self::tagsText(['pay','status','service','type']);
        foreach($list as $k=>$v){
            //订单类型
            $list[$k]['type_text'] = isset($texts['type_text'][$v['type']])?$texts['type_text'][$v['type']]:'';
            //支付类型
            $list[$k]['paytype_text'] = isset($texts['pay_text'][$v['paytype']])?$texts['pay_text'][$v['paytype']]:'';
            //订单状态
            $list[$k]['status_text'] = isset($texts['status_text'][$v['status']])?$texts['status_text'][$v['status']]:'';
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
            'searchs'=>[
                'status'=>[
                    1=>'待审核',
                    2=>'审核通过',
                    3=>'驳回',
                ],
                'type'=>[
                    1=>'预充金额',
                    2=>'购买服务',
                ]
            ]
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
        //标签字段
        $texts = self::tagsText(['pay','status','service','type','service_record']);
        if($data){
            //订单类型
            $data['type_text'] = isset($texts['type_text'][$data['type']])?$texts['type_text'][$data['type']]:'';
            //支付类型
            $data['paytype_text'] = isset($texts['pay_text'][$data['paytype']])?$texts['pay_text'][$data['paytype']]:'';
            //订单状态
            $data['status_text'] = isset($texts['status_text'][$data['status']])?$texts['status_text'][$data['status']]:'';
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
                        $v['course'] = isset($courseArr[$v['course_id']]['title'])?$courseArr[$v['course_id']]['title']:0;
                        $v['price'] = isset($courseArr[$v['course_id']]['impower_price'])?$courseArr[$v['course_id']]['impower_price']:'';
                    }
                }
                $data['content'] = $list;
            }elseif(in_array($data['type'],[1,2])){
                $list = SchoolAccount::where('oid',$data['oid'])
                        ->select('type','money')
                        ->get()->toArray();
                foreach($list as $k=>&$v){
                    $v['type'] = isset($texts['service_text'][$v['type']])?$texts['service_text'][$v['type']]:'';
                }
                $data['content'] = $list;

            }elseif(in_array($data['type'],[3,4,5])){
                $list = ServiceRecord::where('oid',$data['oid'])
                        ->select('price','num','start_time','end_time','type')
                        ->get()->toArray();
                foreach($list as $k=>&$v){
                    $v['type_text'] = isset($texts['service_record_text'][$v['type']])?$texts['service_record_text'][$v['type']]:'';
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
        $remark = $params['remark']?:null;
        $status = $params['status'];
        $data = self::find($id);
        if(!$data){
            return ['code'=>202,'找不到订单'];
        }
        if($data['status']==2){
            return ['code'=>203,'msg'=>'不可撤销已审核通过订单'];
        }

        $arr = [
            'status'=>$status,
            'admin_remark'=>$remark,
            'manageid'=>isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0,
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
            if($status==1 || $status==3){//1=待审核,3=驳回, 此时不执行其他订单
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
                case 4:
                case 5:
                    $res1 = true;//购买服务
                    break;
                case 6:
                case 7:
                    $res1 = CourseStocks::where('oid',$data['oid'])->update(['is_official'=>0,'is_del'=>0]);
                    break;

            }
            if(!$res1){
                DB::rollBack();
                return ['code'=>205,'msg'=>'网络错误, 请重试'];
            }
            DB::commit();
            Log::info('线下订单审核成功_'.json_encode($params));
            return ['code'=>200,'msg'=>'success'];

        }catch(Exception $e){
            DB::rollBack();
            Log::info('线下订单审核失败'.json_encode($params));
            return ['code'=>206,'msg'=>$e->getMessage()];
        }

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
            'pay_text'=>[
                1=>'内部支付',
                2=>'银行汇款',
            ],
            'type_text'=>[
                1=>'充值金额',
                2=>'赠送金额',
                3=>'购买服务',
                4=>'购买服务',
                5=>'购买服务',
                6=>'购买库存',
                7=>'购买库存',
            ],
            'service_text'=>[
                1=>'充值金额',
                2=>'赠送金额',
                3=>'购买直播并发',
                4=>'购买空间',
                5=>'购买流量',
                6=>'购买库存',
                7=>'批量购买库存',
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
