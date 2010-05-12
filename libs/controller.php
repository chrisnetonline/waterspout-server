<?php
require_once _LIBS . DIRECTORY_SEPARATOR . 'httpresponse.php';
require_once _LIBS . DIRECTORY_SEPARATOR . 'httprequest.php';
abstract class Controller
{
	/**
	 * The request object from the dispatcher.
	 *
	 * @access protected
	 * @var    HTTPRequest
	 */
	protected $request;

	/**
	 * The dispatcher.
	 *
	 * @access public
	 * @var    Dispatcher
	 */
	public $dispatcher;

	/**
	 * The request's UIR.
	 *
	 * @access protected
	 * @var    string
	 */
	protected $uri;

	/**
	 * The background content.
	 *
	 * @access private
	 * @var    string
	 */
	private $_background_content = '';

	/**
	 * The HTTP status of the response.
	 *
	 * @access protected
	 * @var    integer
	 */
	protected $status = 200;

	/**
	 * Constructor. Sets the request and dispatcher.
	 *
	 * @access public
	 * @param  HTTPRequest $request
	 * @param  Dispatcher  $dispatcher
	 * @return void
	 */
	public function __construct(HTTPRequest $request, Dispatcher $dispatcher)
	{
		$this->request    = $request;
		$this->dispatcher = $dispatcher;
		$this->uri        = $request->get_uri();
	}

	/**
	 * Destructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __destruct()
	{
		unset($this->request);
		unset($this->dispatcher);
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
		$config = $this->dispatcher->get_config();
		try
		{
			if ($config['VERBOSE'] >= 2)
			{
				file_put_contents($config['LOG_FILE'], $response->headers_as_string() . "\r\n", FILE_APPEND);
			}

			if ($this->request->write($response))
			{
				$this->close();
			}
		}
		catch (Exception $e)
		{
			if ($config['VERBOSE'] >= 2)
			{
				file_put_contents($config['LOG_FILE'], $e->getMessage() . "\r\n", FILE_APPEND);
			}

			$this->close();
		}

		if ($config['VERBOSE'] >= 1)
		{
			$this->dispatcher->log($this->request, $response);
		}
	}

	/**
	 * Returns the request object.
	 *
	 * @access public
	 * @return HTTPRequest
	 */
	public function get_request()
	{
		return $this->request;
	}

	/**
	 * Closes the connection and removes the controller from the listeners.
	 *
	 * @access public
	 * @return void
	 */
	public function close()
	{
		$this->request->finish();
		$this->dispatcher->remove_listener($this);
	}

	/**
	 * Processes the given event.
	 *
	 * @abstract
	 *
	 * @access public
	 * @return void
	 */
	abstract public function process_event(Controller $controller = null);

	/**
	 * Backgrounds the rest of the process so that we may continue with other users.
	 *
	 * @access protected
	 * @return resource
	 */
	protected function background($command, $split_headers = false)
	{
		$config = $this->dispatcher->get_config();

		// Open a process to execute the content.
		$descriptors = array(0 => array('pipe', 'r'),
		                     1 => array('pipe', 'w'),
		                     2 => array('file', $config['ERROR_LOG_FILE'], 'a')
		                     );

		$cwd   = dirname($this->_path);
		$pipes = array();

		$process = proc_open($command, $descriptors, $pipes, $cwd);

		// Make sure it worked.
		if (!is_resource($process))
		{
			return null;
		}

		// Put the reading stream into the loop so that we can continue doing other
		// stuff.
		$loop = $this->dispatcher->get_server()->get_loop();
		$loop->add_handler($pipes[1], array($this, 'background_write'), IOLoop::READ, array($process, $split_headers));

		return $pipes[0];
	}

	/**
	 * Writes the background contents to the response.
	 *
	 * @access public
	 * @param  resource $socket
	 * @param  integer  $ioevent
	 * @param  resource $process
	 * @param  boolean  $split_headers
	 * @return void
	 */
	public function background_write($socket, $ioevent, $process, $split_headers = false)
	{
		$attempts = 0;

		while ($attempts++ <= 10)
		{
			$chunk = fread($socket, IOStream::MAX_BUFFER_SIZE);

			if (!empty($chunk))
			{
				break;
			}
		}

		if (empty($chunk) && empty($this->_background_content))
		{
			// Write an error response.
			$response = new HTTPResponse(500);
			$response->set_body('Trouble generating content.');
		}
		else
		{
			$this->_background_content.= $chunk;

			// Check to see if the process has finished.
			$info = proc_get_status($process);

			if (!$info['running'])
			{
				// Make sure the proccess finished successfully.
				if ($info['exitcode'] !== 0)
				{
					$response = new HTTPResponse(500);
				}
				else
				{
					$response = new HTTPResponse($this->status);

					$config  = $this->dispatcher->get_config();
					$headers = array('Content-Type'  => $this->_mime,
					                 'Date'          => date('D, d M Y H:i:s \G\M\T'),
					                 'Expires'       => date('D, d M Y H:i:s \G\M\T', time() + $config['CACHE_EXPIRATION']),
					                 'Last-Modified' => date('D, d M Y H:i:s \G\M\T', filemtime($this->_path)),
					                 'Cache-Control' => 'max-age=' . $config['CACHE_EXPIRATION']
					                 );
					$response->add_headers($headers);
				}

				if ($split_headers)
				{
					// Split the chunk into headers and content.
					list($headers, $content) = explode("\r\n\r\n", $this->_background_content, 2);

					// Set the headers.
					foreach (explode("\r\n", $headers) as $header)
					{
						list($h, $v) = explode(':', $header);
						$response->add_header($h, trim($v));
					}
				}
				else
				{
					$content = $this->_background_content;
				}

				// Add the body content.
				$response->set_body($content, false);
			}
		}

		if (isset($response) && $response instanceof HTTPResponse)
		{
			// Send the response.
			$this->write($response);

			// Remove the handler.
			$loop = $this->dispatcher->get_server()->get_loop();
			$loop->remove_handler($socket);

			// Close the sockets.
			if (is_resource($socket))
			{
				fclose($socket);
			}

			// Close the process.
			proc_close($process);

			// Clear the content.
			$this->_background_content = '';
		}
	}
}
?>