<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	HTML
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('JPATH_BASE') or die;

/**
 * Pagination Class.  Provides a common interface for content pagination for the
 * Joomla! Framework.
 *
 * @package 	Joomla.Framework
 * @subpackage	HTML
 * @since		1.5
 */
class JPagination extends JObject
{
	/**
	 * The record number to start dislpaying from.
	 *
	 * @var int
	 */
	public $limitstart = null;

	/**
	 * Number of rows to display per page.
	 *
	 * @var int
	 */
	public $limit = null;

	/**
	 * Total number of rows.
	 *
	 * @var int
	 */
	public $total = null;

	/**
	 * Prefix used for request variables.
	 *
	 * @var int
	 */
	public $prefix = null;

	/**
	 * View all flag
	 *
	 * @var boolean
	 */
	protected $_viewall = false;

	/**
	 * Constructor.
	 *
	 * @param	int		The total number of items.
	 * @param	int		The offset of the item to start at.
	 * @param	int		The number of items to display per page.
	 * @param	string		The prefix used for request variables.
	 */
	function __construct($total, $limitstart, $limit, $prefix = '')
	{
		// Value/type checking.
		$this->total		= (int) $total;
		$this->limitstart	= (int) max($limitstart, 0);
		$this->limit		= (int) max($limit, 0);
		$this->prefix		= $prefix;

		if ($this->limit > $this->total) {
			$this->limitstart = 0;
		}

		if (!$this->limit)
		{
			$this->limit = $total;
			$this->limitstart = 0;
		}

		/*
		 * If limitstart is greater than total (i.e. we are asked to display records that don't exist)
		 * then set limitstart to display the last natural page of results
		 */
		if ($this->limitstart > $this->total) {
			$this->limitstart = (int)(ceil($this->total / $this->limit) - 1) * $this->limit;
		}

		// Set the total pages and current page values.
		if ($this->limit > 0)
		{
			$this->set('pages.total', ceil($this->total / $this->limit));
			$this->set('pages.current', ceil(($this->limitstart + 1) / $this->limit));
		}

		// Set the pagination iteration loop values.
		$displayedPages	= 10;
		$this->set('pages.start', (floor(($this->get('pages.current') -1) / $displayedPages)) * $displayedPages +1);
		if ($this->get('pages.start') + $displayedPages -1 < $this->get('pages.total')) {
			$this->set('pages.stop', $this->get('pages.start') + $displayedPages -1);
		}
		else {
			$this->set('pages.stop', $this->get('pages.total'));
		}

		// If we are viewing all records set the view all flag to true.
		if ($this->limit == $total) {
			$this->_viewall = true;
		}
	}

	/**
	 * Return the rationalised offset for a row with a given index.
	 *
	 * @param	int		$index The row index
	 * @return	int		Rationalised offset for a row with a given index.
	 * @since	1.5
	 */
	public function getRowOffset($index)
	{
		return $index +1 + $this->limitstart;
	}

	/**
	 * Return the pagination data object, only creating it if it doesn't already exist.
	 *
	 * @return	object	Pagination data object.
	 * @since	1.5
	 */
	public function getData()
	{
		static $data;
		if (!is_object($data)) {
			$data = $this->_buildDataObject();
		}
		return $data;
	}

	/**
	 * Create and return the pagination pages counter string, ie. Page 2 of 4.
	 *
	 * @return	string	Pagination pages counter string.
	 * @since	1.5
	 */
	public function getPagesCounter()
	{
		// Initialize variables
		$html = null;
		if ($this->get('pages.total') > 1) {
			$html .= JText::sprintf('JPAGE_CURRENT_OF_TOTAL', $this->get('pages.current'), $this->get('pages.total'));
		}
		return $html;
	}

	/**
	 * Create and return the pagination result set counter string, ie. Results 1-10 of 42
	 *
	 * @return	string	Pagination result set counter string.
	 * @since	1.5
	 */
	public function getResultsCounter()
	{
		// Initialize variables
		$html = null;
		$fromResult = $this->limitstart + 1;

		// If the limit is reached before the end of the list.
		if ($this->limitstart + $this->limit < $this->total) {
			$toResult = $this->limitstart + $this->limit;
		}
		else {
			$toResult = $this->total;
		}

		// If there are results found.
		if ($this->total > 0) {
			$msg = JText::sprintf('Results of', $fromResult, $toResult, $this->total);
			$html .= "\n".$msg;
		}
		else {
			$html .= "\n".JText::_('No records found');
		}

		return $html;
	}

