/**
 * 描述 : 统一导航脚步, 依赖jquery
 * 注明 :
 *      导航配置(config) : {
 *          "remove" : 自动移除的节点, 符合jquery选择器规则
 *          "ssoAcc" : 系统帐号(SSO帐号)
 *      }
 *      导航数据(navData) : {
 *          "userNike" : 用户昵称,
 *          "system"   : 系统数据 {
 *              系统标识(SSO帐号) : {
 *                  "acct"  :x系统标识
 *                  "nHtml" :x浮动导航html
 *                  "name"  : 系统全称
 *                  "group" : 系统分组名称, ""=不分组
 *                  "site"  : 系统根URL
 *                  "navs"  : 导航列表 {
 *                      一级导航权限 : 一级导航 {
 *                          "name" : 导航名称,
 *                          "url"  : 导航路径, ""=没权限
 *                          "navs" : 二级导航 {
 *                              二级导航权限 : {
 *                                  "name" : 导航名称,
 *                                  "url" : 导航路径,
 *                                  ...
 *                              }, ...
 *                          }
 *                      }, ...
 *                  }
 *              }
 *          }
 *      }
 *      系统分组(group) : {
 *          分组名称 : [系统导航数据, ...],
 *          ...
 *      }
 * 作者 : Edgar.lee
 */
