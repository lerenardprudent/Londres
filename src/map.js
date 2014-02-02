//=============================== VARIABLES GLOBALES ==========================

var http_port = 6767;
var _zoomMin = 1, _zoomMax = 20; 
var polyStyle = { color: '#0000FF',
                  fillOpacity: 0.8,
                  opacity: 0.5
                };
var map = L.map('map', {zoomControl: false});
var LANGUE = {
  Français : { ind: 0, val: "fr" },
	Anglais : { ind: 1, val: "en" }
};
var MODE_TRANSPORT = {
  Voiture : { ind: 0, clr: "black" },
	Bus : { ind: 1, clr: "blue" },
  Métro : { ind: 2, clr: "red" },
  Vélo : { ind: 3, clr: "green" },
  Pied : { ind: 4, clr: "purple" }
};
var engTextTag = 'textang';
var urlParams = parseUrl();
var urlBase = window.location.href.split('?')[0];
var langParamKey = 'lang';
var langue;
var currTransMode = "Pied";
var modesHeading = "<b>" + chooseLang("Modes de transport de votre parcours","Transport modes of your itinerary") + " :</b><br>";
var modesEmptyHTML = "<p style='text-align:center'>" + modesHeading + "<i>" + chooseLang("Aucun parcours dessiné", "No itinerary plotted") + "</i></p>";
if ( typeof(urlParams[langParamKey]) !== 'undefined' && urlParams[langParamKey] == LANGUE.Anglais.val ) {
  langue = LANGUE.Anglais;
}
else {
  langue = LANGUE.Français;
}
$('#questintro').text(chooseLang("Le quartier de résidence peut avoir une influence sur la santé, et nous aimerions savoir ce que vous considérez être votre quartier.", "Your residential neighbourhood may influence your health, and we would like to know what you consider to be your neighbourhood."));
$('#quest').text(chooseLang("Pouvez-vous tracer sur la carte les limites de votre quartier telles que vous les percevez?", "Can you draw the boundaries of your neighbourhood as you perceive them to be on the map?"));
var tooltipEdit = chooseLang('Mode ÉDITION', 'EDIT mode');
var tooltipDelete = chooseLang('Mode ÉFFACEMENT', 'DELETE mode');
//========================= CALQUES DE LA CARTE ===============================

var osmLayer = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
  attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
});

defineZoomLevels();
recenter_map();
var offlineLayer = L.tileLayer('img/tiles/{z}/{x}/{y}.png',
                               {attribution: '&copy; <a href="http://http://mapnik.org/">Mapnik</a>',
                               minZoom:_zoomMin, maxZoom:_zoomMax}).addTo(map);

var defaultStyle = { "weight": 2,
                     "opacity": 0.65,
                     "fillColor" : "blue", 
                     "fillOpacity": 0.3,
                     "clickable" : true
                   };

var clickedStyle = { "fillColor" : "lightgreen",
                     "fillOpacity": 0.6
                   };

/*                   
var arrondsLayer = L.geoJson(
    null,
    { style: defaultStyle,
      onEachFeature: function (feature, layer) {
              layer.bindPopup(chooseLang("Arrondissement", "Neighborhood") + ": <b>" + feature.properties.NOM + '</b>');
        layer.on('click', function () { layer.setStyle(clickedStyle); });
        layer.on('popupclose', function () { layer.setStyle(defaultStyle); });
      }
    }).addTo(map);

var jsonData = readJSON('ext/sud-ouest.geojson');
arrondsLayer.addData(jsonData);*/

//============================= CONTROLS ======================================
var drawControl;
var drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);
initControls();

