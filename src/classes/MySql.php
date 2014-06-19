<?php
require_once 'classes/Constants.php';

class MySql {
  private $conn;
  
  function __construct()
  {
    $this->C = new Constants();
    $this->conn = new mysqli($this->C['DB_SERVER'], $this->C['DB_USER'], $this->C['DB_PASSWORD'], $this->C['DB_NAME']) or die("DB problem :(");
  }
  
  function initNewPDO()
  {
    $mysql_hostname = $this->C['DB_SERVER'];
    $mysql_username = $this->C['DB_USER'];
    $mysql_password = $this->C['DB_PASSWORD'];
    $mysql_dbname = $this->C['DB_NAME'];
    
    try {
      $dbh = new PDO("mysql:host=$mysql_hostname;dbname=$mysql_dbname", $mysql_username, $mysql_password);
      $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      
      return $dbh;
    }
    catch ( Exception $e ) {
      die( "DB connection failed (Reason: <i>" . $e->getMessage() . "</i>)" );
    }
  }

  function execute($stmt)
  {
    try {
      $stmt->execute();
    }
    catch(Exception $e)
    {
      /*** if we are here, something has gone wrong with the database ***/
      $message = 'We are unable to process your request. Please try again later"';
      echo $e->getMessage();
    }
  }
  
  function verify_credentials($username, $salted_password)
  {
    $dbh = $this->initNewPDO();
    $uid_key = $this->C['USR_ID_KEY'];
    $curr_pos_key = $this->C['CURR_POS_KEY'];
    $usrtbl = $this->C['TBL_USERS'];

    /*** prepare the select statement ***/
    $stmt = $dbh->prepare("SELECT uid, curr_pos FROM " . $usrtbl . " WHERE uname = :phpro_username AND pwd = :phpro_password");
    /*** bind the parameters ***/
    $stmt->bindParam(':phpro_username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':phpro_password', $salted_password, PDO::PARAM_STR, 40);

    /*** execute the prepared statement ***/
    $this->execute($stmt);
    /*** check for a result ***/
    $row = $stmt->fetch();
    
    /*** if we have no result then fail boat ***/
    if ($row == false) {
      return false;
    }
    /*** if we do have a result, all is well ***/
    else {
      /*** set the session user_id variable ***/
      $_SESSION[$uid_key] = $row[$uid_key];
      $_SESSION[$curr_pos_key] = $row[$curr_pos_key];

      /*** tell the user we are logged in ***/
      return true;
    }
  }
  
  function set_connected($uid, $val = true)
  {
    $dbh = $this->initNewPDO();
    $users_table = $this->C['TBL_USERS'];
    
    $stmt = $dbh->prepare("UPDATE " . $users_table . " SET connected = :connected_val WHERE uid = :uid");
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':connected_val', $val, PDO::PARAM_BOOL);
    $this->execute($stmt);
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