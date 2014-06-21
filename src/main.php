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
$questInfo = array();
$db_err = "";
$db_log = "";

if ($U->authorised()) {
  if (isset($_POST['ansSubmitted']) ) {
    $goBack = ( $_POST['ansSubmitted'] < 0 );
    if ( $_POST['ansSubmitted'] > 0 ) {
      $U->save_answer( $taskno, $qno, $_POST['ansCoords'], $_POST['ansSearches'] );
      noteAnyDBIssues();
    }
    $next_pos = find_next_quest($goBack);
    if ( $next_pos !== false ) {
      $_SESSION[$curr_pos_key] = $next_pos;
      $curr_pos = $next_pos;
      $tokens = explode(',', $curr_pos);
      $taskno = $tokens[0];
      $qno = $tokens[1];
    }
  }
  $first_screen = ($curr_pos == '0,0');
  $x = get_object_vars($Q);
  $y = $x[count($x)-1];
  $last_pos = (count($x)-1) . "," . (count($y)-1);
  $last_screen = ($curr_pos == $last_pos);
  
  $isExplanation = false;
  $noHeading = false;
  if ( $qok ) {
    $info = $Q->$taskno->$qno;
    $qtext = $info->html;
    if ( $info->type == $C['QUEST_TEXT_EXPL'] ) {
      $isExplanation = true;
      array_push($questInfo, $C['QUEST_TEXT_EXPL']);
    }
    if ( $first_screen ) {
      array_push($questInfo, $C['QUEST_TEXT_BEGIN']);
    }
    if ( $last_screen ) {
      array_push($questInfo, $C['QUEST_TEXT_END']);
    }
    if ( isset($info->show_heading) && !$info->show_heading ) {
      $noHeading = true;
    }
  }
  else {
    echo "ERROR! Question not found :(";
  }
  
  $questInfo = implode("-", $questInfo);
}
else {
  /* Redirect to login screen */
  redirect_to_login();
}

