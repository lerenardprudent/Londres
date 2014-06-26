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
$sep = $C['CURR_POS_SEPARATOR'];
$qok = true;
$questInfo = array();
$db_err = "";
$db_log = "";

if ($U->authorised()) {
  $curr_pos = $_SESSION[$curr_pos_key];
  $tokens = explode($sep, $curr_pos);
  $taskno = $tokens[0];
  $qno = $tokens[1];
  
  if (isset($_POST['ansSubmitted']) ) {
    $goBack = ( $_POST['ansSubmitted'] < 0 );
    if ( $_POST['ansSubmitted'] > 0 ) {
      $U->save_answer( $taskno, $qno, $_POST['ansAnswered'], $_POST['ansInfo'], $_POST['ansCoords'], $_POST['ansAddr'], $_POST['ansSearchActivity'] );
      noteAnyDBIssues();
    }
    $next_pos = find_next_quest($goBack);
    if ( $next_pos !== false ) {
      $_SESSION[$curr_pos_key] = $next_pos;
      $curr_pos = $next_pos;
      $tokens = explode($sep, $curr_pos);
      $taskno = $tokens[0];
      $qno = $tokens[1];
      if ( !$goBack) {
        $U->update_curr_pos();
      }
    }
  }
  
  if ( !$U->instr_ok() ) {
    header("location: ".$C['SRC_PHP_INDEX']);
  }
  
  $isExplanation = false;
  $noHeading = false;
  if ( $qok ) {
    $info = $Q[$taskno]->$qno;
    $qtext = $info->html;
    if ( $info->type == $C['QUEST_TEXT_EXPL'] ) {
      $isExplanation = true;
      array_push($questInfo, $C['QUEST_TEXT_EXPL']);
    }
    if ( $Q->is_first_screen($curr_pos) ) {
      array_push($questInfo, $C['QUEST_TEXT_BEGIN']);
    }
    if ( $Q->is_final_screen($curr_pos) ) {
      array_push($questInfo, $C['QUEST_TEXT_END']);
    }
    array_push($questInfo, $curr_pos);
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
  global $sep;
  
  $inc = 1;
  if ( isset($goBack) && $goBack ) {
    $inc = -1;
  }
  
  $next_q = strval(intval($qno)+$inc);
  
  if ( property_exists($Q[$taskno], $next_q )) {
    return $taskno . $sep . $next_q;
  }
  
  $next_t = strval(intval($taskno)+$inc);
  if ( $goBack ) {
    $z = get_object_vars($Q[$next_t]);
    $qq = strval(count($z)-1);
  }
  else {
    $qq = "0";
  }
  
  if ( property_exists($Q[$next_t], $qq) ) {
    return $next_t . $sep . $qq;
  }
  else {
    echo "NOPE";
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
<link rel="stylesheet" type="text/css" href="css/jquery-ui.css" />	
<link rel="stylesheet" type="text/css" href="css/jquery-ui-1.10.3.custom.min.css" />
<script type="text/javascript" src="js/jquery/jquery-ui-1.10.3.custom.min.js"></script>

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

<!-- BOOTSTRAP SCRIPTS -->
<link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
<script type="text/javascript" src="js/bootstrap/bootstrap.min.js"></script>
  
<!-- NOTY -->
<script type="text/javascript" src="js/noty/jquery.noty.js"></script>
<script type="text/javascript" src="js/noty/layouts/center.js"></script>
<script type="text/javascript" src="js/noty/themes/default.js"></script>	

<!-- IMPROMPTU SCRIPTS
<script type="text/javascript" src="js/impromptu/jquery-impromptu.js"></script>
<link rel="stylesheet" href="css/jquery-impromptu.css" />-->

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
  var _searchActivity = [];
  var _endOfQuestionnaire = false;
  var _isExplanation = false;
  var _searchModePlaces = false;
  var _placesSearchRadius = 5; // km
  var _placeMarkers = [];
  var _zoomSnapTo = false;
  var _closeUpZoomLevel = 15;
  var _placesIBOffset = new google.maps.Size(-10, 0);
</script>

<script type="text/javascript">
  $(document).ready( function() {
    var logoutHref = Consts.get('SRC_PHP_LOGIN') + '?' + Consts.get('STAT_KEY') + '=' + Consts.get('SESS_END_VAL');
    $('.logout-link').attr('href', logoutHref);
    $('.submit-btn').click(function() { processFormSubmit($(this)); });
    logAnyDBIssues();
    
    $('.search-button').click( switchSearchMode );
    setSearchSliderAttrs();
    
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
          if ( searchString.length > 0 ) {
            doGoogleSearch(searchString);
            searchField.val("");
          }
        }
      });
      searchBtn.on('mouseover', function(e) {
        searchField.focus();
      });
      
      $('.answer-btn').attr('type','button').addClass('confirm-btn');
      $('.confirm-btn').click( function() { 
        if ( $(this).hasClass('confirm-btn') ) { 
          $('#confirmModal').modal(); 
          var radioBtns = $('input[name=ansConfirm]');
          radioBtns.change(function() { 
            var idxSelected = radioBtns.index($('input[name=ansConfirm]:checked'));
            radioBtns.eq(1-idxSelected).parent().next().find('select').hide('blind', 300);
            radioBtns.eq(idxSelected).parent().next().find('select').show('blind', 300);
            setSubmitBtnEnabledStatus();
          });
          $('.ans-option').change( setSubmitBtnEnabledStatus );
        } });
      $('.btn-primary').click(function() { $('.close').click(); $('.answer-btn').toggleClass('confirm-btn').attr('type', 'submit').click(); });
      
      initMap();
    }
  });
