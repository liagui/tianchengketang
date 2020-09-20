<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Coures;
use App\Models\Couresmethod;
use App\Models\CourseRefResource;
use App\Models\CourseSchool;
use App\Models\School;
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
        // $this->school = School::where(['dns'=>$this->data['dns']])->first();
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
        $aboutArr = FootConfig::where(['school_id'=>$school_id,'is_del'=>0,'is_open'=>0,'type'=>5,'name'=>'关于我们'])->select('text')->first();
    	$about = isset($aboutArr['text']) ?$aboutArr['text'] :'';
    	return response()->json(['code'=>200,'msg'=>'success','data'=>$about]);
    }
    //联系客服
    public function getContact(){
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
        $contactArr = FootConfig::where(['school_id'=>$school_id,'is_del'=>0,'is_open'=>0,'type'=>5,'name'=>'联系客服'])->select('text')->first();
        $contact = isset($contactArr['text']) ?$contactArr['text'] :'';
        return response()->json(['code'=>200,'msg'=>'success','data'=>$contact]);
    }
}