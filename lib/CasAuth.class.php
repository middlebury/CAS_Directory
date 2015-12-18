<?php
/**
 * @since 12/18/2015
 * @package directory
 *
 * @copyright Copyright &copy; 2015, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */

require_once(dirname(__FILE__).'/Auth.interface.php');

/**
 * An authentication/authorization plugin that uses CAS.
 *
 * @since 12/18/2015
 * @package directory
 *
 * @copyright Copyright &copy; 2015, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class CasAuth implements Auth {

	protected $messages = array();
	private $isAuthenticated = false;
	protected $cas_allowed_groups = array();

	public function __construct(array $cas_allowed_groups) {
		$this->cas_allowed_groups = $cas_allowed_groups;
	}

	/**
	 * Authenticate the current request.
	 *
	 * @return boolean True if authenticated, false if not authenticated.
	 */
	public function isAuthenticated() {
		return phpCAS::isAuthenticated();
	}

	/**
 	 * Authorize the current request.
 	 *
 	 * @return null
	 * @throws PermissionDeniedException if unauthorized
 	 */
 	public function authorize() {
		if (!phpCAS::isAuthenticated()) {
			throw new OperationFailedException("authorize() should only be called on this plugin if we are authenticated via it.");
		}
		if (empty($this->cas_allowed_groups) || !is_array($this->cas_allowed_groups)) {
			throw new PermissionDeniedException("No groups are configured to access this service. Please contact an administrator if you believe this is incorrect.");
		} else {
			if (!defined('CAS_MEMBER_OF_ATTRIBUTE')) {
				throw new ConfigurationErrorException('CAS_MEMBER_OF_ATTRIBUTE must be defined in the application configuration.');
			}
			$allowed = false;
			$user_groups = phpCAS::getAttribute(CAS_MEMBER_OF_ATTRIBUTE);
			if (!empty($user_groups)) {
				// Single-value case.
				if (!is_array($user_groups)) {
					$user_groups = array($user_groups);
				}
				$intersection = array_intersect($this->cas_allowed_groups, $user_groups);
				$allowed = (count($intersection) > 0);
			}
			if (!$allowed) {
				throw new PermissionDeniedException("You are not a member of a group granted to access this service. Please contact an administrator if you believe this is incorrect.");
			}
		}
	}

	/**
 	 * Answer a message indicating why authentication and/or authorization is not possible.
 	 *
 	 * @return string
 	 */
 	public function getRequirementsMessage() {
		return "";
	}

}
