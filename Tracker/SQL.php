<?php namespace Tracker;

class SQL {
  protected $sql = NULL;

  function __construct() {
    $this->sql = old_get_mysql_link();
  }

  function throwSQLError($err) {
    throw new \RuntimeException($err . "\n SQL Error [" . $this->sql->errno . "]: " . $this->sql->error);
  }
}
