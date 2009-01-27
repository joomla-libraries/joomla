<?php
/**
 * @version		$Id:plugin.php 6961 2007-03-15 16:06:53Z tcp $
 * @package		JLibMan
 * @subpackage	Installer
 * @copyright 	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License, see LICENSE.php
  */

// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();

jimport('joomla.installer.librarymanifest');
jimport('joomla.base.adapterinstance');

/**
 * Library installer
 *
 * @package		Joomla.Framework
 * @subpackage	Installer
 * @since		1.6
 */
class JInstallerLibrary extends JAdapterInstance
{

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
		$element = str_replace('.xml','',basename($this->parent->getPath('manifest')));
		$this->set('name', $name);
		$this->set('element', $element);

		$db =& $this->parent->getDBO();
		$db->setQuery('SELECT extension_id FROM #__extensions WHERE type="library" AND element = "'. $element .'"');
		$result = $db->loadResult();
		if ($result) { // already installed, can we upgrade?
			if ($this->parent->getOverwrite() || $this->parent->getUpgrade()) {
				// we can upgrade, so uninstall the old one
				$installer = new JInstaller(); // we don't want to compromise this instance!
				$installer->uninstall('library', $result);
			} else {
				// abort the install, no upgrade possible
				$this->parent->abort(JText::_('Library').' '. JText::_('Install').': '.JText::_('Library already installed'));
				return false;
			}
		}


		// Get the component description
		$description = & $this->manifest->getElementByPath('description');
		if (is_a($description, 'JSimpleXMLElement')) {
			$this->parent->set('message', $description->data());
		} else {
			$this->parent->set('message', '');
		}

		// Set the installation path
		$element =& $this->manifest->getElementByPath('files');
		$group = $this->manifest->getElementByPath('libraryname');
		$group = $group->data();
		if (!empty($group)) {
			$this->parent->setPath('extension_root', JPATH_ROOT.DS.'libraries'.DS.implode(DS,explode('/',$group)));
		} else {
			$this->parent->abort(JText::_('Library').' '.JText::_('Install').': '.JText::_('No library file specified'));
			return false;
		}

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Filesystem Processing Section
		 * ---------------------------------------------------------------------------------------------
		 */

