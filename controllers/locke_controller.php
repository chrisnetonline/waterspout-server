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

class Locke_Controller extends Controller
{
	/**
	 * The maximum number of commands to store on the move stack.
	 *
	 * @const
	 */
	const MAX_MOVE_STACK_SIZE = 5000;

	/**
	 * A queue of users that are currently active.
	 *
	 * @access private
	 * @var    array
	 */
	static private $_presence = array();

	/**
	 * A queue of move events that have occurred.
	 *
	 * @static
	 * @access private
	 * @var    array
	 */
	static private $_commands = array();

	/**
	 * The current cursor position for this listener.
	 *
	 * @access private
	 * @var    float
	 */
	private $_cursor;

	/**
	 * The total number of WS requests made to updates.
	 *
	 * @static
	 * @access private
	 * @var    integer
	 */
	static private $_ws_requests = 0;

	/**
	 * The total number of long polling requests made to updates.
	 *
	 * @static
	 * @access private
	 * @var    integer
	 */
	static private $_lp_requests = 0;

	/**
	 * The total number of bytes sent for WS updates.
	 *
	 * @static
	 * @access private
	 * @var    integer
	 */
	static private $_ws_bytes_sent = 0;

	/**
	 * The total number of bytes sent for long polling updates.
	 *
	 * @static
	 * @access private
	 * @var    integer
	 */
	static private $_lp_bytes_sent = 0;

	/**
	 * The total number of bytes received for WS updates.
	 *
	 * @static
	 * @access private
	 * @var    integer
	 */
	static private $_ws_bytes_received = 0;

	/**
	 * The total number of bytes received for long polling updates.
	 *
	 * @static
	 * @access private
	 * @var    integer
	 */
	static private $_lp_bytes_received = 0;

	/**
	 * The deadline for killing off Lockes.
	 *
	 * @access protected
	 * @var    integer
	 */
	private $_presence_deadline;

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
	 * Adds a new move to the queue.
	 *
	 * @access public
	 * @return void
	 */
	public function moving()
	{
		// Write back the message. We do this first to close the connection and keep
		// things moving along.
		$response = new HTTPResponse(200);

		// Determine if this is a JSONP request.
		$body = array('__URI__' => $this->uri);
		if ($this->request->get_request_var('callback'))
		{
			$response->set_body($this->request->get_request_var('callback') . '(' . json_encode($body) . ');', false);
		}
		else
		{
			$response->set_body($body, true);
		}

		$this->write($response);

		if (!empty(self::$_commands))
		{
			$max = max(array_keys(self::$_commands)) + 1;
		}
		else
		{
			$max = 0;
		}

		self::$_commands[$max] = array('coords' => $this->request->get_request_var('coords'),
		                               'person' => $this->request->get_request_var('person')
		                               );

		self::$_commands = array_slice(self::$_commands, -self::MAX_MOVE_STACK_SIZE, self::MAX_MOVE_STACK_SIZE, true);
	}

	/**
	 * Adds a new message to the queue.
	 *
	 * @access public
	 * @return void
	 */
	public function talking()
	{
		// Write back the message. We do this first to close the connection and keep
		// things moving along.
		$response = new HTTPResponse(200);

		// Determine if this is a JSONP request.
		$body = array('__URI__' => $this->uri);
		if ($this->request->get_request_var('callback'))
		{
			$response->set_body($this->request->get_request_var('callback') . '(' . json_encode($body) . ');', false);
		}
		else
		{
			$response->set_body($body, true);
		}

		$this->write($response);

		if (!empty(self::$_commands))
		{
			$max = max(array_keys(self::$_commands)) + 1;
		}
		else
		{
			$max = 0;
		}

		self::$_commands[$max] = array('text'   => strip_tags($this->request->get_request_var('text')),
		                               'person' => $this->request->get_request_var('person')
		                               );

		self::$_commands = array_slice(self::$_commands, -self::MAX_MOVE_STACK_SIZE, self::MAX_MOVE_STACK_SIZE, true);
	}

