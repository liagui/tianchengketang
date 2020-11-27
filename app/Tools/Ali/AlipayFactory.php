<?php
namespace App\Tools;

use App\Models\PaySet;
use App\Providers\aop\AlipayTradeAppPayRequest\AlipayTradeAppPayRequest;
use App\Providers\aop\AlipayTradePrecreateRequest\AlipayTradePrecreateRequest;
use App\Providers\aop\AopClient\AopClient;

class AlipayFactory{
    protected $aop;
    //protected $schoolid;
    //公共参数
    public function __construct($school_id=''){
        require_once 'aop/AopClient.php';
        require_once 'aop/request/AlipayTradeAppPayRequest.php';
        //根据学校查询支付信息
//        if($school_id == ''){
//            $payinfo = [
//                'zfb_app_id' => '2021001105658113',
//                'zfb_app_public_key' =>'MIIEowIBAAKCAQEAqi3W8s2Pz9oYja5nkVKlCkaX9vsEIrBimVhgH/cPGjLKcKKy98QRgSPTaG3zFS8dxDYzEB1RDKjUS2myaabXyuN8qoMj5UyczDxSKWRKiBpOUZ75N8rIGl8AM+reufu7ga1YnZcz8rscTWG1TAF9rAtQS5cYLQF02lXtLUkFPWwqmLfGvh1q9rW0BgcLnD0r38HsMFxj6ROpa4Z/mk6b3Vf+HZ+a46Z5NpymyIJbdt7xIG+0Uy0ctOKcs+YWXkmRYMHHBse6KHjzbgIx246IN7Paix4C5vkOsd4Hbc5Evx1sxczi7yYLFMv1kev6QJiYraZ38tyZURyWIy0Coi5UXQIDAQABAoIBAEihG7WwaYop6IS/RFBPV0SVcFHmO5Oad9o+T3gU9wsVVjTQG1WHBnl5Esbk9fO6khelkhF0kZy3iTNOPui8XiinAhO7uFwqYFkB/YbQ2MZRg89t66sWDmTC2tFNkhUKDLKBiupnF7KmjKOx6bAwirQcd/5q09SRZI+yUHEdUvEtP2+fx8POWSvkz5cuJKusaD4pSzE7f9s2F0G8gF+557i+8aVZnJQWI0JXh5w6UpnltUusaBfsw7MFixF3CJCXA2HiIJM0ikfVQKPm2m6ASWcur2PWblYcixeGe83E7iuBzIosdIKM+uSL9hNWBGcwL4SElb72HTFfnNrlxhuy4gECgYEA6vCpnqjzQnPlKpv53VE8xOZrolxUV24vdRWZKCteGtxfDHS2gxtswszeFuRs9hmEYZBiHnsNHpDM4IGq15x3wx96KQo795U/mM7Ixx1GTEw6oZiGxeF7MfjSV3yHL+kFBAuDP1cvSJO8r+TdWYr3dUVeO4UOOZ78SevPspkToZ0CgYEAuW8PR17JpMPtQzaiXuCCdl0RmCuUWV/MzlJXa35xB9pqUtcQpr/fJM7fxLQ768wGYcloCLm2Q71hcfV5oSIlY1f7m/XyIM64euuZR2bQD3q2P0bHUyp6ibUh18XHCUNqQHTKoWNEfUJpOAbTW4XK0CX2Bj/TDv4H7mWUZwgpYcECgYAUIetnHTM7TpMkw5j1zjBW7yfqEd9oXpjSf7dQKec2hgvfFWFOetsnFkcxzwFHVYhyk9zUn9bP97iWxIXPVCkvH1NokOfyn2eDwLST235aq22ay2dBLcFQ1vGvbYxoHp+/aP0mQGJc5cwVhpcxRSdPdVJN52kApw8Xho2V0GhOQQKBgQCJ8EqOTb1z+mcRW5/XMez6fWrsJmbZQQFJ7UioZstQCzKSYvc5A3vLlrQwT95PHlsU/MyNyRADPeox6mfK7Gqhhr5dGsw9iWkDzyQbUCivixns4gq+G9hBfeMp7i6L/oEYZ4igGwbEotVAXxt0docS5Voo9etbuK5PsXJ+XjziQQKBgDsSlIOiSqd1GnHiOOAKtfxQ9MNZZIgNbKCSAnj8sZXULXiBiQub3ITv5Sapt0VNQf9c3tpieRLoSZM6DomzyeEuakm5mZ9/2IF0Ngv3pE9fv5MaZBBcG3XaJzwCX9NOZtxHdaW2UdYNNCBKJ+YHHNHbhfOvSMQys/yk9MnfBgtk',
//                'zfb_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAlTAdFGs8uzPYG3akYT1qs3gEFtjkuRIjP2i7FHUiF52/FVTSzOiYwy9n4qQYovyP/lKxtFWTlKMZfjy1G8EYJBbcb/5dIdDbgm40yaactPaeGkAvykzw5az0PhYTUFJ7PSewZyTJeqETT8ROpuIY5rxgNVHciASiNvrSOMudHfUtqvS7mUPX/Kcpl9q0ryW6BJUIb5SnFouVmh0x6ZAyb+cXVqPXrBTLlQucT3RKuvR+zMkT9IeFFn9fIsCBGhVg8eHfacKUjOWT00CILyoLk6rIZF+PRDX32kvxLKAlfq1puupT2BZxDpH3+LvcMj0Cpl0jmXylEqAxM6qh5+sdjwIDAQAB'
//            ];
//        }else{
//            $payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>$school_id])->first();
//        }
        $payinfo = [
            'zfb_app_id' => '2021001108657918',
            'zfb_app_public_key'=>'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC97esqK1XnsqepfVljz/Tw/0Nr07  IyQgyGfIWDUvD+9UMSLQfmlUaSXhHR8eKXkALFtV3M  +8yO9Az9wNmkx6oTzn7TGRkouOBuNNS2klVptsgQde3Y6geDPc5VTlbB9OdYsiJLrE5aDeoGBzO8aj  HkTNH  +JbygBCDwuwpAj8GG7oIxZniq/Qytjj7OQN8hawDtiGDx98goYkrvNIWmA46mEbOEFLxQumGK3rzDP  Zd3z0RqHi0LpLQgyhObpVVSt85jqmOK44tfyeYI7C5td0PQ5btzBPDMsf7zzd1+4ntRW  +/80Yd2w98IzXxrkXBlE9p9MZflYm/1p9mwoe6mWkV3AgMBAAECggEAYrIn6hnq4iQsjB7fPMbr  +fAsEPRJPWSlLZ23o66OHW9GE0PjPyeDLLxFdlvD7A6h4iuFOuf  +PKsFtTdp4f7/mptLvFbmhArOVXaOsvEIAY9CF4uwtW  +nx8NuXVYAL3ocXjLzL2+yp4ljew5zDA4DLyfcV700b9K1a2NGyJXrcznIsWlUp3sK6lC3OwFOswgQ  vzl51rz7fPfzmeSLWgPBQGWr6iaevPDIiT8l0bh2BGQ7AKA1ld5Ksill9LfZeF  +BHcgsH2T74m9owRDEMNjj32CurwNXlWLz57shTYOrM  +kc01JHvmGPON95w3CP0o4oCnDVlOkssZKPGNEJkMJKgQKBgQDdnyfHLSvMG  +9hn5jXvxnZVPQGFWd6PgAbcjlope7tN/f8tr0r2C5uLnajio3J8gvm0d  +2Zbfdni7W9RQfhyOOA4t4J0+WrWtt40f4ZWYL2R8N5i4NTP/bIo2Xw7TzB2Rvs1zQP2LbvPAhXTcK  xUwd3a9KVj4eo1M34SdayKnZGQKBgQDbZDm3cFlh9tzaU  +/RlvINBWjO7ESi35fiBNPdcndo7lGrIxPIu00v9onIJrgLIC0W9U  +OPGrCTQ3qdWRsGLoBfkyrOhNcIq3D2uFzTFgGDYLb23t9+7zlA9U4228D7qXFOtl7Ip9Fm7xCzJtR  0N3Y8VkMoQL9eJU2n08FY8OVDwKBgQDCCojEfotbYZYdbqRfOgYC4Lvr26/HOyPS5BbZxndEof1x  +fn1uokklW/wzu5IQ7Ih/d4XEEaFNuuh7+EXnbYGsJnbsOehcOOfyiEInpdThl4HSGNH6AQYtM8ucv  8qzm0k0/FOsED5Thsdy0TXHFoByEijGJG5N500TuGrPghgeQKBgQCZ/HPiRBIIh4umFkn6Mtc7unNA  4WafZw6kzjyibshPNw7NbrZhKs3Xf1RfzoVZEcF0HsQzEkbgj9LXoIPWt1g  +2hYYDJAwGAscr9GT6p7RyMPzas73syx3FcSfvqzh9qwVjeO94KQn6FIwFIpj15UOwv98tLpzjCI3D  4QYRViukwKBgF2kNSRJQziCO5mFfuWpLoL9F0rcqiI8kcS5AthabPOUTnJv1gWHPpua/yuErGZKVFh  iYZ0+aNchIQzIJUWSPyvy9xV8S  +V9LLKRadQtk5bxn6Y1bO9JOFhPP7VwaA5y6dMeyzUSXrbEMWEpRrsxijWbrkIJeUFaezaLnjTeBzJo',
            'zfb_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAu8JUFelJ6t39g78pXLmupT0RJQDX/Ngzav  qpX6GlQnZEmib7/gEePzN4+2I6I1s3Z5IGBewJ0OJzrMS+WX0LwTGyI  +B8KDqD8LhclryXgAdW9Tmkc+zBDs/HKJEvlL+XaJe37dKHBgZwaYaL5VORH9wZlXh  +U2VhESG7X9aCCK7QfGVZyuLgv7AS3QB/Ty+OMLBlqJK8KDhZ7JAIS5TT1+l/gnN14PJB6yV1NtWf  +Neob3jorIxmqMdlkquoc7WqCCloCEHsNNjmWyFODaNP6AzWl4RVMAkuGP1fC0JUJ6dh8/SJrKlZTB  vQn94KoiRjL1CPP2T8d3yMYG/SxLKWQwIDAQAB'
        ];


        $this->aop    =    new AopClient();
        $this->aop->gatewayUrl             = "https://openapi.alipay.com/gateway.do";
        $this->aop->appId                 =  "2021001105658113";
        $this->aop->appId                 =  $payinfo['zfb_app_id'];
        $this->aop->rsaPrivateKey =   $payinfo['zfb_app_public_key'];
        $this->aop->format                 = "JSON";
        $this->aop->charset                = "utf-8";
        $this->aop->signType            = "RSA2";
        $this->aop->apiVersion = '1.0';
        $this->aop->alipayPublicKey = $payinfo['zfb_public_key'];
    }
    //app端购买
    public function createAppPay($title,$order_number, $total_amount,$pay_type){
        require_once 'aop/request/AlipayTradeAppPayRequest.php';
        //$this->schoolid = $schoolid;
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new AlipayTradeAppPayRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent    =    [
            'subject'            =>    $title,
            'out_trade_no'        =>    $order_number,
            'timeout_express'    =>    '1d',//失效时间为 1天
            'total_amount'        =>    $total_amount,//价格
            'product_code'        =>    'QUICK_MSECURITY_PAY',
        ];
        //商户外网可以访问的异步地址 (异步回掉地址，根据自己需求写)
        if($pay_type == 1){
            $request->setNotifyUrl("http://".$_SERVER['HTTP_HOST'].'/api/notify/alinotify');
        }else{
            $request->setNotifyUrl("http://".$_SERVER['HTTP_HOST'].'/api/notify/aliTopnotify');
        }
        $request->setBizContent(json_encode($bizcontent));
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $this->aop->sdkExecute($request);
        return $response;
    }