+function () {
    //导航数据, 导航节点, 临时变量
    var navData, navObj, group = {}, temp;
    //当前脚步
    var script = $('script').eq(-1);
    //OA 网址
    var oaUrl = (oaUrl = script.attr('src')).slice(0, oaUrl.length - 7) || '.';
    //导航配置
    var config = (new Function('return ' + script.prop('text') + ';'))();
    //依赖数据
    var urlList = {
        //样式路径
        'css' : oaUrl + '/nav.css',
        //导航数据
        'nav' : oaUrl + '/nav.json'
    };
    //渲染导航
    var render = function (json) {
        //自动移除节点
        config.remove && $(config.remove).remove();
        //保存导航路径
        navData = json;

        //合并系统分组
        for (var i in json['system']) {
            //当前分组
            temp = json['system'][i];
            //记录系统帐号
            temp['acct'] = i;
            //记录浮动导航html
            temp['nHtml'] = '';
            //默认分组
            temp['group'] || (temp['group']= temp['name']);
            //初始化分组
            group[temp['group']] || (group[temp['group']] = []);
            //系统帐号分组
            group[temp['group']].push(temp);

            //拼接导航html
            for (var j in temp['navs']) {
                //一级导航
                temp['nHtml'] += '<div>' +
                    '<a target="oa-' + i + '" ' + (temp['navs'][j]['url'] ? 
                            ' href="' + temp['site'] + temp['navs'][j]['url'] + '"' : ''
                    ) + '>' +
                        temp['navs'][j]['name'] +
                    '</a>' +
                '</div><ul>';

                //二级导航
                for (var k in temp['navs'][j]['navs']) {
                    temp['nHtml'] += '<li>' +
                        '<a' +
                            ' target="oa-' + i + '"' +
                            ' href="' + temp['site'] + temp['navs'][j]['navs'][k]['url'] + '"' +
                        '>' +
                            temp['navs'][j]['navs'][k]['name'] +
                        '</a>' +
                    '</li>';
                }

                temp['nHtml'] += '</ul>';
            }
        }

        //当前系统
        temp = navData['system'][config['ssoAcc']];

        //创建节点对象
        navObj = '<div class="oa-nav_main">' +
            '<div class="oa-nav_top">' +
                '<div class="oa-nav_logo">' +
                    //系统帐号
                    '<a href="' + temp['site'] + '/">' + temp['name'] + '</a>' +
                '</div>';

        //顶部导航连接
        for (var i in temp['navs']) {
            //拼接一级导航
            navObj += '<span class="oa-nav_link">' +
                '<a' + (temp['navs'][i]['url'] ? 
                        ' href="' + temp['site'] + temp['navs'][i]['url'] + '"' : ''
                ) + '>' +
                    temp['navs'][i]['name'] +
                '</a>' +
                '<ul style="display: none;">';

            //拼接二级导航
            for (var j in temp['navs'][i]['navs']) {
                navObj += '<li>' +
                    '<a href="' + temp['site'] + temp['navs'][i]['navs'][j]['url'] + '" class="">' +
                        temp['navs'][i]['navs'][j]['name'] +
                    '</a>' +
                '</li>';
            }

            navObj += '</ul></span>';
        }

        //拼接用户功能区
        navObj += '<div class="oa-nav_func">' +
            '<div class="logout">' +
                '<a href="#">' +
                    '<svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2465" width="23" height="23">' +
                        '<path d="M512.282433 957.937398c-247.529583 0-448.190719-200.661136-448.190719-448.185602 0-191.062524 119.757524-353.747743 288.115953-418.226267l0 69.339228c-132.148739 60.736293-224.088708 193.907315-224.088708 348.887039 0 212.150818 172.011632 384.16245 384.163473 384.16245 212.152864 0 384.158357-172.010609 384.158357-384.16245 0-154.979724-91.944062-288.150746-224.093824-348.917738l0-69.308529c168.359452 64.478524 288.115953 227.16886 288.115953 418.226267C960.468035 757.276263 759.807922 957.937398 512.282433 957.937398L512.282433 957.937398 512.282433 957.937398zM480.268298 61.561078l64.022129 0 0 449.339892-64.022129 0L480.268298 61.561078 480.268298 61.561078 480.268298 61.561078zM480.268298 61.561078" p-id="2466" fill="#ffffff"></path>' +
                    '</svg>' +
                '</a>' +
            '</div>' +
            '<div class="oa-nav_user">' +
                '<a class="oa-nav_user_set">admin</a>' +
            '</div>' +
            '<div class="oa-nav_search">' +
                '<input type="text" name="system" class="search" value="" placeholder="请输入搜索内容">' +
                '<div class="search_result" style="display: none;"></div>' +
            '</div>' +
        '</div>';

        //顶部导航结束
        navObj += '</div>';

        //开始左侧导航
        navObj += '<div class="oa-nav_left">' +
            '<ul class="oa-nav_left_list">';

        //系统分组
        for (var i in group) {
            navObj += '<li class="oa-nav_group">';

            //单个不分组
            if (group[i].length === 1) {
                navObj += '<a' +
                    //系统窗口
                    ' target="oa-' + group[i][0]['acct'] + '"' +
                    //根域名
                    ' href="' + group[i][0]['site'] + '/"' +
                    //系统帐号
                    ' acct="' + group[i][0]['acct'] + '"' +
                    //短名称
                    ' group="' + group[i][0]['group'] + '"' +
                    //完整名称
                    ' sName="' + group[i][0]['name'] + '"' +
                '>' + group[i][0]['group'] + '</a>';
            //多个分组
            } else {
                navObj += '<a>' + i + '</a><ul style="display: none;">';

                for (var j in group[i]) {
                    navObj += '<li class="oa-nav_group_list">';
                    navObj += '<a' +
                        //系统窗口
                        ' target="oa-' + group[i][j]['acct'] + '"' +
                        //根域名
                        ' href="' +group[i][j]['site']+ '/"' +
                        //系统帐号
                        ' acct="' +group[i][j]['acct']+ '"' +
                        //短名称
                        ' group="' +group[i][j]['group']+ '"' +
                        //完整名称
                        ' sName="' +group[i][j]['name']+ '"' +
                    '>' + group[i][j]['group'] + '</a>';
                    navObj += '</li>'
                }

                navObj += '</ul>';
            }

            navObj += '</li>';
        }
        navObj += '</ul>'

        //拼接左侧浮动导航
        navObj += '<div class="oa-nav_left_full"></div>';
        //左侧导航结束
        navObj += '</div>';
        //整个导航结束
        navObj += '</div>';

        //拼接导航节点
        $(function () {
            //导航节点
            navObj = $(navObj).prependTo('body');

            //窗口改变事件
            $(window).resize(function () {
                $('.oa-nav_left_list').height($(this).height() - 40)
            }).resize();

            //添加顶部导航菜单鼠标事件
            navObj.find('.oa-nav_link')
                .mouseenter(function () {
                    $('ul', this).show();
                }).mouseleave(function () {
                    $('ul', this).hide();
                });

            //添加左侧菜单划出鼠标事件
            navObj.find('.oa-nav_left')
                .mouseenter(function () {
                    //左侧宽度设置130px
                    $('.oa-nav_left_list', this).width(130);
                    //显示长名称
                    $('a[sName]', this).each(function () {
                        this.innerHTML = this.getAttribute('sName');
                    });
                }).mouseleave(function () {
                    //左侧宽度设置70px
                    $('.oa-nav_left_list', this).width(70);
                    //显示短名称
                    $('a[group]', this).each(function () {
                        this.innerHTML = this.getAttribute('group');
                    });
                    //隐藏分组列表
                    $('.oa-nav_group ul', this).hide();
                    //隐藏浮动导航
                    $('.oa-nav_left_full', navObj).hide();
                });

            //系统分组显示系统列表明细鼠标事件
            navObj.find('.oa-nav_group a:not([acct])')
                .mouseenter(function () {
                    //显示分组明细
                    $('ul', this.parentNode).show();
                    //隐藏浮动导航
                    $('.oa-nav_left_full', navObj).hide();
                });

            //添加左侧系统连接菜单显示鼠标事件
            navObj.find('.oa-nav_left a[acct]')
                .mouseenter(function () {
                    //当前A标签
                    var thisObj = $(this);
                    //浮动导航节点
                    var fullObj = $('.oa-nav_left_full', navObj)
                        .css('top', -1000)
                        .html(navData['system'][thisObj.attr('acct')].nHtml)
                        .show();

                    //浮动导航垂直偏移
                    temp = [thisObj.offset().top - $(window).scrollTop() - 50];
                    //浮动导航底部与窗口距离
                    temp[1] = $(window).height() - fullObj.height() - temp[0] - 70;
                    //浮动导航溢出窗口
                    temp[1] < 0 && (temp[0] += temp[1]);

                    //显示浮动框
                    fullObj.css('top', temp[0]);
                });
        });
    }

    //当前窗口名称
    window.name || (window.name = 'oa-' + config['ssoAcc']);
    //加载样式文件
    script.after('<link rel="stylesheet" href="' +urlList['css']+ '">');
    //加载导航数据
    $.ajax(urlList['nav'], {
        "jsonpCallback" : "callback",
        "dataType"      : "jsonp",
        "success"       : render
    });
}()