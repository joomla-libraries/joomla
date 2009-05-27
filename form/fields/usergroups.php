<?php
/**
 * @version		$Id: usergroups.php 11546 2009-01-29 12:39:09Z eddieajau $
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @copyright	Copyright (C) 2008 - 2009 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License <http://www.gnu.org/copyleft/gpl.html>
 */

defined('JPATH_BASE') or die;

jimport('joomla.html.html');
require_once dirname(__FILE__).DS.'list.php';

/**
 * Form Field class for the Joomla Framework.
 *
 * @package		Joomla.Framework
 * @subpackage	Form
 * @since		1.6
 */
class JFormFieldUserGroups extends JFormFieldList
{
	/**
	 * The field type.
	 *
	 * @var		string
	 */
	public $type = 'UserGroups';

	/**
	 * Method to get a list of options for a list input.
	 *
	 * @return	array		An array of JHtml options.
	 */
	protected function _getOptions()
	{
		// Get a database object.
		$db = &JFactory::getDbo();

		// Get the user groups from the database.
		$db->setQuery(
			'SELECT a.id AS value, a.title AS text, COUNT(DISTINCT b.id) AS level' .
			' FROM #__usergroups AS a' .
			' LEFT JOIN `#__usergroups` AS b ON a.left_id > b.left_id AND a.right_id < b.right_id' .
			' GROUP BY a.id' .
			' ORDER BY a.left_id ASC'
		);
		$options = $db->loadObjectList();

		// Pad the option text with spaces using depth level as a multiplier.
		for ($i=0,$n=count($options); $i < $n; $i++) {
			$options[$i]->text = str_repeat('- ',$options[$i]->level).$options[$i]->text;
		}

		$options	= array_merge(
						parent::_getOptions(),
						$options
					);
		return $options;
	}
}