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
  	
  _geocoder = new google.maps.Geocoder();
  _placesServ = new google.maps.places.PlacesService(_map);
  
  var infoWinOptions =  {
    content: "<div id='content'><b>Address</b></div>"
  }
  _infoBubble = new google.maps.InfoWindow(infoWinOptions);

  _placesIBOffset = new google.maps.Size(-10, 0);
  _markerIBOffset = new google.maps.Size(0, -38);
  
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
    _drawingManager.setDrawingMode(google.maps.drawing.OverlayType.POLYGON);
    
    applyGoogleMapHacks();
    _drawingManager.setMap(_map);
    _map.setOptions({draggableCursor:null});
  }
  else {
    _mapmarker = createNewMarker();
    _mapmarker.setOptions({draggable:true});
    google.maps.event.addListener(_map, 'click', handleMapClick);
    google.maps.event.addListener(_mapmarker, 'dragend', handleMarkerDrag);
  }
  
  var dbCoords = $('#ansCoords').val();
  if ( dbCoords.length > 0 ) {
    log("DB geom string", dbCoords);
    var mapCenter;
    var lls = convertGeomTextToLatLng(dbCoords);
    var latLngs = lls.coords;
    if ( latLngs.length == 1 ) {
      _mapmarker.address = $('#ansAddr').val();
      pinMapMarker(latLngs[0], false);
      _map.setCenter(latLngs[0]);
    }
    else if ( lls.isPoly ) {
      _drawnPolygon = new google.maps.Polygon({ paths:latLngs });
      _drawnPolygon.setMap(_map);  
      _map.setCenter(calcPolyCenter());
      setSubmitAnswerOptionEnabled();
    }
    else {
      var bnds = new google.maps.LatLngBounds();
      var addrs = $('#ansAddr').val().split("¦");
      for ( var y = 0; y < latLngs.length; y++ ) {
        _mapmarker.address = addrs[y];
        pinMapMarker(latLngs[y], false);
        if ( y != latLngs.length-1 ) {
          addMarkerToMap();
        }
        bnds.extend(latLngs[y]);
      }
      _map.fitBounds(bnds);
    }
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
    _mapmarker.address = results[res_index].formatted_address;
    log(results.length + " match" + (results.length == 1 ? "" : "es") + " found - choosing "  + quote(_mapmarker.address,"'"));
    pinMapMarker(results[res_index].geometry.location);
  }
  else if (status == google.maps.GeocoderStatus.ZERO_RESULTS) {
    showPopupMsg(5, "Location not found.");
  }
  
  return { 'ok' : ok };
}

function geocoderResponseCenterMap(results, status)
{
  var res = geocoderResponse(results, status);
  if ( res.ok ) {
    _map.panTo(_mapmarker.getPosition());
  }
}

function pinMapMarker(coords, doPopup)
{
  if ( isUndef(doPopup) ) {
    doPopup = true;
  }
  var firstTimeHere = !_mapmarker.getVisible();
  _mapmarker.setPosition(coords);
  _mapmarker.setVisible(true);
  if ( firstTimeHere ) {
    _mapmarker.setAnimation(null);
    setSubmitAnswerOptionEnabled();
    var idx = _markers.push(_mapmarker);
    _mapmarker.idx = idx-1;
    if ( _markers.length < _maxNumMarkers ) {
      $('#addDest').prop('disabled', false);
    }
  }
  if (doPopup) {
    showMarkerInfoBubble();
  }
}

function handleMarkerDrag(e)
{
  geocodeMarker(e.latLng, true);
  showMarkerInfoBubble();
}

function showMarkerInfoBubble(marker)
{
  if ( isUndef(marker) ) {
    marker = _mapmarker;
  }
  _infoBubble.setOptions({
    pixelOffset: _markerIBOffset,
    content: "<div id='infoBubbleContent'><span>" + escapeSpecialChars(marker.address) + "</span></div>"
             //"&nbsp;(<a href='javascript:removeMarker(" + _mapmarker.idx + ");'>Remove</a>)
  });
  _infoBubble.open(_map, marker);
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
  _mapmarker.address = this.name + ", " + addr;
}

function selectPlaceMarker(id)
{
	var markerPos = _placeMarkers[id].getPosition();
	_map.panTo(markerPos);
	google.maps.event.trigger(_placeMarkers[id], 'mouseover');
}

function processDrawnPolygon(e)
{
	var tempPoly = e.overlay;
	var tempPath = tempPoly.getPath().getArray();
	
  /* Validate what has been drawn to make sure it's not bogative */
  if ( tempPath.length > 2 ) {
    _drawnPolygon = tempPoly;
    _drawnPolyJustAdded = true;
    setSubmitAnswerOptionEnabled();
  }
}

