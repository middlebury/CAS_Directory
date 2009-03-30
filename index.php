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

require_once(dirname(__FILE__).'/config.inc.php');
require_once(dirname(__FILE__).'/lib/HarmoniException.class.php');
require_once(dirname(__FILE__).'/lib/ErrorPrinter.class.php');
require_once(dirname(__FILE__).'/lib/LdapConnector.class.php');

try {	 
	 /*********************************************************
	  * Do proxy authentication and return an error state if
	  * authentication fails
	  *********************************************************/
	 // @todo
	 
	 /*********************************************************
	  * Parse/validate our arguments and run the specified action.
	  *********************************************************/
	 if (!isset($_GET['action']))
	 	throw new UnknownActionException('No action specified.');
	 
	 switch ($_GET['action']) {
		case 'search_groups':
			if (isset($_GET['include_members']) && $_GET['include_members'] != 'true' && $_GET['include_members'] != 'false')
				throw new InvalidArgumentException("include_members must be 'true' or 'false'");
		case 'search_users':
			if (!isset($_GET['query']))
				throw new NullArgumentException('You must specify a query');
			// Match a search string that might match a username, email address, first and/or last name.
			if (!preg_match('/^[a-z0-9_,.\'&\s@-]+$/i', $_GET['query']))
				throw new InvalidArgumentException("query '".$_GET['query']."' is not valid format.");
			break;
		case 'get_group':
			if (isset($_GET['include_members']) && $_GET['include_members'] != 'true' && $_GET['include_members'] != 'false')
				throw new InvalidArgumentException("include_members must be 'true' or 'false'");
			if (!isset($_GET['id']))
				throw new NullArgumentException('You must specify an id');
			// Match a group DN
			if (!preg_match('/^[a-z0-9_=,.\'&\s-]$/i', $_GET['id']))
				throw new InvalidArgumentException("id '".$_GET['id']."' is not valid format.");
			break;
		case 'get_user':
			if (!isset($_GET['id']))
				throw new NullArgumentException('You must specify an id');
			// Match a numeric ID
			if (!preg_match('/^[0-9]+$/', $_GET['id']))
				throw new InvalidArgumentException("id '".$_GET['id']."' is not valid format.");
			break;
		default:
			throw new UnknownActionException('action, \''.$_GET['action'].'\' is not one of [search_users, search_groups, get_user, get_group].');
	}
	
	
	$results = array();
	foreach ($ldapConfig as $connectorConfig) {
		$connector = new LdapConnector($connectorConfig);
		$connector->connect();
		 switch ($_GET['action']) {
			case 'search_groups':
				$results = array_merge($results, $connector->searchGroups($_GET['query'], $_GET['include_members']));
				break;
			case 'search_users':
				$results = array_merge($results, $connector->searchUsers($_GET['query']));
				break;
			case 'get_group':
				$results = array_merge($results, $connector->getGroup($_GET['id'], $_GET['include_members']));
				break;
			case 'get_user':
				$results = array_merge($results, $connector->getUser($_GET['id']));
				break;
			default:
				throw new UnknownActionException('action, \''.$_GET['action'].'\' is not one of [search_users, search_groups, get_user, get_group].');
		}
		$connector->disconnect();
	}
	
	@header('Content-Type: text/plain');

	print_r($results);

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