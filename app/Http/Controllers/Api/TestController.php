<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Providers\Rsa\RsaFactory;

class TestController extends Controller {
    //列表
    public function TeacherList(){
        $schoolid = isset($_POST['school_id'])?$_POST['school_id']:'';
        $teachername = isset($_POST['teachername'])?$_POST['teachername']:'';
        $teacherphone = isset($_POST['teacherphone'])?$_POST['teacherphone']:'';
        $date = isset($_POST['date'])?$_POST['date']:'';
        $list = Teacher::AllList($schoolid,$teachername,$teacherphone,$date);
        return $list;
    }
    //增加
    public function TeacherAdd(){
        $add = Teacher::AddTeacher();
        if($add){

        }
    }
    //解密
    public function rsami(){
        $a = "aaaaaaaaaaaaaa";
        echo $a;die;
    }
    //jia
    public function rsaadd(){
//        echo "9999";die;
        $rsa =  new RsaFactory();
        $a = $rsa->Test();
        print_r($a);die;
    }
}
