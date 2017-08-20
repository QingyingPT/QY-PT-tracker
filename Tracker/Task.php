<?php namespace Tracker;

use Tracker\SQL;
use Tracker\Config;
use Tracker\Task\UpdatePeers;
use Tracker\Task\UpdateTorrent;
use Tracker\Task\ProcessTraffic;

class Task extends SQL {
  function dotask($name, $flag, $info, $func) {
    if ($flag) {
      print("\n$info ...\n");
      $timeStart = microtime(true);

      $result = $func() ?: 'DONE';

      $this->sql->query("UPDATE tracker_schedule SET last_action = CURRENT_TIMESTAMP WHERE name = '$name'")
        or $this->throwSQLError('UPDATE Error');

      $time = round((microtime(true) - $timeStart) * 1000);

      print("\n$info use $time ms. result: $result\n");

      ob_flush();
      flush();
    }
  }

  public function start() {
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
      $difftime = Config::$peerReserve;
      $peers = new UpdatePeers();
      if (($num = $peers->cleanOldPeers($difftime)) > 0) {
        print("delete $num dead peers\n");
      } else {
        print("no dead peers\n");
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
      $process->updateTraffic();
      $process->updateSeedTime();
      return 'DONE';
    });

    // check cheater -> traffic
    // check cheater -> time
    // check cheater -> multiple client
    // count client agents
    // count inactive torrents
  }
}
