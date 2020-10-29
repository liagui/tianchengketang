<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AdminLog;
use App\Models\PapersExam;
use App\Models\Bank;
use App\Models\QuestionSubject;
use App\Models\School;
use Validator;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class PaySet extends Model {
    //指定别的表名
    public $table = 'ld_pay_config';
    //时间戳设置
    public $timestamps = false;


    public static function message()
    {
        return [
            'id.required'  => json_encode(['code'=>'201','msg'=>'列表标识不能为空']),
            'id.integer'   => json_encode(['code'=>'202','msg'=>'列表标识类型不合法']),
            'app_id.required' => json_encode(['code'=>'201','msg'=>'app_id不能为空']),
            'app_public_key.required'  => json_encode(['code'=>'201','msg'=>'应用公钥类型不能为空']),
            'zfb_public_key.required'  => json_encode(['code'=>'201','msg'=>'公钥类型不能为空']),
            'shop_number.required'  => json_encode(['code'=>'201','msg'=>'商户号不能为空']),
            'api_key.required'  => json_encode(['code'=>'201','msg'=>'密钥不能为空']),
            'md_key.required'  => json_encode(['code'=>'201','msg'=>'md5密钥不能为空']),
            'wx_deal_shop_number.required'  => json_encode(['code'=>'201','msg'=>'微信交易商户号不能为空']),
            'wx_state.required'  => json_encode(['code'=>'201','msg'=>'微信支付状态不能为空']),
            'wx_state.integer'  => json_encode(['code'=>'202','msg'=>'微信支付状态不合法']),
            'zfb_state.required'  => json_encode(['code'=>'201','msg'=>'支付宝支付状态不能为空']),
            'zfb_state.integer'  => json_encode(['code'=>'202','msg'=>'支付宝支付状态不合法']),
            'zfb_deal_shop_number.required'  => json_encode(['code'=>'201','msg'=>'支付宝交易商户号不能为空']),
        ];
    }
			 /*
         * @param  获取分类列表
         * @param  search     搜索条件
         * @param  page       当前页
         * @param  pagesize   每页显示条数
         * @param  author  lys
         * @param  ctime   2020/5/26
         * return  array
         */
    public static function getList($body=[]){

        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        $pagesize = (int)isset($body['pagesize']) && $body['pagesize'] > 0 ? $body['pagesize'] : 20;
        $page     = isset($body['page']) && $body['page'] > 0 ? $body['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        if($school_status != 1){
          //分校
          $body['search'] = isset($body['search']) && !empty($body['search']) ? $body['search'] : '';  //搜索条件
          $count =  School::where(['id'=>$school_id,'is_del'=>1])->where('name','like',"%".$body['search']."%")->count();
          $sum_page = ceil($count/$pagesize);
          if($count>0){
            $payArr = self::select('ld_school.id as school_id','ld_school.name','ld_school.is_forbid','ld_pay_config.id','ld_pay_config.wx_pay_state','ld_pay_config.zfb_pay_state','ld_pay_config.wx_pay_state','ld_pay_config.hj_wx_pay_state','ld_pay_config.hj_zfb_pay_state','ld_pay_config.yl_pay_state','ld_pay_config.hf_pay_state')
                  ->rightJoin('ld_school','ld_school.id','=','ld_pay_config.school_id')
                  ->where(function($query) use ($body , $school_id){
                    if(!empty($body['search'])){
                            $query->where('ld_school.name','like',"%".$body['search']."%");
                        }
                          $query->where('ld_school.id',$school_id);
                          $query->where('ld_school.is_del',1);
                      })->offset($offset)->limit($pagesize)->get();
              foreach($payArr as $key =>$v){
                  if($v['hj_wx_pay_state'] <1 && $v['hj_zfb_pay_state']<1){
                      $payArr[$key]['hj_state'] = -1;  //关闭
                  }else{
                      $payArr[$key]['hj_state'] = 1;  //开启
                  } 
              }
              $arr['code'] = 200;
              $arr['msg'] = "success";
              $arr['data']= [
                  "list"=>$payArr,
                  'page'=>$page,
                  'pagesize'=>$pagesize,
                  'search'=>$body['search'],
                  'sum_page'=>$sum_page,
                  'total'=>$count
                ];
              return $arr;
          }
        }else{

          //总校
          $body['search'] = isset($body['search']) && !empty($body['search']) ? $body['search'] : '';  //搜索条件
          $count =  School::where(['is_del'=>1])->where('name','like',"%".$body['search']."%")->count();

          $sum_page = ceil($count/$pagesize);
          if($count>0){
            $payArr = self::select('ld_school.id as school_id','ld_school.name','ld_school.is_forbid','ld_pay_config.id','ld_pay_config.wx_pay_state','ld_pay_config.zfb_pay_state','ld_pay_config.wx_pay_state','ld_pay_config.hj_wx_pay_state','ld_pay_config.hj_zfb_pay_state','ld_pay_config.yl_pay_state','ld_pay_config.hf_pay_state')
                  ->rightJoin('ld_school','ld_school.id','=','ld_pay_config.school_id')
                  ->where(function($query) use ($body){
                    if(!empty($body['search'])){
                            $query->where('ld_school.name','like',"%".$body['search']."%");
                        }
                          $query->where('ld_school.is_del',1);
                      })->offset($offset)->limit($pagesize)->get();
              foreach($payArr as $key =>$v){
                  if($v['hj_wx_pay_state'] <1 && $v['hj_zfb_pay_state']<1){
                      $payArr[$key]['hj_state'] = -1;  //关闭
                  }else{
                      $payArr[$key]['hj_state'] = 1;  //开启
                  } 
              }

              $arr['code'] = 200;
              $arr['msg'] = "success";
              $arr['data']= [
                  "list"=>$payArr,
                  'page'=>$page,
                  'pagesize'=>$pagesize,
                  'search'=>$body['search'],
                  'sum_page'=>$sum_page,
                  'total'=>$count
                ];
              return $arr;
          }
        }
      
         	$arr['code'] = 200;
         	$arr['msg'] = "success";
         	$arr['data']= [
         		"list"=>[],
         		'page'=>$page,
         		'pagesize'=>$pagesize,
         		'search'=>$body['search'],
         		'sum_page'=>1,
  		      'total'=>0
         	];
       return $arr;

    }

    public static function doUpdate($where,$update){
    	return self::where($where)->update($update);
    }

    public static function findOne($where=[]){
   		return self::where($where)->first();
    }






}