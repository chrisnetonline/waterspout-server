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

require_once _LIBS . DIRECTORY_SEPARATOR . 'httpconnection.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httprequest.php';
/**
 * A request made over HTTP.
 *
 * @package Waterspout
 * @author  Scott Mattocks
 */
class WSRequest extends HTTPRequest
{
	/**
	 * The communcations protocol.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $protocol = 'ws';

	/**
 	 * The previous request size.
	 *
	 * @access private
	 * @var    integer
	 */
	private $_previous_request_size = 0;

	/**
	 * Writes data to the client. If the method returns true, the connection should be
	 * closed.
	 *
	 * @access public
	 * @param  HTTPResponse $chunk
	 * @return boolean
	 */
	public function write(HTTPResponse $chunk)
	{
		$this->connection->write(chr(0) . $chunk->get_body() . chr(255));

		return ($chunk->get_status() != 200);
	}

	/**
	 * Sends back handshake headers.
	 *
	 * @access public
	 * @return void
	 */
	public function handshake()
	{
		$handshake = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n";
		$handshake.= "Upgrade: WebSocket\r\n";
		$handshake.= "Connection: Upgrade\r\n";
		$handshake.= 'WebSocket-Origin: ' . $this->headers->get('Origin') . "\r\n";

		if (!empty($this->query))
		{
			$handshake.= 'WebSocket-Location: ' . $this->full_url() . '?' . $this->query . "\r\n";
		}
		else
		{
			$handshake.= 'WebSocket-Location: ' . $this->full_url() . "\r\n";
		}

		$handshake.= "\r\n";

		return $handshake;
	}

	/**
	 * Sets the body content after trimming the delimiter.
	 *
	 * @access public
	 * @param  string $body
	 * @return void
	 */
	public function set_body($body)
	{
		$this->body = mb_substr($body, 1, -1);
		$this->parse_body();
	}

	/**
	 * Parses the message body depending on the format.
	 *
	 * @access public
	 * @return void
	 */
	public function parse_body()
	{
		$decoded = json_decode($this->body);

		if ($decoded)
		{
			$this->body = $decoded;

			// Check for a controller in the decoded body.
			if (!empty($decoded->__URI__))
			{
				$this->set_uri($decoded->__URI__);
			}
		}
	}

	/**
	 * Looks for a request variable in order of GET then POST.
	 *
	 * @access public
	 * @param  string $key
	 * @return mixed
	 */
	public function get_request_var($key)
	{
		$var = parent::get_request_var($key);
		if (!is_null($var))
		{
			return $var;
		}
		elseif (is_object($this->body) && property_exists($this->body, $key))
		{
			return $this->body->$key;
		}

		return null;
	}

	/**
	 * Returns the size of the request.
	 *
	 * @access public
	 * @return integer
	 */
	public function get_request_size()
	{
		$size = $this->connection->get_bytes_read() - $this->_previous_request_size;
		$this->_previous_request_size = $this->connection->get_bytes_read();

		return $size;
	}
}
?>