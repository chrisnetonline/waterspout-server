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
	 * @param  array    $args
	 * @return integer
	 */
	public function add_handler($socket, $callback, $events, array $args = null)
	{
		if (!is_resource($socket))
		{
			// We cannot have a handler for something that isn't a resource.
			throw new RuntimeException('Non resource socket added to loop.');
		}

		// Make sure the additonal args are an array.
		if (!is_array($args))
		{
			$args = array();
		}

		$this->_handlers[(string) $socket] = array('socket'   => $socket,
		                                           'callback' => $callback,
		                                           'events'   => $events | self::ERROR,
		                                           'args'     => $args
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

		$this->_running = true;
		while (true)
		{
			// Run any timeouts that have been set.
			if (count($this->_timeouts))
			{
				$now = microtime(true);

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
					call_user_func_array($handler['callback'], array_merge(array($handler['socket'], self::ERROR), $handler['args']));
				}
				else
				{
					// Write first.
					if (in_array($handler['socket'], $write))
					{
						call_user_func_array($handler['callback'], array_merge(array($handler['socket'], self::WRITE), $handler['args']));
					}
					// Read second.
					if (in_array($handler['socket'], $read))
					{
						call_user_func_array($handler['callback'], array_merge(array($handler['socket'], self::READ), $handler['args']));
					}
				}
			}

			// Clean up memory and stuff like that.
			if (mt_rand(1, 20) == 1)
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

		$timeout   = new IOLoop_Timeout($deadline, $callback);
		$timeoutid = spl_object_hash($timeout);
		$this->_timeouts[$timeoutid] = $timeout;

		return $timeoutid;
	}

	/**
	 * Removes a timeout from the loop.
	 *
	 * @access public
	 * @param  string $timeoutid
	 * @return void
	 */
	public function remove_timeout($timeoutid)
	{
		unset($this->_timeouts[$timeoutid]);
	}
}
?>