function initMap()
{
  _latLngLondon = new google.maps.LatLng(51.507224, -0.126103); // London
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

  _placesIBOffset = new google.maps.Size(-10, 0);
  
  /********* Map listeners *************/
  google.maps.event.addListener(_map, 'maptypeid_changed', logMapTypeChange );
  google.maps.event.addListener(_map, 'bounds_changed', function(event) {
		var zoomLevel = _map.getZoom();
		if ( _zoomSnapTo && zoomLevel > _closeUpZoomLevel ) {
			_zoomSnapTo = false;
			_map.setZoom(_closeUpZoomLevel);
		}
	});
  
  if ( _drawing ) {
    _drawingManager = new google.maps.drawing.DrawingManager({
      drawingControl: true,
      drawingControlOptions: {
        position: google.maps.ControlPosition.TOP_CENTER,
        drawingModes: [google.maps.drawing.OverlayType.POLYGON]
      }
    });
    google.maps.event.addListener(_drawingManager, 'overlaycomplete', processDrawnPolygon);
    $('#map').click(deletePolyAlreadyOnMap);
    _drawingManager.setMap(_map);
    _map.setOptions({draggableCursor:null});
  }
  else {
    google.maps.event.addListener(_map, 'click', handleMapClick);
    google.maps.event.addListener(_mapmarker, 'dragend', handleMarkerDrag);
    google.maps.event.addListener(_mapmarker, 'click', showMarkerInfoBubble);
  }
  
  var dbCoords = $('#ansCoords').val();
  if ( dbCoords.length > 0 ) {
    setMarkerAddr($('#ansAddr').val());
    /* Let's put pin at coords that we pulled from DB */
    var tkns = dbCoords.split(" ");
    pinMapMarker(new google.maps.LatLng(tkns[0], tkns[1]));
  }
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
    setMarkerAddr(results[res_index].formatted_address);
		_markerCoords = results[res_index].geometry.location;
    log(results.length + " match" + (results.length == 1 ? "" : "es") + " found - choosing "  + quote(_markerAddr,"'"));
    pinMapMarker(_markerCoords);
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

function pinMapMarker(coords)
{
  var firstTimeHere = !_mapmarker.visible;
  _mapmarker.setPosition(coords);
  _mapmarker.setVisible(true);
  if ( firstTimeHere ) {
    _mapmarker.setAnimation(null);
    $('.temp-disabl').removeClass('temp-disabl').prop('disabled', false);
  }
  var contentHTML = "<div id='infoBubbleContent'><span>" +
                      escapeSpecialChars(_markerAddr) +
                    "</span></div>";
  _infoBubble.setContent(contentHTML);
  showMarkerInfoBubble();
}

function handleMarkerDrag(e)
{
  geocodeMarker(e.latLng, true);
  showMarkerInfoBubble();
}

function showMarkerInfoBubble()
{
  _infoBubble.set('pixelOffset', null );
  _infoBubble.open(_map, _mapmarker);
  window.clearTimeout(_timer);
  _timer = setTimeout( function() {_infoBubble.close()}, 5000);
}

function radialPlaceSearch(searchString)
{
	//var morebtn = document.getElementById('morefinds');
	//morebtn.style.cssText = "color:#FFFFFF; background-color:#C71F2D; cursor: pointer";
	
	var kwds = searchString.split(' ');
	if (kwds.length > 0)
	{
		removePlaceMarkers();
		_placesServ.nearbySearch({ location:_map.getCenter(), radius:_placesSearchRadius*1000, keyword:kwds }, radialSearchResponse);
	}
}

function radialSearchResponse(results, status, pagination) 
{
	if (status != google.maps.places.PlacesServiceStatus.OK) {
		alert("Places search prob");
		return;
	}
	
  /*
	document.getElementById('findspanel').style.visibility = "visible";
  */
	pinPlaceMarkers(results);
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
}

function removePlaceMarkers()
{
	for (var i = 0; i < _placeMarkers.length; i++)
	{ 
		removePlaceMarker(i);
	}
	_placeMarkers = [];
	banishPlacesControl();
	_map.setOptions({ draggableCursor: 'crosshair'});
}

function removePlaceMarker(id) 
{
	_infoBubble.close();
	if (_placeMarkers[id] == null) { return; }
	var l = document.getElementById('lsm'+id);
	try { l.parentNode.removeChild(l); } catch(er){}
	try { clearListeners(_placeMarkers[id], 'click'); } catch(er){}
	try { _placeMarkers[id].setMap(null);} catch(er){ _placeMarkers[id] = null; }
	try {_placeMarkers[id] = null; } catch(er){}
}

function banishPlacesControl()
{
  $('.places-list').html("");
  $('.places-control').hide();
}

function pinPlaceMarkers(places) 			//search results
{
	_bnds = new google.maps.LatLngBounds();
	var placesList = $('.places-list');

	for (var i = 0, place; place = places[i]; i++)
	{
		var image = {
      url:place.icon, 
      size:new google.maps.Size(40, 40), 
      origin:new google.maps.Point(0, 0), 
      anchor:new google.maps.Point(10,20), 
      scaledSize:new google.maps.Size(20, 20)
    };
		var marker = new google.maps.Marker({ map:_map, icon:image, position:place.geometry.location, raiseOnDrag:false });
		marker.ID = _placeMarkers.length;
		marker.name = place.name;
		google.maps.event.addListener(marker, 'mouseover', showPlaceMarkerAddr);
		google.maps.event.addListener(marker, 'mouseout', function() { _infoBubble.close(); });
		google.maps.event.addListener(marker, 'click', pinMapMarkerAtPlace );
		_bnds.extend(place.geometry.location);
		_zoomSnapTo = true;
		addAddress(marker, place.reference);
		_placeMarkers.push(marker);
    placesList.append(  "<li id='lsm" + marker.ID + "' title=\"" + place.name + "\">" +
                          "<div class='place-number'>" + (marker.ID+1) + ".</div><a href='javascript:selectPlaceMarker(" + marker.ID + ")'>" + /*(marker.ID+1) + ". " + */place.name + "</a>"+
                        "</li>");
	}
	_map.fitBounds(_bnds);
  $('.places-control').draggable().show();
  var w = $('.places-list').outerWidth();
  var h = $('.places-list').height()+20;
  log("Setting places control dimensions to " + w + "x" + h);
  $('.places-control').width(w).height(h);
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
        $('#lsm'  + mark.ID).prop('title', mark.address);
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

function showPlaceMarkerAddr() {
	_infoBubble.setContent(getPlaceInfoBubbleHtml(this));
  _infoBubble.set('pixelOffset', _placesIBOffset );
	_infoBubble.open(_map, this);
  var innerDiv = $('.info-bubble-place-details').children().eq(0);
  var h = innerDiv.height();
  var w = innerDiv.width();
  var scale = 1.1;
  $('.info-bubble-place-details').height(h*scale);
  $('.info-bubble-place-details').width(w*scale);
};

function getPlaceInfoBubbleHtml(place)
{
  var html =  "<div class='info-bubble-place-details'>" +
                "<span>" + place.name + "</span>" +
              "</div>";
  return html;
}

function pinMapMarkerAtPlace()
{
  _infoBubble.close();
	var addr = this.address ? this.address : ( this.vicinity ? this.vicinity : "" );
  var coords = this.getPosition();
	pinMapMarker(coords);
  setMarkerAddr(this.name + ", " + addr);
  _markerCoords = coords;
}

function selectPlaceMarker(id)
{
	var markerPos = _placeMarkers[id].getPosition();
	_map.panTo(markerPos);
	google.maps.event.trigger(_placeMarkers[id], 'mouseover');
}

function setMarkerAddr(addr)
{
  _markerAddr = addr;
  $('.marker-addr').text(addr);
}

function processDrawnPolygon(e)
{
	var tempPoly = e.overlay;
	var tempPath = tempPoly.getPath().getArray();
	
  _drawnPolygon = tempPoly;
  _drawnPolyJustAdded = true;
}

function deletePolyAlreadyOnMap()
{
  if (_drawingManager.getDrawingMode() == "polygon" && _drawnPolygon != null && !_drawnPolyJustAdded) {
    _drawnPolygon.setMap(null);
    _drawnPolygon = null;
  }
  _drawnPolyJustAdded = false;
}