<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Twitter
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die();

/**
 * Twitter API object class for the Joomla Platform.
 *
 * @package     Joomla.Platform
 * @subpackage  Twitter
 * @since       12.1
 */
abstract class JTwitterObject
{
	/**
	 * @var    JRegistry  Options for the Twitter object.
	 * @since  12.1
	 */
	protected $options;

	/**
	 * @var    JTwitterHttp  The HTTP client object to use in sending HTTP requests.
	 * @since  12.1
	 */
	protected $client;

	/**
	 * Constructor.
	 *
	 * @param   JRegistry     &$options  Twitter options object.
	 * @param   JTwitterHttp  $client    The HTTP client object.
	 *
	 * @since   12.1
	 */
	public function __construct(JRegistry &$options = null, JTwitterHttp $client = null)
	{
		$this->options = isset($options) ? $options : new JRegistry;
		$this->client = isset($client) ? $client : new JTwitterHttp($this->options);
	}

	/**
	 * Method to check the rate limit for the requesting IP address
	 *
	 * @return  void
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function checkRateLimit()
	{
		// Check the rate limit for remaining hits
		$rate_limit = $this->getRateLimit();

		if ($rate_limit->remaining_hits == 0)
		{
			// The IP has exceeded the Twitter API rate limit
			throw new RuntimeException('This server has exceed the Twitter API rate limit for the given period.  The limit will reset in '
						. $rate_limit->reset_time . 'seconds.'
			);
		}
	}

	/**
	 * Method to build and return a full request URL for the request.  This method will
	 * add appropriate pagination details if necessary and also prepend the API url
	 * to have a complete URL for the request.
	 *
	 * @param   string  $path        URL to inflect
	 * @param   array   $parameters  The parameters passed in the URL.
	 *
	 * @return  string  The request URL.
	 *
	 * @since   12.1
	 */
	protected function fetchUrl($path, $parameters = null)
	{
		if ($parameters)
		{
			foreach ($parameters as $key => $value)
			{
				if (strpos($path, '?') === false)
				{
					$path .= '?' . $key . '=' . $value;
				}
				else
				{
					$path .= '&' . $key . '=' . $value;
				}
			}
		}

		// Get a new JUri object fousing the api url and given path.
		$uri = new JUri($this->options->get('api.url') . $path);

		return (string) $uri;
	}

	/**
	 * Method to retrieve the rate limit for the requesting IP address
	 *
	 * @return  array  The JSON response decoded
	 *
	 * @since   12.1
	 */
	public function getRateLimit()
	{
		// Build the request path.
		$path = '/1/account/rate_limit_status.json';

		// Send the request.
		return $this->sendRequest($path);
	}

	/**
	 * Method to send the request.
	 *
	 * @param   string  $path        The path of the request to make
	 * @param   string  $method      The request method.
	 * @param   array   $parameters  The parameters passed in the URL.
	 * @param   mixed   $data        Either an associative array or a string to be sent with the post request.
	 *
	 * @return  array  The decoded JSON response
	 *
	 * @since   12.1
	 * @throws  DomainException
	 */
	public function sendRequest($path, $method='get', $parameters = null, $data='')
	{
		// Send the request.
		switch ($method)
		{
			case 'get':
				$response = $this->client->get($this->fetchUrl($path, $parameters));
				break;
			case 'post':
				$response = $this->client->post($this->fetchUrl($path, $parameters), $data);
				break;
		}

		// Validate the response code.
		if ($response->code != 200)
		{
			$error = json_decode($response->body);

			throw new DomainException($error->error, $response->code);
		}

		return json_decode($response->body);
	}

	/**
	 * Get an option from the JTwitterObject instance.
	 *
	 * @param   string  $key  The name of the option to get.
	 *
	 * @return  mixed  The option value.
	 *
	 * @since   12.1
	 */
	public function getOption($key)
	{
		return $this->options->get($key);
	}

	/**
	 * Set an option for the JTwitterObject instance.
	 *
	 * @param   string  $key    The name of the option to set.
	 * @param   mixed   $value  The option value to set.
	 *
	 * @return  JTwitterObject  This object for method chaining.
	 *
	 * @since   12.1
	 */
	public function setOption($key, $value)
	{
		$this->options->set($key, $value);

		return $this;
	}
}
