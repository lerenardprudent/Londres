<?php

require_once 'classes/Constants.php';
require_once 'classes/User.php';
session_start();

$C = new Constants();
$U = new User();

$Q = json_decode(utf8_encode(file_get_contents('includes/questionnaire.json')));

$sess_curr_pos = -1;
$curr_pos_key = $C['CURR_POS_KEY'];
$curr_pos = $_SESSION[$curr_pos_key];
$tokens = explode(',', $curr_pos);
$taskno = $tokens[0];
$qno = $tokens[1];
$qok = true;

if ($U->authorised()) {
  if (isset($_POST['ansSubmitted']) ) {
    $next_pos = find_next_quest();
    if ( $next_pos === false ) {
      $qok = false;
    }
    else {
      $_SESSION[$curr_pos_key] = $next_pos;
      $curr_pos = $next_pos;
      $tokens = explode(',', $curr_pos);
      $taskno = $tokens[0];
      $qno = $tokens[1];
      // TODO : Update DB
    }
  }
  
  if ( $qok ) {
    $info = $Q->$taskno->$qno;
    $qtext = $info->html;
    echo $curr_pos . ": " . $qtext;
  }
  else {
    echo "-- End of questionnaire --";
  }
}
else {
  /* Redirect to login screen */
  redirect_to_login();
}

