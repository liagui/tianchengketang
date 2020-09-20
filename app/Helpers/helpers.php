<?php

use Illuminate\Support\Facades\Config;

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

?>
