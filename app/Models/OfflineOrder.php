<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OfflineOrder extends Model {
    //指定别的表名
    public $table = 'ld_offline_order';
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
     * @ctime 2020/10/22
     * @return array
     */
    public static function getlist($params)
    {
        $page = (int) (isset($params['page']) && $params['page'])?$params['page']:1;
        $pagesize = (int) (isset($params['pagesize']) && $params['pagesize'])?$params['pagesize']:15;

        //预定义固定条件
        $whereArr = [
            ['id','>',0]
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
        $list = self::where($whereArr)->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();
        $data = [
            'total'=>$total,
            'list'=>$list,
            'texts'=>self::tagsText(['pay','status','service','type']),
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
        if($data){
            //type int 订单类型:[1=预充金额,2=赠送金额],[3=购买直播并发,4=购买空间,5=购买流量],6=购买库存,7=批量购买库存
            if(in_array($data['type'],[6,7])){
                $list = CourseStocks::where('oid',$data['oid'])->select('school_id','course_id','add_number')->get()->toArray();
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
        return $lastid;
    }

    /**
     *审核线下订单
     */
    public static function doedit($params)
    {
        $id = $params['id'];
        $remark = $params['remark']?:null;
        $data = self::find($id);
        if(!$data){
            return ['code'=>202,'找不到订单'];
        }
        if($data['status']==2){
            return ['code'=>203,'msg'=>'不可撤销已审核通过订单'];
        }

        $arr = ['status'=>$status,'admin_remark'=>$remark,'operate_time'=>date('Y-m-d H:i:s')];

        //开启事务
        DB::beginTransaction();
        try{
            //更改状态
            $res = self::where('id',$id)->update($arr);
            if(!$res){
                return ['code'=>204,'msg'=>'没有执行更改'];
            }
            if($status==1 || $status==3){//1=待审核,3=驳回, 此时不执行其他订单
                return ['code'=>200,'msg'=>'success'];
            }
            //更改库存等操作
            //订单类型:1=预充金额,2=赠送金额,3=购买直播并发,4=购买空间,5=购买流量,6=购买库存,7=批量购买库存
            switch($data['type']){
                case 1:
                case 2:
                    $res1 = true;
                    break;
                case 3:
                case 4:
                case 5:
                    $res1 = true;
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
            return ['code'=>200,'msg'=>'success'];

        }catch(Exception $e){
            DB::rollBack();
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
     * 标签对应文字
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
                1=>'银行汇款',
                2=>'内部支付',
            ],
            'type_text'=>[
                1=>'预充金额',
                2=>'赠送金额',
                3=>'购买服务',
                4=>'购买服务',
                5=>'购买服务',
                6=>'购买服务',
                7=>'购买服务',
            ],
            'service_text'=>[
                1=>'预充金额',
                2=>'赠送金额',
                3=>'购买直播并发',
                4=>'购买空间',
                5=>'购买流量',
                6=>'购买库存',
                7=>'批量购买库存',
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
