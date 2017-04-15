<?php
// TODO: check permission
require_once 'autoload.php';

use Tracker\Task;

require_once('include/bittorrent_announce.php');
dbconn_announce();
require('login.php');

$user = login();

if (!$user || $user['class'] < 'UC_SYSOP') {
  header('HTTP/1.1 403 Forbidden');
  exit();
}

$sqlLink = old_get_mysql_link();

function esc($str) {
  global $sqlLink;
  return $sqlLink->real_escape_string($str);
}

ob_flush();
flush();

$stt = microtime(true);
print("start timing task...\n");

$task = new Task();
$task->start();

$end = microtime(true);
print("\nend timing tasks\n");

exit();
