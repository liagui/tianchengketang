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
        if($school_id == '') {
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
                'zfb_app_public_key' => 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCidG8qgC//7UuRgbM2ZBdWhv8U4mIW5TceK4jVuCSF/g8/G2ZfxKUa6zziH0+RlPzZqFeL7QYV92UA4bSceXafCVk1Md6t44X9hSuPJx7bacg1FN+Z/D9h8Q80Gnm132y+MBv6MemI0tu93VvCV2nKPHSrLMfhMJs/8fgg1kaMMkbaohr8O0x9H79z1HWSNqzAXgMEU1tqcnfXblVNBncEPx2zj7onDEwdLdFZBjyeQ1Wo0b9VJCJuq3Dz/VC9qCmqqlamjruBrYY9dgnT7P6OG2nU37q+9/6E42qobBEjsiL/1Q+C477lIa67Tkv8jUH2cv+T8om8obOLYvNJqJs3AgMBAAECggEAb0yeLNwOBqUotNPLWGRSqPFsKX6//Tek/4KMCQBT5YkeIPpAtTQgecTYvYL+HJuab/SppjAJj1sjU+tOtjVxU5wwBgXYrgHHdt2Z7kW7Gk/q3pMibnknY46n/+ZzpGsiMVr8j/lKKndsHTRe/VKuI+Qvemb/ugR3GORKPxUO2FUyR6cygUsF1aXSv2b+VZSL6v2jQlNTtmOJz20XXPFt0+w82Uv5hbBswMo0OsgsKu2wEamMW9YBcE7bKfvtKFBFjUKHz8/+CxPqp7ea5PQf56NFdyRr29tFpw59qzNpYR8BjrIwrufc/LckY8dUZA8q6IFvrWZE9wxKrRmbHbB/oQKBgQDOuWlOUAoEX6A/XfImwnrlvaGYmILqGetZqWUtq0CoAEUuARq89uKjRmoIro5XROzKnnp49nZqiMk+gahXIpDS8goEf99YTLbAtvY0gfGq3HIk+cVdYSyjAGUF7pgJsh4UGY6spPHIfxVqq8kMZpMVXdiWO5r0bwq9PTYPDNs7BQKBgQDJLaSRyLy5WCha/tzTD8HKKRwqkFueEsusG+NlA8Q99c1G/ZlNo5jHx7IgQk9aNpf1BlFbRne6jZlVimXPmGopDevNrdvZXw2XOxbLsVzuxxqQZi/9n8zyAKxArrFQjrMguWqg/CMzpyLcTGRWiEvH4riyd/DCXFaR/uBgGilqCwKBgQCENbyNo07kwSvBmxnVhCgJaqBA8bk4c187tsTI0m/Fgna8F2S8WcFU3yHNb6YFVkWCyJxXZHkTZWwfl9jL8YViA/44Jnf3BwkSc3E+36RpvBccYsnBEPb1QLlbc960xL/L5xSCgfNIYU6XLEqmrat/zMdKPdC6Z5IcuVsrgPNs6QKBgAj7XrObkMF9rB+T7WwG8hICj91eoJiIIkvG2voxlttlVArtW6DZwwJ4af2CuGRAt7wa0hsPJF1R4RyulykTlvnKQ1LlhkFIOyUbYEMr4ghPH2J/DXl3XwEXApnIsuXz2Q+G97nESBYQSkAnsPskDq4X80MUk805rivKg14HDP5NAoGAXP9QGkdQvzBtcj3Dmd1C+5kOtxxpdlJqLTZz+NNh/5HedlDRjFCDf0jHIujw/GITtddowjxDPzD6YWiVdTOsmUz3gOC8kjgOafCetUG8d4k9d8Q9uQeO/wh4HJ9hUGDzMvGAXcva4PzSRIJDHRGyBcGuwQwrYRQimYSbpoGmVyM=',
                'zfb_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApKlXwOREoKLqIldF6o7PnZcR262JfrK6q0Le49gw16lzenREOi9zZnORLjTRYhwfWaoNVQC9RFBH4+qtmEh5oO3uSz1ClxgOX/Nuh1pw5MD/PRCeIQ9S0MijrrfCTgsVhCNS05bCzkBmMmBkS+4yaSUXt6jvph0oOl+GuXBRMvuS71wD3XUaq44yeU3zjqz4sAE5lWbP67NVAS8AWI3ltp4ksy2PQ3UC4xELlvHTzSa6D1PjIivZAyoDue+psUfsaaxoC4MQAvyhN3IUeaBEcfAtwAiNkofhg5VipgUzI1btJxnD49Hsm0DJ1HRTiuxKlyLX2amWNHlvlq7d8MMaJQIDAQAB'
            ];
        }


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
