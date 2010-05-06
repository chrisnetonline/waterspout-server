<?php
class Alert_Handler extends Handler
{
	/**
	 * The maximum number of alerts to store on the alert stack.
	 *
	 * @const
	 */
	const MAX_ALERT_STACK_SIZE = 100;

	/**
	 * A queue of alert events that have occurred.
	 *
	 * @static
	 * @access public
	 * @var    array
	 */
	static public $alerts = array();

	/**
	 * The current cursor position for this listener.
	 *
	 * @access protected
	 * @var    float
	 */
	protected $cursor;

	/**
	 * The user ID of the connected user. This comes from a cookie set by the main
	 * server.
	 *
	 * @access private
	 * @var    integer
	 */
	private $_userid;

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
		// Call the parent constructor.
		parent::__construct($request, $dispatcher);
	}

	/**
	 * Adds a new alert to the queue.
	 *
	 * @access public
	 * @return void
	 */
	public function add()
	{
		$alerts = $this->request->get_request_var('alerts');
		if (empty($alerts))
		{
			return;
		}

		$response = new HTTPResponse(200);
		$response->set_body('OK', true);

		// Write back the message. We do this first to close the connection and keep
		// things moving along.
		$this->write($response);

		foreach (@unserialize($alerts) as $alert)
		{
			// Create an event and push it onto the stack.
			self::$alerts[] = $alert;
		}
	}

	/**
	 * Listens for updates from other connections.
	 *
	 * @access public
	 * @return void
	 */
	public function updates()
	{
		// Get the user data from the cookie.
		$data = $this->request->get_request_var('waterspout_cookie');
		$data = unserialize(base64_decode($data));

		// Make sure we have real data.
		if (empty($data) || !is_array($data) || empty($data['uid']))
		{
			$response = new HTTPResponse(403);
			$response->set_body('Invalid user');
			$this->write($response);
		}
		else
		{
			$this->_userid = $data['uid'];
		}

		// If a cursor was passed in, make that our new cursor.
		$request_cursor = $this->request->get_request_var('waterspout_cursor');
		if (empty($this->cursor) && !empty($request_cursor) && $request_cursor <= (end(array_keys(self::$alerts)) + 1))
		{
			$this->cursor = $request_cursor;
		}
		// If the server doesn't have any alerts in the stack yet, start the
		// cursor out at 0.
		elseif (!count(self::$alerts))
		{
			$this->cursor = 0;
		}
		// If no cursor was passed in, then figure it out.
		elseif (is_null($this->cursor))
		{
			$this->cursor = (int) end(array_keys(self::$alerts)) + 1;
		}

		$this->dispatcher->add_listener($this);

		if (!empty(self::$alerts) &&
		    !is_null($this->cursor) &&
		    $this->cursor < end(array_keys(self::$alerts))
		    )
		{
			$this->process_event();
		}
	}

	/**
	 * Processes the given event.
	 *
	 * @access public
	 * @return void
	 */
	public function process_event(Handler $handler = null)
	{
		$messages = array_slice(self::$alerts, (int) $this->cursor);

		// Filter the messages for those that this user is allowed to see.
		$alerts = array();
		foreach ($messages as $alert)
		{
			if ($alert->user_cares($this->_userid))
			{
				// Clear some data.
				$alert = clone $alert;
				unset($alert->whocares);
				$alerts[] = $alert;
			}
		}

		if (empty($alerts))
		{
			return;
		}

		$response = new HTTPResponse(200);
		$body     = array('messages' => $alerts,
		                  'cursor'   => end(array_keys(self::$alerts)) + 1
		                  );

		$response->set_body($body);

		$this->write($response);

		$this->cursor = (int) end(array_keys(self::$alerts)) + 1;
	}
}

class rt_alert implements Serializable
{
	/**
	 * The alert ID.
	 *
	 * @access public
	 * @var    integer
	 */
	public $id;

	/**
	 * The event that triggered the alert.
	 *
	 * @access public
	 * @var    event
	 */
	public $event;

	/**
	 * The date and time the alert was triggered.
	 *
	 * @access public
	 * @var    datetriggered
	 */
	public $datetriggered;

	/**
	 * The work order ID for the alert.
	 *
	 * @access public
	 * @var    string
	 */
	public $workorderid;

	/**
	 * A description of the alert.
	 *
	 * @access public
	 * @var    string
	 */
	public $description = '';

	/**
	 * The name of the category.
	 *
	 * @access public
	 * @var    string
	 */
	public $category = 'management';

	/**
	 * True if the alert can be acknowledged away.
	 *
	 * @access public
	 * @var    boolean
	 */
	public $acknowledgable = true;

	/**
 	 * An array of users that might care about this alert.
	 *
	 * @access public
	 * @var    array
	 */
	public $whocares = array();

	/**
	 * The resolution path.
	 *
	 * @access public
	 * @var    string
	 */
	public $resolution;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
	}

	/**
	 * Returns the alert ID.
	 *
	 * @access public
	 * @return integer
	 */
	public function get_id()
	{
		return $this->id;
	}

	/**
	 * Returns the alert type.
	 *
	 * @access public
	 * @return string
	 */
	public function get_type()
	{
		return get_class($this);
	}

	/**
	 * Returns true if the user cares about this alert.
	 *
	 * @access public
	 * @param  integer $uid
	 * @return boolean
	 */
	public function user_cares($uid)
	{
		return in_array($uid, $this->whocares);
	}

	/**
	 * Serializes the data for this alert.
	 *
	 * @access public
	 * @return string
	 */
	public function serialize()
	{
		$data = array();

		foreach ($this as $key => $value)
		{
			$data[$key] = $value;
		}

		return serialize($data);
	}

	/**
	 * Unserializes the object.
	 *
	 * @access public
	 * @param  string $seralized
	 * @return void
	 */
	public function unserialize($serialized)
	{
		$data = unserialize($serialized);

		foreach ($data as $key => $value)
		{
			$this->$key = $value;
		}
	}
}
?>