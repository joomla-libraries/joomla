<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	Application
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License <http://www.gnu.org/copyleft/gpl.html>
 */

// No direct access
defined('JPATH_BASE') or die;

/**
 * Base class for a Joomla Model
 *
 * Acts as a Factory class for application specific objects and
 * provides many supporting API functions.
 *
 * @abstract
 * @package		Joomla.Framework
 * @subpackage	Application
 * @since		1.5
 */
abstract class JModel extends JObject
{
	/**
	 * The model (base) name
	 *
	 * @var string
	 */
	protected $_name;

	/**
	 * Database Connector
	 *
	 * @var object
	 */
	protected $_db;

	/**
	 * An state object
	 *
	 * @var string
	 */
	protected $_state;

	/**
	 * Indicates if the internal state has been set
	 *
	 * @var bool
	 * @since	1.6
	 */
	protected $__state_set	= null;

	/**
	 * Constructor
	 *
	 * @since	1.5
	 */
	function __construct($config = array())
	{
		//set the view name
		if (empty($this->_name))
		{
			if (array_key_exists('name', $config))  {
				$this->_name = $config['name'];
			} else {
				$this->_name = $this->getName();
			}
		}

		//set the model state
		if (array_key_exists('state', $config))  {
			$this->_state = $config['state'];
		} else {
			$this->_state = new JObject();
		}

		//set the model dbo
		if (array_key_exists('dbo', $config))  {
			$this->_db = $config['dbo'];
		} else {
			$this->_db = &JFactory::getDbo();
		}

		// set the default view search path
		if (array_key_exists('table_path', $config)) {
			$this->addTablePath($config['table_path']);
		} else if (defined('JPATH_COMPONENT_ADMINISTRATOR')){
			$this->addTablePath(JPATH_COMPONENT_ADMINISTRATOR.DS.'tables');
		}

		// set the internal state marker - used to ignore setting state from the request
		if (!empty($config['ignore_request'])) {
			$this->__state_set = true;
		}
	}

	/**
	 * Returns a reference to the a Model object, always creating it
	 *
	 * @param	string	The model type to instantiate
	 * @param	string	Prefix for the model class name. Optional.
	 * @param	array	Configuration array for model. Optional.
	 * @return	mixed	A model object, or false on failure
	 */
	public static function &getInstance($type, $prefix = '', $config = array())
	{
		$type		= preg_replace('/[^A-Z0-9_\.-]/i', '', $type);
		$modelClass	= $prefix.ucfirst($type);
		$result		= false;

		if (!class_exists($modelClass))
		{
			jimport('joomla.filesystem.path');
			$path = JPath::find(
				JModel::addIncludePath(),
				JModel::_createFileName('model', array('name' => $type))
			);
			if ($path)
			{
				require_once $path;

				if (!class_exists($modelClass))
				{
					JError::raiseWarning(0, 'Model class ' . $modelClass . ' not found in file.');
					return $result;
				}
			}
			else return $result;
		}

		$result = new $modelClass($config);
		return $result;
	}

	/**
	 * Method to set model state variables
	 *
	 * @param	string	The name of the property
	 * @param	mixed	The value of the property to set
	 * @return	mixed	The previous value of the property
	 */
	public function setState($property, $value=null)
	{
		return $this->_state->set($property, $value);
	}

	/**
	 * Method to get model state variables
	 *
	 * @param	string	Optional parameter name
	 * @param   mixed	Optional default value
	 * @return	object	The property where specified, the state object where omitted
	 */
	public function getState($property = null, $default = null)
	{
		if (!$this->__state_set)
		{
			// Private method to auto-populate the model state.
			$this->_populateState();

			// Set the model state set flat to true.
			$this->__state_set = true;
		}

		return $property === null ? $this->_state : $this->_state->get($property, $default);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * This method should only be called once per instantiation and is designed
	 * to be called on the first call to the getState() method unless the model
	 * configuration flag to ignore the request is set.
	 *
	 * @return	void
	 */
	protected function _populateState()
	{
	}

	/**
	 * Method to get the database connector object
	 *
	 * @return	object JDatabase connector object
	 */
	public function &getDbo()
	{
		return $this->_db;
	}

	/**
	 * Method to set the database connector object
	 *
	 * @param	object	$db	A JDatabase based object
	 * @return	void
	 */
	public function setDbo(&$db)
	{
		$this->_db = &$db;
	}

	/**
	 * Method to get the model name
	 *
	 * The model name by default parsed using the classname, or it can be set
	 * by passing a $config['name�] in the class constructor
	 *
	 * @return	string The name of the model
	 */
	public function getName()
	{
		$name = $this->_name;

		if (empty($name))
		{
			$r = null;
			if (!preg_match('/Model(.*)/i', get_class($this), $r)) {
				JError::raiseError (500, "JModel::getName() : Can't get or parse class name.");
			}
			$name = strtolower($r[1]);
		}

		return $name;
	}

	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param	string The table name. Optional.
	 * @param	string The class prefix. Optional.
	 * @param	array	Configuration array for model. Optional.
	 * @return	object	The table
	 */
	public function &getTable($name='', $prefix='Table', $options = array())
	{
		if (empty($name)) {
			$name = $this->getName();
		}

		if ($table = &$this->_createTable($name, $prefix, $options))  {
			return $table;
		}

		JError::raiseError(0, 'Table ' . $name . ' not supported. File not found.');
		$null = null;
        return $null;
	}

	/**
	 * Add a directory where JModel should search for models. You may
	 * either pass a string or an array of directories.
	 *
	 * @param	string	A path to search.
	 * @return	array	An array with directory elements
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
	 * Adds to the stack of model table paths in LIFO order.
	 *
	 * @static
	 * @param	string|array The directory (-ies) to add.
	 * @return	void
	 */
	public static function addTablePath($path)
	{
		jimport('joomla.database.table');
		JTable::addIncludePath($path);
	}

	/**
	 * Returns an object list
	 *
	 * @param	string The query
	 * @param	int Offset
	 * @param	int The number of records
	 * @return	array
	 */
	protected function &_getList($query, $limitstart=0, $limit=0)
	{
		$this->_db->setQuery($query, $limitstart, $limit);
		$result = $this->_db->loadObjectList();

		return $result;
	}

	/**
	 * Returns a record count for the query
	 *
	 * @param	string The query
	 * @return	int
	 */
	protected function _getListCount($query)
	{
		$this->_db->setQuery($query);
		$this->_db->query();

		return $this->_db->getNumRows();
	}

	/**
	 * Method to load and return a model object.
	 *
	 * @param	string	The name of the view
	 * @param   string  The class prefix. Optional.
	 * @return	mixed	Model object or boolean false if failed
	 */
	private function &_createTable($name, $prefix = 'Table', $config = array())
	{
		$result = null;

		// Clean the model name
		$name	= preg_replace('/[^A-Z0-9_]/i', '', $name);
		$prefix = preg_replace('/[^A-Z0-9_]/i', '', $prefix);

		//Make sure we are returning a DBO object
		if (!array_key_exists('dbo', $config))  {
			$config['dbo'] = &$this->getDbo();;
		}

		$instance = &JTable::getInstance($name, $prefix, $config);
		return $instance;
	}

	/**
	 * Create the filename for a resource
	 *
	 * @param	string 	$type  The resource type to create the filename for
	 * @param	array 	$parts An associative array of filename information
	 * @return	string The filename
	 */
	private static function _createFileName($type, $parts = array())
	{
		$filename = '';

		switch($type)
		{
			case 'model':
				$filename = strtolower($parts['name']).'.php';
				break;

		}
		return $filename;
	}
}