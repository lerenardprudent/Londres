<?php

require_once 'includes/constants.php';
require_once 'classes/ValidUser.php';

$usr = new ValidUser();
$C = new Constants();

$sess_confirm = $usr->confirm();
// Reject user if session has not already been created
if (!$sess_confirm) {
    header("location: ".$C['SRC_PHP_LOGIN']);
}

if (isset($_GET[USR_NAME_KEY])) {
  $usr->setUsername($_GET[USR_NAME_KEY]);
}
$usr->connect();
if (isset($_POST['submit'])) {
  if ($instr_connected = $usr->instructor_connected()) {
    if (isset($_POST['answer']) || isset($_POST['hack'])) {
      $curr_quest = $usr->save_answer_get_next_question($_POST['answer']);
    }
    else {
      $curr_quest = $usr->get_current_question();
    }
  }
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional/EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html" charset="utf-8" />
<title>Member page</title>
<link rel="stylesheet" type="text/css" href="css/login.css">
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
    var params = parseUrl();
        
    if ( uname_key in params ) {
      uname = params[uname_key];
      var logoutHref = Consts.get('SRC_PHP_LOGIN') + '?' + uname_key + "=" + uname + '&' + Consts.get('SESS_KEY') + '=' + Consts.get('SESS_END_VAL');
      $('a').attr('href', logoutHref);
    }
    var qstat_key = Consts.get('Q_STATUS_KEY');
    if ( qstat_key in params ) {
      var resume_quest = params[qstat_key];
      if ( parseInt(resume_quest) ) {
        $("input[type='submit']").attr('value', 'Resume questionnaire');
      }
    }
    
    $('.error').each(function() {
      $('h3').text('Error');
      $('#submit').attr('value', 'Retry');
    });
    
    $('.pause').each(function() {
      $('h3').text('Please wait');
      $('#submit').attr('value', 'Retry');
      $('#hack').attr('value', 'hack');
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
    
    if (isset($curr_quest)) {
      if ($curr_quest < 0) {
        echo '<p class="pause">Please wait for the instructor to reach the next question.</p>';
        echo '<input id="hack" name="hack" type="text" style="display: none" />';
      }
      else {
        echo "<p class='question'>Question ".$curr_quest."</p>";
        echo "<p>\"What browser do you use?\"</p><p/>";
        echo "<div id='options'>";
        echo '<input id="answer" list="browsers" type="text" name="answer">
          <datalist id="browsers">
            <option value="Internet Explorer">
            <option value="Firefox">
            <option value="Chrome">
            <option value="Opera">
            <option value="Safari">
          </datalist>';
        echo '</div><p/>';
      }
    }
    ?>
    <input type="submit" id="submit" value="Start / Resume" name="submit" />
  </form>
  <p />
  <a>Log out</a>
</div>
</body>
</html>