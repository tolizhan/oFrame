<?php
$this->head( array(
    'title' => L::getText('测试输出的标题'),
    'css'   => array(
        //'/main.css',
        //'/components/paging/paging.css'
    )
    //,'js' => array('/paging.js')    //加载js,注:js都将在页面底部加载,默认将加载'jquery.js'
));
?>
<pre>
<?php
echo "本站磁盘根目录: ", ROOT_DIR;    //$this->_rootDir
echo "\n本站URL根目录 : ", ROOT_URL;    //$this->_rootUrl
echo "\n", $this->str;
?>
</pre>
<img id="captchaImg" src="<?php echo OF_URL; ?>/index.php?a=captcha&c=of_base_com_com" title="点击刷新" style="cursor:pointer;" onclick="this.src = (this.backupSrc || (this.backupSrc = this.src)) + '&t=' + new Date().getTime()" />
<input id="captcha" type="text" />
<input type="button" value="验证" onclick="captcha(this)" />
<br />
<input type='button' value='点击产生错误' onclick='error()'/>
<input type='button' value='日期控件' onclick='wDate()'/>
<input type='button' value='打开oFM' onclick='openFileManager()'/>
<input type='button' value='打开tip提示' onclick='openTip()'/>
<input type='button' value='打开zTree树' onclick='openZTree()'/>
<input type='button' value='打开oDialogDiv' onclick='openDialogDiv()'/>
<input type='button' value='打开oEditor' onclick='openEditor()'/>
<input type='button' value='打开oUpload' id="oUpload"/>
<input type='button' value='打开图表' onclick='openECharts()'/>
<div id='wDate'></div>
<textarea id="myArea1" name="myArea1" style="width:50%; height:300px; border: 1px solid #000; display:none;"></textarea>
<ul id="treeDemo" class="ztree"></ul>
<div id="eChartsMain" style="height:500px;border:1px solid #ccc;padding:10px; display:none;"></div>
<script>
function captcha(thisObj)
{
    $.post(ROOT_URL + '/index.php?a=captcha&c=demo_index', {'captcha' : $('#captcha').val()}, function(data) {
        $(thisObj).val(data ? '成功' : '失败');
        $('#captchaImg').click();
    });
}
function wDate()
{
    //第一种控件操作方式
    window.L.open('wDate', {
        'obj' : $('#captcha').get(0),    //需绑定的对象
        'type' : 'focus',    //绑定的触发事件,默认click
        'params' : {'readOnly' : true}    //传递WdatePicker的参数
    });
    //第二种控件操作方式
    window.L.open('wDate')({
        'eCont'    : 'wDate', 
        'onpicked' : function(dp){
            alert('你选择的日期是:'+dp.cal.getDateStr())
        }
    });
}
function error()
{
    ff.ff.ff
}
function openFileManager()
{
    window.L.open('oFM')(
        function(systemObj,callBackObj)
        {
            alert(systemObj.url);
            //alert(systemObj.handle);
            //alert(callBackObj.lizhan);
            oFileManager.close();
        },
        {'lizhan':5},
        false,
        {fileExt:'txt;doc',browseDir:'/.',selectExt:'exe;txt'}
    );
}
function openTip()
{
    window.L.open('tip')('加载一个提示,三秒后关闭');
}
function openZTree()
{
    var setting = {
        data: {
            simpleData: {
                enable: true
            }
        }
    };

    var zNodes =[
        { id:1, pId:0, name:"父节点1 - 展开", open:true},
        { id:11, pId:1, name:"父节点11 - 折叠"},
        { id:111, pId:11, name:"叶子节点111"},
        { id:112, pId:11, name:"叶子节点112"},
        { id:113, pId:11, name:"叶子节点113"},
        { id:114, pId:11, name:"叶子节点114"},
        { id:12, pId:1, name:"父节点12 - 折叠"},
        { id:121, pId:12, name:"叶子节点121"},
        { id:122, pId:12, name:"叶子节点122"},
        { id:123, pId:12, name:"叶子节点123"},
        { id:124, pId:12, name:"叶子节点124"},
        { id:13, pId:1, name:"父节点13 - 没有子节点", isParent:true},
        { id:2, pId:0, name:"父节点2 - 折叠"},
        { id:21, pId:2, name:"父节点21 - 展开", open:true},
        { id:211, pId:21, name:"叶子节点211"},
        { id:212, pId:21, name:"叶子节点212"},
        { id:213, pId:21, name:"叶子节点213"},
        { id:214, pId:21, name:"叶子节点214"},
        { id:22, pId:2, name:"父节点22 - 折叠"},
        { id:221, pId:22, name:"叶子节点221"},
        { id:222, pId:22, name:"叶子节点222"},
        { id:223, pId:22, name:"叶子节点223"},
        { id:224, pId:22, name:"叶子节点224"},
        { id:23, pId:2, name:"父节点23 - 折叠"},
        { id:231, pId:23, name:"叶子节点231"},
        { id:232, pId:23, name:"叶子节点232"},
        { id:233, pId:23, name:"叶子节点233"},
        { id:234, pId:23, name:"叶子节点234"},
        { id:3, pId:0, name:"父节点3 - 没有子节点", isParent:true}
    ];
    window.L.open('zTree').init($("#treeDemo"), setting, zNodes);
}
function openDialogDiv()
{
    window.L.open('oDialogDiv')("点击确认获取取消", 'text:<div style="width:500px; height:500px; border:1px solid #000">\
                <input type="button" value="长提示文本" onclick="oDialogDivInfo(\'长提示文本 长提示文本 长提示文本 长提示文本 长提示文本 长提示文本 长提示文本\')" />\
                <input type="button" value="短提示文本" onclick="oDialogDivInfo(\'短提示文本\')" /><br/>\
                <input type="button" value="移除提示" onclick="oDialogDivInfo()" />\
                <input type="button" value="不自动关闭提示" onclick="oDialogDivInfo(\'不自动关闭提示\', false)" />\
                <input type="button" value="加锁" onclick="oDialogDivInfo(\'加锁\', 2000, true)" />\
                <input type="button" value="解锁" onclick="oDialogDivInfo(\'解锁\', 2000, false)" />\
            </div>', "auto", "auto", [2,
        function(mm) {
            alert(mm?'你点击了确认':'你点击了取消')
        }]);
}
//显示提示
function oDialogDivInfo(info, timeout, lock)
{
    var tip = window.L.open('tip');
    if(lock === false)    //解锁演示
    {
        tip.lock = lock;
    }
    tip(info, timeout);
    if(lock === true)    //枷锁演示
    {
        tip.lock = lock;
    }
}
function openEditor()
{
    if(window.L.open('oEditor'))
    {
        var oEditorObj=new oEditor(
            {
                fullPanel : true,maxHeight : 300
                ,CustomConfig:
                    {
                        AnswerSelect:
                            {
                                AnswerType:"textbox"
                            }
                        ,oFileManager:                                            //oFileManager配置文件
                            {
                                quickUploadDir:{                                  //各类型的文件快速上传路径
                                    img         : '/pictures/..quickUpload'       //图片快速上传文件夹
                                    ,media      : '/media/..quickUpload'          //媒体快速上传文件夹
                                    ,attachment : '/attachment/..quickUpload'     //媒体快速上传文件夹
                                }
                                ,browseDir:{                                      //各类型文件预览文件夹
                                    img         : '/pictures'                     //图片预览文件夹
                                    ,media      : '/media'                        //媒体预览文件夹
                                    ,attachment : '/attachment'                   //附件上传文件夹
                                }
                            }
                    }
            }
        ).panelInstance('myArea1');
    }
}

