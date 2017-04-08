<?php namespace Tracker\Task;

use Tracker\SQL;

class UpdatePeers extends SQL {
  function cleanOldPeers($difftime) {
    $dt = esc(date('Y-m-d H:i:s', TIMENOW - $difftime));

    $this->sql->query("DELETE FROM tracker_peers WHERE last_action < '$dt'")
      or $this->throwSQLError('DELETE Error');

    return $this->sql->affected_rows;
  }
}

