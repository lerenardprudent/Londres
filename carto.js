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
  _markerIBOffset = new google.maps.Size(0, -5);
  
  MyOverlay.prototype = new google.maps.OverlayView();
  MyOverlay.prototype.onRemove = function() {}
  MyOverlay.prototype.onAdd = function(){}
  MyOverlay.prototype.draw = function() {}
  
  function MyOverlay(map)
  {
    this.setMap(map);
  }  
  _overlay = new MyOverlay(_map);
  
  if ( $('.map-focus').val().length > 0 ) {
    var focusCoords = convertGeomTextToLatLng($('.map-focus').val()).coords[0];
    _map.setCenter(focusCoords);
    _focusMarker = new google.maps.Marker({position:focusCoords, draggable:false, map: _map });
    var isHome = ( $('.map-focus').hasClass('home') ); /* For now it can only be home or school */
    var options = {content: "<div class='infowin'><span>" + (isHome ? "Home" : "School") + "</span></div>"};
    var iw = new google.maps.InfoWindow(options);
    google.maps.event.addListener(_focusMarker, 'click', function() {
      iw.open(_map, this);
      /* Below is a hack so that the info window does not cut off text */
    } );
    _focusMarker.setIcon ( isHome ? 'img/home2.png' : 'img/school3.png' );
    _focusMarker.setMap(_map);
  }
  
  /********* Map listeners *************/
  google.maps.event.addListener(_map, 'maptypeid_changed', logMapTypeChange );
  google.maps.event.addListener(_map, 'bounds_changed', function(event) {
		var zoomLevel = _map.getZoom();
		if ( _zoomSnapTo && zoomLevel > _closeUpZoomLevel ) {
			_zoomSnapTo = false;
			_map.setZoom(_closeUpZoomLevel);
		}
	});
  google.maps.event.addListenerOnce(_map, 'idle', function() {
    _overlayProjection = _overlay.getProjection();
    initGoogleMapHacks();
    applyGoogleMapHacks();
    loadDBAnswer();
    blockUI(false);
    log("Map ready"); 
  });
  
  _drawingManager = new google.maps.drawing.DrawingManager({
    drawingControl: (_markers.length != _maxNumMarkers),
    drawingControlOptions: {
      position: google.maps.ControlPosition.TOP_CENTER,
      drawingModes: [_drawingPoly ? google.maps.drawing.OverlayType.POLYGON : google.maps.drawing.OverlayType.MARKER]
    }
  });
  
  google.maps.event.addListener(_drawingManager, 'overlaycomplete', processNewMapObject);
  _drawingManager.setMap(_map);
  setCursor();
  
  if ( !_drawingPoly ) {
    google.maps.event.addListener(_map, 'click', handleMapClick);
    _maxNumMarkers = ( _freqQuestType ? 3 : 1 );
  }
  
  _greenMarker = {
    url: 'img/marker-icon-green-22x39.png',
    // This marker is 20 pixels wide by 32 pixels tall.
    size: new google.maps.Size(22, 39),
    // The origin for this image is 0,0.
    origin: new google.maps.Point(0,0),
    // The anchor for this image is the base of the flagpole at 0,32.
    anchor: new google.maps.Point(11, 39)
  };
}
    
function runSearch()
{
  var searchString = $('.searchfld').val();
  if ( searchString.length > 0 ) {
    log("Searched for " + quote(searchString,"'"));
    $('.searchfld').val("");
    _searchActivity.push((_searchModePlaces ? "placeSearch[" : "locationSearch[") + quote(searchString) + ']');
    // Force search slider to close
    if ( _searchModePlaces ) {
      radialPlaceSearch(searchString);
    }
    else {
      geocodeAddress(searchString);
    }
  }
}

function handleMapClick(clickEvent)
{
  /* If cursor is not a crosshair then it is probably a hand and we can ignore the click event */
  if ( _map.get('draggableCursor') == 'crosshair' ) {
    var pix = clickEvent.pixel; //_overlayProjection.fromLatLngToContainerPixel(clickLatLng);
    log ( "Map clicked at ", pix);
    var mapW = $('#map').width();
    var mapH = $('#map').height();
    var mapH = $('#map').height();
    var minEdgeDist = Math.min(pix.x,mapW-pix.x,pix.y,mapH-pix.y);
    var topEdgeDist = pix.y;
    var needToRecenter = ( minEdgeDist < 10 || topEdgeDist < 50 );
    if ( needToRecenter ) {
      log("Map canvas click at distance of only ", minEdgeDist, "; recentering map.");
    }
    geocodeMarker(clickEvent.latLng, needToRecenter);
    highlightLocation(clickEvent.latLng);
  }
}

