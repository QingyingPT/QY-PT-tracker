<?php
require_once('include/bittorrent_announce.php');
dbconn_announce();

$sqlLink = old_get_mysql_link();

function esc($str) {
  global $sqlLink;
  return $sqlLink->real_escape_string($str);
}


require('./task.php');

ob_flush();
flush();

$stt = microtime(true);
print("start timing task...\n");

doClean();

$end = microtime(true);
print("end timing tasks\n");

exit();
