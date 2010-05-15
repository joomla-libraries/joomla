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
	 * Returns an action on a grid
	 *
	 * @param	int				$i			The row index
	 * @param	string			$task		The task to fire
	 * @param	string|array	$prefix		An optional task prefix or an array of options
	 * @param	string			$text		An optional text to display
	 * @param	string			$title		An optional tooltip to display if $enable is true
	 * @param	boolean			$tip		An optional setting for tooltip
	 * @param	string			$active		An optional active html class
	 * @param	string			$inactive	An optional inactive html class
	 * @param	boolean			$enabled	An optional setting for access control on the action.
	 * @param	boolean			$translate	An optional setting for translation.
	 * @param	string			$checkbox	An optional prefix for checkboxes.
	 *
	 * @return The Html code
	 *
	 * @since	1.6
	 */
	public static function action($i, $task, $prefix='', $text='', $title='', $tip=false, $active='', $inactive='', $enabled = true, $translate=true, $checkbox='cb')
	{
		if (is_array($prefix)) {
			$options	= $prefix;
			$text		= array_key_exists('text',		$options) ? $options['text']		: $text;
			$title		= array_key_exists('title',		$options) ? $options['title']		: $title;
			$tip		= array_key_exists('tip',		$options) ? $options['tip']			: $tip;
			$active		= array_key_exists('active',	$options) ? $options['active']		: $active;
			$inactive	= array_key_exists('inactive',	$options) ? $options['inactive']	: $inactive;
			$enabled	= array_key_exists('enabled',	$options) ? $options['enabled']		: $enabled;
			$translate	= array_key_exists('translate',	$options) ? $options['translate']	: $translate;
			$checkbox	= array_key_exists('checkbox',	$options) ? $options['checkbox']	: $checkbox;
			$prefix		= array_key_exists('prefix',	$options) ? $options['prefix']		: '';
		}
		if ($enabled) {
			return '<a class="jgrid'.($tip?' hasTip':'').'" href="javascript:void(0);" onclick="return listItemTask(\''.$checkbox.$i.'\',\''.$prefix.$task.'\')" title="'.addslashes(htmlspecialchars($translate?JText::_($title):$title, ENT_COMPAT, 'UTF-8')).'"><span class="state '.$active.'"><span class="text">'.($translate?JText::_($text):$text).'</span></span></a>';
		}
		else {
			return '<span class="jgrid'.($tip?' hasTip':'').'" title="'.addslashes(htmlspecialchars($translate?JText::_($title):$title, ENT_COMPAT, 'UTF-8')).'"><span class="state '.$inactive.'"><span class="text">'.($translate?JText::_($text):$text).'</span></span></span>';
		}
	}

	/**
	 * Returns a state on a grid
	 *
	 * @param	array			$states		array of value/state. Each state is an array of the form (task, text, title,html active class, html inactive class)
	 *										or ('task'=>task, 'text'=>text, 'title'=>title, 'tip'=>boolean, 'active'=>html class, 'inactive'=>html class) 
	 * @param	int				$value		The state value.
	 * @param	int				$i			The row index
	 * @param	string|array	$prefix		An optional task prefix or an array of options
	 * @param	boolean			$enabled	An optional setting for access control on the action.
	 * @param	boolean			$translate	An optional setting for translation.
	 * @param	string			$checkbox	An optional prefix for checkboxes.
	 *
	 * @return The Html code
	 *
	 * @since	1.6
	 */
	public static function state($states, $value, $i, $prefix = '', $enabled = true, $translate=true, $checkbox='cb')
	{
		if (is_array($prefix)) {
			$options	= $prefix;
			$enabled	= array_key_exists('enabled',	$options) ? $options['enabled']		: $enabled;
			$translate	= array_key_exists('translate',	$options) ? $options['translate']	: $translate;
			$checkbox	= array_key_exists('checkbox',	$options) ? $options['checkbox']	: $checkbox;
			$prefix		= array_key_exists('prefix',	$options) ? $options['prefix']		: '';
		}
		$state		= JArrayHelper::getValue($states, (int) $value, $states[0]);
		$task		= array_key_exists('task',		$state) ? $state['task']		: $state[0];
		$text		= array_key_exists('text',		$state) ? $state['text']		: (array_key_exists(1,$state) ? $state[1] : '');
		$title		= array_key_exists('title',		$state) ? $state['title']		: (array_key_exists(2,$state) ? $state[2] : '');
		$tip		= array_key_exists('tip',		$state) ? $state['tip'	]		: (array_key_exists(3,$state) ? $state[3] : false);
		$active		= array_key_exists('active',	$state) ? $state['active']		: (array_key_exists(4,$state) ? $state[4] : '');
		$inactive	= array_key_exists('inactive',	$state) ? $state['inactive']	: (array_key_exists(5,$state) ? $state[5] : $active);
		
		return self::action($i, $task, $prefix, $text, $title, $tip, $active, $inactive, $enabled, $translate, $checkbox);
	}

	/**
	 * Returns a published state on a grid
	 *
	 * @param	int				$value		The state value.
	 * @param	int				$i			The row index
	 * @param	string|array	$prefix		An optional task prefix or an array of options
	 * @param	boolean			$enabled	An optional setting for access control on the action.
	 * @param	string			$checkbox	An optional prefix for checkboxes.
	 *
	 * @return The Html code
	 *
	 * @see JHtmlJGrid::state
	 *
	 * @since	1.6
	 */
	public static function published($value, $i, $prefix = '', $enabled = true, $checkbox='cb')
	{
		if (is_array($prefix)) {
			$options	= $prefix;
			$enabled	= array_key_exists('enabled',	$options) ? $options['enabled']		: $enabled;
			$checkbox	= array_key_exists('checkbox',	$options) ? $options['checkbox']	: $checkbox;
			$prefix		= array_key_exists('prefix',	$options) ? $options['prefix']		: '';
		}
		$states	= array(
			1	=> array('unpublish',	'JPUBLISHED',	$enabled ? 'JLIB_HTML_UNPUBLISH_ITEM' : 'JPUBLISHED',	false,	'publish'),
			0	=> array('publish',		'JUNPUBLISHED',	$enabled ? 'JLIB_HTML_PUBLISH_ITEM' : 'JUNPUBLISHED',	false,	'unpublish'),
			2	=> array('unpublish',	'JARCHIVED',	$enabled ? 'JLIB_HTML_UNPUBLISH_ITEM' : 'JARCHIVED',	false,	'archive'),
			-2	=> array('publish',		'JTRASHED',		$enabled ? 'JLIB_HTML_PUBLISH_ITEM' : 'JTRASHED',		false,	'trash'),
		);
		return self::state($states, $value, $i, $prefix, $enabled, true, $checkbox);
	}

	/**
	 * Returns a isDefault state on a grid
	 *
	 * @param	int				$value		The state value.
	 * @param	int				$i			The row index
	 * @param	string|array	$prefix		An optional task prefix or an array of options
	 * @param	boolean			$enabled	An optional setting for access control on the action.
	 * @param	string			$checkbox	An optional prefix for checkboxes.
	 *
	 * @return The Html code
	 *
	 * @see JHtmlJGrid::state
	 *
	 * @since	1.6
	 */
	public static function isdefault($value, $i, $prefix = '', $enabled = true, $checkbox='cb')
	{
		if (is_array($prefix)) {
			$options	= $prefix;
			$enabled	= array_key_exists('enabled',	$options) ? $options['enabled']		: $enabled;
			$checkbox	= array_key_exists('checkbox',	$options) ? $options['checkbox']	: $checkbox;
			$prefix		= array_key_exists('prefix',	$options) ? $options['prefix']		: '';
		}
		$states	= array(
			1	=> array('unsetDefault',	'JDEFAULT', $enabled ? 'JLIB_HTML_UNSETDEFAULT_ITEM' : 'JDEFAULT',	false,	'default'),
			0	=> array('setDefault', 		'',			$enabled ? 'JLIB_HTML_SETDEFAULT_ITEM' : '',			false,	'notdefault'),
		);
		return self::state($states, $value, $i, $prefix, $enabled, true, $checkbox);
	}

	/**
	 * Returns an array of standard published state filter options.
	 *
	 * @param	array			An array of configuration options.
	 *							This array can contain a list of key/value pairs where values are boolean
	 *							and keys can be taken from 'published', 'unpublished', 'archived', 'trash', 'all'.
	 *							These pairs determine which values are displayed.
	 * @return	string			The HTML code for the select tag
	 *
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
	 * Returns a checked-out icon
	 *
	 * @param	integer			$i			The row index.
	 * @param	string			$editorName	The name of the editor.
	 * @param	string			$time		The time that the object was checked out.
	 * @param	string|array	$prefix		An optional task prefix or an array of options
	 * @param	string			$text		The text to display
	 * @param	boolean			$enabled	True to enable the action.
	 *
	 * @return	string	The required HTML.
	 *
	 * @since	1.6
	 */
	public static function checkedout($i, $editorName, $time, $prefix='', $enabled=false, $checkbox='cb')
	{
		JHtml::_('behavior.tooltip');
		if (is_array($prefix)) {
			$options	= $prefix;
			$enabled	= array_key_exists('enabled',	$options) ? $options['enabled']		: $enabled;
			$checkbox	= array_key_exists('checkbox',	$options) ? $options['checkbox']	: $checkbox;
			$prefix		= array_key_exists('prefix',	$options) ? $options['prefix']		: '';
		}
		$text	= addslashes(htmlspecialchars($editorName, ENT_COMPAT, 'UTF-8'));
		$date	= addslashes(htmlspecialchars(JHTML::_('date',$time, '%A, %d %B %Y'), ENT_COMPAT, 'UTF-8'));
		$time	= addslashes(htmlspecialchars(JHTML::_('date',$time, '%H:%M'), ENT_COMPAT, 'UTF-8'));
		$title	= ($enabled ? JText::_('JLIB_HTML_CHECKIN') : JText::_('JLIB_HTML_CHECKED_OUT')) .'::'. $text .'<br />'. $date .'<br />'. $time;

		return  self::action($i, 'checkin', $prefix, JText::_('JLIB_HTML_CHECKED_OUT'), $title, true, 'checkedout', 'checkedout', $enabled, false, $checkbox);
	}

	/**
	 * Creates a order-up action icon.
	 *
	 * @param	integer			$i			The row index.
	 * @param	string			$task		An optional task to fire.
	 * @param	string|array	$prefix		An optional task prefix or an array of options
	 * @param	string			$text		An optional text to display
	 * @param	boolean			$enabled	An optional setting for access control on the action.
	 * @param	string			$checkbox	An optional prefix for checkboxes.
	 *
	 * @return	string	The required HTML.
	 *
	 * @since	1.6
	 */
	public static function orderUp($i, $task='orderup', $prefix='', $text = 'JLIB_HTML_MOVE_UP', $enabled = true, $checkbox='cb')
	{
		if (is_array($prefix)) {
			$options	= $prefix;
			$text		= array_key_exists('text',		$options) ? $options['text']		: $text;
			$enabled	= array_key_exists('enabled',	$options) ? $options['enabled']		: $enabled;
			$checkbox	= array_key_exists('checkbox',	$options) ? $options['checkbox']	: $checkbox;
			$prefix		= array_key_exists('prefix',	$options) ? $options['prefix']		: '';
		}
		return self::action($i, $task, $prefix, $text, $text, false, 'uparrow', 'uparrow_disabled', $enabled, true, $checkbox);
	}

	/**
	 * Creates a order-down action icon.
	 *
	 * @param	integer			$i			The row index.
	 * @param	string			$task		An optional task to fire.
	 * @param	string|array	$prefix		An optional task prefix or an array of options
	 * @param	string			$text		An optional text to display
	 * @param	boolean			$enabled	An optional setting for access control on the action.
	 * @param	string			$checkbox	An optional prefix for checkboxes.
	 *
	 * @return	string	The required HTML.
	 *
	 * @since	1.6
	 */
	public static function orderDown($i, $task='orderdown', $prefix='', $text = 'JLIB_HTML_MOVE_DOWN', $enabled = true, $checkbox='cb')
	{
		if (is_array($prefix)) {
			$options	= $prefix;
			$text		= array_key_exists('text',		$options) ? $options['text']		: $text;
			$enabled	= array_key_exists('enabled',	$options) ? $options['enabled']		: $enabled;
			$checkbox	= array_key_exists('checkbox',	$options) ? $options['checkbox']	: $checkbox;
			$prefix		= array_key_exists('prefix',	$options) ? $options['prefix']		: '';
		}
		return self::action($i, $task, $prefix, $text, $text, false, 'downarrow', 'downarrow_disabled', $enabled, true, $checkbox);
	}
}