//试图例子
function openECharts()
{
    document.getElementById('eChartsMain').style.display = '';
    var eCharts = window.L.open('eCharts', {
        'obj'        : document.getElementById('eChartsMain'),
        'setOption'  : {
                    title : {
                        text: '2013年上半年上证指数'
                    },
                    tooltip : {
                        trigger: 'axis',
                        formatter: function(params) {
                            var res = params[1][1];
                            res += '<br/>' + params[1][0];
                            res += '<br/>  开盘 : ' + params[1][2][0] + '  最高 : ' + params[1][2][3];
                            res += '<br/>  收盘 : ' + params[1][2][1] + '  最低 : ' + params[1][2][2];
                            res += '<br/>' + params[0][0];
                            res += ' : ' + params[0][2];
                            return res;
                        }
                    },
                    legend: {
                        data:['上证指数','成交金额(万)']
                    },
                    toolbox: {
                        show : true,
                        feature : {
                            mark : true,
                            dataZoom : true,
                            dataView : {readOnly: false},
                            magicType:['line', 'bar'],
                            restore : true,
                            saveAsImage : true
                        }
                    },
                    dataZoom : {
                        show : true,
                        realtime: true,
                        start : 50,
                        end : 100
                    },
                    xAxis : [
                        {
                            type : 'category',
                            boundaryGap : true,
                            data : [
                                "2013/1/24", "2013/1/25", "2013/1/28", "2013/1/29", "2013/1/30",
                                "2013/1/31", "2013/2/1", "2013/2/4", "2013/2/5", "2013/2/6", 
                                "2013/2/7", "2013/2/8", "2013/2/18", "2013/2/19", "2013/2/20", 
                                "2013/2/21", "2013/2/22", "2013/2/25", "2013/2/26", "2013/2/27", 
                                "2013/2/28", "2013/3/1", "2013/3/4", "2013/3/5", "2013/3/6", 
                                "2013/3/7", "2013/3/8", "2013/3/11", "2013/3/12", "2013/3/13", 
                                "2013/3/14", "2013/3/15", "2013/3/18", "2013/3/19", "2013/3/20", 
                                "2013/3/21", "2013/3/22", "2013/3/25", "2013/3/26", "2013/3/27", 
                                "2013/3/28", "2013/3/29", "2013/4/1", "2013/4/2", "2013/4/3", 
                                "2013/4/8", "2013/4/9", "2013/4/10", "2013/4/11", "2013/4/12", 
                                "2013/4/15", "2013/4/16", "2013/4/17", "2013/4/18", "2013/4/19", 
                                "2013/4/22", "2013/4/23", "2013/4/24", "2013/4/25", "2013/4/26", 
                                "2013/5/2", "2013/5/3", "2013/5/6", "2013/5/7", "2013/5/8", 
                                "2013/5/9", "2013/5/10", "2013/5/13", "2013/5/14", "2013/5/15", 
                                "2013/5/16", "2013/5/17", "2013/5/20", "2013/5/21", "2013/5/22", 
                                "2013/5/23", "2013/5/24", "2013/5/27", "2013/5/28", "2013/5/29", 
                                "2013/5/30", "2013/5/31", "2013/6/3", "2013/6/4", "2013/6/5", 
                                "2013/6/6", "2013/6/7", "2013/6/13"
                            ]
                        }
                    ],
                    yAxis : [
                        {
                            type : 'value',
                            scale:true,
                            precision: 2,
                            splitNumber: 9,
                            boundaryGap: [0.05, 0.05],
                            splitArea : {show : true}
                        },
                        {
                            type : 'value',
                            scale:true,
                            splitNumber: 9,
                            boundaryGap: [0.05, 0.05],
                            splitArea : {show : true}
                        }
                    ],
                    series : [
                        {
                            name:'成交金额(万)',
                            type:'line',
                            yAxisIndex: 1,
                            symbol: 'none',
                            data:[
                                13560434, 8026738.5, 11691637, 12491697, 12485603, 
                                11620504, 12555496, 15253370, 12709611, 10458354, 
                                10933507, 9896523, 10365702, 10633095, 9722230, 
                                12662783, 8757982, 7764234, 10591719, 8826293, 
                                11591827, 11153111, 14304651, 11672120, 12536480, 
                                12608589, 8843860, 7391994.5, 10063709, 7768895.5, 
                                6921859, 10157810, 8148617.5, 7551207, 11397426, 
                                10478607, 8595132, 8541862, 9181132, 8570842, 
                                10759351, 7335819, 6699753.5, 7759666.5, 6880135.5, 
                                7366616.5, 7313504, 7109021.5, 6213270, 5619688, 
                                5816217.5, 6695584.5, 5998655.5, 6188812.5, 9538301,
                                8224500, 8221751.5, 7897721, 8448324, 6525151, 
                                5987761, 7831570, 8162560.5, 7904092, 8139084.5, 
                                9116529, 8128014, 7919148, 7566047, 6665826.5, 
                                10225527, 11124881, 12884353, 11302521, 11529046, 
                                11105205, 9202153, 9992016, 12035250, 11431155, 
                                10354677, 10070399, 9164861, 9237718, 7114268, 
                                7526158.5, 8105835, 7971452.5
                            ]
                        },
                        {
                            name:'上证指数',
                            type:'k',
                            data:[ // 开盘，收盘，最低，最高
                                [2320.26,2302.6,2287.3,2362.94],
                                [2300,2291.3,2288.26,2308.38],
                                [2295.35,2346.5,2295.35,2346.92],
                                [2347.22,2358.98,2337.35,2363.8],
                                [2360.75,2382.48,2347.89,2383.76],
                                [2383.43,2385.42,2371.23,2391.82],
                                [2377.41,2419.02,2369.57,2421.15],
                                [2425.92,2428.15,2417.58,2440.38],
                                [2411,2433.13,2403.3,2437.42],
                                [2432.68,2434.48,2427.7,2441.73],
                                [2430.69,2418.53,2394.22,2433.89],
                                [2416.62,2432.4,2414.4,2443.03],
                                [2441.91,2421.56,2415.43,2444.8],
                                [2420.26,2382.91,2373.53,2427.07],
                                [2383.49,2397.18,2370.61,2397.94],
                                [2378.82,2325.95,2309.17,2378.82],
                                [2322.94,2314.16,2308.76,2330.88],
                                [2320.62,2325.82,2315.01,2338.78],
                                [2313.74,2293.34,2289.89,2340.71],
                                [2297.77,2313.22,2292.03,2324.63],
                                [2322.32,2365.59,2308.92,2366.16],
                                [2364.54,2359.51,2330.86,2369.65],
                                [2332.08,2273.4,2259.25,2333.54],
                                [2274.81,2326.31,2270.1,2328.14],
                                [2333.61,2347.18,2321.6,2351.44],
                                [2340.44,2324.29,2304.27,2352.02],
                                [2326.42,2318.61,2314.59,2333.67],
                                [2314.68,2310.59,2296.58,2320.96],
                                [2309.16,2286.6,2264.83,2333.29],
                                [2282.17,2263.97,2253.25,2286.33],
                                [2255.77,2270.28,2253.31,2276.22],
                                [2269.31,2278.4,2250,2312.08],
                                [2267.29,2240.02,2239.21,2276.05],
                                [2244.26,2257.43,2232.02,2261.31],
                                [2257.74,2317.37,2257.42,2317.86],
                                [2318.21,2324.24,2311.6,2330.81],
                                [2321.4,2328.28,2314.97,2332],
                                [2334.74,2326.72,2319.91,2344.89],
                                [2318.58,2297.67,2281.12,2319.99],
                                [2299.38,2301.26,2289,2323.48],
                                [2273.55,2236.3,2232.91,2273.55],
                                [2238.49,2236.62,2228.81,2246.87],
                                [2229.46,2234.4,2227.31,2243.95],
                                [2234.9,2227.74,2220.44,2253.42],
                                [2232.69,2225.29,2217.25,2241.34],
                                [2196.24,2211.59,2180.67,2212.59],
                                [2215.47,2225.77,2215.47,2234.73],
                                [2224.93,2226.13,2212.56,2233.04],
                                [2236.98,2219.55,2217.26,2242.48],
                                [2218.09,2206.78,2204.44,2226.26],
                                [2199.91,2181.94,2177.39,2204.99],
                                [2169.63,2194.85,2165.78,2196.43],
                                [2195.03,2193.8,2178.47,2197.51],
                                [2181.82,2197.6,2175.44,2206.03],
                                [2201.12,2244.64,2200.58,2250.11],
                                [2236.4,2242.17,2232.26,2245.12],
                                [2242.62,2184.54,2182.81,2242.62],
                                [2187.35,2218.32,2184.11,2226.12],
                                [2213.19,2199.31,2191.85,2224.63],
                                [2203.89,2177.91,2173.86,2210.58],
                                [2170.78,2174.12,2161.14,2179.65],
                                [2179.05,2205.5,2179.05,2222.81],
                                [2212.5,2231.17,2212.5,2236.07],
                                [2227.86,2235.57,2219.44,2240.26],
                                [2242.39,2246.3,2235.42,2255.21],
                                [2246.96,2232.97,2221.38,2247.86],
                                [2228.82,2246.83,2225.81,2247.67],
                                [2247.68,2241.92,2231.36,2250.85],
                                [2238.9,2217.01,2205.87,2239.93],
                                [2217.09,2224.8,2213.58,2225.19],
                                [2221.34,2251.81,2210.77,2252.87],
                                [2249.81,2282.87,2248.41,2288.09],
                                [2286.33,2299.99,2281.9,2309.39],
                                [2297.11,2305.11,2290.12,2305.3],
                                [2303.75,2302.4,2292.43,2314.18],
                                [2293.81,2275.67,2274.1,2304.95],
                                [2281.45,2288.53,2270.25,2292.59],
                                [2286.66,2293.08,2283.94,2301.7],
                                [2293.4,2321.32,2281.47,2322.1],
                                [2323.54,2324.02,2321.17,2334.33],
                                [2316.25,2317.75,2310.49,2325.72],
                                [2320.74,2300.59,2299.37,2325.53],
                                [2300.21,2299.25,2294.11,2313.43],
                                [2297.1,2272.42,2264.76,2297.1],
                                [2270.71,2270.93,2260.87,2276.86],
                                [2264.43,2242.11,2240.07,2266.69],
                                [2242.26,2210.9,2205.07,2250.63],
                                [2190.1,2148.35,2126.22,2190.1]
                            ]
                        }
                    ]
                }
    });
}
$(function() {
    L.open('oUpload', {
        'node'  : document.getElementById('oUpload'),
        'auto'  : false,
        'multi' : true,
        'exts'  : 'txt;jpg',
        'call'  : function () {
            //剩余上传数量
            document.title = this.setting('queueSize');
            //文件路径
            this.node.value = arguments[3];
        }
    });
});
</script>

<pre>
js语言包的使用
<script>
document.write(L.getText('加载JS端语言包翻译')+'<br/>');
document.write(L.getText('高效加载JS端语言包翻译', {'key' : '关键字'}));
</script>
</pre>
<pre>
php语言包的使用
<?php
echo L::getText('加载默认语言中的该字符串');
echo '<br/>', L::getText('高效加载默认语言中的该字符串', array('key'=>'这一个关键字'));
?>
</pre>
<?php
echo '分页列表的使用';
echo $this->_pagingHtml;
?>