function geocodeMarker(lat_lng, centerOnMarker)
{
  if ( centerOnMarker ) {
    _map.panTo(lat_lng);
  }
  _geocoder.geocode( {latLng:lat_lng}, geocoderResponse );
}

function geocodeAddress(addr)
{
	/* Give hint to where we want to search */
	_geocoder.geocode( { 'address': addr + ",London,UK" }, geocoderResponse );
}

function geocoderResponse(results, status)
{
  var ok = false;
	
  if (status == google.maps.GeocoderStatus.OK) {
    ok = true;
    var res_index = 0;
    var addr = results[res_index].formatted_address;
    log(results.length + " match" + (results.length == 1 ? "" : "es") + " found - choosing "  + quote(addr, "'"));
    setMarkerAddress(_mapmarker, addr);
  }
  else if (status == google.maps.GeocoderStatus.ZERO_RESULTS) {
    showPopupMsg(5, "Location not found.");
  }
  
  return { 'ok' : ok };
}

function pinMapMarker(coords, address)
{
  var marker = (!_tutMode ? _mapmarker : _tutMarker);
  var mustAddMarker = (marker == null);
  if ( mustAddMarker ) {
    if ( !_tutMode ) {
      _mapmarker  = addMarkerToMap(coords, address);
    }
    else {
      _tutMarker  = addMarkerToMap(coords, address);
    }
    marker = (!_tutMode ? _mapmarker : _tutMarker);
  }
  else {
    marker.setOptions({visible:true, position:coords});
    resetActiveModal();
  }
  
  showInfoBubble(marker, true);
}

function handleMarkerDrag(e)
{
  geocodeMarker(e.latLng, true);
  showInfoBubble();
}

function showInfoBubble(marker, timeOut)
{
  if ( isUndef(marker) ) {
    marker = _mapmarker;
  }
  if ( isUndef(timeOut) ) {
    timeOut = false;
  }
  
  var iwOptions;
  if ( _drawingPoly ) {
    iwOptions = { position : _drawnPolygon.centroid,
                  content:  "<div class='info-bubble'>" +
                            "<span><b>ZONE</b>: <i>" + _placeType + "</i></span><br>(" +
                            ( !_drawnPolygon.confirmed ? "<a class='confirm-poly' href='javascript:confirmZone();'>Confirm zone</a> or " : "" ) +
                            "<a class='remove-marker' href='javascript:removePolygon();'>Remove polygon</a>)" +
                            "</div>"
                };
  } else
  iwOptions = {
    pixelOffset: _markerIBOffset,
    content:  "<div class='info-bubble'>" +
                ( isUndef(marker.label) ? "" : "<span class='desc-label'>" + marker.label + "</span>" ) +
                "<span><b>PLACE</b>: <i>" + _placeType + "</i></span><br>(" +
                ( !marker.confirmed ? "<a class='confirm-marker' href='javascript:showConfirmMarkerDialog(" + marker.idx + ");'>Confirm location</a> or " : "" ) +
                "<a class='remove-marker' href='javascript:removeMarker(" + marker.idx + ");'>Remove pushpin</a>)" +
              "</div>"
  };
  
  _infoBubble.setOptions(iwOptions);
  $.noty.closeAll();
  if ( _drawingPoly ) {
    _infoBubble.open(_map);
  } else {
    _infoBubble.open(_map, marker);
  }
  $('.info-bubble').parent().parent().width($('.info-bubble').width()*1.1);
  $('.info-bubble').parent().parent().height($('.info-bubble').height()*1.1);
  if ( timeOut ) {
    window.clearTimeout(_timer);
    _timer = setTimeout( function() {_infoBubble.close()}, 8000);
  }
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
		showPopupMsg(2, "Unable to find any matching places!");
		return;
	}	
  
  pinPlaceMarkers(results);
  var moreLink = document.getElementById('moreResults');
	if (pagination.hasNextPage) 
	{
    moreLink.style.display = 'inline-block';
		google.maps.event.addDomListenerOnce(moreLink, 'click', function() { pagination.nextPage(); });
	}
	else
	{
		moreLink.style.display = 'none';
	}
}

