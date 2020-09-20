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

class NewController extends Controller {
	protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        // $this->school = School::where(['dns'=>$this->data['dns']])->first();
        // $this->school = School::where(['dns'=>$_SERVER['SERVER_NAME']])->first();
    }
    //列表
    public function getList(){
    	$articleArr = [];
        $data = $this->data;
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
    	$pagesize = !isset($data['pagesize']) || $data['pagesize']  <= 0 ? 8:$data['pagesize'];   
    	$page = !isset($data['page']) || $data['page'] <= 0 ?1 :$data['page'];
    	$offset   = ($page - 1) * $pagesize;
    	$count = Article::leftJoin('ld_article_type','ld_article.article_type_id','=','ld_article_type.id')
                        ->where(['ld_article_type.school_id'=>$school_id,'ld_article_type.status'=>1,'ld_article_type.is_del'=>1,'ld_article.status'=>1,'ld_article.is_del'=>1])
    					->where(function($query) use ($data) {
                            if(!empty($data['articleOne']) && $data['articleOne'] != ''){
                                $query->where('article_type_id',$data['articleOne']);
                            }
                        })->orderBy('ld_article.create_at','desc')
    				->select('ld_article.id')
    				->count();
    	$Articletype = Articletype::where(['school_id'=>$school_id,'status'=>1,'is_del'=>1])->select('id','typename')->get();
    	if($count >0){
    		$where = ['ld_article_type.school_id'=>$school_id,'ld_article_type.status'=>1,'ld_article_type.is_del'=>1,'ld_article.status'=>1,'ld_article.is_del'=>1];
    		$articleArr = Article::leftJoin('ld_article_type','ld_article.article_type_id','=','ld_article_type.id')
    					->where($where)
    					->where(function($query) use ($data) {
                            if(!empty($data['articleOne']) && $data['articleOne'] != ''){
                                $query->where('article_type_id',$data['articleOne']);
                            }
                        })->orderBy('ld_article.create_at','desc')
    				->select('ld_article.id','ld_article.article_type_id','ld_article.title','ld_article.share','ld_article.create_at','ld_article.image','ld_article_type.typename')
                    ->offset($offset)->limit($pagesize)
    				->get();
    	}
        if(!empty($articleArr)){
            foreach ($articleArr as $k => &$new) {
                if($new['share'] == null || $new['share'] == 'null'){
                    $new['share'] = 0;
                }
                if($new['watch_num'] == null || $new['watch_num'] == 'null'){
                    $new['watch_num'] = 0;
                }
                $new['share'] = $new['share'] + $new['watch_num'];
            }
        }
    	return  ['code'=>200,'msg'=>'Success','data'=>$articleArr,'total'=>$count,'article_type'=>$Articletype];
    }
}