    //web端直接购买支付宝扫码支付
    public function createPcPay($order_number,$price){
        require_once 'aop/request/AlipayTradePrecreateRequest.php';
        $request = new AlipayTradePrecreateRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent    =    [
            'out_trade_no'        =>    $order_number,
            'total_amount'        =>    $price,//价格
            'subject'                =>    "商品购买",
            'timeout_express'    =>    '1d',//失效时间为 1天
            'product_code'        =>    'FACE_TO_FACE_PAYMENT',
        ];
        $request->setBizContent(json_encode($bizcontent));

        $request->setNotifyUrl("http://".$_SERVER['HTTP_HOST'].'/web/course/alinotify');
        $result =  $this->aop->execute($request);

        return $result;
    }
    //web端扫码支付
    public function convergecreatePcPay($order_number,$price,$title){
        require_once 'aop/request/AlipayTradePrecreateRequest.php';
        $request = new AlipayTradePrecreateRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent    =    [
            'out_trade_no'        =>    $order_number,
            'total_amount'        =>    $price,//价格
            'subject'                =>    $title,
            'timeout_express'    =>    '1d',//失效时间为 1天
            'product_code'        =>    'FACE_TO_FACE_PAYMENT',
        ];
        $request->setBizContent(json_encode($bizcontent));
        $request->setNotifyUrl("http://".$_SERVER['HTTP_HOST'].'/web/course/convergecreateNotifyPcPay');
        $result =  $this->aop->execute($request);
        return $result;
    }

    /**
     * 中控充值, 购买服务等
     * @author 赵老仙
     * @time 2020/10/30
     * @return false|mixed|\SimpleXMLElement
     */
    public function createSchoolPay($order){
        require_once 'aop/request/AlipayTradePrecreateRequest.php';
        $request = new AlipayTradePrecreateRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent    =    [
            'out_trade_no'        =>    $order['oid'],
            'total_amount'        =>    $order['money'],
            'subject'             =>    $order['title'],
            'timeout_express'     =>    '1d',//失效时间为 1天
            'product_code'        =>    'FACE_TO_FACE_PAYMENT',
        ];
        $request->setBizContent(json_encode($bizcontent));

        $request->setNotifyUrl("http://".$_SERVER['HTTP_HOST'].$order['notify']);
        $result =  $this->aop->execute($request);
        return $result;
    }
}
