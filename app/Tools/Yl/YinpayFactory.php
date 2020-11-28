<?php
namespace App\Tools\Yl;

class YinpayFactory{
    //web银联支付  扫码支付
    public function getPrePayOrder($mchid,$key,$order_number,$goodsname,$total_fee){
        //参数拼接
        $url = "https://qra.95516.com/pay/gateway";
        $data['service'] = 'unified.trade.native';
        $data['sign_type'] = 'MD5';
        $data['mch_id'] = $mchid; //测试的商户号
        $data['out_trade_no'] = $order_number;//订单号
        $data['body'] = $goodsname;//商品名
        $data['total_fee'] = $total_fee*100;//金额
        $data['mch_create_ip'] = $this->get_client_ip(); //ip
        $data['notify_url'] = 'http://'.$_SERVER['HTTP_HOST'].'/web/course/ylnotify';
        $data['nonce_str'] = $this->getRandChar(32); //字符串
        $s = $this->getSign($data, $key);
        $data['sign'] = $s;
        $xml = $this->toXml($data);
        $response = $this->postXmlCurl($xml, $url);
        //将微信返回的结果xml转成数组
        $res = $this->xmlstr_to_array($response);
        file_put_contents('ylpay.txt', '时间:' . date('Y-m-d H:i:s') . print_r($res, true), FILE_APPEND);
        if($res['status'] == 0){
            return ['code'=>200,'msg'=>'预支付订单生成成功','data'=>$res['code_url']];
        }else{
            return ['code'=>201,'msg'=>'暂未开通'];
        }
    }

    //web 课程直接购买
    public function getWebPayOrder($mchid,$key,$order_number,$goodsname,$total_fee){
        //参数拼接
        $url = "https://qra.95516.com/pay/gateway";
        $data['service'] = 'unified.trade.native';
        $data['sign_type'] = 'MD5';
        $data['mch_id'] = $mchid; //测试的商户号
        $data['out_trade_no'] = $order_number;//订单号
        $data['body'] = $goodsname;//商品名
        $data['total_fee'] = $total_fee*100;//金额
        $data['mch_create_ip'] = $this->get_client_ip(); //ip
        $data['notify_url'] = 'http://'.$_SERVER['HTTP_HOST'].'/web/course/ylwebnotify';
        $data['nonce_str'] = $this->getRandChar(32); //字符串
        $s = $this->getSign($data, $key);
        $data['sign'] = $s;
        $xml = $this->toXml($data);
        $response = $this->postXmlCurl($xml, $url);
        //将微信返回的结果xml转成数组
        $res = $this->xmlstr_to_array($response);
        file_put_contents('ylwebpay.txt', '时间:' . date('Y-m-d H:i:s') . print_r($res, true), FILE_APPEND);
        if($res['status'] == 0){
            return ['code'=>200,'msg'=>'支付','data'=>$res['code_url']];
        }else{
            return ['code'=>201,'msg'=>'生成二维码失败'];
        }
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
    public static function toXml($array){
        $xml = '<xml>';
        forEach($array as $k=>$v){
            $xml.='<'.$k.'><![CDATA['.$v.']]></'.$k.'>';
        }
        $xml.='</xml>';
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
