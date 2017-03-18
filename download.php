<?php
require_once('include/bittorrent_announce.php');
require_once('include/benc.php');

$torrent_dir = 'torrents';

function Notice($err) {
  // TODO:
  die($err);
}

function E403($err = 'No permission') {
  header('HTTP/1.0 403 Forbidden');
  die($err);
}
function E404($err = 'Not Found') {
  header('HTTP/1.0 404 Not Found');
  die($err);
}


$id = isset($_GET['id']) ? (0 + intval($_GET['id'])) : 0;
$hash = isset($_GET['hash']) ? $_GET['hash'] : '';
$passkey = isset($_GET['passkey']) ? $_GET['passkey'] : '';
if (!$hash || !$id) {
  E403('Invalid URL parameters');
}

dbconn_announce();
$sqlLink = old_get_mysql_link();

function esc($str) {
  global $sqlLink;
  return $sqlLink->real_escape_string($str);
}

if ($passkey) {
  $res = $sqlLink->query("SELECT * FROM users WHERE passkey='" .esc($passkey) ."' LIMIT 0,1")
    or Notice('Invalid passkey');
  // TODO: record it
  $user = $res->fetch_assoc();
} else {
  if (empty($_COOKIE['c_secure_pass'])
    || empty($_COOKIE['c_secure_uid'])
    || empty($_COOKIE['c_secure_login'])) {
    header('HTTP/1.0 403 Forbidden');
    die();
  }

  $userid = 0 + intval(base64($_COOKIE['c_secure_uid'], false));
  $sqlLink->query("SELECT * FROM users WHERE id='" .esc($userid) ."' LIMIT 0,1")
    or Notice('Invalid cookie');

  $user = $res->fetch_assoc();

  if (md5($user['passhash'] .$_SERVER['REMOTE_ARRD']) != $_COOKIE['c_secure_pass']) {
    Notice('Invalid cookie');
  }
}

if (!$user['passkey'] || strlen($user['passkey']) != 32) {
  $passkey = md5($user['username'] .date('c') .$user['passhash']);
  $sqlLink->query("UPDATE users SET passkey='" .esc($passkey) ."' WHERE id='$user[id]'")
    or Notice('SQL error: Duplicate passkey');
} else {
  $passkey = $user['passkey'];
}

if (!isset($user) || $user['enabled'] == 'no' || $user['parked'] == 'yes') {
  Notice('Account disabled or parked');
}
if ($user['downloadpos'] == 'no') {
  E403();
}

// TODO: record ip to 'iplog'


// TODO: update user's 'last_access'


// if ($_COOKIE["c_secure_tracker_ssl"] == base64("yeah"))
//   $tracker_ssl = true;
// else
//   $tracker_ssl = false;

$tracker_ssl = false;
if(isset($_SERVER['HTTPS'])){
  $tracker_ssl = true;
}

if ($tracker_ssl == true){
  $ssl_torrent = 'https://';
  if ($https_announce_urls[0] != "")
    $base_announce_url = $https_announce_urls[0];
  else
    $base_announce_url = $announce_urls[0];
} else {
  $ssl_torrent = 'http://';
  $base_announce_url = $announce_urls[0];
}


$res = sql_query("SELECT id, name, filename, save_as, size, owner, banned, HEX(info_hash) as hash FROM torrents WHERE id='$id'")
  or Notice('No torrent');
$torrent = $res->fetch_assoc();
$fn = "$torrent_dir/$id.torrent";

if (!$torrent || !is_file($fn) || !is_readable($fn)) {
  E404();
}

if (strtoupper($hash) != $torrent['hash']) {
  Notice('Invalid download link');
}

// check user class
if ($torrent['banned'] == 'yes' && $user['class'] < $seebanned_class && $torrent['owner'] != $user['id']) {
  E403();
}

$sqlLink->query("UPDATE torrents SET hits = hits + 1 WHERE id = '$id'")
  or Notice('No torrent');


// generate download key
$res = $sqlLink->query("SELECT * FROM tracker_snatch WHERE torrent = '$torrent[id]' AND userid = '$user[id]'")
  or Notice('SQL error 3');

