<?php
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