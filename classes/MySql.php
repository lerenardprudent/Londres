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
      $dbh = new PDO("mysql:host=$mysql_hostname;dbname=$mysql_dbname", $mysql_username, $mysql_password, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
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
  
  function verify_credentials($salted_password)
  {
    $dbh = $this->initNewPDO();
    $usrtbl = $this->C['TBL_USERS'];
    $uid_key = $this->C['USR_ID_KEY'];
    $curr_pos_key = $this->C['CURR_POS_KEY'];
    
    /*** prepare the select statement ***/
    $stmt = $dbh->prepare("SELECT " . $uid_key . "," . $curr_pos_key . ", uname, admin FROM " . $usrtbl . " WHERE pwd = :phpro_password");
    /*** bind the parameters ***/
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
    return implode("|", array( $row[$uid_key], $row[$curr_pos_key], $row['uname'], $row['admin']));
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
  
  function save_answer($uid, $taskno, $qno, $answered, $ans_info, $coords, $addr, $labl, $searches)
  {
    $dbh = $this->initNewPDO();
    $dbh2 = $this->initNewPDO();
    $answers_tbl = $this->C['TBL_ANSWERS'];
    $num_coords = count(explode(",", $coords));
    if ( $num_coords > 1 && strlen($addr) == 0 ) {
      $geom_txt_ends = array( "POLYGON((", "))");
      $coo = array($coords);
    }
    else {
      $geom_txt_ends = array("POINT(", ")");
      $coo = explode(",", $coords);
    }
    $geom_str = ( $answered ? "GeomFromText(:geom_txt)" : "null" );
    $addresses = explode('¦', $addr );
    $labels = explode('¦', $labl );
    $ret_code = -1;
    
    /* Delete any existing answer(s) */
    $stmt0 = $dbh->prepare("DELETE FROM " . $answers_tbl . " WHERE id = :id and taskno = :taskno and qno = :qno");
    /*** bind the parameters ***/
    $stmt0->bindParam(':id', $uid, PDO::PARAM_INT);
    $stmt0->bindParam(':taskno', $taskno, PDO::PARAM_INT);
    $stmt0->bindParam(':qno', $qno, PDO::PARAM_INT);
    $this->execute($stmt0);
    
    /*** prepare the select statement ***/
    for ( $i = 0; $i < count($coo); $i++ ) {
      $sql = "INSERT INTO " . $answers_tbl . " (taskno,qno,answered,ansinfo,num_coords,id,searches,addr,label,geom) VALUES (:taskno,:qno,:answered,:ans_info, :num_coords,:uid,:searches,:addr,:label," . $geom_str . ")";
      $stmt = $dbh->prepare($sql);
      /*** bind the parameters ***/
      $stmt->bindParam(':taskno', $taskno, PDO::PARAM_INT);
      $stmt->bindParam(':qno', $qno, PDO::PARAM_INT);
      $stmt->bindParam(':answered', $answered, PDO::PARAM_INT);
      $stmt->bindParam(':ans_info', $ans_info, PDO::PARAM_STR);
      $stmt->bindParam(':num_coords', $num_coords, PDO::PARAM_INT);
      $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
      $stmt->bindParam(':searches', $searches, PDO::PARAM_STR);
      $stmt->bindParam(':addr', $addresses[$i], PDO::PARAM_STR);
      $stmt->bindParam(':label', $labels[$i], PDO::PARAM_STR);
      if ( $answered ) { $geom_txt = $geom_txt_ends[0] . $this->swap_coords($coo[$i]) . $geom_txt_ends[1]; $stmt->bindParam(':geom_txt', $geom_txt, PDO::PARAM_STR); }
      $ret_code = $this->execute($stmt);
      $rowsUpdated = $stmt->rowCount();
    }
    
    /*
    if ( $ret_code == 23000 ) {
      $stmt2 = $dbh2->prepare("UPDATE " . $answers_tbl . " SET answered=:answered, ansinfo=:ans_info, addr=:addr, label=:label, num_coords=:num_coords, geom=" . $geom_str . ",searches=:searches WHERE id=:uid AND taskno=:taskno AND qno=:qno");
      $stmt2->bindParam(':uid', $uid, PDO::PARAM_INT);
      $stmt2->bindParam(':taskno', $taskno, PDO::PARAM_INT);
      $stmt2->bindParam(':qno', $qno, PDO::PARAM_INT);
      $stmt2->bindParam(':answered', $answered, PDO::PARAM_INT);
      $stmt2->bindParam(':ans_info', $ans_info, PDO::PARAM_STR);
      $stmt2->bindParam(':addr', $addr, PDO::PARAM_STR);
      $stmt2->bindParam(':label', $labl, PDO::PARAM_STR);
      $stmt2->bindParam(':num_coords', $num_coords, PDO::PARAM_INT);
      if ( $answered ) { $stmt2->bindParam(':geom_txt', $geom_txt, PDO::PARAM_STR); }
      $stmt2->bindParam(':searches', $searches, PDO::PARAM_STR);
      $ret_code = $this->execute($stmt2);
      $rowsUpdated = $stmt2->rowCount();
    }
    */
    
    if ( $ret_code == 0 ) {
      if ( $rowsUpdated == 0) {
        $this->log_back( "Nothing done during update of user (ID: " . $uid . ")" );
      }
      return true;
    }
    return false;
  }
  
  function swap_coords($str)
  {
    $pattern = '/([-\.0123456789]+) ([-\.0123456789]+)/';
    $replacement = '${2} ${1}';
    $flipped = preg_replace($pattern, $replacement, $str);
    return $flipped;
  }
  
  function log_back($log_msg)
  {
    $_SESSION[$this->C['MYSQL_LOG']] = $log_msg;
  }

  function admin_connected($uid)
  {
    $dbh = $this->initNewPDO();
    $usrtbl = $this->C['TBL_USERS'];
    $admin_uname = $this->C['USR_NAME_ADMIN'];
    
    $sql = "select u2.curr_pos from (select * from " . $usrtbl . " where uid = :uid) u join (select * from " . $usrtbl . " where connected = 1) u2 on u.resp_admin = u2.uname";
    $stmt = $dbh->prepare($sql);
    /*** bind the parameters ***/
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);

    /*** execute the prepared statement ***/
    $this->execute($stmt);
    /*** check for a result ***/
    $row = $stmt->fetch();
    
    /*** if we have no result then fail boat ***/
    if ($row == false  ) {
      return false; 
    }
    
    return $row['curr_pos'];
  }
  
  function get_users_codes()
  {
    $dbh = $this->initNewPDO();
    $usrtbl = $this->C['TBL_USERS'];
    $admin_uname = $this->C['USR_NAME_ADMIN'];
    $stmt = $dbh->prepare("SELECT pwd FROM " . $usrtbl . " WHERE uname != :admin_uname");
    $stmt->bindParam(':admin_uname', $admin_uname, PDO::PARAM_STR);
    $this->execute($stmt);
    $rows = $stmt->fetchAll();
    $codes = array();
    foreach ( $rows as $row ) {
      $code = $row['pwd'];
      array_push($codes, $code);
    }
    return $codes;
  }
  
  function create_entries($codes, $admin_name)
  {
    $dbh = $this->initNewPDO();
    $usrtbl = $this->C['TBL_USERS'];
    $params = array();
    for ( $x = 0; $x < count($codes); $x++ ) {
      array_push($params, "('xyz', :code" . $x . ", :resp_admin)");
    }
    $values = implode(",", $params);
    $sql = "INSERT INTO " . $usrtbl . " (uname, pwd, resp_admin) VALUES " . $values;
    $stmt = $dbh->prepare($sql);
    for ( $x = 0; $x < count($codes); $x++ ) {
      $stmt->bindParam(':code' . $x, $codes[$x], PDO::PARAM_STR);
    }
    $stmt->bindParam(':resp_admin' , $admin_name, PDO::PARAM_STR);
    $this->execute($stmt);
    $rows_updated = $stmt->rowCount();
  }
  
  function delete_entries($codes)
  {
    $dbh = $this->initNewPDO();
    $usrtbl = $this->C['TBL_USERS'];
    $w = array();
    for ( $x = 0; $x < count($codes); $x++ ) {
      array_push($w, "pwd = :code" . $x);
    }
    $where = " WHERE " . implode(" OR ", $w);
    $sql = "DELETE FROM " . $usrtbl . $where;
    $stmt = $dbh->prepare($sql);
    for ( $x = 0; $x < count($codes); $x++ ) {
      $stmt->bindParam(':code' . $x, $codes[$x], PDO::PARAM_STR);
    }
    $this->execute($stmt);
    $num_del = $stmt->rowCount();
    return $num_del == count($codes);
  }
  
  function update_attrs($uid, $gender, $dob)
  {
    $dbh = $this->initNewPDO();
    $usrtbl = $this->C['TBL_USERS'];
    $stmt = $dbh->prepare("UPDATE " . $usrtbl . " SET sex=:sex, bdate=:dob WHERE uid = :uid");
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':sex', $gender, PDO::PARAM_STR);
    $stmt->bindParam(':dob', $dob, PDO::PARAM_STR);

    /*** execute the prepared statement ***/
    $this->execute($stmt);
  }
  
  function answer_exists($uid, $taskno, $qno)
  {
    $dbh = $this->initNewPDO();
    $tbl_ans = $this->C['TBL_ANSWERS'];
    
    $stmt = $dbh->prepare("SELECT AsText(geom) AS geom_txt, addr, label FROM " . $tbl_ans . " WHERE id=:uid AND taskno=:taskno AND qno=:qno AND answered=1");
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':taskno', $taskno, PDO::PARAM_INT);
    $stmt->bindParam(':qno', $qno, PDO::PARAM_INT);

    $this->execute($stmt);
    $rows = $stmt->fetchAll();
    if ($rows === false ) {
      return false;
    }
    $coords = array();
    $addrs = array();
    $labels = array();
    foreach ( $rows as $row ) {
      array_push($coords, $this->swap_coords($row['geom_txt']));
      array_push($addrs, $row['addr']);
      array_push($labels, $row['label']);
    }
    return implode('|', array(implode('¦', $coords), implode('¦', $addrs), implode('¦', $labels)));
  }
  
  function get_users()
  {
    $dbh = $this->initNewPDO();
    $usrtbl = $this->C['TBL_USERS'];
    $cols = array('uid', 'bdate', 'sex', 'curr_pos');
    $sql = 
    $stmt = $dbh->prepare("SELECT " . implode(',', $cols) . " FROM " . $usrtbl);
    $this->execute($stmt);
    $rows = $stmt->fetchAll();
    $users = array();
    foreach ( $rows as $row ) {
      $rowObj = new stdClass();
      foreach($row as $key => $value) {
        if ( !is_numeric($key) ) {
          $rowObj->$key = $value;
        }
      }
      array_push($users, json_encode($rowObj));
    }
    $res = new stdClass();
    $col_name = "cols";
    $res->$col_name = $cols;
    $col_name = "rows";
    $res->$col_name = $users;
    return $res;
  }
  
