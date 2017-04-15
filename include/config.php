<?php
// See: https://github.com/ZJUT/NexusPHP/blob/master/include/config.php

# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$announce_urls = array();
$announce_urls[] = $BASIC['announce_url'];

$mysql_host = $BASIC['mysql_host'];
$mysql_user = $BASIC['mysql_user'];
$mysql_pass = $BASIC['mysql_pass'];
$mysql_db = $BASIC['mysql_db'];

$securelogin = $SECURITY['securelogin'];
$securetracker = $SECURITY['securetracker'];
$https_announce_urls = array();
$https_announce_urls[] = $SECURITY['https_announce_url'];

$iv = $SECURITY['iv'];
$maxip = $SECURITY['maxip'];
$maxloginattempts = $SECURITY['maxloginattempts'];
$disableemailchange = $SECURITY['changeemail'];
$cheaterdet_security = $SECURITY['cheaterdet'];
$nodetect_security = $SECURITY['nodetect'];

$torrentnameprefix = $MAIN['torrentnameprefix'];
$max_torrent_size = $MAIN['max_torrent_size'];

$seebanned_class = $AUTHORITY['seebanned'];
