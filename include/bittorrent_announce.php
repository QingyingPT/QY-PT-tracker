<?php
define('IN_TRACKER', true);

//error_reporting(E_ERROR | E_PARSE);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$rootpath = realpath(dirname(__FILE__) . '/..') . "/";

$old_mysql_link = null;

include($rootpath . 'config/userlevel.php');
include($rootpath . 'config/allconfig.php');

include($rootpath . 'include/cache.php');
include($rootpath . 'include/config.php');
include($rootpath . 'include/functions_announce.php');

$Cache = new CACHE();

define('TIMENOW', time());

ignore_user_abort(1);
@set_time_limit(60);

