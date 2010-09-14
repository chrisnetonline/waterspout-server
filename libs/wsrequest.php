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
	 * @param  string $code
	 * @return void
	 */
	public function handshake($code)
	{
		$handshake = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n";
		$handshake.= "Upgrade: WebSocket\r\n";
		$handshake.= "Connection: Upgrade\r\n";
		
		// WebSocket handshake v76.
		if ($this->headers->has('Sec-WebSocket-Key1') && $this->headers->has('Sec-WebSocket-Key2'))
		{
			$handshake.= 'Sec-WebSocket-Origin: ' . $this->headers->get('Origin') . "\r\n";

			if (!empty($this->query))
			{
				$handshake.= 'Sec-WebSocket-Location: ' . $this->full_url() . '?' . $this->query . "\r\n";
			}
			else
			{
				$handshake.= 'Sec-WebSocket-Location: ' . $this->full_url() . "\r\n";
			}

			if ($this->headers->has('Sec-WebSocket-Protocol'))
			{
				$handshake.= 'Sec-WebSocket-Protocol: ' . $this->headers->get('Sec-WebSocket-Protocol') . "\r\n";
			}
			
			$handshake.= "\r\n";
			
			$key1 = $this->headers->get('Sec-WebSocket-Key1');
			$key2 = $this->headers->get('Sec-WebSocket-Key2');
			$handshake.= $this->_create_sec_handshake($key1, $key2, $code);
		}
		// WebSocket handshake v75.
		else
		{
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
		}

		return $handshake;
	}

	/**
	 * Obtain int 32.
	 *
	 * @access public
	 * @param  string $key
	 * @return string
	 */
	private function _obtain_int32($key)
	{
		$n_spaces = 0;
		$len = mb_strlen($key, '8bit');
		$int = '';

		for ($i = 0; $i < $len; $i++)
		{
			$char = $key[$i];
			if (is_numeric($char))
			{
				$int.= $char;
			}
			if ($char == ' ')
			{
				$n_spaces++;
			}
		}
		
		$return = ($int * 1) / $n_spaces;

		return $return;
	}

	/**
	 * Creates the secure handshake.
	 *
	 * @access public
	 * @param  string $key1
	 * @param  string $key2
	 * @param  string $code
	 * @return string
	 */
	private function _create_sec_handshake($key1, $key2, $code)
	{
		$i1 = $this->_obtain_int32($key1);
		$i2 = $this->_obtain_int32($key2);
		
		$return = md5(
			pack('N', $i1) .
			pack('N', $i2) .
			$code,
			true
		);
		return $return;
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
	 * Sets the body content without altering it.
	 *
	 * @access public
	 * @param  string $body
	 * @return void
	 */
	public function set_body_unparsed($body)
	{
		$this->body = $body;
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