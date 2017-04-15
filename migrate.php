<?php
require_once 'autoload.php';

use Tracker\SQL;

require_once 'include/bittorrent_announce.php';
require_once 'shutdown.php';
dbconn_announce();
require('login.php');

$user = login();

if ($user == false) {
  print json_encode(['error' => 403]);
  exit();
}

class Migrate extends SQL {
  function snatchToV2($userid) {
    $key = md5(rand());
    $insertStr = "INSERT INTO tracker_snatch (torrent, userid, downloadkey, finishdat)";
    $selectStr = "SELECT DISTINCT(torrent), '$userid', '$key', 0 FROM peers WHERE userid = '$userid'";
    $this->sql->query("$insertStr $selectStr");

    if ($this->sql->error) {
      $this->throwSQLError('INSERT error');
      return '';
    }

    if ($this->sql->affected_row == 0) {
      return '';
    }

    return $key;
  }
}

$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'snatch';

$migrate = new Migrate();

if ($type === 'snatch') {
  $key = $migrate->snatchToV2($user['id']);
  if ($key) {
    print json_encode([
      'link' => '/tracker/announce.php',
      'passkey' => $user['passkey'],
      'key' => $key,
    ]);
    exit();
  } else {
    print json_encode(['error' => 404]);
    exit();
  }
}

die('{}');