function deletePolyAlreadyOnMap()
{
  if (_drawingManager.getDrawingMode() == google.maps.drawing.OverlayType.POLYGON && _drawnPolygon != null && !_drawnPolyJustAdded) {
    _drawnPolygon.setMap(null);
    _drawnPolygon = null;
    setSubmitAnswerOptionEnabled(false);
  }
  _drawnPolyJustAdded = false;
}

function applyGoogleMapHacks()
{
  /* Hack 1 to listen for map clicks in drawing mode (since Google Maps disable click events in this type of situation */
  var hackSelectors = { '1' : { 'elem' : $('.gm-style').children().eq(0), 'applied' : false }, '2' : { 'elem' : $('.gmnoprint').eq(2).children(), 'applied' : false } };
  
  var allHacksApplied = true;
  for ( var i in hackSelectors ) {
    var rec = hackSelectors[i];
    if ( !rec.applied ) {
      if ( rec.elem.length >= 1 ) {
        applyHack(rec.elem, i);
        rec.applied = true;
      }
      else {
        allHacksApplied = false;
      }
    }
  }
  
  if ( !allHacksApplied) {
    setTimeout(applyGoogleMapHacks, 1000);
  }
  
  function applyHack(gmElem, i) {
    if ( i == 1 ) {
      gmElem.click(deletePolyAlreadyOnMap);
    }
    else if ( i == 2 ) {
      gmElem.eq(0).insertAfter(gmElem.eq(1));
    }
    log("GM hack " + i + " applied");
  }
}

function convertGeomTextToLatLng(geom_text)
{
  var re = /([-\.0123456789]+)/g
  var matches = geom_text.match(re);
  var isPolygon = false;
  
  /* Drop the last lat-lng from list, since it is a repeat of the first point (in order to comply with geom string polygon rules) */
  if ( geom_text.toLowerCase().startsWith("polygon") ) {
    isPolygon = true;
    matches.pop();
    matches.pop();
  }
  var latLngs = [];
  for (var x = 0; x < matches.length; x += 2) {
    var latLng = new google.maps.LatLng(matches[x],matches[x+1])
    latLngs.push(latLng);
  }
  return { coords: latLngs, isPoly: isPolygon };
}

function calcPolyCenter()
{
	var a = [];
	var path = _drawnPolygon.getPath().getArray();
	for (i = 0; i < path.length; i++){ a[i]=[path[i].lat(), path[i].lng()]; }
	var cc = polygonCentroid(a);
	var center = _loca =  new google.maps.LatLng(cc[0], cc[1]);
	_drawnPolygon.cc = center;
	return center;
}

function polygonCentroid(pts) 
{
   var twicearea = 0;
   var x = 0; 
   var y = 0;
   var nPts = pts.length;
   var p1, p2, f;
   if (nPts == 2) 
   { 
	   p1 = pts[0];
	   p2 = pts[1];
	   f = [(p1[0] + p2[0]) / 2, (p1[1] + p2[1]) / 2 ];
	   return f; 
   }
   for (var i = 0, j = nPts - 1 ; i < nPts; j = i++) 
   {
      p1 = pts[i]; p2 = pts[j];
      twicearea += p1[0] * p2[1];
      twicearea -= p1[1] * p2[0];
      f = p1[0] * p2[1] - p2[0] * p1[1];
      x += (p1[0] + p2[0]) * f;
      y += (p1[1] + p2[1]) * f;
   }
   f = twicearea * 3;
   return [x / f, y / f]; 
}

function addMarkerToMap()
{
  _mapmarker.setVisible(false);
  var newMarker = createNewMarker(_mapmarker);
  newMarker.setOptions({icon:'http://maps.google.com/mapfiles/ms/icons/green-dot.png'});
  _markers[_markers.length-1] = newMarker;
  $('#addDest').prop('disabled', true);
}

function removeMarker(idx)
{
  var mark = _markers[idx];
  mark.setMap(null);
  _markers.splice(idx,1);
  if ( idx == _markers.length ) {
    _mapmarker.setVisible(false);
  }
}

function createNewMarker(marker2)
{
  var markerOptions = { 
    map: _map, 
    position: ( isUndef(marker2) ? _latLngLondon : marker2.getPosition() ),
    visible: !isUndef(marker2)
  }
	
  var marker = new google.maps.Marker(markerOptions);
  if (!isUndef(marker2)) {
    marker.address = marker2.address;
  }
  google.maps.event.addListener(marker, 'click', function() { showMarkerInfoBubble(this); } );
  return marker;
}