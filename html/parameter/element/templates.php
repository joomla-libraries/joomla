<?php
/**
 * @version		$Id: templates.php matrikular
 * @package		Joomla.Administrator
 * @subpackage	Menus
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License, see LICENSE.php
**/

//    Basic Access Verification
defined( '_JEXEC' ) or die( 'Access denied' );

/**
 * @package		Joomla.Administrator
 * @subpackage	Menus
 * @since		1.6
**/
class JElementTemplates extends JElement {

	/**
	* Element name
	*
	* @access	protected
	* @var		string
	**/
	var	$_name = 'Paramsets';

	public function fetchElement( $name, $value, &$node, $control_name )
	{
		$db = JFactory::getDBO();

		$query = 'SELECT * FROM #__menu_template '
			. 'WHERE client_id = 0 '
			. 'AND home = 0';
		$db->setQuery( $query );
		$data = $db->loadObjectList();

		$default = JHtml::_( 'select.option', 0, JText::_( 'JOPTION_USE_DEFAULT' ), 'id', 'description' );
        	array_unshift( $data, $default );

		$selected = $this->_getSelected();
		$html = JHTML::_( 'select.genericlist', $data, $control_name.'['.$name.']', 'class="inputbox" size="6"', 'id', 'description', $selected );
		return $html;
	}

	private function _getSelected()
	{
		$id = JRequest::getVar( 'cid', 0 );
		$db = JFactory::getDBO();
		$query = 'SELECT `template_id` FROM `#__menu` '
			. 'WHERE id = '.$id[0];
		$db->setQuery( $query );
		$result = $db->loadResult();
		return $result;
	}
}
