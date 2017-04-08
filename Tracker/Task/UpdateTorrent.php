<?php namespace Tracker\Task;

class UpdateTorrent {
  private $sql = NULL;

  function __construct () {
    $this->sql = old_get_mysql_link();
  }

  function throwSQLError($err) {
    throw new \RuntimeException($err . "\n SQL Error [" . $this->sql->errno . "]: " . $this->sql->error);
  }

  public function updatePeers () {
    $res = $this->sql->query("SELECT torrent, SUM(seeder) as seeders, COUNT(*) as peers FROM tracker_peers GROUP BY torrent")
      or $this->throwSQLError('SELECT Error');

    while($row = $res->fetch_assoc()) {
      $this->sql->query("UPDATE torrents SET seeders = '$row[seeders]', leechers = '" .($row['peers'] - $row['seeders']) ."' WHERE id = '$row[torrent]'")
      or $this->throwSQLError('UPDATE Error');
    }

    return $this->sql->affected_rows / 2;
  }
}

