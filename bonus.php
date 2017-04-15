<?php
require_once 'autoload.php';

use Tracker\Bonus;
use Tracker\Traffic;

require_once('include/bittorrent_announce.php');
require_once 'shutdown.php';
dbconn_announce();
require('login.php');

function getParam ($name) {
  return isset($_REQUEST[$name])
    ? $_REQUEST[$name]
    : '';
}

$user = login();

if ($user == false) {
  trigger_error('403', E_USER_ERROR);
  exit();
}

$torrent = 0 + intval(getParam('torrent'));

$method = getParam('method') ? : 'query';

$yesterday = strtotime('-1 day');
$lastMonth = $yesterday - Traffic::DefaultTimeGap;

if ($method == 'query') {
  $t = new Traffic();
  $traffics = $t->getUserAvailableTraffic($user['id'], $torrent, $lastMonth, $yesterday);

  print json_encode([
    'stt' => $lastMonth,
    'end' => $yesterday,
    'traffics' => $traffics,
  ]);
  exit();
} else if ($method == 'update') {
  $t = new Traffic();
  $traffics = $t->getUserAvailableTraffic($user['id'], $torrent, $lastMonth, $yesterday);
  $result = $t->updateUserTrafficStatus($user['id'], $torrent, $lastMonth, $yesterday);
  if ($result != false) {
    print json_encode(['error' => $result]);
    die();
  }

  $b = new Bonus();
  $result = $b->updateUserSeedBonus($user['id'], $traffics);

  if ($result != 1 && $result != 0) {
    // TODO: log error
    $result = [$result, 'please connact admin'];
    print json_encode(['error' => $result]);
    die();
  }
  $updateResult = $b->result;

  print json_encode([
    'stt' => $lastMonth,
    'end' => $yesterday,
    'traffics' => $traffics,
    'bonus' => $updateResult['bonus'],
    'sum' => $updateResult['sum'],
  ]);
  exit();
}
