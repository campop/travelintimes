<?php

# Class to create turn penalty data as a CSV
# See: https://github.com/Project-OSRM/osrm-backend/wiki/Traffic#turn-penalty-data

# Usage example:
#  php ./buildTurnsData.php /opt/travelintimes/enginedata/multimodal1680/20161201-124000/


# Ensure a directory has been specified
if (!isSet ($argv[1])) {
	echo "ERROR: You must supply a directory argument, to specify where the .osm file is and where the data should be written.\n";
	exit;
}

# Run the class
new createTurnPenalties ($argv[1], $argv[2]);

# Class definition
class createTurnPenalties
{
	# Constructor
	public function __construct ($directory, $turnPenaltiesDefinitionFile)
	{
		# Determine the input and output files
		$inputFile = $directory . '/merged.osm';
		$outputFile = $directory . '/penalties.csv';
		
		# Ensure the directory is writable
		if (!is_writable ($directory)) {
			echo 'ERROR: ' . "The directory {$directory} is not writable." . "\n";
			return false;
		}
		
		# Ensure the turn penalties definition file is readable
		if (!is_readable ($turnPenaltiesDefinitionFile)) {
			echo 'ERROR: ' . "The turn penalties definition file {$turnPenaltiesDefinitionFile} does not exist or is not readable." . "\n";
			return false;
		}
		
		# Parse the turn penalties definition
		$turnPenaltyTypes = $this->parseTurnPenaltiesDefinition ($turnPenaltiesDefinitionFile);
		
		# Parse the input file and create the output file
		if (!$combinations = $this->main ($inputFile, $outputFile, $turnPenaltyTypes, $error)) {
			echo 'ERROR: ' . $error . "\n";
			return false;
		}
		
		# Convert the data to CSV
		$csv = $this->convertToCsv ($combinations);
		
		# Write the file
		file_put_contents ($outputFile, $csv);
	}
	
	
	# Function to parse the turn penalties definition
	/* Example file would contain CSV like:
		residential,secondary,10000
		secondary,residential,2300
		residential,default,20000
		default,residential,19900
		secondary,default,2000
		default,secondary,2000
	*/
	