map.on('draw:drawstart', function (e) {
  if ( langue == LANGUE.Français ) {
    $('li a').each(function() {
      if ( $(this).attr('title').toLowerCase().indexOf('cancel') >= 0 ) {
        $(this).text('Annuler').attr('title', 'Annuler le dessin de la zone');
      }
      else if ( $(this).attr('title').toLowerCase().indexOf('delete') >= 0 ) {
        $(this).text('Effacer le dernier point').attr('title', 'Effacer le dernier point de dessiné');
      }
    });
  }
  if ( e.layerType == 'polyline' ) {  
    for ( var i in MODE_TRANSPORT ) {
      var href = "javascript:setTransportMode(" + MODE_TRANSPORT[i].ind + ")";
      $('ul[style]').append("<li><a style='color:" + MODE_TRANSPORT[i].clr + "' + href='" + href + "'>" + i.toString() + '</a></li>');
    }
  }
  var foo = drawnItems.getLayers();
  if ( foo.length > 0 ) {
    var lastSeg = foo[foo.length-1];
    lastSeg.on('click' , function() {alert("YES!");});
  }
});

map.on('draw:created', function (e) {
  var type = e.layerType,
  layer = e.layer;
  /*var decoLayer = L.polylineDecorator(layer, {
        patterns: [
            {offset: 30, repeat: 30, symbol: L.Symbol.arrowHead({pixelSize: 10, pathOptions : { color: MODE_TRANSPORT[currTransMode].clr, weight: 3}})}
        ]
  }).addTo(map);*/
    
  document.getElementById("save").disabled = false;
  drawnItems.clearLayers();
  drawnItems.addLayer(layer);
  $('#modes').html(modesHeading);
  var accLengs = L.GeometryUtil.accumulatedLengths(layer);
  for ( var x = 1; x < layer._latlngs.length; x++ ) {
    var optionsHTML = "";
    for ( var i in MODE_TRANSPORT ) {
      optionsHTML += '<option value="' + MODE_TRANSPORT[i].ind + '">' + i + '</option>';
    }
    $('#modes').append('<span style="padding: 8px">' + chooseLang("Étape ", "Leg ") + x + ' (distance ' + ((accLengs[x]-accLengs[x-1])/1000).toFixed(2) + 'km): <select>' + optionsHTML + '</select></span><br/>');
  }
  $('#modes').append('<p>');
});

map.on('draw:edited', function (e) {
  var layers = e.layers;
  var countOfEditedLayers = 0;
  layers.eachLayer(function(layer) {
    countOfEditedLayers++;
  });
  console.log("Edited " + countOfEditedLayers + " layers");
});
      
map.on('draw:deleted', function (e) {
  document.getElementById("save").disabled = true;
  console.log("Deleted layer");
  $('.leaflet-draw-edit-edit').attr('title', tooltipEdit );
  $('.leaflet-draw-edit-remove').attr('title', tooltipDelete );
  $('#modes').html(modesEmptyHTML);
});

