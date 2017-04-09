<?php namespace Tracker\Task;
// TODO: validate traffic
// TODO: log abnormal traffic
// TODO: avoid update statement too long. limit 30/time

use Tracker\SQL;
use Tracker\Config;
use Tracker\SQLTrait;
use Tracker\Exception\Normal as NormalException;

class ProcessTraffic extends SQL {
  use SQLTrait;

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

  static function defineUser ($uid, &$users, $cb = NULL) {
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

  static function accUserTraffic ($traffic, $userUp, $userDl, &$users) {
    static::defineUser($userUp, $users, function (&$user) use ($traffic) {
      $user['up'] += $traffic;
    });

    static::defineUser($userDl, $users, function (&$user) use ($traffic) {
      $user['dl'] += $traffic;
    });
  }

  static function accUserDuring($flag, $during, $uid, &$users) {
    if ($during > 0) {
      static::defineUser($uid, $users, function (&$user) use ($flag, $during) {
        $user[$flag] += $during;
      });
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
      $bu = $bucketUp[$i];
      $bd = $bucketDl[$j];
      // print "$i $bu[id] $j $bd[id] $bu[up] $bd[dl] $restDl $restUp \n";

      if ($bucketUp[$i]['seeder'] != false) {
        static::accUserDuring('seed', $bucketUp[$i]['during'], $bucketUp[$i]['userid'], $users);
        $bucketUp[$i]['during'] = 0;
      }
      static::accUserDuring('leech', $bucketDl[$j]['during'], $bucketDl[$j]['userid'], $users);
      $bucketDl[$j]['during'] = 0;

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

  static function formula ($up, $dl, $n) {
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
        ['id', 'up', 'dl', 'seed', 'leech', 'bonus'],
        array_map(function ($row) use ($amount) {
          return [$row['id'], $row['up'], $row['dl'], $row['seed'], $row['leech'],
            static::formula($row['up'], $row['dl'], $amount)];
        }, $updates),
        [
          'up=up+VALUES(up)',
          'dl=dl+VALUES(dl)',
          'seed=seed+VALUES(seed)',
          'leech=leech+VALUES(leech)',
          'bonus=bonus+VALUES(bonus)',
        ]
      ));

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
}
