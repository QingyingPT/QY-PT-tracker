<?php
require_once 'autoload.php';

use Tracker\Peer;

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

$uid = 0 + intval(getParam('user'));
$torrent = 0 + intval(getParam('torrent'));
$type = getParam('type') === 'seed' ? 'seed' : 'leech';
$page = max(0, 0 + intval(getParam('page')));

$Peer = new Peer();
if (!!$torrent) {
  if (!$list = $Cache->get_value("tracker-torrent-peers-$type-$page")) {
    $list = $Peer->getTorrentPeers($torrent, $type, $page);

    $Cache->cache_value("tracker-torrent-peers-$type-$page", $list, 600);
  }
} else if (!!$uid) {
  if ($user['id'] != $uid && $user['class'] < UC_UPLOADER) {
    trigger_error('403', E_USER_ERROR);
    exit();
  }

  // if (!$list = $Cache->get_value("tracker-user-peers-$type-$page")) {
  //   $list = $Peer->getUserPeers($uid, $type, $page);

  //   $Cache->cache_value("tracker-user-peers-$type-$page", $list, 600);
  // }

  $list = $Peer->getUserPeers($uid, $type, $page);
} else {
  $list = [];
}

print json_encode($list);

exit();
