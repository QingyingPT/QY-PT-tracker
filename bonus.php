<?php
require_once 'autoload.php';

use Tracker\Bonus;
use Tracker\Traffic;

require 'include/bittorrent_announce.php';
require 'shutdown.php';
require 'login.php';

function getParam($name) {
  return $_REQUEST[$name] ?? '';
}

function calcRatio($n) {
  return pow(8, max(1, 20000000000 / $n)) / 2;
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
} else if ($method == 'exchange') {
  $n = 0 + intval(getParam('n'));
  $heal = getParam('heal');

  if ($n < 100) {
    trigger_error('Can\' less than 100', E_USER_ERROR);
    exit();
  }

  $b = new Bonus();

  if ($heal) {
    if (!$totalBonus = $Cache->get_value("tracker-total-bonus")) {
      $totalBonus = $b->getTotalBonus();
      $Cache->cache_value("tracker-total-bonus", $totalBonus, 1801);
    }
    // $totalHP = $b->getTotalHP();

    $ratio = calcRatio($totalBonus);

    $myBonus = $b->getBonus($user['id']);

    $bonusCost = ceil($n * $ratio);

    if ($bonusCost > $myBonus) {
      $result = ['n' => 0, 'cost' => 0];
    } else {
      $b->updateBonus($user['id'], -$bonusCost);
      $b->updateHP($user['id'], $n);
      $result = ['n' => $n, 'cost' => $bonusCost];
    }
  } else {
    $myHP = $b->getHP($user['id']);
    $hpCost = ceil($n / 1.414);

    if ($hpCost > $myHP) {
      $result = ['n' => 0, 'cost' => 0];
    } else {
      $b->updateHP($user['id'], -$hpCost);
      $b->updateBonus($user['id'], $n);
      $result = ['n' => $n, 'cost' => $hpCost];
    }
  }

  print json_encode($result);
  exit();
}

