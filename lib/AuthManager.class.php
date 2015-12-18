<?php
/**
 * @since 12/18/2015
 * @package directory
 *
 * @copyright Copyright &copy; 2015, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */

/**
 * A manager of authentication/authorization plugins.
 *
 * @since 12/18/2015
 * @package directory
 *
 * @copyright Copyright &copy; 2015, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class AuthManager {

	private $plugins = array();

	/**
	 * Add an authentication plugin.
	 *
	 * @param Auth $auth The authentication plugin instance.
	 * @return Auth The plugin instance added, for chaining.
	 */
	public function addAuth(Auth $auth) {
		$this->plugins[] = $auth;
	}

	/**
	 * Authenticate and authorize the current request.
	 *
	 * @return null
	 * @throws PermissionDeniedException on authorization failure.
	 */
	 public function authenticateAndAuthorize() {
		 if (!count($this->plugins)) {
			 throw new PermissionDeniedException("No authentication plugins have been configured.");
		 }
		 $authenticatedAndAuthorized = false;
		 $requirements = array();
		 foreach ($this->plugins as $plugin) {
			 if ($plugin->isAuthenticated()) {
				 $plugin->authorize();
				 $authenticatedAndAuthorized = true;
			 } else {
				 $requirements[] = $plugin->getRequirementsMessage();
			 }
		 }
		 if (!$authenticatedAndAuthorized) {
			 throw new PermissionDeniedException("Not authenticated: ".implode("\n", $requirements));
		 }
	 }
}
