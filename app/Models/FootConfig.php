<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use Validator;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionSubject as Subject;
use Illuminate\Support\Facades\Redis;

class FootConfig extends Model {
    //指定别的表名
    public $table      = 'ld_footer_config';
    //时间戳设置
    public $timestamps = false;

      //错误信息
    public static function message(){
        return [
            'id.required'  => json_encode(['code'=>'201','msg'=>'id不能为空']),
            'id.integer'   => json_encode(['code'=>'202','msg'=>'id类型不合法']),
            'type.required' => json_encode(['code'=>'201','msg'=>'类型不能为空']),
            'type.integer'  => json_encode(['code'=>'202','msg'=>'类型类型不合法']),
            'school_id.required' => json_encode(['code'=>'201','msg'=>'学校标识不能为空']),
            'logo.required'  => json_encode(['code'=>'201','msg'=>'logo标识不合法']),
        ];
    }
    public static function getList($body){
    	$schoolid = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登录的id
	    $school_name='';
    	$school_id = isset($body['school_id']) && $body['school_id'] > 0 ? $body['school_id'] : $schoolid; //搜索条件
    	if($school_id>0){
    		$school=School::where(['id'=>$school_id,'is_del'=>1])->select('name')->first();
    		$school_name = $school['name'];
    	}
    	$pageSet = self::where(['is_del'=>0])
    		->where(function($query) use ($body,$school_id){
    				$query->where('school_id',$school_id);
    	})->select('id','parent_id','school_id','logo','name','url','text','type','sort','status','is_show','is_open')->get()->toArray(); //

    	$headerArr = $footer = $icp= $logo = $about = [];
    	if(!empty($pageSet)){
    		foreach($pageSet  as $key=>&$v){
    			if($v['type']== 1){
                    if($v['status'] != 1){
                        $schoolData = School::where('id',$school_id)->select('title','subhead')->first();
                        $v['title'] = isset($schoolData['title']) ? $schoolData['title'] :'';
                        $v['subhead'] = isset($schoolData['subhead']) ? $schoolData['subhead'] :'';
                    }
    				array_push($headerArr,$v);
    			}
    			if($v['type']== 2){
    				array_push($footer,$v);
    			}
    			if($v['type']== 3){
    				array_push($icp,$v);
    			}
                if($v['type']== 4){
                    array_push($logo,$v);
                }
                if($v['type']== 5){
                    array_push($about,$v);
                }
    		}
    		if(!empty($footer)){
    			$footer =getParentsList($footer);
    		}
    	}
    	return ['code'=>200,'msg'=>'Success','data'=>['header'=>$headerArr,'footer'=>$footer,'icp'=>$icp,'school_name'=>$school_name,'logo'=>$logo,'about'=>$about]];
    }
    public static function details($body){
        $schoolid = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登录的id
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0; //当前登录的用户id
        $school_update = [];
    	$body['open'] = isset($body['open']) && $body['open'] > 0 ?1:0;
    	if($body['type'] == 1){ //头部
    		if(!isset($body['name']) || empty($body['name'])){
    			return ['code'=>201,'msg'=>'header_name为空'];
    		}
    		if(!isset($body['url']) || empty($body['url'])){
    			return ['code'=>201,'msg'=>'header_url为空'];
    		}
            if(!isset($body['text']) || empty($body['text']) ){
                $update['text'] =  '';
            }else{
                $update['text'] = $body['text'];
            }
            if(isset($body['title']) && !empty($body['title'])){
                $school_update['title'] = $body['title'];
            }
            if(isset($body['subhead']) && !empty($body['subhead'])){
                $school_update['subhead'] = $body['subhead'];
            }
            if(!empty($school_update) && $schoolid >0){
                $schoolData = self::where(['id'=>$body['id'],'is_del'=>0])->select('school_id')->first();
                if(isset($schoolData['school_id']) && $schoolData['school_id'] >0){
                    $schoolid = $schoolData['school_id'];
                }
                $school_update['update_time'] = date('Y-m-d H:i:s');
                $schoolRes = school::where('id',$schoolid)->update($school_update);
                if(!$schoolRes){
                    return ['code'=>203,'msg'=>'网络错误，请重试'];
                }
            }
    		$res = self::where(['id'=>$body['id'],'type'=>$body['type']])->update(['name'=>$body['name'],'url'=>$body['url'],'is_open'=>$body['open'],'update_at'=>date('Y-m-d H:i:s'),'text'=>$update['text']]);
    	}
    	if($body['type'] == 2){ //尾部
    		if(!isset($body['name']) || empty($body['name'])){
    			return ['code'=>201,'msg'=>'foot_name为空'];
    		}
    		if(!isset($body['url']) || empty($body['url'])){
    			return ['code'=>201,'msg'=>'foot_url为空'];
    		}
    		if(!isset($body['text']) || empty($body['text']) ){
                $update['text'] =  '';
            }else{
                $update['text'] = $body['text'];
            }
            $update['name'] = $body['name'];
    		$update['url'] = $body['url'];
    		$update['is_open'] = $body['open'];
    		$update['update_at'] = date('Y-m-d H:i:s');
    		$res = self::where(['id'=>$body['id'],'type'=>$body['type']])->update($update);
    	}
    	if($body['type'] == 3){ //icp
    		if(!isset($body['name']) || empty($body['name'])){
    			return ['code'=>201,'msg'=>'icp为不能空'];
    		}
    		$res = self::where(['id'=>$body['id'],'type'=>$body['type']])->update(['name'=>$body['name'],'is_open'=>$body['open'],'update_at'=>date('Y-m-d H:i:s')]);
    	}
        if($body['type'] == 5){ //APP/H5  关于我们
            if(!isset($body['text']) || empty($body['text'])){
                return ['code'=>201,'msg'=>'关于我们内容为空'];
            }
            $res = self::where(['id'=>$body['id'],'type'=>$body['type']])->update(['text'=>$body['text'],'is_open'=>0,'update_at'=>date('Y-m-d H:i:s')]);
        }
    	if($res){
            AdminLog::insertAdminLog([
                    'admin_id'       =>   $user_id ,
                    'module_name'    =>  'Pageset' ,
                    'route_url'      =>  'admin/pageset/details' ,
                    'operate_method' =>  'update',
                    'content'        =>  json_encode($body),
                    'ip'             =>  $_SERVER['REMOTE_ADDR'] ,
                    'create_at'      =>  date('Y-m-d H:i:s')
            ]);
    		return ['code'=>200,'msg'=>'Success'];
    	}else{
    		return ['code'=>203,'msg'=>'网络错误，请重试'];
    	}
    }


