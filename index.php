<?php

require_once 'classes/Constants.php';
require_once 'classes/User.php';
require_once 'classes/Questionnaire.php';
session_start();

$C = new Constants();
$U = new User();
$Q = new Questionnaire(); 
$code_leng = 6;

$sess_curr_pos = -1;
$curr_pos_key = $C['CURR_POS_KEY'];
$first_time = false;

if ($U->authorised()) {
  $sess_curr_pos = $_SESSION[$curr_pos_key];
  $instr_err_html = $U->instructor_ahead();
  $btn_text = validate_pos();
  
  $is_instructor = $U->is_instructor();
  if (isset($_POST['submit'])) {
    $curr_pos = $sess_curr_pos; // $curr_pos variable gets set only once user clicks on Start/Resume/Repeat Questionnaire
  }
  else {
    if ( isset($_POST['downl_codes']) && isset($_POST[$C['CREATED_USERS_KEY']]) ) {
      download_access_codes(explode(",", $_POST[$C['CREATED_USERS_KEY']]));
    }
    
    if ( isset($_POST['undo_create']) && isset($_POST[$C['CREATED_USERS_KEY']]) ) {
      delete_users_by_pwd(explode(",", $_POST[$C['CREATED_USERS_KEY']]));
    }
    
    if ( isset($_POST['gen_users']) && isset($_POST[$C['CREATE_USERS_KEY']]) ) {
      $generated_codes = generate_new_codes(intval($_POST[$C['CREATE_USERS_KEY']]));
    }
  }
}
else {
  /* Redirect to login screen */
  redirect_to_login();
}

function redirect_to_login()
{
  global $C;
  header("location: ".$C['SRC_PHP_LOGIN'] . "?" . $C['REDIRECT_KEY'] . "=1");
}

function generate_new_codes($n)
{
  global $U;
  global $code_leng;
  
  $all_curr_codes = $U->get_all_users_codes();
  $new_codes = array();
  if ( $all_curr_codes ) {
    while ( count($new_codes) < $n ) {
      $new_code = generate_random_code($code_leng);
      if ( !in_array(sha1($new_code), $all_curr_codes) ) {
        array_push($new_codes, $new_code);
      }
    }
  }
  $U->create_users($new_codes);
  return $new_codes;
}

function generate_random_code($leng) { 

    $chars = "abcdefghijkmnopqrstuvwxyz023456789"; 
    srand((double)microtime()*1000000); 
    $i = 0; 
    $pass = '' ; 

    while ($i < $leng) { 
        $num = rand() % 33; 
        $tmp = substr($chars, $num, 1); 
        $pass = $pass . $tmp; 
        $i++; 
    } 

    return $pass; 
} 

function create_li_elems($new_codes)
{
  foreach ( $new_codes as $code ) {
    echo "<li>" . $code . "</li>";
  }
}

function delete_users_by_pwd($pwds)
{
  global $U;
  $ok = $U->delete_users($pwds);
  echo ( $ok ? "Done" : "Error" );
}

function download_access_codes($codes)
{
  $content = 
    "VERITAS\n" .
    "=======\n".
    "Access codes of newly created users:\n" . implode("\n", $codes);
    
  header('Content-Type: text/plain');
  header('Content-Disposition: attachement; filename="access_codes.txt');
  echo $content;
  exit();
}

