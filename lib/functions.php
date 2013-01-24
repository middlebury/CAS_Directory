<?php
/**
 * @since 7/30/09
 * @package CASDirectory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */ 

/**
 * Load all results
 * 
 * @param array $ldapConfig
 * @return array
 * @access public
 * @since 7/30/09
 */
function loadAllResults (array $ldapConfig) {
	$results = array();
	foreach ($ldapConfig as $connectorConfig) {
		$connector = new LdapConnector($connectorConfig);
		$connector->connect();
		$error  = '';
		
		try {
			switch ($_GET['action']) {
				case 'search_groups':
					$results = array_merge($results, $connector->searchGroups($_GET));
					break;
				case 'search_users':
					$results = array_merge($results, $connector->searchUsers($_GET));
					break;
				case 'search_users_by_attributes':
					try {
						$results = array_merge($results, $connector->searchUsersByAttributes($_GET));
					} catch (NullArgumentException $e) {
						$error = $e->getMessage();
					}
					break;
				case 'get_group':
					try {
						$results = array_merge($results, array($connector->getGroup($_GET)));
					} catch (UnknownIdException $e) {
						$error = $e->getMessage();
					}
					break;
				case 'get_user':
					try {
						$results = array_merge($results, array($connector->getUser($_GET)));
					} catch (UnknownIdException $e) {
						$error = $e->getMessage();
					}
					break;
				case 'get_group_members':
					try {
						$results = array_merge($results, $connector->getGroupMembers($_GET));
					} catch (UnknownIdException $e) {
						$error = $e->getMessage();
					}
					break;
				case 'get_all_users':
					$results = array_merge($results, $connector->getAllUsers($_GET));
					break;
				default:
					throw new UnknownActionException('action, \''.$_GET['action'].'\' is not one of [search_users, search_groups, get_user, get_group].');
			}
		} catch (LDAPException $e) {
			if ($e->getCode() != 10) // 10 = LDAP_REFERRAL 
				throw $e;
		}
		$connector->disconnect();
	}
	
	switch ($_GET['action']) {
		case 'get_group':
		case 'get_user':
			if (empty($results)) {
				throw new UnknownIdException($error);
			}
			break;
		case 'search_users_by_attributes':
			break;
	}
	return $results;
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}

/**
 * Answer a results XML string (possibly from cache) for all-users
 * 
 * @param array $ldapConfig
 * @param int $page
 * @param optional $proxy
 * @return string
 * @access public
 * @since 7/30/09
 */
function getAllUsersPageXml (array $ldapConfig, $page, $proxy = null) {
	$numPagesCacheKey = getCacheKey(array(), $proxy, 'all_users_pages');
	
	// If we haven't cached the page results, cache them and return the requested one.	
	$allUsersPages = apc_fetch($numPagesCacheKey);
	if ($allUsersPages === false) {
		return loadAllUsersCache($ldapConfig, $page, $proxy);
	}
	
	// If the page is out of range, return and empty result set.
	if ($page > intval($allUsersPages) - 1) {
		return getResultXml(array(), $_GET, $proxy);
	}
	
	// Fetch the page from cache
	$params = $_GET;
	$params['page'] = $page;
	$pageCacheKey = getCacheKey($params, $proxy);
	
	$allUsersString = apc_fetch($pageCacheKey);
	
	// If we haven't cached the page results, cache them and return the requested one.
	if ($allUsersString === false) {
		return loadAllUsersCache($ldapConfig, $page, $proxy);
	}
	
	// Return the cached result
	return $allUsersString;
}

/**
 * Load the all-users results into cache
 * 
 * @param array $ldapConfig
 * @param int $page
 * @param optional $proxy
 * @return string The requested XML string of the page results
 * @access public
 * @since 7/30/09
 */
function loadAllUsersCache (array $ldapConfig, $page, $proxy = null) {
	$params = $_GET;
	$results = loadAllResults($ldapConfig);
	$count = count($results);
	$curPage = 0;
	$i = 0;
	while ($i < $count) {
		$i = $i + ALL_USERS_PAGE_SIZE;
		$params['page'] = $curPage;
		
		$pageResults = array_slice($results, $curPage * ALL_USERS_PAGE_SIZE, ALL_USERS_PAGE_SIZE);
		$pageXml = getResultXml($pageResults, $params, $proxy, ($i < $count));
		
		if ($page == $curPage) {
			$requestedPageXml = $pageXml;
		}
		$curPage++;
		
	}
	
	$numPagesCacheKey = getCacheKey(array(), $proxy, 'all_users_pages');
	apc_store($numPagesCacheKey, $curPage, RESULT_CACHE_TTL);
	
	if (isset($requestedPageXml))
		return $requestedPageXml;
	
	// Return an empty result set.
	return getResultXml(array(), $params, $proxy);
}

/**
 * Answer a cache-key based on a variable array, a prefix, and a suffix
 * 
 * @param array $vars
 * @param optional string $suffix
 * @param optional string $prefix
 * @return string
 * @access public
 * @since 7/31/09
 */
function getCacheKey (array $vars, $suffix = null, $prefix = null) {
	// start with the session-name to prevent collisions with other apps.
	$cacheKey = session_name().':';
	
	if (!is_null($prefix))
		$cacheKey .= $prefix.':';
	
	ksort($vars);
	foreach ($vars as $key => $val) {
		$cacheKey .= '&'.$key.'='.$val;
	}
	
	if (!is_null($suffix))
		$cacheKey .= ':'.$suffix;
	
	return $cacheKey;
}

/**
 * Answer an XML string for a given result-array, params, and proxy
 * 
 * @param array $results
 * @param array $params Parameters in this request to key off of.
 * @param optional string $proxy A proxy that we might be limiting results for.
 * @param optional boolean $hasMore If true, a more_result_pages='true' attribute 
 *			will be added to the response.
 * @return string
 * @access public
 * @since 7/31/09
 */
function getResultXml (array $results, array $params, $proxy = null, $hasMore = false) {
	$printer = new DomXmlPrinter;
	if ($hasMore)
		$printer->morePagesAvailable();
	
	$xmlString = $printer->getOutput($results);
	
	$pageCacheKey = getCacheKey($params, $proxy);
	apc_store($pageCacheKey, $xmlString, RESULT_CACHE_TTL);
	
	return $xmlString;
}