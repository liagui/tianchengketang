<?php
namespace App\Tools;

use App\Models\PaySet;
use App\Models\WxRouting;

class WxpayFactory{
    //pay_type   1购买2充值
    public function getPrePayOrder($goodsname,$order_number,$total_fee,$schoolid,$pay_type){
        //回调
        if($pay_type == 1){
            $notifyurl = 'https://'.$_SERVER['HTTP_HOST'].'/Api/notify/wxnotify';
        }else{
            $notifyurl = 'https://'.$_SERVER['HTTP_HOST'].'/Api/notify/wxTopnotify';
        }
        //获取商品名称
        $shopname = $goodsname;
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $notify_url = $notifyurl;
        $out_trade_no = $order_number;
        $onoce_str = $this->getRandChar(32);
        $data["appid"] = 'wx7663a456bb43d30b';
        $data["body"] = $shopname;
        $data["mch_id"] = '1553512891';
        $data["nonce_str"] = $onoce_str;
        $data["notify_url"] = $notify_url;
        $data["out_trade_no"] = $out_trade_no;
        $data["spbill_create_ip"] = "127.0.0.1";
        $data["total_fee"] = $total_fee*100;
        $data["trade_type"] = "APP";
        $s = $this->getSign($data, false);
        $data["sign"] = $s;
        $xml = $this->arrayToXml($data);
        $response = $this->postXmlCurl($xml, $url);
        //将微信返回的结果xml转成数组
        $res = $this->xmlstr_to_array($response);
        file_put_contents('wxpay.txt', '时间:' . date('Y-m-d H:i:s') . print_r($res, true), FILE_APPEND);
        if($res['return_code'] != 'Success'){
            $arr = array('code'=>204,'msg'=>$res['return_msg']);
        }else{
            $sign2 = $this->getOrder($res['prepay_id']);
            if(!empty($sign2)){
                $arr = array('code'=>200,'list'=>$sign2);
            }else{
                $arr = array('code'=>1001,'list'=>"请确保参数合法性！");
            }
        }
        return $arr;
    }

    //pc购买课程扫码支付
    public function getPcPayOrder($appid,$tenant_number,$api_key,$order_number,$total_fee,$title){
        //获取商品名称
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/web/course/wxwebnotify';
        $onoce_str = $this->getRandChar(32);
        $data["appid"] = $appid;
        $data["body"] = $title;
        $data["mch_id"] = $tenant_number;
        $data["nonce_str"] = $onoce_str;
        $data["notify_url"] = $notify_url;
        $data["out_trade_no"] = $order_number;
        $data["spbill_create_ip"] = $this->get_client_ip();
        $data["total_fee"] = $total_fee*100;
        $data["trade_type"] = "NATIVE";
        $s = $this->getSign($data, $api_key);
        $data["sign"] = $s;
        $xml = $this->arrayToXml($data);
        $response = $this->postXmlCurl($xml, $url);
        //将微信返回的结果xml转成数组
        $res = $this->xmlstr_to_array($response);
        if($res['return_code'] != 'SUCCESS'){
            $arr = array('code'=>204,'data'=>$res['return_msg']);
        }else {
            $arr = array('code' => 200, 'data' => $res['code_url']);
        }
        return $arr;
    }

