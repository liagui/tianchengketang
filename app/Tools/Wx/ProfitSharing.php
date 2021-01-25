<?php
namespace App\Tools;

use App\Tools\ProfitSharingSign;
use App\Tools\ProfitSharingCurl;

class ProfitSharing
{
    private $wxConfig = null;
    private $sign = null;
    private $curl = null;


    public function __construct()
    {
        $this->wxConfig = $this->wxConfig();
        $this->sign = new ProfitSharingSign();
        $this->curl = new ProfitSharingCurl();
    }


    /**
     * @function 发起请求所必须的配置参数
     * @return mixed
     */
    private function wxConfig($data)
    { 
        $wxConfig['app_id'] = 'wxc129cacb4a2a4be7';//服务商公众号AppID
        $wxConfig['mch_id'] = '1601424720'; //服务商商户号
        $wxConfig['sub_app_id'] = '';//todo 子服务商公众号AppID //
        $wxConfig['sub_mch_id'] = ''; //todo 子服务商商户号
        $wxConfig['md5_key'] = '08365ca4d8dc608d561abfc159452b8c'; //md5 秘钥
        $wxConfig['app_cert_pem'] = '';//证书路径
        $wxConfig['app_key_pem'] = '';//证书路径
        return $wxConfig;
    }


    /**
     * @function 请求多次分账接口
     * @param $orders array 待分账订单
     * @param $accounts array 分账接收方
     * @return array
     * @throws Exception
     */
    public function multiProfitSharing($orders,$accounts)
    {
        if(empty($orders)){
            throw new Exception('没有待分帐订单');
        }
        if(empty($accounts)){
            throw new Exception('接收分账账户为空');
        }

        //1.设置分账账号
        $receivers = array();
        foreach ($accounts as $account)
        {
            $tmp = array(
                'type'=>$account['type'],
                'account'=>$account['account'],
                'amount'=>intval($account['amount']),
                'description'=>$account['desc'],
            );
            $receivers[] = $tmp;
        }
        $receivers = json_encode($receivers,JSON_UNESCAPED_UNICODE);

        $totalCount = count($orders);
        $successCount = 0;
        $failCount = 0;
        $now = time();
        foreach ($orders as $order)
        {
            //2.生成签名
            $postArr = array(
                'appid'=>$this->wxConfig['app_id'],
                'mch_id'=>$this->wxConfig['mch_id'],
                'sub_mch_id'=>$this->wxConfig['sub_mch_id'],
                'sub_appid'=>$this->wxConfig['sub_app_id'],
                'nonce_str'=>md5(time() . rand(1000, 9999)),
                'transaction_id'=>$order['trans_id'],
                'out_order_no'=>$order['order_no'].$order['ticket_no'],
                'receivers'=>$receivers,
            );

            $sign = $this->sign->getSign($postArr, 'HMAC-SHA256',$this->wxConfig['md5_key']);
            $postArr['sign'] = $sign;


            //3.发送请求
            $url = 'https://api.mch.weixin.qq.com/secapi/pay/multiprofitsharing';
            $postXML = $this->toXml($postArr);
            Ilog::DEBUG("multiProfitSharing.postXML: " . $postXML);

            $opts = array(
                CURLOPT_HEADER    => 0,
                CURLOPT_SSL_VERIFYHOST    => false,
                CURLOPT_SSLCERTTYPE   => 'PEM', //默认支持的证书的类型，可以注释
                CURLOPT_SSLCERT   => $this->wxConfig['app_cert_pem'],
                CURLOPT_SSLKEY    => $this->wxConfig['app_key_pem'],
            );
            Ilog::DEBUG("multiProfitSharing.opts: " . json_encode($opts));

            $curl_res = $this->curl->setOption($opts)->post($url,$postXML);
            Ilog::DEBUG("multiProfitSharing.curl_res: " . $curl_res);

            $ret = $this->toArray($curl_res);
            if($ret['return_code']=='SUCCESS' and $ret['result_code']=='SUCCESS')
            {
                //更新分账订单状态
                $params = array();
                $params['order_no'] =  $order['order_no'];
                $params['trans_id'] =  $order['trans_id'];
                $params['ticket_no'] =  $order['ticket_no'];

                $data = array();
                $data['profitsharing'] = $receivers;
                $data['state'] = 2;
                pdo_update('ticket_orders_profitsharing',$data,$params);
                $successCount++;

            }else{
                $failCount++;
            }
            usleep(500000);//微信会报频率过高，所以停一下
        }

        return array('processTime'=>date('Y-m-d H:i:s',$now),'totalCount'=>$totalCount,'successCount'=>$successCount,'failCount'=>$failCount);

    }


