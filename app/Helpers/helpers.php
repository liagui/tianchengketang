<?php

use App\Console\Commands\CCRoomLiveAnalysisLiveCron;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/*返回json串
   * addtime 2020.4.17
   * auther liyinsheng
   * $code  int   状态码
   * $data  array  数据数组
   * return  string
* */
//if (! function_exists('responseJson')) {
    function responseJson($code,$msg='', $data = [])
    {
        $arr = config::get('code');
        if (!in_array($code, $arr)) {
            return response()->json(['code' => 404, 'msg' => '非法请求']);
        }else{
            return response()->json(['code' => $code, 'msg' => $arr[$code], 'data' => $data]);
        }
    }
    /*递归
    * addtime 2020.4.27
    * auther liyinsheng
    * $array  array  数据数组
    * $pid    int  父级id
    * $pid    int  层级
    * return  string
    * */

    function getTree($array, $pid =0, $level = 0){

        //声明静态数组,避免递归调用时,多次声明导致数组覆盖
        static $list = [];
        foreach ($array as $key => $value){
            //第一次遍历,找到父节点为根节点的节点 也就是pid=0的节点
            if ($value['pid'] == $pid){
                //父节点为根节点的节点,级别为0，也就是第一级
                $value['level'] = $level;
                //把数组放到list中
                $list[] = $value;
                //把这个节点从数组中移除,减少后续递归消耗
                unset($array[$key]);
                //开始递归,查找父ID为该节点ID的节点,级别则为原级别+1
                getTree($array, $value['id'], $level+1);

            }
        }
        return $list;
    }
//}

/*
 * @param  description   获取IP地址
 * @param  author        dzj
 * @param  ctime         2020-04-27
 */
function getip() {
    static $realip;
    if (isset($_SERVER)) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $realip = $_SERVER['REMOTE_ADDR'];
        }
    } else {
        if (getenv('HTTP_X_FORWARDED_FOR')) {
            $realip = getenv('HTTP_X_FORWARDED_FOR');
        } else if (getenv('HTTP_CLIENT_IP')) {
            $realip = getenv('HTTP_CLIENT_IP');
        } else {
            $realip = getenv('REMOTE_ADDR');
        }
    }
    return $realip;
}


 /*
 * @param  descriptsion    实现三级分类的列表
 * @param  author          dzj
 * @param  ctime           2020-04-29
 * return  array
 */
function getParentsList($categorys,$pId = 0,$l=0){
    $list =array();
    foreach ($categorys as $k=>$v){
        if ($v['parent_id'] == $pId){
            unset($categorys[$k]);
            if ($l < 2){
                $v['children'] = getParentsList($categorys,$v['id'],$l+1);
            }
            $list[] = $v;
        }
    }
    return $list;
}

 /*
 * @param  descriptsion    权限管理数组处理
 * @param  author          lys
 * @param  ctime           2020-05-23
 * return  array
 */

function getAuthArr($arr){
   foreach ($arr as $key => $value) {
            if ($value['parent_id'] == 0) {
                $arr_1 = $value;
                foreach ($arr as $k => $v) {
                    if ($v['parent_id'] == $value['id']) {
                        $arr_2 = $v;
                        foreach ($arr as $kk => $vv) {
                            if ($vv['parent_id'] == $v['id']) {
                                $arr_3 = $vv;
                                $arr_3['parent_id'] = $arr_1['id'].','.$arr_2['id'];
                                $arr_2['child_arr'][] = $arr_3;
                            }
                        }
                        $arr_1['child_arr'][] = $arr_2;
                    }
                }
                $new_arr[] = $arr_1;
            }
        }
        return $new_arr;
}

 /*
 * @param  descriptsion    随机生成字符串
 * @param  author          dzj
 * @param  ctime           2020-04-29
 * return  array
 */
