<?php
require_once 'includes/constants.php';

class MySql {
  private $conn;
  
  function __construct() {
    $this->conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME) or die("DB problem :(");
  }
  
  function verify_credentials($username, $hashed_password) {
    $query = "SELECT admin FROM users where uname = ? AND pwd = ? LIMIT 1";
    
    if ( $stmt = $this->conn->prepare($query) ) {
      $tbl_name = TBL_USERS;
      $stmt->bind_param('ss', $username, $hashed_password);
      $stmt->execute();
      $stmt->bind_result($is_admin);
      if ( $stmt->fetch() ) {
        $stmt->close();
        return true;
      }
    }
    return false;
  }
}

?>