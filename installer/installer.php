<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	Installer
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License, see LICENSE.php
  */

// No direct access
defined('JPATH_BASE') or die();

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.archive');
jimport('joomla.filesystem.path');
jimport('joomla.base.adapter');

/**
 * Joomla base installer class
 *
 * @package		Joomla.Framework
 * @subpackage	Installer
 * @since		1.5
 */
class JInstaller extends JAdapter
{
	/**
	 * Array of paths needed by the installer
	 * @var array
	 */
	protected $_paths = array();

	/**
	 * True if packakge is an upgrade
	 * @var boolean
	 */
	protected $_upgrade = null;

	/**
	 * The manifest trigger class
	 * @var object
	 */
	public $manifestClass = null;

	/**
	 * True if existing files can be overwritten
	 * @var boolean
	 */
	protected $_overwrite = false;

	/**
	 * Stack of installation steps
	 * 	- Used for installation rollback
	 * @var array
	 */
	protected $_stepStack = array();

	/**
	 * Extension Table Entry
	 * @var JTableExtension
	 */
	public $extension = null;

	/**
	 * The output from the install/uninstall scripts
	 * @var string
	 */
	public $message = null;

	/**
	 * The installation manifest XML object
	 * @var object
	 */
	public $manifest = null;

	protected $extension_message = null;
	

	/**
	 * Constructor
	 *
	 * @access protected
	 */
	public function __construct()
	{
		parent::__construct(dirname(__FILE__),'JInstaller');
	}

	/**
	 * Returns a reference to the global Installer object, only creating it
	 * if it doesn't already exist.
	 *
	 * @static
	 * @return	object	An installer object
	 * @since 1.5
	 */
	public static function &getInstance()
	{
		static $instance;

		if (!isset ($instance)) {
			$instance = new JInstaller();
		}
		return $instance;
	}

	/**
	 * Get the allow overwrite switch
	 *
	 * @access	public
	 * @return	boolean	Allow overwrite switch
	 * @since	1.5
	 */
	public function getOverwrite()
	{
		return $this->_overwrite;
	}

	/**
	 * Set the allow overwrite switch
	 *
	 * @access	public
	 * @param	boolean	$state	Overwrite switch state
	 * @return	boolean	Previous value
	 * @since	1.5
	 */
	public function setOverwrite($state=false)
	{
		$tmp = $this->_overwrite;
		if ($state) {
			$this->_overwrite = true;
		} else {
			$this->_overwrite = false;
		}
		return $tmp;
	}

	/**
	 * Get the allow overwrite switch
	 *
	 * @access	public
	 * @return	boolean	Allow overwrite switch
	 * @since	1.5
	 */
	function getUpgrade()
	{
		return $this->_upgrade;
	}

	/**
	 * Set the allow overwrite switch
	 *
	 * @access	public
	 * @param	boolean	$state	Overwrite switch state
	 * @return	boolean	Previous value
	 * @since	1.5
	 */
	function setUpgrade($state=false)
	{
		$tmp = $this->_upgrade;
		if ($state) {
			$this->_upgrade = true;
		} else {
			$this->_upgrade = false;
		}
		return $tmp;
	}

	/**
	 * Get the installation manifest object
	 *
	 * @access	public
	 * @return	object	Manifest object
	 * @since	1.5
	 */
	public function &getManifest()
	{
		if (!is_object($this->manifest)) {
			$this->findManifest();
		}
		return $this->manifest;
	}

	/**
	 * Get an installer path by name
	 *
	 * @access	public
	 * @param	string	$name		Path name
	 * @param	string	$default	Default value
	 * @return	string	Path
	 * @since	1.5
	 */
	public function getPath($name, $default=null)
	{
		return (!empty($this->_paths[$name])) ? $this->_paths[$name] : $default;
	}

	/**
	 * Sets an installer path by name
	 *
	 * @access	public
	 * @param	string	$name	Path name
	 * @param	string	$value	Path
	 * @return	void
	 * @since	1.5
	 */
	public function setPath($name, $value)
	{
		$this->_paths[$name] = $value;
	}

	/**
	 * Pushes a step onto the installer stack for rolling back steps
	 *
	 * @access	public
	 * @param	array	$step	Installer step
	 * @return	void
	 * @since	1.5
	 */
	public function pushStep($step)
	{
		$this->_stepStack[] = $step;
	}

	/**
	 * Installation abort method
	 *
	 * @access	public
	 * @param	string	$msg	Abort message from the installer
	 * @param	string	$type	Package type if defined
	 * @return	boolean	True if successful
	 * @since	1.5
	 */
	public function abort($msg=null, $type=null)
	{
		// Initialize variables
		$retval = true;
		$step = array_pop($this->_stepStack);

		// Raise abort warning
		if ($msg) {
			JError::raiseWarning(100, $msg);
		}

		while ($step != null)
		{
			switch ($step['type'])
			{
				case 'file' :
					// remove the file
					$stepval = JFile::delete($step['path']);
					break;

				case 'folder' :
					// remove the folder
					$stepval = JFolder::delete($step['path']);
					break;

				case 'query' :
					// placeholder in case this is necessary in the future
					break;

				case 'extension' :
					// Get database connector object
					$db =& $this->getDBO();

					// Remove the entry from the #__extensions table
					$query = 'DELETE FROM `#__extensions` WHERE extension_id = '.(int)$step['id'];
					$db->setQuery($query);
					$stepval = $db->Query();
					break;

				default :
					if ($type && is_object($this->_adapters[$type])) {
						// Build the name of the custom rollback method for the type
						$method = '_rollback_'.$step['type'];
						// Custom rollback method handler
						if (method_exists($this->_adapters[$type], $method)) {
							$stepval = $this->_adapters[$type]->$method($step);
						}
					} else {
						$stepval = false; // set it to false
					}
					break;
			}

			// Only set the return value if it is false
			if ($stepval === false) {
				$retval = false;
			}

			// Get the next step and continue
			$step = array_pop($this->_stepStack);
		}

		return $retval;
	}

