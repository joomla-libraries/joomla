<?php
/**
* @version $Id: section.php 3996 2006-06-12 03:44:31Z spacemonkey $
* @package Joomla.Framework
* @subpackage Table
* @copyright Copyright (C) 2005 - 2006 Open Source Matters. All rights reserved.
* @license GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

/**
 * Section table
 *
 * @package 	Joomla.Framework
 * @subpackage 	Table
 * @since	1.0
 */
class JTableSection extends JTable
{
	/** @var int Primary key */
	var $id					= null;
	/** @var string The menu title for the Section (a short name)*/
	var $title				= null;
	/** @var string The full name for the Section*/
	var $name				= null;
	/** @var string */
	var $image				= null;
	/** @var string */
	var $scope				= null;
	/** @var int */
	var $image_position		= null;
	/** @var string */
	var $description		= null;
	/** @var boolean */
	var $published			= null;
	/** @var boolean */
	var $checked_out		= null;
	/** @var time */
	var $checked_out_time	= null;
	/** @var int */
	var $ordering			= null;
	/** @var int */
	var $access				= null;
	/** @var string */
	var $params				= null;

	/**
	* @param database A database connector object
	*/
	function __construct( &$db ) {
		parent::__construct( '#__sections', 'id', $db );
	}
	// overloaded check function
	function check()
	{
		// check for valid name
		if (trim( $this->title ) == '') {
			$this->_error = JText::sprintf( 'must contain a title', JText::_( 'Section') );
			return false;
		}
		if (trim( $this->name ) == '') {
			$this->_error = JText::sprintf( 'must have a name', JText::_( 'Section') );
			return false;
		}
		// check for existing name
		$query = "SELECT id"
		. "\n FROM #__sections "
		. "\n WHERE name = '$this->name'"
		. "\n AND scope = '$this->scope'"
		;
		$this->_db->setQuery( $query );

		$xid = intval( $this->_db->loadResult() );
		if ($xid && $xid != intval( $this->id )) {
			$this->_error = JText::sprintf( 'WARNNAMETRYAGAIN', JText::_( 'Section') );
			return false;
		}
		return true;
	}

	/**
	* Overloaded bind function
	*
	* @access public
	* @param array $hash named array
	* @return null|string	null is operation was satisfactory, otherwise returns an error
	* @see JTable:bind
	* @since 1.5
	*/
	function bind($array, $ignore = '')
	{
		if (isset( $array['params'] ) && is_array($array['params']))
		{
			$registry = new JRegistry();
			$registry->loadArray($array['params']);
			$array['params'] = $registry->toString();
		}

		return parent::bind($array, $ignore);
	}
}
?>
