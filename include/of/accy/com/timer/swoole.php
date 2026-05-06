<?php
/**
 * 描述 : 任务swoole模式
 * 作者 : Edgar.lee
 */
class of_accy_com_timer_swoole {
    //异步任务模式, 启动新任务将用的模式
    private static $forkMode = 4;

    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        //获取任务信息
        $info = &of_base_com_timer::info(4);
        //任务模式, 当前是批量任务 ? 继承协程启动模式 : (在异步任务中 ? 工作协程 : 共享进程)
        self::$forkMode = isset($info['cArg']['type']) && $info['cArg']['type'] & 16 ?
            $info['cArg']['swooleForkMode'] : (join('::', of::dispatch()) === 'swoole::fork' ? 1 : 4);
    }
    /**
     * 描述 : 运行异步任务
     * 作者 : Edgar.lee
     */
    public static function fork($params) {
        //协程任务, 在共享进程中执行
        if ($params['params'][1]['type'] & 24) {
            //记录异步任务模式
            $params['params'][1]['swooleForkMode'] = self::$forkMode;
            //根据任务模式, 运行异步任务
            swoole::fork($params, self::$forkMode);
        //独立进程方式执行
        } else {
            swoole::fork($params, 2);
        }
    }

    /**
     * 描述 : 异步任务数据
     * 作者 : Edgar.lee
     */
    public static function data($mark, $data) {
        //生成完整标识
        $name = 'of_accy_com_timer_swoole::data#' . $mark;
        //获取任务信息
        $info = &of_base_com_timer::info(4);

        //共享数据模式
        if (
            //当前任务正在回写数据 ? 当前任务通过工作协程模式启动的 : 启动新任务将用工作协程模式
            isset($info['cArg']['mark']) && $info['cArg']['mark'] === $mark ?
                $info['cArg']['swooleForkMode'] === 1 : self::$forkMode === 1
        ) {
            //操作共享数据
            $result = &swoole::data($name, $data);
            //修改任务数据
            is_array($data) && $result = $data;
            //返回结果
            return $result;
        //K-V模式, 修改任务数据
        } else if (is_array($data)) {
            of_base_com_kv::set($name, $data, 86400, '_ofSelf');
        //K-V模式, 获取或删除任务数据
        } else {
            return $data ? of_base_com_kv::get($name, null, '_ofSelf') : of_base_com_kv::del($name, '_ofSelf');
        }
    }
}

//初始化
of_accy_com_timer_swoole::init();
