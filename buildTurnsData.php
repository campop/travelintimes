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
$createTurnPenalties = new createTurnPenalties ($argv[1], $argv[2]);
$result = $createTurnPenalties->main ();
if (!$result) {exit (1);}	// Signal failure to shell


# Class definition
class createTurnPenalties
{
	# Class properties
	private $setupOk;
	
	# Constructor
	public function __construct ($directory, $turnPenaltiesDefinitionFile)
	{
		# Determine the input and output files
		$this->inputFile = $directory . '/merged.osm';
		$this->outputFile = $directory . '/penalties.csv';
		
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
		$this->turnPenaltiesDefinitionFile = $turnPenaltiesDefinitionFile;
		
		# Confirm setup is OK
		$this->setupOk = true;
	}
	
	
	# Main entry point
	public function main ()
	{
		# Ensure setup is OK, or end
		if (!$this->setupOk) {return false;}
		
		# Parse the turn penalties definition
		$turnPenaltyTypes = $this->parseTurnPenaltiesDefinition ($this->turnPenaltiesDefinitionFile);
		
		# Parse the input file and create the output file
		#!# This currently assumes that turns are always wanted - an intended lack of matches will cause the build to terminate
		if (!$combinations = $this->process ($this->inputFile, $this->outputFile, $turnPenaltyTypes, $error)) {
			echo 'ERROR: ' . $error . "\n";
			return false;
		}
		
		# Convert the data to CSV
		$csv = $this->convertToCsv ($combinations);
		
		# Write the file
		file_put_contents ($this->outputFile, $csv);
		
		# Return success
		return true;
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
	
	
	# Processor function
	private function process ($inputFile, $outputFile, $turnPenaltyTypes, &$error = '')
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
		
		# Create a list of nodes present in the ways, for each instance, so that tha list of junctions (i.e. those that appear more than once) can be created
		$allNodes = array ();
		foreach ($osm->xpath('/osm/way') as $way) {
			foreach ($way->xpath('./nd') as $node) {
				$allNodes[] = (string) $node['ref'];
			}
		}
		
		# Filter the nodes to junctions
		$checkKeysUniqueComparison = create_function ('$value', 'if ($value > 1) return true;');
		$junctionNodes = array_keys (array_filter (array_count_values ($allNodes), $checkKeysUniqueComparison));
		
		# Create an indexed array of ways at each node
		$nodes = array ();
		foreach ($osm->xpath('/osm/way') as $way) {
			
			# Get the way ID
			$wayId = (string) $way['id'];
			
			# Get the highway type
			$highwayType = (string) $way->xpath('./tag[@k="highway"]/@v')[0];	// See: http://stackoverflow.com/a/4045822
			
			# Get each node ID on this way
			$wayNodes = array ();
			foreach ($way->xpath('./nd') as $node) {
				$wayNodes[] = (string) $node['ref'];
			}
			
			# Register each node
			$lastNodeIndex = count ($wayNodes) - 1;	// e.g. array of 10 nodes would have [9] as the last
			foreach ($wayNodes as $index => $nodeId) {
				
				# Skip if not a junction node
				if (!in_array ($nodeId, $junctionNodes)) {continue;}
				
				# Determine the adjacent node for the way (as the OSRM turns implementation is from-node,via-node,to-node)
				# For each, register the node, saving the node IDs and highway type
				# See: https://github.com/Project-OSRM/osrm-backend/wiki/Traffic
				# See: https://github.com/Project-OSRM/osrm-backend/commit/68d672b5ac52ee451e97d3bb39d25703b06d89ab
				switch ($index) {
					
					# Start of way
					case 0:
						#!# 20170514 dataset gives "PHP Notice:  Undefined offset: 1 in /opt/travelintimes/buildTurnsData.php on line 211" for $nodeId=21416 whose $wayNodes array contains just that node only
						$adjacentNode = $wayNodes[1];
						$nodes[$nodeId][$adjacentNode] = $highwayType;	// Register
						break;
					
					# End of way
					case $lastNodeIndex:
						$penultimateNodeIndex = $lastNodeIndex - 1;
						$adjacentNode = $wayNodes[$penultimateNodeIndex];
						$nodes[$nodeId][$adjacentNode] = $highwayType;	// Register
						break;
					
					# In middle of way
					default:
						
						# Register the previous node
						$previousNodeIndex = $index - 1;
						$adjacentPreviousNode = $wayNodes[$previousNodeIndex];
						$nodes[$nodeId][$adjacentPreviousNode] = $highwayType;	// Register
						
						# Also, register the next node
						$nextNodeIndex = $index + 1;
						$adjacentNextNode = $wayNodes[$nextNodeIndex];
						$nodes[$nodeId][$adjacentNextNode] = $highwayType;	// Register
						
						break;
				}
			}
		}
		//print_r ($nodes);
		
		# Remove junctions where all ways are of the same type, i.e. no transfer cost would be incurred
		foreach ($nodes as $nodeId => $ways) {
			$allValuesSame = (count (array_unique ($ways)) == 1);
			if ($allValuesSame) {
				unset ($nodes[$nodeId]);
			}

		}
		
		# Create all combinations for each node
		$combinations = array ();
		$missingCombinations = array ();
		foreach ($nodes as $nodeId => $ways) {
			foreach ($ways as $fromId => $fromType) {
				foreach ($ways as $toId => $toType) {
					
					# Do not model u-turns, i.e. skip joining to self
					if ($fromId == $toId) {continue;}
					
					# Skip if the origin highway type is the same as the target type, e.g. residential -> residential
					if ($fromType == $toType) {continue;}
					
					# Look up the penalty for this combination, registering missing combinations (which will receive zero penalty)
					$combinationId = $fromType . ',' . $toType;
					if (!isSet ($turnPenaltyTypes[$combinationId])) {
						$missingCombinations[] = "{$fromType} -> {$toType}";
						continue;
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
		
		# Report missing combinations, as an error
		if ($missingCombinations) {
			$error  = "WARNING: The turn penalty definition list does not include a definition for the following, so zero-value entries have been added:";
			$error .= "\n\t" . implode ("\n\t", array_unique ($missingCombinations));
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
