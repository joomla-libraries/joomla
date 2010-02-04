<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	Plugin
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

/**
 * Plugin helper class
 *
 * @static
 * @package		Joomla.Framework
 * @subpackage	Plugin
 * @since		1.5
 */
abstract class JPluginHelper
{
	/**
	 * Get the plugin data of a specific type if no specific plugin is specified
	 * otherwise only the specific plugin data is returned.
	 *
	 * @access	public
	 * @param	string		$type		The plugin type, relates to the sub-directory in the plugins directory.
	 * @param	string		$plugin		The plugin name.
	 * @return	mixed		An array of plugin data objects, or a plugin data object.
	 */
	public static function getPlugin($type, $plugin = null)
	{
		$result		= array();
		$plugins	= self::_load();

		// Find the correct plugin(s) to return.
		for ($i = 0, $t = count($plugins); $i < $t; $i++)
		{
			// Are we loading a single plugin or a group?
			if (is_null($plugin))
			{
				// Is this the right plugin?
				if ($plugins[$i]->type == $type) {
					$result[] = $plugins[$i];
				}
			}
			else
			{
				// Is this plugin in the right group?
				if ($plugins[$i]->type == $type && $plugins[$i]->name == $plugin) {
					$result = $plugins[$i];
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * Checks if a plugin is enabled.
	 *
	 * @access	public
	 * @param	string		$type		The plugin type, relates to the sub-directory in the plugins directory.
	 * @param	string		$plugin		The plugin name.
	 * @return	boolean
	 */
	public static function isEnabled($type, $plugin = null)
	{
		$result = &self::getPlugin($type, $plugin);
		return (!empty($result));
	}

	/**
	 * Loads all the plugin files for a particular type if no specific plugin is specified
	 * otherwise only the specific pugin is loaded.
	 *
	 * @access	public
	 * @param	string		$type		The plugin type, relates to the sub-directory in the plugins directory.
	 * @param	string		$plugin		The plugin name.
	 * @return	boolean		True if success
	 */
	public static function importPlugin($type, $plugin = null, $autocreate = true, $dispatcher = null)
	{
		$results = null;

		// Load the plugins from the database.
		$plugins = self::_load();

		// Get the specified plugin(s).
		for ($i = 0, $t = count($plugins); $i < $t; $i++) {
			if ($plugins[$i]->type == $type && ($plugins[$i]->name == $plugin ||  $plugin === null)) {
				self::_import($plugins[$i], $autocreate, $dispatcher);
				$results = true;
			}
		}

		return $results;
	}

	/**
	 * Loads the plugin file
	 *
	 * @access	private
	 * @return	boolean		True if success
	 */
	protected static function _import(&$plugin, $autocreate = true, $dispatcher = null)
	{
		static $paths = array();

		$plugin->type = preg_replace('/[^A-Z0-9_\.-]/i', '', $plugin->type);
		$plugin->name = preg_replace('/[^A-Z0-9_\.-]/i', '', $plugin->name);

		$legacypath	= JPATH_PLUGINS.DS.$plugin->type.DS.$plugin->name.'.php';
		$path = JPATH_PLUGINS.DS.$plugin->type.DS.$plugin->name.DS.$plugin->name.'.php';

		if (!isset( $paths[$path] ) || !isset($paths[$legacypath]))
		{
			$pathExists = file_exists($path);
			if ($pathExists || file_exists($legacypath))
			{
				$path = $pathExists ? $path : $legacypath;

				jimport('joomla.plugin.plugin');
				if (!isset($paths[$path])) {
					require_once $path;
				}
				$paths[$path] = true;

				if ($autocreate)
				{
					// Makes sure we have an event dispatcher
					if (!is_object($dispatcher)) {
						$dispatcher = &JDispatcher::getInstance();
					}

					$className = 'plg'.$plugin->type.$plugin->name;
					if (class_exists($className))
					{
						// Load the plugin from the database.
						$plugin = &self::getPlugin($plugin->type, $plugin->name);

						// Instantiate and register the plugin.
						new $className($dispatcher, (array)($plugin));
					}
				}
			}
			else
			{
				$paths[$path] = false;
			}
		}
	}

	/**
	 * Loads the published plugins
	 *
	 * @access private
	 */
	protected static function _load()
	{
		static $plugins;

		if (isset($plugins)) {
			return $plugins;
		}

		$db		= &JFactory::getDbo();
		$user	= &JFactory::getUser();

		if (isset($user))
		{
			$query = 'SELECT folder AS type, element AS name, params'
				. ' FROM #__extensions'
				. ' WHERE enabled >= 1'
				. ' AND type = "plugin"'
				. ' AND state >= 0'
				. ' AND access IN ('.implode(',', $user->authorisedLevels()).')'
				. ' ORDER BY ordering';
		}
		else
		{
			$query = 'SELECT folder AS type, element AS name, params'
				. ' FROM #__extensions'
				. ' WHERE enabled >= 1'
				. ' AND type = "plugin"'
				. ' AND state >= 0'
				. ' ORDER BY ordering';
		}

		$db->setQuery($query);

		if (!($plugins = $db->loadObjectList())) {
			JError::raiseWarning('SOME_ERROR_CODE', 'Error loading Plugins: ' . $db->getErrorMsg());
			return false;
		}

		return $plugins;
	}
}