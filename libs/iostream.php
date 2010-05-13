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

require_once _LIBS . DIRECTORY_SEPARATOR . 'ioloop.php';
/**
 * A utility class to read from and write to a non-blocking stream.
 *
 * @package Waterspout
 * @author  Scott Mattocks
 */
class IOStream
{
	/**
	 * Default buffer sizes.
	 *
	 * @const
	 */
	const MAX_BUFFER_SIZE = 104857600;
	const READ_CHUNK_SIZE = 4096;

	/**
	 * The stream for reading and writing.
	 *
	 * @access private
	 * @var    resource
	 */
	private $_socket;

	/**
	 * The IOLoop instance that handles callbacks for the stream.
	 *
	 * @access private
	 * @var    IOLoop
	 */
	private $_loop;

	/**
	 * The buffer for reading.
	 *
	 * @access private
	 * @var    string
	 */
	private $_read_buffer;

	/**
	 * The buffer for writing.
	 *
	 * @access private
	 * @var    string
	 */
	private $_write_buffer;

	/**
	 * The set of characters to read until.
	 *
	 * @access private
	 * @var    string
	 */
	private $_read_delimiter;

	/**
 	 * The number of bytes to read.
	 *
	 * @access private
	 * @var    integer
	 */
	private $_read_bytes;

	/**
	 * The callback to be called when reading is done.
	 *
	 * @access private
	 * @var    callback
	 */
	private $_read_callback;

	/**
	 * The callback to be called when writing is done.
	 *
	 * @access private
	 * @var    callback
	 */
	private $_write_callback;

	/**
	 * The callback to be called when the stream is closed.
	 *
	 * @access private
	 * @var    callback
	 */
	private $_close_callback;

	/**
	 * The state of the stream (reading, writing or error).
	 *
	 * @access private
	 * @var    integer
	 */
	private $_state;

	/**
 	 * The total number of bytes read so far.
	 *
	 * @access private
	 * @var    integer
	 */
	private $_bytes_read = 0;

	/**
	 * Constructor
	 *
	 * @access public
	 * @param  resource $socket
	 * @param  IOLoop   $io_loop
	 * @return void
	 */
	public function __construct($socket, IOLoop $io_loop)
	{
		stream_set_blocking($socket, false);

		$this->_socket = $socket;
		$this->_loop   = $io_loop;
		$this->_state  = IOLoop::ERROR;

		$this->_loop->add_handler($this->_socket,
		                          array($this, 'handle_events'),
		                          $this->_state
		                          );
	}

	/**
	 * Destructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __destruct()
	{
		$this->_loop->remove_handler($this->_socket);
		unset($this->_socket);
		unset($this->_read_callback);
		unset($this->_write_callback);
		unset($this->_close_callback);
	}

	/**
	 * Read until the specified set of characters is found.
	 *
	 * @access public
	 * @param  string   $delimiter
	 * @param  callback $callback
	 * @return void
	 */
	public function read_until($delimiter, $callback)
	{
		if ($this->reading())
		{
			throw new RuntimeException('The stream is already reading');
		}
		elseif (!is_callable($callback))
		{
			throw new RuntimeException('Read callback is not callable.');
		}

		$loc = strpos($this->_read_buffer, $delimiter);

		if ($loc !== false)
		{
			call_user_func($callback, $this->_consume($loc + strlen($delimiter)));

			return;
		}

		$this->_check_closed();
		$this->_read_delimiter = $delimiter;
		$this->_read_callback  = $callback;
		$this->_add_io_state(IOLoop::READ);
	}

	/**
	 * Read the specified number of bytes.
	 *
	 * @access public
	 * @param  integer  $num_bytes
	 * @param  callback $callback
	 * @return void
	 */
	public function read_bytes($num_bytes, $callback)
	{
		if ($this->reading())
		{
			throw new RuntimeException('Already reading');
		}
		elseif (!is_callable($callback))
		{
			throw new RuntimeException('Read callback is not callable.');
		}

		if (mb_strlen($this->_read_buffer, 'UTF-8') >= $num_bytes)
		{
			call_user_func($callback, $this->_consume($num_bytes));
			return;
		}

		$this->_check_closed();
		$this->_read_bytes    = $num_bytes;
		$this->_read_callback = $callback;
		$this->_add_io_state(IOLoop::READ);
	}

	/**
	 * Writes the given data to the stream.
	 *
	 * @access public
	 * @param  string   $data
	 * @param  callback $callback
	 * @return void
	 */
	public function write($data, $callback = null)
	{
		if (!is_null($callback) && !is_callable($callback))
		{
			throw new RuntimeException('Write callback is not callable.');
		}

		$this->_check_closed();
		$this->_write_buffer  .= $data;
		$this->_write_callback = $callback;
		$this->_add_io_state(IOLoop::WRITE);
	}

	/**
	 * Sets the callback to be called when the connection is closed.
	 *
	 * @access public
	 * @param  callback $callback
	 * @return void
	 */
	public function set_close_callback($callback)
	{
		if (!is_callable($callback))
		{
			throw new RuntimeException('Close callback is not callable.');
		}

		$this->_close_callback = $callback;
	}

	/**
	 * Closes the stream.
	 *
	 * @access public
	 * @return void
	 */
	public function close()
	{
		if (is_resource($this->_socket))
		{
			$this->_loop->remove_handler($this->_socket);
			stream_socket_shutdown($this->_socket, 2);
			$this->_socket = null;

			if (is_callable($this->_close_callback))
			{
				call_user_func($this->_close_callback);
			}
		}
	}

