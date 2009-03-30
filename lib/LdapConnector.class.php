<?php
/**
 * @since 3/25/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */ 

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
	
	/**
	 * Answer a single user by Id
	 * 
	 * @param string $id	A numeric string or integer
	 * @return object LdapPerson
	 * @access public
	 * @since 3/25/09
	 */
	public function getUser ($id) {
		$id = strval($id);
		// Match a numeric ID
		if (!preg_match('/^[0-9]+$/', $id))
			throw new InvalidArgumentException("id '".$id."' is not valid format.");
		
		$result = ldap_search($this->_connection, $this->_config['UserBaseDN'], 
						"(".$this->_config['UserIdAttribute']."=".$id.")", 
						array_keys($this->_config['UserAttributes']));
						
		if (ldap_errno($this->_connection))
			throw new LDAPException("Read failed for ".$this->_config['UserIdAttribute']." '$id' with message: ".ldap_error($this->_connection));
		
		$entries = ldap_get_entries($this->_connection, $result);
		ldap_free_result($result);
		
		if (!$entries['count'])
			throw new UnknownIdException("Could not find a user matching '$id'.");
		
		return $entries;
		// Rebuild the array
		$entry = $entries[0];
		$numValues = $entry['count'];
		
		$values = array();
		for ($i=0; $i<$numValues; $i++) {
			$key = $entry[$i];
			$value = $entry[$entry[$i]];
			
			$values[$key] = array();
			
			for ($j = 0; $j < $value['count']; $j++)
					$values[$key][] = $value[$j];
		}
		
		return $values;
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