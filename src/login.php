<?php

require_once 'classes/User.php';
require_once 'classes/Constants.php';
session_start();

$C = new Constants();
$U = new User();

$uname_key = $C['USR_NAME_KEY'];
$pwd_key = $C['PWD_KEY'];

if (isset($_GET[$C['STAT_KEY']]) && $_GET[$C['STAT_KEY']] == $C['SESS_END_VAL']) {
  $U->logout();
}

if ($_POST && isset($_POST[$uname_key]) && isset($_POST[$pwd_key])) {
  $username = filter_var($_POST[$uname_key], FILTER_SANITIZE_STRING);
  $password = filter_var($_POST[$pwd_key], FILTER_SANITIZE_STRING);
  
  $resp = $U->validate_credentials($username, $password);
}
else if ( isset($_GET[$C['REDIRECT_KEY']]) ) {
  $resp = "Please log in before proceeding to the questionnaire.";
  unset($_GET);
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional/EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html" charset="utf-8" />
<title>Login to access</title>
<script src="js/jquery/jquery-1.10.2.min.js"></script>  
<link rel="stylesheet" type="text/css" href="css/login.css">
<script type="text/javascript">
  $(document).ready( function() {
    $('h4.alert').hide().fadeIn(700);
    $('h4.alert').append('<span class="exit">X</span>');
    
    $('span.exit').click(function() {
      $('h4.alert').fadeOut('slow');
    });
  });
</script>
</head>
<body>
<div id="login">
  <form method="post" action="">
    <h2>Login <small>Enter your credentials</small></h2>
    <p>
      <label for="<?php echo $C['USR_NAME_KEY']; ?>">Username:</label>
      <input type="text" name="<?php echo $C['USR_NAME_KEY']; ?>" />
    </p>
    <p>
      <label for="<?php echo $C['PWD_KEY']; ?>">Password:</label>
      <input type="password" name="<?php echo $C['PWD_KEY']; ?>" />
    </p>
    <p>
      <input type="submit" id="submit" value="Login" name="submit" />
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