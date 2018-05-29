<?php
/**
 * @since 12/18/2015
 * @package directory
 *
 * @copyright Copyright &copy; 2015, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */

require_once(dirname(__FILE__).'/TokenAuth.abstract.php');

/**
 * An authentication/authorization plugin that looks for tokens in the request header.
 *
 * @since 12/18/2015
 * @package directory
 *
 * @copyright Copyright &copy; 2015, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class HeaderTokenAuth extends TokenAuth {

	/**
	 * Answer true if there are tokens found in the request, false otherwise.
	 *
	 * @return boolean
	 */
	protected function hasRequestTokens() {
		$headers = getallheaders();
		return isset($headers['ADMIN_ACCESS']);
	}

	/**
	 * Answer the tokens found in the request.
	 *
	 * @return string The tokens.
	 */
	protected function getRequestTokens() {
		$headers = getallheaders();
		if (empty($headers['ADMIN_ACCESS'])) {
			$this->messages[] = $this->formatMessage("@location exists, but is empty.");
			return "";
		}
		return $headers['ADMIN_ACCESS'];
	}

  /**
	 * Answer the location this plugin expects to find the token in so that this can be displayed in error messages.
	 *
	 * @return string The location.
	 */
	protected function getTokenLocation() {
		return "the ADMIN_ACCESS HTTP header";
	}

}
