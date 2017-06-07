<?php namespace Tracker\Task;

use Tracker\SQL;

class UpdatePeers extends SQL {
  function cleanOldPeers($difftime, $uid = 0) {
    $dt = $this->esc(date('Y-m-d H:i:s', TIMENOW - $difftime));

    $wheres = ["last_action < '$dt'"];

    if ($uid) {
      $wheres[] = "userid = '$uid'";
    }

    $where = implode(' AND ', $wheres);

    $this->sql->query("DELETE FROM tracker_peers WHERE $where")
      or $this->throwSQLError('DELETE Error');

    return $this->sql->affected_rows;
  }
}

