<?php
require_once('include/bittorrent_announce.php');
require_once('include/benc.php');
dbconn_announce();

$sqlLink = old_get_mysql_link();
$az = null;
$row = null;

function Notice($err) {
  // TODO:
  benc_resp_raw('d' .benc_str('failure reason') .benc_str($err) .'e');
  die();
}

function getParam($name) {
  return isset($_GET[$name]) ? $_GET[$name] : null;
};

function esc($str) {
  global $sqlLink;
  return $sqlLink->real_escape_string($str);
}

$info = [
  'event' => getParam('event') || '',

  'port' => intval(getParam('port')),
  'left' => intval(getParam('left')),
  'uploaded' => intval(getParam('uploaded')),
  'downloaded' => intval(getParam('downloaded')),

  'agent' => $_SERVER['HTTP_USER_AGENT'],
  'peerid' => getParam('peer_id'),
  'compact' => intval(getParam('compact')),
  'passkey' => getParam('passkey'),
  'infohash' => preg_replace('/ *$/s', '', getParam('info_hash')),
  'noPeerId' => intval(getParam('no_peer_id')),

  'ip' => intval(getip()),
  'ipv6' => intval(getParam('ipv6')),

  'ts' => time(),
];
$peerid = esc($info['peerid']);

$numwant = max(intval(getParam('numwant')), intval(getParam('num_want')), 0); 
$info['numwant'] = min($numwant ? $numwant : 16, 16);

// TODO: get Torrent Info from pathname


// check fields
foreach (['passkey', 'infohash', 'peerid'] as $k) {
  if ($info[$k] === null) {
    Notice("Missing key: $k");
  }
}

if (strlen($info['infohash']) != 20) Notice('Invalid "info_hash"');
if (strlen($info['peerid']) != 20) Notice('Invalid "peer_id"');

if (strlen($info['passkey']) != 32) Notice('Invalid "passkey"');
if ($info['port'] <= 0 || $info['port'] > 0xffff) Notice('Invalid "port"');


// validate passkey
// TODO: use new table
// get user info
if (!$az = $Cache->get_value('tracker_user_' .$info['passkey'] .'_content')) {
	$res = $sqlLink->query("SELECT id, downloadpos, enabled, uploaded, downloaded, class, parked, clientselect, showclienterror FROM users WHERE passkey='". esc($info['passkey'])."' LIMIT 0, 1");
	$az = $res->fetch_assoc();
  $Cache->cache_value('tracker_user' .$info['passkey'] .'_content', $az, 1850);
}

if (!$az) {
  // TODO: record invalid passkey
  Notice('Invalid passkey! Please re-download .torrent file.');
}

$user = $az;

// validate user

if ($user['parked'] == 'yes')
  Notice('Account is parked!');
if ($user['enabled'] == 'no')
  Notice('Account is disabled!');
if ($user['downloadpos'] == 'no')
  Notice('Download priviledge is disabled!');

// validate client
// TODO: new method
$clicheck_res = check_client($info['peerid'], $info['agent']);
$client_familyid = check_client_family($info['peerid'], $info['agent'], $clicheck_res);

if ($clicheck_res || !$client_familyid) {
	if ($az['showclienterror'] == 'no') {
    // TODO: record invalid client version
		$sqlLink->query("UPDATE users SET showclienterror = 'yes' WHERE id = '$user[id]'");
	}
	Notice($clicheck_res);
} elseif ($az['showclienterror'] == 'yes') {
	$USERUPDATESET[] = "showclienterror = 'no'";
}

// validate torrent
// get torrent info

if (!$az = $Cache->get_value('tracker_hash_' .$info['infohash'] .'_content')) {
  $res = $sqlLink->query("SELECT id, owner, sp_state, seeders, leechers, UNIX_TIMESTAMP(added) AS added, banned, timestampdiff(DAY, last_action, NOW()) as diff_action_day FROM torrents WHERE info_hash = '$info[infohash]' LIMIT 0, 1");
  $az = $res->fetch_assoc();
  $Cache->cache_value('tracker_hash_' .$info['infohash'] .'_content', $az, 1870);
}

