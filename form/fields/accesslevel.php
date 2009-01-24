<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	Form
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @copyright	Copyright (C) 2008 - 2009 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License, see LICENSE.php
 */

defined('JPATH_BASE') or die('Restricted Access');

jimport('joomla.html.html');
jimport('joomla.form.fields.list');

/**
 * Form Field class for the Joomla Framework.
 *
 * @package		Joomla.Framework
 * @subpackage	Form
 * @since		1.6
 */
class JFormFieldAccessLevel extends JFormFieldList
{
	/**
	 * The field type.
	 *
	 * @access	public
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'AccessLevel';

	/**
	 * Method to get a list of options for a list input.
	 *
	 * @access	protected
	 * @return	array		An array of JHtml options.
	 * @since	1.6
	 */
	protected function _getOptions()
	{
		$db		= &JFactory::getDBO();
		$query	= new JQuery;

		$query->select('a.id AS value, a.title AS text');
		$query->select('COUNT(DISTINCT g2.id) AS level');
		$query->from('#__access_assetgroups AS a');
		$query->join('LEFT', '#__access_assetgroups AS g2 ON a.left_id > g2.left_id AND a.right_id < g2.right_id');
		$query->group('a.id');

		// Get the options.
		$db->setQuery($query->toString());
		$options = $db->loadObjectList();

		// Check for a database error.
		if ($db->getErrorNum()) {
			JError::raiseWarning(500, $db->getErrorMsg());
		}

		return $options;
	}
}