function randstr($len=6){
    $chars='abcdefghijklmnopqrstuvwxyz0123456789';
    mt_srand((double)microtime()*1000000*getmypid());
    $password='';
    while(strlen($password)<$len)
    $password.=substr($chars,(mt_rand()%strlen($chars)),1);
    return $password;
}

 /*
 * @param  descriptsion    判断客户端平台（PC、安卓、iPhone、平板）
 * @param  author          dzj
 * @param  ctime           2020-07-06
 * return  array
 */
function verifyPlat(){
    $agent      = strtolower($_SERVER['HTTP_USER_AGENT']);
    $is_pc      = (strpos($agent, 'windows nt')) ? true : false;
    $is_iphone  = (strpos($agent, 'iphone')) ? true : false;
    $is_ipad    = (strpos($agent, 'ipad')) ? true : false;
    $is_android = (strpos($agent, 'android')) ? true : false;

    //平台判断返回对应的字符串
    if($is_pc){
        return 'pc';
    }else if($is_iphone){
        return 'iphone';
    }else if($is_ipad){
        return 'ipad';
    }else if($is_android){
        return 'android';
    }
}



/*
 * @param  descriptsion    时间戳转时长
 * @param  author          lys
 * @param  ctime           2020-04-29
 * return  array
 */
  function timetodate($c){
        if($c < 86400){
            $time = explode(' ',gmstrftime('%H %M %S',$c));
            $duration = $time[0].'小时'.$time[1].'分'.$time[2].'秒';
        }else{
            $time = explode(' ',gmstrftime('%j %H %M %S',$c));
            $duration = ($time[0]-1).'天'.$time[1].'小时'.$time[2].'分'.$time[3].'秒';
        }
        return $duration;
    }

/*
 * @param  descriptsion    字符串排序
 * @param  author          dzj
 * @param  ctime           2020-07-10
 * return  array
 */
 function stringSort($string){
    $split_str = str_split($string);
    sort($split_str);
    return implode($split_str);
 }


/*
 * @param  descriptsion    判断一维数组是否在二维数组里
 * @param  author          lys
 * @param  ctime           2020-04-29
 * return  array
 */
function judgeEqual($key1,$key2){
    if(array_diff($key1,$key2) || array_diff($key2,$key1)){
        return true;
    }else{
        return false;
    }
}


function assoc_unique($arr, $key) {
    $tmp_arr = array();
    foreach ($arr as $k => $v) {
        if (in_array($v[$key], $tmp_arr)) {//搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true
         unset($arr[$k]);
        } else {
            $tmp_arr[] = $v[$key];
        }
    }
    sort($arr); //sort函数对数组进行排序
    return $arr;
}

function unique($str){
    //字符串中，需要去重的数据是以数字和“，”号连接的字符串，如$str,explode()是用逗号为分割，变成一个新的数组，见打印
    $arr = explode(',', $str);
    $arr = array_unique($arr);//内置数组去重算法
    $data = implode(',', $arr);
    $data = trim($data,',');//trim — 去除字符串首尾处的空白字符（或者其他字符）,假如不使用，后面会多个逗号
    return $data;//返回值，返回到函数外部
}


    /*
    * 计算两个日期相隔多少年，多少月，多少天
    * @param string $date1[格式如：2020-11-4]
    * @param string $date2[格式如：2021-12-01]
    * @author 赵老仙
    * @return array array('年','月','日');
    */
    function diffDate($date1,$date2){
        // bugfix 如果传递的时间 是一样的那么 按照一个月的时间 进行处理
        if (strtotime($date1) == strtotime($date2)) {

            $date1 = date('Y-m-01', strtotime($date1));
            $date2 = date('Y-m-d', strtotime("+1 month -1 day", strtotime($date1)));
        }

        if(strtotime($date1)>strtotime($date2)){
            $tmp=$date2;
            $date2=$date1;
            $date1=$tmp;
        }
        list($Y1,$m1,$d1)=explode('-',$date1);
        list($Y2,$m2,$d2)=explode('-',$date2);
        $Y=$Y2-$Y1;
        $m=$m2-$m1;
        $d=$d2-$d1;
        if($d<0){
            $d+=(int)date('t',strtotime("-1 month $date2"));
            $m--;
        }
        if($m<0){
            $m+=12;
            $Y--;
        }
        return array('year'=>$Y,'month'=>$m,'day'=>$d);
    }

    /**
     * 计算服务计算金额
     * @param $start_time date 开始时间
     * @param $end_time date 截止时间
     * @param $price float 价格
     * @param $num int 数量
     * @param $level int 计算级别,1=计算年,2=计算年月,3=计算年月日
     * @return $money float
     */
    function getMoney($start_time,$end_time,$price,$num,$level = 3)
    {
        $diff = diffDate(mb_substr($start_time,0,10),mb_substr($end_time,0,10));

        //金额
        $money = 0;
        if($diff['year'] && $level >= 1){
            $money += (int) $diff['year'] * $num * 12 * $price;
        }
        if($diff['month'] && $level >= 2){
            $money += (int) $diff['month'] * $num * $price;
        }
        if($diff['day'] && $level >= 3){
            $money += round((int) $diff['day'] / 30 * $num * $price,2);
        }

        return $money;

    }

    /**
     * 二维数组根据某个字段去重
     * @param array array  二维数组
     * @param field string 去重字段
     * @author 赵老仙
     * @return array  去重后的数组
     */
    function uniquArr($array,$field){
        $result = array();
        foreach($array as $k=>$val){
            if(!isset($val[$field])) die('没有找到'.$field);
            $code = false;
            foreach($result as $_val){
                if($_val[$field] == $val[$field]){
                    $code = true;
                    break;
                }
            }
            if(!$code){
                $result[]=$val;
            }
        }
        return $result;
    }

