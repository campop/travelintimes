#!/usr/bin/env Rscript

# Example usage:
# E.g. ./isochrone.R 5000 0.1276 51.5072


# Get arguments from STDIN
input = readLines(file("stdin"))
args = c(strsplit (input, " +")[[1]])

#	# Get arguments from command line
#	args = commandArgs(trailingOnly=TRUE)

# Validate arguments
if (length(args) < 3) {
	stop('You must supply port,lon,lat arguments.')
}

# Assign arguments
port = args[1]
lon = as.double(args[2])
lat = as.double(args[3])

# Obtain Isochrone
# https://www.rdocumentation.org/packages/osrm/versions/4.1.1/topics/osrmIsochrone
# See also: https://rstudio-pubs-static.s3.amazonaws.com/830357_110c080452d243758215dc7f0f8f8ea7.html
# See also: http://www.eroubenoff.net/2021-03-11-orsm_isochrone/
library('osrm')
isochroneSf = osrmIsochrone(
	loc = c(lon, lat),
	breaks = c(0, 120, 240, 480, 720, 1440, 4320, 10080),
	res = 200,
	osrm.server = sprintf('http://www.travelintimes.org/routing/%s/', port),
#	osrm.server = 'http://www.travelintimes.org:5002/',
	osrm.profile = 'driving'
)

# Convert to GeoJSON; see: https://cran.r-project.org/web/packages/geojsonsf/vignettes/geojson-sf-conversions.html
library('geojsonsf')
sf_geojson(isochroneSf)

# Output of last function will be returned
