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
require_once(dirname(__FILE__).'/lib/phpcas/source/CAS.php');
require_once(dirname(__FILE__).'/lib/AntPath.php');
require_once(dirname(__FILE__).'/lib/AuthManager.class.php');
require_once(dirname(__FILE__).'/lib/HeaderTokenAuth.class.php');
require_once(dirname(__FILE__).'/lib/RequestTokenAuth.class.php');
require_once(dirname(__FILE__).'/lib/CasAuth.class.php');
require_once(dirname(__FILE__).'/lib/HarmoniException.class.php');
require_once(dirname(__FILE__).'/lib/ErrorPrinter.class.php');
require_once(dirname(__FILE__).'/lib/LdapConnector.class.php');
require_once(dirname(__FILE__).'/lib/DomXmlPrinter.class.php');
require_once(dirname(__FILE__).'/lib/functions.php');

if (!defined('SHOW_TIMERS_IN_OUTPUT'))
	define('SHOW_TIMERS_IN_OUTPUT', false);
if (!defined('SHOW_TIMERS'))
	define('SHOW_TIMERS', false);
if (!defined('DISPLAY_ERROR_BACKTRACE'))
	define('DISPLAY_ERROR_BACKTRACE', false);
if (!defined('ALL_USERS_MEMORY_LIMIT'))
	define('ALL_USERS_MEMORY_LIMIT', '300M');
if (!defined('ALL_USERS_PAGE_SIZE'))
	define('ALL_USERS_PAGE_SIZE', '100');
if (!defined('ALLOW_URL_TOKEN_AUTHENTICATION'))
	define('ALLOW_URL_TOKEN_AUTHENTICATION', false);
if (!defined('ALLOW_CAS_AUTHENTICATION'))
	define('ALLOW_CAS_AUTHENTICATION', false);

