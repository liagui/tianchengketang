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
        if($school_id == ''){
            $payinfo = [
                'zfb_app_id' => '2021001105658113',
                'zfb_app_public_key' =>'MIIEowIBAAKCAQEAqi3W8s2Pz9oYja5nkVKlCkaX9vsEIrBimVhgH/cPGjLKcKKy98QRgSPTaG3zFS8dxDYzEB1RDKjUS2myaabXyuN8qoMj5UyczDxSKWRKiBpOUZ75N8rIGl8AM+reufu7ga1YnZcz8rscTWG1TAF9rAtQS5cYLQF02lXtLUkFPWwqmLfGvh1q9rW0BgcLnD0r38HsMFxj6ROpa4Z/mk6b3Vf+HZ+a46Z5NpymyIJbdt7xIG+0Uy0ctOKcs+YWXkmRYMHHBse6KHjzbgIx246IN7Paix4C5vkOsd4Hbc5Evx1sxczi7yYLFMv1kev6QJiYraZ38tyZURyWIy0Coi5UXQIDAQABAoIBAEihG7WwaYop6IS/RFBPV0SVcFHmO5Oad9o+T3gU9wsVVjTQG1WHBnl5Esbk9fO6khelkhF0kZy3iTNOPui8XiinAhO7uFwqYFkB/YbQ2MZRg89t66sWDmTC2tFNkhUKDLKBiupnF7KmjKOx6bAwirQcd/5q09SRZI+yUHEdUvEtP2+fx8POWSvkz5cuJKusaD4pSzE7f9s2F0G8gF+557i+8aVZnJQWI0JXh5w6UpnltUusaBfsw7MFixF3CJCXA2HiIJM0ikfVQKPm2m6ASWcur2PWblYcixeGe83E7iuBzIosdIKM+uSL9hNWBGcwL4SElb72HTFfnNrlxhuy4gECgYEA6vCpnqjzQnPlKpv53VE8xOZrolxUV24vdRWZKCteGtxfDHS2gxtswszeFuRs9hmEYZBiHnsNHpDM4IGq15x3wx96KQo795U/mM7Ixx1GTEw6oZiGxeF7MfjSV3yHL+kFBAuDP1cvSJO8r+TdWYr3dUVeO4UOOZ78SevPspkToZ0CgYEAuW8PR17JpMPtQzaiXuCCdl0RmCuUWV/MzlJXa35xB9pqUtcQpr/fJM7fxLQ768wGYcloCLm2Q71hcfV5oSIlY1f7m/XyIM64euuZR2bQD3q2P0bHUyp6ibUh18XHCUNqQHTKoWNEfUJpOAbTW4XK0CX2Bj/TDv4H7mWUZwgpYcECgYAUIetnHTM7TpMkw5j1zjBW7yfqEd9oXpjSf7dQKec2hgvfFWFOetsnFkcxzwFHVYhyk9zUn9bP97iWxIXPVCkvH1NokOfyn2eDwLST235aq22ay2dBLcFQ1vGvbYxoHp+/aP0mQGJc5cwVhpcxRSdPdVJN52kApw8Xho2V0GhOQQKBgQCJ8EqOTb1z+mcRW5/XMez6fWrsJmbZQQFJ7UioZstQCzKSYvc5A3vLlrQwT95PHlsU/MyNyRADPeox6mfK7Gqhhr5dGsw9iWkDzyQbUCivixns4gq+G9hBfeMp7i6L/oEYZ4igGwbEotVAXxt0docS5Voo9etbuK5PsXJ+XjziQQKBgDsSlIOiSqd1GnHiOOAKtfxQ9MNZZIgNbKCSAnj8sZXULXiBiQub3ITv5Sapt0VNQf9c3tpieRLoSZM6DomzyeEuakm5mZ9/2IF0Ngv3pE9fv5MaZBBcG3XaJzwCX9NOZtxHdaW2UdYNNCBKJ+YHHNHbhfOvSMQys/yk9MnfBgtk',
                'zfb_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAlTAdFGs8uzPYG3akYT1qs3gEFtjkuRIjP2i7FHUiF52/FVTSzOiYwy9n4qQYovyP/lKxtFWTlKMZfjy1G8EYJBbcb/5dIdDbgm40yaactPaeGkAvykzw5az0PhYTUFJ7PSewZyTJeqETT8ROpuIY5rxgNVHciASiNvrSOMudHfUtqvS7mUPX/Kcpl9q0ryW6BJUIb5SnFouVmh0x6ZAyb+cXVqPXrBTLlQucT3RKuvR+zMkT9IeFFn9fIsCBGhVg8eHfacKUjOWT00CILyoLk6rIZF+PRDX32kvxLKAlfq1puupT2BZxDpH3+LvcMj0Cpl0jmXylEqAxM6qh5+sdjwIDAQAB'
            ];
        }else{
            $payinfo = PaySet::select('zfb_app_id','zfb_app_public_key','zfb_public_key')->where(['school_id'=>$school_id])->first();
        }
