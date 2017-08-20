<?php
require_once 'autoload.php';

use Tracker\SQL;

require_once 'include/bittorrent_announce.php';
require_once 'shutdown.php';
require('login.php');

$user = login();

if ($user == false) {
  trigger_error('403', E_USER_ERROR);
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

    if ($this->sql->affected_rows == 0) {
      return '';
    }

    return $key;
  }

  function initBonus($user) {
    // TODO: calculate initial bonus
    $level = $user['class'];
    $initBonus = ($level >= UC_VIP ? 10 : 150) * $level;
    if ($level >= UC_VIP && $level < UC_CODER) {
      $initBonus *= 3.5;
    }

    $res = $this->sql->query("SELECT id FROM tracker_bonus WHERE id = '$user[id]'")
      or $this->throwSQLError('SELECT error');

    if ($res->num_rows > 0) {
      return true;
    } else {
      $this->sql->query("INSERT INTO tracker_bonus (id, bonus) VALUES ('$user[id]', '$initBonus')");
      if ($this->sql->affected_rows > 0) {
        return true;
      }
    }

    return false;
  }
}

$type = $_REQUEST['type'] ?? 'snatch';

$migrate = new Migrate();

if (false && $type == 'snatch') {
  $key = $migrate->snatchToV2($user['id']);
  if ($key) {
    print json_encode([
      'link' => '/tracker/announce.php',
      'passkey' => $user['passkey'],
      'key' => $key,
    ]);
    exit();
  } else {
    trigger_error('404', E_USER_ERROR);
    exit();
  }
} elseif (false && $type == 'bonus') {
  print json_encode(['result' => $migrate->initBonus($user)]);
  exit();
}

trigger_error('400', E_USER_ERROR);
