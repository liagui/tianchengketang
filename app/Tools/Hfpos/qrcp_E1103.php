<?php
namespace App\Tools\Hfpos;
class qrcp_E1103{
    public function Hfpos($data){
    echo "123";
    include "commons/function.php";
    $apiurl = 'https://nspos.cloudpnr.com/qrcp/E1103';
        $jsonData   = utf8_encode(json_encode($data));
        $checkValue = getSign($jsonData);
         echo $checkValue;
        $request = array(
            'jsonData'   => $jsonData,
            'checkValue' => $checkValue
        );
        $result  = http_post($apiurl, $request);

        $resultArr = json_decode($result, true);
        print_r($resultArr);die;
//            if ($resultArr['respCode'] == 000000) {
//                $verify = verifySign($resultArr['jsonData'], $resultArr['checkValue']);
//            }
    }
}
?>
