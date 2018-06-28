<?php
/**
 * @since 6/28/18
 * @package directory
 *
 * @copyright Copyright &copy; 2018, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */


/**
 * A printer for generating the appropriate XML output.
 *
 * @since 36/28/18
 * @package directory
 *
 * @copyright Copyright &copy; 2018, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class XmlWriterXmlPrinter implements XmlPrinterInterface {

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
		$xw = new XMLWriter();
		$xw->openMemory();
		$xw->setIndent(true);
		$xw->setIndentString("  ");
		$xw->startDocument('1.0', 'utf-8');
    $xw->startElementNs('cas', 'results', 'http://www.yale.edu/tp/cas');
		$xw->startAttribute('morePagesAvailable');
		$xw->text(($this->morePagesAvailable?'true':'false'));
    $xw->endAttribute();
		foreach ($entries as $userOrGroup) {
			$this->addEntry($userOrGroup, $xw);
		}
		$xw->endElement();
		$xw->endDocument();
		return $xw->outputMemory();
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
		print $this->getOutput($entries);
	}

	/**
	 * Add an entry to our document.
	 *
	 * @param  LdapUser $userOrGroup
	 * @return void
	 * @access protected
	 * @since 3/30/09
	 */
	protected function addEntry (LdapUser $userOrGroup, XMLWriter $xw) {
		try {
			$xw->startElementNs('cas', 'entry', null);

			if ($userOrGroup->isGroup()) {
				$xw->startElementNs('cas', 'group', null);
			} else {
				$xw->startElementNs('cas', 'user', null);
			}
			$xw->text($userOrGroup->getId());
			$xw->endElement();

			foreach ($userOrGroup->getAttributeKeys() as $attribute) {
				foreach ($userOrGroup->getAttributeValues($attribute) as $value) {
					$xw->startElementNs('cas', 'attribute', null);
					$xw->startAttribute('name');
					$xw->text($attribute);
					$xw->endAttribute();
					$xw->startAttribute('value');
					$xw->text($value);
					$xw->endAttribute();
					$xw->endElement();
				}
			}
			$xw->endElement();
		} catch (OperationFailedException $e) {
			print_r($userOrGroup);
			throw $e;
		}
	}

}

?>
