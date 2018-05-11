<?php
$this->head(array(
    'js'  => array(
        '_' . OF_URL . '/att/sso/js/main.js',
        '_' . OF_URL . '/att/com/com/paging/main.js',
    ),
    'css' => array(
        '_' . OF_URL . '/att/sso/css/main.css'
    )
));
echo '<script>var ofBaseSsoMain = ' .of_base_com_data::json($_SESSION['_of']['of_base_sso']['mgmt']). '</script>';
?>
<table class="of_sso-main" style="">
    <thead class="of_sso-main_thead">
        <tr>
            <th class="user" id="userTitle" colspan="3">用户帐号</th>
            <th id="baleTitle" colspan="3">角色集合</th>
            <th id="realmTitle" colspan="3">系统帐号</th>
            <th id="packTitle" colspan="3">系统角色</th>
            <th class="edit none" id="funcTitle" colspan="3">系统权限</th>
        </tr>
        <tr>
            <th class="user" colspan="3">
                <input id="userSearchInput" class="of_sso-main_thead_search_input" type="search" placeholder="搜索" onkeyup="ofBaseSsoMain.search('user', event)">
                <a class="of_sso-main_thead_search_button" onclick="ofBaseSsoMain.search('user'); return false;">&nbsp;</a>
            </th>
            <th colspan="3">
                <input id="baleSearchInput" class="of_sso-main_thead_search_input" type="search" placeholder="搜索" onkeyup="ofBaseSsoMain.search('bale', event)">
                <a class="of_sso-main_thead_search_button" onclick="ofBaseSsoMain.search('bale'); return false;">&nbsp;</a>
            </th>
            <th colspan="3">
                <input id="realmSearchInput" class="of_sso-main_thead_search_input" type="search" placeholder="搜索" onkeyup="ofBaseSsoMain.search('realm', event)">
                <a class="of_sso-main_thead_search_button" onclick="ofBaseSsoMain.search('realm'); return false;">&nbsp;</a>
            </th>
            <th colspan="3">
                <input id="packSearchInput" class="of_sso-main_thead_search_input" type="search" placeholder="搜索" onkeyup="ofBaseSsoMain.search('pack', event)">
                <a class="of_sso-main_thead_search_button" onclick="ofBaseSsoMain.search('pack'); return false;">&nbsp;</a>
            </th>
            <th class="edit none" colspan="3">
                <input id="funcSearchInput" class="of_sso-main_thead_search_input" type="search" placeholder="搜索" onkeyup="ofBaseSsoMain.search('func', event)">
                <a class="of_sso-main_thead_search_button" onclick="ofBaseSsoMain.search('func'); return false;">&nbsp;</a>
            </th>
        </tr>
        <tr>
            <th class="user"><input type="checkbox" value="" onclick="ofBaseSsoMain.allBox('user', this.checked)"></th>
            <th class="user">昵称</th>
            <th class="user">账号</th>
            <th><input type="checkbox" value="" onclick="ofBaseSsoMain.allBox('bale', this.checked)"></th>
            <th>包名</th>
            <th>键值</th>
            <th><input type="checkbox" value="" onclick="ofBaseSsoMain.allBox('realm', this.checked)"></th>
            <th>简称</th>
            <th>账号</th>
            <th><input type="checkbox" value="" onclick="ofBaseSsoMain.allBox('pack', this.checked)"></th>
            <th>名称</th>
            <th>键值</th>
            <th class="edit none"><input type="checkbox" value="" onclick="ofBaseSsoMain.allBox('func', this.checked)"></th>
            <th class="edit none">名称</th>
            <th class="edit none">键值</th>
        </tr>
    </thead>
    <tbody class="of_sso-main_tbody">
        <tr id="mainTbodyTr">
            <td class="user" colspan="3">
                <div class="of_sso-main_tbody_td_div">
                    <table id="userPaging" name="pagingBlock" method="of_base_sso_main::userPaging">
                        <thead class="of-paging_wait" name="pagingWait" style="display: none;">
                            <tr>
                                <td colspan="3"></td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr name="pagingItem" key="{`id`}" style="display: none;" onclick="ofBaseSsoMain.item('user', '{`id`}');">
                                <td><input type="checkbox">{`_state`}</td>
                                <td>{`nike`}</td>
                                <td>{`name`}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </td>
            <td colspan="3">
                <div class="of_sso-main_tbody_td_div">
                    <table id="balePaging" name="pagingBlock" method="of_base_sso_main::balePaging">
                        <thead class="of-paging_wait" name="pagingWait" style="display: none;">
                            <tr>
                                <td colspan="3"></td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr name="pagingItem" key="{`id`}" style="display: none;" onclick="ofBaseSsoMain.item('bale', '{`id`}');">
                                <td><input type="checkbox">{`_state`}</td>
                                <td>{`lable`}</td>
                                <td>{`name`}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </td>
            <td colspan="3">
                <div class="of_sso-main_tbody_td_div">
                    <table id="realmPaging" name="pagingBlock" method="of_base_sso_main::realmPaging">
                        <thead class="of-paging_wait" name="pagingWait" style="display: none;">
                            <tr>
                                <td colspan="3"></td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr name="pagingItem" key="{`id`}" style="display: none;" onclick="ofBaseSsoMain.item('realm', '{`id`}');">
                                <td><input type="checkbox">{`_state`}</td>
                                <td>{`lable`}</td>
                                <td>{`name`}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </td>
            <td colspan="3">
                <div class="of_sso-main_tbody_td_div">
                    <table id="packPaging" name="pagingBlock" method="of_base_sso_main::packPaging">
                        <thead class="of-paging_wait" name="pagingWait" style="display: none;">
                            <tr>
                                <td colspan="3"></td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr name="pagingItem" key="{`id`}" style="display: none;" onclick="ofBaseSsoMain.item('pack', '{`id`}');">
                                <td><input type="checkbox">{`_state`}</td>
                                <td>{`lable`}</td>
                                <td>{`name`}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </td>
            <td class="edit none" colspan="3">
                <div class="of_sso-main_tbody_td_div">
                    <table id="funcPaging" name="pagingBlock" method="of_base_sso_main::funcPaging">
                        <thead class="of-paging_wait" name="pagingWait" style="display: none;">
                            <tr>
                                <td colspan="3"></td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr name="pagingItem" key="{`id`}" style="display: none;" onclick="ofBaseSsoMain.item('func', '{`id`}');">
                                <td><input type="checkbox">{`_state`}</td>
                                <td>{`lable`}</td>
                                <td>{`name`}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </tbody>
    <tfoot>
        <tr class="of_sso-main_tfoot_func">
            <td class="user" colspan="3">
                <a class="of_sso-main_tfoot_func_next" onclick="ofBaseSsoMain.paging('user', '+1'); return false;">&gt;</a>
                <input id="userJump" class="of_sso-main_tfoot_func_jump" type="text" onkeyup="ofBaseSsoMain.jump('user', event)">
                <a class="of_sso-main_tfoot_func_prev" onclick="ofBaseSsoMain.paging('user', '-1'); return false;">&lt;</a>
                <a class="of_sso-main_tfoot_func_add" onclick="ofBaseSsoMain.edit(true, 'user'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_del" onclick="ofBaseSsoMain.action('user', 'del'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_ice" onclick="ofBaseSsoMain.action('user', 'ice'); return false;">&nbsp;</a>
            </td>
            <td colspan="3">
                <a class="of_sso-main_tfoot_func_next" onclick="ofBaseSsoMain.paging('bale', '+1'); return false;">&gt;</a>
                <input id="baleJump" class="of_sso-main_tfoot_func_jump" type="text" onkeyup="ofBaseSsoMain.jump('bale', event)">
                <a class="of_sso-main_tfoot_func_prev" onclick="ofBaseSsoMain.paging('bale', '-1'); return false;">&lt;</a>
                <a class="of_sso-main_tfoot_func_add edit none" onclick="ofBaseSsoMain.edit(true, 'bale'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_del edit none" onclick="ofBaseSsoMain.action('bale', 'del'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_ice edit none" onclick="ofBaseSsoMain.action('bale', 'ice'); return false;">&nbsp;</a>
            </td>
            <td colspan="3">
                <a class="of_sso-main_tfoot_func_next" onclick="ofBaseSsoMain.paging('realm', '+1'); return false;">&gt;</a>
                <input id="realmJump" class="of_sso-main_tfoot_func_jump" type="text" onkeyup="ofBaseSsoMain.jump('realm', event)">
                <a class="of_sso-main_tfoot_func_prev" onclick="ofBaseSsoMain.paging('realm', '-1'); return false;">&lt;</a>
                <a class="of_sso-main_tfoot_func_add edit none" onclick="ofBaseSsoMain.edit(true, 'realm'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_del edit none" onclick="ofBaseSsoMain.action('realm', 'del'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_ice edit none" onclick="ofBaseSsoMain.action('realm', 'ice'); return false;">&nbsp;</a>
            </td>
            <td colspan="3">
                <a class="of_sso-main_tfoot_func_next" onclick="ofBaseSsoMain.paging('pack', '+1'); return false;">&gt;</a>
                <input id="packJump" class="of_sso-main_tfoot_func_jump" type="text" onkeyup="ofBaseSsoMain.jump('pack', event)">
                <a class="of_sso-main_tfoot_func_prev" onclick="ofBaseSsoMain.paging('pack', '-1'); return false;">&lt;</a>
                <a class="of_sso-main_tfoot_func_add edit none" onclick="ofBaseSsoMain.edit(true, 'pack'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_del edit none" onclick="ofBaseSsoMain.action('pack', 'del'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_ice edit none" onclick="ofBaseSsoMain.action('pack', 'ice'); return false;">&nbsp;</a>
            </td>
            <td class="edit none" colspan="3">
                <a class="of_sso-main_tfoot_func_next" onclick="ofBaseSsoMain.paging('func', '+1'); return false;">&gt;</a>
                <input id="funcJump" class="of_sso-main_tfoot_func_jump" type="text" onkeyup="ofBaseSsoMain.jump('func', event)">
                <a class="of_sso-main_tfoot_func_prev" onclick="ofBaseSsoMain.paging('func', '-1'); return false;">&lt;</a>
                <a class="of_sso-main_tfoot_func_add" onclick="ofBaseSsoMain.edit(true, 'func'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_del" onclick="ofBaseSsoMain.action('func', 'del'); return false;">&nbsp;</a>
                <a class="of_sso-main_tfoot_func_ice" onclick="ofBaseSsoMain.action('func', 'ice'); return false;">&nbsp;</a>
            </td>
        </tr>
        <tr class="of_sso-main_tfoot_tip">
            <td colspan="15">
                <span class="of_sso-main_tfoot_model">
                    <input id="mainModelBox" type="checkbox" onclick="ofBaseSsoMain.model(this);">
                    <label id="mainModelTip" for="mainModelBox">授权模式</label>
                </span>
                <input class="of_sso-main_tfoot_tpl" type="button" value="模版" onclick="location.href=OF_URL + '/att/sso/res/importTpl.csv';">
                <input id="mainTplImport" class="of_sso-main_tfoot_import" type="button" value="导入">
                <input class="of_sso-main_tfoot_logout" type="button" value="退出" onclick="location.href=OF_URL + '/index.php?c=of_base_sso_main&a=logoutMain';">
                <input class="of_sso-main_tfoot_save" type="button" value="保存" onclick="ofBaseSsoMain.save();">
                <span id="mainTipBar"></span>
            </td>
        </tr>
        <tr class="of_sso-main_tfoot_data">
            <td colspan="15">
                <div id="userEdit" style="display: none;">
                    <input name="id" type="hidden">
                    <label>帐号 : <input name="name" type="text"></label>
                    <label>密码 : <input name="pwd" type="text"></label>
                    <label>昵称 : <input name="nike" type="text"></label>
                    <label>问题 : <input name="question" type="text"></label>
                    <label>回答 : <input name="answer" type="text"></label>
                    <label>备注 : <textarea name="notes"></textarea></label>
                </div>
                <div id="baleEdit" style="display: none;">
                    <input name="id" type="hidden">
                    <label>键值 : <input name="name" type="text"></label>
                    <label>包名 : <input name="lable" type="text"></label>
                    <label>备注 : <textarea name="notes"></textarea></label>
                </div>
                <div id="realmEdit" style="display: none;">
                    <input name="id" type="hidden">
                    <label>帐号 : <input name="name" type="text"></label>
                    <label>密码 : <input name="pwd" type="text"></label>
                    <label>简称 : <input name="lable" type="text"></label>
                    <label>
                        对接 : 
                        <select name="trust">
                            <option value="1">仅前台对接 (可操作当前用户和系统的数据)</option>
                            <option value="3">前后台对接 (还可通过帐号密码操作用户数据)</option>
                            <!-- <option value="7">用户管理权 (还可获取用户列表并无限制操作用户)</option> -->
                        </select>
                    </label>
                    <label>备注 : <textarea name="notes"></textarea></label>
                </div>
                <div id="packEdit" style="display: none;">
                    <input name="id" type="hidden">
                    <label>键值 : <input name="name" type="text"></label>
                    <label>名称 : <input name="lable" type="text"></label>
                    <label>数据 : <textarea name="data"></textarea></label>
                </div>
                <div id="funcEdit" style="display: none;">
                    <input name="id" type="hidden">
                    <label>键值 : <input name="name" type="text"></label>
                    <label>名称 : <input name="lable" type="text"></label>
                    <label>数据 : <textarea name="data"></textarea></label>
                </div>
            </td>
        </tr>
    </tfoot>
</table>