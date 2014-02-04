<?php
require_once 'includes/constants.php';

class MySql {
  private $conn;
  
  function __construct()
  {
    $this->conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME) or die("DB problem :(");
  }
  
  function verify_credentials($username, $hashed_password)
  {
    $this->check_username_defined($username);
    
    $query = "SELECT curr_quest FROM ".TBL_USERS." where uname = ? AND pwd = ? LIMIT 1";
    if ( $stmt = $this->conn->prepare($query) ) {
      $stmt->bind_param('ss', $username, $hashed_password);
      $stmt->execute();
      if ( $stmt->fetch() ) {
        $stmt->close();
        return true;
      }
    }
    return false;
  }
  
  function get_curr_quest($username)
  {
    $this->check_username_defined($username);
    
    $query = "SELECT curr_quest FROM ".TBL_USERS." where uname = ?";
    
    if ( $stmt = $this->conn->prepare($query) ) {
      $stmt->bind_param('s', $username);
      $stmt->execute();
      $stmt->bind_result($curr_quest);
      if ( $stmt->fetch() ) {
        $stmt->close();
        return $curr_quest;
      }
    }
    return -1;
  }
  
  function get_next_quest($username)
  {
    $curr_q = $this->get_curr_quest($username);
   
    $query = "SELECT curr_quest FROM ".TBL_USERS." where uname = ? LIMIT 1";
    if ( $stmt = $this->conn->prepare($query) ) {
      $admin_name = USR_NAME_ADMIN;
      $stmt->bind_param('s', $admin_name);
      $stmt->execute();
      $stmt->bind_result($admin_curr_quest);
      if ( $stmt->fetch() ) {
        $stmt->close();
        if ($curr_q+1 <= $admin_curr_quest) {
          $curr_q += 1;
          $this->update_curr_quest($username, $curr_q);
          return $curr_q;
        }
      }
    }
    return -1;
  }
  
  function update_curr_quest($username, $curr_quest)
  {
    $this->check_username_defined($username);
    
    $query = "UPDATE ".TBL_USERS." SET curr_quest=? where uname = ?";
    if ( $stmt = $this->conn->prepare($query) ) {
      $stmt->bind_param('ds', $curr_quest, $username);
      $stmt->execute();
      $stmt->close();
    }
  }
  
  function setConnected($username, $val = true)
  {
    $this->check_username_defined($username);
    
    $query = "UPDATE ".TBL_USERS." SET connected=? where uname = ?";
    if ( $stmt = $this->conn->prepare($query) ) {
      $stmt->bind_param('ds', $val, $username);
      $stmt->execute();
      $stmt->close();
    }
  }
  
  function admin_connected()
  {
    $query = "SELECT connected FROM ".TBL_USERS." where uname = ? LIMIT 1";
    if ( $stmt = $this->conn->prepare($query) ) {
      $admin_name = USR_NAME_ADMIN;
      $stmt->bind_param('s', $admin_name);
      $stmt->execute();
      $stmt->bind_result($admin_connected);
      if ( $stmt->fetch() ) {
        $stmt->close();
        return $admin_connected;
      }
    }
    return false;
  }
  
  function check_username_defined($username)
  {
    if (!isset($username) || $username == USR_NAME_UNDEFINED) {
      die("Could not complete DB request: User name is undefined");
    }
  }
}

?>