<?php namespace Tracker\Task;

class UpdatePeers {
  private $sql = NULL;

  function __construct () {
    $this->sql = old_get_mysql_link();
  }

  function throwSQLError($err) {
    throw new \RuntimeException($err . "\n SQL Error [" . $this->sql->errno . "]: " . $this->sql->error);
  }

  function cleanOldPeers($difftime) {
    $dt = esc(date('Y-m-d H:i:s', TIMENOW - $difftime));

    $this->sql->query("DELETE FROM tracker_peers WHERE last_action < '$dt'")
      or $this->throwSQLError('DELETE Error');

    return $this->sql->affected_rows;
  }
}

