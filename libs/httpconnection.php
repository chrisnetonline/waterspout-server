<?php
require_once _LIBS . DIRECTORY_SEPARATOR . 'wsrequest.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httprequest.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httpheaders.php';
/**
 * An open connection between the client and server.
 *
 * @package Waterspout
 * @author  Scott Mattocks
 */
class HTTPConnection
{
	/**
	 * The server that owns the connection.
	 *
	 * @access private
	 * @var    HTTPServer
	 */
	private $_server;

	/**
	 * The IOStream for this connection.
	 *
	 * @access private
	 * @var    IOStream
	 */
	private $_stream;

	/**
	 * IP address of the client.
	 *
	 * @access private
	 * @var    string
	 */
	private $_address;

	/**
	 * Callback called when new data arrives.
	 *
	 * @access private
	 * @var    callback
	 */
	private $_request_callback;

	/**
	 * The HTTPRequest object.
	 *
	 * @access private
	 * @var    HTTPRequest
	 */
	private $_request;

	/**
	 * Whether or not the request has finished.
	 *
	 * @access private
	 * @var    boolean
	 */
	private $_request_finished = false;

	/**
	 * The time the connection was open.
	 *
	 * @access private
	 * @var    float
	 */
	private $_start;

	/**
	 * The time the request the request finished.
	 *
	 * @access private
	 * @var    float
	 */
	private $_end;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param  HTTPServer $server
	 * @param  IOStream   $stream
	 * @param  string     $address
	 * @param  mixed      $request_callback
	 * @return void
	 */
	public function __construct(HTTPServer $server, IOStream $stream, $address, $request_callback)
	{
		$this->_server           = $server;
		$this->_stream           = $stream;
		$this->_address          = $address;
		$this->_request_callback = $request_callback;

		$this->_start = microtime(true);

		$this->_stream->read_until("\r\n\r\n", array($this, 'on_headers'));
	}

	/**
	 * Destructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __destruct()
	{
		unset($this->_stream);
		unset($this->_server);
		unset($this->_request_callback);
	}

	/**
	 * Writes data to the stream.
	 *
	 * @access public
	 * @param  string  $chunk
	 * @return void
	 */
	public function write($chunk)
	{
		if (empty($this->_request))
		{
			throw new BadMethodCallException('Attempting to write on a closed request connection.');
		}

		if ($this->_server->config('VERBOSE') >= 3)
		{
			file_put_contents($this->_server->config('LOG_FILE'), "RESPONSE BODY\t" . $this->_start . ":\r\n\r\n" . $chunk . "\r\n", FILE_APPEND);
		}

		$this->_stream->write($chunk, array($this, 'on_write_complete'));
	}

	/**
	 * Closes the stream and ends the connection.
	 *
	 * @access public
	 * @return void
	 */
	public function finish()
	{
		if (empty($this->_request))
		{
			file_put_contents($this->_server->config('LOG_FILE'), "Error:\t" . $this->_start . "\tAttempting to finish on a closed request connection.\r\n", FILE_APPEND);
			return;
		}

		$this->_request_finished = true;

		// Make sure we don't cut things off in the middle.
		if (!$this->_stream->writing())
		{
			$this->_finish_request();
		}

		$this->_end = microtime(true);
	}

	/**
	 * Callback called when writing is finished.
	 *
	 * @access public
	 * @return void
	 */
	public function on_write_complete()
	{
		if ($this->_request_finished)
		{
			$this->finish();
		}
	}

	/**
	 * Finish writing the current request.
	 *
	 * @access public
	 * @return void
	 */
	private function _finish_request()
	{
		// Clear out current request data.
		$this->_request          = null;
		$this->_request_finished = false;

		$this->_stream->close();
	}

