// Historic journey planner application code
// Some code based on: https://github.com/cyclestreets/bikedata

/*jslint browser: true, white: true, single: true, for: true */
/*global alert, console, window, $, jQuery, L, autocomplete */

var travelintimes = (function ($) {
	
	'use strict';
	
	// Internal class properties
	var _map = null;
	
	// Settings
	var _settings = {
		
		// Default map view
		defaultLatitude: 53,
		defaultLongitude: -2,
		defaultZoom: 7,
		
		// Tileservers; historical map sources are listed at: https://wiki.openstreetmap.org/wiki/National_Library_of_Scotland
		tileUrls: {
			'os6inch': [
				'https://geo.nls.uk/maps/os/1inch_2nd_ed/{z}/{x}/{-y}.png',	// E.g. https://geo.nls.uk/maps/os/1inch_2nd_ed/12/2046/2745.png
				{maxZoom: 15, attribution: '&copy; <a href="https://maps.nls.uk/copyright.html">National Library of Scotland</a>', backgroundColour: '#f0f1e4', key: '/images/mapkeys/os6inch.jpg'},
				'NLS - OS 6-inch County Series 1888-1913'
			],
			'bartholomew': [
				'https://geo.nls.uk/mapdata2/bartholomew/great_britain/{z}/{x}/{-y}.png',	// E.g. https://geo.nls.uk/mapdata2/bartholomew/great_britain/12/2046/2745.png
				{maxZoom: 15, attribution: '&copy; <a href="https://maps.nls.uk/copyright.html">National Library of Scotland</a>', backgroundColour: '#a2c3ba'},
				'NLS - Bartholomew Half Inch, 1897-1907'
			],
			'mapnik': [
				'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',	// E.g. https://a.tile.openstreetmap.org/16/32752/21788.png
				{maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'},
				'OpenStreetMap style (modern)'
			],
			'osopendata': [
				'https://{s}.os.openstreetmap.org/sv/{z}/{x}/{y}.png',	// E.g. https://a.os.openstreetmap.org/sv/18/128676/81699.png
				{maxZoom: 19, attribution: 'Contains Ordnance Survey data &copy; Crown copyright and database right 2010'},
				'OS Open Data (modern)'
			]
		},
		
		// Geocoder
		geocoderApiBaseUrl: 'https://api.cyclestreets.net/v2/geocoder',
		geocoderApiKey: 'YOUR_API_KEY',		// Obtain at https://www.cyclestreets.net/api/apply/
		autocompleteBbox: '-6.6577,49.9370,1.7797,57.6924',
		
		// LRM Geocoder
		lrmGeocoderServiceUrl: '//nominatim.openstreetmap.org/',
		lrmGeocoderAutocomplete: false,		// NB: Do not enable on nominatim.openstreetmap.org - this is against the Usage policy for that server
		
		// Datasets, and the port they run on
		datasets: [
			{year: 1680, port: 5000},
			{year: 1830, port: 5001},
			{year: 1911, port: 5002}
		]
	};
	
	
	
	return {
		
		// Main function
		initialise: function (config)
		{
			// Obtain the configuration and add to settings
			$.each (config, function (key, value) {
				_settings[key] = value;
			});
			
			// Create the map
			travelintimes.createMap ();
			
			// Show first-run welcome message if the user is new to the site
			travelintimes.welcomeFirstRun ();
			
			// Add page handlers
			travelintimes.about ();
			travelintimes.acknowledgements ();
			travelintimes.travellerstales ();
			
			// Add the data to the map as switchable layers
			travelintimes.journeyplanner ();
		},
		
		
		// Function to create the map
		createMap: function ()
		{
			// Add the tile layers
			var tileLayers = [];		// Background tile layers
			var baseLayers = {};		// Labels, by name
			var baseLayersById = {};	// Layers, by id
			var mapKeys = {};		// Map keys, by name
			var layer;
			var name;
			$.each (_settings.tileUrls, function (tileLayerId, tileLayerAttributes) {
				layer = L.tileLayer(tileLayerAttributes[0], tileLayerAttributes[1]);
				tileLayers.push (layer);
				name = tileLayerAttributes[2];
				baseLayers[name] = layer;
				baseLayersById[tileLayerId] = layer;
				mapKeys[name] = tileLayerAttributes[1]['key'] || null;
			});
			
			// Create the map
			_map = L.map('map', {
				center: [_settings.defaultLatitude, _settings.defaultLongitude],
				zoom: _settings.defaultZoom,
				layers: tileLayers[0]	// Documentation suggests tileLayers is all that is needed, but that shows all together
			});
			
			// Set a class corresponding to the map tile layer, so that the background can be styled with CSS
			travelintimes.setMapBackgroundColour (tileLayers[0].options);
			_map.on('baselayerchange', function(e) {
				travelintimes.setMapBackgroundColour (baseLayers[e.name].options);
			});
			
			// Create the location overlay pane
			travelintimes.createPane ();
			
			// Map key control
			travelintimes.mapKey (mapKeys, tileLayers[0]);
			
			// Add scale
			L.control.scale({maxWidth: 300}).addTo(_map);
			
			// Add geocoder control
			travelintimes.geocoder ();
			
			// Add hash support
			new L.Hash (_map, baseLayersById);
			
			// Add geolocation control
			_map.addControl(L.control.locate({
				icon: 'fa fa-location-arrow',
				locateOptions: {maxZoom: 14}
			}));
			
			// Add the base (background) layer switcher
			L.control.layers(baseLayers, null, {position: 'bottomleft'}).addTo(_map);
		},
		
		
		// Function to set the map background colour for a layer
		setMapBackgroundColour: function (tileLayerOptions)
		{
			// Set, using jQuery, if specified, or clear
			var backgroundColour = (tileLayerOptions.backgroundColour ? tileLayerOptions.backgroundColour : '');
			$('.leaflet-container').css ('background-color', backgroundColour);
		},
		
		
		// Function to create a location overlay pane; see: http://leafletjs.com/examples/map-panes/
		createPane: function ()
		{
			// Create a pane
			_map.createPane('labels');
			_map.getPane('labels').style.zIndex = 650;
			_map.getPane('labels').style.pointerEvents = 'none';
			
			// Create a labels layer; see: https://carto.com/location-data-services/basemaps/
//			var locationLabels = L.tileLayer('http://tiles.oobrien.com/shine_labels_cdrc/{z}/{x}/{y}.png', {
			var locationLabels = L.tileLayer('https://cartodb-basemaps-{s}.global.ssl.fastly.net/light_only_labels/{z}/{x}/{y}.png', {
				attribution: '&copy; OpenStreetMap, &copy; CartoDB',
				pane: 'labels'
			})
			
			// Add to the map
			locationLabels.addTo(_map);
		},
		
		
		// Map key
		mapKey: function (mapKeys, defaultLayer)
		{
			// Add map key div
			$('#mapcontainer').append('<div id="mapkeylink"><p><a href="#">Map key</a></p></div>');
			
			// Default key
			var mapKey = defaultLayer.options.key;
			travelintimes.mapKeyControl (mapKey);
			
			// Detect changes
			_map.on('baselayerchange', function(e) {
				var selectedBasemap = e.name;
				mapKey = mapKeys[selectedBasemap];
				travelintimes.mapKeyControl (mapKey);
			});
		},
		
		
		// Map key control
		mapKeyControl: function (mapKey)
		{
			// Show or hide
			if (mapKey) {
				$('#mapkeylink').show();
				$('#mapkeylink p a').attr('href', mapKey);
				$('#mapkeylink p a').off();	// Remove any existing handler
				var html = '<img src="' + mapKey + '" />';
				travelintimes.dialogBox ('#mapkeylink p a', 'mapkey', html);
			} else {
				$('#mapkeylink').hide();
			}
		},
		
		
		// Wrapper function to add a geocoder control
		geocoder: function ()
		{
			// Attach the autocomplete library behaviour to the location control
			autocomplete.addTo ('#geocoder input', {
				sourceUrl: _settings.geocoderApiBaseUrl + '?key=' + _settings.geocoderApiKey + '&bounded=1&bbox=' + _settings.autocompleteBbox,
				select: function (event, ui) {
					var bbox = ui.item.feature.properties.bbox.split(',');
					_map.fitBounds([ [bbox[1], bbox[0]], [bbox[3], bbox[2]] ]);
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
		
		
		// Acknowlegements page
		acknowledgements: function ()
		{
			return travelintimes.pageHandler ('#menu li.acknowledgements', 'acknowledgements');
		},
		
		
		// Travellers' tales page
		travellerstales: function ()
		{
			return travelintimes.pageHandler ('#menu li.travellerstales', 'travellerstales');
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
		
		
		// Journey planner main entry point
		journeyplanner: function ()
		{
			// https://github.com/perliedman/leaflet-routing-machine/issues/236
			// http://www.liedman.net/leaflet-routing-machine/tutorials/interaction/
			// http://www.liedman.net/leaflet-routing-machine/tutorials/alternative-routers/
			// https://github.com/perliedman/leaflet-routing-machine/issues/200#issuecomment-175082024
			
			function button(label, container) {
				var btn = L.DomUtil.create('button', '', container);
				btn.setAttribute('type', 'button');
				btn.setAttribute('class', 'year ' + label);
				btn.innerHTML = label;
				return btn;
			}
			
			var geoPlan = L.Routing.Plan.extend({
				
				createGeocoders: function() {
					
					var container = L.Routing.Plan.prototype.createGeocoders.call(this);
					
					// Add each route, with a button
					var thisButton;
					$.each (_settings['datasets'], function (index, dataset) {
						thisButton = button(dataset.year, container);
						L.DomEvent.on(thisButton, 'click', function() {
							//console.log(control.getRouter().options);
							control.getRouter().options.serviceUrl = '/routing/' + dataset.port + '/route/v1';
							control.getRouter().options.useHints = false;
							control.route();
							control.setWaypoints(control.getWaypoints());
							//console.log(dataset.year + ' route');
							
							// Highlight the current button, clearing existing selection first (if any)
							$.each (_settings['datasets'], function (indexButton, datasetButton) {
								$('.leaflet-routing-geocoders button.' + datasetButton.year).removeClass ('selected');
							});
							$('.leaflet-routing-geocoders button.' + dataset.year).addClass ('selected');
							
						}, this);
					});
					
					return container;
				}
			});
			
			// Patch the Geocoder to add autocomplete if required
			if (_settings.lrmGeocoderAutocomplete) {
				L.Control.Geocoder.Nominatim.prototype.suggest = function(query, cb, context) {
					return L.Control.Geocoder.Nominatim.prototype.geocode(query, cb, context);
				}
			}
			
			var plan = new geoPlan(
				// #!# Need to convert this into a setting
				[
					L.latLng(52.20200, 0.10835),	// Faculty of History, Cambridge
					L.latLng(51.50795, -0.07842)	// Tower of London
				], {
				geocoder: L.Control.Geocoder.nominatim({
					// See: https://github.com/perliedman/leaflet-control-geocoder#lcontrolgeocodernominatim
					serviceUrl: _settings.lrmGeocoderServiceUrl,
					geocodingQueryParams: {
						// See: https://wiki.openstreetmap.org/wiki/Nominatim#Parameters
						viewboxlbrt: _settings.autocompleteBbox,
						bounded: 1
					}
				}),
				routeWhileDragging: true
			});
			
			var control = L.Routing.control({
				serviceUrl: '/routing/5000/route/v1',
				routeWhileDragging: true,
				plan: plan
			}).addTo(_map);
			
			$('.leaflet-routing-geocoders button.year.' + _settings['datasets'][0]['year']).addClass ('selected');
			
			// Set focus to search start box
			$('.leaflet-routing-geocoder:first-child input').focus();
		}
	}
	
} (jQuery));
