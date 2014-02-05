<?php
require_once 'classes/Constants.php';

class MySql {
  private $conn;
  
  function __construct()
  {
    $this->C = new Constants();
    $this->conn = new mysqli($this->C['DB_SERVER'], $this->C['DB_USER'], $this->C['DB_PASSWORD'], $this->C['DB_NAME']) or die("DB problem :(");
  }
  
  function verify_credentials($username, $hashed_password)
  {
    $this->check_username_defined($username);
    
    $query = "SELECT curr_quest FROM ".$this->C['TBL_USERS']." where uname = ? AND pwd = ? LIMIT 1";
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
    
    $query = "SELECT curr_quest FROM ".$this->C['TBL_USERS']." where uname = ?";
    
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
  
  function update_current_question($username, $new_curr)
  {
    $this->check_username_defined($username);
    
    $query = "SELECT curr_quest FROM ".$this->C['TBL_USERS']." where uname = ? LIMIT 1";
    if ( $stmt = $this->conn->prepare($query) ) {
      $admin_name = $this->C['USR_NAME_ADMIN'];
      $stmt->bind_param('s', $admin_name);
      $stmt->execute();
      $stmt->bind_result($admin_curr_quest);
      if ( $stmt->fetch() ) {
        $stmt->close();
        if ($new_curr <= $admin_curr_quest || $username == $this->C['USR_NAME_ADMIN']) {
          $this->update_curr($username, $new_curr);
          return $new_curr;
        }
        return -$new_curr;
      }
    }
    return -1;
  }
  
  function update_curr($username, $curr_quest)
  {
    $query = "UPDATE ".$this->C['TBL_USERS']." SET curr_quest=? where uname = ?";
    if ( $stmt = $this->conn->prepare($query) ) {
      $stmt->bind_param('ds', $curr_quest, $username);
      $stmt->execute();
      $stmt->close();
    }
  }
  
  function save_answer($username, $qno, $answer)
  {
    $this->check_username_defined($username);
    
    $query = "INSERT INTO ".$this->C['TBL_ANSWERS']." (uname, qno, ans) values (?, ?, ?)";
    if ( $stmt = $this->conn->prepare($query) ) {
      $stmt->bind_param('sds', $username, $qno, $answer);
      $stmt->execute();
      if ( $stmt->affected_rows == 1 )
        return true;
    }
    
    $query = "UPDATE ".$this->C['TBL_ANSWERS']." SET ans = ? WHERE uname = ? AND qno = ?";
    if ( $stmt = $this->conn->prepare($query) ) {
      $stmt->bind_param('ssd', $answer, $username, $qno);
      $stmt->execute();
      if ( $stmt->affected_rows == 1 )
        return true;
    }
    
    return false;
  }
  
  function set_connected($username, $val = true)
  {
    $this->check_username_defined($username);
    
    $query = "UPDATE ".$this->C['TBL_USERS']." SET connected=? where uname = ?";
    if ( $stmt = $this->conn->prepare($query) ) {
      $stmt->bind_param('ds', $val, $username);
      $stmt->execute();
      $stmt->close();
    }
  }
  
  function admin_connected()
  {
    $query = "SELECT connected FROM ".$this->C['TBL_USERS']." where uname = ? LIMIT 1";
    if ( $stmt = $this->conn->prepare($query) ) {
      $admin_name = $this->C['USR_NAME_ADMIN'];
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
    if (empty($username)) {
      die("Could not complete DB request: User name is undefined");
    }
  }
}

?>