function find_next_quest()
{
  global $curr_pos;
  global $taskno;
  global $qno;
  global $Q;
  
  $next_q = strval(intval($qno)+1);
  if ( property_exists($Q->$taskno, $next_q )) {
    return $taskno . "," . $next_q;
  }
  
  $next_t = strval(intval($taskno)+1);
  $qq = "0";
  if ( property_exists($Q, $next_t) ) {
    if ( property_exists($Q->$next_t, $qq) ) {
      return $next_t . "," . $qq;
    }
  }
  
  return false;
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

<!-- ########################## INCLUDES SECTION ########################## -->

<!-- JQUERY -->
<script type="text/javascript" src="js/jquery/jquery-1.10.2.min.js"></script>

<!-- PROJECT SCRIPTS -->
<script type="text/javascript" src="constants.js"></script>
<script type="text/javascript" src="questionnaire.js"></script>
<script type="text/javascript" src="carto.js"></script>
<link rel="stylesheet" href="css/style.css" />
<link rel="stylesheet" href="css/search.css" media="screen" type="text/css" />

<!-- GOOGLE MAPS -->
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD4qnbkOe5X7pll7qyIFtkeLzjzkbPnAGo&amp;libraries=places,drawing,geometry,panoramio,weather&region=ca&sensor=false"></script>

<!-- FONTAWESOME -->
  <link href='http://fonts.googleapis.com/css?family=Scada:400,700' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Cabin:400,500,600,700' rel='stylesheet' type='text/css'>
  <link href='http://homepages.uc.edu/~arthurra/resource/font-awesome/font-awesome.css' rel='stylesheet'>
  <link rel='stylesheet prefetch' href='http://netdna.bootstrapcdn.com/font-awesome/2.0/css/font-awesome.css'>
  
  
<!-- BOOTSTRAP SCRIPTS
<script type="text/javascript" src="js/bootstrap/bootstrap.min.js"></script>
<script type="text/javascript" src="js/bootstrap/bootstrap-filestyle.min.js"> </script>
<link rel="stylesheet" href="css/bootstrap/bootstrap.min.css"> -->

<!-- IMPROMPTU SCRIPTS -->
<script type="text/javascript" src="js/impromptu/jquery-impromptu.js"></script>
<link rel="stylesheet" href="css/jquery-impromptu.css" />

<!-- ##################### END OF INCLUDES SECTION ######################### -->

<!-- The variables -->
<script type="text/javascript">
  var _latLngLondon;
  var _map;
  var _mapmarker, _markerCoords, _markerAddr;
  var _geocoder;
	var _placesServ;
  var _infoBubble;
  var _timer;
  var _searchQueries = [];
</script>

<script type="text/javascript">
  $(document).ready( function() {
    var logoutHref = Consts.get('SRC_PHP_LOGIN') + '?' + Consts.get('STAT_KEY') + '=' + Consts.get('SESS_END_VAL');
    $('.logout-link').attr('href', logoutHref);
    
    $(window).resize( handleWindowResize );
    $(window).resize();
    
    var searchField = $('#searchfield');
    var searchBtn = $('.search-button');
    searchField.val("");
    searchField.on('keypress', function(e) {
      if (e.which == 13 ) {
        var searchString = $(this).val();
        searchBtn.click();
      }
    });
    searchBtn.on('mouseover', function(e) {
      searchField.focus();
    });
    searchBtn.click( function() {
      var searchString = searchField.val();
      if ( searchString.length > 0 ) {
        doGoogleSearch(searchString);
        searchField.val("");
      }
    });
    
    $('.submit-btn').click(prepareSubmitForm);
    initMap();
  });
</script>
</head>
<body>
  <div id="container">
    <div id='topPanel'>
      <span class='headingText'>VERITAS London</span>
      <a style='float:right' class='logout-link'>Log out</a>
      <p class='mini-line-break' />
      <div class='search-slider'>
        <div class='search-container'>
          <input id='searchfield' class='searchf' placeholder='What do you wish to find on the map?' type='text'>
          <a class='search-button'>
            <i class='icon-search'></i>
          </a>
        </div>
      </div>
      <form method="post" action="" style='height: 200px'>
        <input id='ansQID' name='ansQID' type='hidden' value='<?php echo $_SESSION[$curr_pos_key]; ?>'/>
        <input id='ansCoords' name='ansCoords' type='hidden' />
        <input id='ansSearches' name='ansSearches' type='hidden' />
        <input name='ansSubmitted' type='hidden' value='1' />
        <input id='submit' type='submit' value='Submit answer &rarr;' class='submit-btn' disabled/>
      </form>
    </div>
    <div id='main'>
      <div id='map' class='adjust-height'></div>
    </div>
  </div>
  
  <script type="text/javascript">	
    function handleWindowResize()
    {
      var screenHeight = $(window).height() - 12 /* Margin defined somewhere in CSS as 8px */;
      $('body').find('.adjust-height').each( function() {
        var offset = $(this).offset().top;
        var adjustedHeight = screenHeight - offset;
        log("Adjusted height of div '" + $(this).prop('id') + "': ", adjustedHeight);
        $(this).height(adjustedHeight);
      });
    }
    
    function log()
    {
      if ( typeof(window.console ) === 'undefined') {
        return;
      }
  
      var str = "";
      var firstArgPos = 0;
      var logType = "log";
     
      if ( arguments.length >= 1 && typeof(arguments[0].logType) !== 'undefined') {
        firstArgPos = 1;
        logType = arguments[0].logType;
      }
      
      var note = ( typeof(arguments[firstArgPos]) === 'string' ? arguments[firstArgPos] : "" );
      var obj = ( arguments.length-firstArgPos == 2 ? arguments[firstArgPos+1] : arguments[firstArgPos] );
      if ( note !== obj ) {
        window.console[logType](note, obj);
      }
      else {
        window.console[logType](obj);
      }
    }
    
    // Escape a string for HTML interpolation.
    function escapeSpecialChars(str) {
      var htmlEscapes = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#x27;',
        '/': '&#x2F;',
        ',': '&#44;',
        ' ': '&#32;'
      };
      return str.replace(/[&<>"'\/, ]/g, function(match) {
        return htmlEscapes[match]; });
    }
  
    function prepareSubmitForm()
    {
      $('#ansSearches').val(_searchQueries.join(","));
      $('#ansCoords').val(_markerCoords.lat() + "," + _markerCoords.lng());
    }
  </script>
</body>
</html>