    //pc扫码报名
    public function convergecreatePcPay($appid,$tenant_number,$api_key,$order_number,$total_fee,$title){
        //获取商品名称
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/web/course/wxnotify';
        $onoce_str = $this->getRandChar(32);
        $data["appid"] = $appid;
        $data["body"] = $title;
        $data["mch_id"] = $tenant_number;
        $data["nonce_str"] = $onoce_str;
        $data["notify_url"] = $notify_url;
        $data["out_trade_no"] = $order_number;
        $data["spbill_create_ip"] = $this->get_client_ip();
        $data["total_fee"] = $total_fee*100;
        $data["trade_type"] = "NATIVE";
        $s = $this->getSign($data, $api_key);
        $data["sign"] = $s;
        $xml = $this->arrayToXml($data);
        $response = $this->postXmlCurl($xml, $url);
        //将微信返回的结果xml转成数组
        $res = $this->xmlstr_to_array($response);
        if($res['return_code'] != 'SUCCESS'){
            $arr = array('code'=>204,'data'=>$res['return_msg']);
        }else{
            $arr = array('code'=>200,'data'=>$res['code_url']);
        }
        return $arr;
    }
    //微信公众号支付
    public function getAppPayOrder($appid,$mch_id,$key,$order_number,$total_fee,$title,$openid){
        $rand = md5(time() . mt_rand(0, 1000));
        $param["appid"] = $appid;
        $param["openid"] = $openid;
        $param["mch_id"] = $mch_id;
        $param["nonce_str"] = "$rand";
        $param["body"] = $title;
        $param["out_trade_no"] = $order_number; //订单单号
        $param["total_fee"] = 1;//支付金额
        $param["spbill_create_ip"] = $_SERVER["REMOTE_ADDR"];
        $param["notify_url"] = "http://".$_SERVER['HTTP_HOST']."/web/official/wxAppnotify";
        $param["trade_type"] = "JSAPI";
        $signStr = 'appid=' . $param["appid"] . "&body=" . $param["body"] . "&mch_id=" . $param["mch_id"] . "&nonce_str=" . $param["nonce_str"] . "&notify_url=" . $param["notify_url"] . "&openid=" . $param["openid"] . "&out_trade_no=" . $param["out_trade_no"] . "&spbill_create_ip=" . $param["spbill_create_ip"] . "&total_fee=" . $param["total_fee"] . "&trade_type=" . $param["trade_type"];
        $signStr = $signStr . "&key=$key";
        $param["sign"] = strtoupper(MD5($signStr));
        $data = $this->arrayToXml($param);
        $postResult = $this->postXmlCurl($data,"https://api.mch.weixin.qq.com/pay/unifiedorder");
        $postObj = $this->xmlToArray($postResult);
        $msg = $postObj['return_code'];
        if ($msg == "SUCCESS") {
            $result["timestamp"] = strval(time());
            $result["nonceStr"] = $postObj['nonce_str'];  //不加""拿到的是一个json对象
            $result["package"] = "prepay_id=" . $postObj['prepay_id'];
            $result["signType"] = "MD5";
            $paySignStr = 'appId=' . $param["appid"] . '&nonceStr=' . $result["nonceStr"] . '&package=' . $result["package"] . '&signType=' . $result["signType"] . '&timeStamp=' . $result["timestamp"];
            $paySignStr = $paySignStr . "&key=$key";
            $result["paySign"] = strtoupper(MD5($paySignStr));
            $result['appId'] = $appid;
            $arr = ['code'=>200,'list'=>$result];
            return $arr;
        } else {
            //202  代表微信支付生成失败
            $arr = ['code'=>202,'list'=>"请确保参数合法性！"];
            return $arr;
        }
    }


    //微信H5支付
    public function getH5PayOrder($appid,$mch_id,$key,$order_number,$total_fee,$title,$openid){
        $rand = md5(time() . mt_rand(0, 1000));
        $param["appid"] = $appid;
        // $param["openid"] = $openid;
        $param["mch_id"] = $mch_id;
        $param["sub_mch_id"] = "1604760227";
        $param["nonce_str"] = "$rand";
        $param["body"] = $title;
        $param["out_trade_no"] = $order_number; //订单单号
        $param["total_fee"] = 0.05 * 100;//支付金额
        $param['profit_sharing'] = 'Y';
        $param["spbill_create_ip"] = $_SERVER["REMOTE_ADDR"];
        $param["notify_url"] = "http://".$_SERVER['HTTP_HOST']."/web/official/wxApph5notify";
        $param["trade_type"] = "MWEB";
        $signStr = 'appid=' . $param["appid"] . "&body=" . $param["body"] . "&mch_id=" . $param["mch_id"] . "&nonce_str=" . $param["nonce_str"] . "&notify_url=" . $param["notify_url"] . "&out_trade_no=" . $param["out_trade_no"] . "&profit_sharing=" . $param["profit_sharing"] . "&spbill_create_ip=" . $param["spbill_create_ip"] .  "&sub_mch_id=" . $param["sub_mch_id"] . "&total_fee=" . $param["total_fee"] . "&trade_type=" . $param["trade_type"];
        $signStr = $signStr . "&key=$key";
        $param["sign"] = strtoupper(MD5($signStr));
        $data = $this->arrayToXml($param);
        $postResult = $this->postXmlCurl($data,"https://api.mch.weixin.qq.com/pay/unifiedorder");
        $postObj = $this->xmlToArray($postResult);

         return $postObj;
        $msg = $postObj['return_code'];

        if ($msg == "SUCCESS") {
            // $result["timestamp"] = strval(time());
            // $result["nonceStr"] = $postObj['nonce_str'];  //不加""拿到的是一个json对象
            // $result["package"] = "prepay_id=" . $postObj['prepay_id'];
            // $result["signType"] = "MD5";
            // $paySignStr = 'appId=' . $param["appid"] . '&nonceStr=' . $result["nonceStr"] . '&package=' . $result["package"] . '&signType=' . $result["signType"] . '&timeStamp=' . $result["timestamp"];
            // $paySignStr = $paySignStr . "&key=$key";
            // $result["paySign"] = strtoupper(MD5($paySignStr));
            // $result['appId'] = $appid;
            // $arr = ['code'=>200,'list'=>$result];
            return $postObj;
        } else {
            //202  代表微信支付生成失败
            $arr = ['code'=>202,'list'=>"请确保参数合法性！"];
            return $arr;
        }
    }


