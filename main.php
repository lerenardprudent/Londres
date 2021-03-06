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
$draw_mode = false;
$freq_quest = false;

if ($U->authorised()) {
  $curr_pos = $_SESSION[$curr_pos_key];
  $tokens = explode($sep, $curr_pos);
  $taskno = $tokens[0];
  $qno = $tokens[1];
  
  if (isset($_POST['ansSubmitted']) ) {
    $goBack = ( $_POST['ansSubmitted'] < 0 );
    if ( $_POST['ansSubmitted'] > 0 ) {
      $U->save_answer( $taskno, $qno, $_POST['ansAnswered'], $_POST['ansInfo'], $_POST['ansCoords'], $_POST['ansAddr'], $_POST['ansDestLabel'], $_POST['ansSearchActivity'] );
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
  
  $existing_ans = $U->question_answered($taskno, $qno);
  if ( $existing_ans ) {
    $ans_tokens = explode("|", $existing_ans);
    $db_ans_geom_txt = $ans_tokens[0];
    $db_ans_addr = $ans_tokens[1];
    $db_ans_destlabel = $ans_tokens[2];
    $db_ans_assoc_infos = $ans_tokens[3];
  }
  if ( $taskno == 3 && $qno == 1 ) {
    $home_ans = $U->question_answered(2, 1);
    if ( $home_ans ) {
      $home_ans_tokens = explode("|", $home_ans);
      $map_focus = $home_ans_tokens[0];
      $map_focus_icon = 'home';
    }
  }
  else if ( $taskno == 3 && $qno == 2 ) {
    $school_ans = $U->question_answered(1, 1);
    if ( $school_ans ) {
      $school_ans_tokens = explode("|", $school_ans);
      $map_focus = $school_ans_tokens[0];
      $map_focus_icon = 'school';
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
    if ( isset($info->draw) && $info->draw) {
      $draw_mode = true;
      array_push($questInfo, $C['DRAW_KEY']);
    }
    
    if ( isset($info->freq) && $info->freq) {
      $freq_quest = true;
      array_push($questInfo, $C['FREQ_QUEST_KEY']);
    }
    if ( isset($info->place)) {
      $freq_quest = true;
      array_push($questInfo, $C['PLACE_TYPE_KEY'], $info->place);
    }
  }
  else {
    echo "ERROR! Question not found :(";
  }
  
  $questInfo = implode($C['VALS_SEPARATOR'], $questInfo);
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
<title>VERITAS London</title>

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
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD4qnbkOe5X7pll7qyIFtkeLzjzkbPnAGo&amp;libraries=places,drawing&region=GB&sensor=false"></script>

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

<!-- IMPROMPTU SCRIPTS -->
<script type="text/javascript" src="js/impromptu/jquery-impromptu.js"></script>
<link rel="stylesheet" href="css/jquery-impromptu.css" />

<!-- BLOCKUI -->
<script type="text/javascript" src="js/blockUI/jquery.blockUI.js"></script>

<!-- ##################### END OF INCLUDES SECTION ######################### -->

<!-- The variables -->
<script type="text/javascript">
  var _latLngLondon;
  var _map;
  var _bnds, _placesBnds;
  var _mapmarker, _markers = [];
  var _maxNumMarkers = 3;
  var _geocoder;
	var _placesServ;
  var _infoBubble;
  var _timer, _demoTimer;
  var _searchActivity = [];
  var _endOfQuestionnaire = false;
  var _isExplanation = false;
  var _searchModePlaces = false;
  var _placesSearchRadius = 15; // km
  var _placeMarkers = [];
  var _focusMarker;
  var _zoomSnapTo = false;
  var _closeUpZoomLevel = 15;
  var _hackSelectors;
  var _placesIBOffset, _markerIBOffset;
  var _drawingPoly = false;
  var _drawingManager;
  var _drawnPolygon = null;
  var _drawnPolyJustAdded = false;
  var _freqQuestType = false;
  var _followUpBlockClassTag;
  var _overlay, db_;
  var _addMarkerBtnId = 'addMarkerBtn', _drawPolyBtnId = 'drawPolyBtn';
  var _zoomInBtnId = 'zoomInBtn';
  var _changeMapViewBtnId = 'satelliteBtn';
  var _greenMarker;
  var _noAnswerModalId = 'noAnsModal';
  var _modalState;
  var _noAnsFlag = 'no-ans';
  var _modalMarkerPfx = 'modalMarker';
  
  /* For use with tutorials */
  var _unknownStateId = 'UNKNOWN';
  var _currTourState = _unknownStateId;
  var _mapState = {};
  var _tutMarker = null;
  var _tutPoint = new google.maps.Point(100,100);
  var _tutMode = false;
  var _tutPoly = null;
  var _placeType = "location";
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
    _drawingPoly = ($('.questInfo').val().indexOf(Consts.get('DRAW_KEY')) >= 0);
    _freqQuestType = ($('.questInfo').val().indexOf(Consts.get('FREQ_QUEST_KEY')) >= 0);
    var gotPlaceType = ($('.questInfo').val().indexOf(Consts.get('PLACE_TYPE_KEY')) >= 0);
    if ( gotPlaceType ) {
      var vals = $('.questInfo').val().split(Consts.get('VALS_SEPARATOR'));
      var place = vals[vals.length-1];
      if ( place.length ) {
        _placeType = place[0].toUpperCase() + place.substr(1);
      }
    }
    
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
      blockUI(); /* Protection against early clicks of buttons */
      $('.discover-icon').show('puff', 400);
      log("Preparing map display");
      $(window).resize( handleWindowResize );
      $(window).resize();
      
      var searchField = $('#searchfield');
      var searchBtn = $('.search-button');
      searchField.val("");
      searchField.on('keypress', function(e) {
        if (e.which == 13 ) {
          runSearch();
        }
      });
      searchBtn.on('mouseover', function(e) {
        searchField.focus();
      });
      
      $('.searchfld').focusin( function() { $(this).toggleClass('searchfld-focus'); });
      $('.searchfld').focusout(function() { $(this).toggleClass('searchfld-focus'); });
      function searchContainerHoverToggle() { $(this).toggleClass('search-container-hover'); };
      $('.search-container').hover( searchContainerHoverToggle, searchContainerHoverToggle);
      $('.blocked').mouseover(function() {
        generateIt(5, "You cannot add any more destinations to the map. Please remove one of them before continuing.");
      });
      
      function logoutHoverToggle() { $(this).toggleClass('logout-icon-hover') };
      $('.logout-icon').hover(logoutHoverToggle, logoutHoverToggle);
      
      $('.answer-btn').attr('type','button').addClass('confirm-btn');
      $('.confirm-btn').click( function() { 
        if ( $(this).hasClass('confirm-btn') ) {
          //showMarkerAddrInModal();
          
          //var questClass = 'fub-model';
          //_followUpBlockClassTag = '.' + questClass;
          //var clone = $(_freqQuestType ? '#freqQuestModel' : '#confQuestModel').clone().prop('id', '').removeClass('example').addClass(questClass);
          //$('li').after(clone[0].outerHTML);
          //$('.follow-up-block').hide();
            
          $('.follow-up-pair').hide();

          //$('input[name="ansConfirm"]' ).prop('checked' , false);

          var canSubmit = false;
          var cantSubmitYetExpl = "";
          if ( _drawingPoly ) {
            canSubmit = (_drawnPolygon != null && _drawnPolygon.confirmed );
            if ( !canSubmit ) {
              _infoBubble.close();
              cantSubmitYetExpl = "Please confirm or remove the polygon.";
            }
          }
          else if ( _markers.length > 0 ) {
            canSubmit = allMarkersConfirmed();
            if ( !canSubmit ) {
              _infoBubble.close();
              cantSubmitYetExpl = "Please confirm or remove the pushpin.";
            }
          }    
          
          if ( canSubmit ) {
            submitAnswerToDB();
          }
          else if ( cantSubmitYetExpl ) {
            generateIt(5, cantSubmitYetExpl);
          }
          else {
            $('#' + _noAnswerModalId).modal(); 
          }
          
          /*var radioBtns = $('input[name=ansConfirm]');
          radioBtns.change(function() {
            var idxSelected = radioBtns.index($('input[name=ansConfirm]:checked'));
            if ( idxSelected == 0 ) {
              $(_followUpBlockClassTag).each(function() {
                $(this).show('blind', 300).find('.follow-up-pair').first().show('blind', 300); 
              });
              $('#noAnswerFUB').hide('blind', 300);
            }
            else {
              $(_followUpBlockClassTag).hide('blind', 300);
              $('#noAnswerFUB').show('blind', 300).find('.follow-up-pair').first().show('blind', 300); 
            }
            setSubmitBtnEnabledStatus();
          });
            */
        } });
      
      cloneModal(); // Creates modal for case where no answer is given
      
      initMap();
    }
  });
</script>
</head>
<body>
  <div id="container">
    <div id='topPanel' class='block-ui-candidate'>
      <div class='logo-container'>
        <div class='heading-logo' src='img/record-logo.png' onclick="window.open('http://www.lshtm.ac.uk/', '_blank');">
        </div>
        <span class='heading-text'>VERITAS</span>
      </div>
      
      <a title='Discover how to use this site' class='discover-icon' onclick='startTour();'>Log out</a>
      <a title='Log out' class='logout-icon logout-link'>Log out</a>
      <p class='mini-line-break' />
      <div class='quest-block'>
        <div class='quest-no'><span><?php if ( !$noHeading ) { if ($isExplanation) { echo "Task " . $taskno; } else { echo "Question " . str_replace($C['CURR_POS_SEPARATOR'], "&#8212;", $curr_pos); } } ?></span></div>
        <div class='quest-text'><span><?php echo $qtext; ?></span></div>
      </div>
      <p class='mini-line-break' />
      <div class='search-slider show-with-map'>
          <div class='search-container'>
            <input id='searchfield' class='searchfld' placeholder='What do you wish to find on the map?' type='text'>
            <a class='search-button'>
              <i class='icon-search'></i>
            </a>
          </div>
        </div>
      <form method="post" action="" class='form'>
        <input id='ansQID' name='ansQID' type='hidden' value='<?php echo $_SESSION[$curr_pos_key]; ?>'/>
        <input id='ansAnswered' name='ansAnswered' type='hidden' />
        <input id='ansInfo' name='ansInfo' type='hidden' value='<?php if (isset($db_ans_assoc_infos)) { echo $db_ans_assoc_infos; } ?>' />
        <input id='ansCoords' name='ansCoords' value='<?php if (isset($db_ans_geom_txt)) { echo $db_ans_geom_txt; } ?>' type='hidden' />
        <input id='ansAddr' name='ansAddr' value='<?php if (isset($db_ans_addr)) { echo $db_ans_addr; } ?>' type='hidden' />
        <input id='ansDestLabel' name='ansDestLabel' value='<?php if (isset($db_ans_destlabel)) { echo $db_ans_destlabel; } ?>' type='hidden' />
        <input id='ansSearchActivity' name='ansSearchActivity' type='hidden' />
        <input id='ansSubmitted' name='ansSubmitted' type='hidden' />
        <input class='questInfo' type='hidden' value='<?php echo $questInfo; ?>' />
        <input class='dbErr' type='hidden' value='<?php echo $db_err; ?>' />
        <input class='dbLog' type='hidden' value='<?php echo $db_log; ?>' />
        <input class='map-focus <?php if (isset($map_focus_icon)) { echo $map_focus_icon; } ?>' type='hidden' value='<?php if (isset($map_focus)) {echo $map_focus;}; ?>' />
        <div class='submit-div'>
          <input id='back' type='submit' value='&larr; Go back' class='back-btn submit-btn' />
          <input id='submit' type='submit' value='Next question &rarr;' class='answer-btn submit-btn'/>
        </div>
        <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModal" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel">Confirm location</h4>
              </div>
              <div class="modal-body"></div>
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
    <div id='confQuestModel' class='follow-up-block yes-fub example'>
      <div class='follow-up-pair active'>
                        <label>How <b>sure</b> are you that the location you have chosen/area you have drawn is correct?</label>
                        <select id='confOptions' class='nested-option'>
                          <option>-- Please rate your confidence level --</option>
                          <option class='ANS_CONF_VS'>Very sure</option>
                          <option class='ANS_CONF_QS'>Quite sure</option>
                          <option class='ANS_CONF_NSU'>Neither sure nor unsure</option>
                          <option class='ANS_CONF_QU'>Quite unsure</option>
                          <option class='ANS_CONF_VU'>Very unsure</option>
                        </select>
                      </div>
    </div>
    <div id='freqQuestModel' class='follow-up-block yes-fub example'>
                      <div class='follow-up-pair active'>
                        <label>How often do you visit this place?</label>
                        <select class='nested-option'>
                          <option value='0'>-- Please choose --</option>
                          <option class='ANS_FREQ_ED' value='1'>Every day</option>
                          <option class='ANS_FREQ_SPW' value='1'>Several times a week</option>
                          <option class='ANS_FREQ_OAW' value='1'>Once a week</option>
                          <option class='ANS_FREQ_OAF' value='1'>Once a fortnight</option>
                          <option class='ANS_FREQ_OAM' value='1'>Once a month</option>
                          <option class='ANS_FREQ_LO' value='1'>Less often</option>
                        </select>
                      </div>
                      <div class='follow-up-pair'>
                        <label>When you go to this place, are you <b>usually supervised</b> by a parent, another adult or an older brother/sister?</label>
                        <select class='nested-option'>
                          <option value='0'>-- Please choose --</option>
                          <option class='ANS_SPRVS_YES' value='2'>Yes</option>
                          <option class='ANS_SPRVS_NO' value='3'>No</option>
                        </select>
                      </div>
                      <div class='follow-up-pair'>
                        <label>Who <b>usually</b> supervises you?</label>
                        <select class='nested-option'>
                          <option value='0'>-- Please choose --</option>
                          <option class='ANS_SPRVR_POC'>Parent / Carer</option>
                          <option class='ANS_SPRVR_ARA'>Another responsable adult</option>
                          <option class='ANS_SPRVR_OBS'>An older brother/sister</option>
                        </select>
                      </div>
                      <div class='follow-up-pair'>
                        <label>With whom do you <b>usually</b> go?</label>
                        <select class='nested-option'>
                          <option value='0'>-- Please choose --</option>
                          <option class='ANS_CMPNY_FR'>Friends</option>
                          <option class='ANS_CMPNY_YBS'>Younger brother(s)/sister(s)</option>
                          <option class='ANS_CMPNY_NO'>On my own</option>
                        </select>
                      </div>
                    </div>
    <div id='noAnswerModel' class='follow-up-block no-fub example'>
                      <div class='follow-up-pair active'>
                        <label>Could you please indicate why you did you not answer the question?</label>
                        <select id='reasonOptions' class='nested-option'>
                          <option>-- Please indicate your reason --</option>
                          <?php if ($taskno == 3 && $qno == 2 && $U->question_answered(3,1)) { 
                                  echo "<option class='NOANS_SNHN' value='3'>The school and home neighbourhoods are identical</option>";
                                }
                                else if ($freq_quest == 4) {
                                  echo "<option class='NOANS_NRD' value='4'>I do not have regularly visited destination</option>" .
                                       "<option class='NOANS_DPA' value='5'>I do not perform this activity</option>";
                                }
                          ?>
                          <option class='NOANS_CLP' value="1">I could not locate the place on the map</option>
                          <option class='NOANS_DWA' value="2">I did not wish to answer</option>
                        </select>
                      </div>
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
    function escapeSpecialChars(str)
    {
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
      
      if ( isUndef(str) || str == null ) { str = ""; }
      return str.replace(/[&<>"'\/, ]/g, function(match) { return htmlEscapes[match]; });
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
          log("Processing form for submission...");
          $('#ansSubmitted').val(1);
          var answered = !button.hasClass(_noAnsFlag);
          $('#ansAnswered').val(answered ? 1 : 0);
          var ansInfoStr = buildAnsInfoStr(answered);
          $('#ansInfo').val(ansInfoStr);
          $('#ansSearchActivity').val(_searchActivity.join("~"));
          if (_drawnPolygon != null) {
            var path = _drawnPolygon.getPath().getArray();
            path.push(path[0]); // Repeat first point so that path is closed loop and polygon is valid
            var coords = []
            for ( var x = 0; x < path.length; x++ ) {
              var coord = path[x].lat() + " " + path[x].lng();
              coords.push(coord);
              var coordsStr = coords.join(",");
            }
            $('#ansCoords').val(coordsStr);
          }
          else if ( answered ) {
            var allCoords = [];
            var allAddrs = [];
            var allDescLabels = [];
            for ( var x = 0; x < _markers.length; x++ ) {
              allCoords.push(_markers[x].getPosition().lat() + " " + _markers[x].getPosition().lng());
              allAddrs.push(_markers[x].address);
              allDescLabels.push(_markers[x].label);
            }
            $('#ansCoords').val(allCoords.join(","));
            $('#ansAddr').val(allAddrs.join("¦"));
            $('#ansDestLabel').val(allDescLabels.join("¦"));
          }
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
    
    function switchSearchMode(searchModePlaces)
    {
      if ( !isUndef(searchModePlaces.type) ) { /* For the case where the click event is passed */
        $('.search-button').toggleClass('search-mode-places');
      }
      else {
        searchModePlaces ? $('.search-button').addClass('search-mode-places') : $('.search-button').removeClass('search-mode-places');
      }
      setSearchSliderAttrs();
      $('.searchfld').focus().select();
      return false;
    }

    function setSearchSliderAttrs()
    {
      var searchBtn = $('.search-button');
      _searchModePlaces = searchBtn.hasClass('search-mode-places');
      searchBtn.prop('title', "Click to toggle search mode (Currently: " + (_searchModePlaces ? "PLACES" : "LOCATION") + ")");
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
    
    function generateIt(d, f, e) {
    var minDelay = 5 /*secs*/ * 1000;
    if ( typeof e === "undefined" ) {
      e = minDelay;
    }
    else if ( e < 0 ) {
      e *= -1; 
    }
    else {
      e = Math.max(minDelay, e);
    }
      if (d == 1) {
          generate("success", f)
      } else {
          if (d == 2) {
              generate("error", f)
          } else {
              if (d == 3) {
                  generate("alert", f)
              } else {
                  if (d == 4) {
                      generate("information", f)
                  } else {
                      if (d == 5) {
                          generate("warning", f)
                      } else {
                          if (d == 6) {
                              generate("notification", f)
                          }
                      }
                  }
              }
          }
      }
      setTimeout(function () {
          $.noty.closeAll()
      }, e)
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
    
    function setSubmitBtnEnabledStatus(thisModal)
    {
      //var radioBtns = $('input[name=ansConfirm]');
      //var idxRadioBtnChecked = radioBtns.index($('input[name=ansConfirm]:checked'));
      //var underRadioDiv = $('input[name=ansConfirm]:checked').parent('.radio-div').next();
      var fub = thisModal.find('.follow-up-block');
      var numVisibleValidSelectsExpected = ( _freqQuestType && !_tutMode && fub.hasClass('yes-fub') ? 3 : 1 );
      var allFUBQuestionsAnswered = ( getVisibleValidSelectCount(fub) == numVisibleValidSelectsExpected );
      thisModal.find('.btn-primary').prop('disabled', !allFUBQuestionsAnswered);
    }
    
    function getVisibleValidSelectCount(fubDiv)
    {
      var count = 0;
      fubDiv.find('select:visible').each( function() {
        count += ( $(this)[0].selectedIndex > 0 );
      });
      return count;
    }
    
    function buildAnsInfoStr(answered)
    {
      if ( answered ) {
        var info = [];
        for ( var v = 0; v < _markers.length; v++ ) {
          var modalId = _modalMarkerPfx + v;
          var t = getModalText(modalId);
          info.push(t);
        }
        return info.join("¦");
      }
      
      return getModalText(_noAnswerModalId); //return $('#' + _noAnswerModalId + ' option:selected')[0].className;
    }
    
    function getModalText(modalId)
    {
      var xinfo = [];
      $('#' + modalId + ' .active option:selected').each(function() {
         
        /* For newly created markers, we would not need to verify the selectedIndex because the user would not have been able to submit an answer if the selectedIndex for every select was not > 0. However, if the user returns to a previous question, leaves the markers unchanged then clicks submit, the modals will contain selects where the selectedIndex is 0.
        */
        if ( $(this)[0].index > 0 ) {
          var ansText = $(this).parent().prev().text() + "[" + $(this).text() + "]";
          xinfo.push(ansText);
        }
      });
      return xinfo.join(" + ");
    }
    
    function setSubmitAnswerOptionEnabled(enabled)
    {
      if (isUndef(enabled)) {
        enabled = true;
      }
      
      if ( enabled ) {
        $('.radio-yes').removeClass('temp-disabl').prop('disabled', false);
      }
      else {
        $('.radio-yes').addClass('temp-disabl').prop('disabled', true);
        $('#confOptions').hide();
      }
    }
    
    function showMarkerAddrInModal()
    {
      if ( _markers.length > 1 ) {
        $('.radio-yes').next().text("Submit addresses designated by pushpins");
      }
      $('.marker-addr').html("");
      for ( var v = 0; v < _markers.length; v++ ) {
        $('.marker-addr').append("<li>" + _markers[v].address + "</li>");
      }      
    }
    
    function cloneModal(idx)
    {
      var isNoAnswerModal = isUndef(idx);
      var newModalId = (isNoAnswerModal ? _noAnswerModalId : _modalMarkerPfx + idx );
      var clonedModal = $('#confirmModal').clone().prop('id', newModalId);
      var cloneDiv = $('#' + newModalId);
      if ( cloneDiv.length == 0 ) {
        $('#confirmModal').after(clonedModal[0].outerHTML);
        cloneDiv = $('#' + newModalId);
      }
      else {
        cloneDiv.html(clonedModal[0].innerHTML);
      }
      
      var noAnsModel = 'noAnswerModel', freqQuestModel = 'freqQuestModel';
      fubModelId = (isUndef(idx) ? noAnsModel : ( _freqQuestType && !_tutMode ? freqQuestModel : 'confQuestModel' ) );
      cloneDiv.find('.modal-body').append($('#' + fubModelId).clone().prop('id', '').removeClass('example')[0].outerHTML);
      
      if ( fubModelId == freqQuestModel ) {
        cloneDiv.find('.modal-footer').prepend("<div><span class='dest-desc'><label>Add a descriptive label for this place (optional):</label><input type='text' class='desc-input' /></span></div>");
        cloneDiv.find('.desc-input').focusout(function() { setMarkerLabel(idx, $(this).val()); });
      }
      cloneDiv.find('.btn-default').remove();
      cloneDiv.find('.close').remove();
      if ( isNoAnswerModal ) {
        cloneDiv.find('.modal-title').text("Confirm non-answer");
      }
      else {  
        cloneDiv.find('.btn-primary').text("Confirm");
      }
      
      cloneDiv.find('.nested-option').change( function() {
        var scope = $(this).closest('.modal');
        var idxThisSelect = scope.find('.nested-option').index($(this));
        var idxNextSelect = parseInt($('option:checked', this).val());
        if ( idxNextSelect != "0" ) {
          scope.find('.follow-up-pair:gt(' + idxThisSelect + ')').removeClass('active').hide();
          scope.find('.nested-option').eq(idxNextSelect).val(0);
          scope.find('.follow-up-pair').eq(idxNextSelect).addClass('active').show('blind', 200);
        }
        setSubmitBtnEnabledStatus(scope);
      });
      
      cloneDiv.find('.btn-primary').click( function() {
        cloneDiv.modal('hide');
        if ( isNoAnswerModal ) {
          $('.answer-btn').addClass(_noAnsFlag).toggleClass('confirm-btn').attr('type', 'submit').click(); 
        }
        else {
          confirmMarker(idx);
        }
      });
      
      cloneDiv.find('.modal-dialog').width(700); /* Make the modal a little wider */
      return newModalId;
    }
    
    function saveModalState(modalId)
    {
      _modalState = [];
      $(modalId + ' select').each( function() {
        _modalState.push({'sel_idx' : $(this)[0].selectedIndex, 'vis' : $(this).is(':visible')});
      });
    }
    
    function loadPreviousModalState(modalId)
    {
      var idx = 0;
      $('#' + modalId + ' select').each( function() {
        ( _modalState[idx].vis ? $(this).show() : $(this).hide() );
        $(this)[0].selectedIndex = _modalState[idx].sel_idx;
      });
    }
    
    function allMarkersConfirmed()
    {
      var allMarkersConfirmed = true;      
      for ( var i = 0; i < _markers.length; i++ ) {
        allMarkersConfirmed &= _markers[i].confirmed;
      }
      return allMarkersConfirmed;
    }
    
    function submitAnswerToDB()
    {
      $('.confirm-btn').toggleClass('confirm-btn').attr('type', 'submit').click(); 
    }
    
    function blockUI(unblockFlag)
    {
      $('.block-ui-candidate').each(function() {
        ( isUndef(unblockFlag) ? $(this).block({message:null}) : $(this).unblock() );
      });
    }
    
    function startTour()
    {
      boxWidth = 400;
      var boxHeight = 138;
    
      states = [
        {name:"START"},
        {name:"QUESTION"},
        {name:"MAP"},
        {name:"IDENTIFY_BY_BTN"},
        {name:"IDENTIFY_BY_CLICK"},
        {name:"LOC_SEARCH", blockUI:true, hideMarker:true},
        {name:"TOGGLE_SEARCH"},
        {name:"PLACE_SEARCH", blockUI:true, hideMarker:true},
        {name:"PLACE_SELECT", blockUI:true},
        {name:"PLACES_REMOVE"},
        {name:"INITIATE_CONFIRM"},
        {name:"CONFIRM"},
        {name:"REMOVE"},
        {name:"DRAW_POLY", blockUI:true},
        {name:"SUBMIT"},
        {name:"GO_BACK"},
        {name:"ZOOM"},
        {name:"CHANGE_MAP_VIEW"},
        {name:"LOGOUT"},
        
        {name:"END",hideMarker:true}
      ];
      
      var tourSubmitFunc = function(e,v,m,f){
        if (v === -1){
          $.prompt.prevState();
          return false;
        }
        else if (v === 1){
          $.prompt.nextState();
          return false;
        }
      },
      tourStates = [
        {
          title: 'Welcome',
          html: 'This quick tour will show you how to use the features of the VERITAS site.',
          buttons: { Begin: 1 },
          focus: 0,
          position: { container: '.discover-icon', x: -(boxWidth/2) + 4, y: $('.discover-icon').outerHeight()+20, width: boxWidth },
          submit: tourSubmitFunc
        },
        {
          title: 'The question',
          html: 'A question is presented to you in this panel. Please read it carefully.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.quest-block', x: 0, y: $('.quest-block').outerHeight() + 10, arrow: 'tl', width: boxWidth },
          submit: tourSubmitFunc
        },
        {
          title: "The map",
          html: 'To answer the question, you are required to identify a location (or locations) or region on the map.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.quest-block', x: 0, y: $('.quest-block').outerHeight() + 10, arrow: 'br', width: boxWidth },
          submit: tourSubmitFunc
        },
        {
          title: 'Identifying a location',
          html: 'You may place a pushpin on the map by clicking the \'Add pushpin\' button...',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: "[title^='Add'],[title^='Trace']", x: -(boxWidth+($("[title^='Add'],[title^='Trace']").outerWidth()/2)), y: -3, width: boxWidth, arrow: 'rt' },
          submit: tourSubmitFunc
        },
        {
          title: 'Identifying a location (continued)',
          html: '...or by clicking directly on the map at the particular location that you wish to identify.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '#map', x: _tutPoint.x+20, y: _tutPoint.y-boxHeight+15, width: boxWidth, arrow: 'lb' },
          submit: tourSubmitFunc
        },
        {
          title: 'Searching',
          html: 'To perform a search, move your mouse over the magnifying glass, type your search terms into the text box that appears, and press \'Enter\'. In <i>Location</i> search mode, the location best matching your search terms will be shown with the pushpin.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.search-slider', x: -boxWidth+($('.search-slider').outerWidth()/2)+20, y: $('.search-slider').outerHeight()+15, width: boxWidth, arrow: 'tr' },
          submit: tourSubmitFunc
        },
        {
          title: 'Two search modes',
          html: 'Toggle between <i>Location</i> and <i>Place</i> search modes by clicking on the magnifying glass.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.search-slider', x: -boxWidth+($('.search-slider').outerWidth()/2)+20, y: $('.search-slider').outerHeight()+15, width: boxWidth, arrow: 'tr' },
          submit: tourSubmitFunc
        },
        {
          title: 'Searching for a place',
          html: 'To search for a place, type its name and/or terms that describe it, then press \'Enter\'. A list of names of potential matches will appear, alomg with icons on the map showing those places\' location.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.search-slider', x: -boxWidth+($('.search-slider').outerWidth()/2)+20, y: $('.search-slider').outerHeight()+15, width: boxWidth, arrow: 'tr' },
          submit: tourSubmitFunc
        },
        {
          title: 'Selecting a place',
          html: 'Click on an item in the list to zoom to that place on the map. Click on the icon directly to select the place.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.draggable', x: $('.draggable').outerWidth()*.55, y: 10, width: boxWidth*.6, arrow: 'lt' },
          submit: tourSubmitFunc
        },
        {
          title: 'Removing Place search results',
          html: 'If you wish to remove the search results overlay, click the \'x\' in the top-right corner of the list.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.draggable', x: 160, y: -8, width: boxWidth*.6, arrow: 'lt' },
          submit: tourSubmitFunc
        },
        {
          title: 'Initiating confirmation',
          html: 'We would like the position of the pushpin on the map to accurately indicate your response to the question being asked. Once you are satisfied with the pushpin\'s position, we ask you to confirm your selection. Initiate confirmation by clicking on the pushpin and then on the \'Confirm\' link.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '#map', x: ($('#map').width()/2)-(boxWidth*1.2/2)+2, y: ($('#map').height()/2)+15, width: boxWidth*1.2, arrow: 'tc' },
          submit: tourSubmitFunc
        },
        {
          title: 'Confirming a location',
          html: 'Upon clicking the \'Confirm\' link, a panel (not shown) will appear that contains one (or sometimes more) multiple-choice questions about the location you have selected. Once you have answered these questions, you may click the \'Confirm\' button to complete the confirmation process. The pushpin will change colour to indicate that the location has been confirmed.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '#map', x: ($('#map').width()/2)-(boxWidth*1.2/2)+2, y: ($('#map').height()/2)+15, width: boxWidth*1.2, arrow: 'tc' },
          submit: tourSubmitFunc
        },
        {
          title: 'Removing a pushpin',
          html: 'At any time, a pushpin may be removed from the map by clicking on it and then on the \'Remove\' link.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '#map', x: ($('#map').width()/2)+25, y: ($('#map').height()/2)-25, width: boxWidth, arrow: 'lt' },
          submit: tourSubmitFunc
        },
        {
          title: 'Drawing a region',
          html: 'For some questions, you will be required to designate a region rather than simply indicate a location. To do so, click the \'Trace a region\' button, then begin clicking on the map to draw. Click once to add a point, repeating as many times as necessary, then double-click when you wish to add the final point. The region traced by the points will become visible.<p><p>You may repeat these steps to erase a previously drawn region and replace it with one that you have just drawn.<p><p>There is no confirmation process required for questions that ask you to designate a region on the map.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: "[title^='Add'],[title^='Trace']", x: $("[title^='Add'],[title^='Trace']").outerWidth()+10, y: -5, width: boxWidth, arrow: 'lt' },
          submit: tourSubmitFunc
        },
        {
          title: 'Transitioning to the next question',
          html: "To advance to the next question, click this button. If you have a red (i.e. unconfirmed) pushpin on the map, you will be asked to either confirm its location or remove it. Alternatively, if you have chosen not to answer the question, you will be asked a few questions on why this is so.",
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.answer-btn', x: -boxWidth-12, y: -3, width: boxWidth, arrow: 'rt' },
          submit: tourSubmitFunc
        },
        {
          title: 'Returning to the previous question',
          html: "You can go back to the previous question by clicking this button. Please note that any changes made to your answer to the question currently being shown will not be saved if you click the \'Go back\' button.",
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.back-btn', x: $('.back-btn').outerWidth()+12, y: -3, width: boxWidth, arrow: 'lt' },
          submit: tourSubmitFunc
        },
        {
          title: 'Zooming in/out',
          html: "To change the zoom level of the map, use the '+' and '-' buttons.",
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '#' + _zoomInBtnId, x: $('#' + _zoomInBtnId).outerWidth()+10, y: -2, width: boxWidth*.6, arrow: 'lt' },
          submit: tourSubmitFunc
        },
        {
          title: 'Changing the map view',
          html: 'Use these buttons to change the map view. Possibile views are Road, Satellite, Terrain, and Hydrid.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '#' + _changeMapViewBtnId, x: -boxWidth+20, y: $('#' + _changeMapViewBtnId).outerHeight()+10, width: boxWidth, arrow: 'tr' },
          submit: tourSubmitFunc
        },
        {
          title: 'Logging out',
          html: 'To log out of VERITAS, click on the exit door.',
          buttons: { Next: 1 },
          focus: 0,
          position: { container: '.logout-icon', x: -boxWidth-12, y: -2, width: boxWidth, arrow: 'rt' },
          submit: tourSubmitFunc
        },
        {
          title: 'End of tour',
          html: 'Thank you for taking the time to learn more about VERITAS. Enjoy!',
          buttons: { Done: 2 },
          focus: 0,
          position: { container: '.discover-icon', x: -(boxWidth/2) + 4, y: $('.discover-icon').outerHeight()+20, width: boxWidth },
          submit: tourSubmitFunc
        }
      ];
    
      _mapState = { 'add_marker_btn' : $('#' + _addMarkerBtnId).is(':visible'), 'search_mode_places' : _searchModePlaces, 'poly' : _drawnPolygon != null };
      var myPrompt = $.prompt(tourStates);
      /* Make some visual changes to impromptu dialog box */
      myPrompt.on('impromptu:loaded', function(e) {
        $('.jqi').width(boxWidth);
        $('.jqi').width(boxWidth);
        $('.jqi .jqiclose').css({'font-size':'25px','top': '-5px', 'right': '1px','cursor':'pointer'}).prop('title','Exit the tour');
        $('.jqimessage').css({'margin-top':'-10px','padding-top':'0px'});
        $('.jqititle').css('font-weight', 'bold');
        $(".jqibuttons button").css('padding', '10px');
        $('.jqi .jqidefaultbutton').focus();
        _drawingManager.set('drawingControlOptions', {position: google.maps.ControlPosition.TOP_CENTER, drawingModes: [google.maps.drawing.OverlayType.MARKER]});
        if ( _drawnPolygon != null ) {
          _drawnPolygon.setVisible(false);
        }
      });

      myPrompt.on('impromptu:statechanging', function(e) {
        if ( $('.jqi').length == 1 ) {
          boxHeight = $('.jqi').outerHeight();
        }
        window.clearTimeout(_timer);
        window.clearTimeout(_demoTimer);
        _infoBubble.close();
        $('.search-container').removeClass('search-container-hover');
        $('.searchfld').val("");
        _map.setMapTypeId(google.maps.MapTypeId.ROADMAP);
        
        /* Check if we should block UI for next state */
        var nextState = states[parseInt($.prompt.getCurrentStateName())+1];
        if ( !isUndef(nextState.blockUI) ) {
          toggleBtnNextEnabled();
        }
        if ( !isUndef(nextState.blockUI) ) {
          _tutMarker.setVisible(false);
        }
        
        if ( _tutPoly != null ) {
          _tutPoly.setMap(null);
          _tutPoly = null;
        }
      });
      
      $('.logout-icon').removeClass('logout-icon-hover');
      
      myPrompt.on('impromptu:statechanged', function(e) {
        var currStateIdx = parseInt($.prompt.getCurrentStateName());
        _currTourState = ( currStateIdx < states.length ? states[currStateIdx] : _unknownStateId );
        if ( _currTourState.name == "START") {
          _tutMode = true;
          if ( !_mapState.map_button && !_drawingPoly ) {
            $('#' + _addMarkerBtnId).show();
          }
          $.noty.closeAll();
          _infoBubble.close();
          showMarkersOnMap(false);
          switchSearchMode(false);
          _tutMarker = new google.maps.Marker({position: _map.getCenter(), visible: false, map: _map});
          google.maps.event.addListener(_tutMarker, 'click', function() { showInfoBubble(this, false); } );
        }
        else if ( _currTourState.name == 'IDENTIFY_BY_BTN' ) {
          _timer = setTimeout( function() {
          _tutMarker.setOptions({visible: true, animation: google.maps.Animation.DROP });
          }, 1500 );
        }
        else if ( _currTourState.name == 'IDENTIFY_BY_CLICK' ) {
          _timer = setTimeout( function() {
            var latLng = _overlayProjection.fromContainerPixelToLatLng(_tutPoint);
            _tutMarker.setOptions({position: latLng, visible: true});
          }, 2000 );
        }
        else if ( _currTourState.name == 'LOC_SEARCH' ) {
          _timer = setTimeout( function() {
            demoSearch("Pall Mall", toggleBtnNextEnabled );
          }, 2000);
        }
        else if ( _currTourState.name == 'TOGGLE_SEARCH' ) {
          _timer = setTimeout( function() {
            switchSearchMode(true);
          }, 2000);
        }
        else if ( _currTourState.name == 'PLACE_SEARCH' ) {
          _timer = setTimeout( function() {
            switchSearchMode(true);
            demoSearch("Koba", toggleBtnNextEnabled );
          }, 2000);
        }
        else if ( _currTourState.name == 'PLACE_SELECT' ) {
          _timer = setTimeout( function() {
            $('.places-list li').eq(0).find('a')[0].click();
            _demoTimer = setTimeout(function() { 
              google.maps.event.trigger(_placeMarkers[0], 'click');
              toggleBtnNextEnabled();
            }, 3000);
          }, 2000);
        }
        else if ( _currTourState.name == 'PLACES_REMOVE' ) {
          _timer = setTimeout(function() {
            removePlaceMarkers();
          }, 4000 );
        }
        else if ( _currTourState.name == 'INITIATE_CONFIRM' ) {
          _timer = setTimeout(function() {
            google.maps.event.trigger(_tutMarker, 'click');
          }, 3500 );
        }
        else if ( _currTourState.name == 'CONFIRM' ) {
          _timer = setTimeout(function() {
            _tutMarker.setIcon(_greenMarker);
          }, 5000 );
        }
        else if ( _currTourState.name == 'REMOVE' ) {
          _timer = setTimeout(function() {
            google.maps.event.trigger(_tutMarker, 'click');
            _demoTimer = setTimeout( function() {
              _tutMarker.setVisible(false);
              _infoBubble.close();
            }, 2000);
          }, 2500 );
        }
        else if ( _currTourState.name == 'DRAW_POLY' ) {
          _timer = setTimeout(function() {
            _drawingManager.set('drawingControlOptions', {position: google.maps.ControlPosition.TOP_CENTER, drawingModes: ['polygon']});
            _demoTimer = setTimeout(function() {
              _drawingManager.setDrawingMode(google.maps.drawing.OverlayType.POLYGON);
              setTimeout(function() {
                var tutPolyCoords = [
                  _overlayProjection.fromContainerPixelToLatLng(new google.maps.Point(100,100)),
                  _overlayProjection.fromContainerPixelToLatLng(new google.maps.Point(50,200)),
                  _overlayProjection.fromContainerPixelToLatLng(new google.maps.Point(220,250)),
                  _overlayProjection.fromContainerPixelToLatLng(new google.maps.Point(350,150)),
                  _overlayProjection.fromContainerPixelToLatLng(new google.maps.Point(200,80))
                ];
                _tutPoly = new google.maps.Polygon({paths: tutPolyCoords});
                _tutPoly.setMap(_map);
                _drawingManager.setDrawingMode(null);
                toggleBtnNextEnabled();
              }, 5000);
            }, 2000);
          }, 500 );
        }
        else if ( _currTourState.name == 'ZOOM' ) {
          _timer = setTimeout(function() {
            var z = _map.getZoom();
            _map.setZoom(z+1);
            _demoTimer = setTimeout(function() {_map.setZoom(z);}, 1500);
          }, 2000 );
        }
        else if ( _currTourState.name == 'CHANGE_MAP_VIEW' ) {
          _timer = setTimeout(function() {
            _map.setMapTypeId(google.maps.MapTypeId.SATELLITE);
            _demoTimer = setTimeout(function() {_map.setMapTypeId(google.maps.MapTypeId.ROADMAP);}, 2500);
          }, 2000 );
        }
        else if ( _currTourState.name == 'LOGOUT' ) {
          _timer = setTimeout(function() {
            var logoutIcon = $('.logout-icon');
            logoutIcon.toggleClass('logout-icon-hover');
            _demoTimer = setTimeout(function() { logoutIcon.toggleClass('logout-icon-hover'); }, 1000);
          }, 1500 );
        }
      });
      
      myPrompt.on('impromptu:close', reloadMapState);
    }
    
    function demoSearch(query, onDone)
    {      
      $('.search-container').toggleClass('search-container-hover');
      fld = $('.searchfld');
      fld.val("").focus();
      var idx = 0;
      function typeNextLetter() {
        _demoTimer = setTimeout( function() {
          if ( idx <= query.length ) {
            fld.val( fld.val() + ( idx < query.length ? query[idx] : "" ) );
            ++idx;
            typeNextLetter();
          }
          else { /* Run this when typing demo is finished */
            $('.search-container').toggleClass('search-container-hover');
            runSearch();
            if ( typeof(onDone) == 'function' ) {
              _demoTimer = setTimeout( onDone, 3000 );
            }
            $('.jqidefaultbutton').focus();
          }
        }, 225);
      }
      typeNextLetter();
      
      return true;
    }
    
    function toggleBtnNextEnabled()
    {
      var disablClassName = 'btn-disabled';
      var btn = $('.jqidefaultbutton');
      var btnPanel = $('.jqibuttons');
      btn.toggleClass(disablClassName);
      btn.hasClass(disablClassName) ? btnPanel.block({message:null}) : btnPanel.unblock();
    }
    
  </script>
  <div id="draggable" class="ui-widget-content draggable places-control">
    <div class="ui-widget-header draggable-heading">
      <span>Search results (<a href='javascript:showAll();'>Show all</a>)</span>
      <button class='close-draggable close' onclick='removePlaceMarkers();' title='Closes panel and removes search result icons from map'>x</button>
    </div>
    <ul class='places-list'></ul>
    <a id='moreResults' class='load-more-res-link'>Load more results...</a>
  </div>
</body>
</html>