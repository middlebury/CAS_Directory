<?php
/**
 * @since 3/30/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */ 

/**
 * A printer for generating the appropriate XML output.
 * 
 * @since 3/30/09
 * @package directory
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class DomXmlPrinter {
	
	/**
	 * Constructor
	 * 
	 * @return void
	 * @access public
	 * @since 3/30/09
	 */
	public function __construct () {
		$this->doc = new DOMDocument ('1.0', 'utf-8');
		$this->doc->formatOutput = true;
		
		$this->doc->appendChild($this->doc->createElementNS('http://www.yale.edu/tp/cas', 'cas:results'));
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
		foreach ($entries as $userOrGroup) {
			$this->addEntry($userOrGroup);
		}
		print $this->doc->saveXML();
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
			$elem = $this->doc->documentElement->appendChild($this->doc->createElementNS('http://www.yale.edu/tp/cas', 'cas:entry'));
			
			if ($userOrGroup->isGroup())
				$elem->appendChild($this->doc->createElementNS('http://www.yale.edu/tp/cas', 'cas:group', $userOrGroup->getId()));
			else
				$elem->appendChild($this->doc->createElementNS('http://www.yale.edu/tp/cas', 'cas:user', $userOrGroup->getId()));
			
			foreach ($userOrGroup->getAttributeKeys() as $attribute) {
				foreach ($userOrGroup->getAttributeValues($attribute) as $value) {
					$attraElem = $elem->appendChild($this->doc->createElementNS('http://www.yale.edu/tp/cas', 'cas:attribute'));
					$attraElem->setAttribute('name', $attribute);
					$attraElem->setAttribute('value', $value);
				}
			}
		} catch (OperationFailedException $e) {
			print_r($userOrGroup);
			throw $e;
		}
	}
	
}

?>