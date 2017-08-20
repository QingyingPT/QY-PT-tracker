<?php namespace Tracker;

use Tracker\SQL;

class Peer extends SQL {
  static private function range($page = 0, $per = 30) {
    $stt = $page * $per;
    $end = $page * $per + $per;
    return "$stt,$end";
  }

  public function getTorrentPeers($torrent, $type = 'seed', $page = 0) {
    $limit = static::range($page);
    $seeder = ($type === 'seed') ? 'true' : 'false';
    $res = $this->sql->query("SELECT p.agent as agent, p.uploaded as upload, p.downloaded as download, p.last_action as last_action, s.uploaded as u, s.downloaded as d, u.id as uid, u.username as username, u.class as level"
      . " FROM tracker_peers AS p"
      . "  LEFT JOIN tracker_snatch AS s ON s.userid = p.userid AND s.torrent = p.torrent"
      . "  LEFT JOIN users AS u ON u.id = p.userid"
      . " WHERE p.torrent = '$torrent' AND p.seeder = $seeder"
      . " ORDER BY p.id DESC"
      . " LIMIT $limit"
    );

    if (!$res) {
      $this->throwSQLError('Query Error');
    }

    $result = [];
    while ($row = $res->fetch_assoc()) {
      $result[] = $row;
    }
    return $result;
  }

  public function getUserPeers($user, $type = 'seed', $page = 0) {
    $limit = static::range($page);
    $seeder = ($type === 'seed') ? 'true' : 'false';

    $res = $this->sql->query("SELECT t.id as tid, t.name as title, t.small_descr as subtitle, t.size as size, t.seeders as seeders, t.leechers as leechers, p.agent as agent, p.uploaded as upload, p.downloaded as download, p.last_action as last_action"
      . " FROM tracker_peers AS p"
      . "  LEFT JOIN torrents AS t ON t.id = p.torrent"
      . " WHERE p.userid = '$user' AND p.seeder = $seeder"
      . " ORDER BY t.id DESC"
      . " LIMIT $limit"
    );

    if (!$res) {
      $this->throwSQLError('Query Error');
    }

    $result = [];
    while ($row = $res->fetch_assoc()) {
      $result[] = $row;
    }
    return $result;
  }
}
