<?php
namespace App\Tools;

use App\Models\PaySet;

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
        $notify_url = 'https://'.$_SERVER['HTTP_HOST'].'/web/course/wxnotifyurl';
        $onoce_str = $this->getRandChar(32);
        $data["appid"] = $appid;
        $data["body"] = $title;
        $data["mch_id"] = $tenant_number;
        $data["nonce_str"] = $onoce_str;
        $data["notify_url"] = $notify_url;
        $data["out_trade_no"] = $order_number;
        $data["spbill_create_ip"] = "127.0.0.1";
        $data["total_fee"] = $total_fee*100;
        $data["trade_type"] = "NATIVE";
        $s = $this->getSign($data, $api_key);
        $data["sign"] = $s;
        $xml = $this->arrayToXml($data);
        $response = $this->postXmlCurl($xml, $url);
        //将微信返回的结果xml转成数组
        $res = $this->xmlstr_to_array($response);
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

    //pc扫码报名
    public function convergecreatePcPay($appid,$tenant_number,$api_key,$order_number,$total_fee,$title){
        //获取商品名称
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $notify_url = 'https://'.$_SERVER['HTTP_HOST'].'web/course/wxnotify';
        $onoce_str = $this->getRandChar(32);
        $data["appid"] = $appid;
        $data["body"] = $title;
        $data["mch_id"] = $tenant_number;
        $data["nonce_str"] = $onoce_str;
        $data["notify_url"] = $notify_url;
        $data["out_trade_no"] = $order_number;
        $data["spbill_create_ip"] = "127.0.0.1";
        $data["total_fee"] = $total_fee*100;
        $data["trade_type"] = "NATIVE";
        $s = $this->getSign($data, $api_key);
        $data["sign"] = $s;
        $xml = $this->arrayToXml($data);
        $response = $this->postXmlCurl($xml, $url);
        //将微信返回的结果xml转成数组
        $res = $this->xmlstr_to_array($response);
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
    public function getOrder($prepayId){
        $data["appid"] = 'wx7663a456bb43d30b';
        $data["noncestr"] = $this->getRandChar(32);
        $data["package"] = "Sign=WXPay";
        //商户号
        $data["partnerid"] = '1553512891';
        $data["prepayid"] = $prepayId;
        $data["timestamp"] = time();
        $s = $this->getSign($data, false);
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
             if (is_numeric($val))
             {
                $xml.="<".$key.">".$val."</".$key.">";


             }
             else
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
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


}