function removePlaceMarkers()
{
	for (var i = 0; i < _placeMarkers.length; i++)
	{ 
		removePlaceMarker(i);
	}
	_placeMarkers = [];
	banishPlacesControl();
  $('.places-list').html("");
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
	_placesBnds = new google.maps.LatLngBounds();
	var placesList = $('.places-list');

	for (var i = 0, place; place = places[i]; i++)
	{
		var image = {
      url:place.icon, 
      size:new google.maps.Size(30, 30), 
      origin:new google.maps.Point(0, 0), 
      anchor:new google.maps.Point(10,20), 
      scaledSize:new google.maps.Size(15, 15)
    };
		var marker = new google.maps.Marker({ map:_map, icon:image, position:place.geometry.location, raiseOnDrag:false });
		marker.ID = _placeMarkers.length;
		marker.name = place.name;
		google.maps.event.addListener(marker, 'mouseover', showPlaceMarkerAddr);
		google.maps.event.addListener(marker, 'mouseout', function() {
      _infoBubble.close(); $('.places-list li').eq(this.ID).toggleClass('highlight-li');
    });
		google.maps.event.addListener(marker, 'click', handlePlaceMarkerClick );
		_placesBnds.extend(place.geometry.location);
		_zoomSnapTo = true;
		addAddress(marker, place.reference);
		_placeMarkers.push(marker);
    var newLi = $("<li id='lsm" + marker.ID + "' title=\"" + place.name + "\"><div class='li-content'>" +
                          "<div class='place-number'>" + (marker.ID+1) + ".</div><a href='javascript:selectPlaceMarker(" + marker.ID + ")'>" + /*(marker.ID+1) + ". " + */place.name + "</a></div>"+
                        "</li>").hover( function() { $(this).toggleClass('highlight-li'); }, function() { $(this).toggleClass('highlight-li'); } );
    placesList.append(newLi);
	}
	_map.fitBounds(_placesBnds);
  $('.places-control').draggable({handle: ".draggable-heading"}).show();
  var maxWidth = 0;
  placesList.find('.li-content').each( function() {
    if ( $(this).outerWidth() > maxWidth ) {
      maxWidth = $(this).outerWidth();
    }
  });
  log("Setting places control width to " + (maxWidth*1.2));
  $('.places-control').width(maxWidth*1.2);
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
  _searchActivity.push( "mapViewChange[" + _map.getMapTypeId() + ']' );
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
  $('.places-list li').eq(this.ID).toggleClass('highlight-li');
  var scrollPos = 15*(this.ID);
  var scrollTop = $('.places-list')[0].scrollTop;
  var scrollDiff = 0;
  if ( scrollPos < scrollTop ) {
    scrollDiff = scrollPos - scrollTop;
  }
  else if ( (scrollPos+15) > (scrollTop + (20*15)) ) { /* We want the whole li to be visible, not just the top of it */
    scrollDiff = scrollPos - scrollTop - (18*15);
  }
  $('.places-list').animate({scrollTop:scrollTop+scrollDiff}, 600);
};

function getPlaceInfoBubbleHtml(place)
{
  var html =  "<div class='info-bubble-place-details'>" +
                "<span>" + place.name + "</span>" +
              "</div>";
  return html;
}

function handlePlaceMarkerClick()
{
  _infoBubble.close();
  var clickedMarker = this;
	var addr = clickedMarker.address ? clickedMarker.address : ( clickedMarker.vicinity ? clickedMarker.vicinity : "" );
  var coords = clickedMarker.getPosition();
	highlightLocation(coords);
  setMarkerAddress(!_tutMode ? _mapmarker : _tutMarker, clickedMarker.name + ", " + addr);
}

function selectPlaceMarker(id)
{
	var markerPos = _placeMarkers[id].getPosition();
  _map.setZoom(15);
	_map.panTo(markerPos);
	google.maps.event.trigger(_placeMarkers[id], 'mouseover');
}

