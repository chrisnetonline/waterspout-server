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

class Compare_Controller extends Controller
{
	/**
	 * The total number of WS requests made.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_ws_requests = array();

	/**
	 * The total number of long polling requests made.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_lp_requests = array();

	/**
	 * The total number of short polling requests made.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_sp_requests = array();

	/**
	 * The total number of bytes sent for WS.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_ws_bytes_sent = array();

	/**
	 * The total number of bytes sent for long polling.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_lp_bytes_sent = array();

	/**
	 * The total number of bytes sent for short polling.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_sp_bytes_sent = array();

	/**
	 * The total number of bytes received for WS.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_ws_bytes_received = array();

	/**
	 * The total number of bytes received for long polling.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_lp_bytes_received = array();

	/**
	 * The total number of bytes received for short polling.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_sp_bytes_received = array();

	/**
	 * Constructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct(HTTPRequest $request, Dispatcher $dispatcher)
	{
		// Call the parent constructor.
		parent::__construct($request, $dispatcher);

		// Log the request.
		$this->_log_request();
	}

	/**
	 * Reset stats.
	 *
	 * @access public
	 * @return void
	 */
	public function reset()
	{
		$ip = $this->request->get_remote_ip();

		self::$_ws_requests[$ip]       = 0;
		self::$_ws_bytes_received[$ip] = 0;
		self::$_ws_bytes_sent[$ip]     = 0;

		self::$_lp_requests[$ip]       = 0;
		self::$_lp_bytes_received[$ip] = 0;
		self::$_lp_bytes_sent[$ip]     = 0;

		self::$_sp_requests[$ip]       = 0;
		self::$_sp_bytes_received[$ip] = 0;
		self::$_sp_bytes_sent[$ip]     = 0;

		$response = new HTTPResponse(200);

		// Determine if this is a JSONP request.
		$body = array();
		if ($this->request->get_request_var('callback'))
		{
			$response->set_body($this->request->get_request_var('callback') . '(' . json_encode($body) . ');', false);
		}
		else
		{
			$response->set_body($body, true);
		}

		$this->write($response);
	}

	/**
	 * Handle websocket requests.
	 *
	 * @access public
	 * @return void
	 */
	public function websocket()
	{
		$ip = $this->request->get_remote_ip();

		$response = new HTTPResponse(200);

		// Determine if this is a JSONP request.
		$body = array('ws_requests'       => self::$_ws_requests[$ip],
					  'ws_bytes_received' => self::$_ws_bytes_received[$ip],
					  'ws_bytes_sent'     => self::$_ws_bytes_sent[$ip]
					  );
		if ($this->request->get_request_var('callback'))
		{
			$response->set_body($this->request->get_request_var('callback') . '(' . json_encode($body) . ');', false);
		}
		else
		{
			$response->set_body($body, true);
		}

		$this->_log_response($response);
		$this->write($response);
	}

	/**
	 * Handle long polling requests.
	 *
	 * @access public
	 * @return void
	 */
	public function long_polling()
	{
		$ip = $this->request->get_remote_ip();

		$response = new HTTPResponse(200);

		// Determine if this is a JSONP request.
		$body = array('lp_requests'       => self::$_lp_requests[$ip],
					  'lp_bytes_received' => self::$_lp_bytes_received[$ip],
					  'lp_bytes_sent'     => self::$_lp_bytes_sent[$ip]
					  );
		if ($this->request->get_request_var('callback'))
		{
			$response->set_body($this->request->get_request_var('callback') . '(' . json_encode($body) . ');', false);
		}
		else
		{
			$response->set_body($body, true);
		}

		$this->_log_response($response);
		$this->write($response);
	}

