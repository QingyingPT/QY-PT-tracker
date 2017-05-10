<?php namespace Tracker\Task;
// TODO: validate traffic
// TODO: log abnormal traffic
// TODO: avoid update statement too long. limit 30/time

use Tracker\SQL;
use Tracker\Config;
use Tracker\SQLHelper;
use Tracker\Exception\Normal as NormalException;

class ProcessTraffic extends SQL {
  use SQLHelper;

  static $queryfields = [
    'id',
    'torrent',
    'userid',
    'during',
    'seeder',
    'rest_up as up',
    'rest_dl as dl',
    'rest_up',
    'rest_dl',
    // 'seeder',
    // 'last_action',
  ];

  static function defineUser($uid, &$users, $cb = NULL) {
    if (!isset($users[$uid])) {
      $users[$uid] = [
        'id' => $uid,
        'up' => 0,
        'dl' => 0,
        'seed' => 0,
        'leech' => 0,
      ];
    }

    if ($cb) $cb($users[$uid]);
  }

  static function accUserTraffic($traffic, $userUp, $userDl, &$users) {
    static::defineUser($userUp, $users, function (&$user) use ($traffic) {
      $user['up'] += $traffic;
    });

    static::defineUser($userDl, $users, function (&$user) use ($traffic) {
      $user['dl'] += $traffic;
    });
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
      $bu = $bucketUp[$i];
      $bd = $bucketDl[$j];

      if ($restUp < $restDl) {
        $bucketUp[$i]['up'] = 0;
        $bucketDl[$j]['dl'] = $restDl - $restUp;

        static::accUserTraffic($restUp, $bucketUp[$i]['userid'], $bucketDl[$j]['userid'], $users);

        $restDl -= $restUp + $bucketDl[$j]['dl'];
        $restUp = 0;
        $i++;
      } else {
        $bucketUp[$i]['up'] = $restUp - $restDl;
        $bucketDl[$j]['dl'] = 0;

        static::accUserTraffic($restDl, $bucketUp[$i]['userid'], $bucketDl[$j]['userid'], $users);

        $restUp -= $restDl + $bucketUp[$i]['up'];
        $restDl = 0;
        $j++;
      }
    }
  }

  static private function formulaTime($chunk, $weight) {
    return round($chunk * $weight);
  }

  static private function formulaTraffic($up, $dl, $n) {
    // TODO: bonus formula
    return round(100 * ($up - $dl) / 1073741824 * (1 + 1 / log10(max(10, $n))));
  }

  // TODO: migrate to Tracker\Traffic
  function updateBucket(&$bucket) {
    // generator update statement
    // TODO: later update
    $updates = array_filter($bucket, function ($row){
      return ($row['rest_dl'] != $row['dl']) || ($row['rest_up'] != $row['up']);
    });

    if (count($updates) > 0) { // > 1
      $this->sql->query(static::genBatchUpdateSql(
        'tracker_traffic',
        ['id', 'rest_up', 'rest_dl'],
        array_map(function ($row) {
          return [$row['id'], $row['up'], $row['dl']];
        }, $updates),
        ['rest_up=VALUES(rest_up)', 'rest_dl=VALUES(rest_dl)']
      ));

      if ($this->sql->affected_rows > 0) {
        // TODO: / 2 ? affected rows ??
        print "UPDATE " . $this->sql->affected_rows . " rows' traffics\n";
      } else {
        $this->throwSQLError('Upate Error');
      }
    }
  }

  // TODO: migrate to Tracker\Bonus
  function updateUser(&$set) {
    $updates = array_filter($set, function ($row) {
      return ($row['up'] != 0 || $row['dl'] != 0);
    });

    $amount = count($updates);

    if ($amount > 0) { // > 1
      $this->sql->query(static::genBatchUpdateSql(
        'tracker_bonus',
        ['id', 'up', 'dl', 'bonus'],
        array_map(function ($row) use ($amount) {
          return [$row['id'], $row['up'], $row['dl'],
            static::formulaTraffic($row['up'], $row['dl'], $amount)];
        }, $updates),
        [
          'up=up+VALUES(up)',
          'dl=dl+VALUES(dl)',
          'bonus=bonus+VALUES(bonus)',
        ]
      ));

      if ($this->sql->affected_rows > 0) {
        print "UPDATE " . $this->sql->affected_rows . " users' traffic\n";
      } else {
        $this->throwSQLError('Update user bonus ERROR');
      }
    }
  }

  function updateTime($seed) {
    $annInterval = Config::$annInterval;
    $seedTimeChunk = Config::$seedTimeChunk;
    $dt = esc(date('Y-m-d H:i:s', TIMENOW - $annInterval * 5));
    $where = implode(' AND ', [
      "is_old = false",
      "seeder = " . $seed ? 'true' : 'false',
      "during > 0", // without init traffic
      "during <= " . $annInterval * 2, // without timeout traffic
      "last_action <= '$dt'",
    ]);

    $res = $this->sql->query("SELECT torrent, userid, SUM(during) AS t FROM tracker_traffic_null WHERE $where"
      . " GROUP BY torrent, userid"
      . " HAVING SUM(during) > '" . ($seed ? $seedTimeChunk : $annInterval) . "'"
    );

    if (!$res) {
      $this->throwSQLError('Query Error');
    }

    $users = [];
    $conditions = [];
    // TODO: weight the torrent size, the number of seeders, the day or night, the torrent age, etc.
    while($row = $res->fetch_assoc()) {
      $torrent = $row['torrent'];
      $userid = $row['userid'];
      $conditions[] = "(torrent=$torrent AND userid=$userid)";

      if (!isset($users[$userid])) {
        $users[$userid] = 0;
      }
      // TODO: remove default value
      $users[$userid] += $row['t'];
    }

    if (count($conditions) == 0) return;

    $condition = implode(' OR ', $conditions);
    $where .= " AND $condition";
    $this->sql->query("UPDATE tracker_traffic_null SET is_old = true WHERE $where");

    print("process " . $this->sql->affected_rows . " rows traffic\n");

    $updates = [];
    if ($seed) {
      foreach ($users as $id => $time) {
        $updates[] = [$id, $time, static::formulaTime($time / $seedTimeChunk, 10)];
      }

      $this->sql->query(static::genBatchUpdateSql(
        'tracker_bonus',
        ['id', 'seed', 'bonus'],
        $updates,
        ["seed=seed+VALUES(seed)", "bonus=bonus+VALUES(bonus)"]
      ));

      print("UPDATE " . $this->sql->affected_rows . " users' seedtime");
    } else {
      // TODO: calculater leech bonus
      foreach ($users as $id => $time) {
        $updates[] = [$id, $time];
      }

      $this->sql->query(static::genBatchUpdateSql(
        'tracker_bonus',
        ['id', 'leech'],
        $updates,
        ["leech=leech+VALUES(leech)"]
      ));

      print("UPDATE " . $this->sql->affected_rows . " users' seedtime");
    }
  }

  function updateSeedTime() {
    $this->updateTime(true);
  }

  // TODO: process leech time
  // function updateLeechTime() {
  // }

  function updateTraffic() {
    // incomplete
    $annInterval = Config::$annInterval;
    $where = implode(' AND ', [
      "is_old = false",
      "invalid = false",
      "(rest_up > 0 OR rest_dl > 0)",
      "during > 0", // without init traffic
      "during <= ". $annInterval * 2, // without timeout traffic
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

    return 0;
  }
}
