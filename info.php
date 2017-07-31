<?php
require_once 'autoload.php';

use Tracker\User\Details as UserDetails;
use Tracker\Task\UpdatePeers as UpdatePeersTask;
use Tracker\Config as Config;

require 'include/bittorrent_announce.php';
require 'shutdown.php';
require 'login.php';

function getParam($name) {
  return $_REQUEST[$name] ?? '';
}

$user = login();

if ($user == false) {
  trigger_error('403', E_USER_ERROR);
  exit();
}

$info = getParam('info');
$type = getParam('type');

$data = null;

if ($info == 'user' && $type) {
  $d = new UserDetails();
  $data = $d->getUserInfo($user['id'], $type, true);
} elseif ($info == 'tracker' && $type == 'clean') {
  $t = new UpdatePeersTask();
  $data = $t->cleanOldPeers(Config::$annInterval * 3, $user['id']);
} elseif ($info == 'tracker') {
  $d = new UserDetails();
  $data = $d->getTrackerInfo($user['id']);
} elseif ($info == 'unread') {
  $d = new UserDetails();
  $data = $d->getUnreadCount($user['id']);
} elseif ($info == 'sign' && $type == 'view') {
  $d = new UserDetails();
  $data = $d->getSigninInfo($user['id']);
}

print json_encode($data);
exit();