function get_answers()
  {
    $dbh = $this->initNewPDO();
    $anstbl = $this->C['TBL_ANSWERS'];
    $cols = array('id', 'taskno', 'qno', 'answered', 'ansinfo');
    $sql = 
    $stmt = $dbh->prepare("SELECT " . implode(',', $cols) . " FROM " . $anstbl);
    $this->execute($stmt);
    $rows = $stmt->fetchAll();
    $answers = array();
    foreach ( $rows as $row ) {
      $rowObj = new stdClass();
      foreach($row as $key => $value) {
        if ( !is_numeric($key) ) {
          $rowObj->$key = $value;
        }
      }
      array_push($answers, json_encode($rowObj));
    }
    $res = new stdClass();
    $col_name = "cols";
    $res->$col_name = $cols;
    $col_name = "rows";
    $res->$col_name = $answers;
    return $res;
  }
  
  function get_questions_answered()
  {
    $dbh = $this->initNewPDO();
    $usrtbl = $this->C['TBL_USERS'];
    $anstbl = $this->C['TBL_ANSWERS'];
    $cols = array('id', 'taskno', 'qno', 'answered', 'ansinfo');
    $sql = 
    $stmt = $dbh->prepare("SELECT distinct concat(concat(a.taskno, '-'), a.qno) as q from $anstbl a join $usrtbl u on a.id = u.uid and u.admin = 0 order by q");
    $this->execute($stmt);
    $rows = $stmt->fetchAll();
    $qs = array();
    foreach ( $rows as $row ) {
      array_push($qs, $row['q']);
    }
    return $qs;
  }
}

?>