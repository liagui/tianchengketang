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
//        if($school_id == '') {
//            $payinfo = [
//                'zfb_app_id' => '2021001105658113',
//                'zfb_app_public_key' =>'MIIEowIBAAKCAQEAqi3W8s2Pz9oYja5nkVKlCkaX9vsEIrBimVhgH/cPGjLKcKKy98QRgSPTaG3zFS8dxDYzEB1RDKjUS2myaabXyuN8qoMj5UyczDxSKWRKiBpOUZ75N8rIGl8AM+reufu7ga1YnZcz8rscTWG1TAF9rAtQS5cYLQF02lXtLUkFPWwqmLfGvh1q9rW0BgcLnD0r38HsMFxj6ROpa4Z/mk6b3Vf+HZ+a46Z5NpymyIJbdt7xIG+0Uy0ctOKcs+YWXkmRYMHHBse6KHjzbgIx246IN7Paix4C5vkOsd4Hbc5Evx1sxczi7yYLFMv1kev6QJiYraZ38tyZURyWIy0Coi5UXQIDAQABAoIBAEihG7WwaYop6IS/RFBPV0SVcFHmO5Oad9o+T3gU9wsVVjTQG1WHBnl5Esbk9fO6khelkhF0kZy3iTNOPui8XiinAhO7uFwqYFkB/YbQ2MZRg89t66sWDmTC2tFNkhUKDLKBiupnF7KmjKOx6bAwirQcd/5q09SRZI+yUHEdUvEtP2+fx8POWSvkz5cuJKusaD4pSzE7f9s2F0G8gF+557i+8aVZnJQWI0JXh5w6UpnltUusaBfsw7MFixF3CJCXA2HiIJM0ikfVQKPm2m6ASWcur2PWblYcixeGe83E7iuBzIosdIKM+uSL9hNWBGcwL4SElb72HTFfnNrlxhuy4gECgYEA6vCpnqjzQnPlKpv53VE8xOZrolxUV24vdRWZKCteGtxfDHS2gxtswszeFuRs9hmEYZBiHnsNHpDM4IGq15x3wx96KQo795U/mM7Ixx1GTEw6oZiGxeF7MfjSV3yHL+kFBAuDP1cvSJO8r+TdWYr3dUVeO4UOOZ78SevPspkToZ0CgYEAuW8PR17JpMPtQzaiXuCCdl0RmCuUWV/MzlJXa35xB9pqUtcQpr/fJM7fxLQ768wGYcloCLm2Q71hcfV5oSIlY1f7m/XyIM64euuZR2bQD3q2P0bHUyp6ibUh18XHCUNqQHTKoWNEfUJpOAbTW4XK0CX2Bj/TDv4H7mWUZwgpYcECgYAUIetnHTM7TpMkw5j1zjBW7yfqEd9oXpjSf7dQKec2hgvfFWFOetsnFkcxzwFHVYhyk9zUn9bP97iWxIXPVCkvH1NokOfyn2eDwLST235aq22ay2dBLcFQ1vGvbYxoHp+/aP0mQGJc5cwVhpcxRSdPdVJN52kApw8Xho2V0GhOQQKBgQCJ8EqOTb1z+mcRW5/XMez6fWrsJmbZQQFJ7UioZstQCzKSYvc5A3vLlrQwT95PHlsU/MyNyRADPeox6mfK7Gqhhr5dGsw9iWkDzyQbUCivixns4gq+G9hBfeMp7i6L/oEYZ4igGwbEotVAXxt0docS5Voo9etbuK5PsXJ+XjziQQKBgDsSlIOiSqd1GnHiOOAKtfxQ9MNZZIgNbKCSAnj8sZXULXiBiQub3ITv5Sapt0VNQf9c3tpieRLoSZM6DomzyeEuakm5mZ9/2IF0Ngv3pE9fv5MaZBBcG3XaJzwCX9NOZtxHdaW2UdYNNCBKJ+YHHNHbhfOvSMQys/yk9MnfBgtk',
//                'zfb_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAlTAdFGs8uzPYG3akYT1qs3gEFtjkuRIjP2i7FHUiF52/FVTSzOiYwy9n4qQYovyP/lKxtFWTlKMZfjy1G8EYJBbcb/5dIdDbgm40yaactPaeGkAvykzw5az0PhYTUFJ7PSewZyTJeqETT8ROpuIY5rxgNVHciASiNvrSOMudHfUtqvS7mUPX/Kcpl9q0ryW6BJUIb5SnFouVmh0x6ZAyb+cXVqPXrBTLlQucT3RKuvR+zMkT9IeFFn9fIsCBGhVg8eHfacKUjOWT00CILyoLk6rIZF+PRDX32kvxLKAlfq1puupT2BZxDpH3+LvcMj0Cpl0jmXylEqAxM6qh5+sdjwIDAQAB'
//            ];
//        }else{
//            $payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>$school_id])->first();
//        }
            $payinfo = [
                'zfb_app_id' => '2021002112680289',
                'zfb_app_public_key' =>'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCBQbMuBHHML6bttRbyjgpghIqjMPtA/3TViJUcTKOq+1n0fOe0MP2hmCKGNWivdZNAAkGSjYSpjJukfK0ZNpwJA0hL4WZtV79MkH/swlVKSCXf/qlq/X2UJhN21fWSMMQk1xoZ+yqnmLe+Ddg7NHocf0FPCSwjQlfgTxRW+5oACvduM4G6wv9FeKdpqf9kgLHaeiyypf1qZgjNZj7dKOMW8vT4sj+4H+jEcHyOEMzB8VD08FpckButycxpfckXqKWBGMSElevxzYMC4bOAtpNYjtWJm8nd7INaYWnSf67+Nehadqr5F5WmqN+63vKINKFgyoiQ0hXEx18EBDbgYKMJAgMBAAECggEAKr216UaQ9qrfMQVLpEMxzT6mtVXSD2kpkh6K6AOzrytUUJMo7x2wEMpEyEpK5pIjcewNEM/5CTO0l8yeVJMJP7W1J1pOS2PTnxa9JmYQW/LFi28jSHywFNgOYaJYhNHZWjFCsUqrjSXjxFXBpboxktDFoMPws2wRsNuVZznlb5O4Tla/kpgSXajEC/Mtp93kzbcUhNy6dNxNj6tWj7Up5KmiY8t1QvNKNSaPL/wyh7SDBBuoJribMmWub0D02dLTUQIarB7TUKQnuhOPIvEGnfg30kOK619AurvoAryD1hg/XcE+X7yTY+Tryq6LwO+dtzQRFI1Q81ogkahVrr3ggQKBgQC/awVFeCN8R23ihVYC104mgI6N6DDjFYm6tJXw1LMQ+JpaYwSPiSFvMzj5TnhFQfl6VfIKbHBfOtl5cnvkJi+9TE/vWEwYA/Aozu3mLNDCzOqRKojsHCufz2At490Mr6e9mu3H1/5XdZj4ABHwYlSWQd1yC0R/bYm5jVxxU9L4kQKBgQCs3buRQ0DdwUY6Pu5pOBD5wqfw0jc17nrdxGksph69aZCxAzXqUotzGa8CXKa7+XbtGAuZTd/+eM+vytiAEAlR0QrM5rczENNdsmVDfMrf1OJk7CDx+E3RJnfvMvGb7rQX2Onn8wE5vpEPiE7XFXNhK0LxES2l1OIYqqhMmz3++QKBgCu+iGDc2LcxF7KZqBNUIt73qBxUAMAUjvolBNMZuKZbOeMIF+W5f/OKsC2BOkkRj7Lea0boizGoIET53chA1c4b33SiKBmg5MRerc1ZVof+DmkIYGwc7/dAjAlQtpaMmffhrQ9rlAtmq4OZeIAH6K7B9Jp4arn4TW4jGQopDuCRAoGAWEUtNyLpZ5L+wlVKNm5LtM2iJgWh7acmFEHsFsGI5t2V5d2m1U1rKc4OoJ0hAtGC2dSyb4jlfTLBkpl47gPCToX5BuI04B2SYMDsloaNrbguFqzAse5CBi+nhHw2BZLhYWABKNDcalkWB6Fp6MhqT07g8un7rjdDcKcMI3oOMTkCgYEAqL77EByGbP3gw7rje39AOmUbI9SJ6b2YQaVjXgdSa7l6+n4Nj4kTjSh24xisAHhWGHOyT8DyCxcejpOJt6zmZD/PW1seic3V5AYR7iMxsG8E/riyzBzhflIrSRndBG7HeSb8Pqlcd+PMm9P63BJaspCRQwCTW0qlYMXkAEBpBS4=',
                'zfb_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApKlXwOREoKLqIldF6o7PnZcR262JfrK6q0Le49gw16lzenREOi9zZnORLjTRYhwfWaoNVQC9RFBH4+qtmEh5oO3uSz1ClxgOX/Nuh1pw5MD/PRCeIQ9S0MijrrfCTgsVhCNS05bCzkBmMmBkS+4yaSUXt6jvph0oOl+GuXBRMvuS71wD3XUaq44yeU3zjqz4sAE5lWbP67NVAS8AWI3ltp4ksy2PQ3UC4xELlvHTzSa6D1PjIivZAyoDue+psUfsaaxoC4MQAvyhN3IUeaBEcfAtwAiNkofhg5VipgUzI1btJxnD49Hsm0DJ1HRTiuxKlyLX2amWNHlvlq7d8MMaJQIDAQAB'
            ];


        $this->aop    =    new AopClient();
        $this->aop->gatewayUrl             = "https://openapi.alipay.com/gateway.do";
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
    public function createPcPay($order_number,$price,$title){
        require_once 'aop/request/AlipayTradePrecreateRequest.php';
        $request = new AlipayTradePrecreateRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent    =    [
            'out_trade_no'        =>    $order_number,
            'total_amount'        =>    $price,//价格
            'subject'             =>    $title,
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
