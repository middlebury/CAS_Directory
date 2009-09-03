Copyright &copy; 2009, Middlebury College
License: http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)

Author: 	Adam Franco
Date:		2009-04-06

-----------------------

For documentation on this service, see:

https://mediawiki.middlebury.edu/wiki/LIS/CAS_Directory

== Installation ==
1. Copy config.inc.php.sample to config.inc.php
2. Change config options as appropriate.
3. Make the index.php accessible on a websever.



== Change-Log ==
0.4.1
	- Added support for clearing the cache on notification from an external service.
	
0.4.0
	- Added support for a new 'get_all_users' action to allow user-accounts to be
	  synced with remote systems.

0.3.1
	- Moved the PHPCAS path out of the config as it is now included as a submodule.

0.3.0
	- Added support for returning only attributes specified in the CAS Services Management tool.
	- Added an 'include_membership' parameter to all requests allowing applications that aren't
	  interested in the group-membership of users to avoid wasting time fetching that data.
	- Group membership requests now traverse AD groups to return parent groups.