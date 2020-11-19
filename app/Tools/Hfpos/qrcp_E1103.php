<?php
namespace App\Tools\Hfpos;
class qrcp_E1103{
    public function Hfpos($data,$filePath,$key){

    include "commons/function.php";
    $apiurl = 'https://nspos.cloudpnr.com/qrcp/E1103';
        $jsonData   = utf8_encode(json_encode($data));
        $checkValue = getSign($jsonData,$filePath,$key);
        
        $request = array(
            'jsonData'   => $jsonData,
            'checkValue' => $checkValue
        );
        $result  = http_post($apiurl, $request);

        $resultArr = json_decode($result, true);
        return $resultArr;
        // if ($resultArr['respCode'] == 000000) { //验签
        //     $verify = verifySign($resultArr['jsonData'], $resultArr['checkValue']);
        // }
    }
}
?>
