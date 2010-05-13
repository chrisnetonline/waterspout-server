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
 * A class representing an IO loop timeout.
 *
 * @package Waterspout
 * @author  Scott Mattocks
 */
class IOLoop_Timeout
{
	/**
	 * The deadline for calling the callback.
	 *
	 * @access private
	 * @var    float
	 */
	public $_deadline;

	/**
 	 * The callback to be called at the deadline.
	 *
	 * @access private
	 * @var    callback
	 */
	public $_callback;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct($deadline, $callback)
	{
		if (!is_callable($callback))
		{
			throw new RuntimeException('Timeout callback is not callable.');
		}

		$this->_deadline = $deadline;
		$this->_callback = $callback;
	}

	/**
	 * Returns the deadline for running.
	 *
	 * @access public
	 * @return float
	 */
	public function get_deadline()
	{
		return $this->_deadline;
	}

	/**
	 * Returns the callback that is to be called at the deadline.
	 *
	 * @access public
	 * @return mixed
	 */
	public function get_callback()
	{
		return $this->_callback;
	}
}
?>