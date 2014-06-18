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
</script>

<script type="text/javascript">
  $(document).ready( function() {
    var logoutHref = Consts.get('SRC_PHP_LOGIN') + '?' + Consts.get('STAT_KEY') + '=' + Consts.get('SESS_END_VAL');
    $('.logout-link').attr('href', logoutHref);
    
    $(window).resize( handleWindowResize );
    $(window).resize();
    
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
          <input placeholder='What do you wish to find on the map?' type='text'>
          <a class='button'>
            <i class='icon-search'></i>
          </a>
        </div>
      </div>
    </div>
    <div id='main'>
      <form method="post" action="">
        <div id='map' class='adjust-height'></div>
      </form>
    </div>
  </div>
  
  <script type="text/javascript">	
    function initMap()
    {
      _latLngLondon = new google.maps.LatLng(51.507224, -0.126103); // Montreal
      var mapOptions = {
              zoom: 15,
              center: _latLngLondon,
              zoomControl: true,
          panControl:false,
              scaleControl: true,
              streetViewControl: false,
          mapTypeControl: false,
          draggableCursor: 'crosshair',
              mapTypeId: google.maps.MapTypeId.ROADMAP		//HYBRID, SATELLITE, TERRAIN
        }	  
      _map = new google.maps.Map(document.getElementById('map'), mapOptions);	  
    }
    
    function handleWindowResize()
    {
      var screenHeight = $(window).height() - 10 /* Margin defined somewhere in CSS as 8px */;
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

  </script>
</body>
</html>