<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
#use App\Models\User;
#use App\Models\Auth;


#use DB;
#use Illuminate\Support\Facades\Redis;
use App\Models\Demo_User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Code;

class DemoController extends Controller {

    public function demo(Request $request){
        $id = $request->id;
        $data = Demo_User::getInfoById($id);

        if(empty($data)){
            return responseJson(0);
        }else{
            return responseJson(200,$data);
        }

    }

    /**
     * @Notes:添加数据
     * @Author: liyinsheng
     * @Date: 2020/4/14
     * @Time: 18:46
     * @Interface insert
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
   public  function insert(){
        $arr=['188.88','168.88','16.88','48.88','1','2','3'];
        $count = count($arr);
        $i=0;
        if( $count>0){
        while ($i<=9) {
            $i++;
            $num = array_rand($arr);
            echo $arr[$num].'<br/>';
            unset($arr[$num]);
            if(empty($arr)){
                echo '活动结束<br/>';die;
            }
            $newarr = $arr;
            $arr = $newarr;
            print_r($newarr);
        }
       }
}
public function demos(){
    $arr=['188.88','168.88','16.88','48.88','1','2','3'];
}






}

