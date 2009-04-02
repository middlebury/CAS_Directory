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
	 * @param string $idAttribute
	 * @param array $attributeMap
	 * @param array $entryArray
	 * @return void
	 * @access public
	 * @since 3/30/09
	 */
	public function __construct ($idAttribute, array $attributeMap, array $entryArray) {
		if (!strlen($idAttribute))
			throw new InvalidArgumentException("No valid \$idAttribute specified.");
		
		$this->idAttribute = $idAttribute;
		$this->attributeMap = $attributeMap;
		$this->entryArray = $entryArray;
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