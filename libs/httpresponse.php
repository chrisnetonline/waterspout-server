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

/**
 * A response to be sent to the client.
 *
 * @package Waterspout
 * @author  Scott Mattocks
 */
class HTTPResponse
{
	/**
	 * HTTP response codes.
	 *
	 * @static
	 * @access public
	 * @var    array
	 */
	static $statuses = array(200 => 'OK',
	                         301 => 'Moved Permanently',
	                         302 => 'Found',
	                         304 => 'Not Modified',
	                         403 => 'Forbidden',
	                         404 => 'File Not Found',
	                         408 => 'Request Timeout',
	                         500 => 'Internal Server Error',
	                         503 => 'Service Unavailable'
	                         );

	/**
	 * The response status code.
	 *
	 * @access protected
	 * @var    integer
	 */
	protected $status;

	/**
	 * The headers to be sent back with the response.
	 *
	 * @access protected
	 * @var    array
	 */
	protected $headers = array();

	/**
	 * Cookies for the user.
	 *
	 * @access protected
	 * @var    array
	 */
	protected $cookies = array();

	/**
	 * The response body.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $body;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param  integer $status
	 * @return void
	 */
	public function __construct($status)
	{
		// Default the status to an internal error.
		if (!array_key_exists($status, self::$statuses))
		{
			$status = 500;
		}
		$this->set_status($status);
	}

	/**
	 * Sets the default headers.
	 *
	 * @access public
	 * @return void
	 */
	public function set_default_headers()
	{
		$headers = array('Content-Length' => $this->_get_content_length(),
		                 'Server'         => 'WaterSpout/0.6-alpha',
		                 'Date'           => date('D, d M Y H:i:s \G\M\T')
		                 );
		$this->add_headers($headers);

		if (empty($this->headers['Access-Control-Allow-Origin']))
		{
			$this->add_header('Access-Control-Allow-Origin', '*');
		}
	}

	/**
	 * Sets the response status.
	 *
	 * @access public
	 * @param  integer $status
	 * @return void
	 */
	public function set_status($status)
	{
		$this->status = $status;
	}

	/**
	 * Returns the response status code.
	 *
	 * @access public
	 * @return integer
	 */
	public function get_status()
	{
		return $this->status;
	}

	/**
	 * Adds a new response header.
	 *
	 * @access public
	 * @param  string $header
	 * @param  mixed  $value
	 * @return void
	 */
	public function add_header($header, $value)
	{
		$this->headers[$header] = $value;
	}

	/**
	 * Adds multiple headers to the array at once.
	 *
	 * @access public
	 * @param  array  $headers
	 * @return void
	 */
	public function add_headers(array $headers)
	{
		$this->headers = array_merge($this->headers, $headers);
	}

	/**
	 * Sets a cookie to be sent to the client.
	 *
	 * @access public
	 * @param  string  $name
	 * @param  string  $value
	 * @param  integer $expire
	 * @param  string  $path
	 * @param  string  $domain
	 * @return void
	 */
	public function set_cookie($name, $value = null, $expire = 0, $path = '/', $domain = null)
	{
		$this->cookies[$name] = array('value'  => $value,
		                              'expire' => $expire,
		                              'path'   => $path,
		                              'domain' => $domain
		                              );
	}

	/**
	 * Sets the response body and optionally JSON encodes it.
	 *
	 * @access public
	 * @param  string  $body
	 * @param  boolean $encode Whether or not to JSON encode the value.
	 * @return void
	 */
	public function set_body($body, $encode = true)
	{
		if ($encode)
		{
			$body = json_encode($body);
			$this->add_header('Content-Type', 'application/json; charset=UTF-8');
			$this->add_header('X-JSON', $body);
		}

		$this->body = $body;

		$this->add_header('Content-Length', $this->_get_content_length());
	}

	/**
	 * Returns the response body.
	 *
	 * @access public
	 * @return string
	 */
	public function get_body()
	{
		return $this->body;
	}

	/**
	 * Returns a multibyte safe content length.
	 *
	 * @access private
	 * @return integer
	 */
	private function _get_content_length()
	{
		return strlen($this->body);
	}

	/**
	 * Returns the headers as a string.
	 *
	 * @access public
	 * @return string
	 */
	public function headers_as_string()
	{
		$message = 'HTTP/1.0 ' . $this->status . ' ' . self::$statuses[$this->status] . "\r\n";

		$headers = array();
		foreach ($this->headers as $key => $value)
		{
			$headers[] = $key . ': ' . $value;
		}

		foreach ($this->cookies as $name => $cookie)
		{
			$header = $name . '=' . $cookie['value'] . ';';
			if (!empty($cookie['expire']))
			{
				$header.= 'expires=' . date('D, d M Y H:i:s \G\M\T', time() + $cookie['expire']) . ';';
			}
			if (!empty($cookie['path']))
			{
				$header.= 'path=' . $cookie['path'] . ';';
			}
			if (!empty($cookie['domain']))
			{
				$header.= 'domain=' . $cookie['domain'] . ';';
			}
			$headers[] = 'Set-cookie: ' . $header;
		}

		$message.= implode("\r\n", $headers);
		$message.= "\r\n\r\n";

		return $message;
	}

	/**
	 * Returns the response object as a string.
	 *
	 * @access public
	 * @return string
	 */
	public function __toString()
	{
		return $this->headers_as_string() . $this->body;
	}
}
?>