function validate_pos()
{
  global $U, $Q, $C;
  global $sess_curr_pos;
  global $first_time;
  
  if ( $U->pos_leq($Q->get_final_pos()) ) {
    $_SESSION[$C['CURR_POS_KEY']] = $Q->get_final_pos(); /* Set it again in case the value was bogatively high */
    return "Repeat questionnaire";
  }
  
  if ( $U->pos_less( $Q->get_initial_pos() ) ) {
    return "Resume questionnaire";
  }
  
  if ( $U->pos_greater( $Q->get_initial_pos() ) ) {
    $first_time = true;
  }
  
  /* This triggers a call to reset_curr_pos() later on in this file */
  $_SESSION[$C['CURR_POS_KEY']] = $Q->get_final_pos(); 
  
  return "Start questionnaire";
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional/EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html" charset="utf-8" />
<title>VERITAS London</title>
<!--<link rel="stylesheet" href="css/login.css">-->
<link rel="stylesheet" href="css/style.css" />
<script type="text/javascript" src="js/jquery/jquery-1.10.2.min.js"></script>
<link rel="stylesheet" type="text/css" href="css/jquery-ui.css" />	
<link rel="stylesheet" type="text/css" href="css/jquery-ui-1.10.3.custom.min.css" />
<script type="text/javascript" src="js/jquery/jquery-ui-1.10.3.custom.min.js"></script>
<script type="text/javascript" src="constants.js"></script>
<script type="text/javascript">
  var uname_key = 'uid';
  var _retryPeriod = 20;
  var _retrySecsLeft;
  var _timer;
  var uname = null;
  function parseUrl() {
    var urlParams = {};
    var query = window.location.search.substring(1).split("&");
    for (var i = 0, max = query.length; i < max; i++) {
      if (query[i] === "") // check for trailing & with no param
        continue;

      var param = query[i].split("=");
      urlParams[decodeURIComponent(param[0])] = decodeURIComponent(param[1] || "");
    }
    return urlParams;
  }
    
  $(document).ready( function() {
    var logoutHref = Consts.get('SRC_PHP_LOGIN') + '?' + Consts.get('STAT_KEY') + '=' + Consts.get('SESS_END_VAL');
    $('.logout-link').attr('href', logoutHref);
    
    $('.retry').each(function() {
      $('h3').text('Retry situation');
      $('#submit').attr('value', 'Retry now');
      startCountdownToRetry();
    });
    
    $('.pause').each(function() {
      $('h3').text('Please wait');
      $('#submit').attr('value', 'Retry');
      $('#hack').attr('value', 'hack');
      $('html').css('cursor', 'progress');
    });
    
    $('.question').each(function() {
      $('h3').text('The questionnaire');
      $('#submit').attr('value', 'Submit');
    });
    
    $("input[type='text']").each(function() {
      $('input[type="submit"]').prop('disabled', $(this).prop('value').length == 0);
      $(this).keyup(function() {$('input[type="submit"]').prop('disabled', $(this).val().length == 0);});
    });
    
    if ( $('.codes-list').children().length > 0 ) {
      $('.draggable').css('display', 'inline-block').draggable({handle: ".draggable-heading"});
    }
    
    $("input[name='" + Consts.get('DOB_KEY') + "']").on('keyup', function(e) {
      var dob = $(this).val();
      $('#submit').prop('disabled', dob.length == 0 || $('input[name="gender"]:checked').length == 0);
    });
    
    $('#dob').datepicker({ maxDate: "-10y", dateFormat: "yy-mm-dd" });
  });
</script>
</head>
<body>
<a title='Log out' class='logout-icon logout-link'>Log out</a>
<div id="container">
  <p>
    <h3>Welcome to VERITAS! <?php if ( $is_instructor ) { echo "[You have <i>instructor</i> priviledges]"; } ?></h3>
  </p>
  <form method="post" action="">
    <?php
    if (strlen($instr_err_html) > 0) {
      echo $instr_err_html;
    }
    else if ( isset($curr_pos) ) {
      $gender = "";
      $dob = "";
      if ( isset($_POST[$C['DOB_KEY']] ) ) {
        $dob = $_POST[$C['DOB_KEY']];
      }
      if ( isset($_POST[$C['GENDER_KEY']] ) ) {
        $gender = $_POST[$C['GENDER_KEY']];
      }
      if ( isset($gender) && isset($dob) ) {
        $U->set_attributes($gender, $dob);
      }
      
      if ( $curr_pos == $Q->get_final_pos() ) {
        $U->reset_curr_pos();
      }
      
      header("location: " . $C['SRC_PHP_QUEST'] );
    }
    ?>
    <div style='display: <?php echo ( $first_time && strlen($instr_err_html) == 0 ? 'block' : 'none' ); ?>'>
      <span class='radio-div'>
        <label>You are:</label>
        <input id='m' type="radio" name="<?php echo $C['GENDER_KEY']; ?>" value='M' onclick="maybeEnableSubmitBtn();" />
        <label for='m'>Male</label>
        <input id='f' type="radio" name="<?php echo $C['GENDER_KEY']; ?>" value='F' onclick="maybeEnableSubmitBtn();"/>
        <label for='f'>Female</label>
        <p/>
      </span>
      <span>
        <label>Your date of birth:</label>
        <input id='dob' onchange="maybeEnableSubmitBtn();" name='<?php echo $C['DOB_KEY']; ?>' type='date' />
      </span>
      <p/>
    </div>
    <input type="submit" id="submit" value="<?php echo $btn_text; ?>" name="submit" <?php echo ($first_time && strlen($instr_err_html) == 0 ? "disabled" : ""); ?> />
    <?php if ($is_instructor) { echo "<input type='button' onclick=\"$('.new-users').show();\" value='Generate user codes' />"; } ?>
    <div class='new-users'>
      <span>How many new users?</span>
      <input class='num-new-users' name='<?php echo $C['CREATE_USERS_KEY']; ?>' type='number' min='1' />
      <input name='gen_users' type='submit' value='Go' />
      <input name='<?php echo $C['CREATED_USERS_KEY']; ?>' type='hidden' value='<?php if (isset($generated_codes)) {echo implode(",", $generated_codes); } ?>' />
    </div>
    
    <div class="ui-widget-content new-codes draggable">
      <div class="draggable-heading ui-widget-header">
        <span>New user(s) created</span>
        <button class='close-draggable close' onclick='wipeCodes();' title='Closes panel and removes search result icons from map'>x</button>
      </div>
      <div class='draggable-contents'>
        <ul class='codes-list'><?php if ( isset($generated_codes) ) { create_li_elems($generated_codes); } ; ?></ul>
        <button onclick="printCodes();">Print codes</button>
        <input name='downl_codes' type='submit' value='Download codes' />
        <input name='undo_create' type='submit' value='Erase users' />
      </div>
    </div>
  </form>
  <p />
</div>
<script type="text/javascript">	
  function countDownByOne() {
    _retrySecsLeft -= 1;
    $('.secs-left').text(_retrySecsLeft);
    _timer = setTimeout( _retrySecsLeft == 1 ? retry : countDownByOne, 1000 );
  }
  
  function startCountdownToRetry()
  {
    $('.secs-left').text(_retryPeriod);
    _retrySecsLeft = _retryPeriod;
    _timer = setTimeout( function() {
      countDownByOne();
    }, 1000 );
  }
  
  function retry()
  {
    $('.retry-info').text("Retrying...");
    setTimeout(function() { $('input').click() }, 500 );
  }
  
  function wipeCodes()
  {
    $('.codes-list').html("");
    $('.draggable').hide();
  }
  
  function printCodes()
  {
    var restorePage = document.body.innerHTML;
    var printContent = "<h3>New user(s) access codes</h3><ul>" + $('.codes-list')[0].outerHTML + "</ul>";
    document.body.innerHTML = printContent;
    $('li').addClass('li-print');
    window.print();
    document.body.innerHTML = restorePage;
  }
  
  function maybeEnableSubmitBtn()
  {
    $('#submit').prop('disabled', $("input[name='" + Consts.get('DOB_KEY') + "']").val().length == 0 || $("input[name='" + Consts.get('GENDER_KEY') + "']:checked").length == 0);
  }
  
  function pad(n)
  {
    return ( n < 10 ? "0" + n : n);
  }
  
  function getDateStr()
  {
    var date = $('#dob').datepicker("getDate");
    var datestr = date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate()) + " " +  pad(date.getHours()) + ":" + pad(date.getMinutes()) + ":" + pad(date.getSeconds());
    return datestr;
  }

</script>
</body>
</html>