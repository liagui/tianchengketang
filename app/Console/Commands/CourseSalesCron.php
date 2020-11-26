<?php

namespace App\Console\Commands;

use App\Models\SchoolOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;

class CourseSalesCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'CourseSalesUpdate';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '统计昨天的订单更改入课程销量字段';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 统计昨天的订单 , 更新入课程销量salesnum字段
     *
     * @return mixed
     */
    public function handle()
    {
        $before_time = time();
        $yesterday = date("Y-m-d H:i:s", strtotime("-1 day"));

        //自增课程
        $msg = '统计订单课程销售量开始';
        $course_salesArr = DB::table('ld_order')
            ->where('nature',0)
            ->where('pay_time','>=',$yesterday)
            ->where('oa_status',1)//订单成功
            ->where('status',2)//订单成功
            ->whereIn('pay_status',[3,4])//付费完成订单
            ->select(DB::raw('count(class_id) as total,class_id'))
            ->groupBy('class_id')
            ->get()->toArray();
        $course_salesArr = json_decode(json_encode($course_salesArr),true);
        $msg .= '自增课程 '.count($course_salesArr).'门, ';

        //统计授权课程
        $course_school_salesArr = DB::table('ld_order as order')
            ->join('ld_course_school as course','course.id','=','order.class_id')
            ->where('order.nature',1)
            ->where('order.oa_status',1)//订单成功
            ->where('order.status',2)//订单成功
            ->where('pay_time','>=',$yesterday)
            ->whereIn('order.pay_status',[3,4])//付费完成订单
            ->select(DB::raw('count(order.class_id) as total,course.course_id'))
            ->groupBy('order.class_id')
            ->get()->toArray();
        $course_school_salesArr = json_decode(json_encode($course_school_salesArr),true);
        $msg .= '授权课程 '.count($course_school_salesArr) .' 门, ';
        $saleArr = [];

        //根据course_id 将自增课程写入新数组
        foreach($course_salesArr as $a){
            if( !isset($saleArr[$a['class_id']]) ){
                $saleArr[$a['class_id']] = $a['total'];
            }else{
                $saleArr[$a['class_id']] += $a['total'];
            }
        }

        //根据course_id 将授权课程写入新数组
        foreach($course_school_salesArr as $a){
            if( !isset($saleArr[$a['course_id']]) ){
                $saleArr[$a['course_id']] = $a['total'];
            }else{
                $saleArr[$a['course_id']] += $a['total'];
            }
        }
        $msg.='将授权课程转换到course表后, 共'.count($saleArr).'门, ';

        //执行
        $i = 0;
        foreach($saleArr as $k=>$v){
            if($k%100==0){
                sleep(5);
            }
            DB::table('ld_course')->where('id',$k)->increment('salesnum', $v);
            $i++;
        }
        //end
        $use_time = time() - $before_time;
        $msg .='统计入库结束,用时' . $use_time . '秒 。';
        Log::info($msg);

    }

}
