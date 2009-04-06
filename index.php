<?php
/**
 * This script implements a simple REST web service that supports four actions:
 *
 *		search_users
 *				query	A search string to match against, should not include wild cards.
 *				ticket	The proxy ticket.
 *
 *		search_groups
 *				query	A search string to match against, should not include wild cards.
 *				include_members		'true' or 'false', default is false.
 *				ticket	The proxy ticket.
 *
 *		get_user
 *				id		The user id to return
 *				ticket	The proxy ticket.
 *
 *		get_group
 *				id		The group id to return
 *				include_members		'true' or 'false', default is false.
 *				ticket	The proxy ticket.
 *
 * All actions require a proxy ticket to be specified as a query parameter. E.g:
 *		http://directory.example.com/?action=search_users&query=John%20Doe&ticket=PT-957-ZuucXqTZ1YcJw81T3dxf
 *
 * All arguments should be passed as query parameters. The following status codes will
 * be returned on error:
 *		
 *		400 Bad Request						- One or more of the required parameters was missing or invalid.
 *		403 Forbidden						- Proxy Authentication succeeded, but the user is not authorized
 *											  to execute the request.
 *		404 Not Found						- The specified action or id was not found.
 *		407 Proxy Authentication Required	- Proxy Authentication was not successful. 
 *
 *
 * @since 3/24/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */ 

$name = preg_replace('/[^a-z0-9_-]/i', '', dirname($_SERVER['SCRIPT_NAME']));
session_name($name);

session_start();


require_once(dirname(__FILE__).'/config.inc.php');
require_once(dirname(__FILE__).'/lib/HarmoniException.class.php');
require_once(dirname(__FILE__).'/lib/ErrorPrinter.class.php');
require_once(dirname(__FILE__).'/lib/LdapConnector.class.php');
require_once(dirname(__FILE__).'/lib/DomXmlPrinter.class.php');
require_once(dirname(__FILE__).'/lib/phpcas/source/CAS.php');

try {
	
	/*********************************************************
	 * Do proxy authentication and return an error state if
	 * authentication fails.
	 *********************************************************/
	// set debug mode
	phpCAS::setDebug();
	
	// initialize phpCAS
	phpCAS::client(CAS_VERSION_2_0, CAS_HOST, CAS_PORT, CAS_PATH, false);
	
	// no SSL validation for the CAS server
	phpCAS::setNoCasServerValidation();
	
	// force CAS authentication
	phpCAS::forceAuthentication();
	
	/*********************************************************
	 * Parse/validate our arguments and run the specified action.
	 *********************************************************/
	if (!isset($_GET['action']))
		throw new UnknownActionException('No action specified.');	
	
	if (SHOW_TIMERS)
		$start = microtime();
	
	$cacheKey = $name.':';
	foreach ($_GET as $key => $val) {
		$cacheKey .= '&'.$key.'='.$val;
	}
	$xmlString = apc_fetch($cacheKey);
	if ($xmlString === false) {
	
		$results = array();
		foreach ($ldapConfig as $connectorConfig) {
			$connector = new LdapConnector($connectorConfig);
			$connector->connect();
			 switch ($_GET['action']) {
				case 'search_groups':
					$results = array_merge($results, $connector->searchGroups($_GET));
					break;
				case 'search_users':
					$results = array_merge($results, $connector->searchUsers($_GET));
					break;
				case 'search_users_by_attributes':
					$results = array_merge($results, $connector->searchUsersByAttributes($_GET));
					break;
				case 'get_group':
					$results = array_merge($results, array($connector->getGroup($_GET)));
					break;
				case 'get_user':
					$results = array_merge($results, array($connector->getUser($_GET)));
					break;
				case 'get_group_members':
					$results = array_merge($results, $connector->getGroupMembers($_GET));
					break;
				default:
					throw new UnknownActionException('action, \''.$_GET['action'].'\' is not one of [search_users, search_groups, get_user, get_group].');
			}
			$connector->disconnect();
		}
		
		if (SHOW_TIMERS)
			$end = microtime();
			
		$printer = new DomXmlPrinter;
		$xmlString = $printer->getOutput($results);
		
		apc_store($cacheKey, $xmlString, RESULT_CACHE_TTL);
	}
	
	@header('Content-Type: text/plain');
	print $xmlString;
		
	if (SHOW_TIMERS) {
		$end2 = microtime();
		
		list($sm, $ss) = explode(" ", $start);
		list($em, $es) = explode(" ", $end);
		$s = $ss + $sm;
		$e = $es + $em;
		print "\n<time>".($e-$s)."</time>";
		list($sm, $ss) = explode(" ", $end);
		list($em, $es) = explode(" ", $end2);
		$s = $ss + $sm;
		$e = $es + $em;
		print "\n<output_time>".($e-$s)."</output_time>";
		print "\n<number>".(count($results))."</number>";
	}
	
// Handle certain types of uncaught exceptions specially. In particular,
// Send back HTTP Headers indicating that an error has ocurred to help prevent
// crawlers from continuing to pound invalid urls.
} catch (UnknownActionException $e) {
	ErrorPrinter::handleException($e, 404);
} catch (NullArgumentException $e) {
	ErrorPrinter::handleException($e, 400);
} catch (PermissionDeniedException $e) {
	ErrorPrinter::handleException($e, 403);
} catch (UnknownIdException $e) {
	ErrorPrinter::handleException($e, 404);
}
// Default 
catch (Exception $e) {
	ErrorPrinter::handleException($e, 500);
}