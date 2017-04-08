<?php namespace Tracker\Task;
// TODO: validate traffic
// TODO: update user data
// TODO: log abnormal traffic
// TODO: avoid update statement too long. limit 30/time

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

  static function statisticUser($traffic, $userUp, $userDl, &$users) {
    if (!isset($users[$userUp])) {
      $users[$userUp] =  [
        'id' => $userUp,
        'up' => $traffic,
        'dl' => 0,
      ];
    } else {
      $users[$userUp]['up'] += $traffic;
    }
    if (!isset($users[$userDl])) {
      $users[$userDl] =  [
        'id' => $userDl,
        'up' => 0,
        'dl' => $traffic,
      ];
    } else {
      $users[$userDl]['dl'] += $traffic;
    }
    
  }

  static function balanceTraffic(&$bucketUp, &$bucketDl, &$users) {
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

        static::statisticUser($restUp, $bucketUp[$i]['userid'], $bucketDl[$j]['userid'], $users);

        $restDl -= $restUp + $bucketDl[$j]['dl'];
        $restUp = 0;
        $i++;
      } else {
        $bucketUp[$i]['up'] = $restUp - $restDl;
        $bucketDl[$j]['dl'] = 0;

        static::statisticUser($restDl, $bucketUp[$i]['userid'], $bucketDl[$j]['userid'], $users);

        $restUp -= $restDl + $bucketUp[$i]['up'];
        $restDl = 0;
        $j++;
      }
    }
  }

  static function formula ($up, $dl, $n) {
    // TODO: bonus formula
    return 100 * ($up - $dl) / 1073741824 * (1 + 1 / log10(max(10, $n)));
  }

  function __construct() {
    $this->sql = old_get_mysql_link();
  }

  function updateBucket(&$bucket) {
    // generator update statement
    // TODO: later update
    $updates = array_filter($bucket, function ($row){
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
        print "UPDATE " . $this->sql->affected_rows . " rows' traffics\n";
      } else {
        $this->throwSQLError('Upate Error');
      }
    }
  }

  function updateUser(&$set) {
    $updates = array_filter($set, function ($row) {
      return ($row['up'] != 0 || $row['dl'] != 0);
    });

    $amount = count($updates);

    if ($amount > 0) { // > 1
      $updateStatement = '(' .
        implode('),(',
          array_map(function ($row) use ($amount) {
            return "'$row[id]', '$row[up]', '$row[dl]', '"
              . static::formula($row['up'], $row['dl'], $amount)
              . "'";
          }, $updates)
        )
        . ')';
      $this->sql->query("INSERT INTO tracker_bonus (id, up, dl, bonus) VALUES $updateStatement"
        . " ON DUPLICATE KEY UPDATE up=up+VALUES(up), dl=dl+VALUES(dl), bonus=bonus+VALUES(bonus)"
      );
      if ($this->sql->affected_rows > 0) {
        print "UPDATE " . $this->sql->affected_rows . " users' bonus\n";
      } else {
        $this->throwSQLError('Update user bonus ERROR');
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
    $usersUpdateSet = [];
    $bucket = [];
    $bucketUp = [];
    $bucketDl = [];
    while ($row = $res->fetch_assoc()) {
      $tid = $row['torrent'];
      if ($lastTid && $lastTid != $tid) {
        static::balanceTraffic($bucketUp, $bucketDl, $usersUpdateSet);
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

    static::balanceTraffic($bucketUp, $bucketDl, $usersUpdateSet);
    $this->updateBucket($bucket);

    // update user bonus
    $this->updateUser($usersUpdateSet);

    return 'DONE';
  }

  function throwSQLError($err) {
    throw new \RuntimeException($err . "\n SQL Error [" . $this->sql->errno . "]: " . $this->sql->error);
  }
}
