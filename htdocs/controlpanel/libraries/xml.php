<?php

# XML wrapper class
# Version 1.8.0
class xml
{
	# Function to convert XML to an array
	#!# Consider making the last two items default to false
	public static function xml2array ($xmlfile, $cacheXml = false, $documentToDataOrientatedXml = true, $xmlIsFile = true, $getAttributes = false, $entityConversions = false, $utf8Decode = false, $skipComments = false)
	{
		# If there is not a cached file, pre-process the XML
		if (!$cacheXml || ($cacheXml && !file_exists ($cacheXml))) {
			
			# Get the XML
			if (!$xml = ($xmlIsFile ? file_get_contents ($xmlfile) : $xmlfile)) {return false;}
			
			# Remove the DOCTYPE
			$xml = preg_replace ('/<!DOCTYPE ([^]]+)]>/', '', $xml);
			$xml = preg_replace ('/<!DOCTYPE ([^>]+)>/', '', $xml);
			
			# Do entity conversions if necessary
			if ($entityConversions) {
				
				# Get the entities
				$entities = self::getEntityConversions ();
				
				# Convert entities
				$xml = str_replace (array_keys ($entities), array_values ($entities), $xml);
			}
			
			# Convert from document-orientated to data-orientated XML, if required
			if ($documentToDataOrientatedXml) {
				$xml = self::documentToDataOrientatedXml ($xml);
			}
			
			# Cache the contents if required
			if ($cacheXml) {
				file_put_contents ($cacheXml, $xml);
			}
			
		# Otherwise get the XML file
		} else {
			$xml = file_get_contents ($cacheXml ? $cacheXml : $xmlfile);
		}
		
		# End if a problem
		if (!$xml) {return false;}
		
		# Convert the XML to an object
		#!# Note that this will lose single-item -containing nodes, e.g. <foo><bar /><foo> will lose bar
		if (!$xmlobject = simplexml_load_string ($xml, NULL, LIBXML_NOENT)) {return false;}
		
		# Convert the object to an array
		if (!$xml = self::simplexml2array ($xmlobject, $getAttributes, $utf8Decode, $skipComments)) {return false;}
		
		# Return the XML
		return $xml;
	}
	
	
	# Function to convert XML to an array, with namespace support
	public static function xml2arrayWithNamespaces ($xmlString)
	{
		# Convert namespaces to a placeholder string for safety
		$uniqueString = '____';		// String judged not to appear in key names
		$xmlString = preg_replace ('/<([^:> ]+):([^>]+)>/', "<\\1{$uniqueString}\\2>", $xmlString);
		
		# Convert the XML to an array
		$xml   = simplexml_load_string ($xmlString);
		$array = json_decode (json_encode ((array) $xml), 1);
		$array = array ($xml->getName () => $array);	// Restore top-level tag as this is lost in the conversion process
		
		# Substitute namespaces
		require_once ('application.php');
		$array = application::array_key_str_replace ($uniqueString, ':', $array);
		
		# Return the array
		return $array;
	}
	
	
	# Function to get entity conversion
	public static function getEntityConversions ($url = 'http://www.w3.org/TR/html4/sgml/entities.html')
	{
		/* This commented out code results in the array below
		# Get the file contents
		$document = file_get_contents ($url);
		
		# Perform matches
		preg_match_all ('/ENTITY\s([a-zA-Z]+)(\s+)CDATA(\s+)"&amp;([^"]+)"/', $document, $matches);
		
		# Surround the entities with & and ;]
		foreach ($matches[1] as $index => $value) {
			$matches[1][$index] = "&{$value};";
		}
		foreach ($matches[4] as $index => $value) {
			$matches[4][$index] = "&{$value}";
		}
		
		# Arrange as key to value
		$entities = array_combine ($matches[1], $matches[4]);
		*/
		
		$entities = array (
			'&nbsp;' => '&#160;',
			'&iexcl;' => '&#161;',
			'&cent;' => '&#162;',
			'&pound;' => '&#163;',
			'&curren;' => '&#164;',
			'&yen;' => '&#165;',
			'&brvbar;' => '&#166;',
			'&sect;' => '&#167;',
			'&uml;' => '&#168;',
			'&copy;' => '&#169;',
			'&ordf;' => '&#170;',
			'&laquo;' => '&#171;',
			'&not;' => '&#172;',
			'&shy;' => '&#173;',
			'&reg;' => '&#174;',
			'&macr;' => '&#175;',
			'&deg;' => '&#176;',
			'&plusmn;' => '&#177;',
			'&acute;' => '&#180;',
			'&micro;' => '&#181;',
			'&para;' => '&#182;',
			'&middot;' => '&#183;',
			'&cedil;' => '&#184;',
			'&ordm;' => '&#186;',
			'&raquo;' => '&#187;',
			'&iquest;' => '&#191;',
			'&Agrave;' => '&#192;',
			'&Aacute;' => '&#193;',
			'&Acirc;' => '&#194;',
			'&Atilde;' => '&#195;',
			'&Auml;' => '&#196;',
			'&Aring;' => '&#197;',
			'&AElig;' => '&#198;',
			'&Ccedil;' => '&#199;',
			'&Egrave;' => '&#200;',
			'&Eacute;' => '&#201;',
			'&Ecirc;' => '&#202;',
			'&Euml;' => '&#203;',
			'&Igrave;' => '&#204;',
			'&Iacute;' => '&#205;',
			'&Icirc;' => '&#206;',
			'&Iuml;' => '&#207;',
			'&ETH;' => '&#208;',
			'&Ntilde;' => '&#209;',
			'&Ograve;' => '&#210;',
			'&Oacute;' => '&#211;',
			'&Ocirc;' => '&#212;',
			'&Otilde;' => '&#213;',
			'&Ouml;' => '&#214;',
			'&times;' => '&#215;',
			'&Oslash;' => '&#216;',
			'&Ugrave;' => '&#217;',
			'&Uacute;' => '&#218;',
			'&Ucirc;' => '&#219;',
			'&Uuml;' => '&#220;',
			'&Yacute;' => '&#221;',
			'&THORN;' => '&#222;',
			'&szlig;' => '&#223;',
			'&agrave;' => '&#224;',
			'&aacute;' => '&#225;',
			'&acirc;' => '&#226;',
			'&atilde;' => '&#227;',
			'&auml;' => '&#228;',
			'&aring;' => '&#229;',
			'&aelig;' => '&#230;',
			'&ccedil;' => '&#231;',
			'&egrave;' => '&#232;',
			'&eacute;' => '&#233;',
			'&ecirc;' => '&#234;',
			'&euml;' => '&#235;',
			'&igrave;' => '&#236;',
			'&iacute;' => '&#237;',
			'&icirc;' => '&#238;',
			'&iuml;' => '&#239;',
			'&eth;' => '&#240;',
			'&ntilde;' => '&#241;',
			'&ograve;' => '&#242;',
			'&oacute;' => '&#243;',
			'&ocirc;' => '&#244;',
			'&otilde;' => '&#245;',
			'&ouml;' => '&#246;',
			'&divide;' => '&#247;',
			'&oslash;' => '&#248;',
			'&ugrave;' => '&#249;',
			'&uacute;' => '&#250;',
			'&ucirc;' => '&#251;',
			'&uuml;' => '&#252;',
			'&yacute;' => '&#253;',
			'&thorn;' => '&#254;',
			'&yuml;' => '&#255;',
			'&fnof;' => '&#402;',
			'&Alpha;' => '&#913;',
			'&Beta;' => '&#914;',
			'&Gamma;' => '&#915;',
			'&Delta;' => '&#916;',
			'&Epsilon;' => '&#917;',
			'&Zeta;' => '&#918;',
			'&Eta;' => '&#919;',
			'&Theta;' => '&#920;',
			'&Iota;' => '&#921;',
			'&Kappa;' => '&#922;',
			'&Lambda;' => '&#923;',
			'&Mu;' => '&#924;',
			'&Nu;' => '&#925;',
			'&Xi;' => '&#926;',
			'&Omicron;' => '&#927;',
			'&Pi;' => '&#928;',
			'&Rho;' => '&#929;',
			'&Sigma;' => '&#931;',
			'&Tau;' => '&#932;',
			'&Upsilon;' => '&#933;',
			'&Phi;' => '&#934;',
			'&Chi;' => '&#935;',
			'&Psi;' => '&#936;',
			'&Omega;' => '&#937;',
			'&alpha;' => '&#945;',
			'&beta;' => '&#946;',
			'&gamma;' => '&#947;',
			'&delta;' => '&#948;',
			'&epsilon;' => '&#949;',
			'&zeta;' => '&#950;',
			'&eta;' => '&#951;',
			'&theta;' => '&#952;',
			'&iota;' => '&#953;',
			'&kappa;' => '&#954;',
			'&lambda;' => '&#955;',
			'&mu;' => '&#956;',
			'&nu;' => '&#957;',
			'&xi;' => '&#958;',
			'&omicron;' => '&#959;',
			'&pi;' => '&#960;',
			'&rho;' => '&#961;',
			'&sigmaf;' => '&#962;',
			'&sigma;' => '&#963;',
			'&tau;' => '&#964;',
			'&upsilon;' => '&#965;',
			'&phi;' => '&#966;',
			'&chi;' => '&#967;',
			'&psi;' => '&#968;',
			'&omega;' => '&#969;',
			'&thetasym;' => '&#977;',
			'&upsih;' => '&#978;',
			'&piv;' => '&#982;',
			'&bull;' => '&#8226;',
			'&hellip;' => '&#8230;',
			'&prime;' => '&#8242;',
			'&Prime;' => '&#8243;',
			'&oline;' => '&#8254;',
			'&frasl;' => '&#8260;',
			'&weierp;' => '&#8472;',
			'&image;' => '&#8465;',
			'&real;' => '&#8476;',
			'&trade;' => '&#8482;',
			'&alefsym;' => '&#8501;',
			'&larr;' => '&#8592;',
			'&uarr;' => '&#8593;',
			'&rarr;' => '&#8594;',
			'&darr;' => '&#8595;',
			'&harr;' => '&#8596;',
			'&crarr;' => '&#8629;',
			'&lArr;' => '&#8656;',
			'&uArr;' => '&#8657;',
			'&rArr;' => '&#8658;',
			'&dArr;' => '&#8659;',
			'&hArr;' => '&#8660;',
			'&forall;' => '&#8704;',
			'&part;' => '&#8706;',
			'&exist;' => '&#8707;',
			'&empty;' => '&#8709;',
			'&nabla;' => '&#8711;',
			'&isin;' => '&#8712;',
			'&notin;' => '&#8713;',
			'&ni;' => '&#8715;',
			'&prod;' => '&#8719;',
			'&sum;' => '&#8721;',
			'&minus;' => '&#8722;',
			'&lowast;' => '&#8727;',
			'&radic;' => '&#8730;',
			'&prop;' => '&#8733;',
			'&infin;' => '&#8734;',
			'&ang;' => '&#8736;',
			'&and;' => '&#8743;',
			'&or;' => '&#8744;',
			'&cap;' => '&#8745;',
			'&cup;' => '&#8746;',
			'&int;' => '&#8747;',
			'&sim;' => '&#8764;',
			'&cong;' => '&#8773;',
			'&asymp;' => '&#8776;',
			'&ne;' => '&#8800;',
			'&equiv;' => '&#8801;',
			'&le;' => '&#8804;',
			'&ge;' => '&#8805;',
			'&sub;' => '&#8834;',
			'&sup;' => '&#8835;',
			'&nsub;' => '&#8836;',
			'&sube;' => '&#8838;',
			'&supe;' => '&#8839;',
			'&oplus;' => '&#8853;',
			'&otimes;' => '&#8855;',
			'&perp;' => '&#8869;',
			'&sdot;' => '&#8901;',
			'&lceil;' => '&#8968;',
			'&rceil;' => '&#8969;',
			'&lfloor;' => '&#8970;',
			'&rfloor;' => '&#8971;',
			'&lang;' => '&#9001;',
			'&rang;' => '&#9002;',
			'&loz;' => '&#9674;',
			'&spades;' => '&#9824;',
			'&clubs;' => '&#9827;',
			'&hearts;' => '&#9829;',
			'&diams;' => '&#9830;',
			'&quot;' => '&#34;',
			'&amp;' => '&#38;',
			'&lt;' => '&#60;',
			'&gt;' => '&#62;',
			'&OElig;' => '&#338;',
			'&oelig;' => '&#339;',
			'&Scaron;' => '&#352;',
			'&scaron;' => '&#353;',
			'&Yuml;' => '&#376;',
			'&circ;' => '&#710;',
			'&tilde;' => '&#732;',
			'&ensp;' => '&#8194;',
			'&emsp;' => '&#8195;',
			'&thinsp;' => '&#8201;',
			'&zwnj;' => '&#8204;',
			'&zwj;' => '&#8205;',
			'&lrm;' => '&#8206;',
			'&rlm;' => '&#8207;',
			'&ndash;' => '&#8211;',
			'&mdash;' => '&#8212;',
			'&lsquo;' => '&#8216;',
			'&rsquo;' => '&#8217;',
			'&sbquo;' => '&#8218;',
			'&ldquo;' => '&#8220;',
			'&rdquo;' => '&#8221;',
			'&bdquo;' => '&#8222;',
			'&dagger;' => '&#8224;',
			'&Dagger;' => '&#8225;',
			'&permil;' => '&#8240;',
			'&lsaquo;' => '&#8249;',
			'&rsaquo;' => '&#8250;',
			'&euro;' => '&#8364;',
		);
		
		# Return the matches
		return $entities;
	}
	
	
	# Function to determine if a string is valid XML
	public static function isValid ($string, &$errors = array ())
	{
		# Check the XML
		libxml_use_internal_errors (true);
		if (!$isValidXml = simplexml_load_string ($string)) {
			
			# Capture errors and assemble as a list
			$errorObjects = libxml_get_errors ();
			$errors = array ();
			foreach ($errorObjects as $errorObject) {
				$errors[] = "Line {$errorObject->line}, Character {$errorObject->column}: {$errorObject->message}";
			}
		}
		
		# Return status
		return $isValidXml;
	}
	
	
	# Function to convert from document-orientated to data-orientated XML
	public static function documentToDataOrientatedXml ($xml)
	{
		# Perform a search & replace on the offending strings
		#!# Note: this fails if xyz is one/two characters only: <CONTAINER>xyz<SUB-CONTAINER>Data</SUB-CONTAINER>
		
		/*  Here is a worked-through example of this bug:
		
		<Administration><Progress><Keyword>R</Keyword><Type>
		
		$search = "<([-a-zA-Z0-9]+)>([^<]{1})([^/]{1})([^<]+)" . "<([-a-zA-Z0-9]+)>";
		$replacement = "<\\1><\\1>\\2\\3\\4</\\1>" . "<\\5>";
				
		<([-a-zA-Z0-9]+)>	([^<]{1})	([^/]{1})	([^<]+)		<([-a-zA-Z0-9]+)>
		
		<Keyword>			R			<			/Keyword>	<Type>
		
		<Keyword><Keyword>R</Keyword></Keyword><Type>
		
		*/
		
		#!# This is also catching simple top-level cases like e.g. <NUMBER-OF-ITEMS>1</NUMBER-OF-ITEMS> for some reason
		#!# Can't get the spaces aspect working
		$search = "<([-a-zA-Z0-9]+)>" . /* "\s*" . */ "([^<])([^/])([^<]+)" . /* "\s*" . */ "<([-a-zA-Z0-9]+)(/?)>";
		$replacement = "<\\1><\\1>\\2\\3\\4</\\1><\\5\\6>";
		#!# This is currently extremely memory-intensive and prone to failure with large files
		$xml = preg_replace ('|' . $search . '|ims', $replacement, $xml);	// preg_replace supports backreferences in the search string
		
		# Return the XML
		return $xml;
	}
	
	
	# From http://uk2.php.net/manual/en/ref.simplexml.php
	public static function simplexml2array (/* Object */ $xml, $getAttributes = false, $utf8decode = false, $skipComments = false)
	{
	   if (is_a ($xml, 'SimpleXMLElement')) {
	       $attributes = $xml->attributes();
	       foreach ($attributes as $k => $v) {
	           if ($v) {
//			   	$v = str_replace ("\xe2\x80\xa6", '&#8230;', $v);
//			   	if ($utf8decode) {$v = utf8_decode ($v);}
				$string = (string) $v;
				$a[$k] = $string;
			 }
	       }
	       $x = $xml;
	       $xml = get_object_vars ($xml);
	   }
	   
	   if (is_array ($xml)) {
	       if (count ($xml) == 0) {
//		   	   if ($utf8decode) {$x = utf8_decode ($x);}
		       $return = (string) $x; // for CDATA
			   return $return;
		   }
	       foreach ($xml as $key => $value) {
	           if ($skipComments && $key == 'comment') {continue;}
			   $r[$key] = self::simplexml2array ($value, $getAttributes, $utf8decode);
	       }
	       if ($getAttributes) {
				if (isset ($a)) {
					$r['@'] = $a;    // Attributes
				}
		   }
	       return $r;
	   }
	   
	   if ($utf8decode) {$xml = utf8_decode ($xml);}
	   return (string) $xml;
	}
	
	
	# Function to chunk files into pieces into a database; NB This does *not* clear existing records - only inserts/overwrites records
	public static function databaseChunking ($file, $credentials, $database, $table, $xpathRecordsRoot, $recordIdPath, $otherPaths = array (), $multiplesDelimiter = '|', $entityConversions = true, $documentToDataOrientatedXml = true, $timeLimit = 300, $filter = false)
	{
		# Set a larger time limit than the default
		set_time_limit ($timeLimit);
		
		# Obtain the file
		if (!is_readable ($file)) {
			echo "\n<p class=\"warning\">The file {$file} could not be read or does not exist.</p>";
			return false;
		}
		$xml = file_get_contents ($file);
		
		# Do entity conversions if required
		if ($entityConversions) {
			
			# Get the entities
			$entities = self::getEntityConversions ();
			
			# Convert entities
			$xml = str_replace (array_keys ($entities), array_values ($entities), $xml);
		}
		
		# Convert from document-orientated to data-orientated XML, if required
		if ($documentToDataOrientatedXml) {
			$xml = self::documentToDataOrientatedXml ($xml);
		}
		
		# Cache the contents if required
		// file_put_contents ('./cache.xml', $xml);
		
		# Start an array of data
		$dataset = array ();
		
		# Chunk the XML
		$xml = new SimpleXMLElement ($xml);
		$records = $xml->xpath ($xpathRecordsRoot);
		foreach ($records as $record) {
			
			# Assign the record number
			$id = NULL;
			$xpathResults = $record->xpath ($recordIdPath);
			foreach ($xpathResults as $xpathResult) {
				$id = trim ((string) $xpathResult);
				break;
			}
			
			# Skip if no ID
			if (!$id) {continue;}
			
			# If a filter is defined check it
			if ($filter) {
				if (!$filterResult = $record->xpath ($filter)) {	// Check for any result, i.e. not empty array
					continue;
				}
			}
			
			# Get the record itself as XML
			$data = $record->asXML();
			
/*
	if ($id == 'N: 257') {
		echo "<pre>" . htmlspecialchars ($data) . '</pre>';
		return false;
	}
*/
			
			# Add the data to the array of records
			$dataset[$id] = array ('id' => $id, 'data' => $data);
			
			# Add other records
			if ($otherPaths) {
				foreach ($otherPaths as $name => $path) {
					
					# Skip if absent
					if (empty ($path)) {continue;}
					
					# Switch to special handling if required, effectively using a callback method
					# e.g. Material,Description/Material>foo::bar means
					# $name = Material
					# $path to get the array for is Description/Material, but pass that into foo::bar() statically first
					$useCallback = false;
					if (preg_match ('/^([^>]+)>([^:]+)::(.+)$/', $path, $matches)) {
						list ($ignore, $path, $class, $method) = $matches;
						if (method_exists ($class, $method)) {
							$useCallback = true;
						}
					}
					
					# Process this node
					if ($xpathResults = $record->xpath ($path)) {
						
						# Use the callback if required
						if ($useCallback) {
							$result = call_user_func (array ($class, $method), $xpathResults);
						} else {
							
							# Otherwise process natively
							$xpathResultComponents = array ();
							foreach ($xpathResults as $xpathResult) {
								$item = trim ((string) $xpathResult);
								if (!empty ($item)) {$xpathResultComponents[] = $item;}
							}
							$result = implode ("{$multiplesDelimiter}{$multiplesDelimiter}", $xpathResultComponents);
							
							# Surround with the delimiter if there is more than one component
							if (count ($xpathResultComponents) > 1) {$result = $multiplesDelimiter . $result . $multiplesDelimiter;}
						}
						
						# Assign the result
						$dataset[$id][$name] = $result;
					}
				}
			}
		}
		
		# Get the authentication credentials
		#!# This is failing
		if (is_string ($credentials)) {
			if (!is_readable ($credentials)) {
				echo "\n<p class=\"warning\">The authentication file could not be read or does not exist.</p>";
				return false;
			}
			include ($credentials);
		}
		
		# Connect to the database
		require_once ('database.php');
		if (!$databaseConnection = new database ($credentials['hostname'], $credentials['username'], $credentials['password'])) {
			echo "<p>There was a problem connecting to the database.</p>";
			return false;
		}
		
		# Insert the data, converting an INSERT to an UPDATE when the id exists already
		foreach ($dataset as $key => $data) {
			if (!$databaseConnection->insert ($database, $table, $data, true)) {
				echo "<p>There was a problem inserting the data into the database. MySQL said:</p>";
				application::dumpData ($databaseConnection->error ());
				return false;
			}
		}
		
		# Return success
		return count ($dataset);
	}
	
	
	# XML string formatter, based on http://forums.devnetwork.net/viewtopic.php?p=213989
	public static function formatter ($xml, $boxClass = 'code')
	{
		# Protect inline tag combinations
		$inlineTagCombinations = array (
			'</a></p>',
		);
		$inlineTagCombinationsReplacements = array ();
		$safeString = '~~FOO~~';
		foreach ($inlineTagCombinations as $inlineTagCombination) {
			$replacement = str_replace ('><', ">{$safeString}<", $inlineTagCombination);
			$inlineTagCombinationsReplacements[$inlineTagCombination] = $replacement;
		}
		$xml = strtr ($xml, $inlineTagCombinationsReplacements);
		
		// add marker linefeeds to aid the pretty-tokeniser (adds a linefeed between all tag-end boundaries)
		$xml = preg_replace ('/(>)\s*(<)(\/*)/', "$1\n$2$3", $xml);
		
		# Undo protection of inline tag combinations
		$inlineTagCombinationsReplacements = array_flip ($inlineTagCombinationsReplacements);
		$xml = strtr ($xml, $inlineTagCombinationsReplacements);
		
		// now indent the tags
		$token      = strtok ($xml, "\n");
		$result     = ''; // holds formatted version as it is built
		$pad        = 0; // initial indent
		$matches    = array (); // returns from preg_matches()
		
		// scan each line and adjust indent based on opening/closing tags
		while ($token !== false) {
			
			// test for the various tag states
			
			// 1. open and closing tags on same line - no change
			if (preg_match ('/.+<\/\w[^>]*>$/', $token, $matches)) {
				$indent = 0;
			// 2. closing tag - outdent now
			} elseif (preg_match ('/^<\/\w/', $token, $matches)) {
				$pad--;
			// 3. opening tag - don't pad this one, only subsequent tags
			} elseif (preg_match ('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) {
				$indent = 1;
			// 4. no indentation needed
			} else {
				$indent = 0;
			}
			
			// Pad the line with the required number of leading spaces
			$padString = "\t";
			// Note: Warnings "str_repeat(): Second argument has to be greater than or equal to 0" mean that the XML is not properly data-orientated
			$result .= str_repeat ($padString, $pad) . htmlspecialchars ($token) . "\n"; // add to the cumulative result, with linefeed
			$token   = strtok ("\n"); // get the next token
			$pad    += $indent; // update the pad size for subsequent lines    
		}
		
		# Make the tags appear faded
		$result = str_replace (array ('&lt;', '&gt;'), array ("<span>&lt;", '&gt;</span>'), $result);
		
		# Compile the HTML
		$html  = '';
		if ($boxClass) {$html .= "\n<div class=\"{$boxClass}\">";}
		$html .= "\n<pre>";
		$html .= $result;
		$html .= "\n</pre>";
		if ($boxClass) {$html .= "\n</div>";}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the schema as a list with a statement of whether each key is a container
	public static function flattenedXmlWithContainership ($xml)
	{
		# Get the schema as an array
		$schemaXml = self::xml2array ($xml, false, true, $xmlIsFile = false, false, false, false, $skipComments = true);
		
		# Flatten the schema
		require_once ('directories.php');
		$schemaFlattened = directories::flatten ($schemaXml);
		
		# Rearrange as a list which specifies which are containers
		$schema = array ();
		foreach ($schemaFlattened as $path) {
			
			# Determine if the path is a container
			$isContainer = false;
			foreach ($schemaFlattened as $testKey) {
				if (preg_match ("|^{$path}.+$|", $testKey)) {
					$isContainer = true;
					break;	// No point finding any more
				}
			}
			
			# Register this path
			$schema[$path] = $isContainer;
		}
		
		# Return the schema
		return $schema;
	}
	
	
	# Function to generate an XML (hierarchical) representation of a record
	public static function dropSerialRecordIntoSchema ($schema, $record, &$xPathMatches = array (), &$xPathMatchesWithIndex = array (), &$errorHtml = '', &$debugString = '')
	{
		# Start an string to represent the eventual listing
		$xml = '';
		
		# Start a stack
		$stack = array ();
		
		# Start two arrays of xPath matches, for passing back
		$xPathMatches = array ();	// e.g. /foo/bar
		$xPathMatchesWithIndex = array ();	// e.g. /foo/bar[2]; values will always have an index, even if there is only one
		
		# Start a registry of xPath indexes, e.g. /foo => 2, /foo/bar => 1, for use with creating the $xPathMatchesWithIndex array
		$xPathCounts = array ();
		
		# Loop through part of the record
		$errorHtml = '';
		$debugString = '';
		foreach ($record as $lineIndex => $data) {
			$key = $data['field'];
			$value = $data['value'];
			
			# Register the key in the stack
			array_push ($stack, $key);
			
			# Create a string representation of the current state of the stack
			$stackAsString = '/' . implode ('/', $stack) . '/';
			
			# Iterate back up the stack until a match is found
			$stackAsStringBeforeLoop = $stackAsString;
			$debugString .= "\n" . $stackAsStringBeforeLoop;
			while (!isSet ($schema[$stackAsString])) {
				
				# Cache the stack string before changes
				$stackAsStringBefore = $stackAsString;
				
				# Revert the addition of the current item, also remove the previous item in the stack, then re-add the current item
				array_pop ($stack);	// Revert current item
				$closeKey = array_pop ($stack);	// Remove previous item
				array_push ($stack, $key);	// Add current item
				
				# Close the key
				$tabs = str_repeat ("\t", count ($stack) - 1);
				$xml .= "\n{$tabs}</{$closeKey}>";
				
				# Update the stack string
				$stackAsString = '/' . implode ('/', $stack) . '/';
				$debugString .= "\n&mdash;" . $stackAsString;
				
				# Detect unmatchable keys, which cause an infinite loop
				if ($stackAsStringBefore == $stackAsString) {
					$errorHtml = "<p class=\"warning\">PARSE ERROR: The schema processing failed at <strong>{$stackAsStringBeforeLoop}</strong>, indicating an incomplete schema in the area near before this or an incorrect record. Please modify the schema or fix the record.</p>";
					$debugString = trim ($debugString);
					$xml = trim ($xml);
					return $xml;
				}
			}
			
			# Register the match, trimming the final slash to make a proper XPath
			$xPath = rtrim ($stackAsString, '/');
			$xPathMatches[$lineIndex] = $xPath;
			
			# Register this XPath in the counts registry, either creating it or incrementing it
			$xPathCounts[$xPath] = (isSet ($xPathCounts[$xPath]) ? $xPathCounts[$xPath] : 0) + 1;	// XPaths are indexed from 1
			
			# Add the counts version
			$xPathMatchesWithIndex[$lineIndex] = $xPath . '[' . $xPathCounts[$xPath] . ']';
			
			# If a container, open the key
			$isContainer = $schema[$stackAsString];
			if ($isContainer) {
				$tabs = str_repeat ("\t", count ($stack) - 1);
				$xml .= "\n{$tabs}<{$key}>";
				continue;
			}
			
			# It's a value, so write the value, and close the tag, remove it from the stack
			$tabs = str_repeat ("\t", count ($stack) - 1);
			$xml .= "\n{$tabs}<{$key}>";
			$xml .= htmlspecialchars ($value);
			$xml .= "</{$key}>";
			array_pop ($stack);
		}
		
		//application::dumpData ($stack);
		$isContainer = $schema[$stackAsString];
		if ($isContainer) {
			$xml .= htmlspecialchars ($value);
			$xml .= "</{$key}>";
			array_pop ($stack);
		}
		
		# Now release the remainder of the stack, i.e. close the opened tags
		foreach ($stack as $key) {
			$tabs = str_repeat ("\t", count ($stack) - 1);
			$closeKey = array_pop ($stack);
			$xml .= "\n{$tabs}</{$closeKey}>";
		}
		
		# Trim the XML
		$xml = trim ($xml);
		
		# Ensure debugging output is empty
		$errorHtml = '';
		$debugString = '';
		
		# Return the XML
		return $xml;
	}
	
	
	# Function to get an XPath value
	public static function xPathValue ($xml /* of type SimpleXMLElement */, $xPath, $autoPrependRoot = false)
	{
		if ($autoPrependRoot) {
			$xPath = '/root' . $xPath;
		}
		$result = $xml->xpath ($xPath);
		if (!$result) {return false;}
		$value = array ();
		foreach ($result as $node) {
			$value[] = (string) $node;
		}
		$value = implode ($value);
		return $value;
	}
	
	
	# Function to get a set of XPath values for a field known to have multiple entries; these are indexed from 1, mirroring the XPath spec, not 0
	public static function xPathValues ($xml, $xPath, $autoPrependRoot = false, $maxItems = 20)
	{
		# Get each value
		$values = array ();
		for ($i = 1; $i <= $maxItems; $i++) {
			$xPathThisI = str_replace ('%i', $i, $xPath);	// Convert %i to loop ID if present
			$value = self::xPathValue ($xml, $xPathThisI, $autoPrependRoot);
			if (strlen ($value)) {
				$values[$i] = $value;
			}
		}
		
		# Return the values
		return $values;
	}
}



// From: http://uk2.php.net/book.dom
/**
 * basic class for converting an array to xml.
 * @author Matt Wiseman (trollboy at shoggoth.net)
 * License unknown - contact the author
 *
 */
class array2xml {
    
    public $data;
    public $dom_tree;
    
    /**
     * basic constructor
     *
     * @param array $array
     */
    public  function __construct($array){
        if(!is_array($array)){
            throw new Exception('array2xml requires an array', 1);
            //unset($this);
        }
        if(!count($array)){
            throw new Exception('array is empty', 2);
            //unset($this);
        }
        
        $this->data = new DOMDocument('1.0');
        
        $this->dom_tree = $this->data->createElement('result');
        $this->data->appendChild($this->dom_tree);
        $this->recurse_node($array, $this->dom_tree);
    }
    
    /**
     * recurse a nested array and return dom back
     *
     * @param array $data
     * @param dom element $obj
     */
    private function recurse_node($data, $obj){
        $i = 0;
        foreach($data as $key=>$value){
            if(is_array($value)){
                //recurse if neccisary
                $sub_obj[$i] = $this->data->createElement($key);
                $obj->appendChild($sub_obj[$i]);
                $this->recurse_node($value, $sub_obj[$i]);
            } elseif(is_object($value)) {
                //no object support so just say what it is
                $sub_obj[$i] = $this->data->createElement($key, 'Object: "' . $key . '" type: "'  . get_class($value) . '"');
                $obj->appendChild($sub_obj[$i]);
            } else {
                //straight up data, no weirdness
                $sub_obj[$i] = $this->data->createElement($key, $value);
                $obj->appendChild($sub_obj[$i]);
            }
            $i++;
        }
    }
    
    /**
     * get the finished xml
     *
     * @return string
     */
    public function saveXML () {
        return $this->data->saveXML ();
    }
}

?>
