<?php
/**
 * @version		$Id$
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Utility class for creating HTML Grids
 *
 * @package		Joomla.Framework
 * @subpackage	HTML
 * @since		1.6
 */
abstract class JHtmlJGrid
{
	/**
	 * @param	int $value	The state value.
	 * @param	int $i
	 * @param	string		An optional prefix for the task.
	 * @param	boolean		An optional setting for access control on the action.
	 */
	public static function published($value = 0, $i, $taskPrefix = '', $canChange = true)
	{
		// Array of image, task, title, action
		$states	= array(
			1	=> array('tick.png',		$taskPrefix.'unpublish',	'JPUBLISHED',		'JLIB_HTML_UNPUBLISH_ITEM'),
			0	=> array('publish_x.png',	$taskPrefix.'publish',		'JUNPUBLISHED',	'JLIB_HTML_PUBLISH_ITEM'),
			2	=> array('disabled.png',	$taskPrefix.'unpublish',	'JARCHIVED',		'JLIB_HTML_UNPUBLISH_ITEM'),
			-2	=> array('trash.png',		$taskPrefix.'publish',		'JTRASHED',		'JLIB_HTML_PUBLISH_ITEM'),
		);
		$state	= JArrayHelper::getValue($states, (int) $value, $states[0]);
		$html	= JHTML::_('image','admin/'.$state[0], JText::_($state[2]), NULL, true);
		if ($canChange) {
			$html	= '<a href="javascript:void(0);" onclick="return listItemTask(\'cb'.$i.'\',\''.$state[1].'\')" title="'.JText::_($state[3]).'">'
					. $html.'</a>';
		}

		return $html;
	}

	/**
	 * @param	int $value	The state value.
	 * @param	int $i
	 * @param	string		An optional prefix for the task.
	 * @param	boolean		An optional setting for access control on the action.
	 */
	public static function makedefault($value = 0, $i, $taskPrefix = '', $canChange = true)
	{
		// Array of image, task, title, action
		$states	= array(
			1	=> array('icon-16-default.png',	$taskPrefix.'unsetDefault',	'JDEFAULT', 'JLIB_HTML_UNSETDEFAULT_ITEM'),
			0	=> array('icon-16-default-grayed.png', $taskPrefix.'setDefault', '',	'JLIB_HTML_SETDEFAULT_ITEM'),
		);
		$state	= JArrayHelper::getValue($states, (int) $value, $states[0]);
		$html	= JHTML::_('image','menu/'.$state[0], JText::_($state[2]), NULL, true);
		if ($canChange) {
			$html	= '<a href="javascript:void(0);" onclick="return listItemTask(\'cb'.$i.'\',\''.$state[1].'\')" title="'.JText::_($state[3]).'">'
					. $html.'</a>';
		}

		return $html;
	}

	/**
	 * Returns an array of standard published state filter options.
	 *
	 * @param	array			An array of configuration options.
	 *							This array can contain a list of key/value pairs where values are boolean
	 *							and keys can be taken from 'published', 'unpublished', 'archived', 'trash', 'all'.
	 *							These pairs determine which values are displayed.
	 * @return	string			The HTML code for the select tag
	 * @since	1.6
	 */
	public static function publishedOptions($config = array())
	{
		// Build the active state filter options.
		$options	= array();
		if (!array_key_exists('published', $config) || $config['published']) {
			$options[]	= JHtml::_('select.option', '1', 'JPUBLISHED');
		}
		if (!array_key_exists('unpublished', $config) || $config['unpublished']) {
			$options[]	= JHtml::_('select.option', '0', 'JUNPUBLISHED');
		}
		if (!array_key_exists('archived', $config) || $config['archived']) {
			$options[]	= JHtml::_('select.option', '2', 'JARCHIVED');
		}
		if (!array_key_exists('trash', $config) || $config['trash']) {
			$options[]	= JHtml::_('select.option', '-2', 'JTRASH');
		}
		if (!array_key_exists('all', $config) || $config['all']) {
			$options[]	= JHtml::_('select.option', '*', 'JALL');
		}
		return $options;
	}

	/**
	 * Displays a checked-out icon
	 *
	 * @param	string	The name of the editor.
	 * @param	string	The time that the object was checked out.
	 *
	 * @return	string	The required HTML.
	 */
	public static function checkedout($editorName, $time)
	{
		$text	= addslashes(htmlspecialchars($editorName, ENT_COMPAT, 'UTF-8'));
		$date	= JHTML::_('date',$time, '%A, %d %B %Y');
		$time	= JHTML::_('date',$time, '%H:%M');

		$hover = '<span class="editlinktip hasTip" title="'. JText::_('JLIB_HTML_CHECKED_OUT') .'::'. $text .'<br />'. $date .'<br />'. $time .'">';
		$checked = $hover .JHTML::_('image','admin/checked_out.png', JText::_('JLIB_HTML_CHECKED_OUTs'), NULL, true).'</span>';

		return $checked;
	}

	/**
	 * Create a order-up action icon.
	 *
	 * @param	integer	The row index.
	 * @param	string	The task to fire.
	 * @param	boolean	True to show the icon.
	 * @param	string	The image alternate text string.
	 *
	 * @return	string	The HTML for the IMG tag.
	 * @since	1.6
	 */
	public static function orderUp($i, $task, $enabled = true, $alt = 'JLIB_HTML_MOVE_UP')
	{
		$alt = JText::_($alt);

		// TODO: Deal with hardcoded links.
		if ($enabled)
		{
			$html	= '<a href="#reorder" onclick="return listItemTask(\'cb'.$i.'\',\''.$task.'\')" title="'.$alt.'">';
			$html	.= JHTML::_('image','admin/uparrow.png', $alt, array( 'width' => 16, 'height' => 16, 'border' => 0), true);
			$html	.= '</a>';
		}
		else {
			$html	= JHTML::_('image','admin/uparrow0.png', $alt, array( 'width' => 16, 'height' => 16, 'border' => 0), true);
		}
		return $html;
	}

	/**
	 * Create a move-down action icon.
	 *
	 * @param	integer	The row index.
	 * @param	string	The task to fire.
	 * @param	boolean	True to show the icon.
	 * @param	string	The image alternate text string.
	 *
	 * @return	string	The HTML for the IMG tag.
	 * @since	1.6
	 */
	public static function orderDown($i, $task, $enabled = true, $alt = 'JLIB_HTML_MOVE_DOWN')
	{
		$alt = JText::_($alt);

		// TODO: Deal with hardcoded links.
		if ($enabled)
		{
			$html	= '<a href="#reorder" onclick="return listItemTask(\'cb'.$i.'\',\''.$task.'\')" title="'.$alt.'">';
			$html	.= JHTML::_('image','admin/downarrow.png', $alt, array( 'width' => 16, 'height' => 16, 'border' => 0), true);
			$html	.= '</a>';
		}
		else {
			$html	= JHTML::_('image','admin/downarrow0.png', $alt, array( 'width' => 16, 'height' => 16, 'border' => 0), true);
		}
		return $html;
	}
}
