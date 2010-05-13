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

require_once _LIBS . DIRECTORY_SEPARATOR . 'ioloop.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'iostream.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httpconnection.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httprequest.php';
/**
 * A non-blocking, single-threaded HTTP server.
 *
 * @package Waterspout
 * @author  Scott Mattocks
 */
class HTTPServer
{
	/**
 	 * Callback to be called when a request is made.
	 *
	 * @access private
	 * @var    mixed
	 */
	private $_request_callback;

	/**
 	 * The IOLoop.
	 *
	 * @access private
	 * @var    IOLoop
	 */
	private $_loop;

	/**
	 * The IOStreams used for getting incoming requests.
	 *
	 * @access private
	 * @var    array
	 */
	private $_sockets = array();

	/**
 	 * Configuration settings.
	 *
	 * @access private
	 * @var    array
	 */
	private $_config = array();

	/**
	 * Flag indicating the server needs to restart.
	 *
	 * @access private
	 * @var    boolean
	 */
	private $_restart = false;

	/**
	 * A white list of acceptable IP addresses.
	 *
	 * @access private
	 * @var    array
	 */
	private $_ip_whitelist = array();

	/**
	 * A black list of unacceptable IP addresses.
	 *
	 * @access private
	 * @var    array
	 */
	private $_ip_blacklist = array();

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param  mixed  $request_callback
	 * @param  IOLoop $io_loop
	 * @param  array  $config
	 * @return void
	 */
	public function __construct($request_callback, IOLoop $io_loop, array $config)
	{
		$this->_request_callback = $request_callback;
		$this->_loop             = $io_loop;
		$this->_config           = $config;

		// Check for a white list of IP addresses.
		if (isset($this->_config['IP_WHITELIST']) && @file_exists($this->_config['IP_WHITELIST']))
		{
			$whitelist = include_once $this->_config['IP_WHITELIST'];
			if (is_array($whitelist) && !empty($whitelist))
			{
				$this->_ip_whitelist = $whitelist;
			}
		}

		// Check for a black list of IP addresses.
		if (empty($this->_ip_whitelist) && isset($this->_config['IP_BLACKLIST']) && @file_exists($this->_config['IP_BLACKLIST']))
		{
			$blacklist = include_once $this->_config['IP_BLACKLIST'];
			if (is_array($blacklist) && !empty($blacklist))
			{
				$this->_ip_blacklist = $blacklist;
			}
		}

		// Add a profiling if the config calls for it.
		if (!empty($this->_config['TIMING']) && function_exists('xhprof_enable'))
		{
			xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY + XHPROF_FLAGS_NO_BUILTINS);
		}
	}

	/**
	 * Sets up the socket and listens for new requests.
	 *
	 * @access public
	 * @return void
	 */
	public function listen()
	{
		// Make sure we have the values we need for connecting.
		if (empty($this->_config['SERVER_PORT']) ||
		    !is_array($this->_config['SERVER_PORT'])
		    )
		{
			throw new RuntimeException('Ports must be defined in the configuration before running the server.');
		}

		if (empty($this->_config['SERVER_ADDRESS']))
		{
			throw new RuntimeException('The server address must be defined in the configuration before running the server.');
		}

		// Create new sockets for all the ports we are listening on.
		foreach ($this->_config['SERVER_PORT'] as $port)
		{
			$tcp = stream_socket_server('tcp://' . $this->_config['SERVER_ADDRESS'] . ':' . $port);

			if ($tcp === false)
			{
				// Could not open on the port or IP address.
				continue;
			}

			// Tell the loop what to do with new requests.
			$this->_loop->add_handler($tcp, array($this, 'handle_events'), IOLoop::READ);

			$this->_sockets[] = $tcp;
		}

		if (!empty($this->_config['SSL_SERVER_PORT']) &&
		    is_array($this->_config['SSL_SERVER_PORT'])
		    )
		{
			$context = stream_context_create();
			stream_context_set_option($context, 'ssl', 'local_cert', $this->_config['SSL_CERT_PATH']);
			stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
			stream_context_set_option($context, 'ssl', 'verify_peer', false);

			foreach ($this->_config['SSL_SERVER_PORT'] as $port)
			{
				$ssl = stream_socket_server('ssl://' . $this->_config['SERVER_ADDRESS'] . ':' . $port, $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);

				// Tell the loop what to do with new requests.
				$this->_loop->add_handler($ssl, array($this, 'handle_events'), IOLoop::READ);

				$this->_sockets[] = $ssl;
			}
		}

		if (!count($this->_sockets))
		{
			throw new RuntimeException('The server was unable to open any listening connections.');
		}
	}

	/**
	 * Shuts down the socket server.
	 *
	 * @access public
	 * @return void
	 */
	public function close()
	{
		// Close the socket connection.
		foreach ($this->_sockets as $socket)
		{
			@socket_close($socket);
		}

		// Check if we need to restart.
		if ($this->_restart)
		{
			$cmd = 'php ' . join(' ', $GLOBALS['argv']);
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
			{
				die(pclose(popen('start ' . $cmd, 'r')));
			}
			else
			{
				die(exec($cmd . ' > /dev/null &'));
			}
		}
	}

	/**
	 * Callback for handling new requests.
	 *
	 * @access public
	 * @param  resource $socket
	 * @return void
	 */
	public function handle_events($socket)
	{
		// Accept the new requests.
		$connection = stream_socket_accept($socket, 0, $address);

		if (!is_resource($connection))
		{
			return;
		}

		// Check for white/black list matching.
		if (!$this->_whitelisted($address) ||
		    $this->_blacklisted($address)
		    )
		{
			return;
		}

		// Open up a new stream to the client.
		$stream  = new IOStream($connection, $this->_loop);

		// Create a new connection.
		new HTTPConnection($this, $stream, $address, $this->_request_callback);
	}

	/**
	 * Returns a config value.
	 *
	 * @access public
	 * @return mixed
	 */
	public function config($key)
	{
		if (array_key_exists($key, $this->_config))
		{
			return $this->_config[$key];
		}

		return null;
	}

	/**
	 * Sets the config array.
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
	 * Returns the loop object.
	 *
	 * @access public
	 * @return IOLoop
	 */
	public function get_loop()
	{
		return $this->_loop;
	}

	/**
	 * Stops the main loop and collects profiling stats.
	 *
	 * @access public
	 * @param  float  $deadline
	 * @return void
	 */
	public function stop($deadline)
	{
		$this->get_loop()->add_timeout($deadline, array($this->get_loop(), 'stop'));

		// Stop profiling if necessary.
		if ($this->config('TIMING') && function_exists('xhprof_disable'))
		{
			$this->get_loop()->add_timeout($deadline, array($this, 'profiling_shutdown'));
		}
	}

	/**
	 * Shut down the timing because Chris uses an antique version of PHP.
	 *
	 * @access public
	 * @return void
	 */
	public function profiling_shutdown()
	{
		$data = xhprof_disable();
		require_once $this->config('XHPROF_ROOT') . '/xhprof_lib/utils/xhprof_lib.php';
		require_once $this->config('XHPROF_ROOT') . '/xhprof_lib/utils/xhprof_runs.php';
		$xhprof_runs = new XHProfRuns_Default();

		$xhprof_runs->save_run($data, 'waterspout');
	}

	/**
	 * Checks to see if the given address has been white listed.
	 *
	 * @access private
	 * @param  string  $address
	 * @return boolean
	 */
	private function _whitelisted($address)
	{
		if (empty($this->_ip_whitelist))
		{
			return true;
		}

		foreach ($this->_ip_whitelist as $ip)
		{
			if (strpos($address, $ip) === 0)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks to see if the given address has been black listed.
	 *
	 * @access private
	 * @param  string  $address
	 * @return boolean
	 */
	private function _blacklisted($address)
	{
		if (empty($this->_ip_blacklist))
		{
			return false;
		}

		foreach ($this->_ip_blacklist as $ip)
		{
			if (strpos($address, $ip) === 0)
			{
				return true;
			}
		}

		return false;
	}
}
?>