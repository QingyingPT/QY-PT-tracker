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

  function throwSQLError($err = '') {
    $errmsg = $err;
    if ($this->sql->error) {
      $errmsg .= "\n SQL Error [" . $this->sql->errno . "]: " . $this->sql->error;
    }
    throw new \RuntimeException($errmsg);
  }

  function esc($str) {
    return $this->sql->real_escape_string($str);
  }

  function select($sql, $opts = []) {
    $res = $this->sql->query($sql);
    $single = $opts['single'] ?? false;

    if (!$res) {
      $this->throwSQLError('Query Error');
    }

    if ($single) {
      $row = $res->fetch_assoc();
      return $row;
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
      $rows[] = $row;
    }

    return $rows;
  }

  function update($sql, $opts = []) {
    $res = $this->sql->query($sql);
    $allowZero = $opts['allow_zero'] ?? false;

    if ($this->sql->error) {
      $this->throwSQLError('UPDATE Error');
    } else if ($allowZero == false && $this->sql->affected_rows == 0) {
      $this->throwSQLError('UPDATE 0 rows');
    }

    return $this->sql->affected_rows;
  }
}