if (!$az) {
  // TODO: record invalid hashinfo
  Notice('Torrent not exists');
}

$torrent = $az;

// validate torrent priviledge
if ($torrent['banned'] && $user['class'] < $seebanned_class && $torrent['owner'] != $user['id'])
  Notice('Torrent banned!');

// evalute wait time

$announce_wait = 300;
$real_annnounce_interval = $announce_interval;
if ($anninterthreeage && ($anninterthree > $announce_wait) && (TIMENOW - $torrent['added']) >= ($anninterthreeage * 86400))
  $real_annnounce_interval = $anninterthree;
elseif ($annintertwoage && ($annintertwo > $announce_wait) && (TIMENOW - $torrent['added']) >= ($annintertwoage * 86400))
  $real_annnounce_interval = $annintertwo;

// get peer info

$peerFields = join(',', [
  'seeder',
  'peer_id',
  'ip',
  'ipv6',
  'uploader',
  'downloaded',
  'UNIX_TIMESTAMP(last_action) AS last_action',
  'UNIX_TIMESTAMP(prev_action) AS prev_action',
]);

if ($info['left'] == 0) {
  $seeder = true;
  $numPeerMax = $torrent['leechers'];
} else {
  $seeder = false;
  $numPeerMax = $torrent['leechers'] + $torrent['seeders'];
}

$self = null;
$res = $sqlLink->query("SELECT $peerFields FROM tracker_peers WHERE torrent = '$torrent[id]' AND peer_id = '$peerid' LIMIT 0,1");
$self = $res ? $res->fetch_assoc() : false;

// validate interval time

if ($self && $self['prev_action'] > (TIMENOW - $annouce_wait))
	Notice('There is a minimum announce time of ' .$announce_wait .' seconds');

// validate leech and seed limit
$res = $sqlLink->query("SELECT COUNT(*) FROM tracker_peers WHERE torrent = '$torrent[id]' AND userid = '$user[id]'");
$num = $res ? $res->fetch_row()[0] : 0;
if ($num > 0 && !$seeder)
  Notice('Please wait 30 minutes.');
if ($num > 2 && $seeder)
  Notice('Please seed the same torrent from < 4 locations.');

// basic info

$body = [
  benc_str('interval') .'i' .$real_annnounce_interval .'e',
  benc_str('min interval') .'i' .$announce_wait .'e',
  benc_str('complete') .'i' .$torrent['seeders'] .'e',
  benc_str('incomplete') .'i' .$torrent['leechers'] .'e',
];

// get peer list

$res = $sqlLink->query("SELECT $peerFields FROM tracker_peers WHERE torrent = '$torrent[id]' " 
  .($seeder ? "AND seeder = 'no' " : '')
  ."ORDER BY RAND() LIMIT 0, $info[numwant]");

$peerList = [];
while ($res && $row = $res->fetch_assoc()) {
  $row['peer_id'] = hash_pad($row['peer_id']);
  if ($row['peer_id'] === $info['peerid']) {
    continue;
  }

  // TODO: network address transform
  // TODO: ip or ipv6 from 'ip' field
  if ($info['compact'] == 1) {
    $l = ip2long($row['ip']);
    if ($l) $peerList[] = pack('Nn', sprintf('%d', $l), $row['port']);
  } elseif ($info['noPeerId'] == 1) {
    if ($row['ip']) $peerList[] = 'd' .join('', array_map('benc_str', ['ip', $row['ip'], 'port'])) .'i' .$row['port'] .'e' .'e';
    if ($row['ipv6']) $peerList[] = 'd' .join('', array_map('benc_str', ['ip', $row['ipv6'], 'port'])) .'i' .$row['port'] .'e' .'e';
  } else {
    if ($row['ip']) $peerList[] = 'd' .join('', array_map('benc_str', ['ip', $row['ip'], 'peer id', $row['peer_id'], 'port'])) .'i' .$row['port'] .'e' .'e';
    if ($row['ipv6']) $peerList[] = 'd' .join('', array_map('benc_str', ['ip', $row['ipv6'], 'peer id', $row['peer_id'], 'port'])) .'i' .$row['port'] .'e' .'e';
  }
}

