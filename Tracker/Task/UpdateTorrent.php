<?php namespace Tracker\Task;

use Tracker\SQL;
use Tracker\SQLHelper;

class UpdateTorrent extends SQL {
  use SQLHelper;

  public function updatePeers($difftime = 1200) {
    $dt = esc(date('Y-m-d H:i:s', round(TIMENOW - $difftime)));

    $ret = 0;
    $this->sql->query("INSERT INTO torrents (id, info_hash, seeders, leechers)"
      . " SELECT t.id, t.info_hash, SUM(p.seeder), (COUNT(*) - SUM(p.seeder))"
      . "   FROM tracker_peers AS p RIGHT JOIN torrents AS t ON t.id = p.torrent"
      . "   GROUP BY p.torrent"
      . " ON DUPLICATE KEY"
      . " UPDATE seeders = VALUES(seeders), leechers = VALUES(leechers), last_update = CURRENT_TIMESTAMP"
    );

    if ($this->sql->error) {
      $this->throwSQLError('Can\'t update torrent info');
      return 0;
    }

    $this->sql->query("UPDATE torrents SET seeders = 0, leechers = 0, visible = 'no', last_update = CURRENT_TIMESTAMP WHERE last_update < '$dt'");
    // TODO: check error

    $ret = $this->sql->affected_rows;
    return $ret;
  }
}

