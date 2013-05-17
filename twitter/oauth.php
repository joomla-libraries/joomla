<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Twitter
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die();
jimport('joomla.environment.response');


/**
 * Joomla Platform class for generating Twitter API access token.
 *
 * @package     Joomla.Platform
 * @subpackage  Twitter
 *
 * @since       12.3
 */
class JTwitterOAuth
{
	/**
	* @var array  Contains consumer key and secret for the Twitter application.
	* @since 12.3
	*/
	protected $consumer = array();

	/**
	 * @var array  Contains user's id and screen name.
	 * @since 12.3
	 */
	protected $user = array();

	/**
	 * @var array  Contains access token key, secret and verifier.
	 * @since 12.3
	 */
	protected $token = array();

	/**
	* @var string  Callback URL for the Twitter application.
	* @since 12.3
	*/
	protected $callback_url;

	/**
	 * @var    JTwitterHttp  The HTTP client object to use in sending HTTP requests.
	 * @since  12.3
	 */
	protected $client;

	/**
	 * @var string  The access token URL
	 * @since 12.3
	 */
	protected $accessTokenURL = 'https://api.twitter.com/oauth/access_token';

	/**
	 * @var string  The authenticate URL
	 * @since 12.3
	 */
	protected $authenticateURL = 'https://api.twitter.com/oauth/authenticate';

	/**
	 * @var string  The authorise URL
	 * @since 12.3
	 */
	protected $authoriseURL = 'https://api.twitter.com/oauth/authorize';

	/**
	 * @var string  The request token URL
	 * @since 12.3
	 */
	protected $requestTokenURL = 'https://api.twitter.com/oauth/request_token';

	/**
	 * Constructor.
	 *
	 * @param   string        $consumer_key     Twitter consumer key.
	 * @param   string        $consumer_secret  Twitter consumer secret.
	 * @param   string        $callback_url     Twitter calback URL.
	 * @param   JTwitterHttp  $client           The HTTP client object.
	 *
	 * @since 12.3
	 */
	public function __construct($consumer_key, $consumer_secret, $callback_url, JTwitterHttp $client = null)
	{
		$this->consumer = array('key' => $consumer_key, 'secret' => $consumer_secret);
		$this->callback_url = $callback_url;
		$this->client = isset($client) ? $client : new JTwitterHttp($this->options);
	}

	/**
	 * Method to for the oauth flow.
	 *
	 * @return void
	 *
	 * @since  12.3
	 *
	 * @throws DomainException
	 */
	public function oauth()
	{
		$session = JFactory::getSession();

		// Already got some credentials stored
		if ($session->get('key', null, 'oauth_token'))
		{
			// Get token form session.
			$this->token = array('key' => $session->get('key', null, 'oauth_token'), 'secret' => $session->get('secret', null, 'oauth_token'));

			$response = $this->verifyCredentials();
			if ($response->code == 200)
			{
				return;
			}
			else
			{
				$this->token = null;
			}
		}

		$request = JFactory::getApplication()->input;
		$verifier = $request->get('oauth_verifier');

		if (empty($verifier))
		{
			// Generate a request token.
			$this->generateRequestToken();

			// Authenticate the user.
			$this->authorise();
		}
		// Callback from Twitter.
		else
		{
			$session = JFactory::getSession();

			// Get token form session.
			$this->token = array('key' => $session->get('key', null, 'oauth_token'), 'secret' => $session->get('secret', null, 'oauth_token'));

			// Verify the returned request token.
			if ($this->token['key'] != $request->get('oauth_token'))
			{
				throw new DomainException('Bad session!');
			}

			// Set token verifier.
			$this->token['verifier'] = $request->get('oauth_verifier');

			// Generate access token.
			$this->generateAccessToken();
		}
	}

	/**
	 * Method used to get a request token.
	 *
	 * @return void
	 *
	 * @since  12.3
	 * @throws  DomainException
	 */
	public function generateRequestToken()
	{
		// Set the parameters.
		$parameters = array(
			'oauth_token' => $this->consumer['key']
		);

		// Make an OAuth request for the Request Token.
		$response = $this->oauthRequest($this->requestTokenURL, 'POST', $parameters);

		parse_str($response->body, $params);
		if ($params['oauth_callback_confirmed'] == true)
		{
			// Save the request token.
			$this->token = array('key' => $params['oauth_token'], 'secret' => $params['oauth_token_secret']);

			// Save the request token in session
			$session = JFactory::getSession();
			$session->set('key', $this->token['key'], 'oauth_token');
			$session->set('secret', $this->token['secret'], 'oauth_token');
		}
		else
		{
			throw new DomainException('Bad request token!');
		}
	}

	/**
	 * Method used to authenticate the user.
	 *
	 * @return void
	 *
	 * @since  12.3
	 */
	public function authenticate()
	{
		$url = $this->authenticateURL . '?oauth_token=' . $this->token['key'];
		JResponse::setHeader('Location', $url, true);
		JResponse::sendHeaders();
	}

