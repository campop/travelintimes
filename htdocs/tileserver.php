<?php

# Define settings
$settings = array (
	'mapTileUrls' => array (
		'bartholomew' => 'https://geo.nls.uk/mapdata2/bartholomew/great_britain/{z}/{x}/{y}.png',
		'osquarterinch' => 'https://geo.nls.uk/maps/os/quarter-inch-first-hills/{z}/{x}/{y}.png',
		'osopendata' => 'https://{s}.os.openstreetmap.org/sv/{z}/{x}/{y}.png',
		'mapnik' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
		'opencyclemap' => 'https://{s}.tile.cyclestreets.net/opencyclemap/{z}/{x}/{y}.png',		// CycleStreets has licensed this usage *only* for www.travelintimes.org
		'light_only_labels' => 'https://cartodb-basemaps-a.global.ssl.fastly.net/light_only_labels/{z}/{x}/{y}.png',
	),
	'fallbackMapTileUrl' => false,		// 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
);



# Get and validate tileset, or end
if (!$tileset = (isSet ($_GET['tileset']) && array_key_exists ($_GET['tileset'], $settings['mapTileUrls']) ? $_GET['tileset'] : false)) {
	http_response_code(404);
	return false;
}

# Get and validate path, or end
if (!isSet ($_GET['path']) || !preg_match ('|^/([0-9]{1,2})/([0-9]{1,4})/([0-9]+)\.png$|', $_GET['path'], $matches)) {
	http_response_code(404);
	return false;
}

# Substitute for upstream
$replacements = array (
	'{z}' => $matches[1],
	'{x}' => $matches[2],
	'{y}' => $matches[3],
	'{s}' => chr(rand(97, 99)),		// a-c
);
$url = strtr ($settings['mapTileUrls'][$tileset], $replacements);

# Determine local tile path
$localPath = './tiles/' . $tileset . strtr ('/{z}/{x}/{y}.png', $replacements);

# Retrieve the tile from upstream if it is does not exist in the cache
if (!file_exists ($localPath)) {
	# Attempt to get the URL
	$options = array ('http' => array ('user_agent' => 'Travel in Times tile proxy cache - www.travelintimes.org'));
	if (!$tile = @file_get_contents ($url, false, stream_context_create ($options))) {		// @ used as tile may not exist, causing 404, which is then handled in subsequent line
		$error = error_get_last ();
		if (!substr_count ($error['message'], '404 Not Found')) {
			error_log ($error['message']);
		}
	}
	
	# Log upstream retrieval (debug)
	//file_put_contents ('./tiles/log.txt', $url . "\n", FILE_APPEND);
	
	# Use fallback server if required
	if ($settings['fallbackMapTileUrl']) {
		if (!$tile) {
			$url = strtr ($settings['fallbackMapTileUrl'], $replacements);
			$tile = @file_get_contents ($url, false, stream_context_create ($options));
		}
	}
	
	# End if no tile
	if (!$tile) {
		http_response_code(404);
		return false;
	}
	
	# Cache the tile
	umask (0000);
	$directory = dirname ($localPath) . '/';
	if (!is_dir ($directory)) {
		mkdir ($directory, 0775, true);
	}
	file_put_contents ($localPath, $tile);
	chmod ($localPath, 0664);
}

# Read the tile from local cache
$tile = file_get_contents ($localPath);

# Serve the tile
header ('Content-Length: ' . strlen ($tile));
header ('Content-Type: image/png');
echo $tile;
return;

?>
