<?php

function login() {
  $sql = get_mysql_link();

  $ip = getip();
  $nip = ip2long($ip);

  if ($nip) {
    // TODO: check bans
  }

  if (empty($_COOKIE['c_secure_pass'])
    || empty($_COOKIE['c_secure_uid'])
    || empty($_COOKIE['c_secure_login'])
  ) return false;

  $id = 0 + intval(isset($_COOKIE['c_secure_uid']) ? base64_decode($_COOKIE['c_secure_uid']) : 0);
  $pass = isset($_COOKIE['c_secure_pass']) ? $_COOKIE['c_secure_pass'] : '';
  $login = isset($_COOKIE['c_secure_login']) ? base64_decode($_COOKIE['c_secure_login']) : '';

  if ($id <= 0 || strlen($pass) != 32) return false;

  $res = $sql->query("SELECT * FROM users WHERE id = '$id' AND enabled = 'yes' AND status = 'confirmed' LIMIT 1");
  if ($sql->error) return false;

  $row = $res->fetch_assoc();
  if (!$row) return false;

  if ($login == 'yeah') {
    if ($pass != md5($row['passhash']) . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0')) return false;
  } else {
    // TODO: fix bug
    if ($pass != md5($row['passhash'])) return false;
  }

  return $row;
}
