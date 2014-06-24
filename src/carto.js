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
    mapTypeControl: true,
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
  google.maps.event.addListener(_map, 'maptypeid_changed', logMapTypeChange );
  google.maps.event.addListener(_mapmarker, 'dragend', handleMarkerDrag);
  google.maps.event.addListener(_mapmarker, 'click', showMarkerInfoBubble);
}
    
function doGoogleSearch(searchStr)
{
  log("Searched for " + quote(searchStr,"'"));
  _searchActivity.push((_searchModePlaces ? "PL_SEARCH:" : "LOC_SEARCH:") + quote(searchStr));
  // Force search slider to close
  if ( _searchModePlaces ) {
    radialPlaceSearch(searchStr);
  }
  else {
    geocodeAddress(searchStr);
  }
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

function geocodeAddress(addr, centerOnMarker)
{
	var regionHint = "London, UK";
	addr += regionHint;
		
	var geocoderRespHandler = geocoderResponse;
	if (centerOnMarker) {
		geocoderRespHandler = geocoderResponseCenterMap;
	}
	
	_geocoder.geocode( { 'address': addr }, geocoderRespHandler );
}

function geocoderResponse(results, status)
{
  var ok = false;
	
  if (status == google.maps.GeocoderStatus.OK) {
    ok = true;
    var res_index = 0;
    _markerAddr = results[res_index].formatted_address;
    $('.marker-addr').text(_markerAddr);
		_markerCoords = results[res_index].geometry.location;
    log(results.length + " match" + (results.length == 1 ? "" : "es") + " found - choosing "  + quote(_markerAddr,"'"));
    placeMapMarker(_markerCoords);
    var contentHTML = "<div id='infoBubbleContent'><span>" +
                      escapeSpecialChars(_markerAddr) +
                    "</span></div>";
    _infoBubble.setContent(contentHTML);
  }
  else if (status == google.maps.GeocoderStatus.ZERO_RESULTS) {
    showPopupMsg(5, "Location not found.");
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
    $('.temp-disabl').removeClass('temp-disabl').prop('disabled', false);
  }
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

function radialPlaceSearch(searchString)
{
	//var morebtn = document.getElementById('morefinds');
	//morebtn.style.cssText = "color:#FFFFFF; background-color:#C71F2D; cursor: pointer";
	
	var kwds = searchString.split(' ');
	if(kwds.length > 0)
	{
		//clearAllSearchResults();
		//_addr_changed = false;
		_placesServ.nearbySearch({ location:_map.getCenter(), radius:_placesSearchRadius*1000, keyword:kwds }, radialSearchResponse);
	}
}

function radialSearchResponse(results, status, pagination) 
{
	if (status != google.maps.places.PlacesServiceStatus.OK) {
		/*showDialog( bilingualSubstitution("Aucun lieu correspondant aux mots-clés n'a été trouvé. Veuillez réessayer avec d'autres mots-clés ou avec une adresse. / No places matching your keywords were found. Please try again with different keywords or with an address." ));
		clearAddressField();*/
    alert("Places search prob");
		return;
	}
	
  /*
	document.getElementById('findspanel').style.visibility = "visible";
  */
	makefindmarkers(results);
	/*var morebtn = document.getElementById('morefinds');
	if (pagination.hasNextPage) 
	{
		google.maps.event.addDomListenerOnce(morebtn, 'click', function() { pagination.nextPage(); });
		morebtn.disabled = false;
	}
	else
	{
		morebtn.disabled = true;
		morebtn.style.cssText = "background-color:Lightgrey; color:Grey; cursor:default";
	}
	$("#places").animate({scrollTop: 100000}); // Big number so it always scrolls to the bottom
	$("#morefinds").focus();
	_map.setOptions({ draggableCursor: ''});
  */
  log(results);
}

function makefindmarkers(places) 			//search results
{
	_bnds = new google.maps.LatLngBounds();
	//var placesList = document.getElementById('places');

	for (var i = 0, place; place = places[i]; i++)
	{
		var image = {url:place.icon, size:new google.maps.Size(40, 40), origin:new google.maps.Point(0, 0), anchor:new google.maps.Point(10,20), scaledSize:new google.maps.Size(20, 20)};
		var mark = new google.maps.Marker({ map:_map, /*icon:image,*/ position:place.geometry.location, raiseOnDrag:false });
		/*mark.ID = _findmarkers.length;
		mark.name = place.name;
		google.maps.event.addListener(mark, 'mouseover', placeHoverListener);
		google.maps.event.addListener(mark, 'mouseout', function() { _infowin.close(); });
		google.maps.event.addListener(mark, 'click', placeClickListener );
		placesList.innerHTML += "<li id='lsm" + mark.ID + "' title=\"" + place.name + "\"><a style='color:#404040; width:186px;' href='javascript:selectfindmarker(" + mark.ID + ")'>" + (mark.ID+1) + ". " + place.name + "</a></li>";*/
		_bnds.extend(place.geometry.location);
		//_zoomSnapTo = true;
		addAddress(mark, place.reference);
		//_findmarkers.push(mark);
	}
	_map.fitBounds(_bnds);
}

function addAddress(mark, ref)
{	
	_placesServ.getDetails(
		{reference:ref},
		function(details, status) {
			if (status == google.maps.places.PlacesServiceStatus.OK) {
				log('Set ' + mark.name + details.name + details.formatted_address);
				mark.address = details.formatted_address;
				mark.vicinity = details.vicinity;
			}
			else if (status === google.maps.GeocoderStatus.OVER_QUERY_LIMIT) {
				setTimeout(function() {
                addAddress(mark, ref);
			}, 200);
        }
	});
}

function logMapTypeChange()
{
  _searchActivity.push( "VIEW_CHANGE:" + quote(_map.getMapTypeId()) );
}