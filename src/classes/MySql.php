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
      $_SESSION[$this->C['MYSQL_ERROR_MSG']] = $e->getMessage();
      //echo $_SESSION[$this->C['MYSQL_ERROR_MSG']];
      return $e->getCode();
    }
    unset($_SESSION[$this->C['MYSQL_ERROR_MSG']]);
    return 0;
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
  
  function save_answer($uid, $taskno, $qno, $coords, $searches)
  {
    $dbh = $this->initNewPDO();
    $dbh2 = $this->initNewPDO();
    $answers_tbl = $this->C['TBL_ANSWERS'];
    $tokens = explode(",", $coords);
    $lat = $tokens[0];
    $lng = $tokens[1];
    $geom_txt = "GeomFromText('POINT(" . $lat . " " . $lng . ")')";
    echo $geom_txt;
    $geom_type = "point";
    /*** prepare the select statement ***/
    $stmt = $dbh->prepare("INSERT INTO " . $answers_tbl . " (taskno,qno,geom_type,id) VALUES (:taskno,:qno,:geom_type,:uid)");
    /*** bind the parameters ***/
    $stmt->bindParam(':taskno', $taskno, PDO::PARAM_INT);
    $stmt->bindParam(':qno', $qno, PDO::PARAM_INT);
    $stmt->bindParam(':geom_type', $geom_type, PDO::PARAM_STR);
    //$stmt->bindParam(':geom_txt', $geom_txt, PDO::PARAM_STR);
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $ret_code = $this->execute($stmt);
    $rowsUpdated = $stmt->rowCount();
    
    if ( $ret_code == 23000 ) {
      $stmt2 = $dbh2->prepare("UPDATE " . $answers_tbl . " SET geom_type=:geom_type WHERE id=:uid AND taskno=:taskno AND qno=:qno");
      /*** bind the parameters ***/
      $stmt2->bindParam(':uid', $uid, PDO::PARAM_INT);
      $stmt2->bindParam(':taskno', $taskno, PDO::PARAM_INT);
      $stmt2->bindParam(':qno', $qno, PDO::PARAM_INT);
      $stmt2->bindParam(':geom_type', $geom_type, PDO::PARAM_STR);
      //$stmt2->bindParam(':geom_txt', $geom_txt, PDO::PARAM_STR);
      $ret_code = $this->execute($stmt2);
      $rowsUpdated = $stmt2->rowCount();
    }
    
    if ( $ret_code == 0 ) {
      if ( $rowsUpdated == 0) {
        $_SESSION[$this->C['MYSQL_LOG']] = "Nothing done during update of user (ID: " . $uid . ")";
      }
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