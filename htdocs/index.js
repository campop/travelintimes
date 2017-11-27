var map = L.map('map');

/*
L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
	attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);
*/

/* See: http://wiki.openstreetmap.org/wiki/National_Library_of_Scotland and note that {-y} must be specified as {y} with the tms flag */
L.tileLayer('http://geo.nls.uk/mapdata2/bartholomew/great_britain/{z}/{x}/{y}.png', {
  attribution: 'NLS - Bartholomew Half Inch, 1897-1907',
  tms: true	/* http://leafletjs.com/reference.html#tilelayer-tms */
}).addTo(map);




// https://github.com/perliedman/leaflet-routing-machine/issues/236
// http://www.liedman.net/leaflet-routing-machine/tutorials/interaction/

// http://www.liedman.net/leaflet-routing-machine/tutorials/alternative-routers/
// https://github.com/perliedman/leaflet-routing-machine/issues/200#issuecomment-175082024


function button(label, container) {
    var btn = L.DomUtil.create('button', '', container);
    btn.setAttribute('type', 'button');
    btn.innerHTML = label;
    return btn;
}


var geoPlan = L.Routing.Plan.extend({


        createGeocoders: function() {
            var container = L.Routing.Plan.prototype.createGeocoders.call(this),

                // Create buttons
                button2017 = button('<font color="blue">2017</font>', container);
                button1911 = button('<font color="blue">1911</font>', container);
                button1830 = button('<font color="green">1830</font>', container);
                button1680 = button('<font color="#603">1680</font>', container);


            L.DomEvent.on(button1680, 'click', function() {
		//console.log(control.getRouter().options);
                control.getRouter().options.serviceUrl = 'http://www.travelintimes.org:5000/route/v1';
                control.getRouter().options.useHints = false;
                control.route();
                control.setWaypoints(control.getWaypoints());
                //console.log("1680 route");
                }, this);

            L.DomEvent.on(button1830, 'click', function() {
		//console.log(control.getRouter().options);
                control.getRouter().options.serviceUrl = 'http://www.travelintimes.org:5001/route/v1';
                control.getRouter().options.useHints = false;
                control.route();
                control.setWaypoints(control.getWaypoints());
                //console.log("1830 route");
                }, this);

            L.DomEvent.on(button1911, 'click', function() {
		//console.log(control.getRouter().options);
                control.getRouter().options.serviceUrl = 'http://www.travelintimes.org:5002/route/v1';
                control.getRouter().options.useHints = false;
                control.route();
                control.setWaypoints(control.getWaypoints());
                //console.log("1911 route");
                }, this);

            L.DomEvent.on(button2017, 'click', function() {
		//console.log(control.getRouter().options);
		// https://github.com/openstreetmap/openstreetmap-website/blob/master/config/example.application.yml#L101
                control.getRouter().options.serviceUrl = 'https://router.project-osrm.org/route/v1';
                control.getRouter().options.useHints = false;
                control.route();
                control.setWaypoints(control.getWaypoints());
                //console.log("2017 route");
                }, this);



            return container;
            }
    });



var plan = new geoPlan([
        L.latLng(52.2, 0.2),
        L.latLng(51.5, 0.1)
    ], {
        geocoder: L.Control.Geocoder.nominatim(),
        routeWhileDragging: true
});


var control = L.Routing.control({
	serviceUrl: 'http://www.travelintimes.org:5000/route/v1',
        routeWhileDragging: true,
        plan: plan
}).addTo(map);



/*
var plan = new geoPlan(
    [],
    {
	waypoints: [
		L.latLng(57.74, 11.94),
		L.latLng(57.6792, 11.949)
	],
        geocoder: L.Control.Geocoder.nominatim(),
        routeWhileDragging: true,
});
*/

/*
control2 = L.Routing.control({

        routeWhileDragging: true,
//        router: L.Routing.graphHopper('key'),
        plan: plan
}).addTo(map);
*/





/*
var control = L.Routing.control(L.extend(window.lrmConfig, {
	waypoints: [
		L.latLng(57.74, 11.94),
		L.latLng(57.6792, 11.949)
	],
	geocoder: L.Control.Geocoder.nominatim(),
	routeWhileDragging: true,
	reverseWaypoints: true,
	showAlternatives: true,
	altLineOptions: {
		styles: [
			{color: 'black', opacity: 0.15, weight: 9},
			{color: 'white', opacity: 0.8, weight: 6},
			{color: 'blue', opacity: 0.5, weight: 2}
		]
	}
})).addTo(map);


L.Routing.errorControl(control2).addTo(map);
*/
