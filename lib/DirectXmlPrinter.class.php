<?php
/**
 * @since 3/30/09
 * @package directory
 *
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */


/**
 * A printer for generating the appropriate XML output. Note that this printer
 * has not yet successfully ensured identical output with the DomXmlPrinter or
 * the XmlWriterXmlPrinter.
 *
 * @since 3/30/09
 * @package directory
 *
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class DirectXmlPrinter implements XmlPrinterInterface {

	protected $morePagesAvailable = false;

	/**
	 * Add a morePagesAvailable='true' attribute
	 *
	 * @return null
	 */
	public function morePagesAvailable () {
		$this->morePagesAvailable = true;
	}

	/**
	 * Answer the XML string of the entries
	 *
	 * @param array $entries An array of LdapUser or LdapGroup objects.
	 * @return string
	 */
	public function getOutput (array $entries) {
		ob_start();
		$this->output($entries);
		return ob_get_clean();
	}

	/**
	 * Print out the result entries as an XML document
	 *
	 * @param array $entries
	 * @return void
	 * @access public
	 * @since 3/30/09
	 */
	public function output (array $entries) {
		print '<'.'?xml version="1.0" encoding="utf-8"?'.'>';
		print '
<cas:results xmlns:cas="http://www.yale.edu/tp/cas" morePagesAvailable="'.($this->morePagesAvailable?'true':'false').'">';

		foreach ($entries as $userOrGroup) {
			$this->addEntry($userOrGroup);
		}
		print "\n</cas:results>";
	}

	/**
	 * Add an entry to our document.
	 *
	 * @param  LdapUser $userOrGroup
	 * @return void
	 * @access protected
	 * @since 3/30/09
	 */
	protected function addEntry (LdapUser $userOrGroup) {
		try {
			print "\n  <cas:entry>";

			if ($userOrGroup->isGroup())
				print "\n    <cas:group>".htmlentities($userOrGroup->getId())."</cas:group>";
			else
				print "\n    <cas:user>".htmlentities($userOrGroup->getId())."</cas:user>";

			foreach ($userOrGroup->getAttributeKeys() as $attribute) {
				foreach ($userOrGroup->getAttributeValues($attribute) as $value) {
					print "\n    <cas:attribute name=\"".$attribute."\" value=\"".htmlentities($value)."\"/>";
				}
			}
			print "\n  </cas:entry>";
		} catch (OperationFailedException $e) {
			print_r($userOrGroup);
			throw $e;
		}
	}

}

?>
