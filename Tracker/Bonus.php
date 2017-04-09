<?php namespace Tracker;

use Tracker\SQL;

class Bonus extends SQL {
  const coff = [
    'DaysByTorrent' => 7,
    'HoursByDays' => 10,
    'AmountByTorrent' => 400,
  ];

  public $result = NULL;

  static function seedTime2BonusFormula ($time) {
    // TODO: bonus formula
    static $k = (self::coff['AmountByTorrent'] / self::coff['DaysByTorrent'] * self::coff['HoursByDays']);
    return round($time / 3600 * $k);
  }

  // TODO: weight torrent
  static function sumTrafficTime (&$traffics/* , torrentMap */) {
    return array_sum($traffics);
  }

  function updateUserSeedBonus ($userid, &$traffics) {
    // TODO: use intermediate to dynamic bonus
    $sum = static::sumTrafficTime($traffics);
    $bonus = static::seedTime2BonusFormula($sum);

    if ($bonus > 0) {
      $this->sql->query("UPDATE tracker_bonus SET bonus=bonus+$bonus WHERE id='$userid'");

      if ($this->sql->error) {
        return $this->sql->error;
      } else if ($this->sql->affected_rows == 0) {
        $this->result = [
          'bonus' => 0,
          'sum' => 0,
        ];
        return 1;
      }
    }

    $this->result = [
      'bonus' => $bonus,
      'sum' => $sum,
    ];
    return 0;
  }
}
