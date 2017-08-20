<?php namespace Tracker;

use Tracker\SQL;

class Traffic extends SQL {
  const DefaultTimeGap = 86400 * 30;

  private function genUserTrafficCondSql($userid, $torrent, $stt, $end) {
    $whereSet = [];

    $stt = 0 + intval($stt);
    $end = (0 + intval($end)) ?: ($stt + self::DefaultTimeGap);

    if (!$userid || !$stt || ($stt > $end) || ($end - $stt > self::DefaultTimeGap)) {
      return null; // error: too much data
    }

    if ($userid) {
      $whereSet['userid'] = "userid = '" . $this->esc($userid) . "'";
    }

    if ($torrent) {
      $whereSet['torrent'] = "torrentid = '" . $this->esc($torrent) . "'";
    } else {
      $whereSet['stt'] = "last_action >= FROM_UNIXTIME($stt)";
      $whereSet['end'] = "last_action < FROM_UNIXTIME($end)";
    }

    $whereSet['seeder'] = 'seeder = true';

    $whereSet['is_old'] = 'is_old = false';
    $whereSet['invalid'] = 'invalid = false';

    return $whereSet;
  }

  function getUserAvailableTraffic($userid, $torrent, $stt, $end = 0) {
    $traffics = [];

    $whereSet = $this->genUserTrafficCondSql($userid, $torrent, $stt, $end);
    if (!$whereSet) {
      return [];
    }

    $where = implode(' AND ', $whereSet);

    $res = $this->sql->query("SELECT torrent, SUM(during) AS t FROM tracker_traffic WHERE $where GROUP BY torrent ORDER BY torrent DESC LIMIT 0, 1000")
      or $this->throwSQLError('SELECT tracker_traffic');
    while ($row = $res->fetch_assoc()) {
      $traffics[$row['torrent']] = $row['t'];
    }

    unset($whereSet['invalid']);
    $where = implode(' AND ', $whereSet);

    $res = $this->sql->query("SELECT torrent, SUM(during) FROM tracker_traffic_null WHERE $where GROUP BY torrent ORDER BY torrent DESC LIMIT 0, 1000")
      or $this->throwSQLError('SELECT tracker_traffic_null');
    while ($row = $res->fetch_assoc()) {
      if (!isset($traffics[$row['torrent']])) {
        $traffics[$row['torrent']] = $row['t'];
      } else {
        $traffics[$row['torrent']] += $row['t'];
      }
    }

    // TODO: Cahce it
    return $traffics;
  }

  function updateUserTrafficStatus($userid, $torrent, $stt, $end = 0) {
    $error = [];

    $whereSet = $this->genUserTrafficCondSql($userid, $torrent, $stt, $end);
    $where = implode(' AND ', $whereSet);

    $this->sql->query("UPDATE tracker_traffic SET is_old = true WHERE $where");
    if ($this->sql->error) {
      $error[] = $this->sql->error;
    }

    unset($whereSet['invalid']);
    $where = implode(' AND ', $whereSet);

    $this->sql->query("UPDATE tracker_traffic_null SET is_old = true WHERE $where");
    if ($this->sql->error) {
      $error[] = $this->sql->error;
    }

    return !empty($error) ? $error : false;
  }
}
