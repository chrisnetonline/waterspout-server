<?php
class Core_Handler extends Handler
{
	/**
	 * Just performs a handshake.
	 *
	 * @access public
	 * @return void
	 */
	public function handshake()
	{
		// Nothing to see here. Move along.
	}

	/**
	 * Creates a 404 response and writes it to the connection.
	 *
	 * @access public
	 * @return void
	 */
	public function four_o_four()
	{
		$response = new HTTPResponse(404);

		// Set the custom message if we have one.
		$config = $this->dispatcher->get_config();
		if (isset($config['404_MESSAGE']))
		{
			$response->set_body($config['404_MESSAGE'], false);
		}
		else
		{
			$response->set_body('Page not found', false);
		}

		$this->write($response);
	}

	/**
	 * Creates an options response and writes it to the connection.
	 *
	 * @access public
	 * @return void
	 */
	public function options()
	{
		$response = new HTTPResponse(200);

		$headers = array('Allow'                            => 'GET,POST,OPTIONS',
		                 'Content-Length'                   => 0,
		                 'Content-Type'                     => 'text/plain; charset=utf-8',
		                 'Access-Control-Allow-Methods'     => 'GET,POST,OPTIONS',
						 'Access-Control-Allow-Headers'     => 'x-prototype-version,x-requested-with,waterspout-request',
						 'Access-Control-Allow-Credentials' => 'true'
		                 );
		$response->add_headers($headers);

		$config = $this->dispatcher->get_config();

		if (isset($config['ACCESS_CONTROL_ALLOW_ORIGIN']))
		{
			$response->add_header('Access-Control-Allow-Origin', $config['ACCESS_CONTROL_ALLOW_ORIGIN']);
		}
		else
		{
			$response->add_header('Access-Control-Allow-Origin', $this->request->get_headers()->get('Origin'));
		}

		$this->write($response);
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