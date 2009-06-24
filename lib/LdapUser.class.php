<?php
/**
 * @since 3/30/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */ 

/**
 * An data-access object for reading LDAP results.
 * 
 * @since 3/30/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class LdapUser {
		
	/**
	 * Constructor
	 * 
	 * @param LdapConnector $connector
	 * @param string $idAttribute
	 * @param array $attributeMap
	 * @param array $entryArray
	 * @return void
	 * @access public
	 * @since 3/30/09
	 */
	public function __construct (LdapConnector $connector, $idAttribute, array $attributeMap, array $entryArray) {
		if (!strlen($idAttribute))
			throw new InvalidArgumentException("No valid \$idAttribute specified.");
		
		$this->connector = $connector;
		$this->idAttribute = $idAttribute;
		$this->attributeMap = $attributeMap;
		$this->entryArray = $entryArray;
		
		// Do not allow a user to be created without a valid id.
		try {
			$this->getId();
		} catch (Exception $e) {
			throw new OperationFailedException("Cannot create a user without an Id. DN: ".$this->entryArray['dn']);
		}
		
		// If we are fetching group members, recursively fetch ancestor groups
		$this->groups = array();
		if (isset($this->entryArray['memberof'])) {
			$numValues = intval($this->entryArray['memberof']['count']);
			for ($i = 0; $i < $numValues; $i++) {
				$this->groups[] = $this->entryArray['memberof'][$i];
				$this->groups = array_merge($this->groups, $this->connector->getGroupAncestorDNs( $this->entryArray['memberof'][$i]));
			}
			$this->groups = array_unique($this->groups);
			sort($this->groups);
			unset($this->entryArray['memberof']);
		}
	}
	
	/**
	 * Answer true if this object is a group
	 * 
	 * @return boolean
	 * @access public
	 * @since 3/30/09
	 */
	public function isGroup () {
		return false;
	}
	
	/**
	 * Answer the Id of the User
	 * 
	 * @return string
	 * @access public
	 * @since 3/30/09
	 */
	public function getId () {
		$values = $this->getLdapAttributeValues($this->idAttribute);
		if (count($values) != 1)
			throw new OperationFailedException(count($values)." values found for id attribute '".$this->idAttribute."', expecting 1.");
		
		return strval($values[0]);
	}
	
	/**
	 * Answer the values for an attribute.
	 * 
	 * @param string $attribute
	 * @return array
	 * @access public
	 * @since 3/30/09
	 */
	public function getAttributeValues ($attribute) {
		$ldapKey = array_search($attribute, $this->attributeMap);
		if ($ldapKey === FALSE)
			throw new InvalidArgumentException("Unknown attribute '$attribute'.");
		
		return $this->getLdapAttributeValues($ldapKey);
	}
	
	/**
	 * Answer the values of an attribute
	 * 
	 * @param string $attribute The Ldap key for an attribute
	 * @return array
	 * @access protected
	 * @since 3/30/09
	 */
	protected function getLdapAttributeValues ($attribute) {
		$values = array();
		$attribute = strtolower($attribute);
		
		if ($attribute == 'memberof')
			return $this->groups;
		
		if (!isset($this->entryArray[$attribute]))
			return $values;
		
		$numValues = $this->entryArray[$attribute]['count'];
		for ($i = 0; $i < $numValues; $i++) {
			$values[] = $this->entryArray[$attribute][$i];
		}
		
		return $values;
	}
	
	/**
	 * Answer the attribute keys known to this user
	 * 
	 * @return array
	 * @access public
	 * @since 3/30/09
	 */
	public function getAttributeKeys () {
		return array_values($this->attributeMap);
	}
}

?>