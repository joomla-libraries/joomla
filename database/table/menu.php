<?php
/**
 * @version		$Id$
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('JPATH_BASE') or die;

jimport('joomla.database.tablenested');

/**
 * Menu table
 *
 * @package 	Joomla.Framework
 * @subpackage	Table
 * @since		1.0
 */
class JTableMenu extends JTableNested
{
	/**
	 * Constructor
	 *
	 * @param database A database connector object
	 */
	public function __construct(&$db)
	{
		parent::__construct('#__menu', 'id', $db);

		// Set the default access level.
		$this->access = (int) JFactory::getConfig()->getValue('access');
	}

	/**
	 * Overloaded bind function
	 *
	 * @param	array $hash		named array
	 * @return	mixed			null is operation was satisfactory, otherwise returns an error
	 * @see		JTable:bind
	 * @since	1.5
	 */
	public function bind($array, $ignore = '')
	{
		if (is_array($array['params']))
		{
			$registry = new JRegistry();
			$registry->loadArray($array['params']);
			$array['params'] = $registry->toString();
		}

		return parent::bind($array, $ignore);
	}

	/**
	 * Overloaded check function
	 *
	 * @return	boolean
	 * @see		JTable::check
	 * @since	1.5
	 */
	public function check()
	{
		if (empty($this->alias)) {
			$this->alias = $this->title;
		}
		$this->alias = JFilterOutput::stringURLSafe($this->alias);
		if (trim(str_replace('-','',$this->alias)) == '') {
			$this->alias = JFactory::getDate()->toFormat("%Y-%m-%d-%H-%M-%S");
		}

		return true;
	}
}
