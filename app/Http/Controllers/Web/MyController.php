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
class MyController extends Controller {
	protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['dns']])->first();
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

        if (empty($aboutConfig)) {
            $aboutConfig = '';
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
        return response()->json(['code'=>200,'msg'=>'success','data'=>['data'=>$str]]);
    }
}