	// -----------------------
	// Adapter functions
	// -----------------------

	/**
	 * Package installation method
	 *
	 * @access	public
	 * @param	string	$path	Path to package source folder
	 * @return	boolean	True if successful
	 * @since	1.5
	 */
	public function install($path=null)
	{
		if ($path && JFolder::exists($path)) {
			$this->setPath('source', $path);
		} else {
			$this->abort(JText::_('Install path does not exist'));
			return false;
		}

		if (!$this->setupInstall()) {
			$this->abort(JText::_('Unable to detect manifest file'));
			return false;
		}

		$root		=& $this->manifest->document;
		$type = $root->attributes('type');


		$version        = $root->attributes('version');
                $rootName       = $root->name();
                $config         = &JFactory::getConfig();
		if ((version_compare($version, '1.5', '<') || $rootName == 'mosinstall' || $type == 'mambot')) {
			$this->abort(JText::_('NOTCOMPATIBLE'));
			return false;
		}

		$system_version = new JVersion();
		if ((version_compare($version, $system_version->RELEASE, '<')) && !class_exists('JLegacy')) {
			$this->abort(JText::_('MUSTENABLELEGACY'));
			return false;
		}


		if (is_object($this->_adapters[$type])) {
			// Add the languages from the package itself
			$lang =& JFactory::getLanguage();
			$lang->load('joomla',$path);

			// Fire the onBeforeExtensionInstall event.
			JPluginHelper::importPlugin( 'installer' );
			$dispatcher =& JDispatcher::getInstance();
			$dispatcher->trigger( 'onBeforeExtensionInstall', array( 'method'=>'install', 'type'=>$type, 'manifest'=>$root, 'extension'=>0 ) );

			// Run the install
			$result = $this->_adapters[$type]->install();
			// Fire the onAfterExtensionInstall
			$dispatcher->trigger( 'onAfterExtensionInstall', array( 'installer'=>clone($this), 'eid'=> $result ) );
			if($result !== false) return true; else return false;
		}
		return false;
	}

	/*
	 * Discovered package installation method
	 *
	 * @access	public
	 * @param	string	$path	Path to package source folder
	 * @return	boolean	True if successful
	 * @since	1.5
	 */
	function discover_install($eid=null)
	{
		if($eid) {
			$this->extension =& JTable::getInstance('extension');
			if(!$this->extension->load($eid)) {
				$this->abort(JText::_('Failed to load extension details'));
				return false;
			}
			if($this->extension->state != -1) {
				$this->abort(JText::_('Extension is already installed'));
				return false;
			}

			// Lazy load the adapter
			if (!isset($this->_adapters[$this->extension->type]) || !is_object($this->_adapters[$this->extension->type])) {
				if (!$this->setAdapter($this->extension->type)) {
					return false;
				}
			}

			if (is_object($this->_adapters[$this->extension->type])) {
				if(method_exists($this->_adapters[$this->extension->type], 'discover_install')) {
					// Fire the onBeforeExtensionInstall event.
	                JPluginHelper::importPlugin( 'installer' );
	                $dispatcher =& JDispatcher::getInstance();
	                $dispatcher->trigger( 'onBeforeExtensionInstall', array( 'method'=>'discover_install', 'type'=>$this->extension->type, 'manifest'=>null, 'extension'=>$this->extension ) );
					// Run the install
					$result = $this->_adapters[$this->extension->type]->discover_install();
					// Fire the onAfterExtensionInstall
					$dispatcher->trigger( 'onAfterExtensionInstall', array( 'installer'=>clone($this), 'eid'=> $result ) );
					if($result !== false) return true; else return false;
				} else {
					$this->abort(JText::_('Method not supported for this extension type'));
					return false;
				}
			}
			return false;
		} else {
			$this->abort(JText::_('Extension is not a valid'));
			return false;
		}
	}

	/**
	 * Extension discover method
	 * Asks each adapter to find extensions
	 *
	 * @access public
	 * @return Array JExtension
	 */
	function discover() {
		$this->loadAllAdapters();
		$results = Array();
		foreach($this->_adapters as $adapter) {
			// Joomla! 1.5 installation adapter legacy support
			if(method_exists($adapter,'discover')) {
				$tmp = $adapter->discover();
				// if its an array and has entries
				if(is_array($tmp) && count($tmp)) {
					// merge it into the system
					$results = array_merge($results, $tmp);
				}
			}
		}
		return $results;
	}

