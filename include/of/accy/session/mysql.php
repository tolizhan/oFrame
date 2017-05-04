<?php
/**
 * 描述 : mysql方式存储session
 * 注明 : 创建表结构 CREATE TABLE `_of_base_session` (
 *           `hash` char(50) NOT NULL DEFAULT '' COMMENT 'SESSIONID',
 *           `data` mediumtext NOT NULL COMMENT '存储数据',
 *           `time` timestamp NOT NULL DEFAULT '1971-01-01 00:00:00' COMMENT '时间戳',
 *           PRIMARY KEY (`hash`),
 *           KEY `根据时间查询过期会话` (`time`) USING BTREE
 *       ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='框架SESSION存储列表';
 * 作者 : Edgar.lee
 */
class of_accy_session_mysql extends of_base_session_base {

    protected static function _read(&$sessionId, &$data) {
        of_db::pool(
            'of_accy_session_mysql', 
            of_db::pool(of::config('_of.session.params.dbPool', 'default'))
        );

        of_db::sql(null, 'of_accy_session_mysql');

        $sql = "SELECT
            `data`
        FROM
            `_of_base_session`
        WHERE
            `hash` = '{$sessionId}'
        FOR UPDATE";
        $temp = &of_db::sql($sql, 'of_accy_session_mysql');

        if (empty($temp)) {
            $sql = "INSERT INTO `_of_base_session` (
                `hash`, `data`, `time`
            ) VALUES 
                '{$sessionId}', '', NOW()";
            of_db::sql($sql, 'of_accy_session_mysql');
            $data = '';
        } else {
            $data = $temp[0]['data'];
        }
    }

    protected static function _write(&$sessionId, &$data) {
        $temp = addslashes($data);

        $sql = "UPDATE 
            `_of_base_session` 
        SET 
            `data` = '{$temp}',
            `time` = NOW() 
        WHERE 
            `hash` = '{$sessionId}'";
        of_db::sql($sql, 'of_accy_session_mysql');
    }

    protected static function _destroy(&$sessionId) {
        $sql = "DELETE FROM 
            `_of_base_session`
        WHERE 
            `hash` = '{$sessionId}'";
        of_db::sql($sql, 'of_accy_session_mysql');
    }

    protected static function _gc($maxlifetime) {
        $maxlifetime = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] - $maxlifetime);
        $sql = "DELETE FROM 
            `_of_base_session`
        WHERE 
            `time` < '{$maxlifetime}'";
        of_db::sql($sql, 'of_accy_session_mysql');
    }

    protected static function _open() {
    }

    protected static function _close() {
        of_db::sql(true, 'of_accy_session_mysql');
    }
}