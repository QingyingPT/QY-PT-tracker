<?php namespace Tracker\Task;
// TODO: validate traffic
// TODO: update user data
// TODO: log abnormal traffic

use Tracker\Config;
use Tracker\Exception\Normal as NormalException;

class ProcessTraffic {
  static $queryfields = [
    'id',
    'torrent',
    'userid',
    'during',
    'rest_up as up',
    'rest_dl as dl',
    'rest_up',
    'rest_dl',
    // 'seeder',
    // 'last_action',
  ];

  private $sql = NULL;

  function __construct() {
    $this->sql = old_get_mysql_link();
  }

  function balanceTraffic(&$bucketUp, &$bucketDl) {
    $i = 0;
    $j = 0;
    $leup = count($bucketUp);
    $ledl = count($bucketDl);
    $restUp = 0;
    $restDl = 0;

    while ($i < $leup && $j < $ledl) {
      $restUp += $bucketUp[$i]['up'];
      $restDl += $bucketDl[$j]['dl'];
      if ($restUp < $restDl) {
        $bucketUp[$i]['up'] = 0;
        $bucketDl[$j]['dl'] = $restDl - $restUp;
        $restDl -= $restUp + $bucketDl[$j]['dl'];
        $restUp = 0;
        $i++;
      } else {
        $bucketUp[$i]['up'] = $restUp - $restDl;
        $bucketDl[$j]['dl'] = 0;
        $restUp -= $restDl + $bucketUp[$i]['up'];
        $restDl = 0;
        $j++;
      }
    }
  }

  function updateBucket(&$bucket) {
    // generator update statement
    // TODO: later update
    $updates = array_filter($bucket, function ($row) {
      return ($row['rest_dl'] != $row['dl']) || ($row['rest_up'] != $row['up']);
    });

    if (count($updates) > 0) { // > 1
      $updateStatement = '(' .
        implode('),(',
          array_map(function ($row) {
            return "'" . implode("','", [
              $row['id'],
              $row['up'],
              $row['dl'],
            ]) . "'";
          }, $updates)
        )
        . ')';

      $this->sql->query("INSERT INTO tracker_traffic (id, rest_up, rest_dl) VALUES $updateStatement"
        . " ON DUPLICATE KEY UPDATE rest_up=VALUES(rest_up), rest_dl=VALUES(rest_dl)"
      );
      if ($this->sql->affected_rows > 0) {
        // TODO: / 2 ? affected rows ??
        print "UPDATE " . $this->sql->affected_rows . " rows\n";
      } else {
        $this->throwSQLError('Upate Error');
      }
    }
  }

  function start () {
    // incomplete
    $annInterval = Config::$annInterval;
    $where = implode(' AND ', [
      "is_old = false",
      "invalid = false",
      "(rest_up > 0 OR rest_dl > 0)",
      "last_action >  '" . esc(date('Y-m-d H:i:s', TIMENOW - $annInterval * 5)) . "'",
      "last_action <= '" . esc(date('Y-m-d H:i:s', TIMENOW - $annInterval * 2)) . "'",
    ]);

    $res = $this->sql->query("SELECT " . implode(',', static::$queryfields) . " FROM tracker_traffic WHERE $where ORDER BY torrent ASC, last_action ASC");
    if (!$res) {
      $this->throwSQLError('Query Error');
    }

    $lastTid = 0;
    $bucket = [];
    $bucketUp = [];
    $bucketDl = [];
    while ($row = $res->fetch_assoc()) {
      $tid = $row['torrent'];
      if ($lastTid && $lastTid != $tid) {
        $this->balanceTraffic($bucketUp, $bucketDl);
        $this->updateBucket($bucket);

        $bucket = [];
        $bucketUp = [];
        $bucketDl = [];
      }
      $lastTid = $tid;

      // TODO: check limit
      if (!isset($row['invalid'])) {
        $bucket[] = $row;
        if ($row['up'] > 0) {
          $bucketUp[] = &$bucket[count($bucket) - 1];
        }
        if ($row['dl'] > 0) {
          $bucketDl[] = &$bucket[count($bucket) - 1];
        }
      } else {
        // TODO: log
      }
    }

    $this->balanceTraffic($bucketUp, $bucketDl);
    $this->updateBucket($bucket);

    return 'DONE';
  }

  function throwSQLError($err) {
    throw new \RuntimeException($err . "\n SQL Error [" . $this->sql->errno . "]: " . $this->sql->error);
  }
}