	/**
	 * Package update method
	 *
	 * @access	public
	 * @param	string	$path	Path to package source folder
	 * @return	boolean	True if successful
	 * @since	1.5
	 */
	public function update($path=null)
	{
		if ($path && JFolder::exists($path)) {
			$this->setPath('source', $path);
		} else {
			$this->abort(JText::_('Update path does not exist'));
		}

		if (!$this->setupInstall()) {
			return $this->abort(JText::_('Unable to detect manifest file'));
		}

		$root		=& $this->manifest->document;
		$type = $root->attributes('type');

		$version        = $root->attributes('version');
                $rootName       = $root->name();
                $config         = &JFactory::getConfig();
		if ((version_compare($version, '1.5', '<') || $rootName == 'mosinstall' || $type == 'mambot')) {
			$this->abort(JText::_('NOTCOMPATIBLE'));
			return false;
		}

		$system_version = new JVersion();
		if ((version_compare($version, $system_version->RELEASE, '<')) && !class_exists('JLegacy')) {
			$this->abort(JText::_('MUSTENABLELEGACY'));
			return false;
		}

		if (is_object($this->_adapters[$type])) {
			// Add the languages from the package itself
			$lang =& JFactory::getLanguage();
			$lang->load('joomla',$path);
			// Fire the onBeforeExtensionUpdate event.
			JPluginHelper::importPlugin( 'installer' );
			$dispatcher =& JDispatcher::getInstance();
			$dispatcher->trigger( 'onBeforeExtensionUpdate', array( 'type'=>$type, 'manifest'=>$root ) );
			// Run the update
			$result = $this->_adapters[$type]->update();
			// Fire the onAfterExtensionUpdate
			$dispatcher->trigger( 'onAfterExtensionUpdate', array( 'installer'=>clone($this), 'eid'=> $result ) );
			if($result !== false) return true; else return false;
		}
		return false;
	}

	/**
	 * Package uninstallation method
	 *
	 * @access	public
	 * @param	string	$type	Package type
	 * @param	mixed	$identifier	Package identifier for adapter
	 * @param	int		$cid	Application ID; deprecated in 1.6
	 * @return	boolean	True if successful
	 * @since	1.5
	 */
	public function uninstall($type, $identifier, $cid=0)
	{
		if (!isset($this->_adapters[$type]) || !is_object($this->_adapters[$type])) {
			if (!$this->setAdapter($type)) {
				return false; // we failed to get the right adapter
			}
		}
		if (is_object($this->_adapters[$type])) {
			// We don't load languages here, we get the extension adapter to work it out
			// Fire the onBeforeExtensionUninstall event.
            JPluginHelper::importPlugin( 'installer' );
            $dispatcher =& JDispatcher::getInstance();
            $dispatcher->trigger( 'onBeforeExtensionUninstall', array( 'eid' => $identifier ) );
			// Run the uninstall
			$result = $this->_adapters[$type]->uninstall($identifier);
			// Fire the onAfterExtensionInstall
			$dispatcher->trigger( 'onAfterExtensionUninstall', array( 'installer'=>clone($this), 'eid'=> $identifier, 'result' => $result ) );
			return $result;
		}
		return false;
	}

	function refreshManifestCache($eid) {
		if($eid) {
			$this->extension =& JTable::getInstance('extension');
			if(!$this->extension->load($eid)) {
				$this->abort(JText::_('Failed to load extension details'));
				return false;
			}
			if($this->extension->state == -1) {
				$this->abort(JText::_('Refresh Manifest Cache failed').': '.JText::_('Extension is not currently installed'));
				return false;
			}

			// Lazy load the adapter
			if (!isset($this->_adapters[$this->extension->type]) || !is_object($this->_adapters[$this->extension->type])) {
				if (!$this->setAdapter($this->extension->type)) {
					return false;
				}
			}


			if (is_object($this->_adapters[$this->extension->type])) {
				if(method_exists($this->_adapters[$this->extension->type], 'refreshManifestCache')) {
					$result = $this->_adapters[$this->extension->type]->refreshManifestCache();
					if($result !== false) return true; else return false;
				} else {
					$this->abort(JText::_('Method not supported for this extension type').': '. $this->extension->type);
					return false;
				}
			}
			return false;
		} else {
			$this->abort(JText::_('Refresh Manifest Cache failed').': '.JText::_('Extension not valid'));
			return false;
		}
	}

	// -----------------------
	// Utility functions
	// -----------------------

