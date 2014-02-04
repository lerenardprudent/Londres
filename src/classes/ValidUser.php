<?php
require_once 'MySql.php';
require_once 'includes/constants.php';

class ValidUser {
  function __construct($uname = USR_NAME_UNDEFINED) {
    $this->mysql = new MySql();
    $this->username = $uname;
  }
  
  function validate_user($username, $password) {
    $creds = $this->mysql->verify_credentials($username, md5($password));
    if ( $creds ) {
      $_SESSION[SESS_KEY] = SESS_AUTH_VAL;
      $this->username = $username;
      $curr_quest = $this->mysql->get_curr_quest($this->username);
      header("location: index.php?".USR_NAME_KEY."=".$username."&".Q_STATUS_KEY."=".($curr_quest == 1 ? 0 : 1));
    }
    else {
      return "Please enter a correct username and/or password!";
    }
  }
  
  function confirm() {
    session_start();
    if ($_SESSION[SESS_KEY] != SESS_AUTH_VAL) {
      header("location: login.php");
      return false;
    }
    $this->mysql->setConnected($this->username);
    return true;
  }
  
  function start_or_resume_questionnaire() {
    $this->mysql->update_curr_quest($this->username, 1);
  }
  
  function get_current_question() {
    return $this->mysql->get_curr_quest($this->username);
  }
  
  function get_next_question() {
    return $this->mysql->get_next_quest($this->username);
  }
  
  function instructor_connected() {
    return $this->mysql->admin_connected();   
  }
  
  function log_out() {
    $this->mysql->setConnected($this->username, false);
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