<?php

# Class to create a control panel for the historic planner
require_once ('frontControllerApplication.php');
class historicPlannerControlPanel extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'useDatabase' => false,
			'useCamUniLookup' => false,
			'page404' => false,	// Internal
			'importsSectionsMode' => true,
			
			# Datasets
			'startPort' => 5000,
			'datasets' => array (
				'multimodal1680' => 'Multimodal 1680',	// Port 5000
				'multimodal1830' => 'Multimodal 1830',	// Port 5001
				'multimodal1911' => 'Multimodal 1911',	// Port 5002
			),
			'builds' => array (),	// Array of last known good builds
		
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign supported actions
	function actions ()
	{
		# Define available tasks
		$actions = array (
			
			'import' => array (
				'description' => 'Import GIS data',
				'tab' => 'Import',
				'icon' => 'database_refresh',
				'url' => 'import/',
			),
			'tagtransform' => array (
				'description' => 'Define the tag transform definition',
				'tab' => 'Tag transform',
				'icon' => 'tag',
				'url' => 'tagtransform/',
			),
			'routingprofiles' => array (
				'description' => 'Define the journey planner profiles',
				'tab' => 'Routing profiles',
				'icon' => 'chart_curve',
				'url' => 'routingprofiles/',
			),
			'turns' => array (
				'description' => 'Define the journey planner turns definitions',
				'tab' => 'Turns',
				'icon' => 'arrow_branch',
				'url' => 'turns/',
			),
			'mapnikstylesheet' => array (
				'description' => 'Define the rendering profile',
				'tab' => 'Rendering',
				'icon' => 'map',
				'url' => 'mapnikstylesheet/',
			),
			'frontend' => array (
				'description' => 'Define the frontend GUI configuration',
				'tab' => 'GUI',
				'icon' => 'application_view_gallery',
				'url' => 'frontend/',
			),
			'engine' => array (
				'tab' => 'Start/stop',
				'icon' => 'control_play_blue',
				'url' => 'engine/',
				'description' => 'Start/stop the routing process',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Additional processing
	function main ()
	{
		# Determine the root of the system
		$this->repoRoot = realpath ($this->applicationRoot . '/../..');
		
		# Define the profile and turns locations
		foreach ($this->settings['datasets'] as $profile => $label) {
			$this->profiles[$profile] = "profile-{$profile}.lua";
			$this->turns[$profile] = "turns-{$profile}.csv";
		}
		
	}
	
	
	
	# Home page
	public function home ()
	{
		# Welcome
		$html  = "\n<h2>Welcome</h2>";
		$html .= "\n<p>This system provides a control panel for the historic planner.</p>";
		
		# Import link
		$html .= "\n<h3>Import GIS data</h3>";
		$html .= "\n<ul>\n\t<li><a href=\"{$this->baseUrl}/import/\" class=\"actions\"><img src=\"{$this->baseUrl}/images/icons/database_refresh.png\" class=\"icon\"> Import GIS data</a></li>\n</ul>";
		
		# Link to live site
		$html .= "\n<h3>Live site</h3>";
		$html .= "\n<ul>\n\t<li><a href=\"/\" class=\"actions\"><img src=\"{$this->baseUrl}/images/icons/bullet_go.png\" class=\"icon\"> Go to live site</a></li>\n</ul>";
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to edit the tag transform definition
	public function tagtransform ()
	{
		# Define the instruction text
		$instructionsHtml  = "\n" . "<p>Here you can define the translation of the GIS data to OpenStreetMap (OSM) tags. These tags are then picked up as per the definitions in the <a href=\"{$this->baseUrl}/routingprofiles/\">routing profile</a>.</p>";
		$instructionsHtml .= "\n" . '<p>This definition uses the <a href="http://wiki.openstreetmap.org/wiki/Osmosis/TagTransform#Specifying_a_transform" target="_blank" title="[Opens in a new window]"><strong>Tag transform</strong></a> definition format.</p>';
		$instructionsHtml .= "\n" . '<p>Please use the standard OSM tags as defined at: <a href="http://wiki.openstreetmap.org/wiki/Map_Features" target="_blank" title="[Opens in a new window]">http://wiki.openstreetmap.org/wiki/Map_Features</a>.</p>';
		$instructionsHtml .= "\n" . '<p>The definition should be applicable across all imported datasets.</p>';
		$instructionsHtml .= "\n" . "<p>Changes will only take effect <strong>after</strong> doing an <a href=\"{$this->baseUrl}/import/\">import</a>.</p>";
		
		# Show the HTML, delegating to the file editor
		echo $html = $this->definitionFileEditor (__FUNCTION__, 'tagtransform.xml', $instructionsHtml, 'tagtransformValidation');
	}
	
	
	# Define the tag transform validation
	private function tagtransformValidation ($string, &$errors)
	{
		# Check XML validity
		#!# Ideally this would also check valid Osmosis syntax
		require_once ('xml.php');
		return xml::isValid ($string, $errors);
	}
	
	
	# Function to edit the routing profile definitions
	public function routingprofiles ()
	{
		# Start the HTML
		$html = '';
		
		# Define the instruction text
		$instructionsHtml  = "\n" . '<p>Here you can define the routing profiles.</p>';
		$instructionsHtml .= "\n" . '<p>This is written in Lua using the <a href="https://github.com/Project-OSRM/osrm-backend/wiki/Profiles" target="_blank" title="[Opens in a new window]">OSRM definition profile syntax</a>.</p>';
		$instructionsHtml .= "\n" . "<p>Changes will only take effect <strong>after</strong> doing an <a href=\"{$this->baseUrl}/import/\">import</a>.</p>";
		
		# Select the requested profile, if any
		$selectedProfile = '';	// I.e. false; empty string used so it is picked up by tab highlighting
		if (isSet ($_GET['profile'])) {
			if (!array_key_exists ($_GET['profile'], $this->profiles)) {
				echo $this->page404 ();
				return false;
			}
			$selectedProfile = $_GET['profile'];
		}
		
		# Show tabs
		$list = array ();
		foreach ($this->settings['datasets'] as $profile => $label) {
			$list[$profile] = "<a href=\"{$this->baseUrl}/{$this->actions[$this->action]['url']}{$profile}/\">{$label}</a>";
		}
		$home = "<a href=\"{$this->baseUrl}/{$this->actions[$this->action]['url']}\"><img src=\"/images/icons/house.png\" class=\"icon\" /></a>";
		$tabs = array_merge (array ('' => $home), $list);
		$instructionsHtml .= application::htmlUl ($tabs, 0, 'tabs', true, false, false, false, $selectedProfile);
		
		# If no profile selected, show selection list
		if (!$selectedProfile) {
			$html .= $instructionsHtml;
			$html .= "\n<p>Please select a profile to edit:</p>";
			$html .= application::htmlUl ($list, 0, 'boxylist');
			echo $html;
			return;
		}
		
		# Show the HTML, delegating to the file editor
		echo $this->definitionFileEditor (__FUNCTION__, $this->profiles[$selectedProfile], $instructionsHtml, 'routingprofilesValidation');
	}
	
	
	# Define the tag transform validation
	private function routingprofilesValidation ($string, &$errors)
	{
		#!# todo
		return true;
	}
	
	
	# Function to edit the turns definitions
	public function turns ()
	{
		# Start the HTML
		$html = '';
		
		# Define the instruction text
		$instructionsHtml  = "\n" . '<p>Here you can define the turns definitions.</p>';
		$instructionsHtml .= "\n" . "<p>Changes will only take effect <strong>after</strong> doing an <a href=\"{$this->baseUrl}/import/\">import</a>.</p>";
		
		# Select the requested profile, if any
		$selectedProfile = '';	// I.e. false; empty string used so it is picked up by tab highlighting
		if (isSet ($_GET['profile'])) {
			if (!array_key_exists ($_GET['profile'], $this->turns)) {
				echo $this->page404 ();
				return false;
			}
			$selectedProfile = $_GET['profile'];
		}
		
		# Show tabs
		$list = array ();
		foreach ($this->settings['datasets'] as $profile => $label) {
			$list[$profile] = "<a href=\"{$this->baseUrl}/{$this->actions[$this->action]['url']}{$profile}/\">{$label}</a>";
		}
		$home = "<a href=\"{$this->baseUrl}/{$this->actions[$this->action]['url']}\"><img src=\"/images/icons/house.png\" class=\"icon\" /></a>";
		$tabs = array_merge (array ('' => $home), $list);
		$instructionsHtml .= application::htmlUl ($tabs, 0, 'tabs', true, false, false, false, $selectedProfile);
		
		# If no profile selected, show selection list
		if (!$selectedProfile) {
			$html .= $instructionsHtml;
			$html .= "\n<p>Please select a turns definition to edit:</p>";
			$html .= application::htmlUl ($list, 0, 'boxylist');
			echo $html;
			return;
		}
		
		# Show the HTML, delegating to the file editor
		echo $this->definitionFileEditor (__FUNCTION__, $this->turns[$selectedProfile], $instructionsHtml, 'turnsValidation');
	}
	
	
	# Define the validation
	private function turnsValidation ($string, &$errors)
	{
		#!# todo
		return true;
	}
	
	
	# Function to edit the rendering stylesheet definition
	public function mapnikstylesheet ()
	{
		# Define the instruction text
		$instructionsHtml  = "\n" . "<p>Here you can define the stylesheet rendering definition, used to generate a map image, as shown <a href=\"#image\">below</a>.</p>";
		$instructionsHtml .= "\n" . '<p>This definition uses the <a href="https://github.com/mapnik/mapnik/wiki/XMLConfigReference" target="_blank" title="[Opens in a new window]"><strong>Mapnik XML</strong></a> format.</p>';
		$instructionsHtml .= "\n" . '<p>The definition should be applicable across all imported datasets.</p>';
		
		# Start the HTML, delegating to the file editor
		$html = $this->definitionFileEditor (__FUNCTION__, 'mapnikstylesheet.xml', $instructionsHtml, 'mapnikstylesheetValidation');
		
		# Show the current rendering
		$randomValue = rand (1000, 9999);       // Random string for URL to defeat caching
		$html .= "\n<h3 id=\"image\">Generated image of network:</h3>";
		$html .= "\n<p><em>Note that this will only change <strong>after</strong> an <a href=\"{$this->baseUrl}/import/\">import</a>.</em></p>";
		$html .= "\n<img src=\"{$this->baseUrl}/network.png?{$randomValue}\" id=\"outputfile\">";
		
		# Show the HTML
		echo $html;
	}
	
	
	# Define the validation
	private function mapnikstylesheetValidation ($string, &$errors)
	{
		# Check XML validity
		#!# Ideally this would also check valid Mapnik syntax
		require_once ('xml.php');
		return xml::isValid ($string, $errors);
	}
	
	
	# Function to edit the frontend configuration
	public function frontend ()
	{
		# Define the instruction text
		$instructionsHtml  = "\n" . '<p>Here you can define the frontend configuration file.</p>';
		$instructionsHtml .= "\n" . '<p>This is written in Javascript and expects to comply with the <a href="https://github.com/Project-OSRM/osrm-frontend" target="_blank" title="[Opens in a new window]">OSRM frontend options</a>.</p>';
		$instructionsHtml .= "\n" . "<p>Changes will take effect immediately (as soon as the form confirms success).</p>";
		
		# Show the HTML, delegating to the file editor
		echo $html = $this->definitionFileEditor (__FUNCTION__, 'osrm-frontend.js', $instructionsHtml, 'frontendValidation', 'frontendRunAfter');
	}
	
	
	# Define the validation
	private function frontendValidation ($string, &$errors)
	{
		#!# todo
		return true;
	}
	
	
	# Function to process the front end configuration
	private function frontendRunAfter (&$output)
	{
		# Run the command
		$script = 'cd /opt/osrm-frontend/ && make 2>&1 && cd -';
		exec ($script, $output, $returnStatusValue);
		$output = implode ("\n", $output);
		return (!$returnStatusValue);
	}
	
	
	# Function to implement a file definition editor
	#!# Move into frontControllerApplication as is a typical use-case
	private function definitionFileEditor ($type, $filePathInRepo, $instructionsHtml, $validationCallbackMethod, $runAfterMethod = false)
	{
		# Start the HTML
		$html  = '';
		
		# Load the current definition
		$definitionFilename = $filePathInRepo;
		
		$definitionFile = $this->repoRoot . '/configuration/' . $type . '/' . $definitionFilename;
		$definition = file_get_contents ($definitionFile);
		
		# Get a list of archived files
		$archivedFiles = $this->getArchivedFiles ($definitionFilename, $type);
		
		# If an archived file is specified, pre-load that instead
		if (isSet ($_GET['restore']) && isSet ($archivedFiles[$_GET['restore']])) {
			$definition = file_get_contents ($archivedFiles[$_GET['restore']]);
		}
		
		# Display a flash message if set
		#!# Flash message support needs to be added to ultimateForm natively, as this is a common use-case
		$successMessage = 'The definition has been updated.';
		if ($flashValue = application::getFlashMessage ('submission', $this->baseUrl . '/')) {
			$message = "\n" . "<p>{$this->tick} <strong>" . $successMessage . '</strong></p>';
			$html .= "\n<div class=\"graybox flashmessage\">" . $message . '</div>';
		}
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
			'display' => 'paragraphs',
			'autofocus' => true,
			'unsavedDataProtection' => true,
			'whiteSpaceTrimSurrounding' => false,
		));
		$form->heading ('', $instructionsHtml);
		$form->textarea (array (
			'name'		=> 'definition',
			'title'		=> 'Definition',
			'required'	=> true,
			'rows'		=> 30,
			'cols'		=> 120,
			'default'	=> $definition,
			'wrap'		=> 'off',
		));
		
		# Validate the parser syntax
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['definition']) {
				if (!$this->{$validationCallbackMethod} ($unfinalisedData['definition'], $errors)) {
					$form->registerProblem ('invalidsyntax', 'The definition was not valid syntax, as per the following error(s):' . application::htmlUl ($errors));
				}
			}
		}
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Back up the current version
			$definitionArchiveFile = $this->repoRoot . '/configuration/' . $type . '/archive/' . $definitionFilename;
			if (!copy ($definitionFile, $definitionArchiveFile . '.until-' . date ('Ymd-His') . ".replacedby-{$this->user}" . '.txt')) {
				$html = "\n<p class=\"warning\">There was a problem archiving the old definition.</p>";
				echo $html;
				return false;
			}
			
			# Save the new version
			if (!file_put_contents ($definitionFile, $result['definition'])) {
				$html = "\n<p class=\"warning\">There was a problem saving the new definition at {$definitionFile}.</p>";
				echo $html;
				return false;
			}
			
			# If a method is defined to run after, run this
			if ($runAfterMethod) {
				if (!$this->{$runAfterMethod} ($error)) {
					$html = "\n<p class=\"warning\">There was a problem running the changes:</p>";
					$html .= application::dumpData ($error, false, true);
					echo $html;
					return false;
				}
			}
			
			# Set a flash message
#!# A restore submission needs to go back to <path>/ rather than <path>/<timestamp>.html
			$redirectTo = $_SERVER['_PAGE_URL'];
			$redirectMessage = "\n{$this->tick}" . ' <strong>' . $successMessage . '</strong></p>';
			application::setFlashMessage ('submission', '1', $redirectTo, $redirectMessage, $this->baseUrl . '/');
			
			# Confirm success, resetting the HTML, and show the submission
			$html = application::sendHeader (302, $redirectTo, true);
		}
		
		# Show the list of archived files
		$list = array ();
		foreach ($archivedFiles as $timestamp => $file) {
			$humanReadableDate = date_format (date_create_from_format ('Ymd-His', $timestamp), 'l jS M Y, g.ia');
#!# Fails at /controlpanel/routingprofiles/multimodal1830/
			$list[] = "<a href=\"{$this->baseUrl}/{$this->actions[$this->action]['url']}{$timestamp}.html\">File replaced at {$humanReadableDate}</a>";
		}
		$html .= "\n<p>You can also pre-load a previous definition, and then press Submit to restore it:</p>";
		$html .= application::htmlUl ($list);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to get a list of archived files, as a key-value pair, indexed by timestamp
	private function getArchivedFiles ($definitionFilename, $type)
	{
		# Show list of restorable definitions
		require_once ('directories.php');
		$files = directories::flattenedFileListing ($this->repoRoot . '/configuration/' . $type . '/archive/', array ('txt'), true, false, false, $definitionFilename);
		$archivedFiles = array ();
		foreach ($files as $file) {
			if (preg_match ('/until-([0-9]{8}-[0-9]{6})/', $file, $matches)) {
				$timestamp = $matches[1];
				$archivedFiles[$timestamp] = $file;
			}
		}
		
		# Reverse, to show most recent first
		krsort ($archivedFiles);
		
		# Return the array
		return $archivedFiles;
	}
	
	
	# Function to start/stop the engine process
	public function engine ()
	{
		# Start the HTML
		$html  = '';
		
		# Obtain confirmation from the user
		$message = '<strong>Are you sure you want to (re)start the engine?</strong>';
		$confirmation = 'Yes, (re)start';
		if ($this->areYouSure ($message, $confirmation, $html)) {
			
			# Reset the HTML
			$html = '';
			
			# Restart the engine, for each profile
			$port = $this->settings['startPort'] - 1;	// Minus one, as will be immediately incremented to the first
			foreach ($this->settings['datasets'] as $profile => $label) {
				$port++;	// E.g. 5000, 5001, 5002
				
				# State the engine profile
				$html .= "\n<p><em>Engine profile {$profile}:</em></p>";
				
				# Kill any existing process
				$command = "pgrep -f 'osrm-routed -p ${port}'";
				exec ($command, $pids);		// See: http://stackoverflow.com/a/3111553
				if ($pids) {
					$command = "pkill -f 'osrm-routed -p ${port}'";
					exec ($command, $output = array (), $returnStatusValue);
					#!# Return status does not seem to be handled properly
					/*
					if ($returnStatusValue) {
						$html .= "\n" . '<p class="error">Problem shutting down existing engine process:</p>';
						$html .= "\n" . application::dumpData ($output, false, $return = true);
						continue;
					}
					*/
				}
				
				# Execute the command for this profile
				$commandBase = '/opt/osrm-backend/build/osrm-routed -p %port /opt/travelintimes/enginedata/%profile/%build/merged.osrm > /opt/travelintimes/logs-osrm/osrm-%profile.log &';
				$replacements = array (
					'%port' => $port,
					'%profile' => $profile,
					'%build' => $this->settings['builds'][$profile],
				);
				$command = strtr ($commandBase, $replacements);
				exec ($command, $output = array (), $returnStatusValue);
				#!# Return status does not seem to be handled properly
				/*
				if ($returnStatusValue) {
					$html .= "\n" . '<p class="error">Problem starting the engine:</p>';
					$html .= "\n" . application::dumpData ($output, false, $return = true);
					continue;
				}
				*/
				
				# Confirm success
				$html .= "<p>{$this->tick} The engine was (re)started.</p>";
			}
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to import the file, clearing any existing import
	public function import ()
	{
		# Define the import types
		$importTypes = array (
			'full'	=> 'FULL import',
		);
		
		# Define the introduction HTML
		$fileCreationInstructionsHtml  = "\n\t" . '<p>Assemble the GIS file as a .zip file.</p>';
		$fileCreationInstructionsHtml .= "\n\t" . '<p>This should contain, at top level, a file <strong>multimodal.shp</strong> and associated metadata files.</p>';
		
		# Run the import UI
		$this->importUi (array_keys ($this->settings['datasets']), $importTypes, $fileCreationInstructionsHtml, 'zip');
	}
	
	
	# Import implementation
	public function doImport ($exportFiles, $importType, &$html)
	{
		# Start the HTML
		$html = '';
		
		# Determine the grouping and the filename
		reset ($exportFiles);
		$grouping = key ($exportFiles);         // i.e. first key, e.g. multimodal1911
		$exportFile = $exportFiles[$grouping];  // i.e. first value
		
		# Determine the port the eventual routing engine should use
		$port = $this->settings['startPort'];
		foreach ($this->settings['datasets'] as $dataset => $label) {
			if ($dataset == $grouping) {
				break;	// Port is now set, e.g. first dataset is 5000
			}
			$port++;
		}
		
		# Run the script
		chdir ($this->repoRoot . '/');
		// E.g. /build.sh multimodal1911 exports/multimodal191120161101.zip
		$logFilename = "enginedata/build-{$grouping}.log";
		$script = "./build.sh $grouping $exportFile $port > {$logFilename} 2>&1";    // Also capture error; see: http://stackoverflow.com/a/3863805 and http://stackoverflow.com/a/6674348
		exec ($script, $output, $returnStatusValue);
		// $output = implode ("\n", $output);
		$output = file_get_contents ($logFilename);
		
		# Link to reset
		$html .= "\n<p><a href=\"{$this->baseUrl}/import/\">Reset page.</a></p>";
		
		# Link to result if success
		if ($returnStatusValue == 0) {
			$html .= "\n<p><a href=\"{$_SERVER['_SITE_URL']}\"><strong><img src=\"{$this->baseUrl}/images/icons/tick.png\" class=\"icon\"> Go to the live journey planner!</strong></a></p>";
		}
		
		# Show the generated output file if success
		if ($returnStatusValue == 0) {
			$randomValue = rand (1000, 9999);	// Random string for URL to defeat caching
			$html .= "\n<div class=\"graybox\">";
			$html .= "\n<p><em>Generated image of network:</em></p>";
			$html .= "\n<img src=\"{$this->baseUrl}/network.png?{$randomValue}\" id=\"outputfile\">";
			$html .= "\n</div>";
		}
		
		# Show the output
		$html .= "\n<div class=\"graybox\">";
		$html .= "\n<p><em>Program output:</em></p>";
		if ($returnStatusValue != 0) {
			$html .= "\n<p class=\"warning\">Build failed with return status {$returnStatusValue}. See the error at the end below.</p>";
		}
		$html .= "\n<pre>";
		$html .= "\n" . htmlspecialchars ($output);
		$html .= "\n</pre>";
		$html .= "\n</div>";
		
		# Signal success
		return true;
	}
}

?>
