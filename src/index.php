<?php
require_once 'classes/ValidUser.php';

$usr = new ValidUser();
$usr->confirm();
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional/EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html" charset="utf-8" />
<title>Member page</title>
</head>
<body>
<div id="container">
  <p>
    You're in!
  </p>
  <a href="login.php?status=loggedout">Log out</a>
</div>
</body>
</html>