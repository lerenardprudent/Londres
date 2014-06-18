<?php
require_once 'MySql.php';
require_once 'classes/Constants.php';

class User {
  function __construct() {
    $this->mysql = new MySql();
    $this->C = new Constants();
    if ( isset($_SESSION[$this->C['USR_ID_KEY']]) ) {
      $this->uid = $_SESSION[$this->C['USR_ID_KEY']];
    }
  }
  
  function validate_credentials($username, $password) {
    /* Encrypt before sending */
    $password = sha1( $password );
    
    $valid = $this->mysql->verify_credentials($username, $password);
    if ( $valid ) {
      $_SESSION[$this->C['STAT_KEY']] = $this->C['SESS_AUTH_VAL'];
      $this->uid = $_SESSION[$this->C['USR_ID_KEY']];
   //   $this->username = $username;
   //   $curr_quest = $this->mysql->get_curr_quest($this->username);
      header("location: ".$this->C['SRC_PHP_INDEX']); //."?".$this->C['USR_ID_KEY']."=".$_SESSION[$this->C['USR_ID_KEY']]."&".$this->C['Q_STATUS_KEY']."=".($curr_quest == 1 ? 0 : 1));
    }
    else {
      return "Please enter a correct username and/or password!";
    }
  }
  
  function authorised() {
    return (isset($_SESSION[$this->C['STAT_KEY']]) && $_SESSION[$this->C['STAT_KEY']] == $this->C['SESS_AUTH_VAL']);
  }
  
  function login() {
    $this->mysql->set_connected($this->uid);
  }
  
  function logout() {
    if (isset($_SESSION[$this->C['STAT_KEY']])) {
      unset($_SESSION[$this->C['STAT_KEY']]);
    }
    
    $this->mysql->set_connected($this->uid, false);
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
}

?>