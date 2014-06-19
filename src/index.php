<?php

require_once 'classes/Constants.php';
require_once 'classes/User.php';
session_start();

$C = new Constants();
$U = new User();

$sess_curr_pos = -1;
$curr_pos_key = $C['CURR_POS_KEY'];
if ($U->authorised()) {
  $sess_curr_pos = $_SESSION[$curr_pos_key];

  $U->login();
  if (isset($_POST['submit'])) {
    if ($instr_connected = $U->instructor_connected()) {
      $curr_pos = $sess_curr_pos;
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

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional/EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html" charset="utf-8" />
<title>VERITAS Londres</title>
<link rel="stylesheet" href="css/login.css">
<link rel="stylesheet" href="css/style.css" />
<script type="text/javascript" src="js/jquery/jquery-1.10.2.min.js"></script>
<script type="text/javascript" src="constants.js"></script>
<script type="text/javascript">
  var uname_key = 'uid';
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
    $('a').attr('href', logoutHref);
    
    $('.error').each(function() {
      $('h3').text('Error');
      $('#submit').attr('value', 'Retry');
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
  });
</script>
</head>
<body>
<div id="container">
  <p>
    <h3>You're in!</h3>
  </p>
  <form method="post" action="">
    <?php
    if (isset($instr_connected) && !$instr_connected) {
      echo '<p class="error">Instructor not connected!</p>';
      echo '<p>Please wait for the instructor to reconnect.</p>';
    }
    
    if (isset($curr_pos)) {
      if (false) { // TODO: Check if instructor is up to this question
        echo '<p class="pause">Please wait for the instructor to reach Question '.-$curr_quest.'.</p>';
        echo '<input id="hack" name="hack" type="text" style="display: none" />';
      }
      else {
        echo "Let's jump " . $_SESSION[$curr_pos_key];
        header("location: " . $C['SRC_PHP_QUEST']);
      }
    }
    ?>
    <input type="submit" id="submit" value="<?php if ( $sess_curr_pos != "0,0" ) echo "Resume quesionnaire"; else echo "Start questionnaire"; ?>" name="submit" />
  </form>
  <p />
  <a>Log out</a>
</div>
</body>
</html>