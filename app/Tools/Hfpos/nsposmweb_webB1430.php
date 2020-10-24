<?php
$title     = "商户入驻";
$sub_title = "企业商户入驻";
$body_id   = 'nsposmweb_webB1430';

include "./commons/header.php";
include "./commons/menu.php";
$apiurl = Url::DOMAIN2 . str_replace('_', '/', $body_id);

// name,require,default,type
$input = array(
    'apiVersion'         => array('接口版本号', 1, '3.0.0.2', 'input'),
    'agentId'            => array('代理商号', 1, '', 'input'),
    'opTellerId'         => array('代理商操作员', 1, '', 'input'),
    'merchName'          => array('商户注册名称', 1, '', 'input'),
    'provId'             => array('经营地址所在省', 1, '', 'input'),
    'cityId'             => array('经营地址所在市', 1, '', 'input'),
    'areaId'             => array('经营地址所在县', 1, '', 'input'),
    'merchAddr'          => array('商户经营详细地址', 1, '', 'input'),
    'merchShortName'     => array('商户简称', 1, '', 'input'),
    'businessShours'     => array('营业开始时间', 1, '09:00', 'input'),
    'businessEhours'     => array('营业结束时间', 1, '22:00', 'input'),
    'tellerId'           => array('管理员账号', 1, '186565456562', 'input'),
    'isSendMes'          => array('发送短信通知商户', 1, array('1' => '是', '0' => '否'), 'select'),
    'contactName'        => array('商户联系人', 1, '王小丫', 'input'),
    'contactIdType'      => array(
        '联系人证件类型', 1, array(
            '01' => '身份证', '02' => '护照', '04' => '军官证',
            '03' => '港澳台通行证', '05' => '回乡证',
            '06' => '工商登记号', '09' => '其他'
        ), 'select'
    ),
    'contactIdNo'        => array('联系人证件号码', 1, '', 'input'),
    'contactIdValidType' => array('发送短信通知商户', 1, array('1' => '非长期', '0' => '长期'), 'select'),
    'contactIdSdate'     => array('联系人证件有效期开始日期', 1, '20180115', 'input'),
    'contactIdEdate'     => array('联系人证件有效期开始日期', 0, '20180115', 'input'),
    'contactTelno'       => array('联系人手机', 0, '186565456562', 'input'),
    'contactEmail'       => array('联系人邮箱', 0, '186565456562@139.com', 'input'),
    'csTel'              => array('客服电话', 0, '186565456562', 'input'),
    'isPrivate'          => array('发送短信通知商户', 1, array('0' => '对公', '1' => '对私'), 'select'),
    'bankActName'        => array('结算账户名', 0, '', 'input'),
    'bankActId'          => array('结算账号', 0, '', 'input'),
);

if ($_POST) {
    $params = array();
    foreach ($input as $key => $value) {
        if (empty($_POST[$key])) {
            //continue;
        }
        $params[$key] = $_POST[$key];
    }
    $params["goodsDesc"] = urlencode($params["goodsDesc"]);
    $params["remark"]    = urlencode($params["remark"]);

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