function LogDBExceiption( Exception  $e){
    $ex_str = "Exception: " .$e->getMessage().PHP_EOL;
    $ex_str .= "code lint at File:".$e->getFile()."@".$e->getLine()."@".PHP_EOL.$e->getCode();
    $ex_str .= "Trace:".PHP_EOL.$e->getTraceAsString().PHP_EOL;
    $ex_str .= "Code:".PHP_EOL.$e->getCode();
    return $ex_str;
}


function GBtoBytes(int $GB){
        return  $GB * 1024 * 1024 * 1024;
}

/**
 *  格式化B 字节到 字符串
 * @param $size
 * @param $unit
 * @param int $precision
 * @param int $decimals
 * @return string
 */
function conversionBytes($size, $unit="GB", $precision = 2, $decimals = 2)
 {
     $unit = strtoupper($unit);
     $kb = 1024; // 1KB（Kibibyte，千字节）=1024B，
     $mb = 1024 * $kb; //1MB（Mebibyte，兆字节，简称“兆”）=1024KB，
     $gb = 1024 * $mb; // 1GB（Gigabyte，吉字节，又称“千兆”）=1024MB，
     $tb = 1024 * $gb; // 1TB（Terabyte，万亿字节，太字节）=1024GB，
     $pb = 1024 * $tb; //1PB（Petabyte，千万亿字节，拍字节）=1024TB，
     $fb = 1024 * $pb; //1EB（Exabyte，百亿亿字节，艾字节）=1024PB，
     $zb = 1024 * $fb; //1ZB（Zettabyte，十万亿亿字节，泽字节）= 1024EB，
     $yb = 1024 * $zb; //1YB（Yottabyte，一亿亿亿字节，尧字节）= 1024ZB，
     $bb = 1024 * $yb; //1BB（Brontobyte，一千亿亿亿字节）= 1024YB

     if($size == 0){
         return  0;
     }
     if ($size <=($gb/100)){
         return  number_format(0.01, $decimals) ;
     }


     if ( $unit == "B" )  {
         return $size . " B";
     } else if ( $unit == "KB") {
         return number_format(round($size / $kb, $precision), $decimals) ;
     } else if ( $unit == "MB") {
         return number_format(round($size / $mb, $precision), $decimals) ;
     } else if ( $unit == "GB") {
         return number_format(round($size / $gb, $precision), $decimals) ;
     } else if ($unit == "TB") {
         return number_format(round($size / $tb, $precision), $decimals) ;
     } else if ( $unit == "PB") {
         return number_format(round($size / $pb, $precision), $decimals) ;
     } else if ($unit == "EB") {
         return number_format(round($size / $fb, $precision), $decimals) ;
     } else if ( $unit == "ZB") {
         return number_format(round($size / $zb, $precision), $decimals) ;
     } else {
         return number_format(round($size / $bb, $precision), $decimals) ;
     }


 }

