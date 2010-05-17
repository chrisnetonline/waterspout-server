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
/**
 * A request made over HTTP.
 *
 * @package Waterspout
 * @author  Scott Mattocks
 */
class HTTPRequest implements Serializable
{
	/**
	 * The request method (GET|POST|OPTIONS|HEAD|Whatever)
	 *
	 * @access protected
	 * @var    string
	 */
	protected $method;

	/**
	 * The URI of the request.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $uri;

	/**
	 * The request version.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $version;

	/**
	 * The request headers.
	 *
	 * @access protected
	 * @var    HTTPHeaders
	 */
	protected $headers;

	/**
	 * The request body.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $body;

	/**
	 * The IP address of the client making the request.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $remote_ip;

	/**
	 * The communcations protocol.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $protocol = 'http';

	/**
	 * The host the request was made on.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $host;

	/**
	 * The files that were passed in with the request.
	 *
	 * @access protected
	 * @var    array
	 */
	protected $files = array();

	/**
	 * The connection object for this request.
	 *
	 * @access protected
	 * @var    HTTPConnection
	 */
	protected $connection;

	/**
	 * The requested path.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $path;

	/**
	 * The requested query string
	 *
	 * @access protected
	 * @var    string
	 */
	protected $query;

	/**
 	 * The POST data.
	 *
	 * @access protected
	 * @var    array
	 */
	protected $post = array();

	/**
	 * The GET data.
	 *
	 * @access protected
	 * @var    array
	 */
	protected $get = array();

	/**
	 * The COOKIE data.
	 *
	 * @access protected
	 * @var    array
	 */
	protected $cookie = array();

	/**
	 * The starting time of the request.
	 *
	 * @access protected
	 * @var    float
	 */
	protected $start_time;

	/**
	 * The ending time of the request.
	 *
	 * @access protected
	 * @var    float
	 */
	protected $finish_time;

	/**
	 * The classname of the controller that should handle the request.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $controller_class;

	/**
	 * The method of the controller that should handle the request.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $controller_method;

	/**
	 * The arguments for the controller method.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $controller_args;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param  HTTPConnect $connection
	 * @apram  string      $method
	 * @param  string      $uri
	 * @param  string      $version
	 * @param  HTTPHeaders $headers
	 * @param  string      $remote_ip
	 * @param  string      $protocol
	 * @param  string      $host
	 * @param  array       $files
	 * @return void
	 */
	public function __construct(HTTPConnection $connection, $method, $uri,
	                            $version = 'HTTP/1.0', $headers = null,
	                            $remote_ip = null
	                            )
	{
		$this->method = $method;

		if (strpos($uri, '?') !== false)
		{
			$url = mb_substr($uri, 0, mb_strpos($uri, '?', 0, 'UTF-8'), 'UTF-8');
		}
		else
		{
			$url = $uri;
		}

		$this->set_uri($url);
		$this->version    = $version;
		$this->headers    = $headers;
		$this->remote_ip  = reset(explode(':', $remote_ip));
		$this->connection = $connection;
		$this->start_time = time();

		// Pull some data out of the headers.
		if (!empty($headers['Host']))
		{
			$host = $headers['Host'];
		}
		else
		{
			$host = '127.0.0.1';
		}

		$this->host = $host;

		// Break up the URI into different parts.
		$parts = parse_url($uri);

		$this->path = $parts['path'];
		if (!empty($parts['query']))
		{
			$this->query = $parts['query'];
			parse_str($parts['query'], $args);
			if (!empty($args))
			{
				foreach ($args as $name => $values)
				{
					if (!empty($values))
					{
						$this->get[$name] = $values;
					}
				}
			}
		}

		// Pull out the cookies if there are any.
		if ($this->headers->has('Cookie'))
		{
			$cookies = explode(';', $this->headers->get('Cookie'));
			foreach ($cookies as $cookie)
			{
				list($name, $value) = explode('=', trim($cookie), 2);
				$this->cookie[$name] = $value;
			}
		}
	}

