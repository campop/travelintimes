'use strict';

// var L = require('leaflet');

var streets = L.tileLayer('https://api.mapbox.com/v4/mapbox.streets/{z}/{x}/{y}@2x.png?access_token=pk.eyJ1IjoibXNsZWUiLCJhIjoiclpiTWV5SSJ9.P_h8r37vD8jpIH1A6i1VRg', {
    attribution: '<a href="https://www.mapbox.com/about/maps">© Mapbox</a> <a href="http://openstreetmap.org/copyright">© OpenStreetMap</a> | <a href="http://mapbox.com/map-feedback/">Improve this map</a>'
  }),
  outdoors = L.tileLayer('https://api.mapbox.com/v4/mapbox.outdoors/{z}/{x}/{y}@2x.png?access_token=pk.eyJ1IjoibXNsZWUiLCJhIjoiclpiTWV5SSJ9.P_h8r37vD8jpIH1A6i1VRg', {
    attribution: '<a href="https://www.mapbox.com/about/maps">© Mapbox</a> <a href="http://openstreetmap.org/copyright">© OpenStreetMap</a> | <a href="http://mapbox.com/map-feedback/">Improve this map</a>'
  }),
  satellite = L.tileLayer('https://api.mapbox.com/v4/mapbox.streets-satellite/{z}/{x}/{y}@2x.png?access_token=pk.eyJ1IjoibXNsZWUiLCJhIjoiclpiTWV5SSJ9.P_h8r37vD8jpIH1A6i1VRg', {
    attribution: '<a href="https://www.mapbox.com/about/maps">© Mapbox</a> <a href="http://openstreetmap.org/copyright">© OpenStreetMap</a> | <a href="http://mapbox.com/map-feedback/">Improve this map</a>'
  }),
  osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="http://www.openstreetmap.org/copyright/en">OpenStreetMap</a> contributors'
  }),
  opencyclemap = L.tileLayer('https://tile.cyclestreets.net/opencyclemap/{z}/{x}/{y}.png', {
    attribution: '© <a href="http://www.openstreetmap.org/copyright/en">OpenStreetMap</a> contributors, <a href="http://www.opencyclemap.org/">OpenCycleMap</a>'
  }),

  /* See: http://wiki.openstreetmap.org/wiki/National_Library_of_Scotland and note that {-y} must be specified as {y} with the tms flag */
  bartholomew = L.tileLayer('http://geo.nls.uk/mapdata2/bartholomew/great_britain/{z}/{x}/{y}.png', {
    attribution: 'NLS - Bartholomew Half Inch, 1897-1907',
    tms: true	/* http://leafletjs.com/reference.html#tilelayer-tms */
  }),
  
  localtiles = L.tileLayer('/tiles/{z}/{x}/{y}.png', {
    attribution: '© <a href="http://www.openstreetmap.org/copyright/en">OpenStreetMap</a> contributors'
  }),

  small_components = L.tileLayer('http://tools.geofabrik.de/osmi/tiles/routing_i/{z}/{x}/{y}.png', {})

module.exports = {
  defaultState: {
    center: L.latLng(52.2050, 0.1190),
    zoom: 8,
    maxZoom: 13,
    waypoints: [],
    language: 'en',
    alternative: true,
    layer: localtiles
  },
  services: [{
    label: '1680 network',
    path: 'http://historicplanner.cyclestreets.net:5000/viaroute'
  },
  {
    label: '1830 network',
    path: 'http://historicplanner.cyclestreets.net:5001/viaroute'
  }],
  layer: [{
    'Local tiles': localtiles,
    'Bartholomew map': bartholomew,
    'Mapbox Streets': streets,
    'Mapbox Streets Satellite': satellite,
    'openstreetmap.org': osm,
    'OpenCycleMap': opencyclemap
  }],
  overlay: {
    'Small Components': small_components
  },
  baselayer: {
    one: localtiles,
    two: bartholomew,
    three: streets,
    four: satellite,
    five: osm,
    six: opencyclemap
  }
};
