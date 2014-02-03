<?php

require_once 'classes/ValidUser.php';

session_start();
$user = new ValidUser(isset($_GET[USR_NAME_KEY]) ? $_GET[USR_NAME_KEY] : '' );
if (isset($_GET[SESS_KEY]) && $_GET[SESS_KEY] == SESS_END_VAL) {
  $user->log_out();
}

if ($_POST && isset($_POST['username']) && isset($_POST['pwd'])) {
  $resp = $user->validate_user($_POST['username'], $_POST['pwd']);
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
      <label for="username">Username:</label>
      <input type="text" name="username" />
    </p>
    <p>
      <label for="pwd">Password:</label>
      <input type="password" name="pwd" />
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