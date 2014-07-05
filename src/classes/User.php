<?php
require_once 'MySql.php';
require_once 'classes/Constants.php';
require_once 'classes/Questionnaire.php';

class User {
  private $is_instructor;
  
  function __construct() {
    $this->mysql = new MySql();
    $this->C = new Constants();
    $this->Q = new Questionnaire();
    $this->uid = -1;
    $this->curr_pos = "0" . $this->C['CURR_POS_SEPARATOR'] . "0";
    $this->is_instructor = (isset($_SESSION[$this->C['IS_INSTR_KEY']]) ? $_SESSION[$this->C['IS_INSTR_KEY']] : false);
  }
  
  function validate_credentials($password) {
    /* Encrypt before sending */
    $password = sha1( $password );
    
    $valid = $this->mysql->verify_credentials($password);
    if ( $valid !== false ) {
      $this->login($valid);
      $_SESSION[$this->C['STAT_KEY']] = $this->C['SESS_AUTH_VAL'];
      header("location: ".$this->C['SRC_PHP_INDEX']);
    }
    else {
      return "Please enter a correct username and/or password!";
    }
  }
  
  function authorised() {
    $is_authorised = (isset($_SESSION[$this->C['STAT_KEY']]) && $_SESSION[$this->C['STAT_KEY']] == $this->C['SESS_AUTH_VAL']);
    if ($is_authorised) {
      $this->uid = $_SESSION[$this->C['USR_ID_KEY']];
      $this->curr_pos = $_SESSION[$this->C['MAX_POS_KEY']];
    }
    
    return $is_authorised;
  }
  
  function login($db_vals) {
    $tokens = explode("|", $db_vals);
    $uid = $tokens[0];
    $curr_pos = $tokens[1];
    
    $_SESSION[$this->C['USR_ID_KEY']] = $uid;
    $_SESSION[$this->C['CURR_POS_KEY']] = $curr_pos;
    $_SESSION[$this->C['MAX_POS_KEY']] = $curr_pos;
    $this->mysql->set_connected($uid);
    $this->is_instructor = ( $tokens[2] == $this->C['USR_NAME_ADMIN'] );
    $_SESSION[$this->C['IS_INSTR_KEY']] = $this->is_instructor;
  }
  
  function logout() {
    if (isset($_SESSION[$this->C['STAT_KEY']])) {
      unset($_SESSION[$this->C['STAT_KEY']]);
    }
    
    $this->mysql->set_connected($this->uid, false);
  }
  
  function save_answer($taskno, $qno, $answered, $ans_info, $coords, $addr, $searches) {
    if ( $taskno == 3 && $qno == 2 && $ans_info == "NOANS_SNHN" ) {
      $related_ans = $this->question_answered(3,1);
      if ( $related_ans ) {
        $answered = 1;
        $geom_txt = explode('|', $related_ans)[0];
        $start_pos = strpos($geom_txt, "((")+2;
        $end_pos = strpos($geom_txt, "))");
        $coo = substr($geom_txt, $start_pos, $end_pos - $start_pos);
        $coords = $coo;
      }
    }
    $this->mysql->save_answer($this->uid, $taskno, $qno, $answered, $ans_info, $coords, $addr, $searches);
  }
  
  function reset_curr_pos()
  {
    $init_pos = $this->Q->get_initial_pos();
    $this->update_pos($init_pos);
    $_SESSION[$this->C['CURR_POS_KEY']] = $init_pos;
  }
  
  function set_attributes($gender, $pos)
  {
    echo "HERE";
    $this->mysql->update_attrs($this->uid, $gender, $pos);
  }
  
  function update_curr_pos()
  {
    $curr_pos = $_SESSION[$this->C['CURR_POS_KEY']];
    if ( $this->pos_greater($curr_pos) ) {
      $this->update_pos($curr_pos);
    }
  }
  
  function update_pos($new_pos)
  {
    $this->curr_pos = $new_pos;
    $this->mysql->update_curr_pos($this->uid, $this->curr_pos);
  }
  
  function instructor_connected()
  {
    return $this->mysql->admin_connected();   
  }
  
  function instructor_ahead()
  {
    $prob = "";
      if ( !$this->is_instructor ) {
      $instr_pos = $this->instructor_connected();
      if ( !$instr_pos ) {
        $prob = '<p class="retry">Instructor not connected!</p>' .
                "<p class='retry-info'>Will retry in <span class='secs-left'></span> seconds...</p>";
      }
      else if ($this->pos_less($instr_pos) ) {
        $prob = '<p class="pause">The instructor is currently demonstrating Question ' . str_replace($this->C['CURR_POS_SEPARATOR'], "&ndash;", $instr_pos) . '.</p>'.
                '<input id="hack" name="hack" type="text" style="display: none" />';
      }
    }
    return $prob;
  }
  
  function instr_ok()
  {
    return strlen($this->instructor_ahead()) == 0;
  }
  
  function pos_greater($pos)
  {
    $tokens1 = explode($this->C['CURR_POS_SEPARATOR'], $pos);
    $tokens2 = explode($this->C['CURR_POS_SEPARATOR'], $this->curr_pos);
    
    $t1 = intval($tokens1[0]);
    $q1 = intval($tokens1[1]);
    $t2 = intval($tokens2[0]);
    $q2 = intval($tokens2[1]);
    
    return $t1 > $t2 || ( $t1 == $t2 && $q1 > $q2 );
  }
  
  function pos_less($pos)
  {
    $tokens1 = explode($this->C['CURR_POS_SEPARATOR'], $pos);
    $tokens2 = explode($this->C['CURR_POS_SEPARATOR'], $this->curr_pos);
    $t1 = intval($tokens1[0]);
    $q1 = intval($tokens1[1]);
    $t2 = intval($tokens2[0]);
    $q2 = intval($tokens2[1]);
    
    return $t1 < $t2 || ( $t1 == $t2 && $q1 < $q2 );
  }
  
  function pos_leq($pos)
  {
    $tokens1 = explode($this->C['CURR_POS_SEPARATOR'], $pos);
    $tokens2 = explode($this->C['CURR_POS_SEPARATOR'], $this->curr_pos);
    $t1 = intval($tokens1[0]);
    $q1 = intval($tokens1[1]);
    $t2 = intval($tokens2[0]);
    $q2 = intval($tokens2[1]);
    
    return $t1 <= $t2 || ( $t1 == $t2 && $q1 <= $q2 );
  }
  
  function is_instructor()
  {
    return $this->is_instructor;
  }
  
  function get_all_users_codes()
  {
    if ( $this->is_instructor ) {
      return $this->mysql->get_users_codes();
    }
    return false;
  }
  
  function create_users($codes)
  {
    for ( $x = 0; $x < count($codes); $x++ ) { $codes[$x] = sha1($codes[$x]); }
    return $this->mysql->create_entries($codes);
  }
  
  function delete_users($codes)
  {
    for ( $x = 0; $x < count($codes); $x++ ) { $codes[$x] = sha1($codes[$x]); }
    return $this->mysql->delete_entries($codes);
  }
  
  function question_answered($taskno, $qno)
  {
    return $this->mysql->answer_exists($this->uid, $taskno, $qno);
  }
}

?>