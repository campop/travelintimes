Historic planner control panel
==============================

This is a PHP application which implements a web GUI for management of the historic planner.


Screenshot
----------

![Screenshot](screenshot.png)


Usage
-----

1. Clone the repository.
2. Download the library dependencies and ensure they are in your PHP include_path.
3. Download and install the famfamfam icon set in /images/icons/
4. Add the Apache directives in httpd.conf (and restart the webserver) as per the example given in .httpd.conf.extract.txt; the example assumes mod_macro but this can be easily removed.
5. Create a copy of the index.html.template file as index.html, and fill in the parameters.
6. Access the page in a browser at a URL which is served by the webserver.


Dependencies
------------

* [application.php application support library](http://download.geog.cam.ac.uk/projects/application/)
* [directories.php directory manipulation library](http://download.geog.cam.ac.uk/projects/directories/)
* [frontControllerApplication.php front controller application implementation library](http://download.geog.cam.ac.uk/projects/frontcontrollerapplication/)
* [pureContent.php general environment library](http://download.geog.cam.ac.uk/projects/purecontent/)
* [ultimateForm.php form library](http://download.geog.cam.ac.uk/projects/ultimateform/)
* [xml.php XML support library](http://download.geog.cam.ac.uk/projects/xml/)
* [FamFamFam Silk Icons set](http://www.famfamfam.com/lab/icons/silk/)


Author
------

Martin Lucas-Smith, 2016.


License
-------

GPL2.

