<?php
if (!defined('IN_TRACKER')) {
  die('null');
}

function Notice($str) {
  die($str);
}

function SQLError($file, $line) {
  echo $file ." \n" .$line;
  die();
}

function cleanOldPeers() {
  $sqlLink = old_get_mysql_link();
  $difftime = 1800;
  $dt = esc(date('Y-m-d H:i:s', TIMENOW - $difftime));

  $sqlLink->query("DELETE FROM tracker_peers WHERE last_action < '$dt'")
    or SQLError(__FILE__, __LINE__);

  return $sqlLink->affected_rows;
}

function expireTraffics() {
  // leechtime/seedtime statistics -> user
  // leecher/seeder/complete statistics -> torrent

  /*
  $sqlLink = old_get_mysql_link();
  $difftime = 10800;
  $stt = esc(date('Y-m-d H:i:s', TIMENOW - $difftime));
  $end = esc(date('Y-m-d H:i:s', TIMENOW - $difftime));

  $fields = [
    'torrent',
    'userid',
    'SUM(upload) AS up',
    'SUM(download) AS dl',
    'UNIX_TIMESTAMP(MIN(last_action)) AS stt',
    'UNIX_TIMESTAMP(MAX(last_action)) AS end',
  ];

  $res = $sqlLink->query("SELECT " .implode(',', $fields) ." FROM tracker_traffic WHERE is_old = '' AND last_action > '$stt' AND last_action < '$end' GROUP BY torrent, userid ORDER BY torrent")
    or SQLError(__FILE__, __LINE__);
   */
}

function updateTorrentInfo() {
  $sqlLink = old_get_mysql_link();
  $difftime = 7200;

  $res = $sqlLink->query("SELECT torrent, SUM(seeder) as seeders, COUNT(*) as peers FROM tracker_peers GROUP BY torrent")
    or SQLError(__FILE__, __LINE__);

  while($row = $res->fetch_assoc()) {
    $sqlLink->query("UPDATE torrents SET seeders = '$row[seeders]', leechers = '" .($row['peers'] - $row['seeders']) ."' WHERE id = '$row[torrent]'")
      or SQLError(__FILE__, __LINE__);
  }
}

function task($name, $flag, $info, $func) {
  if ($flag) {
    print("\n$info ...\n");

    $result = $func() ? : 'DONE';

    $sqlLink = old_get_mysql_link();
    $sqlLink->query("UPDATE tracker_schedule SET last_action = CURRENT_TIMESTAMP WHERE name = '$flag'")
      or SQLError(__FILE__, __LINE__);

    print("\n$info ... $result\n");

    ob_flush();
    flush();
  }
}

function doTask() {
  $sqlLink = old_get_mysql_link();

  $res = $sqlLink->query("SELECT name FROM tracker_schedule WHERE (NOW() - (unit * val)) > UNIX_TIMESTAMP(last_action)")
    or SELError(__FILE__, __LINE__);

  if ($res->num_rows == 0) {
    return;
  }

  $arr = [];
  while($row = $res->fetch_row()) {
    $arr[] = $row[0];
  }
  
  // clean old peers
  task('peer', in_array('peer', $arr), 'clean old peers', function () {
    if (($num = cleanOldPeers()) > 0) {
      print("delete $num old peers\n");
    } else {
      print("no old peers\n");
    }
  });

  // update torrent info
  task('torrent', in_array('torrent', $arr), 'update torrent info', function () {
    return updateTorrentInfo();
  });

  // force traffics expiration
  task('traffic', in_array('traffic', $arr), 'update traffic info', function () {
    return expireTraffics();
  });

  // check cheater -> traffic
  // check cheater -> time
  // check cheater -> multiple client
}
