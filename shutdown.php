<?php
// TODO: check 'x-http-requst' header

if (isset($_REQUEST['debug']) && $_REQUEST['debug'] = 1) {
  ini_set('display_errors', 1);
} else {
  ini_set('display_errors', 0);

  register_shutdown_function(function () {
    $error = error_get_last();
    if ($error) {
      if ($error['type'] === E_ERROR || $error['type'] === E_USER_ERROR) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['error' => $error]);
      }
    }
  });
}
