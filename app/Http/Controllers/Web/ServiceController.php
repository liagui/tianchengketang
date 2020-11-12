<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Models\School;
use App\Models\Services;

class ServiceController extends Controller {
    protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['school_dns']])->first();
    }
    /*
         * @param  客服营销
         * @param  author  苏振文
         * @param  ctime   2020/11/12 10:20
         * return  array
         */
    public function servicelist(){
        $returnarr = Services::where(['school_id'=>$this->school['id'],'parent_id'=>0])->first();
        if(!empty($returnarr)){
           $count = Services::where(['school_id'=>$this->school['id'],'parent_id'=>$returnarr['id'],'status'=>1])->count();
           if($count > 0){
                $qq = !empty(Services::where(['school_id'=>$this->school['id'],'bigtype'=>1,'parent_id'=>$returnarr['id'],'status'=>1])->first())?Services::where(['school_id'=>$this->school['id'],'bigtype'=>1,'parent_id'=>$returnarr['id'],'status'=>1])->first():[];
                if(!empty($qq)){
                    $returnarr['qq'] = $qq;
                }
                $wx = Services::where(['school_id'=>$this->school['id'],'parent_id'=>$returnarr['id'],'status'=>1,'type'=>3])->first();
                if(!empty($wx)){
                    $returnarr['wx'] = $wx;
                }
                $wb = Services::where(['school_id'=>$this->school['id'],'parent_id'=>$returnarr['id'],'status'=>1,'type'=>4])->first();
                if(!empty($wx)){
                    $returnarr['wb'] = $wb;
                }
                $kf = Services::where(['school_id'=>$this->school['id'],'parent_id'=>$returnarr['id'],'status'=>1,'type'=>5])->first();
                if(!empty($wx)){
                    $returnarr['kf'] = $kf;
                }
            }else {
               $returnarr=[];
           }
        }else{
            $returnarr=[];
        }
        return response()->json(['code' => 200, 'msg' => '获取成功',$returnarr]);
    }
    /*
         * @param  第三方插件
         * @param  type 1统计分析2在线客服
         * @param  author  苏振文
         * @param  ctime   2020/11/11 20:23
         * return  array
         */
    public function plugin(){
        if(!isset($this->data['type']) || empty($this->data['type'])){
            return response()->json(['code' => 202, 'msg' => '请传类型']);
        }
        if($this->data['type'] == 1){
            $return=[
                'baidu'=>'',
                'guge'=>'',
                'tengx'=>'',
                'cnzz'=>'',
            ];
            //先查询父级
            $res = Plugin::where(['school_id'=>$this->school['id'],'on_type'=>1,'status'=>1])->first();
            if($res){
                for($i=1; $i<=4 ;$i++){
                    $key = Plugin::where(['parent_id'=>$res['id'],'type'=>$i])->first();
                    if(!empty($key)){
                        if($i == 1){
                            $return['baidu'] = $key['key'];
                        }if($i == 2){
                            $return['guge'] = $key['key'];
                        }if($i == 3){
                            $return['tengx'] = $key['key'];
                        }if($i == 4){
                            $return['cnzz'] = $key['key'];
                        }
                    }
                }
            }
            return response()->json(['code' => 200, 'msg' => '获取成功','data'=>$return]);
        }else{
            $return=[
                'baidu'=>'',
            ];
            //先查询父级
            $res = Plugin::where(['school_id'=>$this->school['id'],'on_type'=>2,'status'=>1])->first();
            $key = Plugin::where(['parent_id'=>$res['id']])->first();
            if(!empty($return)){
                $return['baidu'] =$key['key'];
            }
            return response()->json(['code' => 200, 'msg' => '获取成功','data'=>$return]);
        }
    }

}