function initControls()
{
  // Control calques - en bas à gauche
/*  
  if ( langue == LANGUE.Français ) {
    var baseLayers = {"Carte hors-ligne" : offlineLayer, "Carte en-ligne" : osmLayer};
    var overlays = { "Arrondissements du Sud-Ouest" : arrondsLayer};
  }
  else {
    var baseLayers = {"Off-line map" : offlineLayer, "On-line map" : osmLayer};
    var overlays = { "Sud-Ouest neighborhoods" : arrondsLayer};
  }

  L.control.layers(baseLayers, overlays, {position: 'bottomleft'}).addTo(map);*/

  // Controls zoom - en bas à droite
  var zoomControlOptions = { position: 'bottomright' };
  if ( langue == LANGUE.Français ) {
    zoomControlOptions.zoomInTitle = 'Zoomer avant';
    zoomControlOptions.zoomOutTitle = 'Zoomer arrière';
  }
  L.control.zoom(zoomControlOptions).addTo(map);

  // Controls dessin - en haut à droite
  L.drawLocal.draw.toolbar.buttons.polygon = chooseLang('Mode DESSIN', 'DRAW mode');
  
  if ( langue == LANGUE.Français ) {
    L.drawLocal.draw.handlers.polygon.tooltip = { start: 'Cliquer pour entamer le dessin.',
                                                  cont: 'Cliquer pour poursuivre le dessin.',
                                                  end: 'Cliquer le point initial pour achever le dessin.' };
    L.drawLocal.edit.toolbar.actions.save.text = "Sauvegarder";
    L.drawLocal.edit.toolbar.actions.save.title = "Sauvegarder toutes modifications";
    L.drawLocal.edit.toolbar.actions.cancel.text = "Annuler";
    L.drawLocal.edit.toolbar.actions.cancel.title = "Abandonner l'édition";
    L.drawLocal.edit.handlers.edit.tooltip.text = "Cliquer sur 'Annuler' pour défaire tous changements.";
    L.drawLocal.edit.handlers.edit.tooltip.subtext = "Modifier la forme du polygone en déplaçant ses ancranges.";
  }
                                                
  drawControl = new L.Control.Draw({
    position: 'topleft',
    draw: {
      circle: false,
      rectangle: false,
      polyline: {
        shapeOptions: { color: 'red', dashArray: "20 10 0 10", lineCap: "round" }
      },
      polygon: {
        allowIntersection: false,
        showArea: true,
        drawError: {
          color: '#b00000',
          timeout: 1000
        },
        shapeOptions: polyStyle
      },
      marker: false
    },
    edit: {
      featureGroup: drawnItems,
      edit: false,
      remove: true
    }
  });
  map.addControl(drawControl);
/*
  var info = L.control();

  info.onAdd = function (map) {
    this._div = L.DomUtil.create('div', 'info'); // create a div with a class "info"
    this.update();
    return this._div;
  };

  // method that we will use to update the control based on feature properties passed
  info.update = function (props) {
    this._div.innerHTML = "<i class='glyphicon glyphicon-fullscreen myIcon' onclick='recenter_map();'></i>";
};

info.addTo(map);
*/
  var MyCenterControl = L.Control.extend({
    options: {
      position: 'bottomleft',
    },
    onAdd: function (map) {
      var controlDiv = L.DomUtil.create('div', 'info');
      L.DomEvent
        .addListener(controlDiv, 'click', L.DomEvent.stopPropagation)
        .addListener(controlDiv, 'click', L.DomEvent.preventDefault)
        .addListener(controlDiv, 'click', function () { recenter_map(); });
      controlDiv.id = 'recenter';
      controlDiv.innerHTML = "<i class='glyphicon glyphicon-fullscreen myIcon' onclick='recenter_map();'></i>";
      controlDiv.title = chooseLang("Voir l'étendue de la carte", "Show the full map");
      return controlDiv;
    }
  });
  map.addControl(new MyCenterControl());


    var MyControl = L.Control.extend({
    options: {
      position: 'topright',
    },
    onAdd: function (map) {
      var controlDiv = L.DomUtil.create('div', 'info');
      L.DomEvent
        .addListener(controlDiv, 'click', L.DomEvent.stopPropagation)
        .addListener(controlDiv, 'click', L.DomEvent.preventDefault)
        .addListener(controlDiv, 'click', function () { recenter_map(); });
      controlDiv.id = 'modes';
      controlDiv.innerHTML = modesEmptyHTML;
      controlDiv.title = chooseLang("Voir l'étendue de la carte", "Show the full map");
      return controlDiv;
    }
  });
  map.addControl(new MyControl());

  $('.leaflet-draw-edit-edit').attr('title', tooltipEdit );
  $('.leaflet-draw-edit-remove').attr('title', tooltipDelete );
  L.drawLocal.edit.toolbar.buttons.edit = tooltipEdit;
  L.drawLocal.edit.toolbar.buttons.remove = tooltipDelete;
}

function readJSON(file)
{
	var json;
	if (typeof(http_port) === 'undefined')
		url = file;
	else
		url = "http://localhost:" + http_port + "/" + file;
	
	$.ajax({
		type: 'GET',
		async: false,
		beforeSend: function(xhr){
			if (xhr.overrideMimeType) {
				xhr.overrideMimeType("application/json");
			}
		},
		url: url,
		dataType: "json",
		success: function(data) {
			json = data;
		}
	});
	
	return json;
}

function defineZoomLevels()
{
	jsonZoomInfo = readJSON('zoomlevels.php');
	_zoomMin = jsonZoomInfo.minZ;
	_zoomMax = jsonZoomInfo.maxZ;
}

function recenter_map()
{
	map.setView([45.4564755,-73.663326], _zoomMin);
}

