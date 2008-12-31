<?php
/**
* @version		$Id$
* @package		Joomla.Framework
* @subpackage	Parameter
* @copyright	Copyright (C) 2005 - 2008 Open Source Matters, Inc. All rights reserved.
* @license		GNU General Public License, see LICENSE.php
*/

// No direct access
defined('JPATH_BASE') or die();

/**
 * Renders a list element
 *
 * @package 	Joomla.Framework
 * @subpackage		Parameter
 * @since		1.5
 */

class JElementList extends JElement
{
	/**
	* Element type
	*
	* @access	protected
	* @var		string
	*/
	protected $_name = 'List';

	/**
	 * Get the options for the element
	 *
	 * @param	object $node
	 * @return	array
	 * @since	1.6
	 */
	protected function _getOptions(&$node)
	{
		$options = array ();
		foreach ($node->children() as $option)
		{
			$val	= $option->attributes('value');
			$text	= $option->data();
			$options[] = JHtml::_('select.option', $val, JText::_($text));
		}
		return $options;
	}

	public function fetchElement($name, $value, &$node, $control_name)
	{
		$attribs = ($node->attributes('class') ? 'class="'.$node->attributes('class').'"' : 'class="inputbox"');

		return JHtml::_('select.genericlist',  $this->_getOptions($node), ''.$control_name.'['.$name.']', $attribs, 'value', 'text', $value, $control_name.$name);
	}
}
