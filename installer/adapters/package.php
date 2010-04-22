<?php
/**
 * @version		$Id$
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('JPATH_BASE') or die;

jimport('joomla.base.adapterinstance');

/**
 * Package installer
 *
 * @package		Joomla.Framework
 * @subpackage	Installer
 * @since		1.6
 */
class JInstallerPackage extends JAdapterInstance
{

	public function loadLanguage($path)
	{
		$this->manifest = &$this->parent->getManifest();
		$extension = 'pkg_' . strtolower(JFilterInput::getInstance()->clean((string)$this->manifest->name, 'cmd'));
		$lang =& JFactory::getLanguage();
		$source = $path;
			$lang->load($extension . '.sys', $source, null, false, false)
		||	$lang->load($extension . '.sys', JPATH_SITE, null, false, false)
		||	$lang->load($extension . '.sys', $source, $lang->getDefault(), false, false)
		||	$lang->load($extension . '.sys', JPATH_SITE, $lang->getDefault(), false, false);
	}
	/**
	 * Custom install method
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function install()
	{
		// Get the extension manifest object
		$this->manifest = $this->parent->getManifest();

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Manifest Document Setup Section
		 * ---------------------------------------------------------------------------------------------
		 */

		// Set the extensions name
		$name = (string)$this->manifest->packagename;
		$name = JFilterInput::getInstance()->clean($name, 'cmd');
		$this->set('name', $name);

		// Get the component description
		$description = (string)$this->manifest->description;
		if ($description) {
			$this->parent->set('message', JText::_($description));
		}
		else {
			$this->parent->set('message', '');
		}

		// Set the installation path
		$group = (string)$this->manifest->packagename;
		if (!empty($group))
		{
			// TODO: Remark this location
			$this->parent->setPath('extension_root', JPATH_ROOT.DS.'libraries'.DS.implode(DS,explode('/',$group)));
		}
		else
		{
			$this->parent->abort(JText::_('JLIB_INSTALLER_ABORT_PACK_INSTALL_NO_PACK'));
			return false;
		}

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Filesystem Processing Section
		 * ---------------------------------------------------------------------------------------------
		 */

		// If the plugin directory does not exist, lets create it
		$created = false;
		if (!file_exists($this->parent->getPath('extension_root')))
		{
			if (!$created = JFolder::create($this->parent->getPath('extension_root')))
			{
				$this->parent->abort(JText::sprintf('JLIB_INSTALLER_ABORT_PACK_INSTALL_CREATE_DIRECTORY', $this->parent->getPath('extension_root')));
				return false;
			}
		}

		/*
		 * If we created the plugin directory and will want to remove it if we
		 * have to roll back the installation, lets add it to the installation
		 * step stack
		 */
		if ($created) {
			$this->parent->pushStep(array ('type' => 'folder', 'path' => $this->parent->getPath('extension_root')));
		}

		if ($folder = (string)$this->manifest->files->attributes()->folder) {
			$source = $this->parent->getPath('source').DS.$folder;
		}
		else {
			$source = $this->parent->getPath('source');
		}

		// Install all necessary files
		if (count($this->manifest->files->children()))
		{
			foreach ($this->manifest->files->children() as $child)
			{
				$file = $source.DS.$child;
				jimport('joomla.installer.helper');
				if (is_dir($file))
				{
					// if its actually a directory then fill it up
					$package = Array();
					$package['dir'] = $file;
					$package['type'] = JInstallerHelper::detectType($file);
				}
				else { // if its an archive
					$package = JInstallerHelper::unpack($file);
				}
				$tmpInstaller = new JInstaller();
				if (!$tmpInstaller->install($package['dir']))
				{
					$this->parent->abort(JText::sprintf('JLIB_INSTALLER_ABORT_PACK_INSTALL_ERROR_EXTENSION', basename($file)));
					return false;
				}
			}
		}
		else
		{
			$this->parent->abort(JText::sprintf('JLIB_INSTALLER_ABORT_PACK_INSTALL_NO_FILES', print_r($this->manifest->files->children(), true)));
			return false;
		}

		// Parse optional tags
		$this->parent->parseLanguages($this->manifest->languages);

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Extension Registration
		 * ---------------------------------------------------------------------------------------------
		 */
		$row = & JTable::getInstance('extension');
		$row->name = $this->get('name');
		$row->type = 'package';
		$row->element = $this->get('element');
		$row->folder = ''; // There is no folder for modules
		$row->enabled = 1;
		$row->protected = 0;
		$row->access = 1;
		$row->client_id = 0;
		$row->params = $this->parent->getParams();
		$row->custom_data = ''; // custom data
		$row->manifest_cache = $this->parent->generateManifestCache();

