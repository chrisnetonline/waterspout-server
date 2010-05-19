<?php
/**
 * This file is part of WaterSpout.
 *
 * WaterSpout is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * WaterSpout is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with WaterSpout.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package WaterSpout
 * @author  Scott Mattocks <scott@crisscott.com>
 * @author  Chris Lewis <chris@chrisnetonline.com>
 * @license http://www.gnu.org/licenses/lgpl.html LGPL License 3
 * @version SVN: $Id$
 * @link    http://www.spoutserver.com/
 */

require_once _LIBS . DIRECTORY_SEPARATOR . 'httpserver.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httpresponse.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httprequest.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'controller.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'controllers/core_controller.php';
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
	 * The available controllers.
	 *
	 * @access private
	 * @var    array
	 */
	private $_controllers = array();

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

		// Build a list of controllers and methods.
		$dir = dir(_LIBS . DIRECTORY_SEPARATOR . 'controllers');
		while (($file = $dir->read()) !== false)
		{
			if (substr($file, -15) == '_controller.php')
			{
				require_once $dir->path . DIRECTORY_SEPARATOR .$file;
				$controller_class = substr($file, 0, -4);
				$this->_controllers[] = $controller_class;
			}
		}
		$dir->close();
		$dir = dir($this->_config['CONTROLLER_PATH']);
		while (($file = $dir->read()) !== false)
		{
			if (substr($file, -15) == '_controller.php')
			{
				require_once $dir->path . DIRECTORY_SEPARATOR .$file;
				$controller_class = substr($file, 0, -4);
				$this->_controllers[] = $controller_class;
			}
		}
		$dir->close();
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
	 * Dispatches an incoming request to the appropriate controller.
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

		// Figure out which controller should be called.
		$controller_class = $request->get_controller_class() . '_controller';
		if (empty($controller_class) ||
		    !in_array(strtolower($controller_class), $this->_controllers)
		    )
		{
			// Default to the file controller.
			$request->set_uri('/file/get' . $request->get_uri());
			$controller_class = 'File_Controller';
		}

		// Make sure the requested method exists.
		$method = $request->get_controller_method();
		if (empty($method) || !method_exists($controller_class, $method))
		{
			// No good. Get this clown out of here.
			$this->_four_o_four($request);
			return;
		}

		$controller = new $controller_class($request, $this);

		// Handle the request.
		call_user_func(array($controller, $method));

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
		$controller = new Core_Controller($request, $this);
		$controller->four_o_four();
	}

	/**
	 * Returns a response for an OPTIONS request. We allow pretty much anything.
	 *
	 * @access public
	 * @return void
	 */
	public function _options(HTTPRequest $request)
	{
		$controller = new Core_Controller($request, $this);
		$controller->options();
	}

	/**
	 * Adds a new listener to the set of open connections.
	 *
	 * @access public
	 * @param  Controller $controller
	 * @return void
	 */
	public function add_listener(Controller $controller)
	{
		$this->_listeners[spl_object_hash($controller)] = $controller;
	}

	/**
	 * Removes a listener from the set of open connections.
	 *
	 * @access public
	 * @return void
	 */
	public function remove_listener(Controller $controller)
	{
		unset($this->_listeners[spl_object_hash($controller)]);
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
	public function notify_listeners(Controller $controller = null)
	{
		foreach ($this->_listeners as $listener)
		{
			$listener->process_event($controller);
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
		$response->set_body('Request Timeout', false);

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