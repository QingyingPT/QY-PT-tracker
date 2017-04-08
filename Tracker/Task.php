<?php namespace Tracker;

use Tracker\Config;
use Tracker\Task\UpdatePeers;
use Tracker\Task\UpdateTorrent;
use Tracker\Task\ProcessTraffic;

class Task {
  private $sql = NULL;

  function __construct() {
    $this->sql = old_get_mysql_link();
  }

  function throwSQLError($err) {
    throw new \RuntimeException($err . "\n SQL Error [" . $this->sql->errno . "]: " . $this->sql->error);
  }

  function dotask ($name, $flag, $info, $func) {
    if ($flag) {
      print("\n$info ...\n");
      $timeStart = microtime(true);

      $result = $func() ? : 'DONE';

      $this->sql->query("UPDATE tracker_schedule SET last_action = CURRENT_TIMESTAMP WHERE name = '$name'")
        or $this->throwSQLError('UPDATE Error');

      $time = round((microtime(true) - $timeStart) * 1000);

      print("\n$info use $time ms. result: $result\n");

      ob_flush();
      flush();
    }
  }

  public function start () {
    $res = $this->sql->query("SELECT name FROM tracker_schedule WHERE (UNIX_TIMESTAMP() - (unit * val)) > UNIX_TIMESTAMP(last_action)")
      or $this->throwSQLError('SELECT Error');

    if ($res->num_rows == 0) {
      return;
    }

    $arr = [];
    while($row = $res->fetch_row()) {
      $arr[] = $row[0];
    }

    // clean old peers
    $this->dotask('peer', in_array('peer', $arr), 'clean old peers', function () {
      $difftime = Config::$annInterval * 3;
      $peers = new UpdatePeers();
      if (($num = $peers->cleanOldPeers($difftime)) > 0) {
        print("delete $num old peers\n");
      } else {
        print("no old peers\n");
      }
    });

    // update torrent info
    $this->dotask('torrent', in_array('torrent', $arr), 'update torrent info', function () {
      $torrent = new UpdateTorrent();
      if (($num = $torrent->updatePeers()) > 0) {
        print("update $num torrents\n");
      } else {
        print("no peers activated\n");
      }
    });

    // force traffics expiration
    $this->dotask('traffic', in_array('traffic', $arr), 'update traffic info', function () {
      $process = new ProcessTraffic();
      return $process->start();
    });

    // check cheater -> traffic
    // check cheater -> time
    // check cheater -> multiple client
  }
}