</script>
</head>
<body>
  <div id="container">
    <div id='topPanel'>
      <span class='headingText'>VERITAS London</span>
      <a title='Log out' class='logout-icon logout-link'>Log out</a>
      <p class='mini-line-break' />
      <div class='quest-block'>
        <div class='quest-no'><span><?php if ( !$noHeading ) { if ($isExplanation) { echo "Task " . $taskno; } else { echo "Question " . str_replace($C['CURR_POS_SEPARATOR'], "&#8212;", $curr_pos); } } ?></span></div>
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
        <input id='ansAnswered' name='ansAnswered' type='hidden' />
        <input id='ansInfo' name='ansInfo' type='hidden' />
        <input id='ansCoords' name='ansCoords' type='hidden' />
        <input id='ansAddr' name='ansAddr' type='hidden' />
        <input id='ansSearchActivity' name='ansSearchActivity' type='hidden' />
        <input id='ansSubmitted' name='ansSubmitted' type='hidden' />
        <input class='questInfo' type='hidden' value='<?php echo $questInfo; ?>' />
        <input class='dbErr' type='hidden' value='<?php echo $db_err; ?>' />
        <input class='dbLog' type='hidden' value='<?php echo $db_log; ?>' />
        <div class='submit-div'>
          <input id='back' type='submit' value='&larr; Go back' class='back-btn submit-btn' />
          <input id='submit' type='submit' value='Submit answer &rarr;' class='answer-btn submit-btn'/>
        </div>
        <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModal" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel">Confirm answer</h4>
              </div>
              <div class="modal-body">
                <div>
                  <div class='radio-div'>
                    <input type="radio" name="ansConfirm" id="yes" class='temp-disabl pointer' value="1" disabled />
                    <label for="yes" class='radio-text temp-disabl pointer'>Submit address designated by pushpin</label>
                  </div>
                  <div class='under-radio'>
                    <p class='marker-addr'></p>
                    <select id='confOptions' class='ans-option'>
                      <option>-- Please rate your confidence level --</option>
                      <option>Very sure</option>
                      <option>Quite sure</option>
                      <option>Neither sure nor unsure</option>
                      <option>Quite unsure</option>
                      <option>Very unsure</option>
                    </select>
                  </div>
                  <div class='radio-div' style='margin-top: 20px'>
                    <input type="radio" name="ansConfirm" id="r2" class='pointer' value="2" />
                    <label for="r2" class='radio-text pointer'>Submit no answer</label>
                  </div>
                  <div class='under-radio'>
                    <select id='reasonOptions' class='ans-option'>
                      <option>-- Please indicate your reason --</option>
                      <option>Cannot locate place on map</option>
                      <option>Do not wish to answer</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" disabled>Submit</button>
              </div>
            </div>
          </div>
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
      var goingBack = ( button.hasClass('back-btn' ) );
      if ( goingBack ) {
        $('#ansSubmitted').val(-1); 
      }
      else {
        if ( _isExplanation ) {
          if (_endOfQuestionnaire) {
            $('.logout-link')[0].click();
          }
        }
        /* This function gets triggered whether the button is of type submit or not, so we need to make sure that we are ready to submit by looking for absence of class confirm-btn */
        else if ( !button.hasClass('confirm-btn') ) { 
          $('#ansSubmitted').val(1);
          var answered = $('input[name=ansConfirm]:checked').prop('id') == 'yes';
          $('#ansAnswered').val(answered ? 1 : 0);
          $('#ansInfo').val(answered ? $('#confOptions')[0].selectedIndex : ($('#confOptions option').length-1) + $('#reasonOptions')[0].selectedIndex );
          $('#ansSearchActivity').val(_searchActivity.join(","));
          $('#ansCoords').val(answered ? _markerCoords.lat() + " " + _markerCoords.lng() : "0 0");
          $('#ansAddr').val(_markerAddr);
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
    
    function switchSearchMode()
    {
      $('.search-button').toggleClass('search-mode-places');
      $('.searchf').select();
      setSearchSliderAttrs();
    }

    function setSearchSliderAttrs()
    {
      var searchBtn = $('.search-button');
      _searchModePlaces = searchBtn.hasClass('search-mode-places');
      searchBtn.prop('title', "Toggle search mode (Currently: " + (_searchModePlaces ? "PLACES" : "LOCATION") + ")");
      $('.searchf').attr('placeholder', "For what " + (_searchModePlaces ? "place" : "location") + " are you searching?");
    }
    
    function showPopupMsg(d, f)
    {
      if (d == 1) {
        generate("success", f)
      }
      else if (d == 2) {
        generate("error", f)
      }
      else if (d == 3) {
        generate("alert", f)
      }
      else if (d == 4) {
        generate("information", f)
      }
      else if (d == 5) {
        generate("warning", f)
      } else if (d == 6) {
        generate("notification", f)
      }
    
      setTimeout(function () {
          $.noty.closeAll()
      }, 4000);
    }
    
    function generate(e, d)
    {
      var f = noty({
          text: d,
          type: e,
          dismissQueue: false,
          layout: "center",
          theme: "defaultTheme"
      });
      return f;
    }
    
    function setSubmitBtnEnabledStatus()
    {
      var radioBtns = $('input[name=ansConfirm]');
      var idxSelected = radioBtns.index($('input[name=ansConfirm]:checked'));
      var ansSelect = radioBtns.eq(idxSelected).parent().next().find('select');
      var idx = ansSelect[0].selectedIndex;
      $('.btn-primary').prop('disabled', idx == 0);
    }
    
  </script>
  <div id="draggable" class="ui-widget-content draggable places-control">
    <div class="ui-widget-header draggable-heading">
      <span>Search results</span>
      <button class='close-draggable close' onclick='removePlaceMarkers();' title='Closes panel and removes search result icons from map'>x</button>
    </div>
    <ul class='places-list'></ul>
  </div>
</body>
</html>