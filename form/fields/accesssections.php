<?php
/**
 * @version		$Id$
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
class JFormFieldAccessSections extends JFormFieldList
{
	/**
	 * The field type.
	 *
	 * @var		string
	 */
	public $type = 'AccessSections';

	/**
	 * Method to get a list of options for a list input.
	 *
	 * @return	array		An array of JHtml options.
	 */
	protected function _getOptions()
	{
		// Get the user groups from the database.
		$db = &JFactory::getDbo();
		$db->setQuery(
			'SELECT `id` AS value, `title` AS text' .
			' FROM `#__access_sections`' .
			' ORDER BY `ordering`, `title` ASC'
		);
		$options = $db->loadObjectList();

		// Check for a database error.
		if ($db->getErrorNum()) {
			JError::raiseWarning(500, $db->getErrorMsg());
		}

		$options	= array_merge(
						parent::_getOptions(),
						$options
					);
		return $options;
	}
}