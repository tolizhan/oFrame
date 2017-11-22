/**
 * 描述 : 登录主页交互脚本
 * 作者 : Edgar.lee
 */
var ofBaseSsoLogin = {
    //默认操作类型
    'type' : 'login',
    //访问的get参数
    'args' : ofBaseSsoLogin,

    /**
     * 描述 : 注册,登录,找回对应函数
     * 参数 :
     *      type : "reg"=注册, "login"=登录, "find"=找回
     *      node : 触发的节点
     * 作者 : Edgar.lee
     */
    'func' : function (type, node) {
        //存储提交类型
        ofBaseSsoLogin.type = type;

        if (type !== 'login') {
            var temp = document.getElementById('extends');
            //显示界面
            if (temp.style.display === 'none') {
                temp.style.display = '';
                return ;
            }
        }

        ofBaseSsoLogin.submit();
    },

    /**
     * 描述 : 提交数据
     * 作者 : Edgar.lee
     */
    'submit' : function () {
        var list = document.getElementsByTagName('table')[0].getElementsByTagName('input');
        var args = {
            'get'  : ofBaseSsoLogin.args,
            'post' : {'type' : ofBaseSsoLogin.type}
        };

        //收集数据
        for(var i = 0, iL = list.length; i < iL; i++) {
            args.post[list[i].name] = list[i].value;
        }

        //简单校验通过
        if (args.post.name && args.post.pwd && args.post.captcha) {
            L.ajax(OF_URL + '/index.php?c=of_base_sso_main&a=index', args, function (data) {
                data = L.json(data);
                //成功
                if (data.state === 'done') {
                    ofBaseSsoLogin.tipBar('操作成功');

                    //post 跳转页面
                    if (L.type(data.msg) === 'object') {
                        ofBaseSsoLogin.form(data.msg);
                    } else {
                        //跳转 管理界面
                        window.location.href = OF_URL + 
                            '/index.php?c=of_base_sso_main&a=index' +
                            (data.msg ? '&tip=' + data.msg : '');
                    }
                } else {
                    ofBaseSsoLogin.tipBar('操作失败 : ' + data.msg);
                    document.getElementById('captcha').click();
                }
            });
        } else {
            ofBaseSsoLogin.tipBar('用户名, 密码, 验证码 不能为空');
        }
    },

    /**
     * 描述 : 通过 form 发送 post 数据
     * 作者 : Edgar.lee
     */
    'form' : function (post) {
        var form = document.createElement("form"), temp;
        form.action = ofBaseSsoLogin.args.referer;
        form.method = "post";
        form.style.display = "none";

        for (var i in post) {
            temp = document.createElement("input");
            temp.name = i;
            temp.value = post[i];
            form.appendChild(temp);
        }

        document.body.appendChild(form);
        form.submit();
    },

    /**
     * 描述 : 失去或得到焦点时
     * 参数 :
     *      type : true=得到焦点, false=失去焦点
     *      node : 节点对象
     * 作者 : Edgar.lee
     */
    'focus' : function (type, node) {
        var qObj = document.getElementById('question');

        //得到焦点
        if (type) {
            node.select();
        //失去焦点 && 扩展打开
        } else if (qObj.getBoundingClientRect().top) {
            //生成提示信息
            var tips = ofBaseSsoLogin.type === 'reg' ?
                ['正在检测用户信息', '用户已存在', '可以注册'] : ['正在获取提示信息', '获取成功', '用户不存在'];

            ofBaseSsoLogin.tipBar(tips[0], false);
            qObj.value = '';
            type = OF_URL + '/index.php?c=of_base_sso_main&a=getUserInfo';
            L.ajax(type, {'post' : {'name' : node.value}}, function (data) {
                //"\0"=不存在
                if (data === '\0') {
                    ofBaseSsoLogin.tipBar(tips[2]);
                //用户存在
                } else {
                    ofBaseSsoLogin.tipBar(tips[1]);
                    qObj.value = (data = L.json(data)).question;
                    document.getElementById('nike').value = data.nike;
                }
            });
        }
    },

    /**
     * 描述 : 显示提示信息
     * 作者 : Edgar.lee
     */
    'tipBar' : function (value, close) {
        var tipObj = document.getElementById('tipBar');
        //取消关闭
        if (ofBaseSsoLogin.diff && ofBaseSsoLogin.bubble) {
            clearTimeout(ofBaseSsoLogin.diff);
            ofBaseSsoLogin.diff = tipObj.innerHTML = '';
        } else if (ofBaseSsoLogin.diff === undefined) {
            //冒泡关闭
            L.event(document.body, 'click', ofBaseSsoLogin.tipBar);
        }

        ofBaseSsoLogin.bubble = true;
        if (typeof value === 'string') {
            //提示信息显示
            tipObj.innerHTML = L.entity(value, true);
            //添加取消
            close === false || (ofBaseSsoLogin.diff = setTimeout(ofBaseSsoLogin.tipBar, 3000));
            //防止被自身冒泡取消
            ofBaseSsoLogin.bubble = !L.event();
        }
    }
}

delete ofBaseSsoLogin.args.a, delete ofBaseSsoLogin.args.c;
if (ofBaseSsoLogin.args.tip) {
    //默认提示信息
    ofBaseSsoLogin.tipBar(ofBaseSsoLogin.args.tip);
} else if (ofBaseSsoLogin.args.form) {
    //隐藏登录窗口
    document.getElementById('loginBlock').style.display = 'none';
    ofBaseSsoLogin.tipBar('正在自动登录, 请稍后...');
    //登录跳转
    ofBaseSsoLogin.form(ofBaseSsoLogin.args.form);
}