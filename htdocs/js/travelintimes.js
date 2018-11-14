// Historic journey planner
// Some code adapted from work by CycleStreets, GPL2

/*jslint browser: true, white: true, single: true, for: true */
/*global $, alert, console, window, mapboxgl, FULLTILT, routing */


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
				vectorTiles: 'mapbox://styles/mapbox/streets-v9',
				label: 'Streets (modern)',
			},
			"satellite": {
				vectorTiles: 'mapbox://styles/mapbox/satellite-v9',
				label: 'Satellite',
			},
		},
		
		// Mapbox API key
		mapboxApiKey: 'YOUR_MAPBOX_API_KEY',
		
		// Geocoder
		geocoderApiBaseUrl: 'https://api.cyclestreets.net/v2/geocoder',
		geocoderApiKey: 'YOUR_API_KEY',		// Obtain at https://www.cyclestreets.net/api/apply/
		autocompleteBbox: '-6.6577,49.9370,1.7797,57.6924',
		
		// Routing strategies, in order of appearance in the UI
		defaultStrategy: 'year1830',
		strategies: [
			{
				id: 'year1680',
				label: '1680',
				format: 'osrm',
				baseUrl: 'https://www.travelintimes.org/routing/5000',
				parameters: {},
				lineColour: 'silver'
			},
			{
				id: 'year1830',
				label: '1830',
				format: 'osrm',
				baseUrl: 'https://www.travelintimes.org/routing/5001',
				parameters: {},
				lineColour: 'peachpuff'
			},
			{
				id: 'year1911',
				label: '1911',
				format: 'osrm',
				baseUrl: 'https://www.travelintimes.org/routing/5002',
				parameters: {},
				lineColour: 'darkkhaki'
			},
			{
				id: 'year' + new Date().getFullYear().toString(),
				label: new Date().getFullYear().toString(),
				format: 'osrm',
				baseUrl: 'https://router.project-osrm.org',
				parameters: {},
				lineColour: 'thistle'
			}
		]
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
			};
			for (var i = 0; i < inputs.length; i++) {
				inputs[i].onclick = switchLayer;
			}
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
					_map.fitBounds([ [bbox[1], bbox[0]], [bbox[3], bbox[2]] ], {maxZoom: 13});
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
			// Define the journey planner module config
			var config = {
				cyclestreetsApiKey: _settings.geocoderApiKey,
				autocompleteBbox: _settings.autocompleteBbox,
				images: {
					start: '/js/lib/mobiledev/images/itinerarymarkers/start.png',
					waypoint: '/js/lib/mobiledev/images/itinerarymarkers/waypoint.png',
					finish: '/js/lib/mobiledev/images/itinerarymarkers/finish.png'
				},
				defaultStrategy: _settings.defaultStrategy,
				strategies: _settings.strategies
			};
			
			// Delegate to separate class
			routing.initialise (config, _map, false);
		}
	}
	
} (jQuery));
