<?php
require_once 'autoload.php';

use Tracker\Task;

require_once('include/bittorrent_announce.php');
dbconn_announce();

$sqlLink = old_get_mysql_link();

function esc($str) {
  global $sqlLink;
  return $sqlLink->real_escape_string($str);
}

ob_flush();
flush();

$stt = microtime(true);
print("start timing task...\n");

Task::start();

$end = microtime(true);
print("end timing tasks\n");

exit();
