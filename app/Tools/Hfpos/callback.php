<?php
include dirname(__FILE__) . '/commons/function.php';
$log = dirname(__FILE__) . '/callback.log';

if (!empty($_POST)) {
    $logData = '####### POST:' . date('Y-m-d H:i:s') . '#######' . "\n";
    $logData .= 'respCode：' . $_POST['respCode'] . ' @@ respDesc：' . $_POST['respDesc'] . "\n";
    //$logData .= 'checkValue：' . $_POST['checkValue'] . "\n";
    $logData .= 'jsonData：' . $_POST['jsonData'] . "\n";
    $logData .= verifySign($_POST['jsonData'], $_POST['checkValue']) . "\n\n";
    file_put_contents($log, $logData, FILE_APPEND);
}
