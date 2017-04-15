<?php namespace Tracker\Task;

use Tracker\SQL;

class UpdateTorrent extends SQL {
  public function updatePeers() {
    $res = $this->sql->query("SELECT torrent, SUM(seeder) as seeders, COUNT(*) as peers FROM tracker_peers GROUP BY torrent")
      or $this->throwSQLError('SELECT Error');

    // TODO: use "INSERT INTO ... DUPLICATE UPDATE ..."
    while($row = $res->fetch_assoc()) {
      $this->sql->query("UPDATE torrents SET seeders = '$row[seeders]', leechers = '" .($row['peers'] - $row['seeders']) ."' WHERE id = '$row[torrent]'")
      or $this->throwSQLError('UPDATE Error');
    }

    return $this->sql->affected_rows / 2;
  }
}

