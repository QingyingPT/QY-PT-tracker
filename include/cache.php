<?php
// CACHE class (Based on file From ProjectGazelle)
// See: https://github.com/WhatCD/Gazelle/blob/master/classes/cache.class.php

class CACHE extends Memcache{
	private $cacheReadTimes = 0;
	private $cacheWriteTimes = 0;
  private $keyHits = [
    'read' => [],
    'write' => [],
  ];

	function __construct($host = 'localhost', $port = 11211) {
		$success = $this->connect($host, $port); // Connect to memcache
		if (!$success) {
      throw new RuntimeException('Can\'t connact to Memcached');
		}
	}

	function lock($Key){
		$this->cache_value('lock_'.$Key, 'true', 3600);
	}
	
	function unlock($Key) {
		$this->delete('lock_'.$Key);
	}

  function check_lock($Key) {
    return $this->get_value('lock_'.$Key) ? true : false;
  }
	
	function cache_value($Key, $Value, $Duration = 3600){
		$this->set($Key, $Value, 0, $Duration);

		$this->cacheWriteTimes++;
		$this->keyHits['write'][$Key] = !isset($this->keyHits['write'][$Key]) ? 1 : $this->keyHits['write'][$Key] + 1;
	}

	function get_value($Key) {
		$res = $this->get($Key);

    if ($res == false) return false;

		$this->cacheReadTimes++;
		$this->keyHits['read'][$Key] = !isset($this->keyHits['read'][$Key]) ? 1 : $this->keyHits['read'][$Key] + 1;

		return $res;
	}

	function delete_value($Key, $AllLang = false){
    $this->delete($Key);
	}

	function getCacheReadTimes() {
		return $this->cacheReadTimes;
	}

	function getCacheWriteTimes() {
		return $this->cacheWriteTimes;
	}
	
	function getKeyHits($type = 'read') {
		return $this->keyHits[$type];
	}
}