$peerInfo = benc_str('peers');
if ($info['compact'] == 1)
  $peerInfo .= benc_str(join('', $peerList));
else
  $peerInfo .= 'l' .join('', $peerList) .'e';

// TODO: check user level
$body[] = $peerInfo;

// TODO:snatch


// TODO: true uploaded and downloaded <-- report


// update peer
$dt = esc(date('Y-m-d H:i:s', $info['ts']));
if ($self) {
  $updates = [
    "togo = '$info[left]'",
    "upload = '$info[upload]'",
    "download = '$info[download]'",
    "ip = '" .esc($info['ip'] || '') ."'",
    "ipv6 = '" .esc($info['ipv6'] || '') ."'",
    "prev_action = last_action",
    "last_action = '$dt'",
  ];

  $where = "torrent = '$torrent[id]' AND peer_id = '$peerid'";

  // NOTE: don't update torrent info
  if ($info['event'] == 'completed') { // complete: delete peer, update seeder/leecher
    // TODO
    $updates[] = "seeder = '" .($seeder ? 'yes' : 'no') ."'";
    $updates[] = "finishedat = '$dt'";
    $sqlLink->query("UPDATE tracker_peers SET " .join(',', $updates) ." WHERE $where");
  } elseif ($info['event'] == 'stop') {
    // TODO
    $sqlLink->query("DELETE FROM tracker_peers WHERE $where");
  } else {
    // TODO
    $sqlLink->query("UPDATE tracker_peers SET " .join(',', $updates) ." WHERE $where");
  }
} else {
  // TODO
  $fields = [
    'torrent',
    'userid',
    'agent',
    'port',
    'ip',
    'ipv6',
    'uploaded',
    'downloaded',
    'uploadoffset',
    'downloadoffset',
    'togo',
    'seeder',
    'startedat',
    'finishedat',
    'last_action',
    'prev_action',
  ];

  $values = [
    $torrent['id'],
    $user['id'],
    esc($info['agent']),
    $port,
    esc($info['ip']), // TODO: validate ip
    esc($info['ipv6']),
    $info['uploaded'],
    $info['downloaded'],
    $info['uploaded'],
    $info['downloaded'],
    $info['left'],
    $seeder ? 'yes' : 'no',
    $dt,
    $dt,
    $dt,
    $dt,
  ];

  $sqlLink->query("INSERT INTO tracker_peers (" .join(',', $fields) .") VALUES ('" .join("','", $values) ."')")
    or Notice('Error: 0x0002');
}


// update torrent
if ($seeder) {
  $updates = [
    "visible = 'yes'",
    "last_action = '$dt'",
  ];
  $sqlLink->query("UPDATE torrents SET " .join(',', $updates) . " WHERE id = '$torrent[id]'");

  // generator tag
  
  if ($torrent['diff_action_day'] > 100)
    $sqlLink->query("INSERT INTO sitelog (added, txt, security_level) VALUES('$dt', 'Torrent $torrent[id] added BUMP tag. The seeder (User $user[id]) exists after $torrent[diff_action_day] days.', 'mod')");
}


// update peer


// update user
if ($client_familyid != 0 && $client_familyid != $az['clientselect'])
	$USERUPDATESET[] = "clientselect = ".sqlesc($client_familyid);

if ($seeder)
  $USERUPDATESET[] = "last_access = '$dt'";

if (isset($USERUPDATESET)) {
  $sqlLink->query("UPDATE users SET " .join(',', $USERUPDATESET) ." WHERE id = '$user[id]'");
}

// output info

benc_resp_raw('d' .join('', $body) .'e');
exit();