    //微信分账接口
     public function getH5PayOrders($mch_id,$transaction_id,$order_number,$Key){
        $rand = md5(time() . mt_rand(0, 1000));
        $param["appid"] = "wxc129cacb4a2a4be7";
        $param["mch_id"] = "1601424720";
        $param["sub_mch_id"] = "1604760227";
        $param["nonce_str"] = $rand;
        $param["transaction_id"] = $transaction_id;
        $param["out_trade_no"] = $order_number; //订单单号
        $receivers = [
                    [
                      "type"=> "MERCHANT_ID",
                      "account"=>"1601424720",
                      "amount"=>100,
                      "description"=> "分到商户"
                     ],

            ];

        $param['receivers'] = json_encode($receivers);
        $signStr = 'appid='.$param["appid"]."&mch_id=" . $param["mch_id"] . "&nonce_str=" . $param["nonce_str"] . "&out_trade_no=" . $param["out_trade_no"]."&receivers=".$param['receivers']."&sub_mch_id=" . $param["sub_mch_id"]."&transaction_id=" . $param["transaction_id"];

        $signStr = $signStr . "&key=$Key";

        $param["sign"] = hash_hmac('sha256', $signStr, $Key);

        $data = $this->arrayToXml($param);
        $postResult = $this->postXmlH5Curl($data,"https://api.mch.weixin.qq.com/secapi/pay/profitsharing");

        $postObj = $this->xmlToArray($postResult);
        file_put_contents('fenzhangwendang.txt', '时间:'.date('Y-m-d H:i:s').print_r($postObj,true),FILE_APPEND);

        if($postObj['return_code'] == 'SUCCESS' && $postObj['result_code'] == 'SUCCESS'){
            //修改数据库信息
            $res = WxRouting::where('routing_order_number',$order_number)->update(['status'=>1,'update_time'=>date('Y-m-d H:i:s')]);
            if($res){
                return "Success";
            }else{
                return "Fail";
            }
        }else{
              return 202;
        }



     }


    //添加微信分账账户

    public function addWxfzAccount($mch_id,$transaction_id,$fzorder_number,$order_number,$Key){
        $rand = md5(time() . mt_rand(0, 1000));
        $param["mch_id"] = "1601424720"; //微信支付分配的服务商商户号
        $param["sub_mch_id"] = "1604760227"; //	微信支付分配的子商户号，即分账的出资商户号。
        $param["appid"] = "wxc129cacb4a2a4be7";
        $param["nonce_str"] = $rand;
        $receiver =
            [
                'type'=>'MERCHANT_ID',
                'account'=>'1601424720',
                'name'=>'深圳爱道信息科技有限公司',
                'relation_type'=>'SERVICE_PROVIDER'
            ];
        $param['receiver'] = json_encode($receiver);
        $signStr ="appid=" . $param["appid"]. '&mch_id=' . $param["mch_id"] . "&nonce_str=" . $param["nonce_str"] . "&receiver=" . $param["receiver"] . "&sub_mch_id=" . $param["sub_mch_id"];
        $signStr = $signStr . "&key=$Key";

        $param["sign"] = hash_hmac('sha256', $signStr, '08365ca4d8dc608d561abfc159452b8c');

        $data = $this->arrayToXml($param);

        $postResult = $this->postXmlCurl($data,"https://api.mch.weixin.qq.com/pay/profitsharingaddreceiver");
        $postObj = $this->xmlstr_to_array($postResult);
         file_put_contents('fzAccountsssss.txt', '时间:'.date('Y-m-d H:i:s').print_r($postObj,true),FILE_APPEND);
    
        if($postObj['return_code'] == 'SUCCESS' && $postObj['result_code'] == 'SUCCESS'){
            //入库
            $params["appid"] = $postObj['appid'];
            $params["mch_id"] = $postObj['mch_id'];
            $params["sub_mch_id"] = $postObj['sub_mch_id'];
            $params["nonce_str"] = $postObj['nonce_str'];
            $params["transaction_id"] = $transaction_id;
            $params["out_order_no"] = $fzorder_number; //订单单号
            $params["update_time"] = date('Y-m-d H:i:s');
            $res = WxRouting::where(['routing_order_number'=>$order_number])->update($params);
            if($res){
                return "Success";
            }else{
                return "Fail";
            }
        }else{
              return 202;
        }

    }




    /**
     * 移除空值的key
     * @param $para
     * @return array
     */
    public function paraFilter($para)
    {
        $paraFilter = array();
        while (list($key, $val) = each($para))
        {
            if ($val == "") {
                continue;

            } else {
                if (! is_array($para[$key])) {
                    $para[$key] = is_bool($para[$key]) ? $para[$key] : trim($para[$key]);
                }

                $paraFilter[$key] = $para[$key];
            }
        }
        return $paraFilter;
    }


