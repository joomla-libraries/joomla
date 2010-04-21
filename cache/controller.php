<?php
/**
 * @version		$Id:storage.php 6961 2007-03-15 16:06:53Z tcp $
 * @package		Joomla.Framework
 * @subpackage	Cache
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('JPATH_BASE') or die;



/**
 * Public cache handler
 *
 * @abstract
 * @package		Joomla.Framework
 * @subpackage	Cache
 * @since		1.6
 */
class JCacheController

{
	protected $cache;
	public $options;

	/**
	 * Constructor
	 *
	 * @param	array	$options	options
	*/

	public function __construct($options) {

		$this->cache = new JCache($options);
		$this->options = $this->cache->_options;

		// Overwrite default options with given options
		foreach ($options AS $option=>$value) {
			if (isset($options[$option])) {
				$this->options[$option] = $options[$option];
			}
		}
	}

	public function __call ($name, $arguments) {

		$nazaj = call_user_func_array (array ($this->cache,$name),$arguments);
		return $nazaj;
	}

	/**
	 * Returns a reference to a cache adapter object, always creating it
	 *
	 * @param	string	$type	The cache object type to instantiate
	 * @return	object	A JCache object
	 * @since	1.6
	 */
	public static function getInstance($type = 'output', $options = array())
	{
		JCacheController::addIncludePath(JPATH_LIBRARIES.DS.'joomla'.DS.'cache'.DS.'controller');

		$type = strtolower(preg_replace('/[^A-Z0-9_\.-]/i', '', $type));

		$class = 'JCacheController'.ucfirst($type);

		if (!class_exists($class))
		{
			// Search for the class file in the JCache include paths.
			jimport('joomla.filesystem.path');
			if ($path = JPath::find(JCacheController::addIncludePath(), strtolower($type).'.php')) {
				require_once $path;
			} else {
				JError::raiseError(500, 'Unable to load Cache Controller: '.$type);
			}
		}

		return new $class($options);
	}

	/**
	 * Set caching enabled state
	 *
	 * @param	boolean	$enabled	True to enable caching
	 * @return	void
	 * @since	1.6
	 */
	public function setCaching($enabled)
	{
		$this->cache->setCaching($enabled);
	}

	/**
	 * Set cache lifetime
	 *
	 * @param	int	$lt	Cache lifetime
	 * @return	void
	 * @since	1.6
	 */
	public function setLifeTime($lt)
	{
		$this->cache->setLifeTime($lt);
	}

	/**
	 * Add a directory where JCache should search for controllers. You may
	 * either pass a string or an array of directories.
	 *
	 * @param	string	A path to search.
	 * @return	array	An array with directory elements
	 * @since	1.6
	 */

	public static function addIncludePath($path='')
	{
		static $paths;

		if (!isset($paths)) {
			$paths = array();
		}
		if (!empty($path) && !in_array($path, $paths)) {
			jimport('joomla.filesystem.path');
			array_unshift($paths, JPath::clean($path));
		}
		return $paths;
	}

	/**
	 * Store the cached data by id and group
	 *
	 * @param	string	$id		The cache data id
	 * @param	string	$group	The cache data group
	 * @param	mixed	$data	The data to store
	 * @return	boolean	True if cache stored
	 * @since	1.6
	 */
	public function get($id, $group=null)
	{	$data = unserialize($this->cache->get($id, $group=null));
		return $data;
	}

	/**
	 * Store the cached data by id and group
	 *
	 * @param	string	$id		The cache data id
	 * @param	string	$group	The cache data group
	 * @param	mixed	$data	The data to store
	 * @return	boolean	True if cache stored
	 * @since	1.6
	 */
	public function store($data, $id, $group=null)
	{
		return $this->cache->store(serialize($data), $id, $group=null);
	}

}
