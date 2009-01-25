<?php
/**
* @version		$Id$
* @package		Joomla.Framework
* @subpackage	Document
* @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
* @license		GNU General Public License, see LICENSE.php
*/

// No direct access
defined('JPATH_BASE') or die();

/**
 * Component renderer
 *
 * @package		Joomla.Framework
 * @subpackage	Document
 * @since		1.5
 */
class JDocumentRendererInstallation extends JDocumentRenderer
{
	/**
	 * Renders a component script and returns the results as a string
	 *
	 * @access public
	 * @param string 	$component	The name of the component to render
	 * @param array 	$params	Associative array of values
	 * @return string	The output of the script
	 */
	public function render($component, $params = array(), $content = null)
	{
		return $content;
	}
}