	/**
	 * Create and return the pagination page list string, ie. Previous, Next, 1 2 3 ... x.
	 *
	 * @return	string	Pagination page list string.
	 * @since	1.0
	 */
	public function getPagesLinks()
	{
		$app = &JFactory::getApplication();

		// Build the page navigation list.
		$data = $this->_buildDataObject();

		$list = array();
		$list['prefix']			= $this->prefix;

		$itemOverride = false;
		$listOverride = false;

		$chromePath = JPATH_THEMES.DS.$app->getTemplate().DS.'html'.DS.'pagination.php';
		if (file_exists($chromePath))
		{
			require_once $chromePath;
			if (function_exists('pagination_item_active') && function_exists('pagination_item_inactive')) {
				$itemOverride = true;
			}
			if (function_exists('pagination_list_render')) {
				$listOverride = true;
			}
		}

		// Build the select list
		if ($data->all->base !== null) {
			$list['all']['active'] = true;
			$list['all']['data'] = ($itemOverride) ? pagination_item_active($data->all) : $this->_item_active($data->all);
		} else {
			$list['all']['active'] = false;
			$list['all']['data'] = ($itemOverride) ? pagination_item_inactive($data->all) : $this->_item_inactive($data->all);
		}

		if ($data->start->base !== null) {
			$list['start']['active'] = true;
			$list['start']['data'] = ($itemOverride) ? pagination_item_active($data->start) : $this->_item_active($data->start);
		} else {
			$list['start']['active'] = false;
			$list['start']['data'] = ($itemOverride) ? pagination_item_inactive($data->start) : $this->_item_inactive($data->start);
		}
		if ($data->previous->base !== null) {
			$list['previous']['active'] = true;
			$list['previous']['data'] = ($itemOverride) ? pagination_item_active($data->previous) : $this->_item_active($data->previous);
		} else {
			$list['previous']['active'] = false;
			$list['previous']['data'] = ($itemOverride) ? pagination_item_inactive($data->previous) : $this->_item_inactive($data->previous);
		}

		$list['pages'] = array(); //make sure it exists
		foreach ($data->pages as $i => $page)
		{
			if ($page->base !== null) {
				$list['pages'][$i]['active'] = true;
				$list['pages'][$i]['data'] = ($itemOverride) ? pagination_item_active($page) : $this->_item_active($page);
			} else {
				$list['pages'][$i]['active'] = false;
				$list['pages'][$i]['data'] = ($itemOverride) ? pagination_item_inactive($page) : $this->_item_inactive($page);
			}
		}

		if ($data->next->base !== null) {
			$list['next']['active'] = true;
			$list['next']['data'] = ($itemOverride) ? pagination_item_active($data->next) : $this->_item_active($data->next);
		}
		else {
			$list['next']['active'] = false;
			$list['next']['data'] = ($itemOverride) ? pagination_item_inactive($data->next) : $this->_item_inactive($data->next);
		}

		if ($data->end->base !== null) {
			$list['end']['active'] = true;
			$list['end']['data'] = ($itemOverride) ? pagination_item_active($data->end) : $this->_item_active($data->end);
		}

		else {
			$list['end']['active'] = false;
			$list['end']['data'] = ($itemOverride) ? pagination_item_inactive($data->end) : $this->_item_inactive($data->end);
		}

		if ($this->total > $this->limit){
			return ($listOverride) ? pagination_list_render($list) : $this->_list_render($list);
		}
		else {
			return '';
		}
	}

	/**
	 * Return the pagination footer.
	 *
	 * @return	string	Pagination footer.
	 * @since	1.0
	 */
	public function getListFooter()
	{
		$app = JFactory::getApplication();

		$list = array();
		$list['prefix']			= $this->prefix;
		$list['limit']			= $this->limit;
		$list['limitstart']		= $this->limitstart;
		$list['total']			= $this->total;
		$list['limitfield']		= $this->getLimitBox();
		$list['pagescounter']	= $this->getPagesCounter();
		$list['pageslinks']		= $this->getPagesLinks();

		$chromePath	= JPATH_THEMES.DS.$app->getTemplate().DS.'html'.DS.'pagination.php';
		if (file_exists($chromePath))
		{
			require_once $chromePath;
			if (function_exists('pagination_list_footer')) {
				return pagination_list_footer($list);
			}
		}
		return $this->_list_footer($list);
	}