if ($res->num_rows == 0) {
  $key = md5($user['passhash'] .$user['id'] .$torrent['id'] .date('c') .rand());
  $sqlLink->query("INSERT INTO tracker_snatch (torrent, userid, downloadkey, finishdat) VALUES ('$torrent[id]', '$user[id]', '$key', 0)")
    or Notice('SQL error 4');
} else if ($res->num_rows > 1) {
  // TODO: remove all
  $sqlLink->query("DELETE FROM tracker_snatch WHERE torrent = '$torrent[id]' AND userid = '$user[id]'")
    or Notice('SQL error 5');
} else {
  $row = $res->fetch_assoc();
  $key = $row['downloadkey'];
  $sqlLink->query("UPDATE tracker_snatch SET last_action = NOW() WHERE torrent = '$torrent[id]' AND userid = '$user[id]'")
    or Notice('SQL error 6');
}


// bencode
$dict = bdec_file($fn, $max_torrent_size);
$dict['value']['announce']['value'] = $ssl_torrent . $base_announce_url . "?passkey=$user[passkey]&downloadkey=$key";
$dict['value']['announce']['string'] = strlen($dict['value']['announce']['value']).":".$dict['value']['announce']['value'];
$dict['value']['announce']['strlen'] = strlen($dict['value']['announce']['string']);

/*if ($announce_urls[1] != "") { // add multi-tracker
	$dict['value']['announce-list']['type'] = "list";
	$dict['value']['announce-list']['value'][0]['type'] = "list";
	$dict['value']['announce-list']['value'][0]['value'][0]["type"] = "string";
	$dict['value']['announce-list']['value'][0]['value'][0]["value"] = $ssl_torrent . $announce_urls[0] . "?passkey=$CURUSER[passkey]";
	$dict['value']['announce-list']['value'][0]['value'][0]["string"] = strlen($dict['value']['announce-list']['value'][0]['value'][0]["value"]).":".$dict['value']['announce-list']['value'][0]['value'][0]["value"];
	$dict['value']['announce-list']['value'][0]['value'][0]["strlen"] = strlen($dict['value']['announce-list']['value'][0]['value'][0]["string"]);
	$dict['value']['announce-list']['value'][0]['string'] = "l".$dict['value']['announce-list']['value'][0]['value'][0]["string"]."e";
	$dict['value']['announce-list']['value'][0]['strlen'] = strlen($dict['value']['announce-list']['value'][0]['string']);
	$dict['value']['announce-list']['value'][1]['type'] = "list";
	$dict['value']['announce-list']['value'][1]['value'][0]["type"] = "string";
	$dict['value']['announce-list']['value'][1]['value'][0]["value"] = $ssl_torrent . $announce_urls[1] . "?passkey=$CURUSER[passkey]";
	$dict['value']['announce-list']['value'][1]['value'][0]["string"] = strlen($dict['value']['announce-list']['value'][0]['value'][0]["value"]).":".$dict['value']['announce-list']['value'][0]['value'][0]["value"];
	$dict['value']['announce-list']['value'][1]['value'][0]["strlen"] = strlen($dict['value']['announce-list']['value'][0]['value'][0]["string"]);
	$dict['value']['announce-list']['value'][1]['string'] = "l".$dict['value']['announce-list']['value'][0]['value'][0]["string"]."e";
	$dict['value']['announce-list']['value'][1]['strlen'] = strlen($dict['value']['announce-list']['value'][0]['string']);
	$dict['value']['announce-list']['string'] = "l".$dict['value']['announce-list']['value'][0]['string'].$dict['value']['announce-list']['value'][1]['string']."e";
	$dict['value']['announce-list']['strlen'] = strlen($dict['value']['announce-list']['string']);
}*/


// header("Content-Type: application/octet-stream");
header("Content-Type: application/x-bittorrent");

if ( str_replace("Gecko", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
	header ("Content-Disposition: attachment; filename=\"$torrentnameprefix.".$torrent["save_as"].".torrent\" ; charset=utf-8");
} else if ( str_replace("Firefox", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'] ) {
	header ("Content-Disposition: attachment; filename=\"$torrentnameprefix.".$torrent["save_as"].".torrent\" ; charset=utf-8");
} else if ( str_replace("Opera", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'] ) {
	header ("Content-Disposition: attachment; filename=\"$torrentnameprefix.".$torrent["save_as"].".torrent\" ; charset=utf-8");
} else if ( str_replace("IE", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'] ) {
	header ("Content-Disposition: attachment; filename=".str_replace("+", "%20", rawurlencode("$torrentnameprefix." . $torrent["save_as"] .".torrent")));
} else {
	header ("Content-Disposition: attachment; filename=".str_replace("+", "%20", rawurlencode("$torrentnameprefix." . $torrent["save_as"] .".torrent")));
}

//ob_implicit_flush(true);
print(benc($dict));
?>
