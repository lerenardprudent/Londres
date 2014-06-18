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

<!-- GOOGLE MAPS -->
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD4qnbkOe5X7pll7qyIFtkeLzjzkbPnAGo&amp;libraries=places,drawing,geometry,panoramio,weather&region=ca&sensor=false"></script>

<!-- BOOTSTRAP SCRIPTS -->
<script type="text/javascript" src="js/bootstrap/bootstrap.min.js"></script>
<script type="text/javascript" src="js/bootstrap/bootstrap-filestyle.min.js"> </script>
<link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">

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
    $('a').attr('href', logoutHref);
    
    initMap();
  });
</script>
</head>
<body>
  <div id="container">
    <p>
      <h3>Questionnaire</h3>
    </p>
    <form method="post" action="">
      <div class='questionblock'></div>
      <div id='map'></div>
    </form>
    <p />
    <a>Log out</a>
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
    
  </script>
</body>
</html>