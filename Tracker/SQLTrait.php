<?php namespace Tracker;

trait SQLHelper {
  static function genBatchUpdateSql($table, $fields, $updates, $how) {
    // TODO: limit rows

    $updatestring = implode('),(',
      array_map(function ($row) {
        return is_array($row) ? ("'" . implode("','", $row) . "'") : $row;
      }, $updates)
    );

    $fieldstring = implode(',', $fields);
    $howstring = implode(',', $how);

    return "INSERT INTO $table ($fieldstring) VALUES ($updatestring) ON DUPLICATE KEY UPDATE $howstring";
  }
}
