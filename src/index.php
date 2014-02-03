<?php

require_once 'includes/constants.php';
require_once 'classes/ValidUser.php';

$usr = new ValidUser();
if (isset($_GET[USR_NAME_KEY])) {
  $usr = new ValidUser($_GET[USR_NAME_KEY]);
  if ( $usr->confirm() ) {
    //$usr->start_or_resume_questionnaire();
  }
}

if (isset($_POST['submit'])) {
  if ($instr_connected = $usr->instructor_connected()) {
    $curr_quest = $usr->get_current_question();
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
<script type="text/javascript">
  var uname_key = 'uid';
  var uname = null;
  function parseUrl() {
        var urlParams = {};
        var query = window.location.search.substring(1).split("&");
        for (var i = 0, max = query.length; i < max; i++)
        {
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
      var logoutHref = $('a').attr('href');
      logoutHref += "&" + uname_key + "=" + uname;
      $('a').attr('href', logoutHref);
    }
    var qstat_key = 'qstat';
    if ( qstat_key in params ) {
      var resume_quest = params[qstat_key];
      if ( parseInt(resume_quest) ) {
        $('input').attr('value', 'Resume questionnaire');
      }
    }
    
    $('.error').each(function() {
      $('h3').text('Error');
    });
    
    $('.question').each(function() {
      $('h3').text('The questionnaire');
      $('#submit').attr('value', 'Submit');
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
      echo '<p>Please wait for your instructor to log in, then try again.</p>
      ';
    }
    
    if (isset($curr_quest)) {
      echo "<p class='question'>Question ".$curr_quest."</p>";
      echo "<p>\"Blah blah blah?\"</p>";
    }
    ?>
    <input type="submit" id="submit" value="Start / Resume" name="submit" />
  </form>
  <p />
  <a href="login.php?status=loggedout">Log out</a>
</div>
</body>
</html>