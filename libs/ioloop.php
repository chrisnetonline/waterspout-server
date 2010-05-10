<?php
/**
 * A level-triggered I/O loop for non-blocking sockets.
 *
 * @package Waterspout
 * @author  Scott Mattocks
 */
class IOLoop
{
	/**
	 * Stream event flags.
	 *
	 * @const
	 */
	const NONE  = 0;
	const READ  = 1;
	const WRITE = 4;
	const ERROR = 8216;

	/**
	 * Singleton instance.
	 *
	 * @static
	 * @access private
	 * @var    IOLoop
	 */
	private static $_instance;

	/**
	 * Socket callback handlers.
	 *
	 * @access private
	 * @var    array
	 */
	private $_handlers = array();

	/**
	 * Callbacks that happen at regular intervals.
	 *
	 * @access private
	 * @var    array
	 */
	private $_timeouts = array();

	/**
	 * Whether or not the loop is currently running.
	 *
	 * @access private
	 * @var    boolean
	 */
	private $_running = false;

	/**
	 * Creates a singleton instance (if needed) and returns it.
	 *
	 * @access public
	 * @return void
	 */
	public static function singleton()
	{
		if (empty(self::$_instance))
		{
			self::$_instance = new IOLoop();
		}

		return self::$_instance;
	}

	/**
	 * Adds a new event handler to the loop.
	 *
	 * @access public
	 * @param  resource $socket
	 * @param  callback $callback
	 * @param  integer  $events
	 * @return integer
	 */
	public function add_handler($socket, $callback, $events)
	{
		if (!is_resource($socket))
		{
			// We cannot have a handler for something that isn't a resource.
			throw new RuntimeException('Non resource socket added to loop.');
		}

		$this->_handlers[(string) $socket] = array('socket'   => $socket,
		                                           'callback' => $callback,
		                                           'events'   => $events | self::ERROR
		                                           );

		return count($this->_handlers);
	}

	/**
	 * Changes the events for which the handler listens.
	 *
	 * @access public
	 * @param  resource $socket
	 * @param  integer  $events
	 * @return void
	 */
	public function update_handler($socket, $events)
	{
		$this->_handlers[(string) $socket]['events'] = $events | self::ERROR;
	}

	/**
	 * Removes a handler from the loop.
	 *
	 * @access public
	 * @param  resource $socket
	 * @return void
	 */
	public function remove_handler($socket)
	{
		unset($this->_handlers[(string) $socket]);
	}

	/**
	 * Runs the loop.
	 *
	 * @access public
	 * @return void
	 */
	public function start()
	{
		static $check = 0;

		// Check to see if we can collect garbage.
		$gc_collect = function_exists('gc_collect_cycles');

		$this->_running = true;
		while (true)
		{
			$now = microtime(true);

			// Run any timeouts that have been set.
			if (count($this->_timeouts))
			{
				$timeouts = $this->_timeouts;
				foreach ($timeouts as $key => $timeout)
				{
					if ($timeout->get_deadline() <= $now)
					{
						call_user_func($timeout->get_callback());
						unset($this->_timeouts[$key]);
					}
				}
			}

			if (!$this->_running)
			{
				break;
			}

			if (empty($this->_handlers))
			{
				continue;
			}

			// Figure out which sockets are for reading and which are for writing.
			$read  = array();
			$write = array();
			$error = array();
			foreach ($this->_handlers as $handler)
			{
				if ($handler['events'] & self::READ)
				{
					$read[] = $handler['socket'];
				}
				if ($handler['events'] & self::WRITE)
				{
					$write[] = $handler['socket'];
				}
				if ($handler['events'] & self::ERROR)
				{
					$error[] = $handler['socket'];
				}
			}

			// See which sockets have data on them.
			$socks = stream_select($read, $write, $error, null, null);

			// Bail out if the select failed.
			if ($socks === false)
			{
				throw new RuntimeException('Socket select failed');
			}

			// Run handlers for sockets that are ready for some action.
			foreach ($this->_handlers as $handler)
			{
				if (in_array($handler['socket'], $error))
				{
					call_user_func($handler['callback'], $handler['socket'], self::ERROR);
				}
				else
				{
					if (in_array($handler['socket'], $read))
					{
						call_user_func($handler['callback'], $handler['socket'], self::READ);
					}
					if (in_array($handler['socket'], $write))
					{
						call_user_func($handler['callback'], $handler['socket'], self::WRITE);
					}
				}
			}

			// Clean up memory and stuff like that.
			if ($gc_collect && mt_rand(1, 20) == 1)
			{
				gc_collect_cycles();
			}
		}
	}

	/**
	 * Stops the loop.
	 *
	 * @access public
	 * @return void
	 */
	public function stop()
	{
		$this->_running = false;
	}

	/**
	 * Checks to see if the loop is running.
	 *
	 * @access public
	 * @return boolean
	 */
	public function running()
	{
		return $this->_running;
	}

	/**
	 * Adds a timeout to the loop
	 *
	 * @access public
	 * @param  float    $deadline
	 * @param  callback $callback
	 * @return void
	 */
	public function add_timeout($deadline, $callback)
	{
		require_once _LIBS . DIRECTORY_SEPARATOR . 'iolooptimeout.php';

		$timeout = new IOLoop_Timeout($deadline, $callback);
		$this->_timeouts[] = $timeout;
	}

	/**
	 * Removes a timeout from the loop.
	 *
	 * @access public
	 * @param  Timeout $timeout
	 * @return void
	 */
	public function remove_timeout(Timeout $timeout)
	{
		unset($this->_timeouts[array_search($timeout, $this->_timeouts)]);
	}
}
?>