function saveTextAsFile()
{
  var textToWrite = JSON.stringify(drawnItems.getLayers()[0].getLatLngs());
	var textFileAsBlob = new Blob([textToWrite], {type:'text/plain'});
  var name = document.getElementById("nomsess");
  var ext = ".json";
	var fileNameToSaveAs = ( name.value.length > 0 ? name.value + ext : name.placeholder + ext );

	var downloadLink = document.createElement("a");
	downloadLink.download = fileNameToSaveAs;
	downloadLink.innerHTML = "Download File";
	if (window.webkitURL != null)
	{
		// Chrome allows the link to be clicked
		// without actually adding it to the DOM.
		downloadLink.href = window.webkitURL.createObjectURL(textFileAsBlob);
	}
	else
	{
		// Firefox requires the link to be added to the DOM
		// before it can be clicked.
		downloadLink.href = window.URL.createObjectURL(textFileAsBlob);
		downloadLink.onclick = destroyClickedElement;
		downloadLink.style.display = "none";
		document.body.appendChild(downloadLink);
	}

	downloadLink.click();
}

function destroyClickedElement(event)
{
	document.body.removeChild(event.target);
}

function loadSession()
{
  var fileToLoad = document.getElementById("fileToLoad").files[0];
	var fileReader = new FileReader();
  
	fileReader.onload = function(fileLoadedEvent) 
	{
		var textFromFileLoaded = fileLoadedEvent.target.result;
    var latlngs = JSON.parse(textFromFileLoaded);
    drawnItems.clearLayers();
    var polyLayer = new L.Polygon(latlngs);
    polyLayer.setStyle(polyStyle);
    drawnItems.addLayer(polyLayer);
	};
	fileReader.readAsText(fileToLoad, "UTF-8");
  var nomDeSess = fileToLoad.name;
  var suffix = ".json";
  if ( nomDeSess.toLowerCase().endsWith(suffix) )
    nomDeSess = nomDeSess.substring(0, nomDeSess.length-suffix.length);
  document.getElementById("nomsess").value = nomDeSess;
  document.getElementById("save").disabled = false;
}

function getCurrTimestamp()
{
  var d = new Date();
  var timestamp = leftPad(d.getDate()) + leftPad(d.getMonth()+1) + (d.getYear()-100) + "-" + leftPad(d.getHours()) + leftPad(d.getMinutes()) + leftPad(d.getSeconds());
  return timestamp;
}

function leftPad(x, padChar)
{
  if (typeof(padChar) === 'undefined')
    padChar = '0';
  var strX = x.toString();
  if (x < 10)
    strX = padChar + strX;
  return strX;
}

function parseUrl()
{
  var urlParams = {};
	var query = window.location.search.substring(1).split("&");
	for (var i = 0, max = query.length; i < max; i++)
	{
		if (query[i] === "") // check for trailing & with no param
			continue;

    var param = query[i].split("=");
    urlParams[decodeURIComponent(param[0])] = decodeURIComponent(param[1] || "");
	}
  return urlParams;
}

function renderSiteInEnglish()
{
  $('p[' + engTextTag + ']').each(function() { $(this).text($(this).attr(engTextTag)); });
  $('button[' + engTextTag + ']').each(function() {
    var oldHtml = $(this).html();
    var engTokens = $(this).attr(engTextTag).split(',');
    $(this).html(oldHtml.substring(0, oldHtml.lastIndexOf(';')) + engTokens[0]);
    if ( engTokens.length > 1 )
      $(this).attr('title', engTokens[1]);
  });
  $('input[' + engTextTag + ']').each(function() { $(this).attr('title', $(this).attr(engTextTag)); });
  $('div[' + engTextTag + ']').each(function() { 
    $(this).attr('title', $(this).attr(engTextTag)); 
  });
  $('button[data-target]').each(function() { $(this).attr('data-target', $(this).attr('data-target') + engTextTag); });
//  $('#douglas-link').attr('href', 'http://www.douglas.qc.ca/?locale=en');
}

function chooseLang(fr, en)
{
  return ( langue == LANGUE.Français ? fr : en );
}

