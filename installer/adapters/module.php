<?php
/**
 * @version		$Id:module.php 6961 2007-03-15 16:06:53Z tcp $
 * @package		Joomla.Framework
 * @subpackage	Installer
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License, see LICENSE.php
  */

// No direct access
defined('JPATH_BASE') or die();
jimport('joomla.base.adapterinstance');

/**
 * Module installer
 *
 * @package		Joomla.Framework
 * @subpackage	Installer
 * @since		1.5
 */
class JInstallerModule extends JAdapterInstance
{
	/** @var string install function routing */
	protected $route = 'Install';
	protected $manifest = null;
	protected $manifest_script = null;
	protected $name = null;
	protected $element = null;
	protected $scriptElement = null;

	/**
	 * Custom install method
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	public function install()
	{
		// if this is an update, set the route accordingly
		if ($this->parent->getUpgrade()) {
			$this->route = 'Update';
		}

		// Get a database connector object
		$db =& $this->parent->getDBO();

		// Get the extension manifest object
		$manifest =& $this->parent->getManifest();
		$this->manifest =& $manifest->document;

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Manifest Document Setup Section
		 * ---------------------------------------------------------------------------------------------
		 */

		// Set the extensions name
		$name =& $this->manifest->getElementByPath('name');
		$name = JFilterInput::clean($name->data(), 'string');
		$this->set('name', $name);

		// Get the component description
		$description = & $this->manifest->getElementByPath('description');
		if ($description INSTANCEOF JSimpleXMLElement) {
			$this->parent->set('message', $description->data());
		} else {
			$this->parent->set('message', '');
		}

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Target Application Section
		 * ---------------------------------------------------------------------------------------------
		 */

