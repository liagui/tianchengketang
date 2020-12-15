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
                'zfb_app_id' => '2021002114638191',
                'zfb_app_public_key' =>'MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCY+cMfDWE/k58XPWw1percg07j2nw4Jra6lFEn7j2i7TSxApElMgaq9EDEISdff53jTxZUSh925ZeR4xC8k/nyebb4CAdxsub7cj4cfUHuI3V7rGvHINIUS++WxI+LYmBsp9f/ASOWDhilclOFMrAlDjgtMb2VCrtCgYagtRvl5DPqIo2O/+c5iksl5bCo/5jA4BhK0Dv6QteFuLmvRNYZ8d6Hj9b+TvHrfKl6usebaeEkfxduCNaCdkSJYSUHDrhZKO3e76OiEjgkoY6S7n9apmwYfZaXyQzGJFbmUBN+yUJFtJ7av06iUgL/xiyHe72qXHe3ga6Hm5Cg+pfB4AVLAgMBAAECggEAFtg9rlZ2LmNiP7s5fK2BbFrjnh1TraMCaMybbgYjCEC6PLDwcgiNfFwri96TnaxWLMrzv7SuDDhvMSciqbbDfdV1YIb6bZ1U5zoBUonWHbUH52cxVTRuiv9oETDbaupPEgris788zDzgq1xuYpbWPUJpWz5xXF+U5XKoYzmIqAT4EDcCWsKsoow1P9kGdkJIUd7NhIAnv0liMrMCbnoteiEQmwN5qvmEaToo1tlFCRwfHAJGqIRRRrhAYAKjB/Ay1YvmDC1+BlpuJvyM/ja/kNU6BIHPWTJokM/YG6WacdifcsuabfUneacpxs0IQ8LquiwSAh2rxUgXv8Ed4caOaQKBgQDSZrmWlGWLBDJ6WA52gfCWz6IasJglCHF64Wk/NoMhH0bEdkQfowC5lX4IFztI1xMC3TRbgWT5vziP0WJi2kC/I3oYuLQ97hBml5sgCjH/Usr+09FN0dC1k3PYjTqVYuAcowS8yRgZSKLu2W7T02+0bMUyWNbOEWunhVA8fQj0TwKBgQC6IQFDT75k9xfXL1juroh+DdQmD0E2RPEannjUVb4SglTQ+4E3R41TwVrVJ0gLiOf7fhtzXq+WDxU9BEuUuZo9l1WluXTb9rIoJXNZxYGS5qSa+MT6+1ZG8try3X+9lKZ9q2Vhs3+Rqfg6oLxJA7DW6XLbAoznV5zuycVcNqAURQKBgEI3A/cosqBCbORl6/9Nu3qq05hUtT0KYDRM1kOHk1MMNchEdAIE2YiOTICdVxIAZcJD/WNC4JVcoZ9ZRP5ZM4Ez2AwJrOfEkTLISZCWxM9Wrf3uiXNVMbrDMfUX1TUITgGVKDuFiekDHAvRL/jIFOKSTKkgnlFx2BB7exneO8AvAoGAEy9BtRFsG3YIhAqCTR+6O9vrCP07iH90pny9EY3A6KF8Yon52JvMalycoynIWEAFQTvPUoXo7TuiwcZGkA7NYhnOHyXNcLqtxEuP5ynfYCfbwYlwrDnpu6hefvfrqbu/qWZYLcjVlksBpfFZVuFFgfFQAFjz8whpyYcMi6Opvk0CgYA24FRIdeRfUBC+3/1eCj+4jfXJ6MvranqscqzS5lLTPbYI+EeZtriB0mFKVsJ6pE2O8pKFmWA1FoJ+mSJ2pBtKqhIzWvsykLq+0z4RdkiTcwUuQm2Aj6O76daBm7bZC7Q6hjbFSmPxeDhRDEGY2Qb/ulbdmGz6zL3GOigoWFw1nA==',
                'zfb_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAozZdwt4JfFkLgRJg+YXFKcalXa8Dgw12e+sZcgvI8igr6UmuqJUBr7EYFeRwLwjydtaMquN7thsoNL/Mg9iAmkHm4Vo1F8q5rDmoGBc9lC35ZXE2Jow21C+fNnSbL4cCoTMDvgE8qTqgbd3Mj9mO5oRO31tlWRexR38Iy+85E5Xs5CnwBFeD1TD8puPITh9GeA/I+P+PgDg+FPbDDKp4A/FIkjZuKq+Df5UgC1yvQRiSi/dVvOW07kvLQcUxq/OpY6+B1s5fmuMre/nDIniKk4z9Ftz4B2V+3FryDkuPpbpb3+8olpz/MgR/3d4sOOxCMqUTHD41u6efT6S1Rbq3rQIDAQAB'
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
