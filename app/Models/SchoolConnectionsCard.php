<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

// use App\Models\Teacher;
// use App\Models\Admin;
// use App\Models\CouresSubject;
// use App\Models\Coures;
// use App\Models\Couresmethod;

use App\Tools\CurrentAdmin;

/**
 *
 *  并发数的购买
 *  莱斯流量卡 点卡的机制
 *  分配流量卡的时候 这里生成一张虚拟的卡
 *  分配的时候 按照类似虚拟的方式 扣除并发数
 * 提供一下功能
 *   1 添加一张并发卡
 *   2 获取某一个月份的可以分配的并发数
 *   3
 * Class SchoolBuyConnectionsCard
 * @package App\Models
 */
class SchoolConnectionsCard extends Model
{

    public $table = 'ld_school_connections_card';

    //region 类似 流量卡的的功能函数

    /**
     *  添加一张 流量卡
     *
     * @param string $school_id 网校的id
     * @param int $num 并发数
     * @param string $start_date 有效开始日期
     * @param string $end_date 有效结束日期
     */
    public function addCard(string $school_id, int $num, string $start_date, string $end_date)
    {
        // todo 这里是不是需要把日期 格式化 'Y-m-d' 的格式
        $start_date = date("Y-m-d", strtotime($start_date));
        $end_date = date("Y-m-d", strtotime($end_date));
        $data = array(
            "school_id"            => $school_id,
            "num"                  => $num,
            "effective_start_date" => $start_date,
            "effective_end_date"   => $end_date,

        );

        return $this->newModelQuery()->insert($data);
    }

    /**
     * 通过某一个日期获取该日期 可用的分配数
     * @param $school_id
     * @param $date
     */
    public function getNumByDate(string $school_id, string $date)
    {

        $date = date("Y-m-d", strtotime($date));

        $query = $this->newBaseQueryBuilder();

        $query->selectRaw("SUM(num-use_num) as count ")
            ->from($this->table)
            ->where("school_id", "=", $school_id)
            ->whereRaw("effective_start_date <=  DATE_FORMAT('$date','%Y-%m-%d') AND effective_end_date >= DATE_FORMAT('$date','%Y-%m-%d')")
            ->groupBy("school_id");

        // 查询
        $list = $query->first();
        if ($list) {
            // 返回这个月份可以使用的并发数
            return $list->count;
        }

        return 0;

    }

    public function useNumByDay($school_id, $will_use_num, $day)
    {
        // 获取当前可用的并发数
        $card_info = $this->getNumInfoByDate($school_id, $day);

        $card_update_info = array();

        $total_num = 0; //当前已经扣除的并发数

        foreach ($card_info as $card) {

            // 计算当前卡的可用分配并发数
            $current_card_free_num = intval($card[ 'num' ]) - intval($card[ 'use_num' ]);
            $current_card_will_be_use = $will_use_num - $total_num;

            //echo "current_card_free_num:$current_card_free_num".PHP_EOL;
            //echo "current_card_will_be_use:$current_card_will_be_use".PHP_EOL;
            // 判断已分配的 并发数是否满足 要分配的并发数 如果满足直接分配
            if ($total_num >= $will_use_num) {
                break;
            }

            // 这张卡的可用num 大于等于当前卡的需要分配并发数
            if ($current_card_free_num >= $current_card_will_be_use) {

                $total_num += $current_card_will_be_use;
               // echo "1 del:".$current_card_will_be_use.PHP_EOL;
                $card_update_info[] = array(
                    "card_id"      => $card[ 'id' ],
                    'will_use_num' => $current_card_will_be_use
                );
            } else {
                // 这张卡的可用并发数 小于要分配的点数 那么先全部扣除
                $total_num += $current_card_free_num;
                //echo "2 del:".$current_card_free_num.PHP_EOL;
                $card_update_info[] = array(
                    "card_id"      => $card[ 'id' ],
                    'will_use_num' => $current_card_free_num
                );
            }

            //只要扣够了 并发数 那么不在循环
            if ($total_num == $will_use_num) {
                break;
            }

        }

        // 遍历完所有的卡那么 查看一下
        if ($total_num < $will_use_num) {
            //echo " calc total_num:$total_num will_use_num: $will_use_num ".PHP_EOL;
            return false;
        } else {
            // 更新本次扣减的每一行并发卡数
            $query = $this->newQuery();
            foreach ($card_update_info as $info){
                // 用了几张卡 那么就  增加几张卡的 use_num
                $query->where("id","=" ,$info['card_id'])->increment("use_num",$info['will_use_num']);
            }

            return $card_update_info;
        }


    }

    public function getNumInfoByDate(string $school_id, string $date)
    {
        $date = date("Y-m-d", strtotime($date));

        $query = $this->newBaseQueryBuilder();

        // 寻找 当前日期可用的 并发流量卡
        $query->select([ "id", "num", "effective_start_date", "effective_end_date", "use_num" ])
            ->from($this->table)
            ->where("school_id", "=", $school_id)
            ->whereRaw("effective_start_date <=  DATE_FORMAT('$date','%Y-%m-%d') AND effective_end_date >= DATE_FORMAT('$date','%Y-%m-%d')")
            ->orderBy("id");


        $list = $query->get();
        $ret_list = array();
        if ($list) {
            foreach ($list as $item)
                // 返回这个月份可以使用的并发卡
                $ret_list[] = array(
                    "id"                   => $item->id,
                    "num"                  => $item->num,
                    "effective_start_date" => $item->effective_start_date,
                    "effective_end_date"   => $item->effective_end_date,
                    "use_num"              => $item->use_num,

                );
        }

        return $ret_list;

    }

    // endregion


}


