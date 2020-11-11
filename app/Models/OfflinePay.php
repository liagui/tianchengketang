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
class  OfflinePay extends Model {
    //指定别的表名
    public $table = 'offline_pay';
    //时间戳设置
    public $timestamps = false;

    public static function message()
    {
        return [
            'id.required'  => json_encode(['code'=>'201','msg'=>'通道标识不能为空']),
            'id.integer'   => json_encode(['code'=>'202','msg'=>'通道标识类型不合法']),
            'account_name.required' => json_encode(['code'=>'201','msg'=>'账户名称不能为空']),
            'is_show.required'  => json_encode(['code'=>'201','msg'=>'是否展示不能为空']),
            'is_show.integer'   => json_encode(['code'=>'202','msg'=>'是否展示类型不合法']),
            'type.required'  => json_encode(['code'=>'201','msg'=>'类型不能为空']),
            'type.integer'   => json_encode(['code'=>'202','msg'=>'类型不合法']),
        ];
    }
    //获取列表
    public static function getList($body){
        $oneArr = $twoArr = $thereArr = [];
        $arr = self::where('is_del',1)->select('id','account_name','type','is_show')->get()->toArray();
        if(!empty($arr)){

            foreach($arr as $k=>$v){
                switch ($v['type']) {
                    case '1':array_push($oneArr,$v);break;
                    case '2':array_push($twoArr,$v);break;
                    case '3':array_push($thereArr,$v);break;
                }
            }
        }
        return ['code'=>200,'msg'=>'success','data'=>['corporate'=>$oneArr,'bank'=>$twoArr,'zfb'=>$thereArr]];
    }
    //添加线下支付
    public static function doInsertPay($body){
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        if(!isset($body['type'])|| empty($body['type']) || $body['type'] <=0){
            return ['code'=>201,'msg'=>'类型为空或不合法'];
        }
        if(!isset($body['account_name'])|| empty($body['account_name'])){
            return ['code'=>201,'msg'=>'账户名称为空或不合法'];
        }
        if(!isset($body['is_show']) || strlen($body['is_show'])<=0){
            return ['code'=>201,'msg'=>'是否展示为空或不合法'];
        }
        $count  = self::where(['is_del'=>1,'type'=>$body['type'],'account_name'=>$body['account_name']])->count();
        if($count>=1){
            return ['code'=>201,'msg'=>'账户信息已存在！'];
        }else{
            DB::beginTransaction();
            try {
                $insert = [
                    'create_time'=>date('Y-m-d H:i:s'),
                    'type'=>$body['type'],
                    'account_name'=>$body['account_name'],
                    'create_id' =>$admin_id,
                    'is_show'=>$body['is_show']
                ];
                $offlinePayid = self::insertGetId($insert);
                if($offlinePayid<=0){
                    DB::rollBack();
                    return ['code'=>203,'msg'=>'添加失败'];
                }else{
                    DB::commit();
                    return ['code'=>200,'msg'=>'添加成功'];
                }

            } catch (\Exception $ex) {
                DB::rollBack();
                return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
            }
        }
    }
    //获取线下支付
    public static function getOfflinePayById($body){
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        if(!isset($body['id'])|| empty($body['id']) || $body['id'] <=0){
            return ['code'=>201,'msg'=>'类型为空或不合法'];
        }
        $offlinePay = self::where(['id'=>$body['id'],'is_del'=>1])->select('id','is_show','account_name')->first();

        return ['code'=>200,'msg'=>'success','data'=>$offlinePay];
    }
     //获取线下支付
    public static function doUpdateOfflinePay($body){

        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        if(!isset($body['id'])|| empty($body['id']) || $body['id'] <=0){
            return ['code'=>201,'msg'=>'标识为空或不合法'];
        }
        if(!isset($body['account_name'])|| empty($body['account_name'])){
            return ['code'=>201,'msg'=>'账户名称为空或不合法'];
        }
        if(!isset($body['is_show'])|| strlen($body['is_show'])<=0){
            return ['code'=>201,'msg'=>'是否展示不合法'];
        }
        if(!isset($body['type'])|| strlen($body['type'])<=0){
            return ['code'=>201,'msg'=>'类型不合法'];
        }
        $count  = self::where(['is_del'=>1,'id'=>$body['id']])->count();
        if($count<=0){
            return ['code'=>201,'msg'=>'账户信息不存在或已删除'];
        }else{
            DB::beginTransaction();
            try {
                if(isset($body['is_del'])){
                    $delRes = self::where(['id'=>$body['id']])->update(['is_del'=>0,'update_time'=>date('Y-m-d')]); //删除操作
                    if(!$delRes){
                        DB::rollBack();
                        return ['code'=>203,'msg'=>'编辑失败！'];
                    }else{
                        DB::commit();
                        return ['code'=>200,'msg'=>'编辑成功！'];
                    }
                }else{
                    $count  = self::where(['is_del'=>0,'type'=>$body['type'],'account_name'=>$body['account_name']])->where('id','!=',$body['id'])->count();
                    if($count>=1){
                        return ['code'=>201,'msg'=>'账户信息已存在！'];
                    }else{
                        $offlinePayRes = self::where(['id'=>$body['id']])->update(['account_name'=>$body['account_name'],'is_show'=>$body['is_show'],'update_time'=>date('Y-m-d')]); //编辑操作
                        if(!$offlinePayRes){
                            DB::rollBack();
                            return ['code'=>203,'msg'=>'编辑失败'];
                        }else{
                            DB::commit();
                            return ['code'=>200,'msg'=>'编辑成功'];
                        }
                    }
                }

            } catch (\Exception $ex) {
                DB::rollBack();
                return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];
            }
        }
    }
}
