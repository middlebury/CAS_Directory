<?php
/**
 * @since 12/18/2015
 * @package directory
 *
 * @copyright Copyright &copy; 2015, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */

/**
 * An interface for authentication/authorization plugins.
 *
 * @since 12/18/2015
 * @package directory
 *
 * @copyright Copyright &copy; 2015, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
interface Auth {

	/**
	 * Authenticate the current request.
	 *
	 * @return boolean True if authenticated, false if not authenticated.
	 */
	public function isAuthenticated();

	/**
 	 * Authorize the current request.
 	 *
 	 * @return null
	 * @throws PermissionDeniedException if unauthorized
 	 */
 	public function authorize();

	/**
 	 * Answer a message indicating why authentication and/or authorization is not possible.
 	 *
 	 * @return string
 	 */
 	public function getRequirementsMessage();
	
}
