#!/bin/bash

# Usage:
if [ "$1" == "-h" ]; then
  echo "Usage: `basename $0` softwareroot strategy datafile port"
  echo "E.g. ./build.sh /opt multimodal1911 /opt/travelintimes/exports/multimodal191120161031.zip 5000"
  exit 0
fi

# Bomb out if something goes wrong
set -e

# Get the script directory see: http://stackoverflow.com/a/246128/180733
# The multi-line method of geting the script directory is needed to enable the script to be called from elsewhere.
SOURCE="${BASH_SOURCE[0]}"
DIR="$( dirname "$SOURCE" )"
while [ -h "$SOURCE" ]
do
SOURCE="$(readlink "$SOURCE")"
	[[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE"
	DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
SCRIPTDIRECTORY=$DIR


# Obtain the arguments - the routing strategy to use (e.g. multimodal1911) and the source data file
if [ "$#" -ne 4 ]; then
	echo "ERROR: You must supply four parameters: a path to the software a strategy, a datafile, and a port (e.g. 5000)"
	exit 1
fi
softwareRoot=$1
strategy=$2
datafile=$3
port=$4

# Create the working area, versioned by strategy then datetime (e.g. 20161101-113308)
datetime=`date +'%Y%m%d-%H%M%S'`
buildDirectory="./enginedata/${strategy}/${datetime}"
mkdir -p $buildDirectory

# Copy in the data file
cp -p $datafile "${buildDirectory}/"

# Save a copy of the current profile definition and tag transform definition to the build directory, as an archive
cp -p "${SCRIPTDIRECTORY}/configuration/routingprofiles/profile-${strategy}.lua" "${buildDirectory}/profile.lua"
cp -p "${SCRIPTDIRECTORY}/configuration/tagtransform/tagtransform.xml" "${buildDirectory}/"

# Do all work in the build directory
cd $buildDirectory

# Unpack the data; several formats supported
if [ -e *.zip ]; then
	unzip -o *.zip
fi
if [ -e *.gz ]; then
	unzip -o *.gz
fi
if [ -e *.7z ]; then
	7za e *.7z
fi
ls -lAF

# Determine the PROJ4 string
#!# Needs to be extracted automatically
###gdalsrsinfo *Edges.prj
gdalsrsinfo *.prj
# Returns:
# PROJ.4 : '+proj=longlat +datum=WGS84 +no_defs '
proj4='+proj=longlat +datum=WGS84 +no_defs'

# Convert each Shapefile to WGS84; see: http://www.mercatorgeosystems.com/blog-articles/2008/05/30/using-ogr2ogr-to-re-project-a-shape-file/
## Already in WGS84
##for file in *.shp ; do
##	ogr2ogr -f "ESRI Shapefile" $file ${file}-wgs84.shp -s_srs EPSG:27700 -t_srs EPSG:4326
##done

# Determine the edges Shapefile
###edgesShapefile=`find . -maxdepth 1 -name '*Edges.shp'`
#edgesShapefile=`find . -maxdepth 1 -name '*.shp'`
#edgesShapefile=Multimodal.shp
edgesShapefile=multimodal.shp

# Determine the bbox of the edges Shapefile
# See "ogr_extent" at https://github.com/dwtkns/gdal-cheat-sheet
extent=$( ogrinfo -al -so $edgesShapefile | grep Extent | sed 's/Extent: //g' | sed 's/(//g' | sed 's/)//g' | sed 's/ - /, /g' )
bboxWsen=`echo $extent | awk -F ',' '{print $1 " " $2 " " $3 " " $4}'`
echo "BBOX is: ${bboxWsen}"

# Convert each Shapefile to OSM (.osm) format in WGS84 projection
# Version and timestamp are necessary because otherwise Osmosis will complain "Node xxx does not have a version attribute as OSM 0.6 are required to have.  Is this a 0.5 file?"
# See reference to undocumented options at: https://sourceforge.net/p/vectormap2garmin/wiki/ogr2osm/
# File is saved as converted/<basename>.shp.osm
for file in *.shp ; do
#	ogr2osm --epsg=4326 --proj4="'${proj4}'" --add-version --add-timestamp $file
	#rm "${file/.shp/.osm}"
	#!# -f used to overwrite whatever was there before; ideally should be cleaning out the directory instead
	ogr2osm -f --epsg=4326 --gis-order --add-version --add-timestamp $file
	mv "${file/.shp/.osm}" "${file/.shp/.shp.osm}"
done
##cd ../

# Convert tags
# See: https://wiki.openstreetmap.org/wiki/Osmosis/Detailed_Usage_0.48
# See: https://wiki.openstreetmap.org/wiki/Osmosis/TagTransform
# Avoid using highway=motorway as that will give an implied oneway=yes; see: http://wiki.openstreetmap.org/wiki/Key:highway
##cd converted/
###for file in *.shp.osm ; do
	file="${edgesShapefile}.osm"
##	rm -f "${file/.shp.osm/.osm}"
	osmosis --read-xml file="./${file}" --tag-transform file="./tagtransform.xml" --write-xml file="${file/.shp.osm/.osm}"
###done
##cd ../

# Merge files
# Note: --sort is needed after each --rx as noted here: https://www.mail-archive.com/osmosis-dev@openstreetmap.org/msg00319.html to avoid "org.openstreetmap.osmosis.core.OsmosisRuntimeException: Pipeline entities are not sorted, previous entity type=Node, id=-2, version=1 current entity type=Node, id=-3, version=1."
#osmosis --rx Railway.osm --sort --rx InlandWways.osm --sort --merge --wx merged.osm
# Alternative method: osmconvert Railway.osm InlandWways.osm -o=merged.osm
##cd converted/
###osmosis --rx *Edges.osm --sort --rx *Junctions.osm --sort --merge --wx merged.osm
mv "${file/.shp.osm/.osm}" merged.osm
file=merged.shp.osm
##cd ../

# Convert OSM file to using positive integers for node/way IDs
# This is necessary as otherwise the osrm-contact phase will complain about invalid OSM IDs due to ulong_long expected: https://github.com/Project-OSRM/osrm-backend/blob/5a311012afd39d96700b2a039f867cccf3892c08/src/contractor/contractor.cpp#L326
sed -i -e 's/node id="-/node id="/' -e 's/way id="-/way id="/' -e 's/nd ref="-/nd ref="/' "${SCRIPTDIRECTORY}/${buildDirectory}/merged.osm"

# Write the turn penalties file
#!# This currently assumes that turns are always wanted - an intended lack of matches will cause the build to terminate
php "${SCRIPTDIRECTORY}/buildTurnsData.php" "${SCRIPTDIRECTORY}/${buildDirectory}" "${SCRIPTDIRECTORY}/configuration/turns/turns-${strategy}.csv"
echo "Turns file written to ${SCRIPTDIRECTORY}/${buildDirectory}/penalties.csv"

# Build a routing graph; the routing profile needs to be the OSRM profiles directory, as that contains lib/access.lua and other dependencies listed in the profile
cp "${SCRIPTDIRECTORY}/configuration/routingprofiles/profile-${strategy}.lua" "$softwareRoot/osrm-backend/profiles/latest-build-profile.lua"
echo "Starting OSRM extraction and contraction using ${SCRIPTDIRECTORY}/${buildDirectory}/${file/.shp.osm/.osm}..."
cd $softwareRoot/osrm-backend/build/
# Turn penalties system (using --generate-edge-lookup and then --turn-penalty-file) documented at: https://github.com/Project-OSRM/osrm-backend/wiki/Traffic#turn-penalty-data
./osrm-extract "${SCRIPTDIRECTORY}/${buildDirectory}/${file/.shp.osm/.osm}" -p "$softwareRoot/osrm-backend/profiles/latest-build-profile.lua" --generate-edge-lookup
./osrm-contract "${SCRIPTDIRECTORY}/${buildDirectory}/${file/.shp.osm/.osrm}" --turn-penalty-file "${SCRIPTDIRECTORY}/${buildDirectory}/penalties.csv"
cd -

# Start the router process in the background, killing any previous instantiation if running
# E.g. $softwareRoot/osrm-backend/build/osrm-routed converted/Transportations.osrm &
if pgrep -f "osrm-routed -p ${port}"; then pkill -f "osrm-routed -p ${port}"; fi
$softwareRoot/osrm-backend/build/osrm-routed -p $port "${SCRIPTDIRECTORY}/${buildDirectory}/${file/.shp.osm/.osrm}" > "${SCRIPTDIRECTORY}/logs-osrm/osrm-${strategy}.log" &
echo "Running $softwareRoot/osrm-backend/build/osrm-routed -p $port ${buildDirectory}/${file/.shp.osm/.osrm}"

# Generate a GeoJSON file of the network
osmtogeojson "${SCRIPTDIRECTORY}/${buildDirectory}/merged.osm" > "${SCRIPTDIRECTORY}/${buildDirectory}/merged.geojson"
# Filter unwanted features using ndjson-cat; see: https://github.com/mbostock/ndjson-cli and https://medium.com/@mbostock/command-line-cartography-part-2-c3a82c5c0f3
#   Reformat file to newline-delimited JSON; see: http://www.roblabs.com/ndjson/
ndjson-cat "${SCRIPTDIRECTORY}/${buildDirectory}/merged.geojson" | ndjson-split 'd.features' > "${SCRIPTDIRECTORY}/${buildDirectory}/merged.ndjson"
#   Filter unwanted properties file
ndjson-filter 'delete d.id, true' < "${SCRIPTDIRECTORY}/${buildDirectory}/merged.ndjson" \
	| ndjson-filter 'delete d.properties.id, true' \
	| ndjson-filter 'delete d.properties.timestamp, true' \
	| ndjson-filter 'delete d.properties.version, true' \
	| ndjson-filter 'delete d.properties.Shape_Leng, true' \
	| ndjson-filter 'delete d.properties.Type, true' \
	| ndjson-filter 'delete d.properties.Mode, true' \
	| ndjson-filter 'delete d.properties.Name, true' \
	> "${SCRIPTDIRECTORY}/${buildDirectory}/filtered.ndjson"
#   Convert back to GeoJSON
ndjson-reduce < "${SCRIPTDIRECTORY}/${buildDirectory}/filtered.ndjson" | ndjson-map '{type: "FeatureCollection", features: d}' > "${SCRIPTDIRECTORY}/${buildDirectory}/${filtered}.geojson"
geojson-precision -p 4 "${SCRIPTDIRECTORY}/${buildDirectory}/${filtered}.geojson" "${SCRIPTDIRECTORY}/${buildDirectory}/${filtered}-p4.geojson"
cp -p "${SCRIPTDIRECTORY}/${buildDirectory}/${filtered}-p4.geojson" "${softwareRoot}/travelintimes/htdocs/geojson/${strategy}.geojson"


exit



# Generate mapnik image; uses merged.osm
# From: https://github.com/openstreetmap/mapnik-stylesheets/blob/master/generate_image.py
#bboxWsen="10.72265625 50.064191736 2.724609375 59.26588062825"
imageFilename=network.png
# https://github.com/mapnik/mapnik/wiki/XMLConfigReference
mapnikFile="${SCRIPTDIRECTORY}/configuration/mapnikstylesheet/mapnikstylesheet.xml"
"${SCRIPTDIRECTORY}/generate_image.py" "${softwareRoot}/travelintimes/htdocs/geojson/${imageFilename}" $mapnikFile $bboxWsen
echo "Image now at http://www.travelintimes.org/geojson/${imageFilename}"




# Generate tiles
export MAPNIK_MAP_FILE=$mapnikFile
tilesDirectory="./tiles/"
rm -rf $tilesDirectory
mkdir $tilesDirectory
export MAPNIK_TILE_DIR=$tilesDirectory
# Testing with smaller BBOX:
bboxWsen="0.057 52.163 0.197 52.24"
# generate_tiles seems to segfault sometimes, so run in a loop; see: http://askubuntu.com/questions/572820/re-run-an-application-script-when-it-crashes
set +e
result=1
while [ $result -ne 0 ]; do
	./generate_tiles.py $bboxWsen 0 15 "Cambridgeshire"
	result=$?
	sleep 1
done
set -e

