<?php
/**
 * 描述 : 调用用例, 生产日志
 * 作者 : Edgar.lee
 */
class of_base_test_case {
    //计划任务当前配置
    private static $info = null;

    /**
     * 描述 : 调试测试用例
     * 参数 :
     *      call : 调用测试, "/"开头字符串=调用案例清单, 其它=框架调用结构
     * 作者 : Edgar.lee
     */
    public static function debug($call) {
        if (is_string($call) && $call[0] === '/') {
            $call = explode('::', $call, 2);
            $list = include ROOT_DIR . $call[0];
            $call = $list['cases'][$call[1]]['php'];
        }

        //打印结果集
        echo '<pre><hr>',
            print_r(self::callTest($call), true),
            '<hr>',
            print_r($call, true),
            '</pre>';
    }

    /**
     * 描述 : 启动用例测试任务
     * 作者 : Edgar.lee
     */
    public static function task($params = null) {
        //生产环境不启用
        if (OF_DEBUG === false) return ;
        //格式化时间
        $time = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);
        //用例故事配置路径
        $path = ROOT_DIR . OF_DATA . '/_of/of_base_test/info';
        //有效清单列表
        $list = array();
        //用例清单配置
        of_base_com_disk::each($path, $dirs, false);

        //读取配置信息
        foreach ($dirs as $k => &$v) {
            //解析配置信息
            $v = of_base_com_disk::file($k, true, true);
            //文件修改时间
            $v['time'] = filemtime($k);

            //用例案例文件不存在
            if (!is_file(ROOT_DIR . $v['path'])) {
                unlink($k);
                unset($dirs[$k]);
            //开启执行 && 达到执行时间
            } else if ($v['mode'] & 2 && $v['start'] < $time) {
                //添加到有效清单
                $list[$k] = &$v;
            }
        }

        //可用配置为空
        if (!$list) {
            of_base_com_timer::task(array(
                'call' => 'of_base_test_case::task',
                'cNum' => 1,
                'time' => strtotime(substr($time, 0, 10)) + 86400
            ));
            return ;
        }

        //按开始倒序完成正序排列
        of_base_com_com::arraySort($list, array(
            'start' => 'DESC',
            'done' => 'ASC'
        ));

        //执行用例配置路径
        $iDir = key($list);
        //用例清单分组标识
        $iMd5 = substr($iDir, -36, 32);
        //用例案例缓存键名
        $cKey = "of_base_test_case::casesProgress.{$iMd5}";
        //读取用例列表清单
        $test = include ROOT_DIR . $list[$iDir]['path'];

        //引用当前配置信息
        $info = &self::$info;
        //记录用例配置信息
        $info = array(
            'iDir'  => &$iDir,
            'cKey'  => &$cKey,
            'count' => count($test['cases'])
        ) + $list[$iDir];

        //异常退出回调
        of::event('of::halt', 'of_base_test_case::ofHalt');
        //记录更新时间
        of_base_com_timer::renew();

        //读取进度缓存
        if ($temp = of_base_com_kv::get($cKey, null, '_ofSelf')) {
            $info['info'] = $temp['info'];
        //没有进度缓存
        } else {
            $info['info'] = array(
                'done' => $info['count'],
                'error' => 0
            );
        }

        //代码未变动
        do {
            //读取进度用例
            if ($val = array_slice($test['cases'], $info['exec'], 1)) {
                $key = key($val);
                $val = &$val[$key];

                //执行用例测试
                $temp = self::callTest($val['php'], $info['params'] = array(
                    //用例案例分组
                    'group'  => &$iMd5,
                    //用例案例路径
                    'path'   => &$info['path'],
                    //用例案例名称
                    'title'  => &$test['title'],
                    //用例键名
                    'name'   => &$key,
                ));

                //成功 || 记录错误数量
                $temp['code'] < 400 || $info['info']['error'] += 1;
                //执行成功, 进度推进
                $info['exec'] += 1;

                //记录进度到k-v
                of_base_com_kv::set($cKey, array(
                    'exec'  => $info['exec'],
                    'count' => $info['count'],
                    'info'  => $info['info'],
                ), 86400, '_ofSelf');
            //用例库执行完成
            } else {
                //标记执行结束
                $info['mode'] = -1;
                //重置执行进度
                $info['exec'] = 0;
                //结束计划任务
                break ;
            }

            //用例清单配置
            of_base_com_disk::each($path, $data, false);

            if (
                //配置数量有变化
                count($dirs) !== count($data) ||
                //加载代码有变化
                of_base_com_timer::renew()
            ) {
                //重载计划任务
                break ;
            } else {
                foreach ($data as $k => &$v) {
                    //新增配置 || 配置被修改
                    if (!isset($dirs[$k]) || filemtime($k) !== $dirs[$k]['time']) {
                        //重载计划任务
                        break 2;
                    }
                }
            }
        } while (true);

        //保存进度信息
        self::saveInfo();

