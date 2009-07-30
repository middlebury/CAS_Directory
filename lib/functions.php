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
			case 'get_all_users':
				$results = array_merge($results, $connector->getAllUsers($_GET));
				break;
			default:
				throw new UnknownActionException('action, \''.$_GET['action'].'\' is not one of [search_users, search_groups, get_user, get_group].');
		}
		$connector->disconnect();
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
 * Answer a results array (possibly from cache) for all-users
 * 
 * @param array $ldapConfig
 * @param int $page
 * @return array
 * @access public
 * @since 7/30/09
 */
function getAllUsersPageResults (array $ldapConfig, $page) {
	// If we haven't cached the page results, cache them and return the requested one.
	$allUsersPages = apc_fetch('all_users_pages');
	if ($allUsersPages === false) {
		return loadAllUsersCache($ldapConfig, $page);
	}
	
	// If the page is out of range, return and empty result set.
	if ($page > intval($allUsersPages)) {
		return array();
	}
	
	// Fetch the page from cache
	$allUsersString = apc_fetch('all_users-'.$page);
	
	// If we haven't cached the page results, cache them and return the requested one.
	if ($allUsersString === false) {
		return loadAllUsersCache($ldapConfig, $page);
	}
	
	// Return the cached result
	return unserialize($allUsersString);
}

/**
 * Load the all-users results into cache
 * 
 * @param array $ldapConfig
 * @param int $page
 * @return array The requested page results
 * @access public
 * @since 7/30/09
 */
function loadAllUsersCache (array $ldapConfig, $page) {
	$requestedPageResults = array();
	$results = loadAllResults($ldapConfig);
	$count = count($results);
	$curPage = 0;
	for ($i = 0; $i < $count; $i = $i + ALL_USERS_PAGE_SIZE) {
		$pageResults = array_slice($results, $curPage * ALL_USERS_PAGE_SIZE, ALL_USERS_PAGE_SIZE);
		if ($page == $curPage)
			$requestedPageResults = $pageResults;
		apc_store('all_users-'.$curPage, serialize($pageResults));
		$curPage++;
	}
	apc_store('all_users_pages', $curPage);
	return $requestedPageResults;
}