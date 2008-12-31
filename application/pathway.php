<?php
/**
* @version		$Id$
* @package		Joomla.Framework
* @subpackage	Application
* @copyright	Copyright (C) 2005 - 2008 Open Source Matters, Inc. All rights reserved.
* @license		GNU General Public License, see LICENSE.php
*/

// No direct access
defined('JPATH_BASE') or die();

/**
 * Class to maintain a pathway.
 *
 * Main example of use so far is the mod_breadcrumbs module that keeps track of
 * the user's navigated path within the Joomla application.
 *
 * @abstract
 * @package 	Joomla.Framework
 * @subpackage	Application
 * @since		1.5
 */
abstract class JPathway extends JObject
{
	/**
	 * Array to hold the pathway item objects
	 * @access private
	 */
	protected $_pathway = null;

	/**
	 * Integer number of items in the pathway
	 * @access private
	 */
	protected $_count = 0;

	/**
	 * Class constructor
	 */
	protected function __construct($options = array())
	{
		//Initialise the array
		$this->_pathway = array();
	}

	/**
	 * Returns a reference a JPathway object
	 *
	 * This method must be invoked as:
	 * 		<pre>  $menu = &JPathway::getInstance();</pre>
	 *
	 * @access	public
	 * @param   string  $client  The name of the client
	 * @param array	 $options An associative array of options
	 * @return JPathway 	A pathway object.
	 * @since	1.5
	 */
	public static function &getInstance($client, $options = array())
	{
		static $instances;

		if (!isset( $instances )) {
			$instances = array();
		}

		if (!isset($instances[$client]) || empty($instances[$client]))
		{
			//Load the router object
			$info =& JApplicationHelper::getClientInfo($client, true);

			$path = $info->path.DS.'includes'.DS.'pathway.php';
			if(file_exists($path))
			{
				require_once $path;

				// Create a JPathway object
				$classname = 'JPathway'.ucfirst($client);
				$instance = new $classname($options);
			}
			else
			{
				throw new JException('Unable to load pathway', 500, E_ERROR, $client, true);
			}

			$instances[$client] = & $instance;
		}

		return $instances[$client];
	}

	/**
	 * Return the JPathWay items array
	 *
	 * @access public
	 * @return array Array of pathway items
	 * @since 1.5
	 */
	public function getPathway()
	{
		$pw = $this->_pathway;

		// Use array_values to reset the array keys numerically
		return array_values($pw);
	}

	/**
	 * Set the JPathway items array.
	 *
	 * @access	public
	 * @param	array	$pathway	An array of pathway objects.
	 * @return	array	The previous pathway data.
	 * @since	1.5
	 */
	public function setPathway($pathway)
	{
		$oldPathway	= $this->_pathway;
		$pathway	= (array) $pathway;

		// Set the new pathway.
		$this->_pathway = array_values($pathway);

		return array_values($oldPathway);
	}

	/**
	 * Create and return an array of the pathway names.
	 *
	 * @access public
	 * @return array Array of names of pathway items
	 * @since 1.5
	 */
	public function getPathwayNames()
	{
		// Initialize variables
		$names = array (null);

		// Build the names array using just the names of each pathway item
		foreach ($this->_pathway as $item) {
			$names[] = $item->name;
		}

		//Use array_values to reset the array keys numerically
		return array_values($names);
	}

	/**
	 * Create and add an item to the pathway.
	 *
	 * @access public
	 * @param string $name
	 * @param string $link
	 * @return boolean True on success
	 * @since 1.5
	 */
	public function addItem($name, $link='')
	{
		// Initalize variables
		$ret = false;

		if ($this->_pathway[] = $this->_makeItem($name, $link)) {
			$ret = true;
			$this->_count++;
		}

		return $ret;
	}

	/**
	 * Set item name.
	 *
	 * @access public
	 * @param integer $id
	 * @param string $name
	 * @return boolean True on success
	 * @since 1.5
	 */
	public function setItemName($id, $name)
	{
		// Initalize variables
		$ret = false;

		if (isset($this->_pathway[$id])) {
			$this->_pathway[$id]->name = $name;
			$ret = true;
		}

		return $ret;
	}

	/**
	 * Create and return a new pathway object.
	 *
	 * @access private
	 * @param string $name Name of the item
	 * @param string $link Link to the item
	 * @return object Pathway item object
	 * @since 1.5
	 */
	protected function _makeItem($name, $link)
	{
		$item = new stdClass();
		$item->name = html_entity_decode($name);
		$item->link = $link;

		return $item;
	}
}

