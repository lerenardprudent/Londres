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
    if (isset($_POST['answer'])) {
      $usr->save_answer($_POST['answer']);
      $curr_quest =  $usr->update_current_question();
    }
    else if (isset($_POST['hack'])) {
       $curr_quest = $usr->update_current_question();
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
<title>VERITAS Londres</title>

<!-- ########################## INCLUDES SECTION ########################## -->

<!-- JQUERY -->
<script type="text/javascript" src="js/jquery/jquery-1.10.2.min.js"></script>

<!-- PROJECT SCRIPTS -->
<script type="text/javascript" src="constants.js"></script>
<link rel="stylesheet" href="css/login.css">
<link rel="stylesheet" href="css/style.css" />

<!-- LEAFLET SCRIPTS -->
<script type="text/javascript" src="js/leaflet/leaflet.js"></script>
<script type="text/javascript" src="js/leaflet/leaflet.geometryutil.js"></script>
<script type="text/javascript" src="js/leaflet/leaflet.geometryutil.js"></script>  
<script type="text/javascript" src="js/leaflet/bouncemarker.js"></script>
<link rel="stylesheet" href="css/leaflet.css" />

<!-- LEAFLET DRAW SCRIPTS -->
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/Leaflet.draw.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/draw/handler/Draw.Feature.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/draw/handler/Draw.Polyline.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/draw/handler/Draw.Polygon.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/draw/handler/Draw.SimpleShape.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/draw/handler/Draw.Rectangle.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/draw/handler/Draw.Circle.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/draw/handler/Draw.Marker.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/ext/LatLngUtil.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/ext/GeometryUtil.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/ext/LineUtil.Intersect.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/ext/Polyline.Intersect.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/ext/Polygon.Intersect.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/Control.Draw.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/Tooltip.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/Toolbar.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/draw/DrawToolbar.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/edit/handler/Edit.Poly.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/edit/handler/Edit.SimpleShape.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/edit/handler/Edit.Circle.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/edit/handler/Edit.Rectangle.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/edit/EditToolbar.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/edit/handler/EditToolbar.Edit.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.draw.src/edit/handler/EditToolbar.Delete.js"></script>
<link rel="stylesheet" href="css/leaflet.draw.dist.css" />  

<!-- LEAFLET GEOSEARCH SCRIPTS -->
<!--
<script type="text/javascript" src="js/leaflet/geosearch/l.control.geosearch.js"></script>
<script type="text/javascript" src="js/leaflet/geosearch/l.geosearch.provider.openstreetmap.js"></script>
<link rel="stylesheet" href="css/l.geosearch.css" />
-->

<!-- LEAFLET LABEL SCRIPTS -->
<script type="text/javascript" src="js/leaflet/Leaflet.label/Label.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.label/BaseMarkerMethods.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.label/Marker.Label.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.label/Path.Label.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.label/Map.Label.js"></script>
<script type="text/javascript" src="js/leaflet/Leaflet.label/FeatureGroup.Label.js"></script>	
<link rel="stylesheet" href="css/leaflet.label.css" />

<!-- BOOTSTRAP SCRIPTS -->
<script type="text/javascript" src="js/bootstrap/bootstrap.min.js"></script>
<script type="text/javascript" src="js/bootstrap/bootstrap-filestyle.min.js"> </script>
<link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">

<!-- IMPROMPTU SCRIPTS -->
<script type="text/javascript" src="js/impromptu/jquery-impromptu.js"></script>
<link rel="stylesheet" href="css/jquery-impromptu.css" />

<!-- ##################### END OF INCLUDES SECTION ######################### -->

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
    
    if (isset($curr_quest)) {
      if ($curr_quest < 0) {
        echo '<p class="pause">Please wait for the instructor to reach Question '.-$curr_quest.'.</p>';
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
        echo '</div><p/><p/>';
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