		// Get the target application
		if ($cname = $this->manifest->attributes('client')) {
			// Attempt to map the client to a base path
			jimport('joomla.application.helper');
			$client =& JApplicationHelper::getClientInfo($cname, true);
			if ($client === false) {
				$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.JText::_('Unknown client type').' ['.$client->name.']');
				return false;
			}
			$basePath = $client->path;
			$clientId = $client->id;
		} else {
			// No client attribute was found so we assume the site as the client
			$cname = 'site';
			$basePath = JPATH_SITE;
			$clientId = 0;
		}

		// Set the installation path
		$element = '';
		$module_files =& $this->manifest->getElementByPath('files');
		if ($module_files INSTANCEOF JSimpleXMLElement && count($module_files->children())) {
			$files =& $module_files->children();
			foreach ($files as $file) {
				if ($file->attributes('module')) {
					$element = $file->attributes('module');
					$this->set('element',$element);
					break;
				}
			}
		}
		if (!empty ($element)) {
			$this->parent->setPath('extension_root', $basePath.DS.'modules'.DS.$element);
		} else {
			$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.JText::_('No module file specified'));
			return false;
		}

		/*
		 * If the module directory already exists, then we will assume that the
		 * module is already installed or another module is using that
		 * directory.
		 */
		if (file_exists($this->parent->getPath('extension_root'))&&!$this->parent->getOverwrite()) {
			// look for an update function or update tag
			$updateElement = $this->manifest->getElementByPath('update');
			// upgrade manually set
			// update function available
			// update tag detected
			if ($this->parent->getUpgrade() || ($this->parent->manifestClass && method_exists($this->parent->manifestClass,'update')) || is_a($updateElement, 'JSimpleXMLElement')) {
				// force these one
				$this->parent->setOverwrite(true);
				$this->parent->setUpgrade(true);
				$this->route = 'Update';
			} else if (!$this->parent->getOverwrite()) { // overwrite is set
				// we didn't have overwrite set, find an udpate function or find an update tag so lets call it safe
				$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.JText::_('Another module is already using directory').': "'.$this->parent->getPath('extension_root').'"');
				return false;
			}
		}

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Installer Trigger Loading
		 * ---------------------------------------------------------------------------------------------
		 */
		// If there is an manifest class file, lets load it; we'll copy it later (don't have dest yet)
		$this->scriptElement =& $this->manifest->getElementByPath('scriptfile');
		if (is_a($this->scriptElement, 'JSimpleXMLElement')) {
			$manifestScript = $this->scriptElement->data();
			$manifestScriptFile = $this->parent->getPath('source').DS.$manifestScript;
			if (is_file($manifestScriptFile)) {
				// load the file
				include_once($manifestScriptFile);
			}
			// Set the class name
			$classname = $element.'InstallerScript';
			if (class_exists($classname)) {
				// create a new instance
				$this->parent->manifestClass = new $classname($this);
				// and set this so we can copy it later
				$this->set('manifest_script', $manifestScript);
				// Note: if we don't find the class, don't bother to copy the file
			}
		}

		// run preflight if possible (since we know we're not an update)
		ob_start();
		ob_implicit_flush(false);
		if ($this->parent->manifestClass && method_exists($this->parent->manifestClass,'preflight')) $this->parent->manifestClass->preflight($this->route, $this);
		$msg = ob_get_contents(); // create msg object; first use here
		ob_end_clean();

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Filesystem Processing Section
		 * ---------------------------------------------------------------------------------------------
		 */

		// If the module directory does not exist, lets create it
		$created = false;
		if (!file_exists($this->parent->getPath('extension_root'))) {
			if (!$created = JFolder::create($this->parent->getPath('extension_root'))) {
				$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.JText::_('Failed to create directory').': "'.$this->parent->getPath('extension_root').'"');
				return false;
			}
		}

		/*
		 * Since we created the module directory and will want to remove it if
		 * we have to roll back the installation, lets add it to the
		 * installation step stack
		 */
		if ($created) {
			$this->parent->pushStep(array ('type' => 'folder', 'path' => $this->parent->getPath('extension_root')));
		}

		// Copy all necessary files
		if ($this->parent->parseFiles($module_files, -1) === false) {
			// Install failed, roll back changes
			$this->parent->abort();
			return false;
		}

		// Parse optional tags
		$this->parent->parseMedia($this->manifest->getElementByPath('media'), $clientId);
		$this->parent->parseLanguages($this->manifest->getElementByPath('languages'), $clientId);

		// Parse deprecated tags
		$this->parent->parseFiles($this->manifest->getElementByPath('images'), -1);

		// If there is a manifest script, lets copy it.
		if ($this->get('manifest_script')) {
			$path['src'] = $this->parent->getPath('source').DS.$this->get('manifest_script');
			$path['dest'] = $this->parent->getPath('extension_root').DS.$this->get('manifest_script');

			if (!file_exists($path['dest'])) {
				if (!$this->parent->copyFiles(array ($path))) {
					// Install failed, rollback changes
					$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.JText::_('Could not copy PHP manifest file.'));
					return false;
				}
			}
		}

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Database Processing Section
		 * ---------------------------------------------------------------------------------------------
		 */

		// Check to see if a module by the same name is already installed
		// If it is, then update the table because if the files aren't there
		// we can assume that it was (badly) uninstalled
		// If it isn't, add an entry to extensions
		$query = 'SELECT `extension_id`' .
				' FROM `#__extensions` ' .
				' WHERE element = '.$db->Quote($element) .
				' AND client_id = '.(int)$clientId;
		$db->setQuery($query);
		try {
			$db->Query();
		} catch(JException $e) {
			// Install failed, roll back changes
			$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.$db->stderr(true));
			return false;
		}
		$id = $db->loadResult();

		// Was there a module already installed with the same name?
		if ($id) {
			// load the entry and update the manifest_cache
			$row =& JTable::getInstance('extension');
			$row->load($id);
			$row->name = $this->get('name'); // update name
			$row->manifest_cache = $this->parent->generateManifestCache(); // update manifest
			if (!$row->store()) {
				// Install failed, roll back changes
				$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.$db->stderr(true));
				return false;
			}
		} else {
			$row = & JTable::getInstance('extension');
			$row->name = $this->get('name');
			$row->type = 'module';
			$row->element = $this->get('element');
			$row->folder = ''; // There is no folder for modules
			$row->enabled = 1;
			$row->protected = 0;
			$row->access = $clientId == 1 ? 2 : 0;
			$row->client_id = $clientId;
			$row->params = $this->parent->getParams();
			$row->data = ''; // custom data
			$row->manifest_cache = $this->parent->generateManifestCache();

			if (!$row->store()) {
				// Install failed, roll back changes
				$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.$db->stderr(true));
				return false;
			}

			// Since we have created a module item, we add it to the installation step stack
			// so that if we have to rollback the changes we can undo it.
			$this->parent->pushStep(array ('type' => 'extension', 'extension_id' => $row->extension_id));
		}

		/*
		 * Let's run the queries for the module
		 *	If Joomla 1.5 compatible, with discreet sql files - execute appropriate
		 *	file for utf-8 support or non-utf-8 support
		 */
		// try for Joomla 1.5 type queries
		// second argument is the utf compatible version attribute
		$utfresult = $this->parent->parseSQLFiles($this->manifest->getElementByPath(strtolower($this->route).'/sql'));
		if ($utfresult === false) {
			// Install failed, rollback changes
			$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.JText::_('SQLERRORORFILE')." ".$db->stderr(true));
			return false;
		}

		// Start Joomla! 1.6
		ob_start();
		ob_implicit_flush(false);
		if ($this->parent->manifestClass && method_exists($this->parent->manifestClass,$this->route)) $this->parent->manifestClass->{$this->route}($this);
		$msg .= ob_get_contents(); // append messages
		ob_end_clean();

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Finalization and Cleanup Section
		 * ---------------------------------------------------------------------------------------------
		 */

		// Lastly, we will copy the manifest file to its appropriate place.
		if (!$this->parent->copyManifest(-1)) {
			// Install failed, rollback changes
			$this->parent->abort(JText::_('Module').' '.JText::_($this->route).': '.JText::_('Could not copy setup file'));
			return false;
		}

		// And now we run the postflight
		ob_start();
		ob_implicit_flush(false);
		if ($this->parent->manifestClass && method_exists($this->parent->manifestClass,'postflight')) $this->parent->manifestClass->postflight($this->route, $this);
		$msg .= ob_get_contents(); // append messages
		ob_end_clean();
		if ($msg != '') {
			$this->parent->set('extension_message', $msg);
		}
		return true;
	}

	/**
	 * Custom update method
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.6
	 */
	function update()
	{
		// set the overwrite setting
		$this->parent->setOverwrite(true);
		$this->parent->setUpgrade(true);
		// set the route for the install
		$this->route = 'Update';
		// go to install which handles updates properly
		return $this->install();
	}

	/**
	 * Custom discover method
	 *
	 * @access public
	 * @return array(JExtension) list of extensions available
	 * @since 1.6
	 */
	function discover() {
		$results = Array();
		$site_list = JFolder::folders(JPATH_SITE.DS.'modules');
		$admin_list = JFolder::folders(JPATH_ADMINISTRATOR.DS.'modules');
		$site_info = JApplicationHelper::getClientInfo('site', true);
		$admin_info = JApplicationHelper::getClientInfo('administrator', true);
		foreach($site_list as $module) {
			$extension =& JTable::getInstance('extension');
			$extension->type = 'module';
			$extension->client_id = $site_info->id;
			$extension->element = $module;
			$extension->name = $module;
			$extension->state = -1;
			$results[] = $extension;
		}
		foreach($admin_list as $module) {
			$extension =& JTable::getInstance('extension');
			$extension->type = 'module';
			$extension->client_id = $admin_info->id;
			$extension->element = $module;
			$extension->name = $module;
			$extension->state = -1;
			$results[] = $extension;
		}
		return $results;
	}

	/**
	 * Custom discover_install method
	 *
	 * @access public
	 * @param int $id The id of the extension to install (from #__discoveredextensions)
	 * @return void
	 * @since 1.6
	 */
	function discover_install() {
		// Modules are like templates, and are one of the easiest
		// If its not in the extensions table we just add it
		$client = JApplicationHelper::getClientInfo($this->parent->extension->client_id);
		$manifestPath = $client->path . DS . 'modules'. DS . $this->parent->extension->element . DS . $this->parent->extension->element . '.xml';
		$this->parent->manifest = $this->parent->isManifest($manifestPath);
		$this->parent->setPath('manifest', $manifestPath);
		$manifest_details = JApplicationHelper::parseXMLInstallFile($this->parent->getPath('manifest'));
		$this->parent->extension->manifest_cache = serialize($manifest_details);
		$this->parent->extension->state = 0;
		$this->parent->extension->name = $manifest_details['name'];
		$this->parent->extension->enabled = 1;
		$this->parent->extension->params = $this->parent->getParams();
		if ($this->parent->extension->store()) {
			return true;
		} else {
			JError::raiseWarning(101, JText::_('Module').' '.JText::_('Discover Install').': '.JText::_('Failed to store extension details'));
			return false;
		}
	}

	function refreshManifestCache() {
		$client = JApplicationHelper::getClientInfo($this->parent->extension->client_id);
		$manifestPath = $client->path . DS . 'modules'. DS . $this->parent->extension->element . DS . $this->parent->extension->element . '.xml';
		$this->parent->manifest = $this->parent->isManifest($manifestPath);
		$this->parent->setPath('manifest', $manifestPath);
		$manifest_details = JApplicationHelper::parseXMLInstallFile($this->parent->getPath('manifest'));
		$this->parent->extension->manifest_cache = serialize($manifest_details);
		$this->parent->extension->name = $manifest_details['name'];
		if ($this->parent->extension->store()) {
			return true;
		} else {
			JError::raiseWarning(101, JText::_('Module').' '.JText::_('Refresh Manifest Cache').': '.JText::_('Failed to store extension details'));
			return false;
		}
	}

	/**
	 * Custom uninstall method
	 *
	 * @access	public
	 * @param	int		$id			The id of the module to uninstall
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	public function uninstall($id)
	{
		// Initialize variables
		$row	= null;
		$retval = true;
		$db		=& $this->parent->getDBO();

		// First order of business will be to load the module object table from the database.
		// This should give us the necessary information to proceed.
		$row = & JTable::getInstance('extension');
		if (!$row->load((int) $id) || !strlen($row->element)) {
			JError::raiseWarning(100, JText::_('ERRORUNKOWNEXTENSION'));
			return false;
		}

		// Is the module we are trying to uninstall a core one?
		// Because that is not a good idea...
		if ($row->protected) {
			JError::raiseWarning(100, JText::_('Module').' '.JText::_('Uninstall').': '.JText::sprintf('WARNCOREMODULE', $row->name)."<br />".JText::_('WARNCOREMODULE2'));
			return false;
		}

		// Get the extension root path
		jimport('joomla.application.helper');
		$element = $row->element;
		$client =& JApplicationHelper::getClientInfo($row->client_id);
		if ($client === false) {
			$this->parent->abort(JText::_('Module').' '.JText::_('Uninstall').': '.JText::_('Unknown client type').' ['.$row->client_id.']');
			return false;
		}
		$this->parent->setPath('extension_root', $client->path.DS.'modules'.DS.$element);

		// Get the package manifest objecct
		$this->parent->setPath('source', $this->parent->getPath('extension_root'));
		$manifest =& $this->parent->getManifest();
		$this->manifest =& $manifest->document;

		// If there is an manifest class file, lets load it
		$this->scriptElement =& $this->manifest->getElementByPath('scriptfile');
		if (is_a($this->scriptElement, 'JSimpleXMLElement')) {
			$manifestScript = $this->scriptElement->data();
			$manifestScriptFile = $this->parent->getPath('extension_root').DS.$manifestScript;
			if (is_file($manifestScriptFile)) {
				// load the file
				include_once($manifestScriptFile);
			}
			// Set the class name
			$classname = $element.'InstallerScript';
			if (class_exists($classname)) {
				// create a new instance
				$this->parent->manifestClass = new $classname($this);
				// and set this so we can copy it later
				$this->set('manifest_script', $manifestScript);
				// Note: if we don't find the class, don't bother to copy the file
			}
		}

		ob_start();
		ob_implicit_flush(false);
		// run uninstall if possible
		if ($this->parent->manifestClass && method_exists($this->parent->manifestClass,'uninstall')) $this->parent->manifestClass->uninstall($this);
		$msg = ob_get_contents();
		ob_end_clean();

		if (!$manifest INSTANCEOF JSimpleXML) {
			// Make sure we delete the folders
			JFolder::delete($this->parent->getPath('extension_root'));
			JError::raiseWarning(100, 'Module Uninstall: Package manifest file invalid or not found');
			return false;
		}

		/*
		 * Let's run the uninstall queries for the component
		 *	If Joomla 1.5 compatible, with discreet sql files - execute appropriate
		 *	file for utf-8 support or non-utf support
		 */
		// try for Joomla 1.5 type queries
		// second argument is the utf compatible version attribute
		$utfresult = $this->parent->parseSQLFiles($this->manifest->getElementByPath('uninstall/sql'));
		if ($utfresult === false) {
			// Install failed, rollback changes
			JError::raiseWarning(100, JText::_('Module').' '.JText::_('Uninstall').': '.JText::_('SQLERRORORFILE')." ".$db->stderr(true));
			$retval = false;
		}

		// Remove other files
		$root =& $manifest->document;
		$this->parent->removeFiles($root->getElementByPath('media'));
		$this->parent->removeFiles($root->getElementByPath('languages'), $row->client_id);

		// Lets delete all the module copies for the type we are uninstalling
		$query = 'SELECT `id`' .
				' FROM `#__modules`' .
				' WHERE module = '.$db->Quote($row->element) .
				' AND client_id = '.(int)$row->client_id;
		$db->setQuery($query);
		try {
			$modules = $db->loadResultArray();
		} catch(JException $e) {
			$modules = array();
		}

		// Do we have any module copies?
		if (count($modules)) {
			// Ensure the list is sane
			JArrayHelper::toInteger($modules);
			$modID = implode(',', $modules);

			// Wipe out any items assigned to menus
			$query = 'DELETE' .
					' FROM #__modules_menu' .
					' WHERE moduleid IN ('.$modID.')';
			$db->setQuery($query);
			try {
				$db->query();
			} catch(JException $e) {
				JError::raiseWarning(100, JText::_('Module').' '.JText::_('Uninstall').': '.$db->stderr(true));
				$retval = false;
			}

			// Wipe out any instances in the modules table
			$query = 'DELETE' .
					' FROM #__modules' .
					' WHERE id IN ('.$modID.')';
			$db->setQuery($query);
			try {
				$db->query();
			} catch (JException $e) {
				JError::raiseWarning(100, JText::_('Module').' '.JText::_('Uninstall').': '.$db->stderr(true));
				$retval = false;
			}
		}


		// Now we will no longer need the module object, so lets delete it and free up memory
		$row->delete($row->extension_id);
		$query = 'DELETE FROM `#__modules` WHERE module = '.$db->Quote($row->module) . ' AND client_id = ' . $row->client_id;
		$db->setQuery($query);
		try {
			$db->Query(); // clean up any other ones that might exist as well
		} catch(JException $e) {
			//Ignore the error...
		}

		unset ($row);

		// Remove the installation folder
		if (!JFolder::delete($this->parent->getPath('extension_root'))) {
			// JFolder should raise an error
			$retval = false;
		}
		return $retval;
	}

	/**
	 * Custom rollback method
	 * 	- Roll back the menu item
	 *
	 * @access	public
	 * @param	array	$arg	Installation step to rollback
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	protected function _rollback_menu($arg)
	{
		// Get database connector object
		$db =& $this->parent->getDBO();

		// Remove the entry from the #__modules_menu table
		$query = 'DELETE' .
				' FROM `#__modules_menu`' .
				' WHERE moduleid='.(int)$arg['id'];
		$db->setQuery($query);
		try {
			return $db->query();
		} catch(JException $e) {
			return false;
		}
	}

	/**
	 * Custom rollback method
	 * 	- Roll back the module item
	 *
	 * @access	public
	 * @param	array	$arg	Installation step to rollback
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	protected function _rollback_module($arg)
	{
		// Get database connector object
		$db =& $this->parent->getDBO();

		// Remove the entry from the #__modules table
		$query = 'DELETE' .
				' FROM `#__modules`' .
				' WHERE id='.(int)$arg['id'];
		$db->setQuery($query);
		try {
			return $db->query();
		} catch(JException $e) {
			return false;
		}
	}
}