	private function parseTurnPenaltiesDefinition ($filename)
	{
		# Get the file
		$string = file_get_contents ($filename);
		
		# Trim block and convert to lines
		$lines = explode ("\n", str_replace ("\r\n", "\n", trim ($string)));
		
		# Convert to key/value pairs
		$turnPenalties = array ();
		foreach ($lines as $line) {
			list ($fromType, $toType, $penaltySeconds) = explode (',', trim ($line));
			$combinationId = $fromType . ',' . $toType;
			$turnPenaltyTypes[$combinationId] = $penaltySeconds;
		}
		
		# Return the list
		return $turnPenaltyTypes;
	}
	
	
	# Main function
	private function main ($inputFile, $outputFile, $turnPenaltyTypes, &$error = '')
	{
		# Ensure the file exists and is readable
		if (!is_readable ($inputFile)) {
			$error = "Cannot read input file {$inputFile}";
			return false;
		}
		
		# Load the input file
		if (!$osm = simplexml_load_file ($inputFile)) {
			$error = "Invalid XML in file {$inputFile}";
			return false;
		}
		
		# Narrate
		//echo "Successfully parsed XML in {$inputFile}\n";
		
		/* The .osm file format is a fairly simple XML format consisting of a set of nodes and ways which reference those nodes:

<?xml version='1.0' encoding='UTF-8'?>
<osm version="0.6" generator="Osmosis 0.45">
	
	<node id="-2" version="1" timestamp="2016-12-01T13:01:43Z" lat="52.1166072" lon="-0.4686726"/>
	<node id="-3" version="1" timestamp="2016-12-01T13:01:43Z" lat="52.1165977" lon="-0.4689011"/>
	[...]
	<node id="-1123" version="1" timestamp="2016-12-01T13:01:43Z" lat="52.1048082" lon="-0.3858589"/>
	
	<way id="-1" version="1" timestamp="2016-12-01T13:01:43Z">
		<nd ref="-2"/>
		<nd ref="-3"/>
		<nd ref="-5"/>
		<tag k="Shape_Leng" v="0.00022870718"/>
		<tag k="OBJECTID" v="855"/>
		<tag k="Type" v="residential"/>
		<tag k="Mode" v="highway"/>
		<tag k="name" v="Secondary road [highway=residential]"/>
		<tag k="highway" v="residential"/>
		<tag k="Name" v="unnamed road"/>
	</way>
	<way id="-4" version="1" timestamp="2016-12-01T13:01:43Z">
		<nd ref="-5"/>
		<nd ref="-6"/>
		<nd ref="-7"/>
		<tag k="Shape_Leng" v="0.00775550305"/>
		<tag k="OBJECTID" v="1039"/>
		<tag k="Type" v="residential"/>
		<tag k="Mode" v="highway"/>
		<tag k="name" v="Secondary road [highway=residential]"/>
		<tag k="highway" v="residential"/>
		<tag k="Name" v="unnamed road"/>
	</way>
	[...]
	
</osm>
		*/
		
		# Create an indexed array of ways at each node
		$nodes = array ();
		foreach ($osm->xpath('/osm/way') as $way) {
			
			# Get the way ID
			$wayId = (string) $way['id'];
			
			# Get the highway type
			$highwayType = (string) $way->xpath('./tag[@k="highway"]/@v')[0];	// See: http://stackoverflow.com/a/4045822
			
			# Get each node ID on this way
			foreach ($way->xpath('./nd') as $node) {
				$nodeId = (string) $node['ref'];
				
				# Register the node, saving the way ID and highway type
				$nodes[$nodeId][$wayId] = $highwayType;
			}
		}
		
		# Remove non-junction nodes, i.e. nodes that have only have one way listed
		foreach ($nodes as $nodeId => $ways) {
			if (count ($ways) == 1) {
				unset ($nodes[$nodeId]);
			}
		}
		
		# Remove junctions where all ways are of the same type, i.e. no transfer cost would be incurred
		foreach ($nodes as $nodeId => $ways) {
			$allValuesSame = (count (array_unique ($ways)) == 1);
			if ($allValuesSame) {
				unset ($nodes[$nodeId]);
			}

		}
		
		# Create all combinations for each node
		$combinations = array ();
		foreach ($nodes as $nodeId => $ways) {
			foreach ($ways as $fromId => $fromType) {
				foreach ($ways as $toId => $toType) {
					
					# Do not model u-turns, i.e. skip joining to self
					if ($fromId == $toId) {continue;}
					
					# Skip if the origin highway type is the same as the target type, e.g. residential -> residential
					if ($fromType == $toType) {continue;}
					
					# Look up the penalty for this combination
					$combinationId = $fromType . ',' . $toType;
					if (!isSet ($turnPenaltyTypes[$combinationId])) {
						$error = "The turn penalty definition list does not include a definition for {$fromType} -> {$toType}";
						return false;
					}
					
					# Add this combination; we assume a directional graph, i.e. residential -> rail could be different to rail -> residential
					$combinations[] = array (
						'fromId' => $fromId,
						'via' => $nodeId,
						'toId' => $toId,
						'penaltySeconds' => $turnPenaltyTypes[$combinationId],
					);
				}
			}

		}
		
		# Return the combinations list
		return $combinations;
	}
	
	
	# Function to convert the list to a (headerless) CSV
	private function convertToCsv ($combinations)
	{
		# Start a list of CSV lines
		$lines = array ();
		
		# Add each line
		foreach ($combinations as $combination) {
			$lines[] = implode (',', array_values ($combination));
		}
		
		# Compile to string
		$csv = implode ("\n", $lines);
		
		# Return the CSV string
		return $csv;
	}
}

?>
