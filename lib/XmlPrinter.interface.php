<?php
/**
 * @package directory
 *
 * @copyright Copyright &copy; 2018, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */

/**
 * A printer for generating the appropriate XML output.
 *
 * @package directory
 *
 * @copyright Copyright &copy; 2018, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
interface XmlPrinterInterface {

	/**
	 * Add a morePagesAvailable='true' attribute
	 *
	 * @return null
	 */
	public function morePagesAvailable ();

	/**
	 * Print out the result entries as an XML document
	 *
	 * @param array $entries An array of LdapUser or LdapGroup objects.
	 * @return null
	 */
	public function output (array $entries);

	/**
	 * Answer the XML string of the entries
	 *
	 * @param array $entries An array of LdapUser or LdapGroup objects.
	 * @return string
	 */
	public function getOutput (array $entries);

}