	/**
	 * Prepare for installation: this method sets the installation directory, finds
	 * and checks the installation file and verifies the installation type
	 *
	 * @access public
	 * @return boolean True on success
	 * @since 1.0
	 */
	public function setupInstall()
	{
		// We need to find the installation manifest file
		if (!$this->findManifest()) {
			return false;
		}

		// Load the adapter(s) for the install manifest
		$root =& $this->manifest->document;
		$type = $root->attributes('type');

		// Lazy load the adapter
		if (!isset($this->_adapters[$type]) || !is_object($this->_adapters[$type])) {
			if (!$this->setAdapter($type)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Backward compatible Method to parse through a queries element of the
	 * installation manifest file and take appropriate action.
	 *
	 * @access	public
	 * @param	object	$element 	The xml node to process
	 * @return	mixed	Number of queries processed or False on error
	 * @since	1.5
	 */
	public function parseQueries($element)
	{
		// Get the database connector object
		$db = & $this->_db;

		if (!($element INSTANCEOF JSimpleXMLElement) || !count($element->children())) {
			// Either the tag does not exist or has no children therefore we return zero files processed.
			return 0;
		}

		// Get the array of query nodes to process
		$queries = $element->children();
		if (count($queries) == 0) {
			// No queries to process
			return 0;
		}

		// Process each query in the $queries array (children of $tagName).
		foreach ($queries as $query)
		{
			$db->setQuery($query->data());
			try {
				$db->query();
			} catch(JException $e) {
				JError::raiseWarning(1, 'JInstaller::install: '.JText::_('SQL Error')." ".$db->stderr(true));
				return false;
			}
		}
		return (int) count($queries);
	}

	/**
	 * Method to extract the name of a discreet installation sql file from the installation manifest file.
	 *
	 * @access	public
	 * @param	object	$element 	The xml node to process
	 * @param	string	$version	The database connector to use
	 * @return	mixed	Number of queries processed or False on error
	 * @since	1.5
	 */
	public function parseSQLFiles($element)
	{
		// Initialize variables
		$queries = array();
		$db = & $this->_db;
		$dbDriver = strtolower($db->get('name'));
		if ($dbDriver == 'mysqli') {
			$dbDriver = 'mysql';
		}
		$dbCharset = ($db->hasUTF()) ? 'utf8' : '';

		if (!$element INSTANCEOF JSimpleXMLElement) {
			// The tag does not exist.
			return 0;
		}

		// Get the array of file nodes to process
		$files = $element->children();
		if (count($files) == 0) {
			// No files to process
			return 0;
		}

		// Get the name of the sql file to process
		$sqlfile = '';
		foreach ($files as $file)
		{
			$fCharset = (strtolower($file->attributes('charset')) == 'utf8') ? 'utf8' : '';
			$fDriver  = strtolower($file->attributes('driver'));
			if ($fDriver == 'mysqli') {
				$fDriver = 'mysql';
			}

			if( $fCharset == $dbCharset && $fDriver == $dbDriver) {
				$sqlfile = $this->getPath('extension_root').DS.$file->data();
				// Check that sql files exists before reading. Otherwise raise error for rollback
				if ( !file_exists( $sqlfile ) ) {
					JError::raiseWarning(1,'JInstaller::installer: '. JText::_('SQL File not found').' '. $sqlfile);
					return false;
				}
				$buffer = file_get_contents($sqlfile);

				// Graceful exit and rollback if read not successful
				if ( $buffer === false ) {
					JError::raiseWarning(1, 'JInstaller::installer: '. JText::_('SQL File Buffer Read Error'));
					return false;
				}

				// Create an array of queries from the sql file
				jimport('joomla.installer.helper');
				$queries = JInstallerHelper::splitSql($buffer);

				if (count($queries) == 0) {
					// No queries to process
					return 0;
				}

				// Process each query in the $queries array (split out of sql file).
				foreach ($queries as $query)
				{
					$query = trim($query);
					if ($query != '' && $query{0} != '#') {
						$db->setQuery($query);
						try {
							$db->query();
						} catch(JException $e) {
							JError::raiseWarning(1, 'JInstaller::install: '.JText::_('SQL Error')." ".$db->stderr(true));
							return false;
						}
					}
				}
			}
		}

		return (int) count($queries);
	}

	/**
	 * Method to parse through a files element of the installation manifest and take appropriate
	 * action.
	 *
	 * @access	public
	 * @param	object	$element 	The xml node to process
	 * @param	int		$cid		Application ID of application to install to
	 * @param 	Array	List of old files (JSimpleXMLElement's)
	 * @param	Array	List of old MD5 sums (indexed by filename with value as MD5)
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	public function parseFiles($element, $cid=0, $oldFiles=null, $oldMD5=null)
	{
		// Initialize variables
		$copyfiles = array ();

		// Get the client info
		jimport('joomla.application.helper');
		$client =& JApplicationHelper::getClientInfo($cid);

		// Get the array of file nodes to process; we checked this had children above
		if (!is_a($element, 'JSimpleXMLElement') || !count($files = $element->children())) {
			// Either the tag does not exist or has no children (hence no files to process) therefore we return zero files processed.
			return 0;
		}

		/*
		 * Here we set the folder we are going to remove the files from.
		 */
		if ($client) {
			$pathname = 'extension_'.$client->name;
			$destination = $this->getPath($pathname);
		} else {
			$pathname = 'extension_root';
			$destination = $this->getPath($pathname);
		}

		/*
		 * Here we set the folder we are going to copy the files from.
		 *
		 * Does the element have a folder attribute?
		 *
		 * If so this indicates that the files are in a subdirectory of the source
		 * folder and we should append the folder attribute to the source path when
		 * copying files.
		 */
		$folder = $element->attributes('folder');
		if ($folder && file_exists($this->getPath('source').DS.$folder)) {
			$source = $this->getPath('source').DS.$folder;
		} else {
			$source = $this->getPath('source');
		}

		// Work out what files have been deleted
		if($oldFiles && is_a($oldFiles, 'JSimpleXMLElement')) {
			$oldEntries = $oldFiles->children();
			if(count($oldEntries)) {
				$deletions = $this->findDeletedFiles($oldEntries, $files);
				foreach($deletions['folders'] as $deleted_folder) {
					JFolder::delete($destination.DS.$deleted_folder);
				}
				foreach($deletions['files'] as $deleted_file) {
					JFile::delete($destination.DS.$deleted_file);
				}
			}
		}

		// Copy the MD5SUMS file if it exists
		if(file_exists($source.DS.'MD5SUMS')) {
			$path['src'] = $source.DS.'MD5SUMS';
			$path['dest'] = $destination.DS.'MD5SUMS';
			$path['type'] = 'file';
			$copyfiles[] = $path;
		}

		// Process each file in the $files array (children of $tagName).
		foreach ($files as $file)
		{
			$path['src']	= $source.DS.$file->data();
			$path['dest']	= $destination.DS.$file->data();

			// Is this path a file or folder?
			$path['type']	= ( $file->name() == 'folder') ? 'folder' : 'file';

			/*
			 * Before we can add a file to the copyfiles array we need to ensure
			 * that the folder we are copying our file to exits and if it doesn't,
			 * we need to create it.
			 */
			if (basename($path['dest']) != $path['dest']) {
				$newdir = dirname($path['dest']);

				if (!JFolder::create($newdir)) {
					JError::raiseWarning(1, 'JInstaller::install: '.JText::_('Failed to create directory').' "'.$newdir.'"');
					return false;
				}
			}

			// Add the file to the copyfiles array
			$copyfiles[] = $path;
		}

		return $this->copyFiles($copyfiles);
	}

	/**
	 * Method to parse through a languages element of the installation manifest and take appropriate
	 * action.
	 *
	 * @access	public
	 * @param	object	$element 	The xml node to process
	 * @param	int		$cid		Application ID of application to install to
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	public function parseLanguages($element, $cid=0)
	{
		// Initialize variables
		$copyfiles = array ();

		// Get the client info
		jimport('joomla.application.helper');
		$client =& JApplicationHelper::getClientInfo($cid);

		if (!($element INSTANCEOF JSimpleXMLElement) || !count($element->children())) {
			// Either the tag does not exist or has no children therefore we return zero files processed.
			return 0;
		}

		// Get the array of file nodes to process
		$files = $element->children();
		if (count($files) == 0) {
			// No files to process
			return 0;
		}

		/*
		 * Here we set the folder we are going to copy the files to.
		 *
		 * 'languages' Files are copied to JPATH_BASE/language/ folder
		 */
		$destination = $client->path.DS.'language';

		/*
		 * Here we set the folder we are going to copy the files from.
		 *
		 * Does the element have a folder attribute?
		 *
		 * If so this indicates that the files are in a subdirectory of the source
		 * folder and we should append the folder attribute to the source path when
		 * copying files.
		 */
		$folder = $element->attributes('folder');
		if ($folder && file_exists($this->getPath('source').DS.$folder)) {
			$source = $this->getPath('source').DS.$folder;
		} else {
			$source = $this->getPath('source');
		}

		// Process each file in the $files array (children of $tagName).
		foreach ($files as $file)
		{
			/*
			 * Language files go in a subfolder based on the language code, ie.
			 *
			 * 		<language tag="en-US">en-US.mycomponent.ini</language>
			 *
			 * would go in the en-US subdirectory of the language folder.
			 *
			 * We will only install language files where a core language pack
			 * already exists.
			 */
			if ($file->attributes('tag') != '') {
				$path['src']	= $source.DS.$file->data();
				if($file->attributes('client') != '') {
					// override the client
					$langclient =& JApplicationHelper::getClientInfo($file->attributes('client'), true);
					$path['dest'] = $langclient->path.DS.'language'.DS.$file->attributes('tag').DS.basename($file->data());
				} else {
					// use the default client
					$path['dest']	= $destination.DS.$file->attributes('tag').DS.basename($file->data());
				}

				// If the language folder is not present, then the core pack hasn't been installed... ignore
				if (!JFolder::exists(dirname($path['dest']))) {
					continue;
				}
			} else {
				$path['src']	= $source.DS.$file->data();
				$path['dest']	= $destination.DS.$file->data();
			}

			/*
			 * Before we can add a file to the copyfiles array we need to ensure
			 * that the folder we are copying our file to exits and if it doesn't,
			 * we need to create it.
			 */
			if (basename($path['dest']) != $path['dest']) {
				$newdir = dirname($path['dest']);

				if (!JFolder::create($newdir)) {
					JError::raiseWarning(1, 'JInstaller::install: '.JText::_('Failed to create directory').' "'.$newdir.'"');
					return false;
				}
			}

			// Add the file to the copyfiles array
			$copyfiles[] = $path;
		}

		return $this->copyFiles($copyfiles);
	}

	/**
	 * Method to parse through a media element of the installation manifest and take appropriate
	 * action.
	 *
	 * @access	public
	 * @param	object	$element 	The xml node to process
	 * @param	int		$cid		Application ID of application to install to
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	public function parseMedia($element, $cid=0)
	{
		// Initialize variables
		$copyfiles = array ();

		// Get the client info
		jimport('joomla.application.helper');
		$client =& JApplicationHelper::getClientInfo($cid);

		if (!($element INSTANCEOF JSimpleXMLElement) || !count($element->children())) {
			// Either the tag does not exist or has no children therefore we return zero files processed.
			return 0;
		}

		// Get the array of file nodes to process
		$files = $element->children();
		if (count($files) == 0) {
			// No files to process
			return 0;
		}

		/*
		 * Here we set the folder we are going to copy the files to.
		 * 	Default 'media' Files are copied to the JPATH_BASE/images folder
		 */
		$folder = ($element->attributes('destination')) ? DS.$element->attributes('destination') : null;
		$destination = JPath::clean(JPATH_ROOT.DS.'media'.$folder);

		/*
		 * Here we set the folder we are going to copy the files from.
		 *
		 * Does the element have a folder attribute?
		 *
		 * If so this indicates that the files are in a subdirectory of the source
		 * folder and we should append the folder attribute to the source path when
		 * copying files.
		 */
		$folder = $element->attributes('folder');
		if ($folder && file_exists($this->getPath('source').DS.$folder)) {
			$source = $this->getPath('source').DS.$folder;
		} else {
			$source = $this->getPath('source');
		}

		// Process each file in the $files array (children of $tagName).
		foreach ($files as $file)
		{
			$path['src']	= $source.DS.$file->data();
			$path['dest']	= $destination.DS.$file->data();

			/*
			 * Before we can add a file to the copyfiles array we need to ensure
			 * that the folder we are copying our file to exits and if it doesn't,
			 * we need to create it.
			 */
			if (basename($path['dest']) != $path['dest']) {
				$newdir = dirname($path['dest']);

				if (!JFolder::create($newdir)) {
					JError::raiseWarning(1, 'JInstaller::install: '.JText::_('Failed to create directory').' "'.$newdir.'"');
					return false;
				}
			}

			// Add the file to the copyfiles array
			$copyfiles[] = $path;
		}

		return $this->copyFiles($copyfiles);
	}

