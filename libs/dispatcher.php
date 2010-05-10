<?php
require_once _LIBS . DIRECTORY_SEPARATOR . 'httpserver.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httpresponse.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httprequest.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'handler.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'handlers/core_handler.php';
class Dispatcher
{
	/**
	 * Config values. Normally defined in config.php and passed in on construction.
	 *
	 * @access private
	 * @var    array
	 */
	private $_config = array();

	/**
	 * Open connections waiting for some data.
	 *
	 * @access private
	 * @var    SplObjectStorage
	 */
	private $_listeners;

	/**
	 * The server that is running this dispatcher. Having a reference to the server
	 * allows us to control the server with HTTP requests.
	 *
	 * @access private
	 * @var    HTTPServer
	 */
	private $_httpserver;

	/**
	 * Constructor. Sets the config values and the default timezone.
	 *
	 * @access public
	 * @param  array  $config
	 * @return void
	 */
	public function __construct($config)
	{
		$this->_config = $config;
		date_default_timezone_set($config['DEFAULT_TIMEZONE']);

		$this->_listeners = array();
	}

	/**
	 * Sets the http server instance.
	 *
	 * @access public
	 * @param  HTTPServer $server
	 * @return void
	 */
	public function set_server(HTTPServer $server)
	{
		$this->_httpserver = $server;

		// Now that we have a server, we have a reason to start our notify listeners
		// timeouts. We will notify listeners every second.
		$this->_httpserver->get_loop()->add_timeout(microtime(true) + .002, array($this, 'notify_listeners'));
	}

	/**
	 * Dispatches an incoming request to the appropriate handler.
	 *
	 * @access public
	 * @param  HTTPRequest $request
	 * @return void
	 */
	public function dispatch(HTTPRequest $request)
	{
		// Respond with something for an OPTIONS request.
		if ($request->get_method() == 'OPTIONS')
		{
			$this->_options($request);
			return;
		}

		// Figure out which handler should be called.
		$handler_class = $request->get_handler_class() . '_Handler';

		// If there is no handler, send back a 404.
		if (!class_exists($handler_class) &&
		    !@file_exists($this->_config['HANDLER_PATH'] . strtolower($handler_class) . '.php') &&
		    !@file_exists(_LIBS . DIRECTORY_SEPARATOR . 'handlers/' . strtolower($handler_class) . '.php')
		    )
		{
			// Default to the static handler.
			$request->set_uri('/static/get' . $request->get_uri());
			$handler_class = 'Static_Handler';

			// Include the handler.
			require_once _LIBS . DIRECTORY_SEPARATOR . 'handlers/static_handler.php';
		}
		else
		{
			// Include the handler.
			if (@file_exists(_LIBS . DIRECTORY_SEPARATOR . 'handlers/' . strtolower($handler_class) . '.php'))
			{
				require_once _LIBS . DIRECTORY_SEPARATOR . 'handlers/' . strtolower($handler_class) . '.php';
			}
			else
			{
				require_once $this->_config['HANDLER_PATH'] . strtolower($handler_class) . '.php';
			}
		}

		// Make sure the requested method exists.
		$method = $request->get_handler_method();
		if (empty($method) || !method_exists($handler_class, $method))
		{
			// No good. Get this clown out of here.
			$this->_four_o_four($request);
			return;
		}

		$handler = new $handler_class($request, $this);

		// Handle the request.
		call_user_func(array($handler, $method));

		// Clean up dead listeners.
		if ($this->_config['CLEANUP_INTERVAL'] > 0)
		{
			$this->timeout_listeners();
		}
	}

	/**
	 * logs the request and some of the response data to the log file.
	 *
	 * @access public
	 * @param  HTTPRequest  $request
	 * @param  HTTPResponse $response
	 * @return void
	 */
	public function log(HTTPRequest $request, HTTPResponse $response)
	{
		// Silently fail if the log file is not writeable.
		if (!@is_writeable($this->_config['LOG_FILE']))
		{
			return;
		}

		// Create the log entry.
		$entry = $request->get_remote_ip() . "\t" . date('c') . "\t";
		$entry.= $request->get_uri() . "\t" . $response->get_status() . "\t";
		$entry.= mb_strlen($response->get_body(), 'UTF-8') . "\t";
		$entry.= $request->get_connection()->processing_time();

		// Add user agent if we have one.
		if ($request->get_headers()->get('User-Agent'))
		{
			$entry.= "\t" . $request->get_headers()->get('User-Agent');
		}

		// Write the data.
		file_put_contents($this->_config['LOG_FILE'], $entry . "\r\n", FILE_APPEND);
	}

	/**
	 * Sends back a 404 message.
	 *
	 * @access public
	 * @param  HTTPRequest $request
	 * @return void
	 */
	private function _four_o_four(HTTPRequest $request)
	{
		$handler = new Core_Handler($request, $this);
		$handler->four_o_four();
	}

	/**
	 * Returns a response for an OPTIONS request. We allow pretty much anything.
	 *
	 * @access public
	 * @return void
	 */
	public function _options(HTTPRequest $request)
	{
		$handler = new Core_Handler($request, $this);
		$handler->options();
	}

	/**
	 * Adds a new listener to the set of open connections.
	 *
	 * @access public
	 * @param  Handler $handler
	 * @return void
	 */
	public function add_listener(Handler $handler)
	{
		$this->_listeners[spl_object_hash($handler)] = $handler;
	}

	/**
	 * Removes a listener from the set of open connections.
	 *
	 * @access public
	 * @return void
	 */
	public function remove_listener(Handler $handler)
	{
		unset($this->_listeners[spl_object_hash($handler)]);
	}

	/**
	 * Returns all the open connections patiently waiting for some data.
	 *
	 * @access public
	 * @return SplObjectStorage
	 */
	public function get_listeners()
	{
		return $this->_listeners;
	}

	/**
	 * Notifies all listeners of a new event.
	 *
	 * @access public
	 * @return void
	 */
	public function notify_listeners(Handler $handler = null)
	{
		foreach ($this->_listeners as $listener)
		{
			$listener->process_event($handler);
		}

		// Put this call back in the loop.
		$this->_httpserver->get_loop()->add_timeout(microtime(true) + .002, array($this, 'notify_listeners'));
	}

	/**
	 * Timeout all listeners.
	 *
	 * @access public
	 * @return void
	 */
	public function timeout_listeners()
	{
		$response = new HTTPResponse(408);
		$response->set_body('Server shutting down', false);

		foreach ($this->_listeners as $listener)
		{
			if ($listener->get_request()->request_time() > $this->_config['CLEANUP_INTERVAL'])
			{
				$listener->write($response);
			}
		}
	}

	/**
	 * Closes all listeners.
	 *
	 * @access public
	 * @return void
	 */
	public function close_listeners()
	{
		$response = new HTTPResponse(503);
		$response->set_body('Server shutting down', false);

		foreach ($this->_listeners as $listener)
		{
			$listener->write($response);
		}
	}

	/**
	 * Returns the array of config values.
	 *
	 * @access public
	 * @return array
	 */
	public function get_config()
	{
		return $this->_config;
	}

	/**
	 * Sets the array of config values.
	 *
	 * @access public
	 * @param  array  $config
	 * @return void
	 */
	public function set_config(array $config)
	{
		$this->_config = $config;
	}

	/**
	 * Returns the server instance.
	 *
	 * @access public
	 * @return HTTPServer
	 */
	public function get_server()
	{
		return $this->_httpserver;
	}
}
?>