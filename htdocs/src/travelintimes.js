// Historic journey planner
// Some code adapted from work by CycleStreets Ltd, GPL3

/*jslint browser: true, white: true, single: true, for: true */
/*global $, alert, console, window, mapboxgl, FULLTILT, routing, geojsonExtent, Cookies */


var travelintimes = (function ($) {
	
	'use strict';
	
	
	// Settings
	var _settings = {
		
		// Initial lat/lon/zoom of map and tile layer
		defaultLocation: {
			latitude: 53,
			longitude: -2,
			zoom: 7
		},
		
		// Initial route
		initialRoute: [[0.123902, 52.202968], [-0.127669, 51.507318]],	// As array of lon,lat pairs, or false to disable
		
		// Tileservers; historical map sources are listed at: https://wiki.openstreetmap.org/wiki/National_Library_of_Scotland
		// Raster styles; see: https://www.mapbox.com/mapbox-gl-js/example/map-tiles/
		// NB If using only third-party sources, a Mapbox API key is not needed: see: https://github.com/mapbox/mapbox-gl-native/issues/2996#issuecomment-155483811
		defaultStyle: 'bartholomew',
		tileUrls: {
			'bartholomew': {
				tiles: 'https://geo.nls.uk/mapdata2/bartholomew/great_britain/{z}/{x}/{y}.png',	// E.g. https://geo.nls.uk/mapdata2/bartholomew/great_britain/12/2021/1353.png
				maxZoom: 15,
				attribution: '&copy; <a href="https://maps.nls.uk/copyright.html">National Library of Scotland</a>',
				backgroundColour: '#a2c3ba',
				tileSize: 256,
				label: 'NLS - Bartholomew Half Inch, 1897-1907'
			},
			'os1inch': {
				tiles: 'https://geo.nls.uk/maps/os/1inch_2nd_ed/{z}/{x}/{y}.png',	// E.g. https://geo.nls.uk/maps/os/1inch_2nd_ed/15/16395/10793.png
				maxZoom: 15,
				attribution: '&copy; <a href="https://maps.nls.uk/copyright.html">National Library of Scotland</a>',
				backgroundColour: '#f0f1e4',
				key: '/images/mapkeys/os1inch.jpg',
				tileSize: 256,
				label: 'NLS - OS One Inch, 1885-1900'
			},
			'osopendata': {
				tiles: 'https://{s}.os.openstreetmap.org/sv/{z}/{x}/{y}.png',	// E.g. https://a.os.openstreetmap.org/sv/18/128676/81699.png
				maxZoom: 19,
				attribution: 'Contains Ordnance Survey data &copy; Crown copyright and database right 2010',
				tileSize: 256,
				label: 'OS Open Data (modern)'
			},
			'mapnik': {
				tiles: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',	// E.g. https://a.tile.openstreetmap.org/16/32752/21788.png
				maxZoom: 19,
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
				tileSize: 256,
				label: 'OpenStreetMap style (modern)'
			},
			"opencyclemap": {
				tiles: 'https://{s}.tile.cyclestreets.net/opencyclemap/{z}/{x}/{y}.png',
				maxZoom: 22,
				attribution: 'Maps © <a href="https://www.thunderforest.com/">Thunderforest</a>, Data © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
				tileSize: 256,
				label: 'OpenCycleMap (modern)'
			},
			"streets": {
				vectorTiles: 'mapbox://styles/mapbox/streets-v11',
				label: 'Streets (modern)',
			},
			"satellite": {
				vectorTiles: 'mapbox://styles/mapbox/satellite-v9',
				label: 'Satellite',
			},
		},
		
		// Max zoom
		maxZoomToSegment: 14,
		
		// Mapbox API key
		mapboxApiKey: 'YOUR_MAPBOX_API_KEY',
		
		// Geocoder
		geocoderApiBaseUrl: 'https://api.cyclestreets.net/v2/geocoder',
		geocoderApiKey: 'YOUR_API_KEY',		// Obtain at https://www.cyclestreets.net/api/apply/
		autocompleteBbox: '-6.6577,49.9370,1.7797,57.6924',
		
		// Routing strategies, in order of appearance in the UI
		defaultStrategy: 'year1830',
		multiplexedStrategies: false,
		strategies: [
			{
				id: 'roman',
				label: 'Roman',
				implementation: 'osrm',
				baseUrl: '/routing/5000/route/v1/driving',
				parameters: {},
				lineColour: '#505160',
				gpx: false,
				attribution: 'Routing by Campop',
				isochroneUrl: '/isochrone/?port=5000'
			},
			{
				id: 'year1680',
				label: '1680',
				implementation: 'osrm',
				baseUrl: '/routing/5001/route/v1/driving',
				parameters: {},
				lineColour: 'green',
				gpx: false,
				attribution: 'Routing by Campop',
				isochroneUrl: '/isochrone/?port=5001'
			},
			{
				id: 'year1830',
				label: '1830',
				implementation: 'osrm',
				baseUrl: '/routing/5002/route/v1/driving',
				parameters: {},
				lineColour: 'yellow',
				gpx: false,
				attribution: 'Routing by Campop',
				isochroneUrl: '/isochrone/?port=5002'
			},
			{
				id: 'year1911',
				label: '1911',
				implementation: 'osrm',
				baseUrl: '/routing/5003/route/v1/driving',
				parameters: {},
				lineColour: 'orange',
				gpx: false,
				attribution: 'Routing by Campop',
				isochroneUrl: '/isochrone/?port=5003'
			},
			{
				id: 'year' + new Date().getFullYear().toString(),
				label: new Date().getFullYear().toString(),
				implementation: 'osrm',
				baseUrl: 'https://api.mapbox.com/directions/v5/mapbox/driving',
				parameters: {access_token: '%mapboxApiKey'},
				lineColour: 'brown',
				gpx: false,
				attribution: new Date().getFullYear().toString() + ' routing using OpenStreetMap data',
				isochroneUrl: false
			}
		],
		
		// Travellable hours per day
		travellableHoursPerDay: 8,
		
		// Isochrone times, in minutes
		isochrones: {
			'#bbb': 120,		// 1/4 day
			'#777': 240,		// 1/2 day
			'maroon': 480,		// 1 day
			'red': 720,			// 1.5 days
			'orange': 1440,		// 3 day
			'green': 4320,		// 9 days
			'aqua': 10080		// 21 days
		}
	};
	
	// Internal class properties
	var _map = null;
	var _styles = {};
	
	
	return {
		
		// Main function
		initialise: function (config)
		{
			// Obtain the configuration and add to settings
			$.each (config, function (key, value) {
				_settings[key] = value;
			});
			
			// Load styles
			travelintimes.getStyles ();
			
			// Create the map
			travelintimes.createMap ();
			
			// Add a geolocation control
			travelintimes.geolocation ();
			
			// Add layer switching
			travelintimes.layerSwitcher ();
			
			// Add placenames overlay
			travelintimes.placenamesOverlay ();
			
			// Add move-to control
			travelintimes.addMoveToControl ();
			
			// Show first-run welcome message if the user is new to the site
			travelintimes.welcomeFirstRun ();
			
			// Add page handlers
			travelintimes.about ();
			travelintimes.videos ();
			travelintimes.travellerstales ();
			travelintimes.acknowledgements ();
			
			// Add routing
			travelintimes.routing ();
			
			// Add isochrones
			travelintimes.isochrones ();
		},
		
		
		// Create map; see: https://www.mapbox.com/mapbox-gl-js/example/simple-map/
		createMap: function ()
		{
			// Create map, specifying the access token
			mapboxgl.accessToken = _settings.mapboxApiKey;
			_map = new mapboxgl.Map ({
				container: 'map',
				style: _styles[_settings.defaultStyle],
				center: [_settings.defaultLocation.longitude, _settings.defaultLocation.latitude],
				zoom: _settings.defaultLocation.zoom,
				maxZoom: _settings.maxZoom,
				hash: true
			});
			
			// Set a class corresponding to the map tile layer, so that the background can be styled with CSS
			travelintimes.setMapBackgroundColour (_settings.tileUrls[_settings.defaultStyle]);
			
			// Enable zoom in/out buttons
			_map.addControl (new mapboxgl.NavigationControl ());
			
			// Add scale; see: https://stackoverflow.com/a/42510295/180733
			_map.addControl (new mapboxgl.ScaleControl ());
		},
		
		
		// Define styles
		getStyles: function ()
		{
			// Register each tileset
			$.each (_settings.tileUrls, function (tileLayerId, tileLayerAttributes) {
				
				// Vector tiles
				if (tileLayerAttributes.vectorTiles) {
					_styles[tileLayerId] = tileLayerAttributes.vectorTiles;
					
				// Traditional bitmap tiles
				} else {
					
					// Convert {s} server to a,b,c if present
					if (tileLayerAttributes.tiles.indexOf('{s}') != -1) {
						tileLayerAttributes.tiles = [
							tileLayerAttributes.tiles.replace ('{s}', 'a'),
							tileLayerAttributes.tiles.replace ('{s}', 'b'),
							tileLayerAttributes.tiles.replace ('{s}', 'c')
						]
					}
					
					// Convert string (without {s}) to array
					if (typeof tileLayerAttributes.tiles === 'string') {
						tileLayerAttributes.tiles = [
							tileLayerAttributes.tiles
						]
					}
					
					// Register the definition
					_styles[tileLayerId] = {
						"version": 8,
						"sources": {
							"raster-tiles": {
								"type": "raster",
								"tiles": tileLayerAttributes.tiles,
								"tileSize": (tileLayerAttributes.tileSize ? tileLayerAttributes.tileSize : 256),	// NB Mapbox GL default is 512
								"attribution": tileLayerAttributes.attribution
							}
						},
						"layers": [{
							"id": "simple-tiles",
							"type": "raster",
							"source": "raster-tiles",
							"minzoom": 0,
							// #!# Something is causing maxzoom not to be respected
							"maxzoom": (tileLayerAttributes.maxZoom ? tileLayerAttributes.maxZoom : 22)
						}]
					};
				}
			});
		},
		
		
		// Function to add a geolocation control
		// https://www.mapbox.com/mapbox-gl-js/example/locate-user/
		// https://github.com/mapbox/mapbox-gl-js/issues/5464
		geolocation: function ()
		{
			// Create a tracking control
			var geolocate = new mapboxgl.GeolocateControl({
				positionOptions: {
					enableHighAccuracy: true
				},
				trackUserLocation: true
			});
			
			// Add to the map
			_map.addControl (geolocate);
		},
		
		
		// Function to add layer switching
		// https://www.mapbox.com/mapbox-gl-js/example/setstyle/
		// https://bl.ocks.org/ryanbaumann/7f9a353d0a1ae898ce4e30f336200483/96bea34be408290c161589dcebe26e8ccfa132d7
		// Would be nice to use https://github.com/el/style-switcher but this is ES6, and lacks hover and event firing support
		layerSwitcher: function ()
		{
			// Add layer switcher UI
			var control = this.createControl ('layerswitcher', 'bottom-left');
			
			// Construct HTML for layer switcher
			var layerSwitcherHtml = '<ul>';
			var name;
			$.each (_styles, function (styleId, style) {
				name = (_settings.tileUrls[styleId].label ? _settings.tileUrls[styleId].label : travelintimes.ucfirst (styleId));
				layerSwitcherHtml += '<li><input id="' + styleId + '" type="radio" name="layerswitcher" value="' + styleId + '"' + (styleId == _settings.defaultStyle ? ' checked="checked"' : '') + '><label for="' + styleId + '"> ' + name + '</label></li>';
			});
			layerSwitcherHtml += '</ul>';
			$('#layerswitcher').append (layerSwitcherHtml);
			
			// Switch to selected layer
			var layerList = document.getElementById ('layerswitcher');
			var inputs = layerList.getElementsByTagName ('input');
			function switchLayer (layer) {
				var layerId = layer.target.id;
				var style = _styles[layerId];
				_map.setStyle (style);
				
				// Set the background colour if required
				travelintimes.setMapBackgroundColour (_settings.tileUrls[layerId]);
				
				// Fire an event; see: https://javascript.info/dispatch-events
				travelintimes.styleChanged ();
			};
			for (var i = 0; i < inputs.length; i++) {
				inputs[i].onclick = switchLayer;
			}
		},
		
		
		// Function to trigger style changed, checking whether it is actually loading; see: https://stackoverflow.com/a/47313389/180733
		// Cannot use _map.on(style.load) directly, as that does not fire when loading a raster after another raster: https://github.com/mapbox/mapbox-gl-js/issues/7579 and https://github.com/mapbox/mapbox-gl-js/issues/7579
		styleChanged: function ()
		{
			// Delay for 250ms in a loop until the style is loaded; see: https://stackoverflow.com/a/47313389/180733
			if (!_map.isStyleLoaded()) {
				setTimeout (function () {
					travelintimes.styleChanged ();	// Done inside a function to avoid "Maximum Call Stack Size Exceeded"
				}, 250);
				return;
			}
			
			// Fire a custom event that client code can pick up when the style is changed
			var body = document.getElementsByTagName ('body')[0];
			var myEvent = new Event ('style-changed', {'bubbles': true});
			body.dispatchEvent (myEvent);
		},
		
		
		// Function to make first character upper-case; see: https://stackoverflow.com/a/1026087/180733
		ucfirst: function (string)
		{
			if (typeof string !== 'string') {return string;}
			return string.charAt(0).toUpperCase() + string.slice(1);
		},
		
		
		// Function to create a control in a corner
		// See: https://www.mapbox.com/mapbox-gl-js/api/#icontrol
		createControl: function (id, position)
		{
			function newControl () { }
			
			newControl.prototype.onAdd = function(_map) {
				this._map = map;
				this._container = document.createElement('div');
				this._container.setAttribute ('id', id);
				this._container.className = 'mapboxgl-ctrl-group mapboxgl-ctrl local';
				return this._container;
			};
			
			newControl.prototype.onRemove = function () {
				this._container.parentNode.removeChild(this._container);
				this._map = undefined;
			};
			
			// #!# Need to add icon and hover; partial example at: https://github.com/schulzsebastian/mapboxgl-legend/blob/master/index.js
			
			// Instiantiate and add the control
			_map.addControl (new newControl (), position);
		},
		
		
		// Move-to control
		addMoveToControl: function ()
		{
			travelintimes.geocoder ('#geocoder input', false);
		},
		
		
		// Function to add a geocoder control
		geocoder: function (addTo, callbackFunction)
		{
			// Geocoder URL; re-use of settings values is supported, represented as placeholders {%cyclestreetsApiBaseUrl}, {%cyclestreetsApiKey}, {%autocompleteBbox}
			var geocoderApiUrl = travelintimes.settingsPlaceholderSubstitution (_settings.geocoderApiUrl, ['cyclestreetsApiBaseUrl', 'cyclestreetsApiKey', 'autocompleteBbox']);
			
			// Attach the autocomplete library behaviour to the location control
			autocomplete.addTo (addTo, {
				sourceUrl: geocoderApiUrl,
				select: function (event, ui) {
					var bbox = ui.item.feature.properties.bbox.split(',');
					_map.setMaxZoom (18);	// Prevent excessive zoom to give context
					_map.fitBounds([ [bbox[0], bbox[1]], [bbox[2], bbox[3]] ]);	// Note that Mapbox GL JS uses sw,ne rather than ws,en as in Leaflet.js
					_map.setMaxZoom (_settings.maxZoom);	// Reset
					if (callbackFunction) {
						callbackFunction (ui.item);
					}
					event.preventDefault();
				}
			});
		},
		
		
		// Helper function to implement settings placeholder substitution in a string
		settingsPlaceholderSubstitution: function (string, supportedPlaceholders)
		{
			// Substitute each placeholder
			var placeholder;
			$.each(supportedPlaceholders, function (index, field) {
				placeholder = '{%' + field + '}';
				string = string.replace(placeholder, _settings[field]);
			});
			
			// Return the modified string
			return string;
		},
		
		
		// Function to set the map background colour for a layer
		setMapBackgroundColour: function (tileLayerOptions)
		{
			// Set, using jQuery, if specified, or clear
			var backgroundColour = (tileLayerOptions.backgroundColour ? tileLayerOptions.backgroundColour : '');
			$('.mapboxgl-map').css ('background-color', backgroundColour);
		},
		
		
		// Function to create a location overlay pane; see: https://www.mapbox.com/help/how-web-apps-work/#adding-layers-to-the-map
		placenamesOverlay: function ()
		{
			// Register the definition
			var locationLabelsLayer = {
				"id": "locationlabels",
				"type": "raster",
				"source": {
					"type": "raster",
					"tiles": [
						'https://cartodb-basemaps-a.global.ssl.fastly.net/light_only_labels/{z}/{x}/{y}.png',
						'https://cartodb-basemaps-b.global.ssl.fastly.net/light_only_labels/{z}/{x}/{y}.png',
						'https://cartodb-basemaps-c.global.ssl.fastly.net/light_only_labels/{z}/{x}/{y}.png',
					],
					"tileSize": 256,	// NB Mapbox GL default is 512
					"attribution": '&copy; OpenStreetMap, &copy; CartoDB'
				}
			};
			
			// Add to the map
			_map.on ('load', function () {
				_map.addLayer (locationLabelsLayer);
			});
		},
		
		
		// Wrapper function to add a geocoder control
		geocoder: function ()
		{
			// Attach the autocomplete library behaviour to the location control
			autocomplete.addTo ('#geocoder input', {
				sourceUrl: _settings.geocoderApiBaseUrl + '?key=' + _settings.geocoderApiKey + '&bounded=1&bbox=' + _settings.autocompleteBbox,
				select: function (event, ui) {
					var bbox = ui.item.feature.properties.bbox.split(',');
					_map.fitBounds([ [bbox[0], bbox[1]], [bbox[2], bbox[3]] ], {maxZoom: 13});	// Note that Mapbox GL JS uses sw,ne rather than ws,en as in Leaflet.js
					event.preventDefault();
				}
			});
		},
		
		
		// Function to show a welcome message on first run
		welcomeFirstRun: function ()
		{
			// End if cookie already set
			var name = 'welcome';
			if (Cookies.get(name)) {return;}
			
			// Set the cookie
			Cookies.set(name, '1', {expires: 14});
			
			// Define a welcome message
			var message =
			   '<p><strong>Welcome to Travel in times, from CAMPOP.</strong></p>'
			 + '<p>Travelintimes.org is a historic online journey planner, created at the University of Cambridge, that allows users to plan journeys around England and Wales at three dates in time, c.1680, c.1830 and in 1911.</p>'
			 + '<p>The website allows users to explore the nature of travel in the past and how it improved over the period 1670 to 1911, based on the latest historical research.</p>'
			 + '<p>Please note that various improvements are still being made to the site.</p>';
			
			// Show the dialog
			vex.dialog.alert ({unsafeMessage: message});
		},
		
		
		// About page
		about: function ()
		{
			return travelintimes.pageHandler ('#menu li.about', 'about');
		},
		
		
		// About page
		videos: function ()
		{
			return travelintimes.pageHandler ('#menu li.videos', 'videos');
		},
		
		
		// Travellers' tales page
		travellerstales: function ()
		{
			return travelintimes.pageHandler ('#menu li.travellerstales', 'travellerstales');
		},
		
		
		// Acknowlegements page
		acknowledgements: function ()
		{
			return travelintimes.pageHandler ('#menu li.acknowledgements', 'acknowledgements');
		},
		
		
		// Page handler
		pageHandler: function (triggerElement, name)
		{
			// Obtain the HTML
			var html = $('#' + name).html();
			
			// Create the dialog box
			travelintimes.dialogBox (triggerElement, name, html);
		},
		
		
		// Dialog box
		dialogBox: function (triggerElement, name, html)
		{
			$(triggerElement).click (function (e) {
				html = '<div id="' + name + 'box">' + html + '</div>';
				vex.dialog.buttons.YES.text = 'Close';
				vex.dialog.alert ({unsafeMessage: html, showCloseButton: true, className: 'vex vex-theme-plain wider'});
				e.preventDefault ();
			});
		},
		
		
		// Routing
		routing: function ()
		{
			// Replace token for modern routing
			$.each (_settings.strategies, function (index, strategy) {
				if (strategy.parameters.hasOwnProperty ('access_token')) {
					if (strategy.parameters.access_token.indexOf ('%mapboxApiKey') !== -1) {
						_settings.strategies[index].parameters.access_token = strategy.parameters.access_token.replace ('%mapboxApiKey', _settings.mapboxApiKey);
					}
				}
			});
			
			// Define the journey planner module config
			var routingUiConfig = {
				title: 'Travel in times - Historic journey planner',
				apiKey: _settings.geocoderApiKey,
				autocompleteBbox: _settings.autocompleteBbox,
				images: {
					
					// Waypoints
					start: '/js/lib/routing-ui/images/itinerarymarkers/start.png',
					waypoint: '/js/lib/routing-ui/images/itinerarymarkers/waypoint.png',
					finish: '/js/lib/routing-ui/images/itinerarymarkers/finish.png',
					
					// Results tabs panel icons
					distance: '/js/lib/routing-ui/images/resultscontainer/icon-cyclist.svg',
					time: '/js/lib/routing-ui/images/resultscontainer/icon-clock.svg',
					calories: '/js/lib/routing-ui/images/resultscontainer/icon-flame.svg',
					co2: '/js/lib/routing-ui/images/resultscontainer/icon-leaf.svg',
					gpx: '/js/lib/routing-ui/images/resultscontainer/icon-jp-red.svg'
				},
				initialRoute: _settings.initialRoute,
				defaultStrategy: _settings.defaultStrategy,
				multiplexedStrategies: _settings.multiplexedStrategies,
				strategies: _settings.strategies,
				showAllRoutes: false,
				maxZoomToSegment: _settings.maxZoomToSegment,
				createPlanningControls: true,
				showToolBox: false,
				travellableHoursPerDay: _settings.travellableHoursPerDay
			};
			
			// Delegate to separate class
			routing.initialise (routingUiConfig, _map, false, false);
		},
		
		
		// Function to add isochrone display; see: https://github.com/urbica/galton and demo at https://galton.urbica.co/
		isochrones: function ()
		{
			// Add layer switcher UI
			var control = this.createControl ('isochrones', 'bottom-left');
			
			// Define hours per day
			var hoursPerDay = 8;
			
			// Create a legend for the isochrones UI control
			var labelsRows = [];
			$.each (_settings.isochrones, function (colour, time) {
				labelsRows.push ('<tr><td>' + '<i style="background-color: ' + colour + ';"></i>' + '</td><td>' + ((time / (60)) / hoursPerDay).toFixed(2).replace(/\.00$/, '').replace(/\.50$/, '.5') + ' ' + hoursPerDay + '-hour days</td></tr>');
			});
			var legendHtml = '<table>' + labelsRows.join ('\n') + '</table>';
			legendHtml = '<div class="legend">' + legendHtml + '</div>';
			
			// Create a button
			var buttonHtml = '<button>Create isochrone from start point</button>';
			
			// Construct HTML for the isochrones UI control
			var isochronesHtml  = '<h2>Travel time isochrones (experimental)</h2>';
			isochronesHtml += '<div id="planning">' + buttonHtml + '</div>';
			isochronesHtml += '<p id="clear"><a href="#">Clear</a></p>'
			isochronesHtml += legendHtml;
			$('#isochrones').append (isochronesHtml);
			
			// Load route indexes
			var strategiesIndexes = travelintimes.loadRouteIndexes ();
			
			// Set waypoint variable for tracking
			var startPoint = false;
			
			// Define function to remove isochrone layer
			var layerName = 'isochrone';
			var removeIsochroneLayer = function () {
				var mapLayer = _map.getLayer (layerName);
				if (typeof mapLayer !== 'undefined') {
					_map.removeLayer (layerName).removeSource (layerName);
				}
				$('#isochrones p#clear').hide ();
			}
			
			// Add isochrone on map click
			$('#isochrones #planning').on ('click', 'button', function () {		// Late-binding, as the button may have been reinstated after being taken out the DOM
				
				// Get the currently-selected strategy from the routing module
				var selectedStrategy = routing.getSelectedStrategy ();
				var selectedStrategyIndex = strategiesIndexes[selectedStrategy];
				
				// Get the start point, or end
				var waypoints = routing.getWaypoints ();
				if (!waypoints.hasOwnProperty (0)) {
					alert ('No start point has been set.');
					return;
				}
				startPoint = waypoints[0];
				
				// Construct the URL
				var url = _settings.strategies[selectedStrategyIndex].isochroneUrl + '&lon=' + startPoint.lng + '&lat=' + startPoint.lat;
				
				// Show loading indicator
				var loadingIndicator = '<p class="loading"><img src="/images/ui-anim_basic_16x16.gif" /> Loading &hellip;<br />(Takes 5-10 secs)</p>';
				$('#isochrones #planning').html (loadingIndicator);
				
				// Load over AJAX; see: https://stackoverflow.com/a/48655332/180733
				$.ajax ({
					dataType: 'json',
					url: url,
					error: function (jqXHR, textStatus, errorThrown) {
						alert ('Sorry, the isochrone for ' + _settings.strategies[selectedStrategyIndex].label + ' could not be loaded: ' + textStatus);
						console.log (errorThrown);
					},
					success: function (geojson) {
						
						// Remove layer if already present
						removeIsochroneLayer ();
						
						// Define the fill-colour definition, adding the colours for each isochrone definition
						var fillColour = [
							'match',
							['get', 'isomin']
						];
						$.each (_settings.isochrones, function (colour, time) {
							fillColour.push (time, colour);		// E.g.: [..., 1200, 'red', 3600, 'orange', ...]
						});
						fillColour.push (/* other */ 'gray');
						
						// Add the map layer
						_map.addLayer ({
							'id': layerName,
							'type': 'fill',
							'source': {
								'type': 'geojson',
								'data': geojson
							},
							'layout': {},
							'paint': {
								// Data-driven styling: https://docs.mapbox.com/mapbox-gl-js/example/data-driven-circle-colors/ and https://docs.mapbox.com/mapbox-gl-js/style-spec/#expressions-match
								'fill-color': fillColour,
								'fill-opacity': 0.5
							}
						});
						
						// Show the clearance button
						$('#isochrones p#clear').show ();
						
						// Remove the loading indicator and reinstate the button
						$('#isochrones #planning').html (buttonHtml);
						
						// Zoom out the map
						var bounds = geojsonExtent (geojson);
						_map.fitBounds (bounds, {padding: 20});
						
						// Enable popups; see: https://stackoverflow.com/questions/45841086/show-popup-on-hover-mapbox
						var popup = new mapboxgl.Popup({
							closeButton: false
						});
						_map.on ('mousemove', layerName, function (e) {
							_map.getCanvas().style.cursor = 'pointer';
							var feature = e.features[0];
							popup.setLngLat (e.lngLat)
								.setHTML ('<p><strong>' + _settings.strategies[selectedStrategyIndex].label + '</strong>: It would have taken<br /><strong>' + (feature.properties.isomin / (60 * hoursPerDay)).toFixed(2).replace(/\.00$/, '').replace(/\.50$/, '.5') + ' - ' + (feature.properties.isomax / (60 * hoursPerDay)).toFixed(2).replace(/\.00$/, '').replace(/\.50$/, '.5') + ' &nbsp;' + hoursPerDay + '-hour days</strong><br />to get to locations in this area, from the start point.</p>')
								.addTo (_map);
						});
						_map.on ('mouseleave', layerName, function (e) {
							_map.getCanvas().style.cursor = '';
							popup.remove ();
						});
					}
				});
			});
			
			
			// Periodically, check for marker clearance/moves
			// #!# This should be changed to looking at a state model, or as a callback from routing-ui
			setInterval (function () {
				
				// Remove the isochrone layer if there are no waypoints or the startpoint has changed
				var waypoints = routing.getWaypoints ();
				if (!waypoints.hasOwnProperty (0) || waypoints[0] != startPoint) {
					removeIsochroneLayer ();
					return;
				}
				
			}, 1000);
			
			// Remove isochrone on strategy selection tab change
			$('#map').on ('click', 'ul#strategies li', function (e) {		// Late-binding, as the UI is created dynamically after load
				removeIsochroneLayer ();
			});
			
			// Remove isochrone explicitly on clear
			$('#isochrones p#clear').click (function (e) {
				removeIsochroneLayer ();
				e.preventDefault ();
			});
		},
		
		
		// Function to create an index of strategies
		loadRouteIndexes: function ()
		{
			// Map from strategyId => index
			var strategiesIndexes = {};
			$.each (_settings.strategies, function (index, strategy) {
				strategiesIndexes[strategy.id] = index;
			});
			
			// Return the indexes
			return strategiesIndexes;
		}
	}
	
} (jQuery));