	/**
	 * Method to parse the parameters of an extension, build the INI
	 * string for it's default parameters, and return the INI string.
	 *
	 * @access	public
	 * @return	string	INI string of parameter values
	 * @since	1.5
	 */
	public function getParams()
	{
		// Get the manifest document root element
		$root = & $this->manifest->document;

		// Get the element of the tag names
		$element =& $root->getElementByPath('params');
		if (!($element INSTANCEOF JSimpleXMLElement) || !count($element->children())) {
			// Either the tag does not exist or has no children therefore we return zero files processed.
			return null;
		}

		// Get the array of parameter nodes to process
		$params = $element->children();
		if (count($params) == 0) {
			// No params to process
			return null;
		}

		// Process each parameter in the $params array.
		$ini = null;
		foreach ($params as $param) {
			if (!$name = $param->attributes('name')) {
				continue;
			}

			if (!$value = $param->attributes('default')) {
				continue;
			}

			$ini .= $name."=".$value."\n";
		}
		return $ini;
	}

	/**
	 * Copy files from source directory to the target directory
	 *
	 * @access	public
	 * @param	array $files array with filenames
	 * @param	boolean $overwrite True if existing files can be replaced
	 * @return	boolean True on success
	 * @since	1.5
	 */
	public function copyFiles($files, $overwrite=null)
	{
		/*
		 * To allow for manual override on the overwriting flag, we check to see if
		 * the $overwrite flag was set and is a boolean value.  If not, use the object
		 * allowOverwrite flag.
		 */
		if (is_null($overwrite) || !is_bool($overwrite)) {
			$overwrite = $this->_overwrite;
		}

		/*
		 * $files must be an array of filenames.  Verify that it is an array with
		 * at least one file to copy.
		 */
		if (is_array($files) && count($files) > 0)
		{
			foreach ($files as $file)
			{
				// Get the source and destination paths
				$filesource	= JPath::clean($file['src']);
				$filedest	= JPath::clean($file['dest']);
				$filetype	= array_key_exists('type', $file) ? $file['type'] : 'file';

				if (!file_exists($filesource)) {
					/*
					 * The source file does not exist.  Nothing to copy so set an error
					 * and return false.
					 */
					JError::raiseWarning(1, 'JInstaller::install: '.JText::sprintf('File does not exist', $filesource));
					return false;
				} elseif (file_exists($filedest) && !$overwrite) {

						/*
						 * It's okay if the manifest already exists
						 */
						if ($this->getPath( 'manifest' ) == $filesource) {
							continue;
						}

						/*
						 * The destination file already exists and the overwrite flag is false.
						 * Set an error and return false.
						 */
						JError::raiseWarning(1, 'JInstaller::install: '.JText::sprintf('WARNSAME', $filedest));
						return false;
				} else {
					// Copy the folder or file to the new location.
					if ( $filetype == 'folder') {

						if (!(JFolder::copy($filesource, $filedest, null, $overwrite,1))) {
							JError::raiseWarning(1, 'JInstaller::install: '.JText::sprintf('Failed to copy folder to', $filesource, $filedest));
							return false;
						}

						$step = array ('type' => 'folder', 'path' => $filedest);
					} else {

						if (!(JFile::copy($filesource, $filedest,null,1))) {
							JError::raiseWarning(1, 'JInstaller::install: '.JText::sprintf('Failed to copy file to', $filesource, $filedest));
// TODO: REMOVE ME!
/*							echo '<p>Copy failed for: '. $filesource .' to '. $filedest.'</p>';
							$tmpApp =& JFactory::getApplication();
							print_r($tmpApp->getMessageQueue());
							die('halting to preserve evidence');*/
							return false;
						}

						$step = array ('type' => 'file', 'path' => $filedest);
					}

					/*
					 * Since we copied a file/folder, we want to add it to the installation step stack so that
					 * in case we have to roll back the installation we can remove the files copied.
					 */
					$this->_stepStack[] = $step;
				}
			}
		} else {

			/*
			 * The $files variable was either not an array or an empty array
			 */
			return false;
		}
		return count($files);
	}

