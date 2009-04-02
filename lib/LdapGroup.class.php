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
	 * Answer true if this object is a group
	 * 
	 * @return boolean
	 * @access public
	 * @since 3/30/09
	 */
	public function isGroup () {
		return true;
	}
}

?>