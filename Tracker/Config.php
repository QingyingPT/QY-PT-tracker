<?php namespace Tracker;

class Config {
  public static $annInterval = 1800;
  public static $annIntervals = [1200, 1800, 2600];
  public static $annIntervalsThreshold = [3 * 86400, 30 * 86400];

  public static $peerReserve = 86400; // TODO: check SQL server load

  public static $seedTimeChunk = 86400 * 3; // TODO: check
}
