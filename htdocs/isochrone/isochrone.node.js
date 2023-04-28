#!/usr/bin/env node

// Example usage:
// echo "5000 0.1276 51.5072" | NODE_PATH=../js/lib/ ./isochrone.node.js


// Set libraries path; see: https://gist.github.com/branneman/8048520#7-the-hack
//process.env.NODE_PATH = '../js/lib/'
//require('module').Module._initPaths ();


// Load libraries
const fs = require('fs');
const OSRM = require('@project-osrm/osrm');
const isochrone = require('isochrone');

// Get arguments from STDIN
let args = fs.readFileSync(0, 'utf-8');
args = args.split (' ')

//	// Get arguments from command line
//	const args = process.argv.slice(2);

// Validate arguments
if (args.length < 3) {
    console.log ('ERROR: You must supply port,lon,lat arguments.');
	return;
}

// Assign arguments
const port = parseInt (args[0]);
const lon = parseFloat (args[1]);
const lat = parseFloat (args[2]);

// Obtain isochrone
// https://www.npmjs.com/package/isochrone
const osrm = new OSRM ({ path: '../../enginedata/port' + port + '/merged.osrm' });
const startPoint = [lon, lat];
const options = {
	osrm,
	radius: 700,		// Longest possible distance - Land's End to Berwick-Upon-Tweed border is 686.45km
	cellSize: 15,
	intervals: [120, 240, 480, 720, 1440, 4320, 10080]
};
isochrone (startPoint, options).then (geojson => {
	
	// Reverse feature order, so that larger areas are first, so they appear underneath
	geojson.features = geojson.features.reverse();
	
	// Output the result
	console.log (JSON.stringify (geojson, null, "\t"));
});

// Output of console.log will be returned