	/**
	 * Creates a dropdown box for selecting how many records to show per page.
	 *
	 * @return	string	The html for the limit # input box.
	 * @since	1.0
	 */
	public function getLimitBox()
	{
		$app = JFactory::getApplication();

		// Initialize variables.
		$limits = array ();

		// Make the option list.
		for ($i = 5; $i <= 30; $i += 5) {
			$limits[] = JHtml::_('select.option', "$i");
		}
		$limits[] = JHtml::_('select.option', '50');
		$limits[] = JHtml::_('select.option', '100');
		$limits[] = JHtml::_('select.option', '0', JText::_('all'));

		$selected = $this->_viewall ? 0 : $this->limit;

		// Build the select list.
		if ($app->isAdmin()) {
			$html = JHtml::_('select.genericlist',  $limits, $this->prefix . 'limit', 'class="inputbox" size="1" onchange="submitform();"', 'value', 'text', $selected);
		}
		else {
			$html = JHtml::_('select.genericlist',  $limits, $this->prefix . 'limit', 'class="inputbox" size="1" onchange="this.form.submit()"', 'value', 'text', $selected);
		}
		return $html;
	}

	/**
	 * Return the icon to move an item UP.
	 *
	 * @param	integer	The row index.
	 * @param	boolean	True to show the icon.
	 * @param	string	The task to fire.
	 * @param	string	The image alternate text string.
	 * @return	string	Either the icon to move an item up or a space.
	 * @since	1.0
	 */
	public function orderUpIcon($i, $condition = true, $task = 'orderup', $alt = 'JGrid_Move_Up', $enabled = true)
	{
		$alt = JText::_($alt);

		$html = '&nbsp;';
		if (($i > 0 || ($i + $this->limitstart > 0)) && $condition)
		{
			if ($enabled) {
				$html	= '<a href="#reorder" onclick="return listItemTask(\'cb'.$i.'\',\''.$task.'\')" title="'.$alt.'">';
				$html	.= '   <img src="templates/bluestork/images/admin/uparrow.png" width="16" height="16" border="0" alt="'.$alt.'" />';
				$html	.= '</a>';
			}
			else {
				$html	= '<img src="templates/bluestork/images/admin/uparrow0.png" width="16" height="16" border="0" alt="'.$alt.'" />';
			}
		}

		return $html;
	}

	/**
	 * Return the icon to move an item DOWN.
	 *
	 * @param	int		The row index.
	 * @param	int		The number of items in the list.
	 * @param	boolean	True to show the icon.
	 * @param	string	The task to fire.
	 * @param	string	The image alternate text string.
	 * @return	string	Either the icon to move an item down or a space.
	 * @since	1.0
	 */
	public function orderDownIcon($i, $n, $condition = true, $task = 'orderdown', $alt = 'JGrid_Move_Down', $enabled = true)
	{
		$alt = JText::_($alt);

		$html = '&nbsp;';
		if (($i < $n -1 || $i + $this->limitstart < $this->total - 1) && $condition)
		{
			if ($enabled) {
				$html	= '<a href="#reorder" onclick="return listItemTask(\'cb'.$i.'\',\''.$task.'\')" title="'.$alt.'">';
				$html	.= '  <img src="templates/bluestork/images/admin/downarrow.png" width="16" height="16" border="0" alt="'.$alt.'" />';
				$html	.= '</a>';
			} else {
				$html	= '<img src="templates/bluestork/images/admin/downarrow0.png" width="16" height="16" border="0" alt="'.$alt.'" />';
			}
		}

		return $html;
	}

	protected function _list_footer($list)
	{
		$html = "<div class=\"list-footer\">\n";

		$html .= "\n<div class=\"limit\">".JText::_('Display Num').$list['limitfield']."</div>";
		$html .= $list['pageslinks'];
		$html .= "\n<div class=\"counter\">".$list['pagescounter']."</div>";

		$html .= "\n<input type=\"hidden\" name=\"" . $list['prefix'] . "limitstart\" value=\"".$list['limitstart']."\" />";
		$html .= "\n</div>";

		return $html;
	}