/**
 *  使用数据库的事务
 * @param Closure $func
 * @param Closure $err_func
 * @return bool
 */
 function UseDBTransaction(\Closure $func, \Closure $err_func):bool{
     DB::beginTransaction();
     try {
         //$func->call();
         $call = \Closure::bind($func,null);
         $call();
         DB::commit();
         return true;
     } catch (\Exception $ex) {
         print_r(LogDBExceiption($ex));
         $err_call = \Closure::bind($err_func,null);
         $err_call($ex);
        //$err_func->call($ex);
         DB::rollBack();
         return false;
     }
 }

/**
 * 获取数组默认值
 * @param $array
 * @param $index
 * @param $default
 * @return mixed
 */
function array_get($array, $index, $default)
 {
     return isset($array[$index]) ? $array[$index] : $default;
 }


/**
 * PHP计算两个时间段是否有交集（边界重叠不算）
 *
 * @param int $a_begin_time 开始时间1
 * @param int $a_end_time 结束时间1
 * @param int $b_begin_time 开始时间2
 * @param int $b_end_time 结束时间2
 * @return bool
 */
function is_time_cross( int $a_begin_time, int $a_end_time , int $b_begin_time , int $b_end_time ) {
    $status = $b_begin_time - $a_begin_time;
    if ($status > 0) {
        $status2 = $b_begin_time - $a_end_time;
        if ($status2 >= 0) {
            return false;
        } else {
            return true;
        }
    } else {
        $status2 = $b_end_time - $a_begin_time;
        if ($status2 > 0) {
            return true;
        } else {
            return false;
        }
    }
}

function RedisTryLockGetOrSet($key, \Closure $set_Call, $ttl = 300,$key_ttl=0)
{
    $_key_lock = "try_" . $key . "_lock";; //锁的名称
    $random = rand(1, 99999);             // 随机值

    //Redis::connection()->client()->set('key', 'value', ['nx', 'ex' => 10]);

    $redis_client = Redis::connection()->client();


    $redis_ret = $redis_client->get($key);
    if (empty($redis_ret)) {
        // 开始 加锁
        //$rs = Redis::set($_key_lock, $random, array( 'nx', 'ex' => $ttl ));
        //$rs = Redis::command('set', [$_key_lock, $random, " NX EX ".$ttl ]);
        $rs = $redis_client->set($_key_lock, $random, ['nx', 'ex' => $ttl]);

        if ($rs) {
            // 执行设置缓存的代码
            $call = \Closure::bind($set_Call, null);
            $ret = $call();

            $redis_client->set($key, json_encode($ret));
            if($key_ttl > 0){
                $redis_client->expire($key, $key_ttl);
            }
            //先判断随机数，是同一个则删除锁 不一样表示锁过期了
            // 此时肯定有其他并发获取到了 锁
            if ($redis_client->get($_key_lock) == $random) {
                $redis_client->del($_key_lock);
            }
            return $ret;
        }else {
            // 获取不到锁直接获取缓存
            $redis_ret = $redis_client->get($key);
            $ret = json_decode($redis_ret, true);
            return $ret;
        }
    }else {
        // 直接后取到了缓存
        $ret = json_decode($redis_ret, true);
        return $ret;
    }

}

// function RedisTryLockGetOrSet($key, \Closure $set_Call, $ttl = 300)
// {
//     $_key_lock = "try_" . $key . "_lock";; //锁的名称
//     $random = rand(1, 99999);             // 随机值

//     //Redis::connection()->client()->set('key', 'value', ['nx', 'ex' => 10]);

//     $redis_client = Redis::connection()->client();