	/**
	 * Handle short polling requests.
	 *
	 * @access public
	 * @return void
	 */
	public function short_polling()
	{
		$ip = $this->request->get_remote_ip();

		$response = new HTTPResponse(200);

		// Determine if this is a JSONP request.
		$body = array('sp_requests'       => self::$_sp_requests[$ip],
					  'sp_bytes_received' => self::$_sp_bytes_received[$ip],
					  'sp_bytes_sent'     => self::$_sp_bytes_sent[$ip]
					  );
		if ($this->request->get_request_var('callback'))
		{
			$response->set_body($this->request->get_request_var('callback') . '(' . json_encode($body) . ');', false);
		}
		else
		{
			$response->set_body($body, true);
		}

		$this->_log_response($response);
		$this->write($response);
	}

	/**
	 * Processes the given event.
	 *
	 * @access public
	 * @return void
	 */
	public function process_event(Controller $controller = null)
	{
	}

	/**
	 * Logs stats for a new request.
	 *
	 * @access private
	 * @return void
	 */
	private function _log_request()
	{
		$ip = $this->request->get_remote_ip();

		// Keep track of the total connections and how much data they sent.
		if ($this->request->get_controller_method() == 'websocket')
		{
			if (!isset(self::$_ws_requests[$ip]))
			{
				self::$_ws_requests[$ip] = 0;
			}
			if (!isset(self::$_ws_bytes_received[$ip]))
			{
				self::$_ws_bytes_received[$ip] = 0;
			}
			if (!isset(self::$_ws_bytes_sent[$ip]))
			{
				self::$_ws_bytes_sent[$ip] = 0;
			}

			++self::$_ws_requests[$ip];
			self::$_ws_bytes_received[$ip]+= $this->request->get_request_size();
		}
		elseif ($this->request->get_controller_method() == 'long_polling')
		{
			if (!isset(self::$_lp_requests[$ip]))
			{
				self::$_lp_requests[$ip] = 0;
			}
			if (!isset(self::$_lp_bytes_received[$ip]))
			{
				self::$_lp_bytes_received[$ip] = 0;
			}
			if (!isset(self::$_lp_bytes_sent[$ip]))
			{
				self::$_lp_bytes_sent[$ip] = 0;
			}

			++self::$_lp_requests[$ip];
			self::$_lp_bytes_received[$ip]+= $this->request->get_request_size();
		}
		elseif ($this->request->get_controller_method() == 'short_polling')
		{
			if (!isset(self::$_sp_requests[$ip]))
			{
				self::$_sp_requests[$ip] = 0;
			}
			if (!isset(self::$_sp_bytes_received[$ip]))
			{
				self::$_sp_bytes_received[$ip] = 0;
			}
			if (!isset(self::$_sp_bytes_sent[$ip]))
			{
				self::$_sp_bytes_sent[$ip] = 0;
			}

			++self::$_sp_requests[$ip];
			self::$_sp_bytes_received[$ip]+= $this->request->get_request_size();
		}
	}

	/**
	 * Logs stats for a new response.
	 *
	 * @access private
	 * @param  HTTPResponse $response
	 * @return void
	 */
	private function _log_response(HTTPResponse $response)
	{
		$ip = $this->request->get_remote_ip();

		$response->set_default_headers();

		// Tally up the total bytes sent.
		if ($this->request->get_controller_method() == 'websocket')
		{
			if (!isset(self::$_ws_bytes_sent[$ip]))
			{
				self::$_ws_bytes_sent[$ip] = 0;
			}
			self::$_ws_bytes_sent[$ip]+= strlen($response->get_body());
		}
		elseif ($this->request->get_controller_method() == 'long_polling')
		{
			if (!isset(self::$_lp_bytes_sent[$ip]))
			{
				self::$_lp_bytes_sent[$ip] = 0;
			}
			self::$_lp_bytes_sent[$ip]+= strlen($response);
		}
		elseif ($this->request->get_controller_method() == 'short_polling')
		{
			if (!isset(self::$_sp_bytes_sent[$ip]))
			{
				self::$_sp_bytes_sent[$ip] = 0;
			}
			self::$_sp_bytes_sent[$ip]+= strlen($response);
		}
	}
}
?>
