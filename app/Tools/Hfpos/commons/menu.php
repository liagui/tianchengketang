<aside class="Hui-aside">
    <div class="menu_dropdown bk_2">
        <dl>
            <dt><i class="Hui-iconfont">&#xe6cb;</i> 商户入驻<i class="Hui-iconfont menu_dropdown-arrow">&#xe6d5;</i></dt>
            <dd>
                <ul>
                    <li id="lnk_nsposmweb_webB1430"><a href="nsposmweb_webB1430.php" title="企业商户基本信息入驻">企业商户入驻</a></li>
                    <li id="lnk_nsposmweb_webB1429"><a href="nsposmweb_webB1429.php" title="商户业务开通">商户业务开通</a></li>
                    <li id="lnk_nsposmweb_webB1467"><a href="nsposmweb_webB1467.php" title="商户业务查询">商户业务查询</a></li>
                </ul>
            </dd>
        </dl>
        <dl>
            <dt><i class="Hui-iconfont">&#xe6cb;</i> 扫码支付<i class="Hui-iconfont menu_dropdown-arrow">&#xe6d5;</i></dt>
            <dd>
                <ul>
                    <li id="lnk_qrcp_E1103"><a href="qrcp_E1103.php" title="支付接口">支付接口</a></li>
                    <li id="lnk_qrcp_P3009"><a href="qrcp_P3009.php" title="支付接口">订单查询</a></li>
                    <li id="lnk_qrcp_closeTrans"><a href="qrcp_closeTrans.php" title="关单接口">关单接口</a></li>
                </ul>
            </dd>
        </dl>
        <dl>
            <dt><i class="Hui-iconfont">&#xe6cb;</i> 小程序支付<i class="Hui-iconfont menu_dropdown-arrow">&#xe6d5;</i></dt>
            <dd>
                <ul>
                    <li id="lnk_qrcp_E1113"><a href="qrcp_E1113.php" title="支付接口">支付接口</a></li>
                </ul>
            </dd>
        </dl>
        <dl>
            <dt><i class="Hui-iconfont">&#xe6cb;</i> app支付<i class="Hui-iconfont menu_dropdown-arrow">&#xe6d5;</i></dt>
            <dd>
                <ul>
                    <li id="lnk_qrcp_appPay"><a href="qrcp_appPay.php" title="支付接口">支付接口</a></li>
                    <li id="lnk_qrcp_P3009"><a href="qrcp_P3009.php" title="支付接口">订单查询</a></li>
                </ul>
            </dd>
        </dl>
        <dl>
            <dt><i class="Hui-iconfont">&#xe6cb;</i> 台拍支付<i class="Hui-iconfont menu_dropdown-arrow">&#xe6d5;</i></dt>
            <dd>
                <ul>
                    <li id="lnk_qrcp_E1101"><a href="qrcp_E1101.php" title="支付接口">支付接口</a></li>
                    <li id="lnk_qrcp_P3009"><a href="qrcp_P3009.php" title="订单查询">订单查询</a></li>
                    <li id="lnk_nsposmweb_webB7019_v2"><a href="nsposmweb_webB7019_v2.php" title="公众号配置查询">公众号配置查询V2</a></li>
                </ul>
            </dd>
        </dl>
        <dl>
            <dt><i class="Hui-iconfont">&#xe6cb;</i> 回调通知<i class="Hui-iconfont menu_dropdown-arrow">&#xe6d5;</i></dt>
            <dd>
                <ul>
                    <li id="lnk_call_log"><a href="call_log.php" title="查看回调日志">查看回调日志</a></li>
                  </ul>
            </dd>
        </dl>
    </div>
</aside>
<div class="dislpayArrow hidden-xs"><a class="pngfix" href="javascript:void(0);" onClick="displaynavbar(this)"></a></div>
<script type="text/javascript">
    //菜单展开样式
    $('#lnk_' + $('body').attr('id')).parent().parent().css('display', 'block');
    $('#lnk_' + $('body').attr('id')).addClass('current');
</script>
<section class="Hui-article-box">
    <nav class="breadcrumb"><i class="Hui-iconfont"></i> <a href="/" class="maincolor">首页</a>
        <span class="c-999 en">&gt;</span>
        <span class="c-666"><?= $title ?></span>
        <span class="c-999 en">&gt;</span>
        <span class="c-666"><?= $sub_title ?></span>
        <a class="btn btn-success radius r" style="line-height:1.6em;margin-top:3px" href="javascript:location.replace(location.href);" title="刷新"><i
                    class="Hui-iconfont">&#xe68f;</i></a>
    </nav>
    <div class="Hui-article">