//     $redis_ret = $redis_client->get($key);
//     if (empty($redis_ret)) {
//         // 开始 加锁
//         //$rs = Redis::set($_key_lock, $random, array( 'nx', 'ex' => $ttl ));
//         //$rs = Redis::command('set', [$_key_lock, $random, " NX EX ".$ttl ]);
//         $rs = $redis_client->set($_key_lock, $random, ['nx', 'ex' => $ttl]);

//         if ($rs) {
//             // 执行设置缓存的代码
//             $call = \Closure::bind($set_Call, null);
//             $ret = $call();

//             $redis_client->set($key, json_encode($ret));
//             //先判断随机数，是同一个则删除锁 不一样表示锁过期了
//             // 此时肯定有其他并发获取到了 锁
//             if ($redis_client->get($_key_lock) == $random) {
//                 $redis_client->del($_key_lock);
//             }
//             return $ret;
//         }else {
//             // 获取不到锁直接获取缓存
//             $redis_ret = $redis_client->get($key);
//             $ret = json_decode($redis_ret, true);
//             return $ret;
//         }
//     }else {
//         // 直接后取到了缓存
//         $ret = json_decode($redis_ret, true);
//         return $ret;
//     }

// }


/**
 *   获取某一个 时间戳所在 周的全部周的时间
 * @param int $day_time
 * @return array
 */
function  GetWeekDayTimeSpanList( int $day_time)
{
    $day = $day_time;  // 时间戳
    $day_at_week = date("w", $day); // 获取 时间戳所在周几

    // 计算一下 当前周 的周一 和 周末  相差 几天
    $first_day = ($day_at_week == 0) ? 6 : $day_at_week - 1; // 和周一差几天
    $last_day = ($day_at_week == 0) ? 0 : 7 - $day_at_week;  // 和周天差几天

    // 计算周一的时间戳
    $day_at_week_first = strtotime("-$first_day day", $day);
    $day_at_week_last = strtotime("+$last_day day", $day);

    // echo "first_day:".date("Y-m-d",$day_at_week_first).PHP_EOL;

    // 直接计算 周一之后六天の 时间
    $ret_day_list = array();
    for ($day_add = 0; $day_add < 7; $day_add++) {
        $ret_day_list[] = strtotime("+$day_add day", $day_at_week_first);
        //echo "in day:" . date("Y-m-d", strtotime("+$day_add day", $day_at_week_first)) . PHP_EOL;
    }

    return $ret_day_list;
    // echo "day:".date("Y-m-d",$day).PHP_EOL;
    // echo "last_day:".date("Y-m-d",$day_at_week_last).PHP_EOL;
}

/**
 * @param int $day_time 所在月的 时间戳
 * @param false $over_half_month 是否添加超过半个月的时间
 * @return array
 */
function GetMonthDayTimeSpanList(int $day_time,$over_half_month = false ){
    $firstday = strtotime(date('Y-m-01', ($day_time)));
    $lastday =  strtotime(date('Y-m-d', strtotime("+1 month -1 day",$firstday)));

    // 前后添加 个半个月的时间
    if($over_half_month == true){
        $firstday = strtotime(" -15 day",$firstday);
        $lastday  = strtotime(" +15 day",$lastday);

    }

    $ret_month_arr = array();
    $current_time = $firstday;
    while( $current_time <= $lastday ){
        $ret_month_arr[] = $current_time;
        $current_time = strtotime("+1 day",$current_time);
    }

    return $ret_month_arr;
}

/**
 *  当 直报开始的时候 发消息给 后台异步任务
 * @param $room_id
 * @return
 */
function notifyLiveStarted($room_id)
{
    return Redis::lpush("live_info_change", json_encode([ "type" => "live_start", "live_info" => [ "room_id" => $room_id ] ]));
}

//


/**
 *  当直报间结束的时候进行直播间的 用户进入进出统计系统
 * @param $room_id
 * @return mixed
 */
function notifyLiveEnd($room_id)
{
    return Redis::zadd(CCRoomLiveAnalysisLiveCron::ANALYSIS_CC_ROOM, strtotime("now"), $room_id);
}



