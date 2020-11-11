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
class  Channel extends Model {
    //指定别的表名
    public $table = 'channel';
    //时间戳设置
    public $timestamps = false;

    public static function message()
    {
        return [
            'id.required'  => json_encode(['code'=>'201','msg'=>'通道标识不能为空']),
            'id.integer'   => json_encode(['code'=>'202','msg'=>'通道标识类型不合法']),
            'channel_name.required' => json_encode(['code'=>'201','msg'=>'通道名称不能为空']),
            'channel_type.required'  => json_encode(['code'=>'201','msg'=>'通道类型不能为空']),
        ];
    }




    //获取支付通过列表 （lys）2020-09-03
    public static function getList($body){

        $channelArr = [];
    	$school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $school_status = isset(AdminLog::getAdminInfo()->admin_user->school_status) ? AdminLog::getAdminInfo()->admin_user->school_status : 0;
        $count =  self::where(['is_del'=>0,'is_forbid'=>0])->count();
        if($count>0){
            $use_channel = self::where(['is_del'=>0,'is_forbid'=>0,'is_use'=>0])->first()['id'];
			$channelArr = self::leftJoin('pay_config','pay_config.channel_id','=','channel.id')
                  ->where(function($query) use ($body){
                          $query->where('channel.is_forbid',0);
                          $query->where('channel.is_del',0);
                      })
                  ->select('pay_config.id','pay_config.channel_id','pay_config.wx_pay_state','pay_config.zfb_pay_state','pay_config.hj_wx_pay_state','pay_config.hj_zfb_pay_state','channel.channel_type','channel_name','channel.is_use')
                  ->get();
        	foreach($channelArr as $key =>&$v){
        		$channel_type = explode(',',$v['channel_type']);
                if(in_array(1, $channel_type)){
                      $v['zfb_show'] = true;
                }else{
                    $v['zfb_show'] = false;
                }
                if(in_array(2, $channel_type)){
                      $v['wx_show'] = true;
                }else{
                    $v['wx_show'] = false;
                }
                if(in_array(3, $channel_type)){
                      $v['hj_show'] = true;
                }else{
                    $v['hj_show'] = false;
                }
              	if($v['hj_wx_pay_state'] <1 && $v['hj_zfb_pay_state']<1){
              	    $v['hj_state'] = 0;  //关闭
              	}else{
              	    $v['hj_state'] = 1;  //开启
              	}
          	}
        }else{
            $use_channel = -1;
        }

        return ['code'=>200,'msg'=>'Success','data'=>$channelArr,'use_channel'=>$use_channel];
    }

    //添加支付通道(lys) 2020-09-03
    public static function doChannelInsert($body){
    	$create_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
    	$create_name = isset(AdminLog::getAdminInfo()->admin_user->real_name) ? AdminLog::getAdminInfo()->admin_user->real_name : '';
    	$count = self::where('channel_name','=',$body['channel_name'])->where(['is_forbid'=>0,'is_del'=>0])->count();
    	if($count<=0){
            $insert['channel_name'] = $body['channel_name'];
    	    $insert['channel_type'] = $body['channel_type'];
    		$insert['create_time'] = date('Y-m-d H:i:s');
    		$insert['create_id'] = $create_id;
            DB::beginTransaction();
            try {
                $channelId = self::insertGetId($insert);
                if($channelId <=0){
                    DB::rollBack();
                    return ['code'=>205,'msg'=>'支付通过添加未成功！'];
                }else{
                    $paysetId = PaySet::insertGetId(['create_id'=>$create_id,'create_at'=>date('Y-m-d H:i:s'),'channel_id'=>$channelId]);
                    if($paysetId >0){
                        DB::commit();
                        return ['code'=>200,'msg'=>'支付通过添加成功'];
                    }else{
                        DB::rollBack();
                        return ['code'=>205,'msg'=>'支付通过添加未成功！！'];
                    }
                }

            } catch (\Exception $ex) {
                DB::rollBack();
                return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];

            }
    	}else{
    		return ['code'=>201,'msg'=>'支付通道已存在！'];
    	}
    }
 	//编辑支付通道【获取】(lys) 2020-09-03
    public static function getChannelPayById($body){
    	if(!isset($body['id']) || empty($body['id'])){
            return response()->json(['code'=>201,'msg'=>'id缺少或为空']);
        }
        $channelData = self::where(['id'=>$body['id'],'is_forbid'=>0,'is_del'=>0])->select('id','channel_type','channel_name')->first();
    	return ['code'=>200,'msg'=>'success','data'=>$channelData];
    }
    //编辑支付通道(lys) 2020-09-03
    public static function doUpdateChannelPay($body){
    	if(!isset($body['id']) || empty($body['id'])){
            return response()->json(['code'=>201,'msg'=>'id缺少或为空']);
        }
        if(!isset($body['channel_name']) || empty($body['channel_name'])){
            return response()->json(['code'=>201,'msg'=>'channel_name缺少或为空']);
        }
        if(!isset($body['channel_type']) || empty($body['channel_type'])){
            return response()->json(['code'=>201,'msg'=>'channel_type缺少或为空']);
        }
        $body['update_time'] = date('Y-m-d H:i:s');
       	unset($body['/admin/channel/doUpdateChannelPay']);
        $res = self::where(['id'=>$body['id']])->update($body);
        if($res){
        	return ['code'=>200,'msg'=>'通道更改成功'];
        }else{
        	return ['code'=>203,'msg'=>'通道更改失败'];
        }
    }



}
