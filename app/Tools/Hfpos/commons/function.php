<?php

/**
 * POST请求
 */
function http_post($url, $params, $contentType = false)
{
    if (function_exists('curl_init')) { // curl方式
        $oCurl = curl_init();
        if (stripos($url, 'https://') !== false) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $string = $params;
        if (is_array($params)) {
            $aPOST = array();
            foreach ($params as $key => $val) {
                $aPOST[] = $key . '=' . urlencode($val);
            }
            $string = join('&', $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_POST, true);
        //$contentType json处理
        if ($contentType) {
            $headers = array(
                "Content-type: application/json;charset='utf-8'",
            );
            curl_setopt($oCurl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($oCurl, CURLOPT_POSTFIELDS, json_encode($params));
        } else {
            curl_setopt($oCurl, CURLOPT_POSTFIELDS, $string);
        }
        $response = curl_exec($oCurl);
        curl_close($oCurl);
        return $response;
        //        return json_decode($response, true);
    } else if (function_exists('stream_context_create')) { // php5.3以上
        $opts    = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($params),
            )
        );
        $_opts   = stream_context_get_params(stream_context_get_default());
        $context = stream_context_create(array_merge_recursive($_opts['options'], $opts));
        return file_get_contents($url, false, $context);
        //        return json_decode(file_get_contents($url, false, $context), true);
    } else {
        return false;
    }
}

/**
 * 根据原文生成签名内容
 * @param string $data 原文内容
 * @param string $filePath 密钥
 * @param string $key 密钥key
 * @return string
 */
function getSign($data, $filePath = '', $key = '')
{
    //empty($filePath) && $filePath = dirname(__FILE__) . '/../config/key.pfx';
    empty($filePath) && $filePath = dirname(__FILE__) ."/../../../../public".$filePath;

    if (!is_string($data)) {
        return "Error: 待签名不是字符串";
    }
    if(empty($key)){
        return "Error：key错误";
    }

    $strLogCofigFilePath = dirname(__FILE__) . "/cfcalog.conf";
    if (function_exists('cfca_initialize')) {
        $nResult = cfca_initialize($strLogCofigFilePath);
    } else {
        return "cfca_initialize not exist";
    }

    if (0 != $nResult) {
        cfca_uninitialize();
        return "cfca_Initialize error:" . $nResult;
    }

    $strSignAlg        = "RSA";
    $strSignSourceData = $data;
    $strPfxFilePath    = $filePath;
    $strPfxPassword    = $key;
    $strHashAlg        = "SHA-256";

    // Msg PKCS7-attached Sign
    $strMsgPKCS7AttachedSignature = "";

    $nResult = cfca_signData_PKCS7Attached($strSignAlg, $strSignSourceData, $strPfxFilePath, $strPfxPassword, $strHashAlg, $strMsgPKCS7AttachedSignature);

    if (0 != $nResult) {
        cfca_uninitialize();
        return "cfca_signData_PKCS7Attached error:" . $nResult;
    }
    cfca_uninitialize();
    return $strMsgPKCS7AttachedSignature;
}

/**
 * @param string $data
 * @param string $signature
 * @return bool
 * @author newsgao@gmail.com
 * @date   2020/7/18 16:08
 */
function verifySign($data, $signature)
{
    $signature           = base64_decode($signature);
    $strLogCofigFilePath = dirname(__FILE__) . "/cfcalog.conf";
    if (function_exists('cfca_initialize')) {
        $nResult = cfca_initialize($strLogCofigFilePath);
    } else {
        return "cfca_initialize not exist";
    }
    $strSignAlg                   = "RSA";
    $strMsgPKCS7AttachedSignature = $signature;

    // PKCS7-attached-Verify
    $strMsgP7AttachedSignCertContent = "";
    $strMsgP7AttachedSource          = "";
    $nResult                         = cfca_verifyDataSignature_PKCS7Attached($strSignAlg, $strMsgPKCS7AttachedSignature, $strMsgP7AttachedSignCertContent, $strMsgP7AttachedSource);
    if (0 != $nResult) {
        cfca_uninitialize();
        return "cfca_verifyDataSignature_PKCS7Attached error:" . $nResult;
    }
    // 对比签名中的内容是否一致
    if ($strMsgP7AttachedSource != $data) {
        return "cfca_verifyDataSignature_PKCS7Attached error:原始数据和签名中数据不一致";
    }

    // Cert manipulate
    $nCertVerifyFlag          = 4;
    $strTrustedCACertFilePath = dirname(__FILE__) . '/../config/CFCA_ACS_OCA31.cer|' . dirname(__FILE__) . '/../config/CFCA_ACS_CA.cer';
    $strCRLFilePath           = dirname(__FILE__) . "/../config/crl1027.crl";
    #$strCRLFilePath = null;
    $nResult = cfca_verifyCertificate($strMsgP7AttachedSignCertContent, $nCertVerifyFlag, $strTrustedCACertFilePath, $strCRLFilePath);
    //$nResult = cfca_verifyCertificate($strMsgP7AttachedSignCertContent, $nCertVerifyFlag, $strTrustedCACertFilePath,"");
    cfca_uninitialize();
    if (0 == $nResult) {
        return '验签成功';
    } else {
        return '验签失败';
    }
}

//生成订单号
function getOrderId()
{
    return date('YmdHis') . mt_rand(100, 999);
}