    /**
     * @function 请求单次分账接口
     * @param $profitSharingOrders array 待分账订单
     * @param $profitSharingAccounts array 分账接收方
     * @return array
     * @throws Exception
     */
    public function profitSharing($profitSharingOrders,$profitSharingAccounts)
    {
        if(empty($profitSharingOrders)){
            throw new Exception('没有待分帐订单');
        }
        if(empty($profitSharingAccounts)){
            throw new Exception('接收分账账户为空');
        }

        //1.设置分账账号
        $receivers = array();
        foreach ($profitSharingAccounts as $profitSharingAccount)
        {
            $tmp = array(
                'type'=>$profitSharingAccount['type'],
                'account'=>$profitSharingAccount['account'],
                'amount'=>intval($profitSharingAccount['amount']),
                'description'=>$profitSharingAccount['desc'],
            );
            $receivers[] = $tmp;
        }
        $receivers = json_encode($receivers,JSON_UNESCAPED_UNICODE);

        $totalCount = count($profitSharingOrders);
        $successCount = 0;
        $failCount = 0;
        $now = time();

        foreach ($profitSharingOrders as $profitSharingOrder)
        {
            //2.生成签名
            $postArr = array(
                'appid'=>$this->wxConfig['app_id'],
                'mch_id'=>$this->wxConfig['mch_id'],
                'sub_mch_id'=>$this->wxConfig['sub_mch_id'],
                'sub_appid'=>$this->wxConfig['sub_app_id'],
                'nonce_str'=>md5(time() . rand(1000, 9999)),
                'transaction_id'=>$profitSharingOrder['trans_id'],
                'out_order_no'=>$profitSharingOrder['order_no'].$profitSharingOrder['ticket_no'],
                'receivers'=>$receivers,
            );

            $sign = $this->sign->getSign($postArr, 'HMAC-SHA256',$this->wxConfig['md5_key']);
            $postArr['sign'] = $sign;

            //3.发送请求
            $url = 'https://api.mch.weixin.qq.com/secapi/pay/profitsharing';
            $postXML = $this->toXml($postArr);
            Ilog::DEBUG("profitSharing.postXML: " . $postXML);

            $opts = array(
                CURLOPT_HEADER    => 0,
                CURLOPT_SSL_VERIFYHOST    => false,
                CURLOPT_SSLCERTTYPE   => 'PEM', //默认支持的证书的类型，可以注释
                CURLOPT_SSLCERT   => $this->wxConfig['app_cert_pem'],
                CURLOPT_SSLKEY    => $this->wxConfig['app_key_pem'],
            );
            Ilog::DEBUG("profitSharing.opts: " . json_encode($opts));

            $curl_res = $this->curl->setOption($opts)->post($url,$postXML);
            Ilog::DEBUG("profitSharing.curl_res: " . $curl_res);

            $ret = $this->toArray($curl_res);
            if($ret['return_code']=='SUCCESS' and $ret['result_code']=='SUCCESS')
            {
                //更新分账订单状态
                $params = array();
                $params['order_no'] =  $profitSharingOrder['order_no'];
                $params['trans_id'] =  $profitSharingOrder['trans_id'];
                $params['ticket_no'] =  $profitSharingOrder['ticket_no'];

                $data = array();
                $data['profitsharing'] = $receivers;
                $data['state'] = 2;
                pdo_update('ticket_orders_profitsharing',$data,$params);
                $successCount++;

            }else{
                $failCount++;
            }

        }

        return array('processTime'=>date('Y-m-d H:i:s',$now),'totalCount'=>$totalCount,'successCount'=>$successCount,'failCount'=>$failCount);

    }


