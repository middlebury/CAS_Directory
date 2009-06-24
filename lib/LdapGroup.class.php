<?php
/**
 * @since 3/30/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */ 

/**
 *  An data-access object for reading LDAP results.
 * 
 * @since 3/30/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class LdapGroup
	extends LdapUser
{
	
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
		parent::__construct($connector, $idAttribute, $attributeMap, $entryArray);
		
		$this->members = array();
		if (isset($this->entryArray['member'])) {
			$numValues = intval($this->entryArray['member']['count']);
			for ($i = 0; $i < $numValues; $i++) {
				$memberDN = $this->entryArray['member'][$i];
				$this->members[] = $memberDN;
				if ($this->connector->isGroupDN($memberDN))
					$this->members = array_merge($this->members, $this->connector->getGroupDecendentDNs( $memberDN));
			}
			$this->members = array_unique($this->members);
			sort($this->members);
			unset($this->entryArray['member']);
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
		return true;
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
		$attribute = strtolower($attribute);
		
		if ($attribute == 'member')
			return $this->members;
		
		return parent::getLdapAttributeValues($attribute);
	}
}

?>