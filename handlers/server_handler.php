<?php
/**
 * A handler for controlling the server.
 *
 * @access public
 * @return void
 */
class Server_Handler extends Handler
{
	/**
	 * Checks to make sure the request came from an authorized IP address.
	 *
	 * @access private
	 * @return boolean
	 */
	private function _validate_request()
	{
		// Make sure the request came from an internal machine.
		return (strpos($this->request->get_remote_ip(), '127.0.0.') === 0 ||
		        strpos($this->request->get_remote_ip(), '192.168.') === 0 ||
		        strpos($this->request->get_remote_ip(), '10.10.')   === 0
		        );
	}

	/**
	 * Reloads the config file.
	 *
	 * @access public
	 * @return void
	 */
	public function reloadconfig()
	{
		if (!$this->_validate_request())
		{
			$response = $this->_403();
		}
		else
		{
			$old_config = $this->dispatcher->get_config();

			$config = include $old_config['CONFIG_PATH'];

			// Check for verbosity level from the command line.
			if (($verbose = $this->request->get_request_var('verbose')) != false)
			{
				$config['server']['VERBOSE']     = $verbose;
				$config['dispatcher']['VERBOSE'] = $verbose;
			}

			$this->dispatcher->set_config($config['dispatcher']);
			$this->dispatcher->get_server()->set_config($config['server']);

			$response = new HTTPResponse(200);
			$response->set_body('Server config reloaded.');
		}

		$this->write($response);
	}

	/**
	 * Shuts down the server.
	 *
	 * @access public
	 * @return void
	 */
	public function shutdown()
	{
		if (!$this->_validate_request())
		{
			$response = $this->_403();
			$this->write($response);
		}
		else
		{
			// Send back a success message. We may not be successful, but this is our
			// last chance to send anything.
			$response = new HTTPResponse(200);
			$response->set_body('Server shut down.');

			$this->write($response);

			// Close all listeners.
			$this->dispatcher->close_listeners();

			// Stop the server loop.
			$delay = 1;
			if ($this->request->get_request_var('delay'))
			{
				$delay+= $this->request->get_requst_var('delay');
			}

			$this->dispatcher->get_server()->stop(time() + $delay);
		}
	}

	/**
	 * Restarts the server.
	 *
	 * @access public
	 * @return void
	 */
	public function restart()
	{
		if (!$this->_validate_request())
		{
			$response = $this->_403();
			$this->write($response);
		}
		else
		{
			// Send back a success message. We may not be successful, but this is our
			// last chance to send anything.
			$response = new HTTPResponse(200);
			$response->set_body('Server restarting.');

			$this->write($response);

			// Close all listeners.
			$this->dispatcher->close_listeners();

			// Stop the server loop.
			$delay = 1;
			if ($this->request->get_request_var('delay'))
			{
				$delay+= $this->request->get_requst_var('delay');
			}

			// Set restart flag.
			$this->dispatcher->get_server()->restart = true;

			$this->dispatcher->get_server()->get_loop()->add_timeout(time() + $delay, array($this->dispatcher->get_server()->get_loop(), 'stop'));
		}
	}

	/**
	 * Reports the server's status.
	 *
	 * @access public
	 * @return void
	 */
	public function status()
	{
		if (!$this->_validate_request())
		{
			$response = $this->_403();
			$this->write($response);
		}
		else
		{
			$response = new HTTPResponse(200);

			$data = array('listeners'      => count($this->dispatcher->get_listeners()),
			              'peak_memory'    => memory_get_peak_usage(),
			              'current_memory' => memory_get_usage()
			              );

			$response->set_body($data);

			$this->write($response);
		}
	}

	/**
	 * Sends a forbidden response.
	 *
	 * @access private
	 * @return void
	 */
	private function _403()
	{
		$response = new HTTPResponse(403);

		$response->add_header('Date', date('D, d M Y H:i:s \G\M\T', time()));
		$response->set_body('Access denied', false);

		return $response;
	}

	/**
	 * Processes the given event.
	 *
	 * @access public
	 * @return void
	 */
	public function process_event(Handler $handler = null)
	{
		return;
	}
}
?>