    /**
     * @function 查询分账结果
     * @param $trans_id string 微信支付单号
     * @param $out_order_no string 分账单号
     * @return array|false
     * @throws Exception
     */
    public function query($trans_id,$out_order_no)
    {
        //1.生成签名
        $postArr = array(
            'mch_id'=>$this->wxConfig['mch_id'],
            'sub_mch_id'=>$this->wxConfig['sub_mch_id'],
            'transaction_id'=>$trans_id,
            'out_order_no'=>$out_order_no,
            'nonce_str'=>md5(time() . rand(1000, 9999)),
        );

        $sign = $this->sign->getSign($postArr, 'HMAC-SHA256',$this->wxConfig['md5_key']);
        $postArr['sign'] = $sign;


        //2.发送请求
        $url = 'https://api.mch.weixin.qq.com/pay/profitsharingquery';
        $postXML = $this->toXml($postArr);
        Ilog::DEBUG("query.postXML: " . $postXML);

        $curl_res = $this->curl->post($url,$postXML);
        Ilog::DEBUG("query.curl_res: " . $curl_res);

        $ret = $this->toArray($curl_res);
        return $ret;
    }


    /**
     * @function 添加分账接收方
     * @param $profitSharingAccount array 分账接收方
     * @return array|false
     * @throws Exception
     */
    public function addReceiver($profitSharingAccount)
    {
        //1.接收分账账户
        $receiver = array(
            'type'=>$profitSharingAccount['type'],
            'account'=>$profitSharingAccount['account'],
            'name'=>$profitSharingAccount['name'],
            'relation_type'=>$profitSharingAccount['relation_type'],
        );
        $receiver = json_encode($receiver,JSON_UNESCAPED_UNICODE);

        //2.生成签名
        $postArr = array(
            'appid'=>$this->wxConfig['app_id'],
            'mch_id'=>$this->wxConfig['mch_id'],
            'sub_mch_id'=>$this->wxConfig['sub_mch_id'],
            'sub_appid'=>$this->wxConfig['sub_app_id'],
            'nonce_str'=>md5(time() . rand(1000, 9999)),
            'receiver'=>$receiver
        );

        $sign = $this->sign->getSign($postArr, 'HMAC-SHA256',$this->wxConfig['md5_key']);
        $postArr['sign'] = $sign;


        //3.发送请求
        $url = 'https://api.mch.weixin.qq.com/pay/profitsharingaddreceiver';
        $postXML = $this->toXml($postArr);
        Ilog::DEBUG("addReceiver.postXML: " . $postXML);

        $curl_res = $this->curl->post($url,$postXML);
        Ilog::DEBUG("addReceiver.curl_res: " . $curl_res);

        $ret = $this->toArray($curl_res);
        return $ret;
    }


