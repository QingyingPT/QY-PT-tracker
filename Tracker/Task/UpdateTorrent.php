<?php namespace Tracker\Task;

use Tracker\SQL;
use Tracker\SQLHelper;

class UpdateTorrent extends SQL {
  use SQLHelper;

  public function updatePeers($difftime = 14400) {
    $dt = esc(date('Y-m-d H:i:s', round(TIMENOW - $difftime)));

    $ret = 0;
    $this->sql->query("INSERT INTO torrents (id, info_hash, seeders, leechers)"
      . " SELECT t.id, t.info_hash, SUM(p.seeder), (COUNT(*) - SUM(p.seeder))"
      . "   FROM tracker_peers AS p RIGHT JOIN torrents AS t ON t.id = p.torrent"
      . "   GROUP BY p.torrent"
      . " ON DUPLICATE KEY"
      . " UPDATE seeders = VALUES(seeders), leechers = VALUES(leechers), visible = 'yes', last_update = CURRENT_TIMESTAMP"
    );

    if ($this->sql->error) {
      $this->throwSQLError('Can\'t update torrent info');
      return 0;
    }
    $ret = $this->sql->affected_rows;

    $this->sql->query("UPDATE torrents SET seeders = 0, leechers = 0, visible = 'no' WHERE last_update < '$dt' AND (seeders > 0 OR leechers > 0)");
    // TODO: check error

    return $ret;
  }
}

