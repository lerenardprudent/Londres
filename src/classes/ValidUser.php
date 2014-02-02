<?php
require_once 'MySql.php';
require_once 'includes/constants.php';

class ValidUser {
  function validate_user($username, $password) {
    $mysql = new MySql();
    $creds = $mysql->verify_credentials($username, md5($password));
    if ( $creds ) {
      $_SESSION[SESS_KEY] = SESS_AUTH_VAL;
      header("location: index.php");
    }
    else {
      return "Please enter a correct username and/or password!";
    }
  }
  
  function confirm() {
    session_start();
    if ($_SESSION[SESS_KEY] != SESS_AUTH_VAL) {
      header("location: login.php");
    }
  }
  
  function log_out() {
    if (isset($_SESSION[SESS_KEY])) {
      unset($_SESSION[SESS_KEY]);
      
      if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 1000);
        session_destroy();
      }
    }
  }
}

?>