//        $payinfo = [
//            'zfb_app_id' => '2021002106695376',
//            'zfb_app_public_key'=>'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCQROo80Jp+0Qf2rS2UTttbtWF+vioHjIgNSFBJ9WWKpeC5Tp8PpVncCg0b/iPNr8C1XHh7dWXmPxDsVvNmo1XyvHydhn8+eHBY5V7bWtjchbL+8M1RfbJaDbk7QMpXDG5kxeQDNvzDiinUr9URU2tzsm1AqxK/5BoApeQZP8r+oEZE7UZNKlv8tXYU81hgwvzxEEBzQ+rgqqeMukTQEPJ5Pf4sdw7gfqYTzj0gbRRSnJPMmhoQVgSyOCRYc64EUMk0bDoZvECdyNuxiDr+7PCcU8gEXpT92bjIHA3WDsAx6ENaum+5f0Z6ReNC2SBAWZMxFK1NW9VowJA6QYnulWABAgMBAAECggEAHvJJdKYindVk6esX/do0f6WWtkNAbMIeZQr//f1fvK/8VoOg5xBHCEY3rauELpjms4CUb5ctNoiMrsWwDHI4+4qnCHtTTCk1oDwDbvY70oHzdXBd+n8GBP8wnp81SBroi0FPucjPy3oVowPcpozIXdcGTHrl2LerRDxRg4EFoDyYIQTFYP3fJuJy8e1UvVVXnuoj1p7BtuBEFa/SUCFOnv5DY3iNN8m/JFjOqFSTMQxn5kZQAHM5yC7Cs/652MqTW322rBFMeCofAo+oC0i1k3MiBunfHgNAl076J+ltkeGNBgcvmDqguccjIkGrumMctsyBab9EtFR7KYSmGXaFqQKBgQD1zi4vutSidLWKtNHtnMvvcjmMbjN3XbsgVByJrh7NKYT14qew4wNnsK+QJLkVPRa5aREJAptPc+nyGGuHGUJF4cj7HTonxFVu8KzuIcC/Un3RD+N0GyfxDpVmGZaP/Yru7QJCe99Gnx8bJ1cE96y1LmXH7c13liee78GNpmnmjwKBgQCWQK6VGdU6/hUVN0L8EkSJy0ot1SkQvuJc5r6VEvKRsYhCIQUl0tC+AMkIYMi6eYEsiom3l0RyPYfezAGiuGyoSddAaSa1SRGv5oDbZZcbit63EpDxnR/eVOdl3yZAY0pNQuvmmHQrCx6uAUVSKyjJSe+chw9cHkH6wqeeNKMYbwKBgQCxBcHhge3s6ZxsvniJZWjBk0O6zQqnpotDZw/+X7WzD3nAE3GtRHCJVoe5iZj5oLsi4HXyRTxQ5ivPvKKD8z71UPwLTSs6xHy6nv4LqadEokYWMBkg3wXO5y/VDgyOuow6Mdp0wv0zwRkH1zcmTrDE8xs99xcITs1N+2ErgHO4JQKBgEhKCHTt/9wpIKWbd9vlQhp1t1PDycUnwarmzWzVt+UG4ELItjxTaGDx6cbhIdIt4Us3wDiXS7QXDIbR6juKtaRmGmz/6kKwTBUwNnDYONJkhvDXuuq0KQAEI2ys5y91VetENlE0qjeHWxRmwh5da3sk2maZyHcOi7oE6zY/pvR7AoGBAOhP1DbfLrIiDHqOWEBqwCAYYGFGn7CCRjROaVPJo7/vsjs5QkMIufb1/P9CBaAp4QVQGfxyWF1h5P7nNmYeiCUcoPmIdoD0hyhtPy8qQznJlGxOTvmN1MQXN3zGH8yV8L+CNjU9stc437ce9ifnrTCeZwcxyFK5SeVQ9Bxi1/v4',
//            'zfb_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAs1kpU9Uo1HUBhssqe6Fgf/NWumCHZnsJQ9Kdc1egV7PnPDBiaMIXQidnZihaSS2+T6NWffexaANiXVxZMNHaW6OgC3CXjmxBk5+St+/tebi/J9LYf0sGwThHZyFl6C1IqWeMQBNBGn/6x5/bico+4VfTxReSG7ttvVtYWb7mY9x64jwU2PGNkkiTVUfBhDq9C9dv0hfaKuFwqV0XQLs3qj17Nw5J1VnehfN7vlUi7lbFpWkr/F+aVyIpAIVchJBV40pI1HRXLoseUaM4TOawDpr+HyZ2uMdFyY0Y7pdxzrFQiytkDhvalrP3oLcJc5TP8pM8/0wW/tsp1pcHZs6N5QIDAQAB'
//        ];


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
