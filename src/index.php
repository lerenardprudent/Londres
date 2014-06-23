<?php

require_once 'classes/Constants.php';
require_once 'classes/User.php';
require_once 'classes/Questionnaire.php';
session_start();

$C = new Constants();
$U = new User();
$Q = new Questionnaire(); 

$sess_curr_pos = -1;
$curr_pos_key = $C['CURR_POS_KEY'];
if ($U->authorised()) {
  
  $instr_html = $U->instructor_ahead();
  
  if (isset($_POST['submit'])) {
    $sess_curr_pos = $_SESSION[$curr_pos_key];
    $curr_pos = $sess_curr_pos;
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
  });
</script>
</head>
<body>
<a title='Log out' class='logout-icon logout-link'>Log out</a>
<div id="container">
  <p>
    <h3>You're in!</h3>
  </p>
  <form method="post" action="">
    <?php
    if (strlen($instr_html) > 0) {
      echo $instr_html;
    }
    else if ( isset($curr_pos) ) {
      if ( $curr_pos == $Q->get_final_pos() ) {
        $U->reset_curr_pos();
      }
      header("location: " . $C['SRC_PHP_QUEST']);
    }
    ?>
    <input type="submit" id="submit" value="<?php if ( $Q->is_final_screen($sess_curr_pos) ) echo "Repeat questionnaire"; else if ( $sess_curr_pos != "0,0" ) echo "Resume questionnaire"; else echo "Start questionnaire"; ?>" name="submit" />
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
</script>
</body>
</html>