<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	Cache
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('JPATH_BASE') or die;

/**
 * WINCACHE cache storage handler
 */
class JCacheStorageWincache extends JCacheStorage
{
	/**
	 * Constructor
	 *
	 * @param array $options optional parameters
	 */
	public function __construct( $options = array() )
	{
		parent::__construct($options);

		$config		= & JFactory::getConfig();
		$this->_hash	= $config->getValue('config.secret');
	}

	/**
	 * Get cached data from WINCACHE by id and group
	 *
	 * @param	string	The cache data id
	 * @param	string	The cache data group
	 * @param	boolean	True to verify cache time expiration threshold
	 * @return	mixed	Boolean false on failure or a cached data string
	 * @since	1.5
	 */
	public function get($id, $group, $checkTime)
	{
		$cache_id = $this->_getCacheId($id, $group);
		$this->_setExpire($cache_id);
		return wincache_ucache_get($cache_id);
	}

	/**
	 * Store the data to WINCACHE by id and group
	 *
	 * @param	string	The cache data id
	 * @param	string	The cache data group
	 * @param	string	The data to store in cache
	 * @return	boolean	True on success, false otherwise
	 * @since	1.5
	 */
	public function store($id, $group, $data)
	{
		$cache_id = $this->_getCacheId($id, $group);
		wincache_ucache_set($cache_id.'_expire', time());
		return wincache_ucache_set($cache_id, $data, $this->_lifetime);
	}

	/**
	 * Remove a cached data entry by id and group
	 *
	 * @param	string	The cache data id
	 * @param	string	The cache data group
	 * @return	boolean	True on success, false otherwise
	 * @since	1.5
	 */
	public function remove($id, $group)
	{
		$cache_id = $this->_getCacheId($id, $group);
		wincache_ucache_delete($cache_id.'_expire');
		return wincache_ucache_delete($cache_id);
	}

	/**
	 * Clean cache for a group given a mode.
	 *
	 * group mode		: cleans all cache in the group
	 * notgroup mode	: cleans all cache not in the group
	 *
	 * @param	string	The cache data group
	 * @param	string	The mode for cleaning cache [group|notgroup]
	 * @return	boolean	True on success, false otherwise
	 * @since	1.5
	 */
	public function clean($group, $mode)
	{
		return true;
	}

	/**
	 * Test to see if the cache storage is available.
	 *
	 * @return boolean  True on success, false otherwise.
	 */
	public static function test()
	{
		return (extension_loaded('wincache') && function_exists('wincache_ucache_get') && !strcmp(ini_get('wincache.ucenabled'), "1"));
	}

	/**
	 * Set expire time on each call since memcache sets it on cache creation.
	 *
	 * @param string  Cache key to expire.
	 * @param integer Lifetime of the data in seconds.
	 */
	protected function _setExpire($key)
	{
		$lifetime	= $this->_lifetime;
		$expire		= wincache_ucache_get($key.'_expire');

		// set prune period
		if ($expire + $lifetime < time()) {
			wincache_ucache_delete($key);
			wincache_ucache_delete($key.'_expire');
		} else {
			wincache_ucache_set($key.'_expire',  time());
		}
	}

	/**
	 * Get a cache_id string from an id/group pair
	 *
	 * @param	string	The cache data id
	 * @param	string	The cache data group
	 * @return	string	The cache_id string
	 * @since	1.5
	 */
	protected function _getCacheId($id, $group)
	{
		$name	= md5($this->_application.'-'.$id.'-'.$this->_hash.'-'.$this->_language);
		return 'cache_'.$group.'-'.$name;
	}
}
