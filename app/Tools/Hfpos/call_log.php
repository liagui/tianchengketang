<?php
$title     = "汇聚支付 API Demo";
$sub_title = "支付回调日志";
$body_id   = 'call_log';
include "./commons/header.php";
include "./commons/menu.php";

$log = dirname(__FILE__) . '/callback.log';
?>
<article class="cl pd-20" style="min-height: calc(100% - 103px)">
    <p class="f-20 text-success"><?= $sub_title ?></p>
    <p><pre><?=file_get_contents($log)?></pre></p>
</article>
<?php include "./commons/footer.php" ?>
