<?php
/**
 * @version		$Id: field.php 11453 2009-01-25 05:12:38Z eddieajau $
 * @package		Joomla.Framework
 * @subpackage	Access
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @copyright	Copyright (C) 2008 - 2009 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License, see LICENSE.php
 */

defined('JPATH_BASE') or die;

if (!defined('JPERMISSION_VIEW')) {
	define('JPERMISSION_VIEW', 3);
}
if (!defined('JPERMISSION_ASSET')) {
	define('JPERMISSION_ASSET', 2);
}
if (!defined('JPERMISSION_ACTION')) {
	define('JPERMISSION_ACTION', 1);
}

/**
 * Class that handles access authorization CRUD
 *
 * @static
 * @package 	Joomla.Framework
 * @subpackage	User
 * @version		1.0
 */
class JXAccessHelper
{
	/**
	 * Factory method to get a JAccessLevel object and optionally load it by title and section.
	 *
	 * @access	public
	 * @param	string	Access level title.
	 * @param	string	Access section name.
	 * @param	string	Access action name.
	 * @return	object	JAccessLevel object.
	 * @since	1.0
	 */
	public function &getAccessLevel($title = null, $section = null, $action = 'core.view')
	{
		jimport('joomla.access.permission.accesslevel');

		$instance = new JAccessLevel();

		// If a title and section are present, attempt to load the access level.
		if (!empty($title) && !empty($section)) {
			$instance->load($title, $section, $action);
		}

		return $instance;
	}

	/**
	 * Factory method to get a JSimpleRule object and optionally load it by action and asset.
	 *
	 * @access	public
	 * @param	string	Rule action name.
	 * @param	string	Rule asset name.
	 * @return	object	JSimpleRule object.
	 * @since	1.0
	 */
	public function &getSimpleRule($action = null, $asset = null)
	{
		jimport('joomla.access.permission.simplerule');

		$instance = new JSimpleRule();

		// If an action is present, attempt to load the rule.
		if (!empty($action)) {
			$instance->load($action, $asset);
		}

		return $instance;
	}

	/**
	 * Get a Section Id by it name
	 *
	 * @param	string $section	The section name
	 *
	 * @return	int				The section id or zero if not found
	 * @since	1.1
	 */
	public function getSectionId($section)
	{
		static $cache;

		if ($cache == null) {
			$cache = array();
		}

		// Sanitize the section name.
		$section = JXAccessHelper::_sanitizeName($section);

		if (empty($cache[$section]))
		{
			// Get a database object.
			$db = &JFactory::getDBO();

			// Check to see if the section exists.
			$db->setQuery(
				'SELECT `id`' .
				' FROM `#__access_sections`' .
				' WHERE `name` = '.$db->Quote($section)
			);
			$sectionId = $db->loadResult();

			// Check for a database error.
			if ($db->getErrorNum()) {
				return new JException($db->getErrorMsg());
			}

			// If the section does not exist, throw an exception.
			if (empty($sectionId)) {
				return 0;
			}
			else {
				$cache[$section] = $sectionId;
			}
		}

		return $cache[$section];
	}

	/**
	 * Method to register an access section if it does not already exist.
	 *
	 * @access	public
	 * @param	string	Section title.
	 * @param	string	Section name.
	 * @return	mixed	JException on failure or section id on success.
	 * @since	1.0
	 */
	public function registerSection($title, $name)
	{
		// Sanitize the section name.
		$name = JXAccessHelper::_sanitizeName($name);

		// Get a database object.
		$db = &JFactory::getDBO();

		// Check to see if the section already exists.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__access_sections`' .
			' WHERE `name` = '.$db->Quote($name)
		);
		$sectionId = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// If the section already exists, update the title, else create the section.
		if (!empty($sectionId))
		{
			// Update the title for the section in the database.
			$db->setQuery(
				'UPDATE `#__access_sections`' .
				' SET `title` = '.$db->Quote($title) .
				' WHERE `id` = '.(int)$sectionId
			);
			$db->query();

			// Check for a database error.
			if ($db->getErrorNum()) {
				return new JException($db->getErrorMsg());
			}
		}
		else
		{
			// Insert the section into the database.
			$db->setQuery(
				'INSERT INTO `#__access_sections` (`name`, `title`) VALUES' .
				' ('.$db->Quote($name).', '.$db->Quote($title).')'
			);
			$db->query();

			// Check for a database error.
			if ($db->getErrorNum()) {
				return new JException($db->getErrorMsg());
			}

			// Get the section id of the section just inserted.
			$sectionId = $db->insertid();
		}