	/**
	 * Destructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __destruct()
	{
		unset($this->connection);
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
		// Check GET.
		if (array_key_exists($key, $this->get))
		{
			return $this->get[$key];
		}
		// Check POST.
		elseif (array_key_exists($key, $this->post))
		{
			return $this->post[$key];
		}
		// Check COOKIE.
		elseif (array_key_exists($key, $this->cookie))
		{
			return $this->cookie[$key];
		}

		return null;
	}

	/**
	 * Returns the array of GET variables.
	 *
	 * @access public
	 * @return array
	 */
	public function get_get()
	{
		return $this->get;
	}

	/**
	 * Returns the array of POST variables.
	 *
	 * @access public
	 * @return array
	 */
	public function get_post()
	{
		return $this->post;
	}

	/**
	 * Returns the array of COOKIE variables.
	 *
	 * @access public
	 * @return array
	 */
	public function get_cookie()
	{
		return $this->cookie;
	}

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
		$chunk->set_default_headers();

		// Check to see if we should close the connection or allow it to stay open.
		$keepalive = $this->connection->keep_alive();
		if ($keepalive)
		{
			$chunk->add_header('Connection', 'Keep-Alive');
			$chunk->add_header('Keep-Alive', 'timeout=' . $this->connection->config('KEEPALIVE_TIMEOUT') . ', max=' . $this->connection->get_keepalives_left());
		}
		else
		{
			$chunk->add_header('Connection', 'close');
		}

		$this->connection->write((string) $chunk);