    /**
     * @function 对输入的数组进行字典排序
     * @param array $param 需要排序的数组
     * @return array
     * @author helei
     */
    public function arraySort(array $param)
    {
        ksort($param);
        reset($param);
        return $param;
    }


    /**
     * @function 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param array $para 需要拼接的数组
     * @return string
     * @throws \Exception
     */
    public function createLinkString($para)
    {
        if (! is_array($para)) {
            throw new \Exception('必须传入数组参数');
        }

        reset($para);
        $arg  = "";
        while (list($key, $val) = each($para)) {
            if (is_array($val)) {
                continue;
            }

            $arg.=$key."=".urldecode($val)."&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }

        return $arg;
    }




    /*
        生成签名
    */
    function getSign($Obj,$key)
    {
        foreach ($Obj as $k => $v)
        {
            $Parameters[strtolower($k)] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //echo "【string】 =".$String."</br>";
        //签名步骤二：在string后加入KEY
        $String = $String."&key=".$key;
        //echo "<textarea style='width: 50%; height: 150px;'>$String</textarea> <br />";
        //签名步骤三：MD5加密
        $result_ = strtoupper(md5($String));
        return $result_;
    }

    //执行第二次签名，才能返回给客户端使用
    public function getOrder($appid,$mch_id,$app_key,$prepayId){
        $data["appid"] = $appid;
        $data["noncestr"] = $this->getRandChar(32);
        $data["package"] = "Sign=WXPay";
        //商户号
        $data["partnerid"] = $mch_id;
        $data["prepayid"] = $prepayId;
        $data["timestamp"] = time();
        $s = $this->getSign($data, $app_key);
        $data["sign"] = $s;
        return $data;
    }
    //获取指定长度的随机字符串
    function getRandChar($length){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;


        for($i=0;$i<$length;$i++){
            $str.=$strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }


        return $str;
    }
    /*
        获取当前服务器的IP
    */
    function get_client_ip()
    {
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $cip = getenv("HTTP_CLIENT_IP");
        } else {
            $cip = "unknown";
        }
        return $cip;
    }
    //xml转数组
    function xmlstr_to_array($xml){
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }
    //数组转xml
    function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            // if (is_numeric($val))
            // {
                $xml.="<".$key.">".$val."</".$key.">";


            // }
            // else
            //     $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
        }
        $xml.="</xml>";
        return $xml;
    }
    //将数组转成uri字符串
    function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v)
        {
            if($urlencode)
            {
                $v = urlencode($v);
            }
            $buff .= strtolower($k) . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0)
        {
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }
    //post https请求，CURLOPT_POSTFIELDS xml格式
    function postXmlCurl($xml,$url,$second=30)
    {
        //初始化curl
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data)
        {
            curl_close($ch);
            return $data;
        }
        else
        {
            $error = curl_errno($ch);
            echo "curl出错，错误码:$error"."<br>";
            echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
            curl_close($ch);
            return false;
        }
    }

    function postXmlH5Curl($xml,$url,$second=30)
    {
        //初始化curl
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

        curl_setopt($ch,CURLOPT_SSLCERT, dirname(__FILE__) . '/apiclient_cert.pem');
        curl_setopt($ch,CURLOPT_SSLKEY, dirname(__FILE__) . '/apiclient_key.pem');
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data)
        {
            curl_close($ch);
            return $data;
        }
        else
        {
            $error = curl_errno($ch);
            echo "curl出错，错误码:$error"."<br>";
            echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
            curl_close($ch);
            return false;
        }
    }
    function domnode_to_array($node) {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = $this->domnode_to_array($child);
                    if(isset($child->tagName)) {
                        $t = $child->tagName;
                        if(!isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    }
                    elseif($v) {
                        $output = (string) $v;
                    }
                }
                if(is_array($output)) {
                    if($node->attributes->length) {
                        $a = array();
                        foreach($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string) $attrNode->value;
                        }
                        $output['@attributes'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if(is_array($v) && count($v)==1 && $t!='@attributes') {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
        return $output;
    }
    function post_curl($url, $para, $json = true,$header=''){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl,CURLOPT_HTTPHEADER,$header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);          //显示输出结果

        if (!empty($para)) {
            if ($json && is_array($para)) {
                $para = json_encode($para);
            }
            curl_setopt($curl, CURLOPT_POST, true);             //post传输数据
            curl_setopt($curl, CURLOPT_POSTFIELDS, $para);      //post传输数据
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $responseText = curl_exec($curl);
        curl_close($curl);
        return $responseText;
    }
    function xmlToArray($xml){
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }



}