	protected function _list_render($list)
	{
		// Reverse output rendering for right-to-left display.
		$html = '&lt;&lt; ';
		$html .= $list['start']['data'];
		$html .= ' &lt; ';
		$html .= $list['previous']['data'];
		foreach($list['pages'] as $page) {
			$html .= ' '.$page['data'];
		}
		$html .= ' '. $list['next']['data'];
		$html .= ' &gt;';
		$html .= ' '. $list['end']['data'];
		$html .= ' &gt;&gt;';

		return $html;
	}

	protected function _item_active(&$item)
	{
		$app = &JFactory::getApplication();
		if ($app->isAdmin())
		{
			if ($item->base > 0) {
				return "<a title=\"".$item->text."\" onclick=\"javascript: document.adminForm.." . $this->prefix . "limitstart.value=".$item->base."; submitform();return false;\">".$item->text."</a>";
			}
			else {
				return "<a title=\"".$item->text."\" onclick=\"javascript: document.adminForm.." . $this->prefix . "limitstart.value=0; submitform();return false;\">".$item->text."</a>";
			}
		}
		else {
			return "<a title=\"".$item->text."\" href=\"".$item->link."\" class=\"pagenav\">".$item->text."</a>";
		}
	}

	protected function _item_inactive(&$item)
	{
		$app = &JFactory::getApplication();
		if ($app->isAdmin()) {
			return "<span>".$item->text."</span>";
		}
		else {
			return "<span class=\"pagenav\">".$item->text."</span>";
		}
	}

	/**
	 * Create and return the pagination data object.
	 *
	 * @return	object	Pagination data object.
	 * @since	1.5
	 */
	protected function _buildDataObject()
	{
		// Initialize variables.
		$data = new stdClass();

		$data->all = new JPaginationObject(JText::_('View All'), $this->prefix);
		if (!$this->_viewall) {
			$data->all->base	= '0';
			$data->all->link	= JRoute::_("&" . $this->prefix . "limitstart=");
		}

		// Set the start and previous data objects.
		$data->start	= new JPaginationObject(JText::_('Start'), $this->prefix);
		$data->previous	= new JPaginationObject(JText::_('Prev'), $this->prefix);

		if ($this->get('pages.current') > 1)
		{
			$page = ($this->get('pages.current') -2) * $this->limit;

			$page = $page == 0 ? '' : $page; //set the empty for removal from route

			$data->start->base	= '0';
			$data->start->link	= JRoute::_("&" . $this->prefix . "limitstart=");
			$data->previous->base	= $page;
			$data->previous->link	= JRoute::_("&" . $this->prefix . "limitstart=".$page);
		}

		// Set the next and end data objects.
		$data->next	= new JPaginationObject(JText::_('Next'), $this->prefix);
		$data->end	= new JPaginationObject(JText::_('End'), $this->prefix);

		if ($this->get('pages.current') < $this->get('pages.total'))
		{
			$next = $this->get('pages.current') * $this->limit;
			$end  = ($this->get('pages.total') -1) * $this->limit;

			$data->next->base	= $next;
			$data->next->link	= JRoute::_("&" . $this->prefix . "limitstart=".$next);
			$data->end->base	= $end;
			$data->end->link	= JRoute::_("&" . $this->prefix . "limitstart=".$end);
		}

		$data->pages = array();
		$stop = $this->get('pages.stop');
		for ($i = $this->get('pages.start'); $i <= $stop; $i ++)
		{
			$offset = ($i -1) * $this->limit;

			$offset = $offset == 0 ? '' : $offset;  //set the empty for removal from route

			$data->pages[$i] = new JPaginationObject($i, $this->prefix);
			if ($i != $this->get('pages.current') || $this->_viewall)
			{
				$data->pages[$i]->base	= $offset;
				$data->pages[$i]->link	= JRoute::_("&" . $this->prefix . "limitstart=".$offset);
			}
		}
		return $data;
	}
}

/**
 * Pagination object representing a particular item in the pagination lists.
 *
 * @package 	Joomla.Framework
 * @subpackage	HTML
 * @since		1.5
 */
class JPaginationObject extends JObject
{
	public $text;
	public $base;
	public $link;
	public $prefix;

	public function __construct($text, $prefix = '', $base = null, $link = null)
	{
		$this->text = $text;
		$this->prefix = $prefix;
		$this->base = $base;
		$this->link = $link;
	}
}
