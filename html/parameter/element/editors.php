<?php
/**
* @version		$Id$
* @package		Joomla.Framework
* @subpackage	Parameter
* @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// No direct access
defined('JPATH_BASE') or die();

/**
 * Renders a editors element
 *
 * @package 	Joomla.Framework
 * @subpackage		Parameter
 * @since		1.5
 */

class JElementEditors extends JElement
{
	/**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	protected $_name = 'Editors';

	public function fetchElement($name, $value, &$node, $control_name)
	{
		$db		= & JFactory::getDBO();
		$user	= & JFactory::getUser();

		//TODO: change to acl_check method
		if(!($user->get('gid') >= 19) ) {
			return JText::_('No Access');
		}

		// compile list of the editors
		$query = 'SELECT element AS value, name AS text'
		. ' FROM #__plugins'
		. ' WHERE folder = "editors"'
		. ' AND published = 1'
		. ' ORDER BY ordering, name'
		;
		$db->setQuery( $query );
		try {
			$editors = $db->loadObjectList();
		} catch(JException $e) {
			$editors = array();
		}

		array_unshift( $editors, JHTML::_('select.option',  '', '- '. JText::_( 'Select Editor' ) .' -' ) );

		return JHTML::_('select.genericlist',   $editors, ''. $control_name .'['. $name .']', 'class="inputbox"', 'value', 'text', $value, $control_name.$name );
	}
}