	/**
	 * Returns true if the stream is currently being read.
	 *
	 * @access public
	 * @return boolean
	 */
	public function reading()
	{
		return !empty($this->_read_callback);
	}

	/**
	 * Returns true if the data is being written to the stream.
	 *
	 * @access public
	 * @return boolean
	 */
	public function writing()
	{
		return strlen($this->_write_buffer) > 0;
	}

	/**
	 * Returns true if the stream is closed.
	 *
	 * @access public
	 * @return boolean
	 */
	public function closed()
	{
		return is_null($this->_socket);
	}

	/**
	 * Calls the event handler for whatever events have occured.
	 *
	 * @access public
	 * @param  integer $events
	 * @return void
	 */
	public function handle_events($socket, $events)
	{
		if (!is_resource($this->_socket))
		{
			return;
		}

		if ($events & IOLoop::ERROR)
		{
			$this->close();
			return;
		}

		if ($events & IOLoop::READ)
		{
			$this->_handle_read();
		}
		if (!is_resource($this->_socket))
		{
			return;
		}

		if ($events & IOLoop::WRITE)
		{
			$this->_handle_write();
		}

		if (!is_resource($this->_socket))
		{
			return;
		}

		$state = IOLoop::ERROR;
		if (!empty($this->_read_delimiter) || !empty($this->_read_bytes))
		{
			$state |= IOLoop::READ;
		}
		if (!empty($this->_write_buffer))
		{
			$state |= IOLoop::WRITE;
		}

		if ($state != $this->_state)
		{
			$this->_state = $state;
			$this->_loop->update_handler($this->_socket, $this->_state);
		}
	}

	/**
	 * Reads data from the stream.
	 *
	 * @access private
	 * @return void
	 */
	private function _handle_read()
	{
		$attempts = 0;

		while ($attempts++ <= 10)
		{
			$chunk = fread($this->_socket, self::READ_CHUNK_SIZE);

			if (!empty($chunk))
			{
				break;
			}
		}

		if (empty($chunk))
		{
			$this->_read_buffer   = null;
			$this->_read_callback = null;
			$this->close();
			return;
		}

		$this->_bytes_read+= strlen($chunk);

		$this->_read_buffer.= $chunk;

		if (mb_strlen($this->_read_buffer, 'UTF-8') >= self::MAX_BUFFER_SIZE)
		{
			$this->close();
			return;
		}

		if ($this->_read_bytes)
		{
			if (mb_strlen($this->_read_buffer, 'UTF-8') >= $this->_read_bytes)
			{
				$num_bytes            = $this->_read_bytes;
				$callback             = $this->_read_callback;
				$this->_read_callback = null;
				$this->_read_bytes    = null;
				call_user_func($callback, $this->_consume($num_bytes));
			}
		}
		elseif (!empty($this->_read_delimiter))
		{
			$loc = strpos($this->_read_buffer, $this->_read_delimiter);
			if ($loc !== false)
			{
				$callback              = $this->_read_callback;
				$delimiter_len         = strlen($this->_read_delimiter);
				$this->_read_callback  = null;
				$this->_read_delimiter = null;
				call_user_func($callback, $this->_consume($loc + $delimiter_len));
			}
		}
	}

	/**
	 * Writes data to the stream.
	 *
	 * @access private
	 * @return void
	 */
	private function _handle_write()
	{
		$attempts = 0;
		while (!empty($this->_write_buffer))
		{
			$num_bytes = @fwrite($this->_socket, $this->_write_buffer);

			if ($num_bytes === FALSE)
			{
				return;
			}
			elseif (empty($num_bytes))
			{
				// After 10000 tries, we give up.
				if ($attempts++ >= 10000)
				{
					break;
				}

				continue;
			}

			$attempts = 0;

			$leftover = substr($this->_write_buffer, $num_bytes);

			$this->_write_buffer = $leftover;
		}

		if (empty($this->_write_buffer) && is_callable($this->_write_callback))
		{
			$callback              = $this->_write_callback;
			$this->_write_callback = null;
			call_user_func($callback);
		}
	}

	/**
	 * Extracts a part of the read buffer.
	 *
	 * @access private
	 * @param  integer $loc
	 * @return string
	 */
	private function _consume($loc)
	{
		$result = mb_substr($this->_read_buffer, 0, $loc, 'UTF-8');
		$this->_read_buffer = mb_substr($this->_read_buffer, $loc, mb_strlen($this->_read_buffer, 'UTF-8'), 'UTF-8');
		return $result;
	}

	/**
	 * Checks to see if the connection is still open.
	 *
	 * @access private
	 * @return void
	 * @throws BadMethodCallException
	 */
	private function _check_closed()
	{
		if (empty($this->_socket))
		{
			throw new BadMethodCallException('Stream is closed');
		}
	}

	/**
	 * Adds a state to watch for to the stream.
	 *
	 * @access private
	 * @return void
	 */
	private function _add_io_state($state)
	{
		if (!($state & $this->_state))
		{
			$this->_state |= $state;
			$this->_loop->update_handler($this->_socket, $this->_state);
		}
	}

	/**
	 * Returns the total number of bytes read so far.
	 *
	 * @access public
	 * @return integer
	 */
	public function get_bytes_read()
	{
		return $this->_bytes_read;
	}
}
?>