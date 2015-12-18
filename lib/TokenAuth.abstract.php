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
 * An interface for authentication/authorization plugins.
 *
 * @since 12/18/2015
 * @package directory
 *
 * @copyright Copyright &copy; 2015, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
abstract class TokenAuth implements Auth {

	protected $tokens = array();
	protected $messages = array();
	private $isAuthenticated = false;

	public function __construct(array $tokens) {
		$this->tokens = $tokens;
	}

	/**
	 * Answer true if there are tokens found in the request, false otherwise.
	 *
	 * @return boolean
	 */
	abstract protected function hasRequestTokens();

	/**
	 * Answer the tokens found in the request.
	 *
	 * @return string The tokens.
	 */
	abstract protected function getRequestTokens();

  /**
	 * Answer the location this plugin expects to find the token in so that this can be displayed in error messages.
	 *
	 * @return string The location.
	 */
	abstract protected function getTokenLocation();

	/**
	 * Authenticate the current request.
	 *
	 * @return boolean True if authenticated, false if not authenticated.
	 */
	public function isAuthenticated() {
		if ($this->hasRequestTokens()) {
			$requestTokens = $this->getRequestTokens();
			if (in_array($requestTokens, $this->tokens)) {
				$this->isAuthenticated = true;
				return true;
			}
			$this->messages[] = $this->formatMessage("Service token is present in @location, but invalid");
		} else {
			$this->messages[] = "No service token specified";
		}
		return false;
	}

	/**
 	 * Authorize the current request.
 	 *
 	 * @return null
	 * @throws PermissionDeniedException if unauthorized
 	 */
 	public function authorize() {
		if (!$this->isAuthenticated) {
			throw new OperationFailedException("authorize() should only be called on this plugin if we are authenticated via it.");
		}
		// If the token exists, not further authorization is needed for this plugin.
		return true;
	}

	/**
 	 * Answer a message indicating why authentication and/or authorization is not possible.
 	 *
 	 * @return string
 	 */
 	public function getRequirementsMessage() {
		return implode("\n", $this->messages);
	}

	/**
	 * Format a message string.
	 *
	 * @param string $message
	 * @return string The formatted message.
	 */
	protected function formatMessage($message) {
		return str_replace("@location", $this->getTokenLocation(), $message);
	}

}
