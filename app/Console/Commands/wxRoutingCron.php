<?php

namespace App\Console\Commands;

use App\Models\SchoolOrder;
use App\Models\WxRouting;
use Illuminate\Console\Command;
use App\Tools\WxpayFactory;
use Illuminate\Support\Facades\Log;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;

class WxRoutingCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'WxRoutingUpdate';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '更改微信下单前一分钟订单分账';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 请求微信前一分钟的订单的分账接口
     *
     * @return mixed
     */
    public function handle()
    {
        $time = time(); //当前时间
        $wxRoutingArr = WxRouting::whereBetween('add_time', [$time-60, $time])->get()->toArray();

        if(!empty($wxRoutingArr)){
            $wxpay = new WxpayFactory();
            foreach($wxRoutingArr as $keys=>$v){
                $params["appid"] = $v['appid']; // 调用接口提供的公众账号ID
                $params["mch_id"] = $v['mch_id']; //调用接口时提供的商户号
                $params["sub_mch_id"] = $v['sub_mch_id'];   //微信支付分配的子商户号，即分账的出资商户号。
                $params["nonce_str"] = $v['nonce_str']; //微信返回的随机字符串
                $params["transaction_id"] = $v['transaction_id'];
                $params["out_order_no"] = $v['out_order_no']; //订单单号
                $receivers=[
                    "type"=>"MERCHANT_ID",
                    "account"=>"1601424720", //服务商的商户号
                    "amount"=>$v['price']*100,
                    "description"=>"服务商"
                ];
                $params['receivers'] = json_encode($receivers);
                $signStr = 'appid='.$params["appid"]."&mch_id=" . $params["mch_id"] . "&nonce_str=" . $params["nonce_str"] . "&out_order_no=" . $params["out_order_no"]."&receivers=".$params['receivers']."&sub_mch_id=" . $params["sub_mch_id"]."&transaction_id=" . $params["transaction_id"];
                $signStr = $signStr . "&key=$Key";
                $params["sign"] = hash_hmac('sha256', $signStr, $Key);
                $data = $wxpay->arrayToXml($params);
                $postResults = $wxpay->postXmlH5Curl($data,"https://api.mch.weixin.qq.com/secapi/pay/profitsharing");
                $postObjs = $wxpay->xmlToArray($postResults);
                file_put_contents('fenzhangwendang.txt', '时间:'.date('Y-m-d H:i:s').print_r($postObjs,true),FILE_APPEND);
                if($postObj['return_code'] == 'SUCCESS' && $postObjs['result_code'] == 'SUCCESS'){
                    //修改数据库信息
                    $res = WxRouting::where('routing_order_number',$order_number)->update(['status'=>1,'update_time'=>date('Y-m-d H:i:s')]);
                    if($res){
                        echo "Success----";
                    }else{
                        echo "Fail---";
                    }
                }else{
                      echo "202--";
                }
            }
        }else{
            echo "....".$time."-60";
        }
    }

}
