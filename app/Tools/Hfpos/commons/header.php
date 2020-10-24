<?php
require_once dirname(__FILE__) . "/../config/config.php";
require_once dirname(__FILE__) . "/../config/Url.php";
require_once dirname(__FILE__) . "/function.php";

date_default_timezone_set('PRC');
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
    <link rel="stylesheet" type="text/css" href="resources/thirdparty/h-ui/css/H-ui.min.css"/>
    <link rel="stylesheet" type="text/css" href="resources/thirdparty/h-ui.admin/css/H-ui.admin.css"/>
    <link rel="stylesheet" type="text/css" href="resources/thirdparty/Hui-iconfont/1.0.8/iconfont.css"/>
    <link rel="stylesheet" type="text/css" href="resources/thirdparty/h-ui.admin/skin/default/skin.css" id="skin"/>
    <link rel="stylesheet" type="text/css" href="resources/thirdparty/h-ui.admin/css/style.css"/>

    <script type="text/javascript" src="resources/thirdparty/jquery/1.9.1/jquery.min.js"></script>
    <script type="text/javascript" src="resources/thirdparty/h-ui/js/H-ui.min.js"></script>
    <script type="text/javascript" src="resources/thirdparty/h-ui.admin/js/H-ui.admin.page.js"></script>
    <script type="text/javascript" src="resources/common/util.js"></script>

    <script type="text/javascript">
        function fun(a, b, c, d) {
            var divKey = document.getElementById(a);
            var divFile = document.getElementById(b);
            var form = document.getElementById(c);
            if (d == "1") {
                divKey.style.display = "block";
                divFile.style.display = "none";
                form.enctype = "application/x-www-form-urlencoded";
            } else {
                divKey.style.display = "none";
                divFile.style.display = "block";
                form.enctype = "multipart/form-data";
            }
        }
    </script>
</head>
<body id="<?= $body_id ?>">
<header class="navbar-wrapper">
    <div class="navbar navbar-fixed-top">
        <div class="container-fluid cl">
            <a class="logo navbar-logo f-l mr-10 hidden-xs" href="javascript:;">NSPOS API Demo</a>
            <span class="logo navbar-slogan f-l mr-10 hidden-xs">v1.0.0</span>
            <nav id="Hui-userbar" class="nav navbar-nav navbar-userbar hidden-xs">
                <ul class="cl">
                    <li id="Huinav_1"><p>技术支持：xxxxxx</p></li>
                </ul>
            </nav>
        </div>
    </div>
</header>