	/**
	 * Listens for updates from other connections.
	 *
	 * @access public
	 * @return void
	 */
	public function updates()
	{
		// If a cursor was passed in, make that our new cursor.
		$request_cursor = $this->request->get_request_var('waterspout_cursor');
		if (empty($this->_cursor) && !empty($request_cursor) && $request_cursor <= (end(array_keys(self::$_commands)) + 1))
		{
			$this->_cursor = $request_cursor;
		}
		// If the server doesn't have any commands in the stack yet, start the
		// cursor out at 0.
		elseif (!count(self::$_commands))
		{
			$this->_cursor = 0;
		}
		// If no cursor was passed in, then figure it out.
		elseif (is_null($this->_cursor))
		{
			$this->_cursor = (int) end(array_keys(self::$_commands)) + 1;
		}

		$this->dispatcher->add_listener($this);

		if ($this->request instanceof WSRequest)
		{
			++self::$_ws_requests;
		}
	}

	/**
	 * Returns information about the number and type of requests made and the amount of
	 * data transfered.
	 *
	 * @access public
	 * @return void
	 */
	public function presence()
	{
		// Add this user to the list.
		self::$_presence[$this->request->get_request_var('person')] = array('timestamp' => time(), 'coords' => $this->request->get_request_var('coords'), 'name' => $this->request->get_request_var('name'));

		// Filter the presence array.
		$this->_presence_deadline = time() - 10;
		self::$_presence = array_filter(self::$_presence, array($this, '_presence_filter'));

		$response = new HTTPResponse(200);

		// Determine if this is a JSONP request.
		$body = array('__URI__'           => $this->uri,
					  'ws_requests'       => self::$_ws_requests,
					  'ws_bytes_received' => self::$_ws_bytes_received,
					  'ws_bytes_sent'     => self::$_ws_bytes_sent,
					  'lp_requests'       => self::$_lp_requests,
					  'lp_bytes_received' => self::$_lp_bytes_received,
					  'lp_bytes_sent'     => self::$_lp_bytes_sent,
					  'players'           => self::$_presence
					  );
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
	 * Processes the given event.
	 *
	 * @access public
	 * @return void
	 */
	public function process_event(Controller $mover = null)
	{
		$key = array_search((int) $this->_cursor, array_keys(self::$_commands));
		if ($key === false && !is_null($this->_cursor))
		{
			return;
		}

		$commands = array_slice(self::$_commands, $key);

		if (empty($commands))
		{
			return;
		}

		$response = new HTTPResponse(200);

		$body = array('__URI__'  => $this->uri,
					  'cursor'   => end(array_keys(self::$_commands)) + 1,
					  'commands' => $commands
					  );
		if ($this->request->get_request_var('callback'))
		{
			$response->set_body($this->request->get_request_var('callback') . '(' . json_encode($body) . ');', false);
		}
		else
		{
			$response->set_body($body, true);
		}

		$this->write($response);

		$this->_cursor = (int) end(array_keys(self::$_commands)) + 1;
	}

	/**
	 * Writes the response to connection.
	 *
	 * @access public
	 * @param  HTTPResponse $response
	 * @return void
	 */
	public function write(HTTPResponse $response)
	{
		parent::write($response);

		$this->_log_response($response);
	}

	/**
	 * Logs stats for a new request.
	 *
	 * @access private
	 * @return void
	 */
	private function _log_request()
	{
		// Keep track of the total connections and how much data they sent.
		if ($this->request instanceof WSRequest)
		{
			self::$_ws_bytes_received+= $this->request->get_request_size();
		}
		else
		{
			++self::$_lp_requests;
			self::$_lp_bytes_received+= $this->request->get_request_size();
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
		$response->set_default_headers();
		// Tally up the total bytes sent.
		if ($this->request instanceof WSRequest)
		{
			self::$_ws_bytes_sent+= strlen($response->get_body());
		}
		else
		{
			self::$_lp_bytes_sent+= strlen($response);
		}
	}

	/**
	 * Filters out dead Lockes.
	 *
	 * @access public
	 * @return void
	 */
	public function _presence_filter($item)
	{
		return $item['timestamp'] >= $this->_presence_deadline;
	}
}
?>