/**
 *  当直播的时间 改变的时候 发消息给 后台异步任务
 * @param $room_id
 * @return
 */
function notifyLiveTimeChanged($room_id,$start_time,$end_time)
{
    return Redis::lpush("live_info_change", json_encode( [
        "type"      => "time_change",
        "live_info" => [
            "room_id"    => $room_id,
            "start_time" => $start_time,
            "end_time"   => $end_time
        ] ] ));
}

/**
 *  时间戳 到 格式化的  年月日
 * @param $second
 * @return string
 */
function time2string($second)
{
    $day = floor($second / (3600 * 24));
    $second = $second % (3600 * 24);//除去整天之后剩余的时间
    $hour = floor($second / 3600);
    $second = $second % 3600;//除去整小时之后剩余的时间
    $minute = floor($second / 60);
    $second = $second % 60;//除去整分钟之后剩余的时间
    //返回字符串
    return $day . '天' . $hour . '小时' . $minute . '分' . $second . '秒';
}

function isHttps()
{
    if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
        return true;
    }
    if (isset($_SERVER['HTTP_ORIGIN']) && (strpos($_SERVER['HTTP_ORIGIN'], 'https://') === 0)) {
        return true;
    }
    if (isset($_SERVER['HTTP_REFERER']) && (strpos($_SERVER['HTTP_REFERER'], 'https://') === 0)) {
        return true;
    }
    return false;
}
//生成密码随机数
function get_password( $length = 3 )
{
    $str='ABCDEFGHJKMNOPQRSTUVWXYZabcdefghjkmnopqrstuvwxyz';
    $randStr = str_shuffle($str);//打乱字符串
    $rands= substr($randStr,0,$length);//substr(string,start,length);返回字符串的一部分
    return $rands;
}

function array_orderby()
{
    $args = func_get_args();
    $data = array_shift($args);
    foreach ($args as $n => $field) {
        if (is_string($field)) {
            $tmp = array();
            foreach ($data as $key => $row)
                $tmp[$key] = $row[$field];
            $args[$n] = $tmp;
        }
    }
    $args[] = &$data;
    call_user_func_array('array_multisort', $args);
    return array_pop($args);
}
//加密函数
function encrypt_sensitive($txt,$key='XxH'){
    $txt = $txt.$key;
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
    $nh = rand(0,64);
    $ch = $chars[$nh];
    $mdKey = md5($key.$ch);
    $mdKey = substr($mdKey,$nh%8, $nh%8+7);
    $txt = base64_encode($txt);
    $tmp = '';
    $i=0;$j=0;$k = 0;
    for ($i=0; $i<strlen($txt); $i++) {
        $k = $k == strlen($mdKey) ? 0 : $k;
        $j = ($nh+strpos($chars,$txt[$i])+ord($mdKey[$k++]))%64;
        $tmp .= $chars[$j];
    }
    return urlencode(base64_encode($ch.$tmp));
}
//解密函数
function decrypt_sensitive($txt,$key='XxH'){
    $txt = base64_decode(urldecode($txt));
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
    $ch = $txt[0];
    $nh = strpos($chars,$ch);
    $mdKey = md5($key.$ch);
    $mdKey = substr($mdKey,$nh%8, $nh%8+7);
    $txt = substr($txt,1);
    $tmp = '';
    $i=0;$j=0; $k = 0;
    for ($i=0; $i<strlen($txt); $i++) {
        $k = $k == strlen($mdKey) ? 0 : $k;
        $j = strpos($chars,$txt[$i])-$nh - ord($mdKey[$k++]);
        while ($j<0) $j+=64;
        $tmp .= $chars[$j];
    }
    return trim(base64_decode($tmp),$key);
}

function debug_to_sql($query){

    $bindings = $query->getBindings();

    $sql = str_replace('?', '%s', $query->toSql());

    $sql = sprintf($sql, ...$bindings);

    print_r($sql.PHP_EOL) ;
}