        //设置重新执行
        $params['try'][0] = 0;
        //执行重试
        return false;
    }

    /**
     * 描述 : 测试计划任务异常退出抛错
     * 作者 : Edgar.lee
     */
    public static function ofHalt() {
        //引用配置信息 && 存在异常退出
        if ($info = &self::$info) {
            //记录日志
            self::saveLogs(array(
                'code' => 500,
                'info' => 'Callback function "exit" unexpectedly.',
                'data' => array()
            ), $info['params']);

            //跳过失败案例
            $info['exec'] += 1;
            //保存进度
            self::saveInfo();

            //一分钟后重启
            of_base_com_timer::task(array(
                'call' => 'of_base_test_case::task',
                'cNum' => 1,
                'time' => 60
            ));
        }
    }

    /**
     * 描述 : 工作调用测试用例
     * 作者 : Edgar.lee
     */
    private static function callTest($func, $params = null) {
        try {
            of::work(null, '', null);

            //执行测试用例
            $result = of::callFunc($func);

            //判断返回结构是否为{"code" : int, "info" : str, "data" : arr}
            $temp = is_array($result) && count($result) === 3 &&
                isset($result['code'], $result['info'], $result['data']) &&
                is_int($result['code']) && is_string($result['info']);

            //非工作格式转成标准格式
            $temp === false && $result = array(
                'code' => $result === true ? 200 : 400,
                'info' => '',
                'data' => $result
            );

            //响应结果
            $result['code'] < 400 || of::work($result['code'], $result['info'], $result['data']);

            of::work(false);
        } catch (Exception $e) {
            $result = of::work($e);
        } catch (Error $e) {
            $result = of::work($e);
        }

        //记录错误日志 (记录日志 && 发生错误)
        $params && $result['code'] >= 400 && self::saveLogs($result, $params);

        return $result;
    }

    /**
     * 描述 : 保持错误日志
     * 作者 : Edgar.lee
     */
    private static function saveLogs($result, $params) {
        $lData = array(
            //用例案例路径
            'path'  => $params['path'],
            //用例案例名称
            'title' => $params['title'],
            //用例键名
            'name'  => $params['name'],
            //测试结果
            'state' => $result['code'] < 400,
            //调用结果
            'info'  => of_base_com_data::json($result),
            //当前时间
            'time'  => time()
        );

        //日志路径
        $lPath = ROOT_DIR . OF_DATA . '/_of/of_base_test/logs' .
            date('/Y/m/d', $lData['time']) . 'log';
        //错误的分组明细路径
        $ePath = $lPath . 'Attr/group/' . $params['group'] . '.bin';
        //追加方式打开日志
        $handle = &of_base_com_disk::file($lPath . 'Data.php', null, null);

        //已写入日志
        if ($temp = ftell($handle)) {
            //日志当前偏移
            $lSize = str_pad(
                //日志大小转换(十进制字节=>8位36进制)
                base_convert($temp, 10, 36),
                8, '0', STR_PAD_LEFT
            );
        //新日志文件
        } else {
            //写入保护代码
            fwrite($handle, '<?php exit; ?> ');
            //删除索引数据
            of_base_com_disk::delete($lPath . 'Attr');
            //日志起始偏移
            $lSize = '0000000f';
        }

        //首次添加到分组概要日志
        is_file($ePath) || of_base_com_disk::file(
            $lPath . 'Attr/group.bin', $lSize . $params['group'], null
        );

        //日志文本数据
        $lText = strtr(serialize($lData), array(
            "\r"   => "+\2+",
            "\n"   => "+\1+"
        )) . "\n";

        //写入日志成功
        if (of_base_com_disk::file($handle, $lText, null)) {
            //记录索引日志
            of_base_com_disk::file($lPath . 'Attr/index.bin', $lSize, null);
            //记录分组明细日志
            of_base_com_disk::file($ePath, $lSize, null);
        }

        //释放连接源
        $handle = null;

        //日志有时限 && 1%的机会清理
        if (rand(0, 99) === 1) {
            //日志生命期
            $gcTime = $lData['time'] - 30 * 86400;
            //执行清理
            $path = ROOT_DIR . OF_DATA . '/_of/of_base_test/logs';

            //文件遍历成功
            if (of_base_com_disk::each($path, $data, false)) {
                foreach ($data as $k => &$v) {
                    //是文件 && 文件已过期
                    if ($v === false && filectime($k) <= $gcTime) {
                        //删除文件及父空文件夹
                        of_base_com_disk::delete($k, true);
                    }
                }
            }
        }
    }

    /**
     * 描述 : 保存进度信息
     * 作者 : Edgar.lee
     */
    private static function saveInfo() {
        //引用配置信息
        $info = &self::$info;
        //加锁读取配置信息
        $fp = of_base_com_disk::file($info['iDir'], null, null);
        $data = of_base_com_disk::file($fp, true, true);

        //更新进度信息
        $data['exec'] = $info['exec'];
        $data['count'] = $info['count'];

        //当前用例执行结束
        if ($info['mode'] === -1) {
            //当前时间
            $time = time();
            //记录完成进度
            $data['info'] = $info['info'];
            //记录完成时间
            $data['done'] = date('Y-m-d H:i:s', $time);
            //删除缓存
            of_base_com_kv::del($info['cKey'], '_ofSelf');

            //是启动状态
            if ($data['mode'] & 2) {
                //自动循环
                if ($data['mode'] & 1) {
                    //更新下次执行时间
                    $data['start'] = date('Y-m-d 00:00:00', $time + 86400);
                //设置停止
                } else {
                    $data['mode'] -= 2;
                }
            //去掉暂停转为停止
            } else {
                $data['mode'] &= ~6;
            }
        }

        //回写配置信息
        of_base_com_disk::file($fp, $data, true);
        //清理句柄
        $fp = $info = null;
    }
}