	/**
	 * Method to parse through a files element of the installation manifest and remove
	 * the files that were installed
	 *
	 * @access	public
	 * @param	object	$element 	The xml node to process
	 * @param	int		$cid		Application ID of application to remove from
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	public function removeFiles($element, $cid=0)
	{
		// Initialize variables
		$removefiles = array ();
		$retval = true;

		// Get the client info if we're using a specific client
		jimport('joomla.application.helper');
		if($cid > -1) {
			$client =& JApplicationHelper::getClientInfo($cid);	
		} else {
			$client = null;
		}
		

		if (!($element INSTANCEOF JSimpleXMLElement) || !count($element->children())) {
			// Either the tag does not exist or has no children therefore we return zero files processed.
			return true;
		}

		// Get the array of file nodes to process
		$files = $element->children();
		if (count($files) == 0) {
			// No files to process
			return true;
		}
		
		$folder = '';

		/*
		 * Here we set the folder we are going to remove the files from.  There are a few
		 * special cases that need to be considered for certain reserved tags.
		 */
		switch ($element->name())
		{
			case 'media':
				if ($element->attributes('destination')) {
					$folder = $element->attributes('destination');
				}
				$source = $client->path.DS.'media'.DS.$folder;
				break;

			case 'languages':
				if($client) {
					$source = $client->path.DS.'language';
				} else {
					$source = '';
				}
				break;

			default:
				if ($client) {
					$pathname = 'extension_'.$client->name;
					$source = $this->getPath($pathname);
				} else {
					$pathname = 'extension_root';
					$source = $this->getPath($pathname);
				}
				break;
		}

