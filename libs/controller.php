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
}
?>