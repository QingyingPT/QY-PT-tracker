<?php
require_once 'autoload.php';

use Tracker\User\Details as UserDetails;

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
} elseif ($info == 'tracker') {
  $d = new UserDetails();
  $data = $d->getTrackerInfo($user['id']);
} elseif ($info == 'unread') {
  $d = new UserDetails();
  $data = $d->getUnreadCount($user['id']);
}

print json_encode($data);
exit();