		// Process each file in the $files array (children of $tagName).
		foreach ($files as $file)
		{
			/*
			 * If the file is a language, we must handle it differently.  Language files
			 * go in a subdirectory based on the language code, ie.
			 *
			 * 		<language tag="en_US">en_US.mycomponent.ini</language>
			 *
			 * would go in the en_US subdirectory of the languages directory.
			 */
			if ($file->name() == 'language' && $file->attributes('tag') != '') {
				if($source) {
					$path = $source.DS.$file->attributes('tag').DS.basename($file->data());	
				} else {
					$target_client = JApplicationHelper::getClientInfo($file->attributes('client'), true);
					$path = $target_client->path.DS.'language'.DS.$file->attributes('tag').DS.basename($file->data()); 
				}
				
				// If the language folder is not present, then the core pack hasn't been installed... ignore
				if (!JFolder::exists(dirname($path))) {
					JError::raiseWarning(42,'Tried to delete non-existent file at ' . $path);
					continue;
				}
			} else {
				$path = $source.DS.$file->data();
			}

			/*
			 * Actually delete the files/folders
			 */
			if (is_dir($path)) {
				$val = JFolder::delete($path);
			} else {
				$val = JFile::delete($path);
			}

			if ($val === false) {
				JError::raiseWarning(43, 'Failed to delete '. $path);
				$retval = false;
			}
		}
		
		if(!empty($folder)) {
			$val = JFolder::delete($source);
		}

