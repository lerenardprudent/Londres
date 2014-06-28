<?php

require_once 'classes/User.php';
require_once 'classes/Constants.php';
session_start();

$C = new Constants();
$U = new User();

$uname_key = $C['USR_NAME_KEY'];
$pwd_key = $C['PWD_KEY'];

if (isset($_GET[$C['STAT_KEY']]) && $_GET[$C['STAT_KEY']] == $C['SESS_END_VAL']) {
  if ( $U->authorised() ) {
    $U->logout();
  }
  session_destroy();
  header("location: " . $C['SRC_PHP_LOGIN']);
  echo "DONE";
}

$_SESSION = array();

if ($_POST && isset($_POST[$pwd_key])) {
  $password = filter_var($_POST[$pwd_key], FILTER_SANITIZE_STRING);
  
  $resp = $U->validate_credentials($password);
}
else if ( isset($_GET[$C['REDIRECT_KEY']]) ) {
  $resp = "Please log in before proceeding to the questionnaire.";
  unset($_GET);
}
else {
  $resp = "Please enter your access code to begin!";
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional/EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html" charset="utf-8" />
<title>Welcome to VERITAS</title>
<script src="js/jquery/jquery-1.10.2.min.js"></script>  
<link rel="stylesheet" type="text/css" href="css/login.css">
<script type="text/javascript">
  $(document).ready( function() {
    $('h4.alert').hide().fadeIn(700);
    $('h4.alert').append('<span class="exit">X</span>');
    
    $('span.exit').click(function() {
      $('h4.alert').fadeOut('slow');
    });
    
    setTimeout( function() { $('span.exit').click() }, 6000);
  });
</script>
</head>
<body>
<div id="login">
  <form method="post" action="">
    <h2>VERITAS <small>Login</small></h2>
    <p>
      <label for="<?php echo $C['PWD_KEY']; ?>">Access code:</label>
      <input type="password" name="<?php echo $C['PWD_KEY']; ?>" autofocus/>
      <input type="submit" id="submit" value="Enter" name="submit"/>
    </p>
  </form>
  <?php 
    if (isset($resp)) {
      echo "<h4 class='alert'>".$resp;
    }
  ?>
    
</div>
</body>
</html>