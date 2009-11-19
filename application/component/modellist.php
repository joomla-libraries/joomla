<?php
/**
 * @version		$Id$
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

jimport('joomla.application.component.model');
jimport('joomla.database.query');

/**
 * Prototype list model.
 *
 * @package		Joomla.Framework
 * @subpackage	Application
 * @since		1.6
 */
class JModelList extends JModel
{
	/**
	 * An array of totals for the lists.
	 *
	 * @var		array
	 */
	protected $_totals = array();

	/**
	 * Array of lists containing items.
	 *
	 * @var		array
	 */
	protected $_lists = array();

	/**
	 * Model context string.
	 *
	 * @var		string
	 */
	protected $_context = null;

	/**
	 * Method to get a list of items.
	 *
	 * @return	mixed	An array of objects on success, false on failure.
	 */
	public function &getItems()
	{
		// Get a unique key for the current list state.
		$key = $this->_getStoreId($this->_context);

		// Try to load the value from internal storage.
		if (!empty ($this->_lists[$key])) {
			return $this->_lists[$key];
		}

		// Load the list.
		$query	= $this->_getListQuery();
		$rows	= $this->_getList((string) $query, $this->getState('list.start'), $this->getState('list.limit'));

		// Add the rows to the internal storage.
		$this->_lists[$key] = $rows;

		return $this->_lists[$key];
	}

	/**
	 * Method to get a list pagination object.
	 *
	 * @return	object	A JPagination object.
	 */
	public function &getPagination()
	{
		jimport('joomla.html.pagination');

		// Create the pagination object.
		$instance = new JPagination($this->getTotal(), (int)$this->getState('list.start'), (int)$this->getState('list.limit'));

		return $instance;
	}

	/**
	 * Method to get the total number of published items.
	 *
	 * @return	int		The number of published items.
	 */
	public function getTotal()
	{
		// Get a unique key for the current list state.
		$key = $this->_getStoreId($this->_context);

		// Try to load the value from internal storage.
		if (!empty ($this->_totals[$key])) {
			return $this->_totals[$key];
		}

		// Load the total.
		$query = $this->_getListQuery();
		$return = (int) $this->_getListCount((string) $query);

		// Check for a database error.
		if ($this->_db->getErrorNum())
		{
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		// Push the value into internal storage.
		$this->_totals[$key] = $return;

		return $this->_totals[$key];
	}

	/**
	 * Method to build an SQL query to load the list data.
	 *
	 * @return	string		An SQL query
	 */
	protected function _getListQuery()
	{
		$query = new JQuery;

		return $query;
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param	string		$context	A prefix for the store id.
	 * @return	string		A store id.
	 */
	protected function _getStoreId($id = '')
	{
		// Compile the store id.
		$id	.= ':'.$this->getState('list.start');
		$id	.= ':'.$this->getState('list.limit');
		$id	.= ':'.$this->getState('list.ordering');
		$id	.= ':'.$this->getState('list.direction');

		return md5($id);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * This method should only be called once per instantiation and is designed
	 * to be called on the first call to the getState() method unless the model
	 * configuration flag to ignore the request is set.
	 *
	 * @param	string	An optional ordering field.
	 * @param	string	An optional direction (asc|desc).
	 */
	protected function _populateState($ordering = null, $direction)
	{
		// If the context is set, assume that stateful lists are used.
		if ($this->_context)
		{
			$app = JFactory::getApplication();

			$limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'));
			$this->setState('list.limit', $limit);

			$limitstart = $app->getUserStateFromRequest($this->_context.'.limitstart', 'limitstart', 0);
			$this->setState('list.start', $limitstart);

			$orderCol = $app->getUserStateFromRequest($this->_context.'.ordercol', 'filter_order', $ordering);
			$this->setState('list.ordering', $orderCol);

			$orderDirn = $app->getUserStateFromRequest($this->_context.'.orderdirn', 'filter_order_Dir', $direction);
			$this->setState('list.direction', $orderDirn);
		}
		else
		{
			$this->setState('list.start', 0);
		}
	}
}
