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

class Core_Controller extends Controller
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
	 * Stops the server cleanly.
	 *
	 * @access public
	 * @return void
	 */
	public function stop()
	{
		if (strpos($this->request->get_remote_ip(), '127.0.0.') === 0)
		{
			// Send back a success message. We may not be successful, but this is our
			// last chance to send anything.
			$response = new HTTPResponse(200);
			$response->set_body('Server shut down.');

			$this->write($response);

			// Close all listeners.
			$this->dispatcher->close_listeners();

			// Stop the server loop.
			$this->dispatcher->get_server()->stop(time() - 1);
		}
	}

	/**
	 * Processes the given event.
	 *
	 * @access public
	 * @return void
	 */
	public function process_event(Controller $controller = null)
	{
		return;
	}
}
?>