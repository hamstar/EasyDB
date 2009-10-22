# Changelog

## Version 0.4.1 (22 Oct 2009)
* Removed RETURN_OBJECT definition.  All rows are now returned as objects and not associative arrays
* Fixed a bug in the findall/find methods where the id was not returned
* Changed the read method to always return an array

## Version 0.4 (22 Oct 2009)
* Added dsn function to set login details on the fly
* Fixed a bug whereby the connect() method was using the wrong password var

## Version 0.3
* Fixed a bug in the getWhereString and escape functions

## Version 0.2
* Added a field argument to the find and findall function so that different id field names can be used.
* Added a fetch function to return rows depending on the RETURN_OBJECT constant and the number of rows returned
* Added RETURN_OBJECT constant to set returning objects (mysql_fetch_object) or return associative arrays (mysql_fetch_assoc)

## Version 0.1
* Converted from a function based library to a class library.