function find_next_quest($goBack)
{
  global $curr_pos;
  global $taskno;
  global $qno;
  global $Q;
  
  $inc = 1;
  if ( isset($goBack) && $goBack ) {
    $inc = -1;
  }
  
  $next_q = strval(intval($qno)+$inc);
  if ( property_exists($Q->$taskno, $next_q )) {
    return $taskno . "," . $next_q;
  }
  
  $next_t = strval(intval($taskno)+$inc);
  if ( $goBack ) {
    $z = get_object_vars($Q->$next_t);
    $qq = strval(count($z)-1);
  }
  else {
    $qq = "0";
  }
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

function noteAnyDBIssues()
{
  global $C;
  global $db_err;
  global $db_log;
  
  $err_key = $C['MYSQL_ERROR_MSG'];
  if ( isset($_SESSION[$err_key]) ) {
    $db_err = $_SESSION[$err_key];
    unset($_SESSION[$err_key]);
  }
  
  $log_key = $C['MYSQL_LOG'];
  if ( isset($_SESSION[$log_key]) ) {
    $db_log = htmlentities($_SESSION[$log_key], ENT_QUOTES);
    unset($_SESSION[$log_key]);
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
<script type="text/javascript" src="questionnaire.js"></script>
<script type="text/javascript" src="carto.js"></script>
<link rel="stylesheet" href="css/style.css" type="text/css" />
<link rel="stylesheet" href="css/search.css" media="screen" type="text/css" />

<!-- GOOGLE MAPS -->
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD4qnbkOe5X7pll7qyIFtkeLzjzkbPnAGo&amp;libraries=places,drawing,geometry,panoramio,weather&region=ca&sensor=false"></script>

<!-- FONTS -->
<link rel="stylesheet" href="css/fonts.css" type="text/css" />
<link rel="stylesheet" href="css/font-awesome.css" type="text/css" />
<link rel="stylesheet" href="css/font-awesome-2.css" type="text/css" />
  
  
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
  var _endOfQuestionnaire = false;
  var _isExplanation = false;
</script>

<script type="text/javascript">
  $(document).ready( function() {
    var logoutHref = Consts.get('SRC_PHP_LOGIN') + '?' + Consts.get('STAT_KEY') + '=' + Consts.get('SESS_END_VAL');
    $('.logout-link').attr('href', logoutHref);
    $('.submit-btn').click(processFormSubmit);
    
    logAnyDBIssues();
    
    _isExplanation = ($('.questInfo').val().indexOf(Consts.get('QUEST_TEXT_EXPL')) >= 0);
    var firstScreen = ($('.questInfo').val().indexOf(Consts.get('QUEST_TEXT_BEGIN')) >= 0);
    _endOfQuestionnaire = ($('.questInfo').val().indexOf(Consts.get('QUEST_TEXT_END')) >= 0);
    
    if ( _isExplanation ) {
      $('.quest-block').removeClass('quest-block').addClass('explanation-block');
      $('.quest-text').removeClass('quest-text').addClass('explanation-text');
      $('.show-with-map').hide();
      $('.answer-btn').prop('disabled', false).val("Continue →");
      if ( firstScreen ) {
        $('.back-btn').hide();
        $('.answer-btn').val("Begin →");
      }
      if (_endOfQuestionnaire) {
        $('.logout-link').hide();
        $('.answer-btn').val("Terminate questionnaire");
      }
    }
    else {
      log("Preparing map display");
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
      
      initMap();
    }
  });
</script>
</head>
<body>
  <div id="container">
    <div id='topPanel'>
      <span class='headingText'>VERITAS London</span>
      <a style='float:right' class='logout-link'>Log out</a>
      <p class='mini-line-break' />
      <div class='quest-block'>
        <div class='quest-no'><span><?php if ( !$noHeading ) { if ($isExplanation) { echo "Task " . $taskno; } else { echo "Question " . str_replace(",", "&#8212;", $curr_pos); } } ?></span></div>
        <div class='quest-text'><span><?php echo $qtext; ?></span></div>
      </div>
      <p class='mini-line-break' />
      <div class='search-slider show-with-map'>
          <div class='search-container'>
            <input id='searchfield' class='searchf' placeholder='What do you wish to find on the map?' type='text'>
            <a class='search-button'>
              <i class='icon-search'></i>
            </a>
          </div>
        </div>
      <form method="post" action="" class='form'>
        <input id='ansQID' name='ansQID' type='hidden' value='<?php echo $_SESSION[$curr_pos_key]; ?>'/>
        <input id='ansCoords' name='ansCoords' type='hidden' />
        <input id='ansSearches' name='ansSearches' type='hidden' />
        <input id='ansSubmitted' name='ansSubmitted' type='hidden' />
        <input class='questInfo' type='hidden' value='<?php echo $questInfo; ?>' />
        <input class='dbErr' type='hidden' value='<?php echo $db_err; ?>' />
        <input class='dbLog' type='hidden' value='<?php echo $db_log; ?>' />
        <div class='submit-div'>
          <input id='back' type='submit' value='&larr; Go back' class='back-btn submit-btn' />
          <input id='submit' type='submit' value='Submit answer &rarr;' class='answer-btn submit-btn' disabled/>
        </div>
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
  
    function isUndef(x)
    {
      return typeof(x) === 'undefined';
    }
    
    function quote(str, delim)
    {
      if ( isUndef(delim) ) {
        delim = "\"";
      }
      return delim + str + delim;
    }
    
    function processFormSubmit(button)
    {
      $('#ansSubmitted').val(0); 
      var goingBack = ( button.currentTarget.id == "back" );
      if ( goingBack ) {
        $('#ansSubmitted').val(-1); 
      }
      else {
        if ( _isExplanation ) {
          if (_endOfQuestionnaire) {
            $('.logout-link')[0].click();
          }
        }
        else { // Don't enter here unless we have coords to submit!
          $('#ansSubmitted').val(1);
          for ( var i = 0; i < _searchQueries.length; i++ ) {
            _searchQueries[i] = quote(_searchQueries[i]);
          }
          $('#ansSearches').val(_searchQueries.join(","));
          $('#ansCoords').val(_markerCoords.lat() + "," + _markerCoords.lng());
        }
      }
    }
    
    function logAnyDBIssues()
    {
      if ( $('.dbErr').val().length > 0 ) {
        log({logType:'error'}, $('.dbErr').val());
      }
      if ( $('.dbLog').val().length > 0 ) {
        log({logType:'warn'}, $('.dbLog').val());
      }
    
    }
  </script>
</body>
</html>