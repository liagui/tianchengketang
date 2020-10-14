<?php
$title     = "商户入驻";
$sub_title = "商户业务查询";
$body_id   = 'nsposmweb_webB1467';

include "./commons/header.php";
include "./commons/menu.php";
$apiurl = Url::DOMAIN2 . str_replace('_', '/', $body_id);

// name,require,default,type
$input = array(
    'apiVersion'   => array('接口版本号', 1, '3.0.0.2', 'input'),
    'memberId'     => array('商户号', 1, $config['MEMBER_ID'], 'input'),
    'reqSerialNum' => array('请求流水号', 1, getOrderId(), 'input'),
);

if ($_POST) {
    foreach ($input as $key => $value) {
        if (empty($_POST[$key])) {
            continue;
        }
        $params[$key] = $_POST[$key];
    }

    $jsonData   = utf8_encode(json_encode($params));
    $checkValue = getSign($jsonData);

    $request = array(
        'jsonData'   => $jsonData,
        'checkValue' => $checkValue
    );
    $result  = http_post($apiurl, $request);

    $resultArr = json_decode($result, true);
    if ($resultArr['respCode'] == 000000) {
        $verify = verifySign($resultArr['jsonData'], $resultArr['checkValue']);
    }
}
?>
<article class="cl pd-20" style="min-height: calc(100% - 103px)">
    <p class="f-20 text-success"><?= $sub_title ?></p>
    <form method="post" class="form form-horizontal" id="form-customquery">
        <div class="row cl">
            <?php
            $i = 0;
            foreach ($input as $k => $v) {
                if (empty($v))
                    continue;
                $i++;
                ?>
                <label class="form-label col-xs-2 col-sm-2" for="<?= $k ?>">
                    <?php if ($v[1]) { ?> <span class="c-red"> * </span> <?php } ?><?= $v[0] ?>：
                </label>
                <div class="formControls col-xs-3 col-sm-3">
                    <?php if ($v[3] == 'input') { ?>
                        <input type="text" id="<?= $k ?>" name="<?= $k ?>" value="<?= $v[2] ?>" class="input-text radius">
                    <?php } else if ($v[3] == 'text') { ?>
                        <textarea class="textarea input-text radius" id="<?= $k ?>" name="<?= $k ?>"><?= $v[2] ?></textarea>
                    <?php } else if ($v[3] == 'select') { ?>
                        <select class="select" size="1" id="<?= $k ?>" name="<?= $k ?>">
                            <?php
                            $select = [];
                            is_string($v[2]) && $select = $config[$v[2]];
                            is_array($v[2]) && $select = $v[2];
                            foreach ($select as $key => $value) : ?>
                                <option value="<?= $key ?>"><?= $value ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php } ?>
                </div>
                <?php if ($i % 2 == 0) {
                    echo '</div><div class="row cl">';
                }
            } ?>
        </div>
        <div class="row cl">
            <div class="col-xs-6 col-sm-7 col-xs-offset-3 col-sm-offset-2">
                <input class="btn btn-primary radius" type="submit" onclick="return checkRequired()" value="&nbsp;&nbsp;提交&nbsp;&nbsp;">
            </div>
        </div>
    </form>
    <?php if (isset($result)) { ?>
        <p class="f-20 text-success">请求：<?= $apiurl ?></p>
        <p><?= $jsonData ?></p>
        <p class="f-20 text-success">签名：</p>
        <p><textarea rows="3" style="width: 100%" disabled><?= $checkValue ?></textarea></p>
        <p class="f-20 text-success">响应：(<?= $verify ?>)</p>
        <?php if (isset($verify) && stristr($verify, 'error') == false) { ?>
            <p><?= $resultArr['jsonData'] ?></p>
            <p class="f-20 text-success">响应签名：</p>
            <p><textarea rows="3" style="width: 100%" disabled><?= $resultArr['checkValue'] ?></textarea></p>
            <p class="f-20 text-success">验签：</p>
            <p><?= $verify ?></p>
        <?php } else { ?>
            <p><?= $result ?></p>
        <?php }
    } ?>
</article>
<?php include "./commons/footer.php" ?>