		// If the plugin directory does not exist, lets create it
		$created = false;
		if (!file_exists($this->parent->getPath('extension_root'))) {
			if (!$created = JFolder::create($this->parent->getPath('extension_root'))) {
				$this->parent->abort(JText::_('Library').' '.JText::_('Install').': '.JText::_('Failed to create directory').': "'.$this->parent->getPath('extension_root').'"');
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

		// Copy all necessary files
		if ($this->parent->parseFiles($element, -1) === false) {
			// Install failed, roll back changes
			$this->parent->abort();
			return false;
		}

		/**
		 * ---------------------------------------------------------------------------------------------
		 * Extension Registration
		 * ---------------------------------------------------------------------------------------------
		 */
		$row = & JTable::getInstance('extension');
		$row->name = $this->get('name');
		$row->type = 'library';
		$row->element = $this->get('element');
		$row->folder = ''; // There is no folder for modules
		$row->enabled = 1;
		$row->protected = 0;
		$row->access = 0;
		$row->client_id = 0;
		$row->params = $this->parent->getParams();
		$row->data = ''; // custom data
		$row->manifest_cache = $this->parent->generateManifestCache();
		if (!$row->store()) {
			// Install failed, roll back changes
			$this->parent->abort(JText::_('Library').' '.JText::_('Install').': '.$db->stderr(true));
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
		$manifest['dest'] = JPATH_MANIFESTS.DS.'libraries'.DS.basename($this->parent->getPath('manifest'));
		if (!$this->parent->copyFiles(array($manifest), true)) {
			// Install failed, rollback changes
			$this->parent->abort(JText::_('Library').' '.JText::_('Install').': '.JText::_('Could not copy setup file'));
			return false;
		}
		return true;
	}

	/**
	 * Custom update method
	 * @access public
	 * @return boolean True on success
	 * @since  1.5
	 */
	function update() {
		// since this is just files, an update removes old files
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
		$element = str_replace('.xml','',basename($this->parent->getPath('manifest')));
		$this->set('name', $name);
		$this->set('element', $element);
		$installer = new JInstaller(); // we don't want to compromise this instance!
		$db =& $this->parent->getDBO();
		$db->setQuery('SELECT extension_id FROM #__extensions WHERE type="library" AND element = "'. $element .'"');
		$result = $db->loadResult();
		if ($result) { // already installed, which would make sense
			$installer->uninstall('library', $result);
		}
		// now create the new files
		return $this->install();
	}

	/**
	 * Custom uninstall method
	 *
	 * @access	public
	 * @param	string	$id	The id of the library to uninstall
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function uninstall($id)
	{
		// Initialize variables
		$retval = true;

		// First order of business will be to load the module object table from the database.
		// This should give us the necessary information to proceed.
		$row = & JTable::getInstance('extension');
		if (!$row->load((int) $id) || !strlen($row->element)) {
			JError::raiseWarning(100, JText::_('ERRORUNKOWNEXTENSION'));
			return false;
		}

		// Is the library we are trying to uninstall a core one?
		// Because that is not a good idea...
		if ($row->protected) {
			JError::raiseWarning(100, JText::_('Library').' '.JText::_('Uninstall').': '.JText::sprintf('WARNCOREMODULE', $row->name)."<br />".JText::_('WARNCOREMODULE2'));
			return false;
		}

		$manifestFile = JPATH_MANIFESTS.DS.'libraries' . DS . $row->element .'.xml';

		// Because libraries may not have their own folders we cannot use the standard method of finding an installation manifest
		if (file_exists($manifestFile))
		{
			$manifest = new JLibraryManifest($manifestFile);
			// Set the plugin root path
			$this->parent->setPath('extension_root', JPATH_ROOT.DS.'libraries'.DS.$manifest->libraryname);

			$xml =& JFactory::getXMLParser('Simple');

			// If we cannot load the xml file return null
			if (!$xml->loadFile($manifestFile)) {
				JError::raiseWarning(100, JText::_('Library').' '.JText::_('Uninstall').': '.JText::_('Could not load manifest file'));
				return false;
			}

			/*
			 * Check for a valid XML root tag.
			 * @todo: Remove backwards compatability in a future version
			 * Should be 'extension', but for backward compatability we will accept 'install'.
			 */
			$root =& $xml->document;
			if ($root->name() != 'install' && $root->name() != 'extension') {
				JError::raiseWarning(100, JText::_('Library').' '.JText::_('Uninstall').': '.JText::_('Invalid manifest file'));
				return false;
			}

			$this->parent->removeFiles($root->getElementByPath('files'), -1);
			JFile::delete($manifestFile);

		} else {
			// remove this row entry since its invalid
			$row->delete($row->extension_id);
			unset($row);
			JError::raiseWarning(100, 'Library Uninstall: Manifest File invalid or not found');
			return false;
		}

		// TODO: Change this so it walked up the path backwards so we clobber multiple empties
		// If the folder is empty, let's delete it
		if (JFolder::exists($this->parent->getPath('extension_root'))) {
			if (is_dir($this->parent->getPath('extension_root'))) {
				$files = JFolder::files($this->parent->getPath('extension_root'));
				if (!count($files)) {
					JFolder::delete($this->parent->getPath('extension_root'));
				}
			}
		}

		$row->delete($row->extension_id);
		unset($row);

		return $retval;
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
		$file_list = JFolder::files(JPATH_MANIFESTS.DS.'libraries','\.xml$');
		foreach($file_list as $file) {
			$file = JFile::stripExt($file);
			$extension =& JTable::getInstance('extension');
			$extension->type = 'library';
			$extension->client_id = 0;
			$extension->element = $file;
			$extension->name = $file;
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
		/* Libraries are a strange beast, they actually references files
		 * There are two parts to a library which are disjunct in their locations
		 * 1) The manifest file (stored in /JPATH_MANIFESTS/libraries)
		 * 2) The actual files (stored in /JPATH_LIBRARIES/libraryname)
		 * Thus installation of a library is the process of dumping files
		 * in two different places. As such it is impossible to perform
		 * any operation beyond mere registration of a library under the presumption
		 * that the files exist in the appropriate location so that come uninstall
		 * time they can be adequately removed.
		 */

		$manifestPath = JPATH_MANIFESTS . DS . 'libraries' . DS . $this->parent->extension->element . '.xml';
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
			JError::raiseWarning(101, JText::_('Plugin').' '.JText::_('Discover Install').': '.JText::_('Failed to store extension details'));
			return false;
		}
	}

}

