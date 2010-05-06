<?php
/**
 * A generic event class.
 */
abstract class Event
{
	/**
	 * The event data.
	 *
	 * @access protected
	 * @var    mixed
	 */
	protected $data;

	/**
	 * The timestamp of when the event was thrown.
	 *
	 * @access protected
	 * @var    float
	 */
	protected $timestamp;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param  mixed  $data
	 * @return void
	 */
	public function __construct($data = null)
	{
		$this->data      = $data;
		$this->timestamp = (string) microtime(true);
	}

	/**
	 * Returns the event's data.
	 *
	 * @access public
	 * @return void
	 */
	public function get_data()
	{
		return $this->data;
	}

	/**
	 * Returns the event's timestamp.
	 *
	 * @access public
	 * @return float
	 */
	public function get_timestamp()
	{
		return $this->timestamp;
	}
}
?>