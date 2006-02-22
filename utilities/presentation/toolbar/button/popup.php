<?php
/**
* @version $Id$
* @package Joomla
* @copyright Copyright (C) 2005 Open Source Matters. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

/**
 * Renders a popup window button
 *
 * @author 		Louis Landry <louis@webimagery.net>
 * @package 	Joomla.Framework
 * @subpackage 	Utilities
 * @since		1.1
 */
class JButton_Popup extends JButton
{
	/**
	 * Button type
	 *
	 * @access	protected
	 * @var		string
	 */
	var $_name = 'Popup';

	function fetchButton( $type='Popup', $name = '', $text = '', $task = '', $url = '', $list = true, $width=640, $height=480, $top=0, $left=0 )
	{
		$text	= JText::_($text);
		$class	= $this->fetchIconClass($name);
		$doTask	= $this->_getCommand($name, $task, $url, $list, $width, $height, $top, $left);

		$html .= "<a class=\"$class\" onclick=\"$doTask\" title=\"$text\" type=\"$type\">\n";
		$html .= "$text\n";
		$html .= "</a>\n";

		return $html;
	}
	
	/**
	 * Get the button id
	 * 
	 * Redefined from JButton class
	 * 
	 * @access		public
	 * @param		string	$name	Button name
	 * @return		string	Button CSS Id
	 * @since		1.1
	 */
	function fetchId($name)
	{
		return $this->_parent->_name.'-'."popup-$name";
	}
	
	/**
	 * Get the JavaScript command for the button
	 * 
	 * @access	private
	 * @param	object	$definition	Button definition
	 * @return	string	JavaScript command string
	 * @since	1.1
	 */
	function _getCommand($name, $task, $url, $list, $width, $height, $top, $left)
	{
		$cmd = "popupWindow('$url','$name',$width,$height,'no');";
		
		return $cmd;
	}
}
?>