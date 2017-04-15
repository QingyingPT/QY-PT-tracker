<?php
ini_set('display_errors', 0);

function shutdown() {
  $error = error_get_last();
  if ($error) {
    if ($error['type'] === E_ERROR) {
      header('HTTP/1.1 500 Internal Server Error');
      print json_encode(['error' => $error]);
    }
  }
}

register_shutdown_function(shutdown);
