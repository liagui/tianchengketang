<?php
/**
 * Create by newsgao@gmail.com
 * Time：2020/7/20 17:07
 */
/**
 * array $config
 */
require_once dirname(__FILE__) . '/config/config.php';
$request = file_get_contents('php://input');
$request = json_decode($request, true);

switch ($request['do']) {
    case 'openid':
        getOpenid($request);
        break;
    case 'order':
        getOrder($request);
        break;
    case 'pay':
        getPay($request);
        break;
    case 'alipay':
        getAliPay($request);
        break;
    default:
        echo json_encode(array('code' => 2, 'msg' => '操作不存在'));
}

/**
 * @param $request
 * @author newsgao@gmail.com
 * @date   2020/8/4 11:43
 */
function getOpenid($request)
{
    global $config;
    $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=';
    $url .= $config['MINI_APPID'] . '&secret=' . $config['MINI_SECRET'];
    $url .= '&js_code=' . $request['code'] . '&grant_type=authorization_code';
    echo file_get_contents($url);
}

function getOrder($request)
{
    $dir = dirname(__FILE__) . '/order';
    is_dir($dir) || mkdir($dir);
    $file = $dir . '/' . $request['order'] . '.json';
    if (is_file($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(array('code' => 1, 'msg' => '订单不存在'), JSON_UNESCAPED_UNICODE);
    }
}

function getPay($request)
{
    global $config;
    require_once dirname(__FILE__) . "/config/Url.php";
    require_once dirname(__FILE__) . "/commons/function.php";
    $body_id = 'qrcp_E1113';
    $apiurl  = Url::DOMAIN . str_replace('_', '/', $body_id);
    // name,require,default,type
    $input  = array(
        'ordAmt'         => array('订单金额', 1, '0.01', 'input'),
        'apiVersion'     => array('接口版本号', 1, '3.0.0.2', 'input'),
        'payChannelType' => array('支付类型', 1, 'W2', 'input'),
        'appId'          => array('AppId', 1, $config['MINI_APPID'], 'input'),
        'openId'         => array('OpenID', 1, $request['openid'], 'input'),
        'clientIp'       => array('IP地址', 1, $_SERVER['REMOTE_ADDR'], 'input'),
        'merPriv'        => array('私有域', 0, '{"merNoticeUrl":"https://joinpay.xg360.cc/nspos/callback.php"}', 'text'),
        'termOrdId'      => array('订单号', 1, getOrderId(), 'input'),
        'goodsDesc'      => array('商品名', 1, 'aaaa', 'input'),
        'memberId'       => array('商户号', 1, $config['MEMBER_ID'], 'input'),
        'remark'         => array('备注', 0, '', 'input'),
    );
    $params = array();
    foreach ($input as $key => $value) {
        $params[$key] = $input[$key][2];
    }

    $jsonData   = utf8_encode(json_encode($params));
    $checkValue = getSign($jsonData);

    $request = array(
        'jsonData'   => $jsonData,
        'checkValue' => $checkValue
    );

    $result    = http_post($apiurl, $request);
    $resultArr = json_decode($result, true);
    $verify    = verifySign($resultArr['jsonData'], $resultArr['checkValue']);
    if ($resultArr['respCode'] == 000000 && $verify == '验签成功') {
        $resultData = json_decode($resultArr['jsonData'], true);
        echo $resultData['payInfo'];
    } else {
        echo json_encode(array('code' => 1, 'msg' => '错误'), JSON_UNESCAPED_UNICODE);
    }
}

function getAliPay($request)
{
    global $config;
    require_once dirname(__FILE__) . "/config/Url.php";
    require_once dirname(__FILE__) . "/commons/function.php";
    $body_id = 'qrcp_E1103';
    $apiurl  = Url::DOMAIN . str_replace('_', '/', $body_id);
    // name,require,default,type
    $input  = array(
        'apiVersion'     => array('接口版本号', 1, '3.0.0.2', 'input'),
        'memberId'       => array('商户号', 1, $config['MEMBER_ID'], 'input'),
        'termOrdId'      => array('订单号', 1, getOrderId(), 'input'),
        'ordAmt'         => array('订单金额', 1, '0.01', 'input'),
        'goodsDesc'      => array('商品名', 1, '石榴', 'input'),
        'remark'         => array('备注', 0, '0.5Kg', 'input'),
        'payChannelType' => array('支付类型', 1, 'A1', 'select'),
        'merPriv'        => array('私有域', 0, '{"merNoticeUrl":"https://joinpay.xg360.cc/nspos/callback.php"}', 'text'),
    );
    $params = array();
    foreach ($input as $key => $value) {
        $params[$key] = $input[$key][2];
    }
    $params["goodsDesc"] = urlencode($params["goodsDesc"]);
    $params["remark"]    = urlencode($params["remark"]);

    $jsonData   = utf8_encode(json_encode($params));
    $checkValue = getSign($jsonData);

    $request = array(
        'jsonData'   => $jsonData,
        'checkValue' => $checkValue
    );

    $result    = http_post($apiurl, $request);
    $resultArr = json_decode($result, true);

    $verify = verifySign($resultArr['jsonData'], $resultArr['checkValue']);
    if ($resultArr['respCode'] == 000000 && $verify == '验签成功') {
        $resultData = json_decode($resultArr['jsonData'], true);
        echo json_encode(array('code' => 0, 'qr' => 'alipays://platformapi/startapp?saId=10000007&qrcode=' . $resultData['qrcodeUrl']));
    } else {
        echo json_encode(array('code' => 1, 'msg' => '错误'), JSON_UNESCAPED_UNICODE);
    }
}
