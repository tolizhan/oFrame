<?php
include dirname(__FILE__) . '/of.php';

//仅允许访问框架类
if (isset($_GET['c']) && strncmp('of_', $_GET['c'], 3) === 0) {
    of::dispatch($_GET['c'], isset($_GET['a']) ? $_GET['a'] : 'index', true);
}