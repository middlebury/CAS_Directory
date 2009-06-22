<?php
/**
 * @since 3/25/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */ 

require_once(dirname(__FILE__).'/LdapUser.class.php');
require_once(dirname(__FILE__).'/LdapGroup.class.php');

/**
 * An LDAP connector
 * 
 * @since 3/25/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class LdapConnector {
	/**
	 * @var array $_config;  
	 * @access private
	 * @since 3/25/09
	 */
	private $_config;
	
	/**
	 * @var resourse $_connection;  
	 * @access private
	 * @since 3/25/09
	 */
	private $_connection;
		
	/**
	 * Constructor
	 * 
	 * @param array $config
	 * @return void
	 * @access public
	 * @since 3/25/09
	 */
	public function __construct (array $config) {
		/*********************************************************
		 * Check our configuration
		 *********************************************************/
		if (!isset($config['LDAPHost']) || !strlen($config['LDAPHost']))
			throw new ConfigurationErrorException("Missing LDAPHost configuration");
		if (!isset($config['LDAPPort']) || !strlen($config['LDAPPort']))
			throw new ConfigurationErrorException("Missing LDAPPort configuration");
		
		if (!isset($config['BindDN']) || !strlen($config['BindDN']))
			throw new ConfigurationErrorException("Missing BindDN configuration");
		if (!isset($config['BindDNPassword']) || !strlen($config['BindDNPassword']))
			throw new ConfigurationErrorException("Missing BindDNPassword configuration");
		
		if (!isset($config['UserBaseDN']) || !strlen($config['UserBaseDN']))
			throw new ConfigurationErrorException("Missing UserBaseDN configuration");
		if (!isset($config['GroupBaseDN']) || !strlen($config['GroupBaseDN']))
			throw new ConfigurationErrorException("Missing GroupBaseDN configuration");
		
		if (!isset($config['UserIdAttribute']) || !strlen($config['UserIdAttribute']))
			throw new ConfigurationErrorException("Missing UserIdAttribute configuration");
		if (!isset($config['GroupIdAttribute']) || !strlen($config['GroupIdAttribute']))
			throw new ConfigurationErrorException("Missing GroupIdAttribute configuration");
		
		if (!isset($config['UserAttributes']) || !is_array($config['UserAttributes']))
			throw new ConfigurationErrorException("Missing UserAttributes configuration");
		if (!isset($config['GroupAttributes']) || !is_array($config['GroupAttributes']))
			throw new ConfigurationErrorException("Missing group_attributes configuration");
		
		$this->_config = $config;
	}
	
	/**
	 * Clean up an open connections or cache.
	 * 
	 * @return void
	 * @access public
	 * @since 3/25/09
	 */
	public function __destruct () {
		if (isset($this->_connection) && $this->_connection)
			$this->disconnect();
	}
	
	/**
	 * Connects to the LDAP server.
	 * @access public
	 * @return void 
	 **/
	public function connect() {		
		$this->_connection = 
			ldap_connect($this->_config['LDAPHost'], intval($this->_config['LDAPPort']));
		if ($this->_connection == false)
			throw new LDAPException ("LdapConnector::connect() - could not connect to LDAP host <b>".$this->_config['LDAPHost']."</b>!");
		
		ldap_set_option($this->_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($this->_connection, LDAP_OPT_REFERRALS, 0);
		
		$this->_bind = @ldap_bind($this->_connection, $this->_config['BindDN'], $this->_config['BindDNPassword']);
		if (!$this->_bind)
			throw new LDAPException ("LdapConnector::connect() - could not bind to LDAP host <b>".$this->_config['LDAPHost']." using the BindDN and BindDNPassword given.</b>!");
	}
	
	/**
	 * Disconnects from the LDAP server.
	 * @access public
	 * @return void 
	 **/
	public function disconnect() {
		ldap_close($this->_connection);
		$this->_connection = NULL;
	}
	
	
	/*********************************************************
	 * Action methods - Start
	 *********************************************************/
	
	
	/**
	 * Answer a single user by Id
	 * 
	 * @param array $args Must include a numeric 'id' value.
	 * @return object LdapPerson
	 * @access public
	 * @since 3/25/09
	 */
	public function getUser ($args) {
		if (!isset($args['id']))
			throw new NullArgumentException('You must specify an id');
		
		$id = $args['id'];
		
		// Match a numeric ID
		if (!preg_match('/^[0-9A-F]+$/', $id))
			throw new InvalidArgumentException("id '".$id."' is not valid format.");
		
		
		
		$result = ldap_search($this->_connection, $this->_config['UserBaseDN'], 
						"(".$this->_config['UserIdAttribute']."=".$id.")", 
						array_merge(array($this->_config['UserIdAttribute'], 'objectClass'), array_keys($this->_config['UserAttributes'])));
						
		if (ldap_errno($this->_connection))
			throw new LDAPException("Read failed for ".$this->_config['UserIdAttribute']." '$id' with message: ".ldap_error($this->_connection));
		
		$entries = ldap_get_entries($this->_connection, $result);
		ldap_free_result($result);
		
		if (!intval($entries['count']))
			throw new UnknownIdException("Could not find a user matching '$id'.");
		
		if (intval($entries['count']) > 1)
			throw new OperationFailedException("Found more than one user matching '$id'.");
		
		return new LdapUser($this->_config['UserIdAttribute'], $this->_config['UserAttributes'], $entries[0]);
	}
	
	/**
	 * Answer a single user by Id
	 * 
	 * @param array $args Must include a string 'id' value.
	 * @return object LdapPerson
	 * @access public
	 * @since 3/25/09
	 */
	public function getGroup ($args, $includeMembers = false) {
		if (!isset($args['id']))
			throw new NullArgumentException('You must specify an id');
		
		$id = $this->escapeDn($args['id']);
		
		
		
		$attributes = array_merge(	array($this->_config['GroupIdAttribute'], 'objectClass'), 	
									array_keys($this->_config['GroupAttributes']));
		if ($includeMembers)
			$attributes[] = 'member';
		
		$result = ldap_search($this->_connection, $this->_config['GroupBaseDN'], 
						"(".$this->_config['GroupIdAttribute']."=".$id.")", 
						$attributes);
						
		if (ldap_errno($this->_connection))
			throw new LDAPException("Read failed for ".$this->_config['GroupIdAttribute']." '$id' with message: ".ldap_error($this->_connection));
		
		$entries = ldap_get_entries($this->_connection, $result);
		ldap_free_result($result);
		
		if (!intval($entries['count']))
			throw new UnknownIdException("Could not find a group matching '$id' for ".$args['id'].".");
		
		if (intval($entries['count']) > 1)
			throw new OperationFailedException("Found more than one group matching '$id'.");
		
		if ($includeMembers)
			return new LdapGroup($this->_config['GroupIdAttribute'], array_merge($this->_config['GroupAttributes'], array('member' => 'Members')), $entries[0]);
		else
			return new LdapGroup($this->_config['GroupIdAttribute'], $this->_config['GroupAttributes'], $entries[0]);
	}
	
	/**
	 * Answer an array of group members
	 * 
	 * @param array $args Must include a string 'id' value.
	 * @return array of LdapPerson objects
	 * @access public
	 * @since 3/25/09
	 */
	public function getGroupMembers ($args) {
		$group = $this->getGroup($args, true);
		
		$memberDns = $group->getAttributeValues('Members');
		if (!count($memberDns))
			return array();
		
		$members = array();
		foreach ($memberDns as $dn) {
			try {
				if (strpos($dn, $this->_config['GroupBaseDN']) !== FALSE)
					$members[] = $this->getGroup(array('id' => $dn));
				else if (strpos($dn, $this->_config['UserBaseDN']) !== FALSE)
					$members[] = $this->getUserByDn($dn);
			} catch (OperationFailedException $e) {
// 				print "<pre>".$e->getMessage()."</pre>";
			}
			
		}
		return $members;
	}
	
	/**
	 * Answer an array of users by search
	 * 
	 * @param array $args Must include a 'query' element.
	 * @return array of LdapPerson objects
	 * @access public
	 * @since 4/2/09
	 */
	public function searchUsers ($args) {
		if (!isset($args['query']))
			throw new NullArgumentException('You must specify an query');
		
		$filter = $this->buildFilterFromQuery($args['query']);
		
// 		print $filter."\n";
		
		$result = ldap_search($this->_connection, $this->_config['UserBaseDN'], 
						$filter, 
						array_merge(array($this->_config['UserIdAttribute'], 'objectClass'), array_keys($this->_config['UserAttributes'])));
						
		if (ldap_errno($this->_connection))
			throw new LDAPException("Read failed for filter '$filter' with message: ".ldap_error($this->_connection));
		
		$entries = ldap_get_entries($this->_connection, $result);
		ldap_free_result($result);
		
		$numEntries = intval($entries['count']);
		$matches = array();
		for ($i = 0; $i < $numEntries; $i++) {
// 			print "\t".$entries[$i]['dn']."\n";
			try {
				$matches[] = new LdapUser($this->_config['UserIdAttribute'], $this->_config['UserAttributes'], $entries[$i]);
			} catch (OperationFailedException $e) {
// 				print "<pre>".$e->getMessage()."</pre>";
			}
		}
		return $matches;
	}
	
	/**
	 * Answer an array of users by search
	 * 
	 * @param array $args Must include a 'query' element.
	 * @return array of LdapPerson objects
	 * @access public
	 * @since 4/2/09
	 */
	public function searchUsersByAttributes ($args) {
		if (isset($args['strict']) && strtolower($args['strict']) == 'false')
			$strict = false;
		else
			$strict = true;
		
		$terms = array();
		foreach ($args as $key => $val) {
			$ldapKey = array_search($key, $this->_config['UserAttributes']);
			if ($ldapKey !== FALSE) {
				// Match a search string that might match a username, email address, first and/or last name.
				if (!preg_match('/^[a-z0-9_,.\'&\s@-]+$/i', $val))
					throw new InvalidArgumentException("Attribute '$val' is not valid format.");
				if ($strict)
					$terms[] = '('.$ldapKey.'='.$val.')';
				else
					$terms[] = '('.$ldapKey.'=*'.$val.'*)';
			}
		}
		
		if (!count($terms))
			throw new NullArgumentException("No attributes specified for search");
		
		$filter = '(&'.implode('', $terms).')';
		
// 		print $filter."\n";
		
		$result = ldap_search($this->_connection, $this->_config['UserBaseDN'], 
						$filter, 
						array_merge(array($this->_config['UserIdAttribute'], 'objectClass'), array_keys($this->_config['UserAttributes'])));
						
		if (ldap_errno($this->_connection))
			throw new LDAPException("Read failed for filter '$filter' with message: ".ldap_error($this->_connection));
		
		$entries = ldap_get_entries($this->_connection, $result);
		ldap_free_result($result);
		
		$numEntries = intval($entries['count']);
		$matches = array();
		for ($i = 0; $i < $numEntries; $i++) {
// 			print "\t".$entries[$i]['dn']."\n";
			try {
				$matches[] = new LdapUser($this->_config['UserIdAttribute'], $this->_config['UserAttributes'], $entries[$i]);
			} catch (OperationFailedException $e) {
// 				print "<pre>".$e->getMessage()."</pre>";
			}
		}
		return $matches;
	}
	
	/**
	 * Answer an array of groups by search
	 * 
	 * @param array $args Must include a 'query' element.
	 * @return array of LdapPerson objects
	 * @access public
	 * @since 4/2/09
	 */
	public function searchGroups ($args) {
		if (!isset($args['query']))
			throw new NullArgumentException('You must specify an query');
		
		$filter = $this->buildFilterFromQuery($args['query']);
		
// 		print $filter."\n";
		
		$result = ldap_search($this->_connection, $this->_config['GroupBaseDN'], 
						$filter, 
						array_merge(array($this->_config['GroupIdAttribute'], 'objectClass'), array_keys($this->_config['GroupAttributes'])));
						
		if (ldap_errno($this->_connection))
			throw new LDAPException("Read failed for filter '$filter' with message: ".ldap_error($this->_connection));
		
		$entries = ldap_get_entries($this->_connection, $result);
		ldap_free_result($result);
		
		$numEntries = intval($entries['count']);
		$matches = array();
		for ($i = 0; $i < $numEntries; $i++) {
// 			print "\t".$entries[$i]['dn']."\n";
			$matches[] = new LdapGroup($this->_config['GroupIdAttribute'], $this->_config['GroupAttributes'], $entries[$i]);
		}
		return $matches;
	}
	
	/*********************************************************
	 * Action methods - End
	 *********************************************************/
	
	/**
	 * Answer a single user by DN
	 * 
	 * @param string $id	A numeric string or integer
	 * @return object LdapPerson
	 * @access protected
	 * @since 3/25/09
	 */
	protected function getUserByDn ($dn) {
		$dn = $this->escapeDn($dn);
		
		$result = ldap_read($this->_connection, $dn, 
						"(objectclass=*)", 
						array_merge(array($this->_config['UserIdAttribute'], 'objectClass'), array_keys($this->_config['UserAttributes'])));
						
		if (ldap_errno($this->_connection))
			throw new LDAPException("Read failed for distinguishedName '$dn' with message: ".ldap_error($this->_connection));
		
		$entries = ldap_get_entries($this->_connection, $result);
		ldap_free_result($result);
		
		if (!intval($entries['count']))
			throw new UnknownIdException("Could not find a user matching '$dn'.");
		
		if (intval($entries['count']) > 1)
			throw new OperationFailedException("Found more than one user matching '$dn'.");
		
		return new LdapUser($this->_config['UserIdAttribute'], $this->_config['UserAttributes'], $entries[0]);
	}
	
	/**
	 * Build a search filter from a query.
	 * 
	 * @param string $query
	 * @return string 
	 * @access protected
	 * @since 4/2/09
	 */
	protected function buildFilterFromQuery ($query) {
		// Match a search string that might match a username, email address, first and/or last name.
		if (!preg_match('/^[a-z0-9_,.\'&\s@-]+$/i', $query))
			throw new InvalidArgumentException("query '$query' is not valid format.");
		
		if (strlen($query) < 2)
			throw new InvalidArgumentException("query '$query' is too short. Please specify at least two characters.");
		
		$terms = explode(" ", $query);
		
		ob_start();
		
		print '(|';
		
		if (count($terms) == 1) {
			foreach ($this->_config['SingleTermOnlySearchAttributes'] as $attribute) {
				print '('.$attribute.'=*'.$terms[0].'*)';
			}
		}
		
		foreach ($this->_config['AnyTermSearchAttributes'] as $attribute) {
			print '(&';
			foreach ($terms as $term) {
				print '('.$attribute.'=*'.$term.'*)';
			}
			print ')';
		}
		
		print ')';
		
		return ob_get_clean();
	}
	
	/**
	 * Escape a DN and throw an InvalidArgumentException if it is not of a valid format.
	 * 
	 * @param string $dn
	 * @return string
	 * @access protected
	 * @since 4/2/09
	 */
	protected function escapeDn ($dn) {
		$dn = strval($dn);
		if (!preg_match('/^[a-z0-9_=\\\,.\'&\s()-]+$/i', $dn))
			throw new InvalidArgumentException("dn '".$dn."' is not valid format.");
		
		// @todo - Escape needed control characters.
		
		return $dn;
	}
}

/**
 * An LDAP Exception
 * 
 * @since 11/6/07
 * @package harmoni.osid_v2.agentmanagement.authn_methods
 * 
 * @copyright Copyright &copy; 2007, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id: LDAPConnector.class.php,v 1.17 2008/04/04 17:55:22 achapin Exp $
 */
class LDAPException
	extends HarmoniException
{
	
}

?>