	/**
	 * Method used to authorise the application.
	 *
	 * @return void
	 *
	 * @since  12.3
	 */
	public function authorise()
	{
		$url = $this->authoriseURL . '?oauth_token=' . $this->token['key'];
		JResponse::setHeader('Location', $url, true);
		JResponse::sendHeaders();
	}

	/**
	 * Method used to get an access token.
	 *
	 * @return void
	 *
	 * @since  12.3
	 */
	public function generateAccessToken()
	{
		// Set the parameters.
		$parameters = array(
			'oauth_verifier' => $this->token['verifier'],
			'oauth_token' => $this->token['key']
		);

		$response = $this->oauthRequest($this->accessTokenURL, 'POST', $parameters);

		parse_str($response->body, $params);

		// Save the access token.
		$this->token = array('key' => $params['oauth_token'], 'secret' => $params['oauth_token_secret']);

		// Save the request token in session
		$session = JFactory::getSession();
		$session->set('key', $this->token['key'], 'oauth_token');
		$session->set('secret', $this->token['secret'], 'oauth_token');

		$this->user = array('id' => $params['user_id'], 'screen_name' => $params['screen_name']);
	}

	/**
	 * Method used to make an OAuth request.
	 *
	 * @param   string  $url          The request URL.
	 * @param   string  $method       The request method.
	 * @param   array   &$parameters  Array containing request parameters.
	 * @param   array   $data         The POST request data.
	 * @param   array   $headers      An array of name-value pairs to include in the header of the request
	 *
	 * @return  object  The JHttpResponse object.
	 *
	 * @since 12.3
	 * @throws  DomainException
	 */
	public function oauthRequest($url, $method, &$parameters, $data = array(), $headers = array())
	{
		// Set the parameters.
		$defaults = array(
			'oauth_callback' => $this->callback_url,
			'oauth_consumer_key' => $this->consumer['key'],
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_version' => '1.0',
			'oauth_nonce' => $this->generateNonce(),
			'oauth_timestamp' => time()
		);

		$parameters = array_merge($parameters, $defaults);

		if ($data)
		{
			// Do not encode multipart parameters.
			if (empty($headers))
			{
				// Use all parameters for the signature.
				$oauth_headers = array_merge($parameters, $data);
			}
			else
			{
				$oauth_headers = $parameters;
			}

			// Sign the request.
			$this->signRequest($url, $method, $oauth_headers);

			// Get parameters for the Authorisation header.
			$oauth_headers = array_diff_key($oauth_headers, $data);
		}
		else
		{
			$oauth_headers = $parameters;

			// Sign the request.
			$this->signRequest($url, $method, $oauth_headers);
		}

		// Send the request.
		switch ($method)
		{
			case 'GET':
				$url = $this->to_url($url, $data);
				$response = $this->client->get($url, array('Authorization' => $this->createHeader($oauth_headers)));
				break;
			case 'POST':
				$headers = array_merge($headers, array('Authorization' => $this->createHeader($oauth_headers)));
				$response = $this->client->post($url, $data, $headers);
				break;
		}

		// Validate the response code.
		if (strpos($url, 'verify_credentials') === false && $response->code != 200)
		{
			$error = json_decode($response->body);

			if (property_exists($error, 'error'))
			{
				throw new DomainException($error->error);
			}
			else
			{
				$error = $error->errors;
				throw new DomainException($error[0]->message, $error[0]->code);
			}
		}

		return $response;
	}

	/**
	 * Method used to create the header for the POST request.
	 *
	 * @param   array  &$parameters  Array containing request parameters.
	 *
	 * @return  string  The header.
	 *
	 * @since 12.3
	 */
	public function createHeader(&$parameters)
	{
		$header = 'OAuth ';

		foreach ($parameters as $key => $value)
		{
			if (!strcmp($header, 'OAuth '))
			{
				$header .= $key . '="' . $this->safeEncode($value) . '"';
			}
			else
			{
				$header .= ', ' . $key . '="' . $value . '"';
			}
		}

		return $header;
	}

	/**
	 * Method to create the URL formed string with the parameters.
	 *
	 * @param   string  $url          The request URL.
	 * @param   array   &$parameters  Array containing request parameters.
	 *
	 * @return  string  The formed URL.
	 *
	 * @since  12.3
	 */
	public function to_url($url, &$parameters)
	{
		foreach ($parameters as $key => $value)
		{
			if (strpos($url, '?') === false)
			{
				$url .= '?' . $key . '=' . $value;
			}
			else
			{
				$url .= '&' . $key . '=' . $value;
			}
		}

		return $url;
	}

	/**
	 * Method used to sign requests.
	 *
	 * @param   string  $url          The URL to sign.
	 * @param   string  $method       The request method.
	 * @param   array   &$parameters  Array containing request parameters.
	 *
	 * @return  void
	 *
	 * @since   12.3
	 */
	public function signRequest($url, $method, &$parameters)
	{
		// Create the signature base string.
		$base = $this->baseString($url, $method, $parameters);

		$parameters['oauth_signature'] = $this->safeEncode(
			base64_encode(
				hash_hmac('sha1', $base, $this->prepare_signing_key(), true)
				)
			);
	}

