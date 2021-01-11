<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Converge;
use App\Models\Coures;
use App\Models\Couresteacher;
use App\Models\CourseSchool;
use App\Models\Order;
use App\Models\PaySet;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Tools\AlipayFactory;
use App\Tools\QRcode;
use App\Tools\WxpayFactory;
//use Endroid\QrCode\QrCode;
use App\Tools\Yl\YinpayFactory;
use App\Tools\Hfpos\qrcp_E1103;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use function Composer\Autoload\includeFile;

class OrderController extends Controller {
    protected $school;
    protected $data;
    protected $userid;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['school_dns']])->first();//改前
        //$this->school = $this->getWebSchoolInfo($this->data['school_dns']); //改后
        $this->userid = isset($this->data['user_info']['user_id'])?$this->data['user_info']['user_id']:0;
    }
    //用户生成订单
     public function userPay(){
        $nature = isset($this->data['nature'])?$this->data['nature']:0;
        if($nature == 1){
            $course = CourseSchool::where(['id'=>$this->data['id'],'is_del'=>0,'status'=>1])->first();
            //查讲师
            $teacherlist = Couresteacher::where(['course_id'=>$course['class_id'],'is_del'=>0])->get();
            $string=[];
            if(!empty($teacherlist)){
                foreach ($teacherlist as $ks=>$vs){
                    $teacher = Teacher::where(['id'=>$vs['teacher_id'],'is_del'=>0,'type'=>2])->first();
                    $string[] = $teacher['real_name'];
                }
            $course['teachername'] = implode(',',$string);
            }
        }else{
            $course = Coures::where(['id'=>$this->data['id'],'is_del'=>0,'status'=>1])->first();
            //查讲师
            $teacherlist = Couresteacher::where(['course_id'=>$course['id'],'is_del'=>0])->get();
            $string=[];
            if(!empty($teacherlist)){
                foreach ($teacherlist as $ks=>$vs){
                    $teacher = Teacher::where(['id'=>$vs['teacher_id'],'is_del'=>0,'type'=>2])->first();
                    $string[] = $teacher['real_name'];
                }
             $course['teachername'] = implode(',',$string);
            }
        }
        //生成订单
         $data['order_number'] = date('YmdHis', time()) . rand(1111, 9999);
         $data['admin_id'] = 0;  //操作员id
         $data['order_type'] = 2;        //1线下支付 2 线上支付
         $data['student_id'] = $this->userid;
         $data['price'] = $course['sale_price'];
         $data['student_price'] = $course['pricing'];
         $data['lession_price'] = $course['pricing'];
         $data['pay_status'] = 4;
         $data['pay_type'] = 0;
         $data['status'] = 0;
         $data['oa_status'] = 0;              //OA状态
         $data['nature'] = $nature;
         $data['class_id'] = $this->data['id'];
         $data['school_id'] = $this->school['id'];
         DB::beginTransaction();
         try {
             $add = Order::insertGetId($data);
             if($add){
                 $course['order_id'] = $add;
                 $course['order_number'] = $data['order_number'];
                 DB::commit();
                 return ['code' => 200 , 'msg' => '生成预订单成功','data'=>$course];
             }else{
                 DB::rollback();
                 return ['code' => 203 , 'msg' => '生成订单失败'];
             }

         } catch (\Exception $ex) {
             DB::rollback();
             return ['code' => $ex->getCode() , 'msg' => $ex->__toString()];

         }
     }
     //用户进行支付  支付方式 1微信2支付宝
     public function userPaying(){
        $order = Order::where(['id'=>$this->data['order_id']])->first();
        Order::where(['id'=>$this->data['order_id']])->update(['pay_type'=>$this->data['pay_type']]);
        //根据订单查询商品
        if($order['nature'] == 0){
            $goods = Coures::where(['id'=>$order['class_id']])->first();
        }else{
            $goods = CourseSchool::where(['id'=>$order['class_id']])->first();
        }
        //微信

         if($this->data['pay_type'] == 1){
             $payinfo = PaySet::select('wx_app_id','wx_commercial_tenant_number','wx_api_key')->where(['school_id'=>$this->school['id']])->first();
             if(empty($payinfo) || empty($payinfo['wx_app_id']) || empty($payinfo['wx_commercial_tenant_number'])){
                 return response()->json(['code' => 202, 'msg' => '商户号为空']);
             }
             $wxpay = new WxpayFactory();
             $return = $wxpay->getPcPayOrder($payinfo['wx_app_id'],$payinfo['wx_commercial_tenant_number'],$payinfo['wx_api_key'],$order['order_number'],$order['price'],$goods['title']);
             if($return['code'] == 200){
                 require_once realpath(dirname(__FILE__).'/../../../Tools/phpqrcode/QRcode.php');
                 $code = new QRcode();
                 ob_start();//开启缓冲区
                 $returnData  = $code->pngString($return['data'], false, 'L', 10, 1);//生成二维码
                 $imageString = base64_encode(ob_get_contents());
                 ob_end_clean();
                 $str = "data:image/png;base64," . $imageString;
                 return response()->json(['code' => 200, 'msg' => '预支付订单生成成功', 'data' => $str]);
             }else{
                 return response()->json(['code' => 202, 'msg' => '生成二维码失败']);
             }
         }
        //支付宝
        if($this->data['pay_type'] == 2){
            $payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>$this->school['id']])->first();
            if(empty($payinfo) || empty($payinfo['zfb_app_id']) || empty($payinfo['zfb_app_public_key'])){
                return response()->json(['code' => 202, 'msg' => '商户号为空']);
            }
            $alipay = new AlipayFactory($this->school['id']);
            $return = $alipay->createPcPay($order['order_number'],$order['price'],$goods['title']);
            if($return['alipay_trade_precreate_response']['code'] == 10000){
                require_once realpath(dirname(__FILE__).'/../../../Tools/phpqrcode/QRcode.php');
                $code = new QRcode();
                $returnData  = $code->pngString($return['alipay_trade_precreate_response']['qr_code'], false, 'L', 10, 1);//生成二维码
                $imageString = base64_encode(ob_get_contents());
                ob_end_clean();
                $str = "data:image/png;base64," . $imageString;
                return ['code' => 200 , 'msg' => '支付','data'=>$str];
            }else{
                return ['code' => 202 , 'msg' => '生成二维码失败'];
            }
        }
         //汇聚微信
         if($this->data['pay_type'] == 3){
             if($this->school['id'] == ''){
                 $paylist=[
                     'hj_md_key' => '3f101520d11240299b25b2d2608b03a3',
                     'hj_commercial_tenant_number' => '888107600008111',
                     'hj_wx_commercial_tenant_deal_number' => '777183300269333'
                 ];
             }else{
                 $paylist = PaySet::select('hj_commercial_tenant_number','hj_md_key','hj_wx_commercial_tenant_deal_number')->where(['school_id'=>$this->school['id']])->first();
                 if(empty($paylist) || empty($paylist['hj_commercial_tenant_number'])){
                     return response()->json(['code' => 202, 'msg' => '商户号错误']);
                 }
             }
             $notify = 'AB|'."http://".$_SERVER['HTTP_HOST']."/web/course/hjwebnotify";
             $pay=[
                 'p0_Version'=>'1.0',
                 'p1_MerchantNo'=> $paylist['hj_commercial_tenant_number'],
                 'p2_OrderNo'=>$order['order_number'],
                 'p3_Amount'=>$order['price'],
                 'p4_Cur'=>1,
                 'p5_ProductName'=>$goods['title'],
                 'p9_NotifyUrl'=>$notify,
                 'q1_FrpCode'=>'WEIXIN_NATIVE',
                 'q4_IsShowPic'=>1,
                 'qa_TradeMerchantNo'=>$paylist['hj_wx_commercial_tenant_deal_number']
             ];
             $str = $paylist['hj_md_key'];
             $token = $this->hjHmac($pay,$str);
             $pay['hmac'] = $token;
             $wxpay = $this->hjpost($pay);
             $wxpayarr = json_decode($wxpay,true);
             file_put_contents('wxwebhjpay.txt', '时间:'.date('Y-m-d H:i:s').print_r($wxpayarr,true),FILE_APPEND);
             if($wxpayarr['ra_Code'] == 100){
                 return response()->json(['code' => 200, 'msg' => '支付','data'=>$wxpayarr['rd_Pic'],'notify'=>$notify]);
             }else{
                 return response()->json(['code' => 202, 'msg' => '生成二维码失败']);
             }
         }
         //汇聚支付宝
         if($this->data['pay_type'] == 4){
             if($this->school['id'] == ''){
                 $paylist=[
                     'hj_md_key' => '3f101520d11240299b25b2d2608b03a3',
                     'hj_commercial_tenant_number' => '888107600008111',
                     'hj_zfb_commercial_tenant_deal_number' => '777183300269333'
                 ];
             }else{
                 $paylist = PaySet::select('hj_commercial_tenant_number','hj_md_key','hj_zfb_commercial_tenant_deal_number')->where(['school_id'=>$this->school['id']])->first();
                 if(empty($paylist) || empty($paylist['hj_commercial_tenant_number'])){
                     return response()->json(['code' => 202, 'msg' => '商户号错误']);
                 }
             }
             $notify = 'AB|'."http://".$_SERVER['HTTP_HOST']."/web/course/hjwebnotify";
             $pay=[
                 'p0_Version'=>'1.0',
                 'p1_MerchantNo'=>$paylist['hj_commercial_tenant_number'],
                 'p2_OrderNo'=>$order['order_number'],
                 'p3_Amount'=>$order['price'],
                 'p4_Cur'=>1,
                 'p5_ProductName'=>$goods['title'],
                 'p9_NotifyUrl'=>$notify,
                 'q1_FrpCode'=>'ALIPAY_NATIVE',
                 'q4_IsShowPic'=>1,
                 'qa_TradeMerchantNo'=>$paylist['hj_zfb_commercial_tenant_deal_number']
             ];
             $str = $paylist['hj_md_key'];
             $token = $this->hjHmac($pay,$str);
             $pay['hmac'] = $token;
             $alipay = $this->hjpost($pay);
             $alipayarr = json_decode($alipay,true);
             file_put_contents('alihjpay.txt', '时间:'.date('Y-m-d H:i:s').print_r($alipayarr,true),FILE_APPEND);
             if($alipayarr['ra_Code'] == 100){
                 return response()->json(['code' => 200, 'msg' => '支付','data'=>$alipayarr['rd_Pic']]);
             }else{
                 return response()->json(['code' => 202, 'msg' => '生成二维码失败']);
             }
         }
         //银联扫码支付
         if(in_array($this->data['pay_type'],[5,8,9])) {
             $payinfo = PaySet::select('yl_mch_id','yl_key')->where(['school_id'=>$this->school['id']])->first();
             if(empty($payinfo) || empty($payinfo['yl_mch_id']) || empty($payinfo['yl_key'])){
                 return response()->json(['code' => 202, 'msg' => '商户号为空']);
             }
             $ylpay = new YinpayFactory();
             $return = $ylpay->getWebPayOrder($payinfo['yl_mch_id'],$payinfo['yl_key'],$order['order_number'],$goods['title'],$order['price']);
             require_once realpath(dirname(__FILE__).'/../../../Tools/phpqrcode/QRcode.php');
             $code = new QRcode();
             $returnData  = $code->pngString($return['data'], false, 'L', 10, 1);//生成二维码
             $imageString = base64_encode(ob_get_contents());
             ob_end_clean();
             $str = "data:image/png;base64," . $imageString;
             $return['data'] = $str;
             return response()->json($return);
         }
         //汇付扫码支付
         if($this->data['pay_type'] == 6) {
             $paylist = PaySet::select('hf_merchant_number','hf_password','hf_pfx_url','hf_cfca_ca_url','hf_cfca_oca_url')->where(['school_id'=>$this->school['id']])->first();
             if(empty($paylist) || empty($paylist['hf_merchant_number'])){
                 return response()->json(['code' => 202, 'msg' => '商户号错误']);
             }
             if(empty($paylist) || empty($paylist['hf_password'])){ //打开 key.pfx密码
                 return response()->json(['code' => 202, 'msg' => '密码错误']);
             }
             $noti['merNoticeUrl']= "http://".$_SERVER['HTTP_HOST']."/web/course/hfwebnotify";
             $newPrice  = str_replace(' ', '', $order['price']);
             $count = substr_count($newPrice,'.');
             if($count > 0){
                 $newPrice = explode(".",$newPrice);
                 if(strlen($newPrice[1])==0){
                     $price = $newPrice[0].".00";
                 }
                 if(strlen($newPrice[1])==1){
                     $price = $newPrice[0].'.'.$newPrice[1]."0";
                 }
                 if(strlen($newPrice[1])==2){
                     $price = $newPrice[0].'.'.$newPrice[1];
                 }
             }else{
                 $price = $newPrice.".00";
             }
             $data=[
                 'apiVersion' => '3.0.0.2',
                 'memberId' => $paylist['hf_merchant_number'],
                 'termOrdId' => $order['order_number'],
                 'ordAmt' => $price,
                 'goodsDesc' => urlencode($goods['title']),
                 'remark' => urlencode(''),
                 'payChannelType' => 'A1',
                 'merPriv' => json_encode($noti),
             ];
             $hfpos = new qrcp_E1103();
             $url = $hfpos->Hfpos($data,$paylist['hf_pfx_url'],$paylist['hf_password']);
             if($url['respCode'] == "000000"){
                 require_once realpath(dirname(__FILE__).'/../../../Tools/phpqrcode/QRcode.php');
                 $code = new QRcode();
                 ob_start();//开启缓冲区
                 $jsonData = json_decode($url['jsonData'],1);
                 $returnData  = $code->pngString($jsonData['qrcodeUrl'], false, 'L', 10, 1);//生成二维码
                 $imageString = base64_encode(ob_get_contents());
                 ob_end_clean();
                 $str = "data:image/png;base64," . $imageString;
                 return response()->json(['code' => 200, 'msg' => '支付', 'data' => $str]);
             }else{
                 return response()->json(['code' => 202, 'msg' => '生成二维码失败']);
             }
         }
     }
     //前端轮询查订单是否支付完成
    public function webajax(){
        if(!isset($this->data['order_number']) || empty($this->data['order_number'])){
            return ['code' => 201 , 'msg' => '订单号为空'];
        }
        $order = Order::where(['order_number'=>$this->data['order_number']])->first();
        if($order){
            if($order['status'] == 2){
                $fanb = 1;
            }else{
                $fanb = 0;
            }
            return ['code' => 200 , 'msg' => '查询成功','data'=>$fanb];
        }else{
            return ['code' => 201 , 'msg' => '订单号错误'];
        }
    }
    //0元购买接口
    public function chargeOrder(){
       $order = Order::where(['id'=>$this->data['order_id']])->first();
       if($order['price'] == 0){
           if($order['nature'] == 1){
               $lesson = CourseSchool::where(['id'=>$order['class_id']])->first();
           }else{
               $lesson = Coures::where(['id'=>$order['class_id']])->first();
           }
           if($lesson['expiry'] ==0){
               $validity = '3000-01-02 12:12:12';
           }else{
               $validity = date('Y-m-d H:i:s', strtotime('+' . $lesson['expiry'] . ' day'));
           }
           $arrs = array(
               'third_party_number'=>'',
               'validity_time'=>$validity,
               'status'=>2,
               'oa_status'=>1,
               'pay_time'=>date('Y-m-d H:i:s'),
               'update_at'=>date('Y-m-d H:i:s')
           );
           Order::where(['id'=>$order['id']])->update($arrs);
           $overorder = Order::where(['student_id'=>$order['student_id'],'status'=>2])->whereIn('pay_status',[3,4])->count(); //用户已完成订单
           $userorder = Order::where(['student_id'=>$order['student_id']])->whereIn('status',[1,2])->whereIn('pay_status',[3,4])->count(); //用户所有订单
           if($overorder == $userorder){
               $state_status = 2;
           }else{
               if($overorder > 0 ){
                   $state_status = 1;
               }else{
                   $state_status = 0;
               }
           }
           Student::where(['id'=>$order['student_id']])->update(['enroll_status'=>1,'state_status'=>$state_status,'update_at'=>date('Y-m-d H:i:s')]);
           return ['code' => 200 , 'msg' => '购买成功'];
       }else{
           return ['code' => 201 , 'msg' => '订单不合法'];
       }
    }
    /*======================================web 对公扫码支付===========================*/
    //对公购买信息
    public function scanPay(){
        $paytype = PaySet::where(['school_id' => $this->school['id']])->first();
        $pay=[];
        if(!empty($paytype)){
            if($paytype['wx_pay_state'] == 1){
                $paystatus=[
                    'paytype' => 1,
                    'payname' => '微信支付',
                    'payimg' => 'https://longdeapi.oss-cn-beijing.aliyuncs.com/wx2xtb.png',
                ];
                $pay[] = $paystatus;
            }
            if($paytype['zfb_pay_state'] == 1){
                $paystatus=[
                    'paytype' => 2,
                    'payname' => '支付宝支付',
                    'payimg' => 'https://longdeapi.oss-cn-beijing.aliyuncs.com/zfb2xtb.png',
                ];
                $pay[] = $paystatus;
            }
            if($paytype['hj_wx_pay_state'] == 1){
                $paystatus=[
                    'paytype' => 3,
                    'payname' => '微信支付',
                    'payimg' => 'https://longdeapi.oss-cn-beijing.aliyuncs.com/wx2xtb.png',
                ];
                $pay[] = $paystatus;
            }
            if($paytype['hj_zfb_pay_state'] == 1){
                $paystatus=[
                    'paytype' => 4,
                    'payname' => '支付宝支付',
                    'payimg' => 'https://longdeapi.oss-cn-beijing.aliyuncs.com/zfb2xtb.png',
                ];
                $pay[] = $paystatus;
            }
            // if($paytype['yl_pay_state'] == 1){  //银联
            //     $paystatus=[
            //         'paytype' => 5,
            //         'payname' => '云闪付',
            //         'payimg' => 'https://longdeapi.oss-cn-beijing.aliyuncs.com/upload/2020-10-10/160230173318475f812f2531b6e.png',
            //     ];
            //     $pay[] = $paystatus;
            // }
            if($paytype['yl_pay_state'] == 1){   // 银联-支付宝支付
                $paystatus=[
                    'paytype' => 8,
                    'payname' => '支付宝支付',
                    'payimg' => 'https://longdeapi.oss-cn-beijing.aliyuncs.com/zfb2xtb.png',
                ];
                $pay[] = $paystatus;
            }
            if($paytype['yl_pay_state'] == 1){   //银联-微信支付
                $paystatus=[
                    'paytype' => 9,
                    'payname' => '微信支付',
                    'payimg' => 'https://longdeapi.oss-cn-beijing.aliyuncs.com/wx2xtb.png',
                ];
                $pay[] = $paystatus;
            }
            if($paytype['hf_pay_state'] == 1){     // paytype = 7 给汇付的微信支付占坑
                $paystatus=[
                    'paytype' => 6,
                    'payname' => '支付宝支付',
                    'payimg' => 'https://longdeapi.oss-cn-beijing.aliyuncs.com/zfb2xtb.png',
                ];
                $pay[] = $paystatus;
            }
        }
        $school['title'] = $this->school['title'];
        $school['subhead'] = $this->school['subhead'];
        return response()->json(['code' => 200, 'msg' => '成功','data' => $school,'payarr' => $pay]);
    }
    //web支付
    public function converge(){
         if(!isset($this->data['parent_id']) || $this->data['parent_id'] == 0){
            return response()->json(['code' => 201, 'msg' => '请选择学科大类']);
         }
         // if(!isset($this->data['chint_id']) || $this->data['chint_id'] == 0){
         //    return response()->json(['code' => 201, 'msg' => '请选择学科小类']);
         // }
         if(!isset($this->data['id']) || $this->data['id'] == 0){
            return response()->json(['code' => 201, 'msg' => '请选择课程']);
         }
         if($this->data['nature'] == 1){
             $course = CourseSchool::where(['id'=>$this->data['id'],'is_del'=>0,'status'=>1])->first();
         }else{
             $course = Coures::where(['id'=>$this->data['id'],'is_del'=>0,'status'=>1])->first();
         }
         if(empty($course)){
             return response()->json(['code' => 201, 'msg' => '未查到此课程信息']);
         }
         if(!isset($this->data['phone']) || $this->data['phone'] == ''){
              return response()->json(['code' => 201, 'msg' => '请填写手机号']);
         }
         if(!isset($this->data['price']) || $this->data['price'] <= 0){
             return response()->json(['code' => 201, 'msg' => '金额不能为0']);
         }
         $arr = [
             'username' => $this->data['username'],
             'phone' => $this->data['phone'],
             'order_number' => date('YmdHis', time()) . rand(1111, 9999),
             'pay_status' => $this->data['pay_status'],
             'price' => $this->data['price'],
             'status' => 0,
             'parent_id' => $this->data['parent_id'],
             'chint_id' => $this->data['chint_id'],
             'course_id' => $this->data['id'],
             'nature' => $this->data['nature'],
             'school_id' => $this->school['id'],
         ];
        $add = Converge::insert($arr);
        if($add) {
            //微信
            if ($this->data['pay_status'] == 1) {
                $payinfo = PaySet::select('wx_app_id','wx_commercial_tenant_number','wx_api_key')->where(['school_id'=>$this->school['id']])->first();
                if(empty($payinfo) || empty($payinfo['wx_app_id']) || empty($payinfo['wx_commercial_tenant_number'])){
                    return response()->json(['code' => 202, 'msg' => '商户号为空']);
                }
                $wxpay = new WxpayFactory();
                $return = $wxpay->convergecreatePcPay($payinfo['wx_app_id'],$payinfo['wx_commercial_tenant_number'],$payinfo['wx_api_key'],$arr['order_number'],$arr['price'],$course['title']);

                if($return['code'] == 200){
                    require_once realpath(dirname(__FILE__).'/../../../Tools/phpqrcode/QRcode.php');
                    $code = new QRcode();
                    ob_start();//开启缓冲区
                    $returnData  = $code->pngString($return['data'], false, 'L', 10, 1);//生成二维码
                    $imageString = base64_encode(ob_get_contents());
                    ob_end_clean();
                    $str = "data:image/png;base64," . $imageString;
                    return response()->json(['code' => 200, 'msg' => '预支付订单生成成功', 'data' => $str]);
                }else{
                    return response()->json(['code' => 202, 'msg' => '生成二维码失败']);
                }
            }
            //支付宝
            if ($this->data['pay_status'] == 2) {
                $payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>$this->school['id']])->first();
                if(empty($payinfo) || empty($payinfo['zfb_app_id']) || empty($payinfo['zfb_app_public_key'])){
                    return response()->json(['code' => 202, 'msg' => '商户号为空']);
                }
                $alipay = new AlipayFactory($this->school['id']);
                $return = $alipay->convergecreatePcPay($arr['order_number'],$arr['price'],$course['title']);
                if($return['alipay_trade_precreate_response']['code'] == 10000){
                    require_once realpath(dirname(__FILE__).'/../../../Tools/phpqrcode/QRcode.php');
                    $code = new QRcode();
                    ob_start();//开启缓冲区
                    $returnData  = $code->pngString($return['alipay_trade_precreate_response']['qr_code'], false, 'L', 10, 1);//生成二维码
                    $imageString = base64_encode(ob_get_contents());
                    ob_end_clean();
                    $str = "data:image/png;base64," . $imageString;
                    return response()->json(['code' => 200, 'msg' => '预支付订单生成成功', 'data' => $str]);
                } else {
                    return response()->json(['code' => 202, 'msg' => '生成二维码失败']);
                }
            }
            //汇聚微信
            if($this->data['pay_status'] == 3){
                if($this->school['id'] == ''){
                    $paylist=[
                        'hj_md_key' => '3f101520d11240299b25b2d2608b03a3',
                        'hj_commercial_tenant_number' => '888107600008111',
                        'hj_wx_commercial_tenant_deal_number' => '777183300269333'
                    ];
                }else{
                    $paylist = PaySet::select('hj_commercial_tenant_number','hj_md_key','hj_wx_commercial_tenant_deal_number')->where(['school_id'=>$this->school['id']])->first();
                    if(empty($paylist) || empty($paylist['hj_commercial_tenant_number'])){
                        return response()->json(['code' => 202, 'msg' => '商户号错误']);
                    }
                }
                $notify = 'AB|'."http://".$_SERVER['HTTP_HOST']."/web/course/hjnotify";
                $pay=[
                    'p0_Version'=>'1.0',
                    'p1_MerchantNo'=> $paylist['hj_commercial_tenant_number'],
                    'p2_OrderNo'=>$arr['order_number'],
                    'p3_Amount'=>$this->data['price'],
                    'p4_Cur'=>1,
                    'p5_ProductName'=>$course['title'],
                    'p9_NotifyUrl'=>$notify,
                    'q1_FrpCode'=>'WEIXIN_NATIVE',
                    'q4_IsShowPic'=>1,
                    'qa_TradeMerchantNo'=>$paylist['hj_wx_commercial_tenant_deal_number']
                ];
                $str = $paylist['hj_md_key'];
                $token = $this->hjHmac($pay,$str);
                $pay['hmac'] = $token;
                $wxpay = $this->hjpost($pay);
                $wxpayarr = json_decode($wxpay,true);
                file_put_contents('wxhjpay.txt', '时间:'.date('Y-m-d H:i:s').print_r($wxpayarr,true),FILE_APPEND);
                if($wxpayarr['ra_Code'] == 100){
                    return response()->json(['code' => 200, 'msg' => '预支付订单生成成功','data'=>$wxpayarr['rd_Pic']]);
                }else{
                    return response()->json(['code' => 202, 'msg' => '暂未开通']);
                }
            }
            //汇聚支付宝
            if($this->data['pay_status'] == 4){
                if($this->school['id'] == ''){
                    $paylist=[
                        'hj_md_key' => '3f101520d11240299b25b2d2608b03a3',
                        'hj_commercial_tenant_number' => '888107600008111',
                        'hj_zfb_commercial_tenant_deal_number' => '777183300269333'
                    ];
                }else{
                    $paylist = PaySet::select('hj_commercial_tenant_number','hj_md_key','hj_zfb_commercial_tenant_deal_number')->where(['school_id'=>$this->school['id']])->first();
                    if(empty($paylist) || empty($paylist['hj_commercial_tenant_number'])){
                        return response()->json(['code' => 202, 'msg' => '商户号错误']);
                    }
                }
                $notify = 'AB|'."http://".$_SERVER['HTTP_HOST']."/web/course/hjnotify";
                $pay=[
                    'p0_Version'=>'1.0',
                    'p1_MerchantNo'=>$paylist['hj_commercial_tenant_number'],
                    'p2_OrderNo'=>$arr['order_number'],
                    'p3_Amount'=>$this->data['price'],
                    'p4_Cur'=>1,
                    'p5_ProductName'=>$course['title'],
                    'p9_NotifyUrl'=>$notify,
                    'q1_FrpCode'=>'ALIPAY_NATIVE',
                    'q4_IsShowPic'=>1,
                    'qa_TradeMerchantNo'=>$paylist['hj_zfb_commercial_tenant_deal_number']
                ];
                $str = $paylist['hj_md_key'];
                $token = $this->hjHmac($pay,$str);
                $pay['hmac'] = $token;
                $alipay = $this->hjpost($pay);
                $alipayarr = json_decode($alipay,true);
                file_put_contents('alihjpay.txt', '时间:'.date('Y-m-d H:i:s').print_r($alipayarr,true),FILE_APPEND);
                if($alipayarr['ra_Code'] == 100){
                    return response()->json(['code' => 200, 'msg' => '预支付订单生成成功','data'=>$alipayarr['rd_Pic']]);
                }else{
                    return response()->json(['code' => 202, 'msg' => '暂未开通']);
                }
            }
            //银联扫码支付
            if(in_array($this->data['pay_status'],[5,8,9])) {
                $payinfo = PaySet::select('yl_mch_id','yl_key')->where(['school_id'=>$this->school['id']])->first();
                if(empty($payinfo) || empty($payinfo['yl_mch_id']) || empty($payinfo['yl_key'])){
                    return response()->json(['code' => 202, 'msg' => '商户号为空']);
                }
                $ylpay = new YinpayFactory();
                $return = $ylpay->getPrePayOrder($payinfo['yl_mch_id'],$payinfo['yl_key'],$arr['order_number'],$course['title'],$arr['price']);
                if($return['code'] == 200){
                    require_once realpath(dirname(__FILE__).'/../../../Tools/phpqrcode/QRcode.php');
                    $code = new QRcode();
                    $returnData  = $code->pngString($return['data'], false, 'L', 10, 1);//生成二维码
                    $imageString = base64_encode(ob_get_contents());
                    ob_end_clean();
                    $str = "data:image/png;base64," . $imageString;
                    $return['data'] = $str;
                    return response()->json($return);
                }else{
                    $return['data'] = '无法生成二维码';
                    return response()->json($return);
                }
            }
            //汇付扫码支付
            if($this->data['pay_status'] == 6) {
                $paylist = PaySet::select('hf_merchant_number','hf_password','hf_pfx_url','hf_cfca_ca_url','hf_cfca_oca_url')->where(['school_id'=>$this->school['id']])->first();
                if(empty($paylist) || empty($paylist['hf_merchant_number'])){
                    return response()->json(['code' => 202, 'msg' => '商户号错误']);
                }
                if(empty($paylist) || empty($paylist['hf_password'])){ //打开 key.pfx密码
                    return response()->json(['code' => 202, 'msg' => '密码错误']);
                }
                $noti['merNoticeUrl']= "http://".$_SERVER['HTTP_HOST']."/web/course/hfnotify";
                $newPrice  = str_replace(' ', '', $arr['price']);
                $count = substr_count($newPrice,'.');
                if($count > 0){
                    $newPrice = explode(".",$newPrice);
                    if(strlen($newPrice[1])==0){
                        $price = $newPrice[0].".00";
                    }
                    if(strlen($newPrice[1])==1){
                        $price = $newPrice[0].'.'.$newPrice[1]."0";
                    }
                    if(strlen($newPrice[1])==2){
                       $price = $newPrice[0].'.'.$newPrice[1];
                    }
                }else{
                    $price = $newPrice.".00";
                }


                $data=[
                    'apiVersion' => '3.0.0.2',
                    'memberId' => $paylist['hf_merchant_number'],
                    'termOrdId' => $arr['order_number'],
                    'ordAmt' => $price,
                    'goodsDesc' => urlencode($course['title']),
                    'remark' => urlencode(''),
                    'payChannelType' => 'A1',
                    'merPriv' => json_encode($noti),
                ];
                $hfpos = new qrcp_E1103();
                $url = $hfpos->Hfpos($data,$paylist['hf_pfx_url'],$paylist['hf_password']);
                if($url['respCode'] == "000000"){
                    require_once realpath(dirname(__FILE__).'/../../../Tools/phpqrcode/QRcode.php');
                    $code = new QRcode();
                    ob_start();//开启缓冲区
                    $jsonData = json_decode($url['jsonData'],1);
                    $returnData  = $code->pngString($jsonData['qrcodeUrl'], false, 'L', 10, 1);//生成二维码
                    $imageString = base64_encode(ob_get_contents());
                    ob_end_clean();
                    $str = "data:image/png;base64," . $imageString;
                    return response()->json(['code' => 200, 'msg' => '预支付订单生成成功', 'data' => $str]);
                }else{
                    return response()->json(['code' => 202, 'msg' => '生成二维码失败']);
                }
            }
        }
    }

    public function csali(){
//        $alipay = new AlipayFactory($this->school['id']);
//        $order_number = date('YmdHis', time()) . rand(1111, 9999);
//        $return = $alipay->convergecreatePcPay($order_number,0.01,'开发人员测试');
//        print_r($return);die;

        $paylist=[
            'hj_md_key' => '330be60cdde54a9391fd6e12ac3ec0c0',
            'hj_commercial_tenant_number' => '888109100000664',
            'hj_wx_commercial_tenant_deal_number' => '777168300273552'
        ];
        $notify = 'AB|'."http://".$_SERVER['HTTP_HOST']."/web/course/hjnotify";
        $pay=[
            'p0_Version'=>'1.0',
            'p1_MerchantNo'=> $paylist['hj_commercial_tenant_number'],
            'p2_OrderNo'=>date('YmdHis', time()) . rand(1111, 9999),
            'p3_Amount'=>0.01,
            'p4_Cur'=>1,
            'p5_ProductName'=>'商品测试',
            'p9_NotifyUrl'=>$notify,
            'q1_FrpCode'=>'WEIXIN_NATIVE',
            'q4_IsShowPic'=>1,
            'qa_TradeMerchantNo'=>$paylist['hj_wx_commercial_tenant_deal_number']
        ];
        $str = $paylist['hj_md_key'];
        $token = $this->hjHmac($pay,$str);
        $pay['hmac'] = $token;
        $wxpay = $this->hjpost($pay);
        $wxpayarr = json_decode($wxpay,true);
        print_r($wxpayarr);die;
    }
    //汇聚签名
    public function hjHmac($arr,$str){
        $newarr = '';
        foreach ($arr as $k=>$v){
            $newarr =$newarr.$v;
        }
        return md5($newarr.$str);
    }
    public function hjpost($data){
        //简单的curl
        $ch = curl_init("https://www.joinpay.com/trade/uniPayApi.action");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    //汇付支付
    public function hfpay(){
        echo "123456";
        $noti['merNoticeUrl']= "http://".$_SERVER['HTTP_HOST']."/web/course/hfnotify";
        $data=[
            'apiVersion' => '3.0.0.2',
            'memberId' => '310000016002293818',
            'termOrdId' => date('YmdHis', time()) . rand(111111, 999999),
            'ordAmt' => '0.01',
            'goodsDesc' => urlencode('aaaa'),
            'remark' => urlencode(''),
            'payChannelType' => 'A1',
            'merPriv' => json_encode($noti),
        ];
        $hfpos = new qrcp_E1103();
        $url = $hfpos->Hfpos($data);



        $zfbpay = $this->hfpost($data);
        return $zfbpay;
    }
}
