<?php

/**
* @version $Id: joomla.factory.php 719 2005-10-28 14:44:21Z Jinx $
* @package Joomla
* @copyright Copyright (C) 2005 Open Source Matters. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// no direct access
defined( '_VALID_MOS' ) or die( 'Restricted access' );

/**
 * The Joomla! Factory class
 * @package Joomla
 * @since 1.1
 */
class JFactory {
	/**
	* Load language files
	* The function will load the common language file of the system and the
	* special files for the actual component.
	* The module related files will be loaded automatically
	*
	* @subpackage Language
	* @param string		actual component which files should be loaded
	* @param boolean	admin languages to be loaded?
	*/
	function &getLanguage( $option=null, $isAdmin=false ) {
		global $mosConfig_absolute_path, $mainframe;
		global $mosConfig_lang, $my;

		require_once $mosConfig_absolute_path .'/libraries/joomla/language.php';

		$mosConfig_admin_path = $mosConfig_absolute_path .'/administrator';
		$path = $mosConfig_absolute_path . '/language/';
		
		$lang = $mainframe->getUserState( 'lang' );
		
		if ($lang == '' && $my && isset( $my->params )) {

			// if admin && special lang?
			if( $mainframe && $mainframe->isAdmin() ) {
				$lang = $my->params->get( 'admin_language', $lang );
			}
		}
		
		// loads english language file by default
		if ($lang == '0' || $lang == '') {
			$lang = $mosConfig_lang;
		}

		// load the site language file (the old way - to be deprecated)
		$file = $path . $lang .'.php';
		if (file_exists( $file )) {
			require_once( $path . $lang .'.php' );
		} else {
			$file = $path .'english.php';
			if (file_exists( $file )) {
				require_once( $file );
			}
		}

		$_LANG = new JLanguage( $lang );
		$_LANG->loadAll( $option, 0 );
		if ($isAdmin) {
			$_LANG->loadAll( $option, 1 );
		}

		// make sure the locale setting is correct
		setlocale( LC_ALL, $_LANG->locale() );

		// In case of frontend modify the config value in order to keep backward compatiblitity
		if( $mainframe && !$mainframe->isAdmin() ) {
			$mosConfig_lang = $lang;
		}

		return $_LANG;
	}
	
	/**
	 * @param array An array of additional template files to load
	 * @param boolean True to use caching
	 */
	function &getPatTemplate( $files=null ) {
		global $mainframe;

		// For some reason on PHP4 the singleton does not clone deep enough
		// The Reader object is not behaving itself and causing problems
		$tmpl =& JFactory::_createPatTemplate();

		//set template cache prefix
		$prefix = '';
		if($mainframe->isAdmin()) {
			$prefix .= 'administrator__';
		}
		$prefix .= $GLOBALS['option'].'__';
		$tmpl->setTemplateCachePrefix($prefix);


		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				$tmpl->readTemplatesFromInput( $file );
			}
		}

		return $tmpl;
	}
	
	/**
	 * Creates an access control object
	 * @param object A Joomla! database object
	 * @return object
	 * $since 1.1
	 */
	function &getACL( ) {
		$acl =& JFactory::_createACL();
		return $acl;
	}
	
	/**
	 * @return object
	 * @since 1.1
	 */
	function &_createACL()	{
		global $mosConfig_absolute_path;

		require_once( $mosConfig_absolute_path . '/includes/gacl.class.php' );
		require_once( $mosConfig_absolute_path . '/includes/gacl_api.class.php' );

		$acl = new gacl_api();

		return $acl;
	}
	
	/**
	 * @return object
	 * @since 1.1
	 */
	function &_createPatTemplate() {
		global $_LANG, $mainframe;
		global $mosConfig_absolute_path, $mosConfig_live_site;

		$path = $mosConfig_absolute_path . '/libraries/pattemplate';

		require_once( $path .'/patTemplate.php' );
		$tmpl = new patTemplate;

		//TODO : add config var
		if ($GLOBALS['mosConfig_tmpl_caching']) {

			$info = array(
				'cacheFolder' 	=> $GLOBALS['mosConfig_cachepath'].'/pattemplate',
				'lifetime' 		=> 'auto',
				'prefix'		=> 'global__',
				'filemode' 		=> 0755
			);
		 	$tmpl->useTemplateCache( 'File', $info );
		}

		$tmpl->setNamespace( 'jos' );

		// load the wrapper and common templates
		$tmpl->setRoot( $path .'/tmpl' );
		$tmpl->readTemplatesFromInput( 'page.html' );
		$tmpl->applyInputFilter('ShortModifiers');

		$tmpl->addGlobalVar( 'option', 				$GLOBALS['option'] );
		$tmpl->addGlobalVar( 'self', 				$_SERVER['PHP_SELF'] );
		$tmpl->addGlobalVar( 'itemid', 				$GLOBALS['Itemid'] );
		$tmpl->addGlobalVar( 'siteurl', 			$mosConfig_live_site );
		$tmpl->addGlobalVar( 'adminurl', 			$mosConfig_live_site .'/administrator' );
		$tmpl->addGlobalVar( 'admintemplateurl', 	$mosConfig_live_site .'/administrator/templates/'. $mainframe->getTemplate() );
		$tmpl->addGlobalVar( 'sitename', 			$GLOBALS['mosConfig_sitename'] );

		$tmpl->addGlobalVar( 'page_encoding', 		$_LANG->iso() );
		$tmpl->addGlobalVar( 'version_copyright', 	$GLOBALS['_VERSION']->COPYRIGHT );
		$tmpl->addGlobalVar( 'version_url', 		$GLOBALS['_VERSION']->URL );

		$tmpl->addVar( 'form', 'formAction', 		$_SERVER['PHP_SELF'] );
		$tmpl->addVar( 'form', 'formName', 			'adminForm' );

		if ($_LANG->iso()) {
			$tmpl->addGlobalVar( 'lang_iso', 		$_LANG->iso() );
		} else {
			// TODO: Try and determine the charset from the browser
			$tmpl->addGlobalVar( 'lang_iso', 		'iso-8859-1' );
			
		}
		
		$tmpl->addGlobalVar( 'lang_charset',	'charset=UTF-8' );

		// tabs
		$tpath = mosFS::getNativePath( $mainframe->getTemplatePath() . 'images/tabs' );
		if (is_dir( $tpath )) {
			$turl = $mainframe->getTemplateURL() .'/images/tabs/';
		} else {
			$turl = $mosConfig_live_site .'/includes/js/tabs/';
		}
		$tmpl->addVar( 'includeTabs', 'taburl', $turl );

		return $tmpl;
	}
}
?>