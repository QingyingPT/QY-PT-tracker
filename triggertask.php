<?php
// TODO: check permission
require_once 'autoload.php';

use Tracker\Task;

require_once('include/bittorrent_announce.php');
require('login.php');

$user = login();

if (!$user) {
  $ip = getip();
  if ($ip != '::1') {
    header('HTTP/1.1 403 Forbidden');
    exit();
  }
} elseif($user['class'] < 'UC_SYSOP') {
  header('HTTP/1.1 403 Forbidden');
  exit();
}

$sqlLink = get_mysql_link();

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