		return (int) $sectionId;
	}

	/**
	 * Method to remove an access section by name.
	 *
	 * @access	public
	 * @param	string	Section name.
	 * @return	mixed	JException on failure or boolean true on success.
	 * @since	1.0
	 */
	public function removeSection($name)
	{
		// Sanitize the section name.
		$name = JXAccessHelper::_sanitizeName($name);

		// Get a database object.
		$db = &JFactory::getDBO();

		// Get the section id by name.
		$db->setQuery(
			'SELECT `id` FROM `#__access_sections`' .
			' WHERE `name` = '.$db->Quote($name)
		);
		$id = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Check if an id was found.
		if (!$id) {
			return true;
		}

		// Delete any actions for this section.
		$db->setQuery(
			'DELETE a, b FROM `#__access_actions` AS a' .
			' LEFT JOIN `#__access_action_rule_map` AS b ON b.action_id = a.id' .
			' WHERE a.section_id = '.(int)$id
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Delete any assets for this section.
		$db->setQuery(
			'DELETE a, b, c FROM `#__access_assets` AS a' .
			' LEFT JOIN `#__access_asset_assetgroup_map` AS b ON b.asset_id = a.id' .
			' LEFT JOIN `#__access_asset_rule_map` AS c ON c.asset_id = a.id' .
			' WHERE a.section_id = '.(int)$id
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Delete any asset groups for this section.
		$db->setQuery(
			'DELETE a, b FROM `#__access_assetgroups` AS a' .
			' LEFT JOIN `#__access_assetgroup_rule_map` AS b ON b.group_id = a.id' .
			' WHERE a.section_id = '.(int)$id
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Delete any rules for this section.
		$db->setQuery(
			'DELETE a, b FROM `#__access_rules` AS a' .
			' LEFT JOIN `#__user_rule_map` AS b ON b.rule_id = a.id' .
			' WHERE a.section_id = '.(int)$id
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Delete any user groups for this section.
		$db->setQuery(
			'DELETE a, b, c FROM `#__usergroups` AS a' .
			' LEFT JOIN `#__usergroup_rule_map` AS b ON b.group_id = a.id' .
			' LEFT JOIN `#__user_usergroup_map` AS c ON c.group_id = a.id' .
			' WHERE a.section_id = '.(int)$id
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Delete the section if it exists.
		$db->setQuery(
			'DELETE FROM `#__access_sections`' .
			' WHERE `id` = '.$id
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		return true;
	}

	/**
	 * Method to register an access action if it does not already exist.
	 *
	 * @access	public
	 * @param	integer	Permission type. Valid: JPERMISSION_VIEW | JPERMISSION_ASSET | JPERMISSION_ACTION
	 * @param	string	Section name.
	 * @param	string	Action title.
	 * @param	string	Action description.
	 * @param	string	Action name segment.
	 * @return	mixed	JException on failure or action name on success.
	 * @since	1.0
	 */
	public function registerAction($type, $section, $title, $description = null, $name = null)
	{
		// Sanitize the section name.
		$section = JXAccessHelper::_sanitizeName($section);

		// Sanitize and build the action name.
		if (!empty($name)) {
			// If a name segment was specified, use it for the name.
			$name = JXAccessHelper::_sanitizeName($name, $section);
		}
		else {
			// If no name segment was specified, use the title as the segment.
			$name = JXAccessHelper::_sanitizeName($title, $section);
		}

		// Get a database object.
		$db = &JFactory::getDBO();

		// Check to see if the section exists.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__access_sections`' .
			' WHERE `name` = '.$db->Quote($section)
		);
		$sectionId = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// If the section does not exist, throw an exception.
		if (empty($sectionId)) {
			return new JException(JText::_('ACCESS SECTION INVALID'));
		}

		// Check to see if the action already exists.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__access_actions`' .
			' WHERE `name` = '.$db->Quote($name) .
			' AND `section_id` = '.(int) $sectionId
		);
		$actionId = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// If the action already exists, update the data, else create it.
		if (!empty($actionId))
		{
			// Update the data for the action in the database.
			$db->setQuery(
				'UPDATE `#__access_actions`' .
				' SET `title` = '.$db->Quote($title).', `description` = '.$db->Quote($description) .
				' WHERE `id` = '.(int) $actionId
			);
			$db->query();

			// Check for a database error.
			if ($db->getErrorNum()) {
				return new JException($db->getErrorMsg());
			}
		}
		else
		{
			// Insert the action into the database.
			$db->setQuery(
				'INSERT INTO `#__access_actions` (`section_id`, `name`, `title`, `description`, `access_type`) VALUES' .
				' ('.(int) $sectionId.', '.$db->Quote($name).', '.$db->Quote($title).', '.$db->Quote($description).', '.(int) $type.')'
			);
			$db->query();

			// Check for a database error.
			if ($db->getErrorNum()) {
				return new JException($db->getErrorMsg());
			}

			// Get the id of the action just inserted.
			$actionId = $db->insertid();
		}

		return $name;
	}

	/**
	 * Method to remove an access action by name.
	 *
	 * @access	public
	 * @param	string	Action name.
	 * @return	mixed	JException on failure or boolean true on success.
	 * @since	1.0
	 */
	public function removeAction($name)
	{
		// Sanitize the action name.
		$name = JXAccessHelper::_sanitizeName($name);

		// Get a database object.
		$db = &JFactory::getDBO();

		// Get the id for the action.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__access_actions`' .
			' WHERE `name` = '.$db->Quote($name)
		);
		$actionId = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Delete any rules for this section.
		$db->setQuery(
			'DELETE a, b FROM `#__access_action_rule_map` AS a' .
			' LEFT JOIN `#__access_rules` AS b ON a.rule_id = b.id' .
			' WHERE a.action_id = '.(int) $actionId
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Delete the action if it exists.
		$db->setQuery(
			'DELETE FROM `#__access_actions`' .
			' WHERE `id` = '.(int) $actionId
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		return true;
	}

	/**
	 * Method to register a user group if it does not already exist.
	 *
	 * @access	public
	 * @param	string	Group title.
	 * @param	string	Section name.
	 * @return	mixed	JException on failure or group id on success.
	 * @since	1.0
	 */
	public function registerUserGroup($title, $section)
	{
		// Sanitize the section name.
		$section = JXAccessHelper::_sanitizeName($section);

		// Get a database object.
		$db = &JFactory::getDBO();

		// Check to see if the section already exists.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__access_sections`' .
			' WHERE `name` = '.$db->Quote($section)
		);
		$sectionId = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// If the section does not exist, throw an exception.
		if (empty($sectionId)) {
			return new JException(JText::_('ACCESS SECTION INVALID'));
		}

		// Check to see if the user group already exists.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__usergroups`' .
			' WHERE `title` = '.$db->Quote($title) .
			' AND `section_id` = '.(int) $sectionId
		);
		$groupId = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// If the group already exists, return the id.
		if (!empty($groupId)) {
			return (int)$groupId;
		}

		// Insert the user group into the database.
		$db->setQuery(
			'INSERT INTO `#__usergroups` (`parent_id` ,`left_id` ,`right_id` ,`title` ,`section_id` ,`section`) VALUES' .
			' (1, 0, 0, '.$db->Quote($title).', '.(int)$sectionId.', '.$db->Quote($section).')'
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Get the group id of the user group just inserted.
		$groupId = (int)$db->insertid();

		// Rebuild the groups tree.
		JXAccessHelper::_rebuildGroupsTree();

		return $groupId;
	}

	/**
	 * Method to remove a user group by title and section.
	 *
	 * @access	public
	 * @param	string	Group title.
	 * @param	string	Section name.
	 * @return	mixed	JException on failure or boolean true on success.
	 * @since	1.0
	 */
	public function removeUserGroup($title, $section)
	{
		// Sanitize the section name.
		$section = JXAccessHelper::_sanitizeName($section);

		// Get a database object.
		$db = &JFactory::getDBO();

		// Check to see if the usergroup exists.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__usergroups`' .
			' WHERE `title` = '.$db->Quote($title) .
			' AND `section` = '.$db->Quote($section)
		);
		$groupId = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Get a usergroup table object.
		$table = & JTable::getInstance('Usergroup', 'JXTable');

		// Attempt to delete the usergroup.
		if (!$table->delete($groupId)) {
			return new JException($table->getError());
		}

		// @todo REMOVE MAPS!!!

		return true;
	}

	/**
	 * Method to register an access action if it does not already exist.
	 *
	 * @access	public
	 * @param	integer	Permission type. Valid: JPERMISSION_VIEW | JPERMISSION_ASSET | JPERMISSION_ACTION
	 * @param	string	Section name.
	 * @param	string	Action title.
	 * @param	string	Action description.
	 * @param	string	Action name segment.
	 * @return	mixed	JException on failure or action name on success.
	 * @since	1.0
	 */
	public function registerAsset($section, $title, $name = null)
	{
		// Sanitize the section name.
		$section = JXAccessHelper::_sanitizeName($section);

		// Sanitize and build the action name.
		if (!empty($name)) {
			// If a name segment was specified, use it for the name.
			$name = JXAccessHelper::_sanitizeName($name, $section);
		}
		else {
			// If no name segment was specified, use the title as the segment.
			$name = JXAccessHelper::_sanitizeName($title, $section);
		}

		$sectionId = JxAccessHelper::getSectionId($section);

		// If the section does not exist, throw an exception.
		if (empty($sectionId)) {
			return new JException(JText::_('ACCESS SECTION INVALID'));
		}

		// Get a database object.
		$db = &JFactory::getDBO();

		// Check to see if the action already exists.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__access_assets`' .
			' WHERE `name` = '.$db->Quote($name) .
			' AND `section_id` = '.(int) $sectionId
		);
		$assetId = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Insert/update the asset into the database.
		$db->setQuery(
			'REPLACE INTO `#__access_assets` (`id`, `section_id`, `section`, `name`, `title`) VALUES' .
			' ('.(int) $assetId.', '.(int) $sectionId.', '.$db->Quote($section).', '.$db->Quote($name).', '.$db->Quote($title).')'
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		return $name;
	}

	/**
	 * Method to remove an access asset by name.
	 *
	 * @access	public
	 * @param	string	Asset name.
	 * @return	mixed	JException on failure or boolean true on success.
	 * @since	1.0
	 */
	public function removeAsset($name)
	{
		// @todo
	}

	/**
	 * Method to register an access level if it does not already exist.
	 *
	 * @access	public
	 * @param	string	Access level title.
	 * @param	string	Section name.
	 * @param	array	Array of user group ids.
	 * @param	array	Array of user ids.
	 * @param	string	Action name.
	 * @return	mixed	JException on failure or access level id on success.
	 * @since	1.0
	 */
	public function registerAccessLevel($title, $section, $userGroups = array(), $users = array(), $action = 'core.view')
	{
		// Sanitize the section name.
		$section = JXAccessHelper::_sanitizeName($section);

		// Get a database object.
		$db = &JFactory::getDBO();

		// Check to see if the section already exists.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__access_sections`' .
			' WHERE `name` = '.$db->Quote($section)
		);
		$sectionId = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// If the section does not exist, throw an exception.
		if (empty($sectionId)) {
			return new JException(JText::_('ACCESS SECTION INVALID'));
		}

		// Check to see if the assetgroup already exists.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `#__access_assetgroups`' .
			' WHERE `title` = '.$db->Quote($title) .
			' AND `section_id` = '.(int) $sectionId
		);
		$groupId = (int) $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Insert the group if it doesn't already exist.
		if (empty($groupId))
		{
			// Insert the assetgroup into the database.
			$db->setQuery(
				'INSERT INTO `#__access_assetgroups` (`parent_id` ,`left_id` ,`right_id` ,`title` ,`section_id` ,`section`) VALUES' .
				' (1, 0, 0, '.$db->Quote($title).', '.(int)$sectionId.', '.$db->Quote($section).')'
			);
			$db->query();

			// Check for a database error.
			if ($db->getErrorNum()) {
				return new JException($db->getErrorMsg());
			}

			// Get the group id of the assetgroup just inserted.
			$groupId = (int) $db->insertid();

			// Rebuild the groups tree.
			JXAccessHelper::_rebuildGroupsTree('assets');
		}

		// Get a JAccessLevel model and populate the values.
		$model = &JXAccessHelper::getAccessLevel($title, $section, $action);

		// Set the access section.
		$model->setSection($section);

		// Set the access action.
		$model->setAction($action);

		// Set the accessgroup.
		$model->setAssetGroup($groupId);

		// Set the accessgroup.
		$model->setUserGroups($userGroups);

		// Set the accessgroup.
		$model->setUsers($users);

		// Store the access level.
		if (!$model->store()) {
			return new JException($model->getError());
		}

		return $groupId;
	}

	/**
	 * Method to remove an access level by title and section.
	 *
	 * @access	public
	 * @param	string	Access level title.
	 * @param	string	Section name.
	 * @param	string	Access action name.
	 * @return	mixed	JException on failure or boolean true on success.
	 * @since	1.0
	 */
	public function removeAccessLevel($title, $section, $action = 'core.view')
	{
		// Get a JAccessLevel model.
		$model = &JXAccessHelper::getAccessLevel();

		// Delete the access level.
		if (!$model->delete($title, $section, $action)) {
			return new JException($model->getError());
		}

		// Get the asset group id from the access level.
		$groupId = $model->getAssetGroupId();

		// Get a database object.
		$db = &JFactory::getDBO();

		// Delete any asset maps to the assetgroup.
		$db->setQuery(
			'DELETE FROM `#__access_asset_assetgroup_map`' .
			' WHERE `group_id` = '.(int) $groupId
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		// Delete the assetgroup.
		$db->setQuery(
			'DELETE FROM `#__access_assetgroups`' .
			' WHERE `id` = '.(int) $groupId
		);
		$db->query();

		// Check for a database error.
		if ($db->getErrorNum()) {
			return new JException($db->getErrorMsg());
		}

		return true;
	}

	/**
	 * Method to register a simple rule if it does not already exist.
	 *
	 * @access	public
	 * @param	string	Action name.
	 * @param	string	Asset name.
	 * @param	array	Array of user group ids.
	 * @param	array	Array of user ids.
	 * @return	mixed	JException on failure or access level id on success.
	 * @since	1.0
	 */
	public function registerSimpleRule($action, $asset = null, $userGroups = array(), $users = array())
	{
		// Get a JSimpleRule model and populate the values.
		$model = &JXAccessHelper::getSimpleRule($action, $asset);

		// Set the usergroups.
		$model->setUserGroups($userGroups);

		// Set the users.
		$model->setUsers($users);

		// Store the rule.
		if (!$model->store()) {
			return new JException($model->getError());
		}

		return $model->getRule();
	}

	/**
	 * Method to remove a simple rule by action and asset.
	 *
	 * @access	public
	 * @param	string	Action name.
	 * @param	string	Asset name.
	 * @return	mixed	JException on failure or boolean true on success.
	 * @since	1.0
	 */
	public function removeSimpleRule($action, $asset = null)
	{
		// Method to remove a simple access rule if it exists.
	}

	public function _sanitizeName($title, $section = null)
	{
		// Sanitize the title.
		$name = strtolower(preg_replace('#[\s\-]+#', '.', trim($title)));

		// Prepend the section if present.
		if (!empty($section)) {
			$name = $section.'.'.$name;
		}

		return $name;
	}

	/**
	 * Method to recursively rebuild the nested set tree.
	 *
	 * @access	protected
	 * @param	integer	The root of the tree to rebuild.
	 * @param	integer	The left id to start with in building the tree.
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	public function _rebuildGroupsTree($type = 'user', $parentId = 0, $left = 0)
	{
		// Get a database object.
		$db = &JFactory::getDBO();

		$table = ($type == 'user') ? '#__usergroups' : '#__access_assetgroups';

		// Get all of the children of the parent.
		$db->setQuery(
			'SELECT `id`' .
			' FROM `'.$table.'`' .
			' WHERE `parent_id` = '. (int) $parentId .
			' ORDER BY `parent_id`, `title`'
		);
		$children = $db->loadResultArray();

		// The right value of this node is the left value + 1.
		$right = $left + 1;

		// Recursively run this method for all children.
		for ($i=0,$n=count($children); $i < $n; $i++)
		{
			// The right value is incremented for reach recursive return.
			$right = JXAccessHelper::_rebuildGroupsTree($type, $children[$i], $right);

			// If there is an error updating a child, return false to break out of recursion.
			if ($right === false) {
				return false;
			}
		}

		// Now having both left and right values, update the current node.
		$db->setQuery(
			'UPDATE `'.$table.'`' .
			' SET `left_id` = '.(int) $left.', `right_id` = '.(int) $right .
			' WHERE `id` = '.(int) $parentId
		);

		// If there is an error updating, return false to break out of recursion.
		if (!$db->query()) {
			return false;
		}

		// Return an incremented right value.
		return $right + 1;
	}

	/**
	 * Syncronise the assets from a local content store
	 *
	 * @param	array $items	A named array (by the Id field of the foreign asset table) of object have the properties name, title, and access
	 * @param	string $section	The asset section the object will belong to
	 *
	 * @return	mixed			True if successful, otherwise a JException
	 * @throws	JException
	 */
	public function synchronizeAssets($items, $section)
	{
		// Check we have a valid section, get the id
		$sectionId = JxAccessHelper::getSectionId($section);

		// If the section does not exist, throw an exception.
		if (empty($sectionId)) {
			return new JException(JText::_('ACCESS SECTION INVALID'));
		}

		//
		// Get the existing assets.
		//

		$db = &JFactory::getDbo();

		// Note the limitation of one groups per asset
		// If multiple groups are required then convert to using GROUP_CONCAT
		$db->setQuery(
			'SELECT asset.*, map.group_id'
			.' FROM #__access_assets AS asset'
			.' LEFT JOIN #__access_asset_assetgroup_map AS map ON map.group_id = asset.id'
			.' WHERE asset.section_id = '.(int) $sectionId
		);

		// Get the raw assets from the model, keyed on the id field
		$assets = $db->loadObjectList('name');

		// Cache the Asset Groups
		$db->setQuery(
			'SELECT id'
			.' FROM #__access_assetgroups'
		);
		$groups = $db->loadResultArray();

		//
		// Build the synchronization lists.
		//

		// Get the IDs which are stored as the array keys for both assets and items.
		$keys1	= array_keys($items);
		$keys2	= array_keys($assets);

		// Create the synchronization lists to add, drop and update.
		$add	= array_diff($keys1, $keys2);
		$drop	= array_diff($keys2, $keys1);
		$update	= array_intersect($keys1, $keys2);

		//
		// Perform the asset synchronization.
		//

		// Perform the add operations first.
		if (!empty($add))
		{
			foreach ($add as $id)
			{
				$result = JxAccessHelper::registerAsset($section, $items[$id]->title, $items[$id]->name);

				if (JError::isError($result)) {
					return $result;
				}
			}
		}

		// Next perform the drop operations.
		if (!empty($drop))
		{
			foreach ($drop as $id)
			{
				/*$result = JxAccessHelper::removeAsset($section, $items[$id]->name);

				if (JError::isError($result)) {
					return $result;
				}*/
			}
		}

		// Lastly perform the update operations.
		if (!empty($update))
		{
			foreach ($update as $id)
			{
				// If the name and ordering are the same, we do not need to do anything.
				if ($assets[$id]->title != $items[$id]->title)
				{
					$result = JxAccessHelper::registerAsset($section, $items[$id]->title, $assets[$id]->name);

					if (JError::isError($result)) {
						return $result;
					}
				}
/*
				// If the access fields are the same, then no need to change the groups
				if ($assets[$id]->group_id !== $items[$id]->access) {
					JxAclAdmin::registerAssetInGroups((int) $assets[$id]->id, $groups[$items[$id]->access]->id);
				}
*/
			}
		}

		return true;
	}
}