		if (!$row->store())
		{
			// Install failed, roll back changes
			$this->parent->abort(JText::sprintf('JLIB_INSTALLER_ABORT_PACK_INSTALL_ROLLBACK', $db->stderr(true)));
			return false;
		}

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Finalization and Cleanup Section
		 * ---------------------------------------------------------------------------------------------
		 */

		// Lastly, we will copy the manifest file to its appropriate place.
		$manifest = Array();
		$manifest['src'] = $this->parent->getPath('manifest');
		$manifest['dest'] = JPATH_MANIFESTS.DS.'packages'.DS.basename($this->parent->getPath('manifest'));

		if (!$this->parent->copyFiles(array($manifest), true))
		{
			// Install failed, rollback changes
			$this->parent->abort(JText::_('JLIB_INSTALLER_ABORT_PACK_INSTALL_COPY_SETUP'));
			return false;
		}
		return true;
	}

	/**
	 * Custom uninstall method
	 *
	 * @access	public
	 * @param	int		$id	The id of the package to uninstall
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function uninstall($id)
	{
		// Initialise variables.
		$row	= null;
		$retval = true;

		$row = & JTable::getInstance('extension');
		$row->load($id);

		$manifestFile = JPATH_MANIFESTS.DS.'packages' . DS . $row->get('element') .'.xml';
		$manifest = new JPackageManifest($manifestFile);

		// Set the plugin root path
		$this->parent->setPath('extension_root', JPATH_MANIFESTS.DS.'packages'.DS.$manifest->packagename);

		// Because libraries may not have their own folders we cannot use the standard method of finding an installation manifest
		if (file_exists($manifestFile))
		{
			$xml =JFactory::getXML($manifestFile);

			// If we cannot load the xml file return false
			if (!$xml)
			{
				JError::raiseWarning(100, JText::_('JLIB_INSTALLER_ERROR_PACK_UNINSTALL_LOAD_MANIFEST'));
				return false;
			}

			/*
			 * Check for a valid XML root tag.
			 * @todo: Remove backwards compatability in a future version
			 * Should be 'extension', but for backward compatability we will accept 'install'.
			 */
			if ($xml->getName() != 'install' && $xml->getName() != 'extension')
			{
				JError::raiseWarning(100, JText::_('JLIB_INSTALLER_ERROR_PACK_UNINSTALL_INVALID_MANIFEST'));
				return false;
			}

			$error = false;
			foreach ($manifest->filelist as $extension)
			{
				$tmpInstaller = new JInstaller();
				$id = $this->_getExtensionID($extension->type, $extension->id, $extension->client, $extension->group);
				$client = JApplicationHelper::getClientInfo($extension->client,true);
				if (!$tmpInstaller->uninstall($extension->type, $id, $client->id))
				{
					$error = true;
					JError::raiseWarning(100, JText::sprintf('JLIB_INSTALLER_ERROR_PACK_UNINSTALL_NOT_PROPER', basename($extension->filename)));
					//$this->parent->abort(JText::_('Package').' '.JText::_('Uninstall').': '.JText::_('There was an error removing an extension, try reinstalling:') . basename($extension->filename));
					//return false;
				}
			}
			$this->parent->removeFiles($xml->languages);
			// clean up manifest file after we're done if there were no errors
			if (!$error) {
				JFile::delete($manifestFile);
			}
			else {
				JError::raiseWarning(100, JText::_('JLIB_INSTALLER_ERROR_PACK_UNINSTALL_MANIFEST_NOT_REMOVED'));
			}
		}
		else
		{
			JError::raiseWarning(100, JText::sprintf('JLIB_INSTALLER_ERROR_PACK_UNINSTALL_INVALID_NOTFOUND_MANIFEST', $id));
			return false;
		}

		return $retval;
	}

	function _getExtensionID($type, $id, $client, $group)
	{
		$db		= &$this->parent->getDbo();
		$result = $id;

		switch($type)
		{
			case 'plugin':
				$db->setQuery("SELECT extension_id FROM #__extensions WHERE `type` = 'plugin' AND `folder` = '$group' AND `element` = '$id'");
				$result = $db->loadResult();
				break;

			case 'component':
				$db->setQuery("SELECT extension_id FROM #__extensions WHERE `type` = 'component' AND `element` = '$id'");
				$result = $db->loadResult();
				break;

			case 'module':
				$db->setQuery("SELECT id FROM #__modules WHERE module = '$id' and client_id = '$client'");
				$result = $db->loadResult();
				break;

			case 'language':
				// A language is a complex beast
				// its actually a path!
				$clientInfo = &JApplicationHelper::getClientInfo($this->_state->get('filter.client'));
				$client = $clientInfo->name;
				$langBDir = JLanguage::getLanguagePath($clientInfo->path);
				$result = $langBDir . DS . $id;
				break;
		}

		// note: for templates, libraries and packages their unique name is their key
		// this means they come out the same way they came in
		return $result;
	}
}