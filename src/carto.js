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
  $('#map').css('border', '2px solid lightblue');
  var markerOptions = { 
    map: _map, 
    position: _latLngLondon, 
    draggable:true,
    animation: google.maps.Animation.DROP,
    title:"I'm your pushpin! Drag me to a new location, or click somewhere on the map to move me there."
  }
  _mapmarker = new google.maps.Marker(markerOptions);
  _mapmarker.setMap(_map);
  _mapmarker.setVisible(false);
		
  _geocoder = new google.maps.Geocoder();
  _placesServ = new google.maps.places.PlacesService(_map);
  
  var infoWinOptions =  {
    content: "<div id='content'><b>Address</b></div>"
  }
  _infoBubble = new google.maps.InfoWindow(infoWinOptions);
  
  /********* Map listeners *************/
  google.maps.event.addListener(_map, 'click', handleMapClick);
  google.maps.event.addListener(_mapmarker, 'dragend', handleMarkerDrag);
  google.maps.event.addListener(_mapmarker, 'click', showMarkerInfoBubble);
}
    
function doGoogleSearch(searchStr)
{
  log("Searched for \"" + searchStr + "\"");
  _searchQueries.push(searchStr);
  // Force search slider to close
  alert(searchStr);
}

function handleMapClick(e)
{
  var pix = e.pixel;
  var mapW = $('#map').width();
  var mapH = $('#map').height();
  var minEdgeDist = Math.min(pix.x,mapW-pix.x,pix.y,mapH-pix.y);
  var topEdgeDist = pix.y;
  var needToRecenter = ( minEdgeDist < 10 || topEdgeDist < 50 );
  if ( needToRecenter ) {
    log("Map canvas click at distance of only ", minEdgeDist, "; recentering map.");
  }
  geocodeMarker(e.latLng, needToRecenter);
}

function geocodeMarker(lat_lng, centerOnMarker)
{
  var geocoderRespHandler = geocoderResponse;
	if (centerOnMarker) {
		geocoderRespHandler = geocoderResponseCenterMap;
	}
	_geocoder.geocode( { latLng:lat_lng}, geocoderRespHandler );
}

function geocoderResponse(results, status)
{
  var ok = false;
	
  if (status == google.maps.GeocoderStatus.OK) {
    ok = true;
    var res_index = 0;
    _markerAddr = results[res_index].formatted_address;
		_markerCoords = results[res_index].geometry.location;
    
    placeMapMarker(_markerCoords);
    var contentHTML = "<div id='infoBubbleContent'><span>" +
                      escapeSpecialChars(_markerAddr) +
                    "</span></div>";
    _infoBubble.setContent(contentHTML);
  }
  
  return { 'ok' : ok, 'addr' : _markerAddr, 'coords' : _markerCoords };
}

function geocoderResponseCenterMap(results, status)
{
  var res = geocoderResponse(results, status);
  if ( res.ok ) {
    _map.panTo(_markerCoords);
  }
}

function placeMapMarker(coords)
{
  var firstTimeHere = !_mapmarker.visible;
  _mapmarker.setPosition(coords);
  _mapmarker.setVisible(true);
  if ( firstTimeHere ) {
    _mapmarker.setAnimation(null);
  }
  $('.submit-btn').prop('disabled', false);
  showMarkerInfoBubble();
}

function handleMarkerDrag(e)
{
  geocodeMarker(e.latLng, true);
  showMarkerInfoBubble();
}

function showMarkerInfoBubble()
{
  _infoBubble.open(_map, _mapmarker);
  window.clearTimeout(_timer);
  _timer = setTimeout( function() {_infoBubble.close()}, 5000);
}