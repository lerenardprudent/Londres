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
    $usrtbl = $this->C['TBL_USERS'];
    $uid_key = $this->C['USR_ID_KEY'];
    $curr_pos_key = $this->C['CURR_POS_KEY'];
    
    /*** prepare the select statement ***/
    $stmt = $dbh->prepare("SELECT " . $uid_key . "," . $curr_pos_key . ", uname FROM " . $usrtbl . " WHERE uname = :phpro_username AND pwd = :phpro_password");
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
    return implode("|", array( $row[$uid_key], $row[$curr_pos_key], $row['uname']));
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
  
  function update_curr_pos($uid, $new_curr_pos)
  {
    $_SESSION[$this->C['MAX_POS_KEY']] = $new_curr_pos;
    
    $dbh = $this->initNewPDO();
    $users_table = $this->C['TBL_USERS'];
    
    $stmt = $dbh->prepare("UPDATE " . $users_table . " SET curr_pos = :curr_pos WHERE uid = :uid");
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':curr_pos', $new_curr_pos, PDO::PARAM_STR);
    $this->execute($stmt);
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
  
  function save_answer($uid, $taskno, $qno, $answered, $ans_info, $coords, $addr, $searches)
  {
    $dbh = $this->initNewPDO();
    $dbh2 = $this->initNewPDO();
    $answers_tbl = $this->C['TBL_ANSWERS'];
    $geom_type = "point";
    $geom_str = ( $answered ? "GeomFromText(:geom_txt)" : "null" );
    $geom_txt = "POINT(" . $coords . ")";
    $ret_code = -1;
    
    /*** prepare the select statement ***/
    $stmt = $dbh->prepare("INSERT INTO " . $answers_tbl . " (taskno,qno,answered,ansinfo,geom_type,id,searches,addr,geom) VALUES (:taskno,:qno,:answered,:ans_info, :geom_type,:uid,:searches,:addr," . $geom_str . ")");
    /*** bind the parameters ***/
    $stmt->bindParam(':taskno', $taskno, PDO::PARAM_INT);
    $stmt->bindParam(':qno', $qno, PDO::PARAM_INT);
    $stmt->bindParam(':answered', $answered, PDO::PARAM_INT);
    $stmt->bindParam(':ans_info', $ans_info, PDO::PARAM_INT);
    $stmt->bindParam(':geom_type', $geom_type, PDO::PARAM_STR);
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':searches', $searches, PDO::PARAM_STR);
    $stmt->bindParam(':addr', $addr, PDO::PARAM_STR);
    if ( $answered ) { $stmt->bindParam(':geom_txt', $geom_txt, PDO::PARAM_STR); }
    $ret_code = $this->execute($stmt);
    $rowsUpdated = $stmt->rowCount();
    
    if ( $ret_code == 23000 ) {
      $stmt2 = $dbh2->prepare("UPDATE " . $answers_tbl . " SET answered=:answered, ansinfo=:ans_info, addr=:addr, geom_type=:geom_type, geom=" . $geom_str . ",searches=:searches WHERE id=:uid AND taskno=:taskno AND qno=:qno");
      $stmt2->bindParam(':uid', $uid, PDO::PARAM_INT);
      $stmt2->bindParam(':taskno', $taskno, PDO::PARAM_INT);
      $stmt2->bindParam(':qno', $qno, PDO::PARAM_INT);
      $stmt2->bindParam(':answered', $answered, PDO::PARAM_INT);
      $stmt2->bindParam(':ans_info', $ans_info, PDO::PARAM_INT);
      $stmt2->bindParam(':addr', $addr, PDO::PARAM_STR);
      $stmt2->bindParam(':geom_type', $geom_type, PDO::PARAM_STR);
      if ( $answered ) { $stmt2->bindParam(':geom_txt', $geom_txt, PDO::PARAM_STR); }
      $stmt2->bindParam(':searches', $searches, PDO::PARAM_STR);
      $ret_code = $this->execute($stmt2);
      $rowsUpdated = $stmt2->rowCount();
    }
    
    if ( $ret_code == 0 ) {
      if ( $rowsUpdated == 0) {
        $this->log_back( "Nothing done during update of user (ID: " . $uid . ")" );
      }
      return true;
    }
    return false;
  }
  
  function log_back($log_msg)
  {
    $_SESSION[$this->C['MYSQL_LOG']] = $log_msg;
  }

  function admin_connected()
  {
    $dbh = $this->initNewPDO();
    $usrtbl = $this->C['TBL_USERS'];
    $admin_uname = $this->C['USR_NAME_ADMIN'];
    
    $stmt = $dbh->prepare("SELECT connected, curr_pos FROM " . $usrtbl . " WHERE uname = :admin_uname");
    /*** bind the parameters ***/
    $stmt->bindParam(':admin_uname', $admin_uname, PDO::PARAM_STR);

    /*** execute the prepared statement ***/
    $this->execute($stmt);
    /*** check for a result ***/
    $row = $stmt->fetch();
    
    /*** if we have no result then fail boat ***/
    if ($row == false || $row['connected'] == "0" ) {
      return false; 
    }
    
    return $row['curr_pos'];
  }
  
}

?>