function startTour()
{
  var windowWidth = 260;
  
  positions = [
  //  { container: '#tour', x: -40, y: -180, width: windowWidth, arrow: 'bc' },
    { container: '.leaflet-control-zoom-in', x: -(windowWidth+12), y: -107, width: windowWidth, arrow: 'rb' },
    { container: '.leaflet-draw-draw-polygon', x: 38, y: -10, width: windowWidth, arrow: 'lt' },
    { container: '#map', x: 300, y: 20, width: windowWidth, arrow: 'bc' },
    { container: '.leaflet-draw-edit-edit', x: 38, y: -10, width: windowWidth, arrow: 'lt' },
    { container: '.leaflet-draw-edit-remove', x: 38, y: -10, width: windowWidth, arrow: 'lt' },
    { container: '.leaflet-control-layers', x: 47, y: -190, width: windowWidth, arrow: 'lb' },
    { container: '#recenter', x: -(windowWidth+12), y:0, width: windowWidth, arrow: 'rt' },
    { container: '#labelnomsess', x: -(windowWidth-3), y:10, width: windowWidth, arrow: 'rt' },
    { container: '#save', x: -(windowWidth+12), y:-5, width: windowWidth, arrow: 'rt' },
    { container: '.bootstrap-filestyle', x: -(windowWidth+12), y:-10, width: windowWidth, arrow: 'rt' }
  ];
      
  function getStartButtons() {
    if ( langue == LANGUE.Français ) {
      return { Commencer: 1, Terminer: 2 };
    }
    return { Begin: 1, Exit: 2 };
  }
  
  function getContinueButtons() {
    if ( langue == LANGUE.Français ) {
      return { Précédent: -1, Suivant: 1 };
    }
    return { Previous: -1, Next: 1 };
  }
  
  function getEndButton() {
    if ( langue == LANGUE.Français ) {
      return { Fin: 2 };
    }
    return { Done: 2 };
  }
  
  var it = 0;
  var tourSubmitFunc = function(e,v,m,f){
			if(v === -1){
				$.prompt.prevState();
				return false;
			}
			else if(v === 1){
				$.prompt.nextState();
				return false;
			}
  },
  tourStates = [
    {
      title: chooseLang('Zoomer avant / arrière', 'Zooming in / out'),
      html: chooseLang('Ajuster votre niveau de zoom via ces boutons-ci.', 'Adjust your zoom level with these buttons.'),
      buttons: getContinueButtons(),
      focus: 1,
      position: positions[it++],
      submit: tourSubmitFunc
    },
    {
      title: chooseLang('Entamer un dessin','Beginning a drawing'),
      html: chooseLang('Cliquer sur le polygone pour passer en mode Dessin. Vous pourrez dorénavant dessiner une zone sur la carte représentant le quartier perçu.', 'Click on the polygon to enter draw mode. You will then be able to draw a zone on the map representing the perceived neighborhood.'),
      buttons: getContinueButtons(),
      focus: 1,
      position: positions[it++],
      submit: tourSubmitFunc
    },
    {
      title: chooseLang('Dessiner une zone','Drawing a zone'),
      html: chooseLang('Préciser un sommet de la zone en cliquant sur la carte. Répeter autant de fois que nécessaire, puis cliquer de nouveau sur le premier sommet pour terminer le dessin.', 'Mark a vertex of the zone by clicking on the map. Repeat as many times as is necessary to define the zone, then click on the first vertex to complete the drawing.'),
      buttons: getContinueButtons(),
      focus: 1,
      position: positions[it++],
      submit: tourSubmitFunc
    },
    {
      title: chooseLang('Éditer une zone', 'Editing a zone'),
      html: chooseLang("Cliquer ici pour passer en mode Édition. Déplacer les ancrages de la zone pour modifier sa forme, et ensuite cliquer sur le bouton gris 'Sauvegarder'.", "Click here to enter edit mode. Move the highlighted points of the zone to modify its shape, then click on the grey 'Save' button."),
      buttons: getContinueButtons(),
      focus: 1,
      position: positions[it++],
      submit: tourSubmitFunc
    },
    {
      title: chooseLang('Effacer une zone', 'Removing a zone'),
      html: chooseLang("Cliquer sur la poubelle pour passer en mode Éffacement. Ensuite, cliquer directement sur la zone pour l'effacer. Ne pas oublier de cliquer sur le bouton 'Sauvegarder' qui apparaîtra pour confirmer l'effacement.", "Click on this trash can to enter delete mode. Then, click directly on a zone to delete it. Lastly, remember to click on the 'Save' button that appears to confirm the deletion."),
      buttons: getContinueButtons(),
      focus: 1,
      position: positions[it++],
      submit: tourSubmitFunc
    },
    {
      title: chooseLang("Basculer le calque des arrondissements", "Toggling the neighborhoods overlay"),
      html: chooseLang("Basculer l'affichage du calque des arrondissements du Sud-Ouest à travers le menu qui appararait lorsque le pointeur de souris est positionné au dessus de cette icône.", "Hover the mouse cursor above this icon to bring up a menu in which you can toggle the display of the Sud-Ouest neighborhoods overlay."),
      buttons: getContinueButtons(),
      focus: 1,
      position: positions[it++],
      submit: tourSubmitFunc
    },
    {
      title: chooseLang("Voir l'étendue de la carte", 'Showing the map extent'),
      html: chooseLang("Cliquer ici pour zoomer arrière et voir tous les arrondissements du Sud-Ouest.", "Click here to zoom out and view the Sud-Ouest in its entirety."),
      buttons: getContinueButtons(),
      focus: 1,
      position: positions[it++],
      submit: tourSubmitFunc
    },
    {
      title: chooseLang('Nommer votre session', 'Naming your session'),
      html: chooseLang("Attribuer un nom à votre session. Par défaut, le nom de session consiste à la date et l'heure actuelle.",'Choose a name for your session. By default, the session name is the current date and time.'),
      buttons: getContinueButtons(),
      focus: 1,
      position: positions[it++],
      submit: tourSubmitFunc
    },
    {
      title: chooseLang('Sauvegarder votre session', 'Saving your session'),
      html: chooseLang("Cliquer ici pour sauvegarder votre dessin dans un fichier. Celui-ci portera le même nom que celui de la session et aura un suffix <i>.json</i>. À titre d'exemple, si vous sauvegardez une session nommée <b>abcd</b>, la session sera enregistrée dans le fichier <i>abcd.json</i>.", 'Click here to save your drawing to a file. This file will be given the same name as that of your session, and will have a <i>.json</i> extension. By way of example, if you save a session named <b>abcd</b>, the session will be saved to the file <i>abcd.json</i>.'),
      buttons: getContinueButtons(),
      focus: 1,
      position: positions[it++],
      submit: tourSubmitFunc
    },
      {
      title: chooseLang('Charger une session antérieure', 'Loading a previous session'),
      html: chooseLang("Cliquer ici pour faire apparaître une boîte de dialogue de fichiers. Sélectionner un fichier correspondant à une session précédente pour afficher sur la carte le dessin alors créé.", "Click here to bring up a file dialog box. Select the file of a previous session to display the drawing that was created during that session."),
      buttons: getEndButton(),
      focus: 0,
      position: positions[it++],
      submit: tourSubmitFunc
    },
  ];
  var tour = $.prompt(tourStates);
    tour.on('impromptu:loaded', function(e){
          $('button.jqidefaultbutton[id^="jqi_0"]').focus();
          $('.jqiclose').attr('title', chooseLang('Abandonner ce tour', 'Quit')).css('font-size','20px').css('top','0px').css('right', '5px').css('color', 'grey');
          $('.jqititle').css('margin', '2px');
  });
}

function setTransportMode(ind)
{
  currTransMode = getTransportMode(ind);
  drawControl.setDrawingOptions({polyline: { shapeOptions: { color: MODE_TRANSPORT[currTransMode].clr } }});
}

function getTransportMode(ind)
{
  for ( var i in MODE_TRANSPORT ) {
      if ( MODE_TRANSPORT[i].ind == ind)
        return i;
  }
  return null;
}