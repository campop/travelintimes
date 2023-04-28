<?php

ini_set ('display_errors', true);

# Get parameters and validate
$port = (isSet ($_GET['port']) && strlen ($_GET['port']) && ctype_digit ($_GET['port']) ? $_GET['port'] : false);
$lon = (isSet ($_GET['lon']) && strlen ($_GET['lon']) && preg_match ('/^[-.0-9]+$/D', $_GET['lon']) ? $_GET['lon'] : false);
$lat = (isSet ($_GET['lat']) && strlen ($_GET['lat']) && preg_match ('/^[-.0-9]+$/D', $_GET['lat']) ? $_GET['lat'] : false);

# End if invalid input
if (!$port || !$lon || !$lat) {
	http_response_code (400);
	echo "ERROR: Invalid input.";
	return false;
}

# Get the data via pipes, which is most secure
#	$geojson = createProcess ('./isochrone.R', "{$port} {$lon} {$lat}");
$geojson = createProcess ('NODE_PATH=../js/lib/ ./isochrone.node.js', "{$port} {$lon} {$lat}");

# Get the data via shell execution
#	$command = "./isochrone.R {$port} {$lon} {$lat}";
#	$command = "NODE_PATH=../js/lib/ ./isochrone.node.js {$port} {$lon} {$lat}";
#	$geojson = shell_exec ($command);

# Reformat
$geojson = json_encode (json_decode ($geojson, true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

# Send data
header ('Content-Type: application/json');
echo $geojson;





	# Function to handle running a command process securely without writing out any files
	# From: https://download.geog.cam.ac.uk/projects/application/application.php
	function createProcess ($command, $string)
	{
		# Set the descriptors
		$descriptorspec = array (
			0 => array ('pipe', 'r'),  // stdin is a pipe that the child will read from
			1 => array ('pipe', 'w'),  // stdout is a pipe that the child will write to
			// 2 => array ('file', '/tmp/error-output.txt', 'a'), // stderr is a file to write to - uncomment this line for debugging
		);
		
		# Assume failure unless the command works
		$returnStatus = 1;
		
		# Create the process
		$command = str_replace ("\r\n", "\n", $command);	// Standardise to Unix newlines
		$process = proc_open ($command, $descriptorspec, $pipes);
		if (is_resource ($process)) {
			fwrite ($pipes[0], $string);
			fclose ($pipes[0]);
			$output = stream_get_contents ($pipes[1]);
			fclose ($pipes[1]);
			$returnStatus = proc_close ($process);
		}
		
		# Return false as the output if the return status is a failure
#		if ($returnStatus) {return false;}	// Unix return status >0 is failure
		
		# Return the output
		return $output;
	}


?>