function processNewMapObject(e)
{
  var newMapObj = e.overlay;
  _drawingManager.setDrawingMode(null);
  if ( e.type == google.maps.drawing.OverlayType.POLYGON ) {
    var tempPath = newMapObj.getPath().getArray();
    
    /* Validate what has been drawn to make sure it's not bogative */
    if ( tempPath.length > 2 ) {
      _drawnPolygon = newMapObj;
      _drawnPolygon.centroid = calcPolyCenter();
      _drawnPolygon.confirmed = false;
      google.maps.event.addListener(_drawnPolygon, 'click', showInfoBubble);
      _drawnPolyJustAdded = true;
      setSubmitAnswerOptionEnabled();
    }
    showInfoBubble();
  } else
  if ( e.type == google.maps.drawing.OverlayType.MARKER ) {
    var pos = newMapObj.getPosition();
    newMapObj.setMap(null);
    geocodeMarker(pos);
    highlightLocation(pos);
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

function initGoogleMapHacks()
{
  /* Hack 1 to listen for map clicks in drawing mode (since Google Maps disable click events in this type of situation */
  _hackSelectors = { 
    '1' : { 'elem' : $('.gm-style').children().eq(0), 'applied' : false }, 
    '2' : { 'elem' : $('.gmnoprint').eq(2).children(), 'applied' : false }, 
    '3' : { 'elem' : $('.gmnoprint').find("[title='Add a marker'],[title='Draw a shape']"), applied: false } ,
    '4' : { 'elem' : $('.gmnoprint').find("[title='Zoom in']"), applied: false },
    '5' : { 'elem' : $('.gmnoprint').find("[title^='Show sat']"), applied: false }
  };
}

function applyGoogleMapHacks()
{
  var allHacksApplied = true;
  for ( var i in _hackSelectors ) {
    var rec = _hackSelectors[i];
    if ( !rec.applied ) {
      if ( rec.elem.length >= 1 ) {
        applyHack(rec.elem, i);
        _hackSelectors[i].applied = true;
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
    else if ( i == 3 ) {
      if ( !_drawingPoly ) {
        gmElem.prop('id', _addMarkerBtnId ).prop('title', 'Add a pushpin to the map');
        if ( _maxNumMarkers > 1 ) {
          gmElem.prop('title', gmElem.prop('title'));
        }
        $('#' + _addMarkerBtnId).css('cursor', 'pointer').click( function() {
          geocodeMarker(_map.getCenter());
        });
      }
      else {
        gmElem.prop('id', _drawPolyBtnId ).prop('title', 'Trace a region').css('cursor', 'pointer');
      }
    }
    else if ( i == 4 ) {
      gmElem.prop('id', _zoomInBtnId );
    }
    else if ( i == 5 ) {
      gmElem.prop('id', _changeMapViewBtnId );
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
  if ( geom_text.toLowerCase().substr(0,geom_text.indexOf('(')) == "polygon" ) {
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

/*
function addMarkerToMap()
{
  _mapmarker.setVisible(false);
  var newMarker = createNewMarker(_mapmarker);
  newMarker.setOptions({icon:'http://maps.google.com/mapfiles/ms/icons/green-dot.png'});
  _markers[_markers.length-1] = newMarker;
}
*/

function removeMarker(idx)
{
  _markers[idx].setMap(null);
  
  /* Two cases to watch out for:
   * - Removal of current (red) active marker
   * - Removal of confirmed marker when all the other markers are also confirmed
   */
  if ( _markers[idx] == _mapmarker || _mapmarker == null ) {
    toggleAddMarkerButton(true);
    _mapmarker = null;
  }
  _markers[idx] = null;
  _markers.splice(idx,1);
  /* Decrement the index since all the markers after the one removed are moving down one in position */
  for ( ; idx < _markers.length; idx++ ) {
    _markers[idx].idx--;
  }
  _infoBubble.close();
  
  setCursor();
}

function addMarkerToMap(coords, addr, anima, dontToggleAddMarkerBtn)
{
  var markerOptions = { 
    map: _map, 
    position: ( isUndef(coords) ? _map.getCenter() : coords ),
    draggable: true,
    animation: (isUndef(anima) ? google.maps.Animation.DROP : anima)
  }
	
  var marker = new google.maps.Marker(markerOptions);
  if (!isUndef(addr)) {
    setMarkerAddress(marker, addr);
  }
  
  addMarkerToList(marker);
  google.maps.event.addListener(marker, 'click', function() { showInfoBubble(this, false); } );
  google.maps.event.addListener(marker, 'dragstart', function() { _infoBubble.close(); } );
  google.maps.event.addListener(marker, 'dragend', handleMarkerDrag );
  setMarkerConfirmationFlag(marker, false);
  
  /* Create modal for marker (if there isn't already one */
  cloneModal(marker.idx);
  
  //setSubmitAnswerOptionEnabled();
    
  /* Hide the add marker button, since another marker cannot be added yet */
  if (isUndef(dontToggleAddMarkerBtn) ) {
    setTimeout( function() { toggleAddMarkerButton(); }, 500 );;
  }
  
  /* In case the Add Marker button was clicked, let's undo the switch into Drawing mode */
  _drawingManager.setDrawingMode(null);
  
  return marker;
}

function setMarkerAddress(marker, addr)
{
  if ( marker != null && !isUndef(addr) ) {
    marker.address = addr;
  }
}

function addMarkerToList(marker)
{
  var idx = _markers.push(marker);
  marker.idx = idx-1;
}

function showConfirmMarkerDialog(idx)
{
  _infoBubble.close();
  _map.panTo(_markers[idx].getPosition());
  $('#' + _modalMarkerPfx + idx).modal();
  //saveModalState('#modalMarker' + idx);
}

function confirmMarker(idx, dontToggleAddMarkerBtn)
{
  if ( idx >= _markers.length ) {
    return;
  }
  
  _markers[idx].setIcon(_greenMarker);
  setMarkerConfirmationFlag(_markers[idx]);
  _markers[idx].setOptions({draggable:false});
  if ( _markers[idx] === _mapmarker ) {
    _mapmarker = null;
  }
  if ( isUndef(dontToggleAddMarkerBtn) && _markers.length < _maxNumMarkers ) {
    toggleAddMarkerButton();
  }
  
  setCursor();
  _infoBubble.close();
  
  /* If this variable is undefined then chances are we are in the middle of adding location(s) 
   * to map loaded from DB and so we don't want to show any popups here
   */
  if ( isUndef(dontToggleAddMarkerBtn) ) {
    showMarkerNotification();
  }
}

function setMarkerConfirmationFlag(marker, confirmed)
{
  if ( isUndef(confirmed) ) {
    confirmed = true;
  }
  marker.confirmed = confirmed;
}

function toggleAddMarkerButton(show)
{
  if ( isUndef(show) ) {
    $('#' + _addMarkerBtnId).toggle('blind', 500 );
  }
  else {
    ( show ? $('#' + _addMarkerBtnId).show('blind', 500 ) : $('#' + _addMarkerBtnId).hide('blind', 500 ) );
  }
}

function loadDBAnswer()
{
  var dbCoords = $('#ansCoords').val();
  if ( dbCoords.length > 0 ) {
    log("DB geom string", dbCoords);
    var mapCenter;
    var lls = convertGeomTextToLatLng(dbCoords);
    var latLngs = lls.coords;
    if ( lls.isPoly ) {
      _drawnPolygon = new google.maps.Polygon({ paths:latLngs });
      _drawnPolygon.setOptions({
        map: _map,
        fillColor: 'green',
        fillOpacity: 0.85
      });
      _drawnPolygon.centroid = calcPolyCenter();
      _drawnPolygon.confirmed = true;
      google.maps.event.addListener(_drawnPolygon, 'click', showInfoBubble);
      _map.setCenter(_drawnPolygon.centroid);
      setSubmitAnswerOptionEnabled();
    }
    else {
      _bnds = new google.maps.LatLngBounds();
      var addrs = $('#ansAddr').val().split("¦");
      var labels = $('#ansDestLabel').val().split("¦");
      var toggleAddMarkerBtn = false;
      for ( var y = 0; y < latLngs.length; y++ ) {
        addMarkerToMap(latLngs[y], addrs[y], null, toggleAddMarkerBtn);
        confirmMarker(y, toggleAddMarkerBtn);
        _bnds.extend(latLngs[y]);
        setMarkerLabel(y, labels[y]);
      }
      setMapBounds();
    }
  }
  
  var ansInfos = $('#ansInfo').val();
  var markerInfos = ansInfos.split('¦');
  for ( var x = 0; x < markerInfos.length; x++ ) {
    var mInfos = markerInfos[x];
    var infos = mInfos.split(' + ');
    for ( var y = 0; y < infos.length; y++ ) {
      var q_and_a = infos[y];
      if ( q_and_a.length > 0 && q_and_a.indexOf('[') > 0 ) {
        var tokens = q_and_a.split('[');
        var q = tokens[0];
        var a = tokens[1].replace(/\]/, '');
        var l = getElemByText('#' + _modalMarkerPfx + x + ' label', q);
        if ( l.length == 1 ) {
          getElemByText('option', a, l.next()).prop('selected', true);
          l.closest('.follow-up-pair').addClass('active');
          log("Adding active to " + l.text());
        }
      }
    }
  }
}

function resetActiveModal()
{
  var activeIdx = _markers.length-1;
  $('#modalMarker' + activeIdx + ' select').each(function() {$(this)[0].selectedIndex = 0; });
  $('#modalMarker' + activeIdx + ' .follow-up-pair').not(':first').hide();
}

function highlightLocation(coords, address)
{
  var dontPlaceMarker = ( _drawingPoly && !_tutMode )/* || _markers.length == _maxNumMarkers*/;
  dontPlaceMarker ? _map.setCenter(coords) : pinMapMarker(coords, address);
}

function setCursor()
{
  var canAddMarker = !_drawingPoly && _mapmarker == null && _markers.length < _maxNumMarkers;
  var cursorVal = ( canAddMarker ? 'crosshair' : null );
  toggleAddMarkerButton(canAddMarker);
  _map.set('draggableCursor', cursorVal );
}

function showMarkerNotification()
{
  var popupMessage = "You may add another destination (or simply proceed to the next question).";
  if ( _maxNumMarkers == _markers.length ) {
    popupMessage = ( _maxNumMarkers == 1 ? "" : "You have added the maximum number of destinations to the map. Thank you!" );
  }
  else if ( _maxNumMarkers - 1 == _markers.length ) {
    popupMessage = "You may add one more destination to the map.";
  } 
  
  if ( popupMessage.length > 0 ) {
    generateIt(5, popupMessage);
  }
}

function setMarkerLabel(idx, label)
{
  if ( _markers.length > idx && !isUndef(label) && label.length > 0 ) {
    _markers[idx].label = label;
  }
}

function showMarkersOnMap(show)
{
  if ( _drawingPoly ) {
    return;
  }
  
  if (isUndef(show)) {
    show = true;
  }
  
  for ( var v = 0; v < _markers.length; v++ ) {
    _markers[v].setVisible(show);
  }
  
  if ( show ) {
    setMapBounds();
  }
}

function reloadMapState()
{
  showMarkersOnMap();
  (_mapState.add_marker_btn ? $('#' + _addMarkerBtnId).show() : $('#' + _addMarkerBtnId).hide() );
  _drawingManager.set('drawingControlOptions', {position: google.maps.ControlPosition.TOP_CENTER, drawingModes: [_drawingPoly ? google.maps.drawing.OverlayType.POLYGON : google.maps.drawing.OverlayType.MARKER]});
  if (_tutMarker != null ) {
    _tutMarker.setMap(null);
    _tutMarker = null;
  }
  _tutMode = false;
  switchSearchMode(_mapState.search_mode_places);
  
  if ( _mapState.poly ) {
    _drawnPolygon.setVisible(true);
  }
  
  $('.draggable .close').click();
}

/* Zooms out to show all place search result icons */    
function showAll()
{
  _infoBubble.close();
  _map.fitBounds(_placesBnds);
}

function setMapBounds()
{
  if ( _markers.length > 1 ) {
    _map.fitBounds(_bnds);
  }
  else {
    _map.panTo(_markers[0].getPosition());
  }
}

function getElemByText(selector, text, rootElem)
{
  var s = ( isUndef(rootElem) ? $(selector) : $(selector, rootElem) );
  return s.filter(function() { return $(this).text() == text });
}

function confirmZone()
{
  _drawnPolygon.confirmed = true;
  _drawnPolygon.setOptions({ fillColor: 'green', fillOpacity: 0.8 });
  _infoBubble.close();
  
}

function removePolygon()
{
  _drawnPolygon.setMap(null);
  _drawnPolygon = null;
  _infoBubble.close();
}