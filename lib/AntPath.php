<?php
/**
 * @since 6/25/09
 * @package directory
 *
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */

/**
 * This class implements ant-style path-matching
 *
 * @since 6/25/09
 * @package directory
 *
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class AntPath {

	/**
	 * Constructor
	 *
	 * @param string $path an Ant-Style path with single-star or double-star wildcards
	 * @return void
	 * @access public
	 * @since 6/25/09
	 */
	public function __construct ($path) {
		if (!is_string($path))
			throw new InvalidArgumentException('$path must be a string');

		$this->pattern = $this->escape($path);
		$this->pattern = str_replace('\*\*', '.*', $this->pattern);
		$this->pattern = str_replace('\*', '[^/]*', $this->pattern);
		$this->pattern = str_replace('\?', '[^/]?', $this->pattern);

// 		print "\n";
// 		print_r($this->pattern);
// 		print "\n";
	}

	/**
	 * Match a string against our pattern.
	 *
	 * @param string $term
	 * @return boolean
	 * @access public
	 * @since 6/25/09
	 */
	public function matches ($term) {
		return preg_match('#'.$this->pattern.'#', $term);
	}

	/**
	 * Escape a pattern
	 *
	 * @param string $pattern
	 * @return string
	 * @access protected
	 * @since 6/25/09
	 */
	protected function escape ($pattern) {
		return preg_replace("/[][{}()*+?.\\^$|#]/", '\\\\\0', $pattern);
	}
}

?>
