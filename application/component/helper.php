<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	Application
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('JPATH_BASE') or die;

/**
 * Component helper class
 *
 * @package		Joomla.Framework
 * @subpackage	Application
 * @since		1.5
 */
class JComponentHelper
{
	/**
	 * The component list cache
	 *
	 * @var	array
	 */
	protected static $_components = null;

	/**
	 * Get the component information.
	 *
	 * @param	string $option 	The component option.
	 * @param 	boolean	$string	If set and a component does not exist, the enabled attribue will be set to false
	 * @return	object			An object with the fields for the component.
	 */
	public static function &getComponent($option, $strict = false)
	{
		if (self::$_components === null) {
			self::_load();
		}

		if (isset(self::$_components[$option]))
		{
			// Convert the params to an object.
			if (is_string(self::$_components[$option]->params))
			{
				$temp = new JParameter(self::$_components[$option]->params);
				self::$_components[$option]->params = $temp;
			}
			$result = &self::$_components[$option];
		}
		else
		{
			$result				= new stdClass;
			$result->enabled	= $strict ? false : true;
			$result->params		= new JParameter;
		}

		return $result;
	}

	/**
	 * Checks if the component is enabled
	 *
	 * @param	string	$option		The component option.
	 * @param 	boolean	$string		If set and a component does not exist, false will be returned
	 * @return	boolean
	 */
	public static function isEnabled($option, $strict = false)
	{
		$result = &self::getComponent($option, $strict);
		return ($result->enabled | JFactory::getApplication()->isAdmin());
	}

	/**
	 * Gets the parameter object for the component
	 *
	 * @param	string $option	The option for the component.
	 * @param 	boolean	$string	If set and a component does not exist, false will be returned
	 * @return	JRegistry		As of 1.6, this method returns a JRegistry (previous versions returned JParameter).
	 */
	public static function &getParams($option, $strict = false)
	{
		$component = &self::getComponent($option, $strict);
		return $component->params;
	}

	/**
	 * Render the component.
	 * @param	string	The component option.
	 */
	public static function renderComponent($option, $params = array())
	{
		// Initialise variables.
		$app	= &JFactory::getApplication();

		if (empty($option)) {
			// Throw 404 if no component
			JError::raiseError(404, JText::_("Component Not Found"));
			return;
		}

		$scope = $app->scope; //record the scope
		$app->scope = $option;  //set scope to component name

		// Build the component path.
		$option	= preg_replace('/[^A-Z0-9_\.-]/i', '', $option);
		$file	= substr($option, 4);

		// Define component path.
		define('JPATH_COMPONENT',				JPATH_BASE.DS.'components'.DS.$option);
		define('JPATH_COMPONENT_SITE',			JPATH_SITE.DS.'components'.DS.$option);
		define('JPATH_COMPONENT_ADMINISTRATOR',	JPATH_ADMINISTRATOR.DS.'components'.DS.$option);

		// get component path
		if ($app->isAdmin() && file_exists(JPATH_COMPONENT.DS.'admin.'.$file.'.php')) {
			$path = JPATH_COMPONENT.DS.'admin.'.$file.'.php';
		}
		else {
			$path = JPATH_COMPONENT.DS.$file.'.php';
		}

		// If component disabled throw error
		if (!self::isEnabled($option) || !file_exists($path)) {
			JError::raiseError(404, JText::_('Component Not Found'));
		}

		$task = JRequest::getString('task');

		// Load common and local language files.
		$lang = &JFactory::getLanguage();
		$lang->load($option);
		$lang->load($option, JPATH_COMPONENT);

		// Handle template preview outlining.
		$contents = null;

		// Execute the component.
		ob_start();
		require_once $path;
		$contents = ob_get_contents();
		ob_end_clean();

		// Build the component toolbar
		jimport('joomla.application.helper');
		if (($path = JApplicationHelper::getPath('toolbar')) && $app->isAdmin())
		{
			// Get the task again, in case it has changed
			$task = JRequest::getString('task');

			// Make the toolbar
			include_once($path);
		}

		$app->scope = $scope; //revert the scope

		return $contents;
	}

	/**
	 * Load the installed components into the _components property.
	 *
	 * @return	boolean
	 */
	protected static function _load()
	{
		jimport('joomla.database.query');
		$db		= &JFactory::getDbo();
		$query	= new JQuery;
		$query->select('*');
		$query->from('#__components');
		$query->where('parent = 0');
		$db->setQuery($query);

		self::$_components = $db->loadObjectList('option');

		if ($error = $db->getErrorMsg())
		{
			// Fatal error.
			JError::raiseWarning(500, 'Error loading components: '.$error);
			return false;
		}

		return true;
	}
}