		return true;
	}

	/**
	 * Finishes the request and closes off the connection.
	 *
	 * @access public
	 * @return void
	 */
	public function finish()
	{
		$this->connection->finish();
		$this->finish_time = time();
	}

	/**
	 * Returns the full URL of the request.
	 *
	 * @access public
	 * @return void
	 */
	public function full_url()
	{
		return $this->protocol . '://' . $this->host . $this->uri;
	}

	/**
	 * Returns the total processing time of the request.
	 *
	 * @access public
	 * @return float
	 */
	public function request_time()
	{
		if ($this->connection->config('VERBOSE') > 0)
		{
			if (empty($this->finish_time))
			{
				return microtime(true) - $this->start_time;
			}
			else
			{
				return $this->finish_time - $this->start_time;
			}
		}
		return 0;
	}

	/**
	 * Sets the body content. This is done in a method incase a child class needs to do
	 * something special.
	 *
	 * @access public
	 * @param  string $body
	 * @return void
	 */
	public function set_body($body)
	{
		$this->body = $body;

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
		$content_type = $this->headers->get('Content-Type');
		if ($this->method == 'POST')
		{
			// Look for different types of form encoded data.
			if (strpos($content_type, 'application/x-www-form-urlencoded') !== false)
			{
				$arguments = array();
				$body = urldecode($this->body);
				parse_str($body, $arguments);
				foreach ($arguments as $name => $value)
				{
					$this->post[$name] = $value;
				}
			}
			elseif (strpos($content_type, 'multipart/form-data') !== false)
			{
				$boundry = mb_substr($content_type, 30, mb_strlen($content_type, 'UTF-8'), 'UTF-8');
				if (!empty($boundry))
				{
					$this->_parse_mime_body($boundry, $this->body);
				}
			}
		}

		$this->body = new stdClass();
		foreach ($this->post as $key => $val)
		{
			$this->body->$key = $val;
		}
	}

	/**
	 * Parse a mime body.
	 *
	 * @access public
	 * @param  string $boundry
	 * @param  string $data
	 * @return void
	 */
	private function _parse_mime_body($boundry, $data)
	{
		// Determine the footer length.
		if (mb_substr($data, -2, mb_strlen($data, 'UTF-8'), 'UTF-8') == "\r\n")
		{
			$footer_length = mb_strlen($boundry, 'UTF-8') + 6;
		}
		else
		{
			$footer_length = mb_strlen($boundry, 'UTF-8') + 4;
		}

		// Blow up the body into different parts.
		$parts = explode($boundry, mb_substr($data, 0, -$footer_length, 'UTF-8'));

		foreach ($parts as $part)
		{
			if (empty($part) || $part == '--')
			{
				continue;
			}
			$eoh = strpos($part, "\r\n\r\n");

			if ($eoh === false)
			{
				trigger_error('multipart/form-data missing headers', E_USER_WARNING);
				continue;
			}
			$headers = HTTPHeaders::parse(mb_substr($part, 0, $eoh, 'UTF-8'));

			$name_header = $headers->get('Content-Disposition');
			if (strpos($name_header, 'form-data') !== 0)
			{
				trigger_error('Invalid multipart/form-data', E_USER_WARNING);
				continue;
			}

			$value = rtrim(mb_substr($part, $eoh + 4, -2, 'UTF-8'));
			$name_values = array();

			foreach (explode(';', mb_substr($name_header, 10, mb_strlen($name_header, 'UTF-8'), 'UTF-8')) as $name_part)
			{
				list($name, $name_value) = explode('=', trim($name_part), 2);
				$name_values[$name]      = utf8_decode(trim($name_value, '"'));
			}

			// Make sure there is a name for the request value.
			if (empty($name_values['name']))
			{
				trigger_error('multipart/form-data value missing name', E_USER_WARNING);
				continue;
			}

			// Pull the files out of the requst.
			$name = $name_values['name'];
			if (!empty($name_values['filename']))
			{
				// Determine the content type.
				if (!empty($headers['Content-Type']))
				{
					$ctype = $headers['Content-Type'];
				}
				else
				{
					$ctype = 'application/unknown';
				}

				// Create a new entry if we need to.
				if (empty($this->files[$name]))
				{
					$this->files[$name] = array();
				}

				$this->files[$name][] = array('filename'     => $name_values['filename'],
				                               'body'         => $value,
				                               'content_type' => $ctype
				                               );
			}
			else
			{
				$this->post[$name] = $value;
			}
		}
	}

	/**
	 * Returns the request method.
	 *
	 * @access public
	 * @return string
	 */
	public function get_method()
	{
		return $this->method;
	}

	/**
	 * Returns the request URI.
	 *
	 * @access public
	 * @return string
	 */
	public function get_uri()
	{
		return $this->uri;
	}

	/**
	 * Sets the request URI.
	 *
	 * @access public
	 * @param  string
	 * @return void
	 */
	public function set_uri($uri)
	{
		$this->uri = $uri;

		// Break up the request.
		if (!empty($uri))
		{
			$parts = explode('/', ltrim($uri, '/'), 3);
			$this->controller_class = $parts[0];

			if (!empty($parts[1]))
			{
				$this->controller_method = $parts[1];
			}

			if (!empty($parts[2]))
			{
				$this->controller_args = $parts[2];
			}
		}
	}

	/**
	 * Returns the connection object.
	 *
	 * @access public
	 * @return HTTPConnection
	 */
	public function get_connection()
	{
		return $this->connection;
	}

	/**
	 * Returns the headers object.
	 *
	 * @access public
	 * @return HTTPHeaders
	 */
	public function get_headers()
	{
		return $this->headers;
	}

	/**
	 * Returns the remote IP address.
	 *
	 * @access public
	 * @return string
	 */
	public function get_remote_ip()
	{
		return $this->remote_ip;
	}

	/**
	 * Returns the controller class for the request.
	 *
	 * @access public
	 * @return string
	 */
	public function get_controller_class()
	{
		return $this->controller_class;
	}

	/**
	 * Returns the controller method for the request.
	 *
	 * @access public
	 * @return string
	 */
	public function get_controller_method()
	{
		return $this->controller_method;
	}

	/**
	 * Returns the controller args for the request.
	 *
	 * @access public
	 * @return string
	 */
	public function get_controller_args()
	{
		return $this->controller_args;
	}

	/**
	 * Serializes the object.
	 *
	 * @access public
	 * @return string
	 */
	public function serialize()
	{
		$clone = clone $this;
		unset($clone->_connection);

		$properties = array();
		foreach ($clone as $prop => $value)
		{
			$properties[$prop] = $value;
		}

		return serialize($properties);
	}

	/**
	 * Unserializes the object. Unserialized objects will have mock connections.
	 *
	 * @access public
	 * @param  string $serialized
	 * @return HTTPRequest
	 */
	public function unserialize($serialized)
	{
		$properties = unserialize($serialized);

		$this->connection = new MockConnection();

		foreach ($properties as $prop => $value)
		{
			$this->$prop = $value;
		}

		return $this;
	}

	/**
	 * Returns the size of the request.
	 *
	 * @access public
	 * @return integer
	 */
	public function get_request_size()
	{
		return $this->connection->get_bytes_read();
	}
}
?>