    /**
     * @function 删除分账接收方
     * @param $profitSharingAccount array 分账接收方
     * @return array|false
     * @throws Exception
     */
    public function removeReceiver($profitSharingAccount)
    {
        //1.接收分账账户
        $receiver = array(
            'type'=>$profitSharingAccount['type'],
            'account'=>$profitSharingAccount['account'],
            'name'=>$profitSharingAccount['name'],
        );
        $receiver = json_encode($receiver,JSON_UNESCAPED_UNICODE);

        //2.生成签名
        $postArr = array(
            'appid'=>$this->wxConfig['app_id'],
            'mch_id'=>$this->wxConfig['mch_id'],
            'sub_mch_id'=>$this->wxConfig['sub_mch_id'],
            'sub_appid'=>$this->wxConfig['sub_app_id'],
            'nonce_str'=>md5(time() . rand(1000, 9999)),
            'receiver'=>$receiver
        );

        $sign = $this->sign->getSign($postArr, 'HMAC-SHA256',$this->wxConfig['md5_key']);
        $postArr['sign'] = $sign;


        //3.发送请求
        $url = 'https://api.mch.weixin.qq.com/pay/profitsharingremovereceiver';
        $postXML = $this->toXml($postArr);
        Ilog::DEBUG("removeReceiver.postXML: " . $postXML);

        $curl_res = $this->curl->post($url,$postXML);
        Ilog::DEBUG("removeReceiver.curl_res: " . $curl_res);

        $ret = $this->toArray($curl_res);
        return $ret;
    }


    /**
     * @function 完结分账
     * @param $profitOrder array 分账订单
     * @param $description string 完结分账描述
     * @return array|false
     * @throws Exception
     */
    public function finish($profitOrder,$description='分账完结')
    {
        $ret = array();
        if(!empty($profitOrder))
        {
            //1.签名
            $postArr = array(
                'mch_id'=>$this->wxConfig['mch_id'],
                'sub_mch_id'=>$this->wxConfig['sub_mch_id'],
                'appid'=>$this->wxConfig['app_id'],
                'nonce_str'=>md5(time() . rand(1000, 9999)),
                'transaction_id'=>$profitOrder['trans_id'],
                'out_order_no'=>'finish'.'_'.$profitOrder['order_no'],
                'description'=>$description,
            );

            $sign = $this->sign->getSign($postArr, 'HMAC-SHA256',$this->wxConfig['md5_key']);
            $postArr['sign'] = $sign;


            //2.请求
            $url = 'https://api.mch.weixin.qq.com/secapi/pay/profitsharingfinish';
            $postXML = $this->toXml($postArr);
            Ilog::DEBUG("finish.postXML: " . $postXML);

            $opts = array(
                CURLOPT_HEADER    => 0,
                CURLOPT_SSL_VERIFYHOST    => false,
                CURLOPT_SSLCERTTYPE   => 'PEM', //默认支持的证书的类型，可以注释
                CURLOPT_SSLCERT   => $this->wxConfig['app_cert_pem'],
                CURLOPT_SSLKEY    => $this->wxConfig['app_key_pem'],
            );
            Ilog::DEBUG("finish.opts: " . json_encode($opts));

            $curl_res = $this->curl->setOption($opts)->post($url,$postXML);
            Ilog::DEBUG("finish.curl_res: " . $curl_res);

            $ret = $this->toArray($curl_res);
        }

        return $ret;
    }


