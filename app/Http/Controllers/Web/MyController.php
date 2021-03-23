<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Coures;
use App\Models\Couresmethod;
use App\Models\CourseRefResource;
use App\Models\CourseSchool;
use App\Models\School;
use App\Models\Services;
use App\Models\SchoolConfig;
use App\Models\Teacher;
use App\Models\Articletype;
use App\Models\FootConfig;
use App\Models\Admin;
use App\Models\CouresSubject;
use App\Models\Article;
use App\Models\Order;
use App\Models\CourseRefTeacher;
use App\Models\WebLog;

class MyController extends Controller {
	protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['dns'],'is_del'=>1])->first(); //改前
		if(count($this->school)<=0){
		     return ['code' => 201 , 'msg' => '该网校不存在,请联系管理员！'];exit;
		}
		$this->userid = isset($this->data['user_info']['user_id'])?$this->data['user_info']['user_id']:0;
        //$this->school = $this->getWebSchoolInfo($this->data['dns']); //改后
    }
    //关于我们
    public function getAbout(){
        $school_id = 1;
        if(isset($this->data['user_info']['school_id'])  && isset($this->data['dns'])){
                $school_id = $this->data['user_info']['school_id'];
        }else{

            if(isset($this->data['user_info']['school_id']) && $this->data['user_info']['school_id']>0 ){
                $school_id = $this->data['user_info']['school_id'];
            }else{
                if(isset($this->data['dns'])&& !empty($this->data['dns'])){
                    $school = School::where(['dns'=>$this->data['dns'],'is_forbid'=>0])->first();
                    if($school){
                        $school_id = $school['id'];
                    }else{
                        $school_id = 1;
                    }
                }
            }

        }

        $aboutConfig = SchoolConfig::query()
            ->where('school_id', $school_id)
            ->value('about_config');
        // //添加日志操作
        // WebLog::insertWebLog([
        //     'admin_id'       =>  $this->userid  ,
        //     'module_name'    =>  'Index' ,
        //     'route_url'      =>  'app/index/getAbout' ,
        //     'operate_method' =>  'select' ,
        //     'content'        =>  '关于我们'.json_encode($aboutConfig) ,
        //     'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
        //     'create_at'      =>  date('Y-m-d H:i:s')
        // ]);
        if (empty($aboutConfig)) {
            $aboutConfig = '';
        }else{
            // //添加日志操作
            // WebLog::insertWebLog([
            //     'admin_id'       =>  $this->userid  ,
            //     'module_name'    =>  'Index' ,
            //     'route_url'      =>  'app/index/getAbout' ,
            //     'operate_method' =>  'select' ,
            //     'content'        =>  '关于我们'.json_encode($aboutConfig) ,
            //     'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
            //     'create_at'      =>  date('Y-m-d H:i:s')
            // ]);
        }
        return response()->json(['code'=>200,'msg'=>'Success','data'=> ['data' => $aboutConfig]]);
    }
    //联系客服
    public function getContact(){
        $school_id = $this->school['id'];
        $list = Services::where(['school_id'=>$school_id,'type'=>5,'status'=>1])->first();
        $str = '';
        if(!empty($list)){
            $number='';
            if(!empty($list['key'])){
                $arr = explode(',',$list['key']);
                foreach ($arr as $k=>$v){
                    $number = $number . "<p style='margin-left: 0.64rem'>".$v."</p>";
                }
            }
            $str = "<p>服务时间：". $list['sing']."</p><div><p style='position: relative;top: 0.18rem;'>电话号码：".$number."</p></div>";
        }
        // //添加日志操作
        // WebLog::insertWebLog([
        //     'admin_id'       =>  $this->userid  ,
        //     'module_name'    =>  'Index' ,
        //     'route_url'      =>  'web/index/getContact' ,
        //     'operate_method' =>  'select' ,
        //     'content'        =>  '联系客服'.json_encode($str) ,
        //     'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
        //     'create_at'      =>  date('Y-m-d H:i:s')
        // ]);
        return response()->json(['code'=>200,'msg'=>'success','data'=>['data'=>$str]]);
    }
}