try {
	$proxy = null;

	$authManager = new AuthManager();
	$authManager->addAuth(new HeaderTokenAuth($admin_access_keys));
	if (ALLOW_URL_TOKEN_AUTHENTICATION) {
		$authManager->addAuth(new RequestTokenAuth($admin_access_keys));
	}

	// Initialize phpCAS
	if (ALLOW_CAS_AUTHENTICATION) {
		// set debug mode
		if (defined('PHPCAS_DEBUG_FILE')) {
			if (PHPCAS_DEBUG_FILE) {
				phpCAS::setDebug(PHPCAS_DEBUG_FILE);
			}
		}
		// initialize phpCAS
		phpCAS::client(CAS_VERSION_2_0, CAS_HOST, CAS_PORT, CAS_PATH, false);
		// no SSL validation for the CAS server
		phpCAS::setNoCasServerValidation();

		$authManager->addAuth(new CasAuth($cas_allowed_groups));

		// Trigger CAS authentication if we have a `login` parameter.
		if (!empty($_GET['login'])) {
			phpCAS::forceAuthentication();
			// Strip out the login parameter.
			$params = $_GET;
			unset($params['login']);
			unset($params['ADMIN_ACCESS']);
			header('Location: '.$_SERVER['SCRIPT_URI'].'?'.http_build_str($params));
			exit;
		}
	}

	$authManager->authenticateAndAuthorize();

	// Strip out the login parameter.
	if (!empty($_GET['login'])) {
		$params = $_GET;
		unset($params['login']);
		header('Location: '.$_SERVER['SCRIPT_URI'].'?'.http_build_str($params));
		exit;
	}

	// Allow clearing of the APC cache for authenticated users.
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'clear_cache') {
		apc_clear_cache('user');
		print "Cache Cleared";
		exit;
	}

	if (ALLOW_CAS_AUTHENTICATION && phpCAS::isAuthenticated()) {
		// If we are being proxied, limit the the attributes to those allowed to
		// be passed to the proxying application. As defined in the CAS Protocol
		//   http://www.jasig.org/cas/protocol
		// The first proxy listed is the most recent in the request chain. Limit
		// to that services' allowed attributes.
		$proxies = phpCAS::getProxies();
		if (count($proxies)) {
			$proxy = $proxies[0];
		}
	}

	/*********************************************************
	 * Parse/validate our arguments and run the specified action.
	 *********************************************************/
	if (!isset($_GET['action']))
		throw new UnknownActionException('No action specified.');

	if (SHOW_TIMERS)
		$start = microtime();

	// Add our proxy to the cache-key in case we are limiting attributes based on it
	$cacheKey = getCacheKey($_GET, $proxy);

	$xmlString = apc_fetch($cacheKey);
	if ($xmlString === false) {

		// If we are being proxied, limit the the attributes to those allowed to
		// be passed to the proxying application. As defined in the CAS Protocol
		//   http://www.jasig.org/cas/protocol
		// The first proxy listed is the most recent in the request chain. Limit
		// to that services' allowed attributes.
		if (isset($proxy)) {
			if (!isset($serviceRegistryPath))
				throw new Exception('No $serviceRegistryPath specified.');

			// Determine which service represents the proxying application.
			// Scan through json service registry files and find matching serviceId.
			// If proxy service is found, retrieve allowedAttributes from json file.
			$serviceFiles = scandir($serviceRegistryPath);
			foreach ($serviceFiles as $serviceFile) {
			  if($serviceFile)
			  {
          $serviceFileContents = file_get_contents($serviceRegistryPath . '/' . $serviceFile);
          $serviceData = json_decode($serviceFileContents,true);
          $serviceId = $serviceData['serviceId'];
          if($serviceId)
          {
            $path = new AntPath($serviceId);
            if ($path->matches($proxy)) {
              $attributes = $serviceData['attributeReleasePolicy']['allowedAttributes'][1];
              if($attributes){
                $matchingServices[]= array(
                    "serviceId" => $serviceId,
                    "allowedAttributes" => $attributes,
                );
              }else{
                $matchingServices[]= array(
                    "serviceId" => $serviceId,
                    "allowedAttributes" => array(),
                );
              }
            }
          }
        }
      }

			// Fetch the list of allowed attributes.
			if (count($matchingServices)) {
        $allowedAttributes = $matchingServices[0]['allowedAttributes'];
			} else {
				$allowedAttributes = array();
			}

			// Remove any disallowed attributes from the list
			foreach ($ldapConfig as $i => $connectorConfig) {
				foreach ($ldapConfig[$i]['UserAttributes'] as $ldapAttr => $casAttr) {
					if (!in_array($casAttr, $allowedAttributes)) {
// 						print "\nRemoving $ldapAttr => $casAttr\n";
						unset($ldapConfig[$i]['UserAttributes'][$ldapAttr]);
					}
				}
				foreach ($ldapConfig[$i]['GroupAttributes'] as $ldapAttr => $casAttr) {
					if (!in_array($casAttr, $allowedAttributes)) {
						unset($ldapConfig[$i]['GroupAttributes'][$ldapAttr]);
					}
				}
			}
		}

		// Paginate the results from the user list.
		if ($_GET['action'] == 'get_all_users') {
			$minBytes = return_bytes(ALL_USERS_MEMORY_LIMIT);
			if (return_bytes(ini_get('memory_limit')) < $minBytes)
				ini_set('memory_limit', $minBytes);

			if (!isset($_GET['page']))
				$page = 0;
			else
				$page = intval($_GET['page']);

			if ($page < 0)
				throw new InvalidArgumentException("'page' must be 0 or greater.");

			$xmlString  = getAllUsersPageXml($ldapConfig, $page, $proxy);

			if (SHOW_TIMERS)
				$end = microtime();
		}
		// Normal case for most actions.
		else {
			$results = loadAllResults($ldapConfig);

			if (SHOW_TIMERS)
				$end = microtime();

			$xmlString = getResultXml($results, $_GET, $proxy);
		}
	}

	if (SHOW_TIMERS) {
		if (!isset($end))
			$end = microtime();
		list($sm, $ss) = explode(" ", $start);
		list($em, $es) = explode(" ", $end);
		$s = $ss + $sm;
		$e = $es + $em;
		@header('X-Runtime: '.($e-$s));
	}

 	@header('Content-Type: text/xml');
//	@header('Content-Type: text/plain');
	print $xmlString;

	if (SHOW_TIMERS_IN_OUTPUT) {
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
} catch (InvalidArgumentException $e) {
	ErrorPrinter::handleException($e, 400);
} catch (PermissionDeniedException $e) {
	$params = $_GET;
	$params['login'] = 'true';
	unset($params['ADMIN_ACCESS']);
	$additionalHtml = "Users can <a href='".$_SERVER['SCRIPT_URI'].'?'.http_build_str($params)."'>login with CAS</a>.";
	ErrorPrinter::handleException($e, 403, $additionalHtml);
} catch (UnknownIdException $e) {
	ErrorPrinter::handleException($e, 404);
}
// Default
catch (Exception $e) {
	ErrorPrinter::handleException($e, 500);
}