	/**
	 * Prepare the signature base string.
	 *
	 * @param   string  $url          The URL to sign.
	 * @param   string  $method       The request method.
	 * @param   array   &$parameters  Array containing request parameters.
	 *
	 * @return string  The base string.
	 *
	 * @since 12.3
	 */
	private function baseString($url, $method, &$parameters)
	{
		// Sort the parameters alphabetically
		uksort($parameters, 'strcmp');

		// Encode parameters.
		foreach ($parameters as $key => $value)
		{
			$key = $this->safeEncode($key);
			$value = $this->safeEncode($value);
			$kv[] = "{$key}={$value}";
		}
		// Form the parameter string.
		$params = implode('&', $kv);

		// Signature base string elements.
		$base = array(
			$method,
			$url,
			$params
			);

		// Return the base string.
		return implode('&', $this->safeEncode($base));
	}

	/**
	 * Encodes the string or array passed in a way compatible with OAuth.
	 * If an array is passed each array value will will be encoded.
	 *
	 * @param   mixed  $data  The scalar or array to encode.
	 *
	 * @return  string  $data encoded in a way compatible with OAuth.
	 *
	 * @since 12.3
	 */
	public function safeEncode($data)
	{
		if (is_array($data))
		{
			return array_map(array($this, 'safeEncode'), $data);
		}
		elseif (is_scalar($data))
		{
			return str_ireplace(
				array('+', '%7E'),
				array(' ', '~'),
				rawurlencode($data)
				);
		}
		else
		{
			return '';
		}
	}

	/**
	 * Method used to generate the current nonce.
	 *
	 * @return  string  The current nonce.
	 *
	 * @since 12.3
	 */
	public static function generateNonce()
	{
		$mt = microtime();
		$rand = mt_rand();

		// The md5s look nicer than numbers.
		return md5($mt . $rand);
	}

	/**
	 * Prepares the OAuth signing key.
	 *
	 * @return string  The prepared signing key.
	 *
	 * @since 12.3
	 */
	private function prepare_signing_key()
	{
		return $this->safeEncode($this->consumer['secret']) . '&' . $this->safeEncode(($this->token) ? $this->token['secret'] : '');
	}

	/**
	 * Returns an HTTP 200 OK response code and a representation of the requesting user if authentication was successful;
	 * returns a 401 status code and an error message if not.
	 *
	 * @param   boolean  $entities     When set to either true, t or 1, each tweet will include a node called "entities,". This node offers a
	 * 								   variety of metadata about the tweet in a discreet structure, including: user_mentions, urls, and hashtags.
	 * @param   boolean  $skip_status  When set to either true, t or 1 statuses will not be included in the returned user objects.
	 *
	 * @return  array  The decoded JSON response
	 *
	 * @since   12.3
	 */
	public function verifyCredentials($entities = false, $skip_status = false)
	{
		// Set the parameters.
		$parameters = array('oauth_token' => $this->getToken('key'));

		$data = array();

		// Check if entities is specified
		if ($entities)
		{
			$data['include_entities'] = $entities;
		}

		// Check if skip_statuses is specified
		if ($skip_status)
		{
			$data['skip_status'] = $skip_status;
		}

		// Set the API base
		$path = 'https://api.twitter.com/1/account/verify_credentials.json';

		// Send the request.
		$response = $this->oauthRequest($path, 'GET', $parameters, $data);
		return $response;
	}

	/**
	 * Ends the session of the authenticating user, returning a null cookie.
	 *
	 * @return  array  The decoded JSON response
	 *
	 * @since   12.3
	 */
	public function endSession()
	{
		// Set parameters.
		$parameters = array('oauth_token' => $this->getToken('key'));

		// Set the API base
		$path = 'https://api.twitter.com/1/account/end_session.json';

		// Send the request.
		$response = $this->oauthRequest($path, 'POST', $parameters);
		return json_decode($response->body);
	}

	/**
	 * Get the current user id and screen name.
	 *
	 * @return  array  The user array.
	 *
	 * @since   12.3
	 */
	public function getUser()
	{
		return $this->user;
	}

	/**
	 * Get the oauth token key or secret.
	 *
	 * @param   string  $key  The array key.
	 *
	 * @return  array  The oauth token key and secret.
	 *
	 * @since   12.3
	 */
	public function getToken($key)
	{
		return $this->token[$key];
	}

	/**
	 * Set the oauth token.
	 *
	 * @param   string  $key     The token key to set.
	 * @param   string  $secret  The token value to set.
	 *
	 * @return  void
	 *
	 * @since   12.3
	 */
	public function setToken($key, $secret)
	{
		$this->token = array('key' => $key, 'secret' => $secret);
	}
}
