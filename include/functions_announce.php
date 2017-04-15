<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// IP Validation
function validip($ip) {
  if (!ip2long($ip)) //IPv6
    return true;

  if (!empty($ip) && $ip == long2ip(ip2long($ip))) {
    // reserved IANA IPv4 addresses
    // http://www.iana.org/assignments/ipv4-address-space
    $reserved_ips = array (
      array('192.0.2.0','192.0.2.255'),
      array('192.168.0.0','192.168.255.255'),
      array('255.255.255.0','255.255.255.255')
    );

    foreach ($reserved_ips as $r) {
      $min = ip2long($r[0]);
      $max = ip2long($r[1]);
      if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) return false;
    }
    return true;
  } else
    return false;
}

function getip() {
  $ip = '';

  if (isset($_SERVER)) {
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && validip($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_REAL_IP']) && validip($_SERVER['HTTP_X_REAL_IP'])) {
      $ip = $_SERVER['HTTP_X_REAL_IP'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }
  } else {
    if (getenv('HTTP_X_FORWARDED_FOR') && validip(getenv('HTTP_X_FORWARDED_FOR'))) {
      $ip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('HTTP_X_REAL_IP') && validip(getenv('HTTP_X_REAL_IP'))) {
      $ip = getenv('HTTP_X_REAL_IP');
    } else {
      $ip = getenv('REMOTE_ADDR');
    }
  }

  return $ip ? : '';
}

function get_mysql_link() {
  global $mysql_link;
  if (!$mysql_link) {
    global $mysql_host, $mysql_user, $mysql_pass, $mysql_db;
    $mysql_link = new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
    if ($mysql_link->connect_error) {
      die;
    }

    $mysql_link->query('SET NAMES UTF8');
    $mysql_link->query("SET collation_connection = 'utf8_general_ci'");
    $mysql_link->query("SET sql_mode=''");
  }

  return $mysql_link;
}

function benc_resp_raw($x) {
  header("Content-Type: text/plain; charset=utf-8");
  header("Pragma: no-cache");

  if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && $_SERVER["HTTP_ACCEPT_ENCODING"] == "gzip") {
    header("Content-Encoding: gzip");
    echo gzencode($x, 9, FORCE_GZIP);
  } else
    echo $x;
}

function err($err) {
  benc_resp_raw('d' .benc_str('failure reason') .benc_str($err) .'e');
  die();
}

