<?php namespace Tracker;

class SQL {
  protected $sql = NULL;

  function __construct() {
    $mysql_link = get_mysql_link();
    if (!$mysql_link) {
      throw new \RuntimeException("Miss MYSQLi connection");
    }
    $this->sql = $mysql_link;
  }

  function throwSQLError($err) {
    throw new \RuntimeException($err . "\n SQL Error [" . $this->sql->errno . "]: " . $this->sql->error);
  }

  function esc($str) {
    return $this->sql->real_escape_string($str);
  }
}
