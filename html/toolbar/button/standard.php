<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	HTML
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('JPATH_BASE') or die;

/**
 * Renders a standard button
 *
 * @package 	Joomla.Framework
 * @subpackage		HTML
 * @since		1.5
 */
class JButtonStandard extends JButton
{
	/**
	 * Button type
	 *
	 * @access	protected
	 * @var		string
	 */
	protected $_name = 'Standard';

	public function fetchButton($type='Standard', $name = '', $text = '', $task = '', $list = true)
	{
		$i18n_text	= JText::_($text);
		$class	= $this->fetchIconClass($name);
		$doTask	= $this->_getCommand($text, $task, $list);

		$html	= "<a href=\"#\" onclick=\"$doTask\" class=\"toolbar\">\n";
		$html .= "<span class=\"$class\">\n";
		$html .= "</span>\n";
		$html	.= "$i18n_text\n";
		$html	.= "</a>\n";

		return $html;
	}

	/**
	 * Get the button CSS Id
	 *
	 * @access	public
	 * @return	string	Button CSS Id
	 * @since	1.5
	 */
	public function fetchId($type='Standard', $name = '', $text = '', $task = '', $list = true, $hideMenu = false)
	{
		return $this->_parent->getName().'-'.$name;
	}

	/**
	 * Get the JavaScript command for the button
	 *
	 * @access	private
	 * @param	string	$name	The task name as seen by the user
	 * @param	string	$task	The task used by the application
	 * @param	???		$list
	 * @return	string	JavaScript command string
	 * @since	1.5
	 */
	protected function _getCommand($name, $task, $list)
	{
		$todo		= JString::strtolower(JText::_($name));
		$message	= JText::sprintf('Please make a selection from the list to', $todo);
		$message	= addslashes($message);

		if ($list) {
			$cmd = "javascript:if (document.adminForm.boxchecked.value==0){alert('$message');}else{ submitbutton('$task')}";
		} else {
			$cmd = "javascript:submitbutton('$task')";
		}


		return $cmd;
	}
}