function check_client($peer_id, $agent) {
  global $Cache;

  if (!$clients = $Cache->get_value('allowed_client_list')){
    $clients = array();
    $res = mysql_query("SELECT * FROM agent_allowed_family ORDER BY hits DESC") or err("check err");
    while ($row = mysql_fetch_array($res))
      $clients[] = $row;
    $Cache->cache_value('allowed_client_list', $clients, 86400);
  }
  foreach ($clients as $row_allowed_ua)
  {
    $allowed_flag_peer_id = false;
    $allowed_flag_agent = false;
    $version_low_peer_id = false;
    $version_low_agent = false;

    if($row_allowed_ua['peer_id_pattern'] != '')
    {
      if(!preg_match($row_allowed_ua['peer_id_pattern'], $row_allowed_ua['peer_id_start'], $match_bench))
      err("regular expression err for: ". $row_allowed_ua['peer_id_start'] . ", please ask sysop to fix this");

      if(preg_match($row_allowed_ua['peer_id_pattern'], $peer_id, $match_target))
      {
        if($row_allowed_ua['peer_id_match_num'] != 0)
        {
          for($i = 0 ; $i < $row_allowed_ua['peer_id_match_num']; $i++)
          {
            if($row_allowed_ua['peer_id_matchtype'] == 'dec')
            {
              $match_target[$i+1] = 0 + $match_target[$i+1];
              $match_bench[$i+1] = 0 + $match_bench[$i+1];
            }
            else if($row_allowed_ua['peer_id_matchtype'] == 'hex')
            {
              $match_target[$i+1] = hexdec($match_target[$i+1]);
              $match_bench[$i+1] = hexdec($match_bench[$i+1]);
            }

            if ($match_target[$i+1] > $match_bench[$i+1])
            {
              $allowed_flag_peer_id = true;
              break;
            }
            else if($match_target[$i+1] < $match_bench[$i+1])
            {
              $allowed_flag_peer_id = false;
              $version_low_peer_id = true;
              $low_version = "Your " . $row_allowed_ua['family'] . " 's version is too low, please update it after " . $row_allowed_ua['start_name'];
              break;
            }
            else if($match_target[$i+1] == $match_bench[$i+1])//equal
            {
              if($i+1 == $row_allowed_ua['peer_id_match_num'])    //last
              {
                $allowed_flag_peer_id = true;
              }
            }
          }
        }
        else // no need to compare version
        $allowed_flag_peer_id = true;
      }
    }
    else  // not need to match pattern
    $allowed_flag_peer_id = true;

    if($row_allowed_ua['agent_pattern'] != '')
    {
      if(!preg_match($row_allowed_ua['agent_pattern'], $row_allowed_ua['agent_start'], $match_bench))
      err("regular expression err for: " . $row_allowed_ua['agent_start'] . ", please ask sysop to fix this");

      if(preg_match($row_allowed_ua['agent_pattern'], $agent, $match_target))
      {
        if( $row_allowed_ua['agent_match_num'] != 0)
        {
          for($i = 0 ; $i < $row_allowed_ua['agent_match_num']; $i++)
          {
            if($row_allowed_ua['agent_matchtype'] == 'dec')
            {
              $match_target[$i+1] = 0 + $match_target[$i+1];
              $match_bench[$i+1] = 0 + $match_bench[$i+1];
            }
            else if($row_allowed_ua['agent_matchtype'] == 'hex')
            {
              $match_target[$i+1] = hexdec($match_target[$i+1]);
              $match_bench[$i+1] = hexdec($match_bench[$i+1]);
            }

            if ($match_target[$i+1] > $match_bench[$i+1])
            {
              $allowed_flag_agent = true;
              break;
            }
            else if($match_target[$i+1] < $match_bench[$i+1])
            {
              $allowed_flag_agent = false;
              $version_low_agent = true;
              $low_version = "Your " . $row_allowed_ua['family'] . " 's version is too low, please update it after " . $row_allowed_ua['start_name'];
              break;
            }
            else //equal
            {
              if($i+1 == $row_allowed_ua['agent_match_num'])    //last
              $allowed_flag_agent = true;
            }
          }
        }
        else // no need to compare version
        $allowed_flag_agent = true;
      }
    }
    else
    $allowed_flag_agent = true;

    if($allowed_flag_peer_id && $allowed_flag_agent)
    {
      $exception = $row_allowed_ua['exception'];
      $family_id = $row_allowed_ua['id'];
      $allow_https = $row_allowed_ua['allowhttps'];
      break;
    }
    elseif(($allowed_flag_peer_id || $allowed_flag_agent) || ($version_low_peer_id || $version_low_agent))  //client spoofing possible
    ;//add anti-cheat code here
  }

  if($allowed_flag_peer_id && $allowed_flag_agent)
  {
    if($exception == 'yes')
    {
      if (!$clients_exp = $Cache->get_value('allowed_client_exception_family_'.$family_id.'_list')){
        $clients_exp = array();
        $res = mysql_query("SELECT * FROM agent_allowed_exception WHERE family_id = $family_id") or err("check err");
        while ($row = mysql_fetch_array($res))
          $clients_exp[] = $row;
        $Cache->cache_value('allowed_client_exception_family_'.$family_id.'_list', $clients_exp, 86400);
      }
      if($clients_exp)
      {
        foreach ($clients_exp as $row_allowed_ua_exp)
        {
          if($row_allowed_ua_exp['agent'] == $agent && preg_match("/^" . $row_allowed_ua_exp['peer_id'] . "/", $peer_id))
          return "Client " . $row_allowed_ua_exp['name'] . " is banned due to: " . $row_allowed_ua_exp['comment'] . ".";
        }
      }
      // $agent_familyid = $row_allowed_ua['id'];
    }
    // else
    // {
      // $agent_familyid = $row_allowed_ua['id'];
    // }

    if(isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on")
    {
      if($allow_https == 'yes')
      return 0;
      else
      return "This client does not support https well";
    }
    else
    return 0;  // no exception found, so allowed or just allowed
  }
  else
  {
    if($version_low_peer_id && $version_low_agent)
    return $low_version;
    else
      return "Banned Client";
  }
}

function check_client_family($peer_id, $agent) {
  global $Cache;

  if (!$clients = $Cache->get_value('allowed_client_list')){
    $clients = array();
    $res = mysql_query("SELECT * FROM agent_allowed_family ORDER BY hits DESC") or err("check err");
    while ($row = mysql_fetch_array($res))
      $clients[] = $row;
    $Cache->cache_value('allowed_client_list', $clients, 86400);
  }
  foreach ($clients as $row_allowed_ua)
  {
    $allowed_flag_peer_id = false;
    $allowed_flag_agent = false;
    $version_low_peer_id = false;
    $version_low_agent = false;

    if($row_allowed_ua['peer_id_pattern'] != '')
    {
      if(!preg_match($row_allowed_ua['peer_id_pattern'], $row_allowed_ua['peer_id_start'], $match_bench))
      err("regular expression err for: ". $row_allowed_ua['peer_id_start'] . ", please ask sysop to fix this");

      if(preg_match($row_allowed_ua['peer_id_pattern'], $peer_id, $match_target))
      {
        if($row_allowed_ua['peer_id_match_num'] != 0)
        {
          for($i = 0 ; $i < $row_allowed_ua['peer_id_match_num']; $i++)
          {
            if($row_allowed_ua['peer_id_matchtype'] == 'dec')
            {
              $match_target[$i+1] = 0 + $match_target[$i+1];
              $match_bench[$i+1] = 0 + $match_bench[$i+1];
            }
            else if($row_allowed_ua['peer_id_matchtype'] == 'hex')
            {
              $match_target[$i+1] = hexdec($match_target[$i+1]);
              $match_bench[$i+1] = hexdec($match_bench[$i+1]);
            }

            if ($match_target[$i+1] > $match_bench[$i+1])
            {
              $allowed_flag_peer_id = true;
              break;
            }
            else if($match_target[$i+1] < $match_bench[$i+1])
            {
              $allowed_flag_peer_id = false;
              $version_low_peer_id = true;
              $low_version = "Your " . $row_allowed_ua['family'] . " 's version is too low, please update it after " . $row_allowed_ua['start_name'];
              break;
            }
            else if($match_target[$i+1] == $match_bench[$i+1])//equal
            {
              if($i+1 == $row_allowed_ua['peer_id_match_num'])    //last
              {
                $allowed_flag_peer_id = true;
              }
            }
          }
        }
        else // no need to compare version
        $allowed_flag_peer_id = true;
      }
    }
    else  // not need to match pattern
    $allowed_flag_peer_id = true;

    if($row_allowed_ua['agent_pattern'] != '')
    {
      if(!preg_match($row_allowed_ua['agent_pattern'], $row_allowed_ua['agent_start'], $match_bench))
      err("regular expression err for: " . $row_allowed_ua['agent_start'] . ", please ask sysop to fix this");

      if(preg_match($row_allowed_ua['agent_pattern'], $agent, $match_target))
      {
        if( $row_allowed_ua['agent_match_num'] != 0)
        {
          for($i = 0 ; $i < $row_allowed_ua['agent_match_num']; $i++)
          {
            if($row_allowed_ua['agent_matchtype'] == 'dec')
            {
              $match_target[$i+1] = 0 + $match_target[$i+1];
              $match_bench[$i+1] = 0 + $match_bench[$i+1];
            }
            else if($row_allowed_ua['agent_matchtype'] == 'hex')
            {
              $match_target[$i+1] = hexdec($match_target[$i+1]);
              $match_bench[$i+1] = hexdec($match_bench[$i+1]);
            }

            if ($match_target[$i+1] > $match_bench[$i+1])
            {
              $allowed_flag_agent = true;
              break;
            }
            else if($match_target[$i+1] < $match_bench[$i+1])
            {
              $allowed_flag_agent = false;
              $version_low_agent = true;
              $low_version = "Your " . $row_allowed_ua['family'] . " 's version is too low, please update it after " . $row_allowed_ua['start_name'];
              break;
            }
            else //equal
            {
              if($i+1 == $row_allowed_ua['agent_match_num'])    //last
              $allowed_flag_agent = true;
            }
          }
        }
        else // no need to compare version
        $allowed_flag_agent = true;
      }
    }
    else
    $allowed_flag_agent = true;

    if($allowed_flag_peer_id && $allowed_flag_agent)
    {
      $exception = $row_allowed_ua['exception'];
      $family_id = $row_allowed_ua['id'];
      $allow_https = $row_allowed_ua['allowhttps'];
      break;
    }
    elseif(($allowed_flag_peer_id || $allowed_flag_agent) || ($version_low_peer_id || $version_low_agent))  //client spoofing possible
    ;//add anti-cheat code here
  }

  if($allowed_flag_peer_id && $allowed_flag_agent)
  {
    if($exception == 'yes')
    {
      if (!$clients_exp = $Cache->get_value('allowed_client_exception_family_'.$family_id.'_list')){
        $clients_exp = array();
        $res = mysql_query("SELECT * FROM agent_allowed_exception WHERE family_id = $family_id") or err("check err");
        while ($row = mysql_fetch_array($res))
          $clients_exp[] = $row;
        $Cache->cache_value('allowed_client_exception_family_'.$family_id.'_list', $clients_exp, 86400);
      }
      if($clients_exp)
      {
        foreach ($clients_exp as $row_allowed_ua_exp)
        {
          if($row_allowed_ua_exp['agent'] == $agent && preg_match("/^" . $row_allowed_ua_exp['peer_id'] . "/", $peer_id))
          return "Client " . $row_allowed_ua_exp['name'] . " is banned due to: " . $row_allowed_ua_exp['comment'] . ".";
        }
      }
      $agent_familyid = $row_allowed_ua['id'];
    }
    else
    {
      $agent_familyid = $row_allowed_ua['id'];
    }

    return $agent_familyid;  // no exception found, so allowed or just allowed
  }

  return 0;
}
