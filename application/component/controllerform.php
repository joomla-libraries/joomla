<?php
/**
 * @version		$Id$
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

// @TODO Add ability to set redirect manually to better cope with frontend usage.

/**
 * Controller tailored to suit most form-based admin operations.
 *
 * @package		Joomla.Framework
 * @subpackage	Application
 * @version		1.6
 */
class JControllerForm extends JController
{
	/**
	 * @var	string	The URL option for the component.
	 */
	protected $_option;

	/**
	 * @var	string	The URL view item variable.
	 */
	protected $_view_item;

	/**
	 * @var	string	The URL view list variable.
	 */
	protected $_view_list;

	/**
	 * @var string	The context for storing internal data, eg com_records.edit.record.
	 */
	protected $_context;

	/**
	 * Constructor.
	 *
	 * @param	array An optional associative array of configuration settings.
	 * @see		JController
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Guess the option as com_NameOfController
		if (empty($this->_option)) {
			$this->_option = 'com_'.strtolower($this->getName());
		}

		// Guess the context as the suffix, eg: OptionControllerContent.
		if (empty($this->_context)) {
			$r = null;
			if (!preg_match('/(.*)Controller(.*)/i', get_class($this), $r)) {
				JError::raiseError(500, 'JController_Error_Cannot_parse_name');
			}
			$this->_context = strtolower($r[2]);
		}

		// Guess the item view as the context.
		if (empty($this->_view_item)) {
			$this->_view_item = $this->_context;
		}

		// Guess the list view as the plural of the item view.
		if (empty($this->_view_list))
		{
			// @TODO Probably worth moving to an inflector class based on http://kuwamoto.org/2007/12/17/improved-pluralizing-in-php-actionscript-and-ror/

			// Simple pluralisation based on public domain snippet by Paul Osman
			// For more complex types, just manually set the variable in your class.
	        $plural = array(
			    array( '/(x|ch|ss|sh)$/i',         "$1es"    ),
			    array( '/([^aeiouy]|qu)y$/i',      "$1ies"   ),
			    array( '/([^aeiouy]|qu)ies$/i',    "$1y"     ),
	            array( '/(bu)s$/i',                "$1ses"   ),
	    	    array( '/s$/i',                    "s"       ),
	    	    array( '/$/',                      "s"       )
	        );

		    // check for matches using regular expressions
		    foreach ($plural as $pattern)
		    {
		    	if (preg_match($pattern[0], $this->_view_item)) {
					$this->_view_list = preg_replace( $pattern[0], $pattern[1], $this->_view_item);
					break;
		    	}
		    }
		}

		// Apply, Save & New, and Save As copy should be standard on forms.
		$this->registerTask('apply',		'save');
		$this->registerTask('save2new',		'save');
		$this->registerTask('save2copy',	'save');
	}

	/**
	 * Method to get a model object, loading it if required.
	 *
	 * @param	string	The model name. Optional.
	 * @param	string	The class prefix. Optional.
	 * @param	array	Configuration array for model. Optional.
	 * @return	object	The model.
	 * @since	1.5
	 */
	public function &getModel($name = '', $prefix = '', $config = array())
	{
		if (empty($name)) {
			$name = $this->_context;
		}

		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * This controller does not have a display method. Redirect back to the list view of the component.
	 *
	 * @return	void
	 */
	public function display()
	{
		$this->setRedirect(JRoute::_('index.php?option='.$this->_option.'&view='.$this->_view_list, false));
	}

	/**
	 * Method to add a new record.
	 *
	 * @return	void
	 */
	public function add()
	{
		// Initialize variables.
		$app		= &JFactory::getApplication();
		$context	= "$this->_option.edit.$this->_context";

		// Clear the record edit information from the session.
		$app->setUserState($context.'.id', null);
		$app->setUserState($context.'data', null);

		// Redirect to the edit screen.
		$this->setRedirect(JRoute::_('index.php?option='.$this->_option.'&view='.$this->_view_item.'&layout=edit', false));
	}

	/**
	 * Method to edit an existing record.
	 *
	 * @return	void
	 */
	public function edit()
	{
		// Initialize variables.
		$app		= &JFactory::getApplication();
		$model		= &$this->getModel();
		$cid		= JRequest::getVar('cid', array(), 'post', 'array');
		$context	= "$this->_option.edit.$this->_context";

		// Get the previous record id (if any) and the current record id.
		$previousId		= (int) $app->getUserState($context.'.id');
		$recordId		= (int) (count($cid) ? $cid[0] : JRequest::getInt('id'));
		$checkin		= method_exists($model, 'checkin');

		// If record ids do not match, checkin previous record.
		if ($checkin && ($previousId > 0) && ($recordId != $previousId))
		{
			if (!$model->checkin($previousId))
			{
				// Check-in failed, go back to the record and display a notice.
				$message = JText::sprintf('JError_Checkin_failed', $model->getError());
				$this->setRedirect('index.php?option='.$this->_option.'&view='.$this->_view_item.'&layout=edit', $message, 'error');
				return false;
			}
		}

		// Attempt to check-out the new record for editing and redirect.
		if ($checkin && !$model->checkout($recordId))
		{
			// Check-out failed, go back to the list and display a notice.
			$message = JText::sprintf('JError_Checkout_failed', $model->getError());
			$this->setRedirect('index.php?option='.$this->_option.'&view='.$this->_view_item.'&id='.$recordId, $message, 'error');
			return false;
		}
		else
		{
			// Check-out succeeded, push the new record id into the session.
			$app->setUserState($context.'.id',	$recordId);
			$app->setUserState($this->_context.'data', null);
			$this->setRedirect('index.php?option='.$this->_option.'&view='.$this->_view_item.'&layout=edit');
			return true;
		}
	}

	/**
	 * Method to cancel an edit
	 *
	 * @return	void
	 * @since	1.0
	 */
	public function cancel()
	{
		JRequest::checkToken() or jexit(JText::_('JInvalid_Token'));

		// Initialize variables.
		$app		= &JFactory::getApplication();
		$model		= &$this->getModel();
		$checkin	= method_exists($model, 'checkin');
		$context	= "$this->_option.edit.$this->_context";

		// Get the record id.
		$recordId = (int) $app->getUserState($context.'.id');

		// Attempt to check-in the current record.
		if ($checkin && $recordId)
{
			if (!$model->checkin($recordId))
			{
				// Check-in failed, go back to the record and display a notice.
				$message = JText::sprintf('JError_Checkin_failed', $model->getError());
				$this->setRedirect('index.php?option='.$this->_option.'&view='.$this->_view_item.'&layout=edit', $message, 'error');
				return false;
			}
		}

		// Clean the session data and redirect.
		$app->setUserState($context.'.id',		null);
		$app->setUserState($context.'.data',	null);
		$this->setRedirect(JRoute::_('index.php?option='.$this->_option.'&view=='.$this->_view_list, false));
	}

	/**
	 * Method to save a record.
	 *
	 * @return	void
	 * @since	1.0
	 */
	public function save()
	{
		// Check for request forgeries.
		JRequest::checkToken() or jexit(JText::_('JInvalid_Token'));

		// Initialize variables.
		$app		= &JFactory::getApplication();
		$model		= $this->getModel();
		$data		= JRequest::getVar('jform', array(), 'post', 'array');
		$checkin	= method_exists($model, 'checkin');
		$context	= "$this->_option.edit.$this->_context";

		// Validate the posted data.
		$form	= &$model->getForm();
		if (!$form)
		{
			JError::raiseError(500, $model->getError());
			return false;
		}
		$data	= $model->validate($form, $data);

		// Check for validation errors.
		if ($data === false)
		{
			// Get the validation messages.
			$errors	= $model->getErrors();

			// Push up to three validation messages out to the user.
			for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++)
			{
				if (JError::isError($errors[$i])) {
					$app->enqueueMessage($errors[$i]->getMessage(), 'notice');
				}
				else {
					$app->enqueueMessage($errors[$i], 'notice');
				}
			}

			// Save the data in the session.
			$app->setUserState($context.'data', $data);

			// Redirect back to the edit screen.
			$this->setRedirect(JRoute::_('index.php?option='.$this->_option.'&view='.$this->_view_item.'&layout=edit', false));
			return false;
		}

		// Attempt to save the record.
		$return = $model->save($data);

		if ($return === false)
		{
			// Save failed, go back to the record and display a notice.
			$message = JText::sprintf('JError_Save_failed', $model->getError());
			$this->setRedirect('index.php?option='.$this->_option.'&view='.$this->_view_item.'&layout=edit', $message, 'error');
			return false;
		}

		// Save succeeded, check-in the record.
		if ($checkin && !$model->checkin())
		{
			// Check-in failed, go back to the record and display a notice.
			$message = JText::sprintf('JError_Checkin_saved', $model->getError());
			$this->setRedirect('index.php?option='.$this->_option.'&view='.$this->_view_item.'&layout=edit', $message, 'error');
			return false;
		}

		$this->setMessage(JText::_('JController_Save_success'));

		// Redirect the user and adjust session state based on the chosen task.
		switch ($this->_task)
		{
			case 'apply':
				// Set the record data in the session.
				$app->setUserState($context.'.id',		$model->getState($this->_content.'.id'));
				$app->setUserState($context.'.data',	null);

				// Redirect back to the edit screen.
				$this->setRedirect(JRoute::_('index.php?option='.$this->_option.'&view='.$this->_view_item.'&layout=edit', false));
				break;

			case 'save2new':
				// Clear the record id and data from the session.
				$app->setUserState($context.'.id', null);
				$app->setUserState($context.'.data', null);

				// Redirect back to the edit screen.
				$this->setRedirect(JRoute::_('index.php?option='.$this->_option.'&view='.$this->_view_item.'&layout=edit', false));
				break;

			default:
				// Clear the record id and data from the session.
				$app->setUserState($context.'.id', null);
				$app->setUserState($context.'.data', null);

				// Redirect to the list screen.
				$this->setRedirect(JRoute::_('index.php?option='.$this->_option.'&view='.$this->_view_list, false));
				break;
		}
	}
}