    public static function doLogoUpdate($body){
        $user_id = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0; //当前登录的用户id
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0; //当前登录的id
        $body['school_id'] = isset($body['school_id']) && $body['school_id'] > 0 ?$body['school_id']:$school_id;
        $Logo = self::where(['school_id'=>$body['school_id'],'type'=>4,'is_del'=>0])->first();
        if(empty($Logo)){
            return ['code'=>203,'msg'=>'数据不存在！'];
        }
        $update['update_at'] = date('Y-m-d H:i:s');
        $update['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->cur_admin_id) ? AdminLog::getAdminInfo()->admin_user->cur_admin_id : 0;
        $update['logo']  = $body['logo'];
        $res = self::where(['school_id'=>$body['school_id'],'type'=>4,'is_del'=>0])->update($update);
        if($res){
            AdminLog::insertAdminLog([
                    'admin_id'       =>   $user_id ,
                    'module_name'    =>  'Pageset' ,
                    'route_url'      =>  'admin/pageset/doLogoUpdate' ,
                    'operate_method' =>  'update',
                    'content'        =>  json_encode(array_merge($body,$update)),
                    'ip'             =>  $_SERVER['REMOTE_ADDR'],
                    'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            return ['code'=>200,'msg'=>'更改成功'];
        }else{
            return ['code'=>203,'msg'=>'网络错误，请重试'];
        }
    }


}