	/**
	 * Callback called when new headers are found on the stream.
	 *
	 * @access public
	 * @param  string $data
	 * @return void
	 */
	public function on_headers($data)
	{
		if ($this->_server->config('VERBOSE') >= 2)
		{
			file_put_contents($this->_server->config('LOG_FILE'), "REQUEST HEADERS\t" . $this->_start . ":\r\n\r\n" . $data . "\r\n", FILE_APPEND);
		}

		list($start_line, $data)      = explode("\r\n", $data, 2);
		list($method, $uri, $version) = explode(' ', $start_line);

		// Make sure this is an HTTP request.
		if (strpos($version, 'HTTP/') !== 0)
		{
			return;
		}

		// Parse the headers.
		$headers = HTTPHeaders::parse($data);

		// Check for a WebSocket upgrade.
		if ($headers->has('Upgrade') &&
		    strtolower($headers->get('Upgrade')) == 'websocket'
		    )
		{
			$this->_request = new WSRequest($this, $method, $uri, $version, $headers, $this->_address);

			$this->write($this->_request->handshake());

			call_user_func($this->_request_callback, $this->_request);
			$this->_stream->read_until(chr(255), array($this, 'on_request_body'));
		}
		// This must be a regular HTTP request.
		else
		{
			$this->_request = new HTTPRequest($this, $method, $uri, $version, $headers, $this->_address);
		}

		// Check for a request body.
		$content_length = $headers->get('Content-Length');
		if ($content_length)
		{
			if ($content_length > IOStream::MAX_BUFFER_SIZE)
			{
				throw new OverflowException('Content-Length too long');
			}
			if ($headers->get('Expect') == '100-continue')
			{
				$this->_stream->write("HTTP/1.0 100 (Continue)\r\n\r\n");
			}
			$this->_stream->read_bytes($content_length, array($this, 'on_request_body'));
		}
		elseif (!($this->_request instanceof wsrequest))
		{
			call_user_func($this->_request_callback, $this->_request);
		}
	}

	/**
	 * Callback called when a request body is found.
	 *
	 * @access public
	 * @param  string $data
	 * @return void
	 */
	public function on_request_body($data)
	{
		if ($this->_server->config('VERBOSE') >= 3)
		{
			file_put_contents($this->_server->config('LOG_FILE'), "REQUEST BODY\t" . $this->_start . ":\r\n\r\n" . $data . "\r\n", FILE_APPEND);
		}

		// Set the body.
		$this->_request->set_body($data);

		call_user_func($this->_request_callback, $this->_request);
		$this->_stream->read_until(chr(255), array($this, 'on_request_body'));
	}

	/**
	 * Figure out how long the whole thing took.
	 *
	 * @access public
	 * @return void
	 */
	public function processing_time()
	{
		if (!$this->_request_finished)
		{
			return null;
		}

		return round($this->_end - $this->_start, 6);
	}

	/**
	 * Returns a unique-ish identifier for the connection.
	 *
	 * @access public
	 * @return float
	 */
	public function get_identifier()
	{
		return $this->_start;
	}

	/**
	 * Returns the total number of bytes read by the stream.
	 *
	 * @access public
	 * @return integer
	 */
	public function get_bytes_read()
	{
		return $this->_stream->get_bytes_read();
	}
}

/**
 * A mock connection object.
 *
 * @package Waterspout
 * @author  Scott Mattocks
 */
class MockConnection extends HTTPConnection
{
	/**
	 * Constructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		$this->_start = microtime(true);
	}

	/**
	 * Destructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __destruct()
	{
	}

	/**
	 * Writes data to the stream.
	 *
	 * @access public
	 * @param  string  $chunk
	 * @return void
	 */
	public function write($chunk)
	{
	}

	/**
	 * Closes the stream and ends the connection.
	 *
	 * @access public
	 * @return void
	 */
	public function finish()
	{
	}

	/**
	 * Callback called when writing is finished.
	 *
	 * @access public
	 * @return void
	 */
	public function on_write_complete()
	{
	}

	/**
	 * Callback called when new headers are found on the stream.
	 *
	 * @access public
	 * @param  string $data
	 * @return void
	 */
	public function on_headers($data)
	{
	}

	/**
	 * Callback called when a request body is found.
	 *
	 * @access public
	 * @param  string $data
	 * @return void
	 */
	public function on_request_body($data)
	{
	}

	/**
	 * Figure out how long the whole thing took.
	 *
	 * @access public
	 * @return void
	 */
	public function processing_time()
	{
		return 0;
	}

	/**
	 * Returns a unique-ish identifier for the connection.
	 *
	 * @access public
	 * @return float
	 */
	public function get_identifier()
	{
		return $this->_start;
	}


	/**
	 * Returns the total number of bytes read by the stream.
	 *
	 * @access public
	 * @return integer
	 */
	public function get_bytes_read()
	{
		return 0;
	}
}
?>