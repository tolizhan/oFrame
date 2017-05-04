<?php
$index = array('_' . OF_URL . '/att/sso/css/login.css');
//加载外部css
isset($_GET['css']) && $index[] = $_GET['css'];

$this->head(array(
    'js'  => array('_' . OF_URL . '/att/sso/js/login.js'),
    'css' => &$index
));

echo '<script>var ofBaseSsoLogin = ' .of_base_com_data::json($_GET). '</script>';
?>
<div class="of_sso-login_bgBlock"></div>
<div class="of_sso-login_bgTip" id="tipBar"></div>
<span class="of_sso-login_func" id="loginBlock">
    <div class="of_sso-login_func_block">
        <form action="" onsubmit="ofBaseSsoLogin.submit(); return false;">
            <table>
                <thead>
                    <tr>
                        <td>账号 </td>
                        <td>
                            <input name="name" onfocus="ofBaseSsoLogin.focus(true, this)" onblur="ofBaseSsoLogin.focus(false, this)">
                        </td>
                    </tr>
                    <tr>
                        <td>密码 </td>
                        <td>
                            <input name="pwd" type="password">
                        </td>
                    </tr>
                </thead>
                <tbody id="extends" style="display: none;">
                    <tr>
                        <td>提示 </td>
                        <td>
                            <input id="question" name="question">
                        </td>
                    </tr>
                    <tr>
                        <td>答案 </td>
                        <td>
                            <input name="answer">
                        </td>
                    </tr>
                    <tr>
                        <td>昵称 </td>
                        <td>
                            <input id="nike" name="nike">
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td>
                            <img id="captcha" class="of_sso-login_func_captcha" src="<?php echo OF_URL; ?>/index.php?a=captcha&amp;c=of_base_com_com&amp;key=of_base_sso_main&amp;height=25" title="点击刷新" onclick="this.src = (this.backupSrc || (this.backupSrc = this.src)) + '&amp;t=' + new Date().getTime()">
                        </td>
                        <td>
                            <input name="captcha" maxlength="4" onfocus="ofBaseSsoLogin.focus(true, this)">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="of_sso-login_func_button">
                            <?php if (of::config('_of.sso.openReg')) { ?>
                            <a href="" onclick="ofBaseSsoLogin.func('reg', this); return false;">注册</a>
                            <?php } ?>
                            <a href="" onclick="ofBaseSsoLogin.func('login', this); return false;">登录</a>
                            <a href="" onclick="ofBaseSsoLogin.func('find', this); return false;">修改</a>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <input type="submit" style="position:absolute; left: -10000px;">
        </form>
    </div>
</span>