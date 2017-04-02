<?php namespace Tracker\Task;

use Tracker\Config;
use Tracker\Exception\Normal as NormalException;

class ProcessTraffic {
  static $queryfields = ['id', 'torrent', 'userid', 'during', 'up', 'dl', 'rest_up', 'rest_dl', 'seeder', 'last_action'];

  private $bucket = [];

  function __construct() {
    $annInterval = Config::$annInterval;
    $sqlLink = old_get_mysql_link();
    $where = implode(' AND ', [
      "is_old = false",
      "invalid = false",
      "(rest_up > 0 OR rest_dl > 0)",
      // "last_action >  '" . esc(date('Y-m-d H:i:s', TIMENOW - $annInterval * 5)) . "'",
      // "last_action <= '" . esc(date('Y-m-d H:i:s', TIMENOW - $annInterval * 2)) . "'",
    ]);

    $res = $sqlLink->query("SELECT " . implode(',', self::$queryfields) . " FROM tracker_traffic WHERE $where ORDER BY torrent ASC, last_action ASC");
    if (!$res) {
      throw new \RuntimeException('SQL Error ' . $sqlLink->errno . ': ' . $sqlLink->error);
    }

    while ($row = $res->fetch_assoc()) {
      $tid = $row['torrent'];
      if (!isset($bucket[$tid])) {
        $this->bucket[$tid] = [];
      }
      $this->bucket[$tid][] = $row;
    }
  }

  function start () {
    // incomplete
    // print json_encode($this->bucket);

    return 'DONE';
  }
}