    /**
     * @function 分账回退
     * @param $profitOrder array 分账订单
     * @return array
     * @throws Exception
     */
    public function profitSharingReturn($profitOrder)
    {
        $ret = array();
        if(!empty($profitOrder) and $profitOrder['channel']==1)
        {
            $accounts = json_decode($profitOrder['profitsharing'],true);
            foreach ($accounts as $account)
            {
                //1.签名
                $postArr = array(
                    'appid'=>$this->wxConfig['app_id'],
                    'mch_id'=>$this->wxConfig['mch_id'],
                    'sub_mch_id'=>$this->wxConfig['sub_mch_id'],
                    'sub_appid'=>$this->wxConfig['sub_app_id'],
                    'nonce_str'=>md5(time() . rand(1000, 9999)),
                    'out_order_no'=>$profitOrder['order_no'].$profitOrder['ticket_no'],
                    'out_return_no'=>'return_'.$profitOrder['order_no'].$profitOrder['ticket_no'].'_'.$account['account'],
                    'return_account_type'=>'MERCHANT_ID',
                    'return_account'=>$account['account'],
                    'return_amount'=>$account['amount'],
                    'description'=>'用户退款',
                    'sign_type'=>'HMAC-SHA256',
                );

                $sign = $this->sign->getSign($postArr, 'HMAC-SHA256',$this->wxConfig['md5_key']);
                $postArr['sign'] = $sign;


                //2.请求
                $url = 'https://api.mch.weixin.qq.com/secapi/pay/profitsharingreturn';
                $postXML = $this->toXml($postArr);
                Ilog::DEBUG("profitSharingReturn.postXML: " . $postXML);

                $opts = array(
                    CURLOPT_HEADER    => 0,
                    CURLOPT_SSL_VERIFYHOST    => false,
                    CURLOPT_SSLCERTTYPE   => 'PEM', //默认支持的证书的类型，可以注释
                    CURLOPT_SSLCERT   => $this->wxConfig['app_cert_pem'],
                    CURLOPT_SSLKEY    => $this->wxConfig['app_key_pem'],
                );
                Ilog::DEBUG("profitSharingReturn.opts: " . json_encode($opts));

                $curl_res = $this->curl->setOption($opts)->post($url,$postXML);
                Ilog::DEBUG("profitSharingReturn.curl_res: " . $curl_res);

                $ret[] = $this->toArray($curl_res);
            }

        }
        return $ret;
    }


    /**
     * @function 回退结果查询
     * @param $order_no string 本地订单号
     * @param $ticket_no string 本地票号
     * @return array|false
     * @throws \Exception
     */
    public function returnQuery($order_no,$ticket_no)
    {
        $ret = array();
        $profitOrder = pdo_fetch("SELECT * FROM zc_ticket_orders_profitsharing WHERE order_no='{$order_no}' AND ticket_no='{$ticket_no}'");
        if($profitOrder['channel']==1 and $profitOrder['state']==2)
        {
            $accounts = json_decode($profitOrder['profitsharing'],true);
            foreach ($accounts as $account)
            {
                //1.签名
                $postArr = array(
                    'appid'=>$this->wxConfig['app_id'],
                    'mch_id'=>$this->wxConfig['mch_id'],
                    'sub_mch_id'=>$this->wxConfig['sub_mch_id'],
                    'nonce_str'=>md5(time() . rand(1000, 9999)),
                    'out_order_no'=>$profitOrder['order_no'].$profitOrder['ticket_no'],
                    'out_return_no'=>'return_'.$profitOrder['order_no'].$profitOrder['ticket_no'].'_'.$account['account'],
                    'sign_type'=>'HMAC-SHA256',
                );

                $sign = $this->sign->getSign($postArr, 'HMAC-SHA256',$this->wxConfig['md5_key']);
                $postArr['sign'] = $sign;

                //2.请求
                $url = 'https://api.mch.weixin.qq.com/pay/profitsharingreturnquery';
                $postXML = $this->toXml($postArr);
                Ilog::DEBUG("returnQuery.postXML: " . $postXML);

                $curl_res = $this->curl->post($url,$postXML);
                Ilog::DEBUG("returnQuery.curl_res: " . $curl_res);

                $ret[] = $this->toArray($curl_res);
            }

        }
        return $ret;
    }


    /**
     * @function 将array转为xml
     * @param array $values
     * @return string|bool
     * @author xiewg
     **/
    public function toXml($values)
    {
        if (!is_array($values) || count($values) <= 0) {
            return false;
        }

        $xml = "<xml>";
        foreach ($values as $key => $val) {
            if (is_numeric($val)) {
                $xml.="<".$key.">".$val."</".$key.">";
            } else {
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    /**
     * @function 将xml转为array
     * @param string $xml
     * @return array|false
     * @author xiewg
     */
    public function toArray($xml)
    {
        if (!$xml) {
            return false;
        }

        // 检查xml是否合法
        $xml_parser = xml_parser_create();
        if (!xml_parse($xml_parser, $xml, true)) {
            xml_parser_free($xml_parser);
            return false;
        }

        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);

        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        return $data;
    }


}