		return $retval;
	}

	/**
	 * Copies the installation manifest file to the extension folder in the given client
	 *
	 * @access	public
	 * @param	int		$cid	Where to copy the installfile [optional: defaults to 1 (admin)]
	 * @return	boolean	True on success, False on error
	 * @since	1.5
	 */
	public function copyManifest($cid=1)
	{
		// Get the client info
		jimport('joomla.application.helper');
		$client =& JApplicationHelper::getClientInfo($cid);

		$path['src'] = $this->getPath('manifest');

		if ($client) {
			$pathname = 'extension_'.$client->name;
			$path['dest']  = $this->getPath($pathname).DS.basename($this->getPath('manifest'));
		} else {
			$pathname = 'extension_root';
			$path['dest']  = $this->getPath($pathname).DS.basename($this->getPath('manifest'));
		}
		return $this->copyFiles(array ($path), true);
	}

	/**
	 * Tries to find the package manifest file
	 *
	 * @access private
	 * @return boolean True on success, False on error
	 * @since 1.0
	 */
	public function findManifest()
	{
		// Get an array of all the xml files from the installation directory
		$xmlfiles = JFolder::files($this->getPath('source'), '.xml$', 1, true);
		// If at least one xml file exists
		if (count($xmlfiles) > 0) {
			foreach ($xmlfiles as $file)
			{
				// Is it a valid joomla installation manifest file?
				$manifest = $this->isManifest($file);
				if (!is_null($manifest)) {

					// If the root method attribute is set to upgrade, allow file overwrite
					$root =& $manifest->document;
					if ($root->attributes('method') == 'upgrade') {
						$this->_upgrade = true;
						$this->_overwrite = true;
					}

					// If the overwrite option is set, allow file overwriting
					if($root->attributes('overwrite') == 'true') {
						$this->_overwrite = true;
					}

					// Set the manifest object and path
					$this->manifest =& $manifest;
					$this->setPath('manifest', $file);

					// Set the installation source path to that of the manifest file
					$this->setPath('source', dirname($file));
					return true;
				}
			}

			// None of the xml files found were valid install files
			JError::raiseWarning(1, 'JInstaller::install: '.JText::_('ERRORNOTFINDJOOMLAXMLSETUPFILE'));
			return false;
		} else {
			// No xml files were found in the install folder
			JError::raiseWarning(1, 'JInstaller::install: '.JText::_('ERRORXMLSETUP'));
			return false;
		}
	}

	/**
	 * Is the xml file a valid Joomla installation manifest file
	 *
	 * @access	private
	 * @param	string	$file	An xmlfile path to check
	 * @return	mixed	A JSimpleXML document, or null if the file failed to parse
	 * @since	1.5
	 */
	public function &isManifest($file)
	{
		// Initialize variables
		$null	= null;
		$xml	=& JFactory::getXMLParser('Simple');

		// If we cannot load the xml file return null
		if (!$xml->loadFile($file)) {
			// Free up xml parser memory and return null
			unset ($xml);
			return $null;
		}

		/*
		 * Check for a valid XML root tag.
		 * @todo: Remove backwards compatability in a future version
		 * Should be 'extension', but for backward compatability we will accept 'extension' or 'install'.
		 */
		$root =& $xml->document;
		// 1.5 uses 'install'
		// 1.6 uses 'extension'
		if (!is_object($root) || ($root->name() != 'install' && $root->name() != 'extension')) {
			// Free up xml parser memory and return null
			unset ($xml);
			return $null;
		}

		// Valid manifest file return the object
		return $xml;
	}

	/**
	 * Generates a manifest cache
	 * @return string serialised manifest data
	 */
	function generateManifestCache() {
		return serialize(JApplicationHelper::parseXMLInstallFile($this->getPath('manifest')));
	}


	/**
	 * Cleans up discovered extensions if they're being installed somehow else
	 */
	function cleanDiscoveredExtension($type, $element, $folder='', $client=0) {
		$dbo =& JFactory::getDBO();
		$dbo->setQuery('DELETE FROM #__extensions WHERE type = '. $dbo->Quote($type).' AND element = '. $dbo->Quote($element) .' AND folder = '. $dbo->Quote($folder). ' AND client_id = '. intval($client).' AND state = -1');
		return $dbo->Query();
	}

	/**
	 * Compares two "files" entries to find deleted files/folders
	 * @param array An array of JSimpleXML objects that are the old files
	 * @param array An array of JSimpleXML objects that are the new files
	 * @return array An array with the delete files and folders in findDeletedFiles[files] and findDeletedFiles[folders] resepctively
	 */
	function findDeletedFiles($old_files, $new_files) {
		// The magic find deleted files function!
		$files = Array(); // the files that are new
		$folders = Array(); // the folders that are new
		$containers = Array(); // the folders of the files that are new
		$files_deleted = Array(); // a list of files to delete
		$folders_deleted = Array(); // a list of folders to delete
		foreach($new_files as $file) {
			switch($file->name()) {
				case 'folder':
					$folders[] = $file->data(); // add any folders to the list
					break;
				case 'file':
				default:
					$files[] = $file->data(); // add any files to the list
					// now handle the folder part of the file to ensure we get any containers
					$container_parts = explode('/',dirname($file->data())); // break up the parts of the directory
					$container = ''; // make sure this is clean and empty
					foreach($container_parts as $part) { // iterate through each part
						if(!empty($container)) $container .= '/'; // add a slash if its not empty
						$container .= $part; // append the folder part
						if(!in_array($container, $containers)) $containers[] = $container; // add the container if it doesn't already exist
					}
					break;
			}
		}

		foreach($old_files as $file) {
			switch($file->name()) {
				case 'folder':
					if(!in_array($file->data(), $folders)) { // look if the folder exists in the new list
						if(!in_array($file->data(), $containers)) { // check if the folder exists as a container in the new list
							$folders_deleted[] = $file->data(); // if its not in the new list or a container then delete it
						}
					}
					break;
				case 'file':
				default:
					if(!in_array($file->data(), $files)) { // look if the file exists in the new list
						if(!in_array(dirname($file->data()), $folders)) { // look if the file is now potentially in a folder
							$files_deleted[] = $file->data(); // not in a folder, doesn't exist, wipe it out!
						}
					}
					break;
			}
		}
		return Array('files'=>$files_deleted, 'folders'=>$folders_deleted);
	}

	/**
	 * Loads an MD5SUMS file into an associative array
	 * @param string Filename to load
	 * @return Array Associative array with filenames as the index and the MD5 as the value
	 */
	function loadMD5Sum($filename) {
		if(!file_exists($filename)) return false; // bail if the file doesn't exist
		$data = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$retval = Array();
		foreach($data as $row) {
			$results = explode('  ', $row); // split up the data
			$results[1] = str_replace('./','', $results[1]); // cull any potential prefix
			$retval[$results[1]] = $results[0]; // throw into the array
		}
		return $retval;
	}
}
