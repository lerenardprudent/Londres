<?php
require_once 'MySql.php';
require_once 'classes/Constants.php';

class ValidUser {
  function __construct($uname = '') {
    $this->mysql = new MySql();
    $this->username = $uname;
    $this->C = new Constants();
  }
  
  function validate_user($username, $password) {
    $creds = $this->mysql->verify_credentials($username, md5($password));
    if ( $creds ) {
      $_SESSION[$this->C['SESS_KEY']] = $this->C['SESS_AUTH_VAL'];
      $this->username = $username;
      $curr_quest = $this->mysql->get_curr_quest($this->username);
      header("location: ".$this->C['SRC_PHP_INDEX']."?".$this->C['USR_NAME_KEY']."=".$username."&".$this->C['Q_STATUS_KEY']."=".($curr_quest == 1 ? 0 : 1));
    }
    else {
      return "Please enter a correct username and/or password!";
    }
  }
  
  function confirm() {
    session_start();
    return ($_SESSION[$this->C['SESS_KEY']] == $this->C['SESS_AUTH_VAL']);
  }
  
  function setUsername($name) {
    $this->username = $name;
  }
  
  function connect() {
    $this->mysql->set_connected($this->username);
  }
  
  function get_current_question() {
    return $this->mysql->get_curr_quest($this->username);
  }
  
  function save_answer($answer) {
    $curr_quest = $this->mysql->get_curr_quest($this->username);
    $this->mysql->save_answer($this->username, $curr_quest, $answer);
  }
  
  function update_current_question()
  {
    $curr_quest = $this->mysql->get_curr_quest($this->username);
    return $this->mysql->update_current_question($this->username, $curr_quest+1);
  }
  
  function instructor_connected() {
    return $this->mysql->admin_connected();   
  }
  
  function log_out() {
    $this->mysql->set_connected($this->username, false);
    if (isset($_SESSION[$this->C['SESS_KEY']])) {
      unset($_SESSION[$this->C['SESS_KEY']]);
      
      if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 1000);
        session_destroy();
      }
    }
  }
}

?>